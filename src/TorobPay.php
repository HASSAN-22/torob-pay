<?php

namespace TorobPay;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use TorobPay\Tools\Tools;

/**
 * Provides the payment lifecycle integration for the Torob Pay API.
 *
 * The class builds cart data, requests payment tokens, verifies and settles
 * successful payments, and cancels eligible payments. API credentials and
 * the endpoint URL are read from the `torobpay` Laravel configuration, while
 * payment tokens and their local statuses are stored in the package table.
 *
 * Cart information is held on the instance between method calls and is not
 * cleared automatically. Use a fresh instance for each independent payment;
 * a long-lived singleton can otherwise retain items from earlier requests.
 */
class TorobPay extends Tools
{
    private const STATUS_PENDING = 'pending';

    private const STATUS_FAILED = 'failed';

    private const STATUS_COMPLETED = 'completed';

    private const STATUS_CANCELED = 'canceled';

    private const REQUEST_TIMEOUT = 20;

    private ?string $token = null;

    private array $cartItems = [];

    private array $cartList = [];

    public function __construct()
    {
        $this->token = $this->createBasicToken();
    }

    /**
     * Append a product to the cart being prepared for a payment request.
     *
     * The item is included in the cart list the next time {@see addList()} is
     * called.
     *
     * @param  int  $id  The merchant's product identifier.
     * @param  string  $name  The product name sent to Torob Pay.
     * @param  int  $count  The number of units in the cart.
     * @param  int  $amount  The product amount expected by the Torob Pay API.
     * @param  string  $category  The product category; defaults to `other`.
     * @param  string  $commissionType  The commission type; defaults to `0`.
     * @return static The current instance for fluent cart configuration.
     */
    public function addCartItem(
        int $id,
        string $name,
        int $count,
        int $amount,
        string $category = 'other',
        string $commissionType = '0'
    ): static {
        $this->cartItems[] = [
            'id' => $id,
            'name' => $name,
            'count' => $count,
            'amount' => $amount,
            'category' => $category,
            'commissionType' => $commissionType,
        ];

        return $this;
    }

    /**
     * Build the cart summary that will be attached to the payment request.
     *
     * This snapshots the items currently added with {@see addCartItem()}.
     *
     * @param  string  $cartId  The merchant's cart identifier.
     * @param  int  $amount  The total cart amount expected by the Torob Pay API.
     * @param  int  $tax  The tax amount for the cart.
     * @param  int  $shippingAmount  The shipping amount for the cart.
     * @param  bool  $isTaxIncluded  Whether the total amount already includes tax.
     * @param  bool  $isShipmentIncluded  Whether the total amount already includes shipping.
     * @return static The current instance for fluent cart configuration.
     */
    public function addList(
        string $cartId,
        int $amount,
        int $tax,
        int $shippingAmount,
        bool $isTaxIncluded = false,
        bool $isShipmentIncluded = false
    ): static {
        $this->cartList = [
            'cartId' => $cartId,
            'totalAmount' => $amount,
            'taxAmount' => $tax,
            'shippingAmount' => $shippingAmount,
            'isTaxIncluded' => $isTaxIncluded,
            'isShipmentIncluded' => $isShipmentIncluded,
            'cartItems' => $this->cartItems,
        ];

        return $this;
    }

