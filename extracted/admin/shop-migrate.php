<?php
require_once __DIR__ . '/../includes/store-admin.php';
require_once __DIR__ . '/../includes/migrator.php';
$admin = require_admin();
shop_require_owner($admin);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $r = migrator_run();
    if ($r['error']) {
        flash_set('error', 'به‌روزرسانی ناموفق بود: ' . $r['error']);
    } else {
        flash_set('ok', $r['applied'] ? 'اعمال شد: ' . implode('، ', $r['applied']) : 'دیتابیس از قبل به‌روز بود.');
        store_audit('db.migrate', 'db', null, implode(',', $r['applied']));
    }
    redirect(shop_schema_ready() ? 'shop-dashboard.php' : 'shop-migrate.php');
}
$pageTitle = 'به‌روزرسانی دیتابیس فروشگاه';
require __DIR__ . '/_layout_top.php';
$status = migrator_status();
?>
<div class="a-card">
  <h2>نصب / به‌روزرسانی جداول فروشگاه</h2>
  <p class="a-muted">این عملیات فقط جدول‌های جدید (<code>shop_*</code>) و تنظیمات پیش‌فرض فروشگاه رو می‌سازه. هیچ جدول، کاربر، مقاله یا نمونه‌کاری حذف یا تغییر نمی‌کنه، و اجرای دوباره‌اش بی‌خطره.</p>
  <div class="a-table-wrap"><table class="a-table">
    <tr><th>فایل مایگریشن</th><th>وضعیت</th></tr>
    <?php foreach ($status as $m): ?><tr><td dir="ltr"><?= e($m['name']) ?></td><td><?= $m['applied'] ? '<span class="a-badge a-badge--ok">اعمال‌شده</span>' : '<span class="a-badge a-badge--warn">در انتظار</span>' ?></td></tr><?php endforeach; ?>
  </table></div>
  <form method="post" style="margin-top:16px;"><?= csrf_field() ?>
    <button class="a-btn a-btn--primary" type="submit" <?= !array_filter($status, fn($m) => !$m['applied']) ? 'disabled' : '' ?>>اجرای به‌روزرسانی</button>
  </form>
  <p class="a-muted" style="margin-top:14px;">پیشنهاد: قبل از اجرا روی سایت زنده از دیتابیس بک‌آپ بگیر.</p>
</div>
<?php require __DIR__ . '/_layout_bottom.php';
