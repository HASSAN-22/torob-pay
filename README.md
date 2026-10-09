<div align="center" dir="rtl">
  <h1>💳 درگاه پرداخت ترب‌پی برای Laravel</h1>
  <p>یکپارچه‌سازی ساده برای ساخت، تأیید، تسویه و لغو پرداخت‌های ترب‌پی در پروژه‌های Laravel</p>
  <p dir="ltr">
    <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+"></a>
    <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/License-MIT-22c55e.svg" alt="MIT License"></a>
    <img src="https://img.shields.io/badge/Docs-Persian-0891b2" alt="Persian documentation">
  </p>
  <p dir="ltr">
    <a href="#quick-start">شروع سریع</a> ·
    <a href="#configuration">پیکربندی</a> ·
    <a href="#payment-flow">جریان پرداخت</a> ·
    <a href="#api-reference">مرجع API</a> ·
    <a href="#security">امنیت</a>
  </p>
</div>

## ✨ امکانات

<p dir="rtl" align="right">این بسته امکانات موردنیاز برای اتصال فروشگاه Laravel به ترب‌پی را فراهم می‌کند. برای ارتباط HTTP از Laravel HTTP Client و برای نگهداری وضعیت تراکنش از پایگاه‌داده برنامه استفاده می‌شود.</p>

<table dir="rtl" align="right">
  <tr>
    <td align="right"><strong>🛒 ساخت پرداخت</strong><br>ارسال جزئیات مشتری و سبد خرید به ترب‌پی</td>
    <td align="right"><strong>✅ تأیید و تسویه</strong><br>بررسی سمت سرور و ثبت نتیجه پرداخت</td>
    <td align="right"><strong>↩️ لغو پرداخت</strong><br>لغو از طریق API و به‌روزرسانی وضعیت محلی</td>
  </tr>
</table>

### پیش‌نیازها

<ul dir="rtl" align="right">
  <li>PHP نسخه <code>8.2</code> یا بالاتر.</li>
  <li>برنامه Laravel با پایگاه‌داده پیکربندی‌شده.</li>
  <li>دسترسی شبکه سرور به API ترب‌پی و اعتبارنامه‌های معتبر درگاه.</li>
</ul>

## 🚀 نصب و شروع سریع

<a id="quick-start"></a>

### نصب بسته

```bash
composer require hasan-22/torob_pay
```

<p dir="rtl" align="right">Laravel به‌صورت خودکار Service Provider را کشف می‌کند. فایل تنظیمات را در صورت نیاز منتشر کنید و migration بسته را اجرا کنید:</p>

```bash
php artisan vendor:publish --tag=torobpay-config
php artisan migrate
```

<blockquote dir="rtl" align="right">در نصب محلی، بسته را به‌عنوان repository از نوع <code>path</code> در Composer برنامه میزبان معرفی کنید. namespace کلاس‌های بسته <code>TorobPay\</code> است.</blockquote>

## ⚙️ پیکربندی

<a id="configuration"></a>

<p dir="rtl" align="right">مقادیر دسترسی را در فایل <code>.env</code> برنامه Laravel تنظیم کنید:</p>

<table dir="rtl" align="right">
  <thead>
    <tr><th align="right">متغیر محیطی</th><th align="right">کلید تنظیمات</th><th align="right">پیش‌فرض</th><th align="right">توضیح</th></tr>
  </thead>
  <tbody>
    <tr><td><code>TOROBPAY_URL</code></td><td><code>torobpay.url</code></td><td><code>https://cpg.torobpay.com</code></td><td>آدرس پایه API ترب‌پی</td></tr>
    <tr><td><code>TOROBPAY_CLIENT_ID</code></td><td><code>torobpay.client_id</code></td><td>خالی</td><td>شناسه کلاینت برای Basic Authorization</td></tr>
    <tr><td><code>TOROBPAY_CLIENT_SECRET</code></td><td><code>torobpay.client_secret</code></td><td>خالی</td><td>رمز کلاینت برای Basic Authorization</td></tr>
    <tr><td><code>TOROBPAY_USERNAME</code></td><td><code>torobpay.username</code></td><td>خالی</td><td>نام کاربری endpoint دریافت توکن</td></tr>
    <tr><td><code>TOROBPAY_PASSWORD</code></td><td><code>torobpay.password</code></td><td>خالی</td><td>رمز عبور endpoint دریافت توکن</td></tr>
  </tbody>
</table>

<p dir="rtl" align="right">فایل تنظیمات منتشرشده در <code>config/torobpay.php</code> قرار می‌گیرد. اگر config cache فعال است، پس از تغییر <code>.env</code> آن را مطابق فرایند استقرار برنامه بازسازی کنید.</p>

