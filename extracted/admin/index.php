<?php
$pageTitle = 'داشبورد';
require __DIR__ . '/_layout_top.php';
require_once __DIR__ . '/../includes/db.php';
$conn = db();

function count_rows(mysqli $conn, string $sql): int
{
    $res = $conn->query($sql);
    return (int) ($res->fetch_row()[0] ?? 0);
}

$stats = [
    'users' => count_rows($conn, 'SELECT COUNT(*) FROM users'),
    'posts' => count_rows($conn, 'SELECT COUNT(*) FROM blog_posts'),
    'posts_published' => count_rows($conn, "SELECT COUNT(*) FROM blog_posts WHERE status='published'"),
    'portfolio' => count_rows($conn, 'SELECT COUNT(*) FROM portfolio_items'),
    'messages' => count_rows($conn, 'SELECT COUNT(*) FROM contact_messages'),
    'messages_unread' => count_rows($conn, "SELECT COUNT(*) FROM contact_messages WHERE status='unread'"),
    'requests' => count_rows($conn, 'SELECT COUNT(*) FROM project_requests'),
    'requests_new' => count_rows($conn, "SELECT COUNT(*) FROM project_requests WHERE status='new'"),
];

$recentRequests = $conn->query('SELECT id, name, project_type, budget, status, created_at FROM project_requests ORDER BY created_at DESC LIMIT 5');
$recentMessages = $conn->query('SELECT id, name, email, status, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 5');

$typeLabels = ['web' => 'وب', 'ai' => 'هوش مصنوعی', 'desktop' => 'دسکتاپ', 'data' => 'داده', 'other' => 'سایر'];
$statusBadge = [
    'new' => 'a-badge--warn', 'reviewing' => 'a-badge--muted', 'contacted' => 'a-badge--ok',
    'in_progress' => 'a-badge--ok', 'completed' => 'a-badge--ok', 'rejected' => 'a-badge--danger',
    'unread' => 'a-badge--warn', 'read' => 'a-badge--muted',
];
$statusLabels = [
    'new' => 'جدید', 'reviewing' => 'در حال بررسی', 'contacted' => 'تماس گرفته شد',
    'in_progress' => 'در حال انجام', 'completed' => 'تکمیل شد', 'rejected' => 'رد شد',
    'unread' => 'خوانده‌نشده', 'read' => 'خوانده‌شده',
];
?>

<?php
// ---- Store overview (users, orders, reviews, purchases, money received). Hidden gracefully if the store isn't installed yet.
$shopOverview = null;
try {
    require_once __DIR__ . '/../includes/store-admin.php';
    if (shop_schema_ready()) {
        $shopOverview = shop_stats();
    }
} catch (Throwable $shopEx) {
    $shopOverview = null;
}
?>
<?php if ($shopOverview): ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
  <strong style="font-size:15px;">فروشگاه</strong>
  <a href="shop-dashboard.php" class="a-btn a-btn--sm">داشبورد کامل فروشگاه</a>
</div>
<div class="a-grid a-grid--4" style="margin-bottom:12px;">
  <a class="a-card a-stat a-stat--link" href="shop-orders.php"><b><?= fa_digits((string) $shopOverview['orders_total']) ?></b><span>کل سفارش‌ها</span><small><?= fa_digits((string) $shopOverview['orders_paid']) ?> پرداخت‌شده</small></a>
  <a class="a-card a-stat a-stat--link" href="shop-orders.php?payment=paid"><b><?= fa_digits((string) $shopOverview['orders_paid']) ?></b><span>خرید موفق</span><small><?= fa_digits((string) $shopOverview['items_sold']) ?> قلم فروخته‌شده</small></a>
  <a class="a-card a-stat a-stat--link" href="shop-reports.php"><b><?= e(shop_money($shopOverview['revenue'])) ?></b><span>مبلغ واریزشده</span><small>فقط پرداخت‌های تأییدشده</small></a>
  <a class="a-card a-stat a-stat--link" href="users.php"><b><?= fa_digits((string) $shopOverview['users']) ?></b><span>کاربران ثبت‌نام‌شده</span></a>
