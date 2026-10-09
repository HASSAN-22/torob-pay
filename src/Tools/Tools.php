<?php

namespace TorobPay\Tools;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Tools{

    protected function orderId(int $orderId){
        return last(explode('.', explode(' ',microtime())[0])) . "_" . $orderId;
    }

    protected function addToDb(string $paymentToken, string $accessToken, string $orderId, string $status){
        try {

            $result = DB::table('torobpay_payment_tokens')->insert([
                'payment_token' => $paymentToken,
                'access_token'  => $accessToken,
                'order_id'      => $orderId,
                'status'        => $status,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            if (!$result) {
                return [
                    'status' => 'error',
                    'data' => [
                        'error' => 'ذخیره دیتای پرداخت با خطا مواجه شد.',
                        'data' => '',
                    ],
                ];
            }
            return [
                'status' => 'success'
            ];
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);

            return [
                'status' => 'error',
                'data' => [
                    'error' => 'ذخیره دیتای پرداخت با خطا مواجه شد.',
                    'data' => '',
                ],
            ];
        }
    } 

    protected function getRecord(string $orderId): object|array
    {
        $result = DB::table('torobpay_payment_tokens')
            ->where('order_id', $orderId)
            ->first();

        if ($result) {
            return [
                'status' => 'success',
                'data' => $result
            ];
        }

        return [
            'status' => 'error',
            'data' => [
                'error' => 'خطا در دریافت payment_token',
                'data' => '',
            ],
        ];
    }

    protected function changeRecordStatus(string $orderId, string $status){
        try{
            DB::table('torobpay_payment_tokens')
            ->where('order_id', $orderId)
            ->update(['status'=>$status]);

            return ['status'=>'success'];
        }catch(Exception $e){
            Log::error("Change status for [torobpay_payment_tokens]: ", [
                'status' => 'error',
                'data' => [
                    'error' => 'خطا در تغییر وضعیت payment_token',
                    'data' => '',
                ],
            ]);
        }
    }
}