<blockquote dir="rtl" align="right">🔐 <strong>مهم:</strong> اعتبارنامه‌ها را در مخزن کد، log یا پاسخ HTTP قرار ندهید. از متغیرهای محیطی امن استفاده کنید.</blockquote>

## 🔄 جریان پرداخت

<a id="payment-flow"></a>

<ol dir="rtl" align="right">
  <li>اقلام سبد را با <code>addCartItem()</code> اضافه کنید.</li>
  <li>اطلاعات کلی سبد را با <code>addList()</code> تنظیم کنید.</li>
  <li>با <code>request()</code> پرداخت را ایجاد و کاربر را به مقدار <code>data.url</code> هدایت کنید.</li>
  <li>پارامترهای برگشتی در callback را به <code>verify()</code> بدهید.</li>
  <li>پس از موفقیت، سفارش و مبلغ را با اطلاعات داخلی فروشگاه تطبیق دهید؛ سپس سفارش را نهایی کنید.</li>
</ol>

### ایجاد پرداخت

```php
use TorobPay\Facades\TorobPay;

// اقلام را از سفارش معتبر سمت سرور تهیه کنید.
$result = TorobPay::addCartItem(
    id: 123,
    name: 'محصول نمونه',
    count: 1,
    amount: 200000,
    category: 'general',
)->addCartItem(
    id: 456,
    name: 'محصول نمونه ۲',
  count: 1,
    amount: 500000,
    category: 'general',
)->addList(
    cartId: 'cart-456',
    amount: 700000,
    tax: 0,
    shippingAmount: 0,
)->request(
    amount: 700000,
    orderId: 456,
    mobile: '09120000000',
    callback: route('torobpay.callback'),
);

if ($result['status'] !== 'success') {
    return back()->withErrors(['payment' => 'ایجاد درخواست پرداخت ناموفق بود.']);
}

return redirect()->away($result['data']['url']);
```

### دریافت callback و تأیید پرداخت

<p dir="rtl" align="right">Route مربوط به callback را در برنامه میزبان تعریف کنید. منطق ثبت و نهایی‌سازی سفارش باید با مدل‌ها و قواعد برنامه شما تکمیل شود:</p>

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use TorobPay\Facades\TorobPay;

