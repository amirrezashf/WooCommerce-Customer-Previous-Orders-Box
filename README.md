# WooCommerce Customer Previous Orders Box

Display a smart previous-orders box inside the WooCommerce orders list, including customer order history, previous order status, status change count, reprocessing indicators, total purchase value, last purchase date, and average purchase interval.

## Features

- Shows previous customer orders inside the WooCommerce orders list
- Displays previous order status
- Tracks order status history
- Shows total status change count
- Detects orders that returned to processing again
- Displays customer previous order count
- Shows total customer purchase amount
- Shows last purchase date
- Shows average purchase interval for returning customers
- Opens previous orders in a modal
- Shows order items with tooltip
- Copy previous order number
- Supports WooCommerce HPOS
- Uses AJAX for better admin performance
- Uses 15-minute modal cache
- Clears cache when related orders change

## Use Cases

This plugin is useful for WooCommerce stores where support, fulfillment, or sales teams need quick access to a customer’s previous orders while reviewing the current order.

It helps admins understand whether a customer is new, returning, high-value, frequently purchasing, or has an order with repeated processing status changes.

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+

## Development

The plugin is built with native WordPress and WooCommerce APIs.

Main components:

- Order status history tracking
- Previous status detection
- Reprocessing detection
- Customer order statistics
- Bulk AJAX stats loading
- Previous orders modal
- HPOS-compatible SQL queries
- Admin column integration
- Modal-level transient caching

### Data Storage

The plugin stores status tracking data in WooCommerce order meta.

Used meta keys:

- `_fa_statusbox_status_history`
- `_fa_statusbox_last_status`
- `_fa_statusbox_status_changes_count`
- `_fa_statusbox_processing_entries`
- `_previous_order_status`

Modal data is cached temporarily with WordPress transients.

### Performance Notes

The plugin avoids loading full previous order details on page load.

Instead:

- Basic customer statistics are loaded in bulk with one AJAX request.
- Full previous order details are loaded only when the admin clicks the button.
- Modal HTML is cached for 15 minutes.
- Cache is cleared when related orders are created, updated, deleted, or changed.

### Suggested Improvements

Future versions can improve the plugin with:

- Plugin settings page
- Configurable modal order limit
- Configurable cache duration
- Filters by customer type
- Export customer order history
- Separate CSS and JS files
- Better i18n support
- Custom capability for access control
- Admin toggle for status history tracking

## Changelog

### 1.0.0

- Initial release
- Added previous orders button
- Added previous orders modal
- Added customer purchase summary
- Added previous order status tracking
- Added status change counter
- Added reprocessing indicator
- Added average purchase interval
- Added HPOS support
- Added AJAX bulk stats loading
- Added transient caching

## License

GPL-2.0-or-later

## Author

Amirreza Shayesteh Far

Website:  
https://amirrezaa.ir

GitHub:  
https://github.com/amirrezashf

---

# باکس سفارشات قبلی مشتری ووکامرس

افزونه‌ای برای نمایش اطلاعات سفارش‌های قبلی مشتری داخل لیست سفارشات ووکامرس، همراه با وضعیت قبلی سفارش، تعداد تغییر وضعیت‌ها، تشخیص ورود مجدد به وضعیت در حال انجام، مجموع خرید مشتری، آخرین خرید و میانگین فاصله بین خریدها.

## امکانات

- نمایش سفارش‌های قبلی مشتری در لیست سفارشات ووکامرس
- نمایش وضعیت قبلی سفارش
- ثبت تاریخچه تغییر وضعیت سفارش
- نمایش تعداد کل تغییر وضعیت‌ها
- تشخیص سفارش‌هایی که دوباره وارد وضعیت در حال انجام شده‌اند
- نمایش تعداد سفارش‌های قبلی مشتری
- نمایش مجموع مبلغ خرید مشتری
- نمایش آخرین تاریخ خرید
- نمایش میانگین فاصله بین خریدها برای مشتریان پرتکرار
- نمایش سفارش‌های قبلی در مودال
- نمایش آیتم‌های سفارش همراه با tooltip
- امکان کپی شماره سفارش قبلی
- پشتیبانی از HPOS ووکامرس
- استفاده از AJAX برای افزایش سرعت پنل مدیریت
- کش ۱۵ دقیقه‌ای برای محتوای مودال
- پاکسازی کش هنگام تغییر سفارش‌های مرتبط

