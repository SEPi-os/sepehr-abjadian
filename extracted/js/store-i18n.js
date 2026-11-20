/* Store UI strings (fa / en / ar). Merged into window.SITE_I18N, which js/i18n-data.js created
   (both are deferred scripts and run in order, before js/site-preferences.js). Keys are nested
   under "store." (plus bottomNav.shop). Missing en/ar keys fall back to fa in site-preferences.js. */
(function () {
  'use strict';
  var D = window.SITE_I18N = window.SITE_I18N || { fa: {}, en: {}, ar: {} };
  ['fa', 'en', 'ar'].forEach(function (l) { D[l] = D[l] || {}; D[l].store = D[l].store || {}; D[l].bottomNav = D[l].bottomNav || {}; });

  function put(lang, key, val) {
    var parts = key.split('.'), node = D[lang];
    for (var i = 0; i < parts.length - 1; i++) { node = node[parts[i]] = node[parts[i]] || {}; }
    node[parts[parts.length - 1]] = val;
  }
  function L(key, fa, en, ar) { put('fa', key, fa); put('en', key, en); put('ar', key, ar); }

  L('bottomNav.shop', 'فروشگاه', 'Shop', 'المتجر');
  L('bottomNav.cart', 'سبد خرید', 'Shopping cart', 'سلة التسوق');

  /* ---- listing / search ---- */
  L('store.heroH1', 'فروشگاه', 'Store', 'المتجر');
  L('store.heroP', 'قالب آماده وب‌سایت، سورس پروژه، افزونه وردپرس، اتوماسیون، مدل هوش مصنوعی و دوره‌ی آموزشی — تحویل فوری و امن پس از پرداخت.', 'Ready-made website templates, project source code, WordPress plugins, automations, AI models and courses — instant, secure delivery after payment.', 'قوالب مواقع جاهزة، أكواد مصدرية، إضافات ووردبريس، أتمتة، نماذج ذكاء اصطناعي ودورات — تسليم فوري وآمن بعد الدفع.');
  L('store.allCategories', 'همه', 'All', 'الكل');
  L('store.allTypes', 'همه‌ی انواع', 'All types', 'كل الأنواع');
  L('store.searchPh', 'جستجوی قالب، افزونه، دوره…', 'Search templates, plugins, courses…', 'ابحث عن قالب أو إضافة أو دورة…');
  L('store.apply', 'اعمال', 'Apply', 'تطبيق');
  L('store.clear', 'پاک‌کردن', 'Clear', 'مسح');
  L('store.resultsPrefix', 'تعداد نتایج:', 'Results:', 'عدد النتائج:');
  L('store.emptyTitle', 'محصولی پیدا نشد', 'No products found', 'لم يتم العثور على منتجات');
  L('store.emptyText', 'عبارت جستجو یا فیلترها را تغییر بده.', 'Try a different search or filters.', 'جرّب تغيير البحث أو الفلاتر.');
  L('store.closedNotice', 'فروشگاه موقتاً بسته است؛ امکان ثبت سفارش جدید وجود ندارد.', 'The store is temporarily closed; new orders are not possible right now.', 'المتجر مغلق مؤقتًا؛ لا يمكن إنشاء طلبات جديدة حاليًا.');
  L('store.sortNewest', 'جدیدترین', 'Newest', 'الأحدث');
  L('store.sortBest', 'پرفروش‌ترین', 'Best selling', 'الأكثر مبيعًا');
  L('store.sortRating', 'بیشترین امتیاز', 'Top rated', 'الأعلى تقييمًا');
  L('store.sortPriceAsc', 'ارزان‌ترین', 'Price: low to high', 'السعر: من الأقل');
  L('store.sortPriceDesc', 'گران‌ترین', 'Price: high to low', 'السعر: من الأعلى');
  L('store.sortFeatured', 'منتخب', 'Featured', 'مميز');
  L('store.details', 'جزئیات', 'Details', 'التفاصيل');
  L('store.off', 'تخفیف', 'off', 'خصم');
  L('store.featured', 'ویژه', 'Featured', 'مميز');
  L('store.free', 'رایگان', 'Free', 'مجاني');
  L('store.addToCart', 'افزودن به سبد خرید', 'Add to cart', 'أضف إلى السلة');
  L('store.cartAria', 'سبد خرید', 'Shopping cart', 'سلة التسوق');
  L('store.type.website_template', 'قالب وب‌سایت', 'Website template', 'قالب موقع');
  L('store.type.source_code', 'سورس پروژه', 'Project source code', 'كود مصدري');
  L('store.type.wp_plugin', 'افزونه وردپرس', 'WordPress plugin', 'إضافة ووردبريس');
  L('store.type.ui_component', 'کامپوننت / المان UI', 'UI component', 'مكوّن واجهة');
  L('store.type.automation', 'اتوماسیون / ورک‌فلو', 'Automation / workflow', 'أتمتة / مسار عمل');
  L('store.type.ai_model', 'مدل هوش مصنوعی', 'AI model', 'نموذج ذكاء اصطناعي');
  L('store.type.app', 'اپلیکیشن', 'Application', 'تطبيق');
  L('store.type.course', 'دوره آموزشی', 'Course', 'دورة تعليمية');
  L('store.type.bundle', 'بسته دانلودی', 'Download bundle', 'حزمة تنزيل');
  L('store.type.other', 'سایر', 'Other', 'أخرى');

  /* ---- product page ---- */
  L('store.zoom', 'بزرگنمایی تصویر', 'Zoom image', 'تكبير الصورة');
  L('store.reviewsWord', 'نظر', 'reviews', 'تقييم');
  L('store.saleEnds', 'پایان تخفیف:', 'Sale ends:', 'ينتهي الخصم:');
  L('store.contactPrice', 'تماس بگیرید', 'Contact us', 'اتصل بنا');
  L('store.delFile', 'فایل دانلودی (ZIP/RAR) پس از پرداخت', 'Downloadable file (ZIP/RAR) after payment', 'ملف للتنزيل (ZIP/RAR) بعد الدفع');
  L('store.delLicense', 'کد لایسنس پس از پرداخت', 'License code after payment', 'رمز الترخيص بعد الدفع');
  L('store.delBoth', 'فایل + کد لایسنس پس از پرداخت', 'File + license code after payment', 'ملف + رمز ترخيص بعد الدفع');
  L('store.delManual', 'تحویل توسط پشتیبانی پس از پرداخت', 'Delivered by support after payment', 'يتم التسليم عبر الدعم بعد الدفع');
  L('store.pointSecure', 'پرداخت امن و تحویل خودکار', 'Secure payment and automatic delivery', 'دفع آمن وتسليم تلقائي');
  L('store.pointDash', 'دسترسی دائمی از داشبورد کاربری', 'Permanent access from your dashboard', 'وصول دائم من لوحة التحكم');
  L('store.qty', 'تعداد', 'Quantity', 'الكمية');
  L('store.getFree', 'دریافت رایگان', 'Get it free', 'احصل عليه مجانًا');
  L('store.buyNow', 'خرید فوری', 'Buy now', 'اشترِ الآن');
  L('store.loginNote', 'برای تکمیل خرید باید وارد حساب کاربری شوی.', 'You need to sign in to complete the purchase.', 'يجب تسجيل الدخول لإتمام الشراء.');
  L('store.outOfStock', 'این محصول موقتاً ناموجود است.', 'This product is temporarily out of stock.', 'هذا المنتج غير متوفر مؤقتًا.');
  L('store.unavailable', 'این محصول فعلاً قابل خرید نیست.', 'This product is not available for purchase right now.', 'هذا المنتج غير متاح للشراء حاليًا.');
  L('store.sku', 'کد محصول', 'SKU', 'رمز المنتج');
  L('store.productType', 'نوع', 'Type', 'النوع');
  L('store.version', 'نسخه', 'Version', 'الإصدار');
  L('store.brand', 'سازنده', 'Maker', 'الصانع');
  L('store.updated', 'آخرین به‌روزرسانی', 'Last updated', 'آخر تحديث');
  L('store.sales', 'تعداد فروش', 'Sales', 'المبيعات');
  L('store.demo', 'مشاهده‌ی دمو', 'View demo', 'عرض العرض التجريبي');
  L('store.tabDesc', 'توضیحات', 'Description', 'الوصف');
  L('store.tabSpecs', 'مشخصات', 'Specifications', 'المواصفات');
  L('store.tabInstall', 'راهنمای نصب', 'Installation guide', 'دليل التثبيت');
  L('store.tabFaq', 'سوالات متداول', 'FAQ', 'الأسئلة الشائعة');
  L('store.tabReviews', 'نظرات', 'Reviews', 'التقييمات');
  L('store.video', 'ویدیوی معرفی', 'Intro video', 'فيديو تعريفي');
  L('store.features', 'ویژگی‌ها', 'Features', 'الميزات');
  L('store.requirements', 'پیش‌نیازها', 'Requirements', 'المتطلبات');
  L('store.compat', 'سازگاری', 'Compatibility', 'التوافق');
  L('store.usage', 'راهنمای استفاده', 'Usage guide', 'دليل الاستخدام');
  L('store.changelog', 'تاریخچه‌ی تغییرات', 'Changelog', 'سجل التغييرات');
  L('store.licenseTerms', 'شرایط لایسنس', 'License terms', 'شروط الترخيص');
  L('store.support', 'پشتیبانی', 'Support', 'الدعم');
  L('store.related', 'محصولات مرتبط', 'Related products', 'منتجات ذات صلة');
  L('store.yourRating', 'امتیاز تو', 'Your rating', 'تقييمك');
  L('store.reviewTitle', 'عنوان (اختیاری)', 'Title (optional)', 'العنوان (اختياري)');
  L('store.reviewBody', 'متن نظر', 'Your review', 'نص التقييم');
  L('store.reviewModerationNote', 'نظرها قبل از نمایش توسط مدیر بررسی می‌شن.', 'Reviews are checked by a moderator before they appear.', 'تتم مراجعة التقييمات قبل ظهورها.');
  L('store.submitReview', 'ثبت نظر', 'Submit review', 'إرسال التقييم');
  L('store.deleteMyReview', 'حذف نظر من', 'Delete my review', 'حذف تقييمي');
  L('store.reviewLogin', 'برای ثبت نظر و امتیاز باید وارد حساب کاربری شوی.', 'Sign in to leave a review and rating.', 'سجّل الدخول لإضافة تقييم.');
  L('store.myReviewPending', 'نظر تو در انتظار تأیید مدیر است و هنوز نمایش داده نمی‌شود.', 'Your review is awaiting moderation and is not visible yet.', 'تقييمك بانتظار موافقة المشرف ولم يظهر بعد.');
  L('store.myReviewApproved', 'نظر تو تأیید و منتشر شده است. می‌تونی ویرایشش کنی (دوباره بررسی می‌شه).', 'Your review is published. You can edit it (it will be re-checked).', 'تم نشر تقييمك. يمكنك تعديله (ستتم مراجعته مجددًا).');
  L('store.myReviewRejected', 'نظر تو منتشر نشد.', 'Your review was not published.', 'لم يتم نشر تقييمك.');
  L('store.verifiedBuyer', 'خریدار تأییدشده', 'Verified buyer', 'مشترٍ موثّق');
  L('store.noReviews', 'هنوز نظری ثبت نشده؛ اولین نفر باش.', 'No reviews yet — be the first.', 'لا توجد تقييمات بعد — كن الأول.');

  /* ---- cart / checkout ---- */
  L('store.added', 'به سبد خرید اضافه شد.', 'Added to your cart.', 'تمت الإضافة إلى السلة.');
  L('store.cartTitle', 'سبد خرید | فروشگاه سپهر ابجدیان', 'Cart | Sepehr Abjadian Store', 'سلة التسوق | متجر سپهر ابجديان');
  L('store.cartH1', 'سبد خرید', 'Shopping cart', 'سلة التسوق');
  L('store.summary', 'خلاصه‌ی سفارش', 'Order summary', 'ملخص الطلب');
  L('store.subtotal', 'جمع اقلام', 'Subtotal', 'المجموع الفرعي');
  L('store.discount', 'تخفیف', 'Discount', 'الخصم');
  L('store.total', 'مبلغ قابل پرداخت', 'Total to pay', 'المبلغ المستحق');
  L('store.completePurchase', 'تکمیل خرید', 'Complete purchase', 'إتمام الشراء');
  L('store.cartLoginHint', 'برای ادامه، وارد حساب کاربری می‌شی و به همین مرحله برمی‌گردی.', 'You will sign in and come straight back to this step.', 'ستسجّل الدخول ثم تعود إلى هذه الخطوة.');
  L('store.clearCart', 'خالی‌کردن سبد', 'Empty cart', 'إفراغ السلة');
  L('store.cartEmptyTitle', 'سبد خرید خالی است', 'Your cart is empty', 'سلتك فارغة');
  L('store.cartEmptyText', 'از فروشگاه محصول انتخاب کن و به سبد اضافه‌ش کن.', 'Pick something from the store and add it to your cart.', 'اختر منتجًا من المتجر وأضفه إلى السلة.');
  L('store.goShop', 'رفتن به فروشگاه', 'Go to the store', 'اذهب إلى المتجر');
  L('store.remove', 'حذف', 'Remove', 'إزالة');
  L('store.fixCart', 'اول مشکل اقلام قرمز را برطرف کن.', 'Fix the items marked in red first.', 'عالج العناصر المعلّمة بالأحمر أولًا.');
  L('store.cartLoadErr', 'بارگذاری سبد ممکن نشد؛ دوباره تلاش کن.', 'Could not load your cart; please retry.', 'تعذّر تحميل السلة؛ حاول مجددًا.');
  L('store.storageErr', 'ذخیره‌ی سبد خرید در مرورگر ممکن نیست.', 'Your browser cannot store the cart.', 'لا يمكن لمتصفحك حفظ السلة.');
  L('store.iss.missing', 'این محصول دیگر وجود ندارد.', 'This product no longer exists.', 'لم يعد هذا المنتج موجودًا.');
  L('store.iss.unavailable', 'این محصول فعلاً قابل خرید نیست.', 'This product is not available right now.', 'هذا المنتج غير متاح حاليًا.');
  L('store.iss.out', 'موجودی این محصول تمام شده.', 'This product is out of stock.', 'نفد مخزون هذا المنتج.');
  L('store.iss.nodeliver', 'تحویل این محصول موقتاً ممکن نیست.', 'Delivery of this product is temporarily unavailable.', 'التسليم غير متاح مؤقتًا.');
  L('store.iss.qty', 'تعداد به سقف مجاز تغییر کرد.', 'Quantity was reduced to the allowed maximum.', 'تم تقليل الكمية للحد الأقصى المسموح.');
  L('store.iss.qtyStock', 'تعداد بر اساس موجودی کاهش یافت.', 'Quantity was reduced to the available stock.', 'تم تقليل الكمية بحسب المخزون.');
  L('store.iss.price', 'قیمت از زمانی که اضافه کردی تغییر کرده.', 'The price changed since you added it.', 'تغيّر السعر منذ إضافته.');
  L('store.checkoutTitle', 'تکمیل خرید | فروشگاه سپهر ابجدیان', 'Checkout | Sepehr Abjadian Store', 'إتمام الشراء | متجر سپهر ابجديان');
  L('store.checkoutH1', 'تکمیل خرید', 'Checkout', 'إتمام الشراء');
  L('store.gatewayOff', 'درگاه پرداخت آنلاین هنوز فعال نشده؛ می‌تونی سفارش رو ثبت کنی ولی پرداخت بعداً ممکن می‌شه.', 'Online payment is not active yet; you can place the order but paying will be possible later.', 'الدفع الإلكتروني غير مفعّل بعد؛ يمكنك تسجيل الطلب والدفع لاحقًا.');
  L('store.buyerInfo', 'اطلاعات خریدار', 'Buyer information', 'بيانات المشتري');
  L('store.fullName', 'نام و نام خانوادگی', 'Full name', 'الاسم الكامل');
  L('store.email', 'ایمیل (برای ارسال رسید)', 'Email (for the receipt)', 'البريد الإلكتروني (للإيصال)');
  L('store.phone', 'موبایل (اختیاری)', 'Mobile (optional)', 'الجوال (اختياري)');
  L('store.note', 'توضیحات سفارش (اختیاری)', 'Order notes (optional)', 'ملاحظات الطلب (اختياري)');
  L('store.acceptTerms', 'شرایط و قوانین خرید محصولات دیجیتال را می‌پذیرم؛ محصولات دیجیتال پس از تحویل قابل بازگشت نیستند.', 'I accept the terms of purchase; digital products cannot be returned after delivery.', 'أوافق على شروط الشراء؛ لا يمكن إرجاع المنتجات الرقمية بعد التسليم.');
  L('store.placeOrder', 'ثبت سفارش و پرداخت', 'Place order and pay', 'تأكيد الطلب والدفع');
  L('store.processing', 'در حال ثبت…', 'Processing…', 'جارٍ المعالجة…');
  L('store.payNote', 'بعد از ثبت سفارش به درگاه پرداخت امن هدایت می‌شی. مبلغ نهایی همیشه روی سرور محاسبه می‌شه.', 'After placing the order you are sent to the secure payment gateway. The final amount is always computed on the server.', 'بعد تأكيد الطلب ستُحوَّل إلى بوابة الدفع الآمنة. يُحسب المبلغ النهائي دائمًا على الخادم.');
  L('store.backToCart', 'بازگشت به سبد خرید', 'Back to cart', 'العودة إلى السلة');

  /* ---- payment ---- */
  L('store.payH1', 'پرداخت سفارش', 'Pay for your order', 'دفع الطلب');
  L('store.orderNo', 'شماره سفارش', 'Order number', 'رقم الطلب');
  L('store.reservedUntil', 'رزرو تا', 'Reserved until', 'محجوز حتى');
  L('store.payNow', 'پرداخت امن', 'Pay securely', 'ادفع بأمان');
  L('store.orderClosed', 'این سفارش دیگر قابل پرداخت نیست.', 'This order can no longer be paid.', 'لم يعد بالإمكان دفع هذا الطلب.');
  L('store.viewOrder', 'مشاهده‌ی سفارش', 'View order', 'عرض الطلب');
  L('store.redirecting', 'در حال انتقال به درگاه پرداخت…', 'Redirecting to the payment gateway…', 'جارٍ التحويل إلى بوابة الدفع…');
  L('store.redirectingText', 'اگر منتقل نشدی روی دکمه‌ی زیر بزن. این صفحه را نبند.', 'If you are not redirected, press the button below. Do not close this page.', 'إن لم يتم تحويلك فاضغط الزر أدناه. لا تغلق الصفحة.');
  L('store.goGateway', 'رفتن به درگاه', 'Go to gateway', 'الذهاب إلى البوابة');
  L('store.paySuccess', 'پرداخت با موفقیت انجام شد', 'Payment successful', 'تم الدفع بنجاح');
  L('store.txRef', 'کد پیگیری بانکی', 'Bank reference', 'رقم المرجع البنكي');
  L('store.deliveredText', 'محصولات تو آماده‌ست؛ از بخش «خریدها و دانلودها» دانلود کن یا لایسنس رو کپی کن.', 'Your products are ready — download them or copy your license from “Purchases & downloads”.', 'منتجاتك جاهزة — حمّلها أو انسخ الترخيص من «المشتريات والتنزيلات».');
  L('store.deliveryPendingText', 'پرداخت تأیید شد، ولی تحویل بعضی اقلام هنوز کامل نشده. تیم پشتیبانی بررسی می‌کنه و بعد از آماده‌شدن در همین بخش می‌بینی.', 'Payment is confirmed but some items are not delivered yet. Support is on it; they will appear in your library.', 'تم تأكيد الدفع لكن بعض العناصر لم تُسلَّم بعد. سيتابع الدعم الأمر وستظهر في مكتبتك.');
  L('store.goPurchases', 'رفتن به خریدها و دانلودها', 'Go to purchases & downloads', 'الذهاب إلى المشتريات والتنزيلات');
  L('store.payPending', 'در حال تأیید پرداخت…', 'Confirming your payment…', 'جارٍ تأكيد الدفع…');
  L('store.payPendingText', 'نتیجه‌ی پرداخت هنوز از بانک تأیید نشده. اگر مبلغ از حسابت کم شده، نگران نباش؛ بعد از تأیید، محصول خودکار تحویل داده می‌شه.', 'The bank has not confirmed the payment yet. If you were charged, do not worry — delivery happens automatically after confirmation.', 'لم يؤكد البنك الدفع بعد. إن خُصم المبلغ فلا تقلق — سيتم التسليم تلقائيًا بعد التأكيد.');
  L('store.refunded', 'این سفارش بازپرداخت شده است', 'This order has been refunded', 'تم استرداد هذا الطلب');
  L('store.payFailed', 'پرداخت انجام نشد', 'Payment failed', 'فشلت عملية الدفع');
  L('store.payFailedText', 'اگر مبلغی از حسابت کم شده، معمولاً تا ۷۲ ساعت به حساب خودت برمی‌گرده. می‌تونی دوباره تلاش کنی.', 'If you were charged, the amount is normally returned within 72 hours. You can try again.', 'إن خُصم مبلغ فعادةً يُعاد خلال 72 ساعة. يمكنك المحاولة مجددًا.');
  L('store.retryPay', 'تلاش مجدد برای پرداخت', 'Try paying again', 'أعد محاولة الدفع');

  /* ---- account / library ---- */
  L('store.navOverview', 'پروفایل', 'Profile', 'الملف الشخصي');
  L('store.navPurchases', 'خریدها و دانلودها', 'Purchases & downloads', 'المشتريات والتنزيلات');
  L('store.navOrders', 'سفارش‌ها', 'Orders', 'الطلبات');
  L('store.navReviews', 'نظرات من', 'My reviews', 'تقييماتي');
  L('store.ordersTitle', 'سفارش‌های من | سپهر ابجدیان', 'My orders | Sepehr Abjadian', 'طلباتي | سپهر ابجديان');
  L('store.purchasesTitle', 'خریدها و دانلودها | سپهر ابجدیان', 'Purchases & downloads | Sepehr Abjadian', 'المشتريات والتنزيلات | سپهر ابجديان');
  L('store.reviewsTitle', 'نظرات من | سپهر ابجدیان', 'My reviews | Sepehr Abjadian', 'تقييماتي | سپهر ابجديان');
  L('store.noOrders', 'هنوز سفارشی نداری', 'You have no orders yet', 'ليست لديك طلبات بعد');
  L('store.date', 'تاریخ', 'Date', 'التاريخ');
  L('store.items', 'اقلام', 'Items', 'العناصر');
  L('store.payStatus', 'وضعیت پرداخت', 'Payment status', 'حالة الدفع');
  L('store.orderStatus', 'وضعیت سفارش', 'Order status', 'حالة الطلب');
  L('store.deliveryStatus', 'وضعیت تحویل', 'Delivery status', 'حالة التسليم');
  L('store.view', 'مشاهده', 'View', 'عرض');
  L('store.history', 'تاریخچه‌ی سفارش', 'Order history', 'سجل الطلب');
  L('store.cancelOrder', 'لغو سفارش', 'Cancel order', 'إلغاء الطلب');
  L('store.pay.unpaid', 'پرداخت‌نشده', 'Unpaid', 'غير مدفوع');
  L('store.pay.pending', 'در انتظار تأیید', 'Awaiting confirmation', 'بانتظار التأكيد');
  L('store.pay.paid', 'پرداخت‌شده', 'Paid', 'مدفوع');
  L('store.pay.failed', 'ناموفق', 'Failed', 'فشل');
  L('store.pay.cancelled', 'لغو توسط کاربر', 'Cancelled', 'ملغى');
  L('store.pay.refunded', 'بازپرداخت‌شده', 'Refunded', 'مسترد');
  L('store.ord.pending', 'در انتظار', 'Pending', 'قيد الانتظار');
  L('store.ord.processing', 'در حال پردازش', 'Processing', 'قيد المعالجة');
  L('store.ord.completed', 'تکمیل‌شده', 'Completed', 'مكتمل');
  L('store.ord.cancelled', 'لغو‌شده', 'Cancelled', 'ملغى');
  L('store.ord.expired', 'منقضی‌شده', 'Expired', 'منتهي');
  L('store.noPurchases', 'هنوز خریدی انجام نداده‌ای', 'You have not bought anything yet', 'لم تشترِ شيئًا بعد');
  L('store.licenseHelp', 'لایسنس دوره‌ها را کپی کن و در اپلیکیشن اسپات‌پلیر (Spot Player) وارد کن تا دوره‌ی دانلودشده برایت باز شود.', 'Copy the course license and enter it in the Spot Player app to unlock the downloaded course.', 'انسخ ترخيص الدورة وأدخله في تطبيق Spot Player لفتح الدورة المنزّلة.');
  L('store.files', 'فایل‌های دانلودی', 'Downloadable files', 'الملفات القابلة للتنزيل');
  L('store.download', 'دانلود', 'Download', 'تنزيل');
  L('store.noFilesYet', 'فایلی برای این محصول هنوز بارگذاری نشده؛ به‌زودی.', 'No file has been uploaded for this product yet.', 'لم يُرفع ملف لهذا المنتج بعد.');
  L('store.licenses', 'لایسنس (Spot Player)', 'License (Spot Player)', 'الترخيص (Spot Player)');
  L('store.show', 'نمایش', 'Show', 'إظهار');
  L('store.hide', 'پنهان', 'Hide', 'إخفاء');
  L('store.copy', 'کپی', 'Copy', 'نسخ');
  L('store.copied', 'لایسنس کپی شد.', 'License copied.', 'تم نسخ الترخيص.');
  L('store.licenseError', 'دریافت لایسنس ممکن نشد.', 'Could not load the license.', 'تعذّر تحميل الترخيص.');
  L('store.deliveryInfo', 'اطلاعات تحویل', 'Delivery information', 'معلومات التسليم');
  L('store.manualPending', 'این محصول به‌صورت دستی تحویل داده می‌شود؛ بعد از آماده‌شدن اینجا می‌بینی.', 'This product is delivered manually; it will appear here when ready.', 'يُسلَّم هذا المنتج يدويًا وسيظهر هنا عند جاهزيته.');
  L('store.partialText', 'تحویل این مورد هنوز کامل نیست؛ پشتیبانی در حال بررسی است.', 'Delivery of this item is not complete yet; support is looking into it.', 'لم يكتمل تسليم هذا العنصر بعد؛ الدعم يتابع.');
  L('store.noMyReviews', 'هنوز نظری ثبت نکرده‌ای', 'You have not written any reviews yet', 'لم تكتب أي تقييم بعد');
  L('store.rv.pending', 'در انتظار تأیید', 'Pending', 'قيد المراجعة');
  L('store.rv.approved', 'منتشر شده', 'Published', 'منشور');
  L('store.rv.rejected', 'رد شده', 'Rejected', 'مرفوض');

  /* ---- dashboard store block ---- */
  L('store.dashStore', 'فروشگاه من', 'My store account', 'حسابي في المتجر');
  L('store.dashOrdersHint', 'پیگیری و تاریخچه‌ی سفارش‌ها', 'Track and review your orders', 'تتبّع طلباتك وسجلّها');
  L('store.dashPurchasesHint', 'دانلود فایل‌ها و لایسنس‌ها', 'Download files and licenses', 'تنزيل الملفات والتراخيص');
  L('store.dashReviewsHint', 'نظرهای ثبت‌شده توسط تو', 'Reviews you have written', 'التقييمات التي كتبتها');
})();
