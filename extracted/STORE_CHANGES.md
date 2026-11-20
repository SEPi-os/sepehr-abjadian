# گزارش تغییرات — تبدیل سایت به فروشگاه

## بررسی پروژه‌ی موجود (قبل از شروع)
PHP 8 خام + MySQLi، بدون فریم‌ورک؛ صفحات عمومی با قالب مشترک glass/dark (3 زبان fa/en/ar سمت کلاینت با `localStorage`)،
احراز هویت جداگانه‌ی ادمین و کاربر (Argon2id، CSRF، قفل ۵ تلاش، reCAPTCHA/honeypot، timeout ۱ ساعته)، پنل مدیریت با `owner/editor`،
`.htaccess` با CSP/هدرهای امنیتی و URL تمیز، sitemap/JSON-LD پویا. همه‌ی این‌ها حفظ شده و فروشگاه روی همین زیرساخت ساخته شد.

## فایل‌های جدید
- **دیتابیس:** `database/migrations/001_store_schema.sql` (۱۶ جدول `shop_*` + ۶ دسته + تنظیمات پیش‌فرض)، `database/migrate.php`، `includes/migrator.php`
- **هسته:** `includes/store-{config,core,catalog,cart,orders,payment,delivery,reviews,images,layout,listing,admin}.php`، `includes/gateways/*` (Null / Zarinpal / Custom + رابط)
- **صفحات عمومی:** `shop.php shop-category.php shop-product.php cart.php checkout.php payment-{start,callback,result}.php orders.php order.php purchases.php my-reviews.php download.php`، `api/store-cart.php api/store-license.php`
- **پنل:** `admin/shop-{dashboard,products,product-edit,categories,category-edit,orders,order,reviews,licenses,settings,reports,migrate}.php`، `admin/_shop_seo_panel.php`، `admin/assets/shop-admin.js`
- **فرانت:** `css/store.css`، `js/store.js`، `js/store-i18n.js`، `js/store-purchases.js`
- **عملیات/تست:** `cron/*`، `tests/*`، `STORE_SETUP.md`، این فایل

## فایل‌های موجود که تغییر کردن (حداقلی و افزایشی)
| فایل | تغییر |
|---|---|
| `includes/functions.php` | `render_auth_button()` دکمه‌ی سبد با شمارنده کنار دکمه‌ی حساب اضافه می‌کنه + لینک‌ها absolute شد (در URL تودرتو مثل `/shop/x` کار کنن) |
| `.htaccess` | مسیرهای فروشگاه، ریدایرکت ۳۰۱ آدرس‌های `.php` جدید، قانون `sitemap.xml` (قبلاً نبود) |
| `account.php` | پارامتر `?next=` (فقط لیست سفیدِ مسیرهای داخلی؛ open-redirect ممکن نیست) تا بعد از ورود به `/checkout` برگرده |
| `dashboard.php` + `css/dashboard.css` | بلوک میان‌بر سفارش‌ها/خریدها/نظرات (اگه جدول‌ها نباشن مخفی می‌شه) |
| صفحات عمومی موجود | آیتم «فروشگاه» به نوار پایین و لینک فوتر (۹ فایل)؛ `js/bottom-nav.js`، `css/bottom-nav.css` (۶ آیتم در موبایل جا بشه)، `js/i18n-data.js` (کلیدهای `bottomNav.shop/cart`) |
| `sitemap.php`، `robots.txt` | آدرس‌های فروشگاه / محدودیت سبد، پرداخت، دانلود |
| `admin/_layout_top.php`، `admin/index.php`، `admin/assets/admin.css` | منوی فروشگاه با شمارنده، آمار فروشگاه در داشبورد اصلی |
| `admin/users.php` | حذف کاربری که سفارش دارد مسدود (غیرفعال‌کردن پیشنهاد می‌شه) تا سوابق مالی نپره |
| `includes/config.php`، `database.sql`، هر جدول و ردیف قبلی | **دست‌نخورده** (تست شده: تعداد و هش داده‌های قبلی قبل/بعد مایگریشن یکسان) |

## چیزهایی که **عمداً** ساخته نشدن / محدودیت‌ها (صادقانه)
- **اتصال به درگاه واقعی** با توئه (طبق خواسته). درایور زرین‌پال آماده ولی روی سرور واقعی‌اش تست نشده.
- **ایمیل رسید/اطلاع‌رسانی** ندارد (زیرساخت SMTP در پروژه نیست؛ `mail()` روی هاست‌ها قابل اتکا نیست). سفارش و رسید در پنل کاربر و ادمین هست.
- **بازپرداخت** در سیستم ثبت می‌شه و دسترسی خریدار رو می‌بنده، ولی برگشت واقعی پول باید از پنل خود درگاه انجام بشه.
- زبان سایت مثل قبل **سمت کلاینت** است؛ متن en/ar محصولات در پایگاه‌داده هست و با سوییچر عوض می‌شه، اما آدرس جدا (`/en/…`) و hreflang ساخته نشد (همان تصمیم مستند‌شده‌ی مرحله‌ی SEO قبلی).
- جستجو با `LIKE` روی چند ستون است (برای کاتالوگ چندصد محصولی عالیه؛ برای ده‌ها هزار محصول باید FULLTEXT/موتور جستجو اضافه شه).
- کد تخفیف/کوپن، علاقه‌مندی‌ها و مقایسه در خواسته نبود و ساخته نشد؛ تخفیف در سطح محصول (قیمت ویژه + بازه‌ی زمانی) هست.
- لایسنس اسپات‌پلیر **فقط به‌عنوان کد تحویل داده می‌شه** (ساخت خودکار لایسنس از API اسپات‌پلیر پیاده‌سازی نشده؛ کدها رو خودت در پنل وارد می‌کنی).