Route::match(['get', 'post'], '/payments/torobpay/callback', function (Request $request) {
    $result = TorobPay::verify($request->all());

    if ($result['status'] !== 'success') {
        return response()->json(['message' => 'تأیید پرداخت ناموفق بود.'], 422);
    }

    $orderId = $result['data']['order_id'];
    $amount = $result['data']['amount'];

    // سفارش را از پایگاه‌داده خود بارگذاری کنید و مبلغ/مالکیت آن را تطبیق دهید.
    // سپس آن را به‌صورت idempotent نهایی کنید.

    return response()->json(['message' => 'پرداخت با موفقیت تأیید شد.']);
})->name('torobpay.callback');
```

<blockquote dir="rtl" align="right"><strong>نکته:</strong> callback به‌تنهایی مدرک پرداخت موفق نیست. <code>verify()</code> پرداخت را از سمت سرور بررسی می‌کند؛ مبلغ، مالکیت و وضعیت سفارش را نیز با داده‌های داخلی خود تطبیق دهید.</blockquote>

## 📚 مرجع API

<a id="api-reference"></a>

### <code>addCartItem(int $id, string $name, int $count, int $amount, string $category = 'other', string $commissionType = '0'): static</code>

<p dir="rtl" align="right">یک محصول به سبد جاری اضافه می‌کند و همان نمونه را برای زنجیره‌کردن فراخوانی‌ها برمی‌گرداند.</p>

<ul dir="rtl" align="right">
  <li><code>id</code>: شناسه محصول در فروشگاه.</li>
  <li><code>name</code>: نام محصول.</li>
  <li><code>count</code>: تعداد واحدها.</li>
  <li><code>amount</code>: مبلغ محصول با واحد مورد انتظار API ترب‌پی.</li>
  <li><code>category</code>: دسته محصول؛ پیش‌فرض <code>other</code>.</li>
  <li><code>commissionType</code>: نوع کمیسیون؛ پیش‌فرض رشته <code>0</code>.</li>
</ul>

### <code>addList(string $cartId, int $amount, int $tax, int $shippingAmount, bool $isTaxIncluded = false, bool $isShipmentIncluded = false): static</code>

<p dir="rtl" align="right">خلاصه سبد را از اقلامی که تا آن لحظه با <code>addCartItem()</code> افزوده شده‌اند می‌سازد.</p>

<ul dir="rtl" align="right">
  <li><code>cartId</code>: شناسه سبد فروشگاه.</li>
  <li><code>amount</code>: مبلغ کل سبد.</li>
  <li><code>tax</code>: مبلغ مالیات.</li>
  <li><code>shippingAmount</code>: مبلغ ارسال.</li>
  <li><code>isTaxIncluded</code> و <code>isShipmentIncluded</code>: مشخص می‌کنند مالیات و هزینه ارسال در مبلغ کل لحاظ شده‌اند یا خیر.</li>
</ul>

<p dir="rtl" align="right">این متد را پس از افزودن اقلام و پیش از <code>request()</code> فراخوانی کنید. بسته مبلغ را تبدیل نمی‌کند؛ واحد مبلغ باید با قرارداد حساب ترب‌پی و مبالغ برنامه شما یکسان باشد.</p>

### <code>request(int $amount, int $orderId, string $mobile, string $callback, string $paymentMethod = 'ONLINE_CREDIT', ?string $address = null, ?string $postalCode = null, ?string $fullName = null, ?string $province = null, ?string $city = null): array</code>

<p dir="rtl" align="right">درخواست پرداخت می‌سازد. مبلغ باید مثبت باشد و سبد باید پیش‌تر با <code>addList()</code> تنظیم شده باشد. در صورت موفقیت، توکن پرداخت و توکن دسترسی ذخیره و وضعیت اولیه <code>pending</code> ثبت می‌شود. مقادیر اختیاری مشتری که <code>null</code> باشند به رشته خالی تبدیل می‌شوند.</p>

### <code>verify(array $response): array</code>

<p dir="rtl" align="right">callback را بررسی و پرداخت pending را از طریق API ترب‌پی verify و settle می‌کند. callback باید <code>state</code> برابر <code>OK</code> و <code>transactionId</code> غیرخالی داشته باشد. در موفقیت وضعیت محلی به <code>completed</code> تغییر می‌کند. پرداخت تکمیل‌شده یا پرداختی با وضعیت غیر از pending دوباره تأیید نمی‌شود.</p>

<p dir="rtl" align="right">اگر نتیجه تسویه نامشخص بود، وضعیت را از پنل یا API ترب‌پی بررسی کنید و از تحویل سفارش یا ایجاد پرداخت جایگزین بدون تطبیق خودداری کنید.</p>

### <code>cancel(string $paymentToken): array</code>

<p dir="rtl" align="right">پرداخت را با payment token لغو و وضعیت محلی را به <code>canceled</code> تغییر می‌دهد. <strong>در پیاده‌سازی فعلی فقط پرداختی با وضعیت محلی <code>completed</code> قابل لغو است.</strong> این شرط را با قرارداد ترب‌پی و جریان کسب‌وکار خود تطبیق دهید.</p>

## 📦 قالب کامل پاسخ‌ها

<p dir="rtl" align="right">در تمام متدها ابتدا <code>status</code> را بررسی کنید. نمونه‌های زیر، ساختار کامل پاسخ موفق هر متد را نشان می‌دهند؛ مقادیر token، URL و شناسه‌ها صرفاً نمونه هستند.</p>

### پاسخ موفق <code>request()</code>

```php
[
  'status' => 'success',
  'data' => [
    'url' => 'https://cpg.torobpay.com/payment/example',
    'payment_token' => 'payment-token-example',
    'order_id' => 456, // شناسه سفارش فروشگاه، به‌صورت integer
    'transaction_id' => '12345678_456', // شناسه داخلی ساخته‌شده برای ترب‌پی
  ],
]
```

<p dir="rtl" align="right">کاربر را به مقدار <code>data.url</code> هدایت کنید. برای پیگیری تراکنش می‌توانید <code>payment_token</code> و <code>transaction_id</code> را از همین پاسخ بخوانید.</p>

### پاسخ موفق <code>verify()</code>

```php
[
  'status' => 'success',
  'data' => [
    'order_id' => '456', // شناسه سفارش؛ در این پاسخ string است
    'payment_token' => 'payment-token-example',
    'amount' => 700000, // مقدار callback؛ اگر ارسال نشده باشد null است
    'transaction_id' => 'gateway-transaction-example', // ممکن است null باشد
  ],
]
```

<p dir="rtl" align="right">در این پاسخ، <code>order_id</code> از شناسه تراکنش داخلی استخراج می‌شود؛ مقدار <code>amount</code> از callback و <code>transaction_id</code> از پاسخ تسویه ترب‌پی می‌آید. بسته نوع این مقادیر را تبدیل نمی‌کند.</p>

### پاسخ موفق <code>cancel()</code>

```php
[
  'status' => 'success',
  'data' => [
    'order_id' => '12345678_456', // شناسه ذخیره‌شده در رکورد پرداخت
  ],
]
```

<p dir="rtl" align="right">توجه کنید که <code>order_id</code> در پاسخ <code>cancel()</code> شناسه کامل تراکنش ذخیره‌شده است و با شناسه سفارش اصلی که <code>request()</code> برمی‌گرداند تفاوت دارد.</p>

### قالب پاسخ خطا

```php
[
  'status' => 'error',
  'data' => [
    'error' => 'شرح خطا',
    'data' => null, // یا پاسخ/داده تشخیصی در دسترس
  ],
]
```

<p dir="rtl" align="right">مقدار <code>data.data</code> در پاسخ خطا ممکن است <code>null</code> یا داده خام پاسخ درگاه باشد. متن خطاها برای نمایش مستقیم به کاربر تضمین نشده است؛ پیام مناسب برنامه خود را نمایش دهید.</p>

## 🗃️ ذخیره‌سازی و وضعیت‌ها

<p dir="rtl" align="right">migration بسته جدول <code>torobpay_payment_tokens</code> را ایجاد می‌کند:</p>

<ul dir="rtl" align="right">
  <li><code>payment_token</code>: توکن پرداخت ترب‌پی.</li>
  <li><code>access_token</code>: توکن دسترسی عملیات بعدی.</li>
  <li><code>order_id</code>: شناسه تراکنش تولیدشده توسط بسته.</li>
  <li><code>status</code>: یکی از <code>pending</code>، <code>completed</code>، <code>failed</code> یا <code>canceled</code>.</li>
  <li><code>created_at</code> و <code>updated_at</code>: زمان ایجاد و به‌روزرسانی رکورد.</li>
</ul>

<p dir="rtl" align="right"><code>verify()</code> در صورت ناموفق‌بودن پاسخ endpoint تأیید، وضعیت را <code>failed</code> می‌کند؛ تسویه موفق وضعیت را <code>completed</code> می‌کند. <code>cancel()</code> در صورت موفقیت وضعیت را <code>canceled</code> می‌کند. توکن‌ها در این migration رمزگذاری نشده‌اند؛ دسترسی پایگاه‌داده و نسخه‌های پشتیبان را محدود و سیاست نگهداری مناسبی تعریف کنید.</p>

## 🩺 خطاها و پایش

<ul dir="rtl" align="right">
  <li>خطاهای HTTP با <code>status: error</code> و جزئیات موجود در <code>data.data</code> بازگردانده می‌شوند.</li>
  <li>خطاهای اتصال و برخی وضعیت‌های نامشخص در Laravel Log ثبت می‌شوند؛ logها را بدون اطلاعات حساس پایش کنید.</li>
  <li>در timeout یا نتیجه نامشخص تأیید/تسویه، تراکنش را از سمت ترب‌پی تطبیق دهید.</li>
  <li>اگر settle سمت درگاه موفق شود اما ثبت وضعیت محلی شکست بخورد، نتیجه درگاه و رکورد پایگاه‌داده را با فرایند تطبیق اصلاح کنید.</li>
</ul>

## 🔒 امنیت و ملاحظات عملیاتی

<a id="security"></a>

<ul dir="rtl" align="right">
  <li>عملیات <code>verify()</code> را فقط در سرور انجام دهید و به ورودی مرورگر اعتماد نکنید.</li>
  <li>پیش از تحویل کالا یا خدمت، شناسه سفارش، مالکیت و مبلغ را با اطلاعات پایگاه‌داده برنامه تطبیق دهید.</li>
  <li>از شناسه‌های سفارش قابل حدس به‌عنوان مجوز دسترسی استفاده نکنید.</li>
  <li><code>client_secret</code>، گذرواژه و access token را در خروجی، log یا کد سمت کاربر قرار ندهید.</li>
  <li>اقلام سبد در نمونه کلاس نگهداری می‌شوند و خودکار پاک نمی‌شوند. Facade نمونه singleton برمی‌گرداند؛ در workerهای طولانی‌عمر، استفاده مجدد از همان نمونه می‌تواند اقلام قبلی را به پرداخت بعدی اضافه کند. برای هر پرداخت مستقل، نمونه تازه بسازید.</li>
  <li>مهلت درخواست‌های HTTP بیست ثانیه است و در پیاده‌سازی فعلی retry خودکار وجود ندارد.</li>
</ul>

## 📄 مجوز

<p dir="rtl" align="right">این بسته تحت مجوز MIT منتشر شده است.</p>