</div>
<div class="a-grid a-grid--4" style="margin-bottom:24px;">
  <a class="a-card a-stat a-stat--link" href="shop-reviews.php"><b><?= fa_digits((string) $shopOverview['reviews_total']) ?></b><span>کل نظرات</span><small><?= fa_digits((string) $shopOverview['reviews_pending']) ?> در انتظار تأیید</small></a>
  <a class="a-card a-stat a-stat--link" href="shop-products.php"><b><?= fa_digits((string) $shopOverview['products_published']) ?></b><span>محصول منتشرشده</span><small><?= fa_digits((string) $shopOverview['products_draft']) ?> پیش‌نویس</small></a>
  <a class="a-card a-stat a-stat--link" href="shop-orders.php?status=pending"><b><?= fa_digits((string) $shopOverview['orders_pending']) ?></b><span>سفارش پرداخت‌نشده</span></a>
  <a class="a-card a-stat a-stat--link" href="shop-orders.php?delivery=problem"><b><?= fa_digits((string) ($shopOverview['delivery_issues'] + $shopOverview['verifying'])) ?></b><span>نیازمند بررسی</span><small>خطای تحویل / تأیید نامشخص</small></a>
</div>
<?php endif; ?>

<div class="a-grid a-grid--4" style="margin-bottom:20px;">
  <div class="a-card a-stat"><b><?= $stats['posts_published'] ?> / <?= $stats['posts'] ?></b><span>مقاله منتشرشده / کل</span></div>
  <div class="a-card a-stat"><b><?= $stats['portfolio'] ?></b><span>نمونه‌کار</span></div>
  <div class="a-card a-stat"><b><?= $stats['requests_new'] ?></b><span>درخواست پروژه‌ی جدید</span></div>
  <div class="a-card a-stat"><b><?= $stats['messages_unread'] ?></b><span>پیام خوانده‌نشده</span></div>
</div>

<div class="a-grid a-grid--2">
  <div class="a-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <strong style="font-size:14px;">آخرین درخواست‌های پروژه</strong>
      <a href="requests.php" class="a-btn a-btn--sm">مشاهده همه</a>
    </div>
    <?php if ($recentRequests->num_rows === 0): ?>
      <div class="a-empty">هنوز درخواستی ثبت نشده.</div>
    <?php else: ?>
      <div class="a-table-wrap"><table class="a-table">
        <?php while ($r = $recentRequests->fetch_assoc()): ?>
        <tr>
          <td><?= e($r['name']) ?></td>
          <td><?= e($typeLabels[$r['project_type']] ?? $r['project_type']) ?></td>
          <td><span class="a-badge <?= $statusBadge[$r['status']] ?>"><?= e($statusLabels[$r['status']] ?? $r['status']) ?></span></td>
          <td><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
        </tr>
        <?php endwhile; ?>
      </table></div>
    <?php endif; ?>
  </div>

  <div class="a-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <strong style="font-size:14px;">آخرین پیام‌های تماس</strong>
      <a href="messages.php" class="a-btn a-btn--sm">مشاهده همه</a>
    </div>
    <?php if ($recentMessages->num_rows === 0): ?>
      <div class="a-empty">هنوز پیامی ثبت نشده.</div>
    <?php else: ?>
      <div class="a-table-wrap"><table class="a-table">
        <?php while ($m = $recentMessages->fetch_assoc()): ?>
        <tr>
          <td><?= e($m['name']) ?></td>
          <td><?= e($m['email']) ?></td>
          <td><span class="a-badge <?= $statusBadge[$m['status']] ?>"><?= e($statusLabels[$m['status']] ?? $m['status']) ?></span></td>
          <td><?= e(date('Y-m-d', strtotime($m['created_at']))) ?></td>
        </tr>
        <?php endwhile; ?>
      </table></div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