    /**
     * Create a Torob Pay payment request and persist its pending payment token.
     *
     * A cart must first be configured with {@see addList()}. On success, the
     * returned `data.url` value is the payment page URL to which the customer
     * should be redirected. The callback URL should lead to an application
     * endpoint that calls {@see verify()} with the gateway response.
     *
     * @param  int  $amount  The payable amount expected by the Torob Pay API; must be positive.
     * @param  int  $orderId  The merchant's order identifier.
     * @param  string  $mobile  The customer's mobile number.
     * @param  string  $callback  The URL to which Torob Pay returns the customer.
     * @param  string  $paymentMethod  The payment method identifier; defaults to `ONLINE_CREDIT`.
     * @param  string|null  $address  The customer's address, if available.
     * @param  string|null  $postalCode  The customer's postal code, if available.
     * @param  string|null  $fullName  The customer's full name, if available.
     * @param  string|null  $province  The customer's province, if available.
     * @param  string|null  $city  The customer's city, if available.
     * @return array{status: 'success', data: array{url: string, payment_token: string, order_id: int, transaction_id: string}}|array{status: 'error', data: array{error: string, data: mixed}} A payment-page URL and identifiers, or a structured error.
     */
    public function request(
        int $amount,
        int $orderId,
        string $mobile,
        string $callback,
        string $paymentMethod = 'ONLINE_CREDIT',
        ?string $address = null,
        ?string $postalCode = null,
        ?string $fullName = null,
        ?string $province = null,
        ?string $city = null
    ): array {
        if ($amount <= 0) {
            return $this->error('مبلغ پرداخت باید بیشتر از صفر باشد.');
        }

        if (empty($this->cartList)) {
            return $this->error('اطلاعات سبد خرید تنظیم نشده است.');
        }

        try {
            $tokenResponse = $this->getToken(
                '/api/online/v1/oauth/token'
            );

            if (! $tokenResponse->successful()) {
                return $this->httpError($tokenResponse);
            }

            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;

            if (! is_string($accessToken) || $accessToken === '') {
                return $this->error(
                    'توکن ترب‌پی دریافت نشد.',
                    $tokenData
                );
            }

            $transactionId = $this->orderId($orderId);

            $orderData = [
                'amount' => $amount,
                'paymentMethodTypeDto' => $paymentMethod,
                'returnURL' => $callback,
                'transactionId' => $transactionId,
                'mobile' => $mobile,
                'address' => $address ?? '',
                'postalCode' => $postalCode ?? '',
                'name_full_customer' => $fullName ?? '',
                'city' => $city ?? '',
                'province' => $province ?? '',
                'number_phone_registration' => $mobile,
                'cartList' => [$this->cartList],
            ];

            $paymentResponse = Http::timeout(self::REQUEST_TIMEOUT)
                ->withToken($accessToken)
                ->post(
                    $this->apiUrl('/api/online/payment/v1/token'),
                    $orderData
                );

            if (! $paymentResponse->successful()) {
                return $this->httpError($paymentResponse);
            }

            $paymentData = $paymentResponse->json();

            if (($paymentData['successful'] ?? false) !== true) {
                return $this->error(
                    'ساخت پرداخت ترب‌پی ناموفق بود.',
                    $paymentData
                );
            }

            $paymentToken = $paymentData['response']['paymentToken'] ?? null;
            $paymentPageUrl = $paymentData['response']['paymentPageUrl'] ?? null;

            if (
                ! is_string($paymentToken) ||
                $paymentToken === '' ||
                ! is_string($paymentPageUrl) ||
                $paymentPageUrl === ''
            ) {
                return $this->error(
                    'اطلاعات پرداخت ترب‌پی ناقص است.',
                    $paymentData
                );
            }

            $saved = $this->addToDb(
                $paymentToken,
                $accessToken,
                $transactionId,
                self::STATUS_PENDING
            );

            if (($saved['status'] ?? null) !== 'success') {
                return $saved;
            }

            return [
                'status' => 'success',
                'data' => [
                    'url' => $paymentPageUrl,
                    'payment_token' => $paymentToken,
                    'order_id' => $orderId,
                    'transaction_id' => $transactionId,
                ],
            ];
        } catch (ConnectionException $e) {
            Log::warning('TorobPay connection failed.', [
                'message' => $e->getMessage(),
            ]);

            return $this->error(
                'ارتباط با سرویس ترب‌پی برقرار نشد.'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->error(
                'خطای داخلی هنگام ایجاد پرداخت.'
            );
        }
    }

    /**
     * Verify a gateway callback and settle its pending payment.
     *
     * The callback must contain `state` equal to `OK` and a non-empty
     * `transactionId`. The method looks up the locally stored payment, checks
     * the gateway, settles the payment, and marks the local record completed.
     * A successful result should still be reconciled with the merchant's own
     * order and expected amount before fulfillment.
     *
     * @param  array<string, mixed>  $response  The callback parameters received from Torob Pay.
     * @return array{status: 'success', data: array{order_id: string, payment_token: string, amount: mixed, transaction_id: mixed}}|array{status: 'error', data: array{error: string, data: mixed}} The settlement result or a structured error.
     */
    public function verify(array $response): array
    {
        if (($response['state'] ?? null) !== 'OK') {
            return $this->error(
                'پرداخت ناموفق است.',
                $response
            );
        }

        $transactionId = $response['transactionId'] ?? null;

        if (! is_string($transactionId) || $transactionId === '') {
            return $this->error(
                'شناسه تراکنش در پاسخ پرداخت وجود ندارد.'
            );
        }

        try {
            $result = $this->getRecord($transactionId);

            if (($result['status'] ?? null) !== 'success') {
                return $result;
            }

            $record = $result['data'];

            $orderId = explode("_", $record->order_id)[1] ?? null;

            if ($record->status === self::STATUS_COMPLETED) {
                return $this->error(
                    'این پرداخت قبلاً تأیید شده است.'
                );
            }

            if ($record->status !== self::STATUS_PENDING) {
                return $this->error(
                    'وضعیت این پرداخت قابل تأیید نیست.'
                );
            }

            $paymentToken = $record->payment_token;
            $accessToken = $record->access_token;

            $verifyResponse = Http::timeout(self::REQUEST_TIMEOUT)
                ->withToken($accessToken)
                ->post(
                    $this->apiUrl('/api/online/payment/v1/verify'),
                    [
                        'paymentToken' => $paymentToken,
                    ]
                );

            if (! $verifyResponse->successful()) {
                return $this->httpError($verifyResponse);
            }

            $verifyData = $verifyResponse->json();

            if (($verifyData['successful'] ?? false) !== true) {
                $this->changeRecordStatus(
                    $transactionId,
                    self::STATUS_FAILED
                );

                return $this->error(
                    'تأیید پرداخت ناموفق بود.',
                    $verifyData
                );
            }

            $settleResponse = Http::timeout(self::REQUEST_TIMEOUT)
                ->withToken($accessToken)
                ->post(
                    $this->apiUrl('/api/online/payment/v1/settle'),
                    [
                        'paymentToken' => $paymentToken,
                    ]
                );

            if (! $settleResponse->successful()) {
                Log::warning(
                    'TorobPay settlement result is uncertain.',
                    [
                        'order_id' => $orderId,
                        'http_status' => $settleResponse->status(),
                    ]
                );

                return $this->error(
                    'نتیجه تسویه مشخص نیست؛ وضعیت پرداخت باید بررسی شود.',
                    $settleResponse->json()
                );
            }

            $settleData = $settleResponse->json();

            if (($settleData['successful'] ?? false) !== true) {
                return $this->error(
                    'تسویه پرداخت تأیید نشد؛ وضعیت را بررسی کنید.',
                    $settleData
                );
            }

            $statusResult = $this->changeRecordStatus(
                $transactionId,
                self::STATUS_COMPLETED
            );

            if (($statusResult['status'] ?? null) !== 'success') {
                Log::error(
                    'TorobPay settled successfully but local status update failed.',
                    [
                        'order_id' => $orderId,
                    ]
                );

                return $this->error(
                    'پرداخت تسویه شده، اما ثبت وضعیت محلی ناموفق بود.'
                );
            }

            return [
                'status' => 'success',
                'data' => [
                    'order_id' => $orderId,
                    'payment_token' => $paymentToken,
                    'amount' => $response['amount'] ?? null,
                    'transaction_id' => $settleData['response']['transactionId'] ?? null,
                ],
            ];
        } catch (ConnectionException $e) {
            Log::warning('TorobPay verification connection failed.', [
                'order_id' => $transactionId,
                'message' => $e->getMessage(),
            ]);

            return $this->error(
                'ارتباط هنگام تأیید پرداخت قطع شد؛ وضعیت را بررسی کنید.'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->error(
                'خطای داخلی هنگام تأیید پرداخت.'
            );
        }
    }

    /**
     * Cancel a payment by its payment token and update its local status.
     *
     * The current implementation permits cancellation only when the local
     * payment status is `completed`.
     *
     * @param  string  $paymentToken  The Torob Pay payment token.
     * @return array{status: 'success', data: array{order_id: string}}|array{status: 'error', data: array{error: string, data: mixed}} The affected order identifier or a structured error.
     */
    public function cancel(string $paymentToken): array
    {
        try {
            $result = DB::table('torobpay_payment_tokens')
                ->where('payment_token', $paymentToken)
                ->first();

            if (! $result) {
                return $this->error(
                    'اطلاعات پرداخت پیدا نشد.'
                );
            }

            if ($result->status !== self::STATUS_COMPLETED) {
                return $this->error(
                    'وضعیت این پرداخت قابل لغو نیست.'
                );
            }

            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->withToken($result->access_token)
                ->post(
                    $this->apiUrl('/api/online/payment/v1/cancel'),
                    [
                        'paymentToken' => $paymentToken,
                    ]
                );

            if (! $response->successful()) {
                return $this->httpError($response);
            }

            $data = $response->json();

            if (($data['successful'] ?? false) !== true) {
                return $this->error(
                    'لغو پرداخت تأیید نشد.',
                    $data
                );
            }

            $statusResult = $this->changeRecordStatus(
                $result->order_id,
                self::STATUS_CANCELED
            );

            if (($statusResult['status'] ?? null) !== 'success') {
                return $this->error(
                    'پرداخت لغو شده، اما تغییر وضعیت محلی ناموفق بود.'
                );
            }

            return [
                'status' => 'success',
                'data' => [
                    'order_id' => $result->order_id,
                ],
            ];
        } catch (ConnectionException $e) {
            Log::warning('TorobPay cancellation connection failed.', [
                'message' => $e->getMessage(),
            ]);

            return $this->error(
                'ارتباط با سرویس ترب‌پی برقرار نشد.'
            );
        } catch (Throwable $e) {
            report($e);

            return $this->error(
                'خطای داخلی هنگام لغو پرداخت.'
            );
        }
    }

    /**
     * Request an OAuth access token using the configured Basic credentials.
     *
     * @param  string  $path  The token endpoint path.
     * @return Response The raw Laravel HTTP response from the token endpoint.
     */
    private function getToken(string $path): Response
    {
        return Http::timeout(self::REQUEST_TIMEOUT)
            ->withHeaders([
                'Authorization' => 'Basic '.$this->token,
                'Accept' => 'application/json',
            ])
            ->post(
                $this->apiUrl($path),
                [
                    'username' => config('torobpay.username'),
                    'password' => config('torobpay.password'),
                ]
            );
    }

    /**
     * Encode the configured client credentials for HTTP Basic authorization.
     *
     * @return string The Base64-encoded client identifier and secret.
     */
    private function createBasicToken(): string
    {
        return base64_encode(
            (string) config('torobpay.client_id').':'.
            (string) config('torobpay.client_secret')
        );
    }

    /**
     * Build an absolute API URL from the configured base URL and a path.
     *
     * @param  string  $path  The API endpoint path.
     * @return string The normalized absolute API URL.
     */
    private function apiUrl(string $path): string
    {
        return rtrim((string) config('torobpay.url'), '/')
            .'/'
            .ltrim($path, '/');
    }

    /**
     * Create the structured error response returned by public operations.
     *
     * @param  string  $message  A human-readable error message.
     * @param  mixed  $data  Optional response or diagnostic data.
     * @return array{status: 'error', data: array{error: string, data: mixed}} The normalized error result.
     */
    private function error(
        string $message,
        mixed $data = null
    ): array {
        return [
            'status' => 'error',
            'data' => [
                'error' => $message,
                'data' => $data,
            ],
        ];
    }

    /**
     * Convert an unsuccessful gateway response into the package error format.
     *
     * @param  Response  $response  The unsuccessful HTTP response.
     * @return array{status: 'error', data: array{error: string, data: mixed}} The normalized error result.
     */
    private function httpError(Response $response): array
    {
        return $this->error(
            (string) (
                $response->json('error.code')
                ?? $response->status()
            ),
            $response->json()
        );
    }
}