## موارد استفاده

این افزونه برای فروشگاه‌هایی مناسب است که تیم پشتیبانی، فروش یا پردازش سفارش نیاز دارند هنگام بررسی یک سفارش، سریعاً سابقه خرید مشتری را ببینند.

با این افزونه می‌توان سریع‌تر تشخیص داد که مشتری جدید است یا قدیمی، ارزش خرید بالایی دارد یا خیر، آخرین خرید او چه زمانی بوده و آیا سفارش فعلی چند بار بین وضعیت‌ها جابه‌جا شده است یا نه.

## پیش‌نیازها

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+

## توسعه

این افزونه با استفاده از APIهای استاندارد وردپرس و ووکامرس توسعه داده شده است.

بخش‌های اصلی افزونه:

- ثبت تاریخچه تغییر وضعیت سفارش
- تشخیص وضعیت قبلی سفارش
- تشخیص ورود مجدد به وضعیت processing
- محاسبه آمار خرید مشتری
- دریافت آمار گروهی با AJAX
- نمایش مودال سفارش‌های قبلی
- کوئری‌های سازگار با HPOS
- اتصال به ستون وضعیت سفارش در ادمین
- کش موقت برای محتوای مودال

### ذخیره‌سازی داده‌ها

اطلاعات مربوط به وضعیت سفارش در متای سفارش‌های ووکامرس ذخیره می‌شود.

کلیدهای متای استفاده‌شده:

- `_fa_statusbox_status_history`
- `_fa_statusbox_last_status`
- `_fa_statusbox_status_changes_count`
- `_fa_statusbox_processing_entries`
- `_previous_order_status`

محتوای مودال به صورت موقت با Transient وردپرس کش می‌شود.

### نکات عملکردی

افزونه برای جلوگیری از کند شدن لیست سفارشات، همه اطلاعات سفارش‌های قبلی را هنگام لود صفحه دریافت نمی‌کند.

در عوض:

- آمار اولیه مشتریان با یک درخواست AJAX گروهی دریافت می‌شود.
- جزئیات سفارش‌های قبلی فقط هنگام کلیک روی دکمه بارگذاری می‌شود.
- HTML مودال برای ۱۵ دقیقه کش می‌شود.
- کش هنگام ایجاد، بروزرسانی، حذف یا تغییر وضعیت سفارش پاک می‌شود.

### پیشنهادهای توسعه

برای نسخه‌های بعدی می‌توان این موارد را اضافه کرد:

- صفحه تنظیمات افزونه
- تعیین تعداد سفارش‌های قابل نمایش در مودال
- تنظیم مدت زمان کش
- فیلتر بر اساس نوع مشتری
- خروجی گرفتن از سابقه سفارش‌های مشتری
- انتقال CSS و JS به فایل‌های جداگانه
- پشتیبانی بهتر از چندزبانه‌سازی
- تعریف capability اختصاصی برای دسترسی
- امکان فعال/غیرفعال کردن ثبت تاریخچه وضعیت

## تغییرات

### 1.0.0

- انتشار اولیه
- افزودن دکمه سفارش‌های قبلی
- افزودن مودال سفارش‌های قبلی
- افزودن خلاصه خرید مشتری
- افزودن ثبت وضعیت قبلی سفارش
- افزودن شمارنده تغییر وضعیت
- افزودن تشخیص ورود مجدد به وضعیت processing
- افزودن میانگین فاصله خرید
- افزودن پشتیبانی از HPOS
- افزودن دریافت آمار گروهی با AJAX
- افزودن کش موقت

## مجوز

GPL-2.0-or-later

## توسعه‌دهنده

Amirreza Shayesteh Far

وب‌سایت:  
https://amirrezaa.ir

گیت‌هاب:  
https://github.com/amirrezashf
