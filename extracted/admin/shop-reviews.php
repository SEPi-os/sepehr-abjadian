<?php
require_once __DIR__ . '/../includes/store-admin.php';
$admin = require_admin();
if (!shop_schema_ready()) { redirect('shop-migrate.php'); }
$aid = (int) $admin['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();
    $action = (string) ($_POST['action'] ?? '');
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [(int) ($_POST['id'] ?? 0)])))));
    $note = trim((string) ($_POST['note'] ?? ''));
    $n = 0;
    foreach ($ids as $rid) {
        if (in_array($action, ['approve', 'reject', 'pending'], true)) {
            $n += store_review_set_status($rid, ['approve' => 'approved', 'reject' => 'rejected', 'pending' => 'pending'][$action], $aid, $note) ? 1 : 0;
        } elseif ($action === 'delete') {
            $n += store_review_admin_delete($rid) ? 1 : 0;
        } elseif ($action === 'edit') {
            $title = mb_substr(store_review_clean_text((string) ($_POST['title'] ?? '')), 0, 120);
            $body = store_review_clean_text((string) ($_POST['body'] ?? ''));
            if (mb_strlen($body) >= 3) {
                store_exec('UPDATE shop_reviews SET title = ?, body = ? WHERE id = ?', 'ssi', [$title, $body, $rid]);
                store_audit('review.edit', 'review', $rid);
                $n++;
            }
        }
    }
    flash_set($n ? 'ok' : 'error', $n ? 'انجام شد (' . $n . ' نظر).' : 'کاری انجام نشد.');
    redirect('shop-reviews.php' . (!empty($_POST['back']) ? '?' . ltrim((string) $_POST['back'], '?') : ''));
}

$status = $_GET['status'] ?? 'pending'; $status = in_array($status, ['pending', 'approved', 'rejected', 'all'], true) ? $status : 'pending';
$product = (int) ($_GET['product'] ?? 0); $rating = (int) ($_GET['rating'] ?? 0); $q = trim($_GET['q'] ?? '');
$where = ['1=1']; $types = ''; $params = [];
if ($status !== 'all') { $where[] = 'r.status = ?'; $types .= 's'; $params[] = $status; }
if ($product) { $where[] = 'r.product_id = ?'; $types .= 'i'; $params[] = $product; }
if ($rating >= 1 && $rating <= 5) { $where[] = 'r.rating = ?'; $types .= 'i'; $params[] = $rating; }
if ($q !== '') { $like = '%' . store_like_escape($q) . '%'; $where[] = '(r.body LIKE ? OR r.title LIKE ? OR u.name LIKE ? OR u.username LIKE ?)'; $types .= 'ssss'; array_push($params, $like, $like, $like, $like); }
$whereSql = implode(' AND ', $where);
$total = (int) store_val("SELECT COUNT(*) FROM shop_reviews r JOIN users u ON u.id = r.user_id WHERE $whereSql", $types, $params);
[$totalPages, $page, $offset] = paginate_args($total, 15, (int) ($_GET['page'] ?? 1));
$rows = store_rows("SELECT r.*, u.name AS user_name, u.username, u.email, p.title_fa, p.slug FROM shop_reviews r JOIN users u ON u.id = r.user_id JOIN shop_products p ON p.id = r.product_id WHERE $whereSql ORDER BY r.id DESC LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [15, $offset]));
$counts = array_column(store_rows('SELECT status, COUNT(*) c FROM shop_reviews GROUP BY status'), 'c', 'status');
$prods = store_rows('SELECT id, title_fa FROM shop_products WHERE deleted_at IS NULL ORDER BY title_fa LIMIT 500');
$back = http_build_query(array_filter(['status' => $status, 'product' => $product ?: '', 'rating' => $rating ?: '', 'q' => $q, 'page' => $page > 1 ? $page : '']));
$pageTitle = 'نظرات مشتریان';
require __DIR__ . '/_layout_top.php';
?>
<div class="a-btn-row" style="margin-bottom:14px;">
  <?php foreach (['pending' => 'در انتظار تأیید', 'approved' => 'تأییدشده', 'rejected' => 'ردشده', 'all' => 'همه'] as $k => $l): ?>
    <a class="a-btn<?= $status === $k ? ' a-btn--primary' : '' ?>" href="?status=<?= $k ?>"><?= e($l) ?><?= $k !== 'all' ? ' (' . fa_digits((string) (int) ($counts[$k] ?? 0)) . ')' : '' ?></a>
  <?php endforeach; ?>
</div>
<form class="a-card shop-filters" method="get"><input type="hidden" name="status" value="<?= e($status) ?>">
  <div class="a-field"><label>جستجو</label><input type="search" name="q" value="<?= e($q) ?>" placeholder="متن نظر یا نام کاربر"></div>
  <div class="a-field"><label>محصول</label><select name="product"><option value="">همه</option><?php foreach ($prods as $p): ?><option value="<?= (int) $p['id'] ?>"<?= $product === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['title_fa']) ?></option><?php endforeach; ?></select></div>
  <div class="a-field"><label>امتیاز</label><select name="rating"><option value="">همه</option><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"<?= $rating === $i ? ' selected' : '' ?>><?= fa_digits((string) $i) ?> ستاره</option><?php endfor; ?></select></div>
  <button class="a-btn a-btn--primary" type="submit">فیلتر</button>
</form>

<form method="post"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
<?php if (!$rows): ?><div class="a-card"><div class="a-empty">نظری در این بخش نیست.</div></div><?php else: ?>
  <div class="a-card" style="margin-bottom:12px;"><div class="a-btn-row">
    <label><input type="checkbox" data-select-all> انتخاب همه</label>
    <button class="a-btn a-btn--primary" type="submit" name="action" value="approve">تأیید انتخاب‌شده‌ها</button>
    <button class="a-btn" type="submit" name="action" value="reject">رد انتخاب‌شده‌ها</button>
    <button class="a-btn a-btn--danger" type="submit" name="action" value="delete" data-confirm="نظرهای انتخاب‌شده برای همیشه حذف بشن؟">حذف</button>
    <input type="text" name="note" placeholder="دلیل رد (اختیاری، به کاربر نشون داده می‌شه)" style="min-width:240px">
  </div></div>
  <?php foreach ($rows as $r): ?>
  <div class="a-card" style="margin-bottom:12px;">
    <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;">
      <input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" aria-label="انتخاب">
      <div style="flex:1;min-width:240px;">
        <div><b><?= e($r['user_name']) ?></b> <small class="a-muted" dir="ltr">@<?= e($r['username']) ?> · <?= e($r['email']) ?></small> <?= $r['is_verified_purchase'] ? '<span class="a-badge a-badge--ok">خریدار واقعی</span>' : '<span class="a-badge a-badge--muted">بدون خرید</span>' ?></div>
        <div>محصول: <a href="shop-product-edit.php?id=<?= (int) $r['product_id'] ?>"><?= e($r['title_fa']) ?></a> · امتیاز: <b><?= fa_digits((string) (int) $r['rating']) ?> ★</b> · <?= e(shop_date($r['created_at'])) ?> · <?= shop_badge($r['status'], ['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'rejected' => 'ردشده'][$r['status']]) ?></div>
        <?php if ($r['title'] !== ''): ?><div><b><?= e($r['title']) ?></b></div><?php endif; ?>
        <div class="shop-review-body"><?= e($r['body']) ?></div>
        <?php if ($r['admin_note']): ?><small class="a-muted">یادداشت مدیر: <?= e($r['admin_note']) ?></small><?php endif; ?>
        <details style="margin-top:8px"><summary class="a-btn a-btn--sm" style="display:inline-block">ویرایش متن</summary>
          <div class="a-field"><label>عنوان</label><input form="edit<?= (int) $r['id'] ?>" name="title" value="<?= e($r['title']) ?>" maxlength="120"></div>
          <div class="a-field"><label>متن</label><textarea form="edit<?= (int) $r['id'] ?>" name="body" rows="4"><?= e($r['body']) ?></textarea></div>
          <button class="a-btn a-btn--sm" type="submit" form="edit<?= (int) $r['id'] ?>">ذخیره‌ی ویرایش</button></details>
      </div>
      <div class="a-btn-row" style="flex-direction:column;align-items:stretch;">
        <?php if ($r['status'] !== 'approved'): ?><button class="a-btn a-btn--sm a-btn--primary" type="submit" name="action" value="approve" onclick="this.form.querySelectorAll('input[name=&quot;ids[]&quot;]').forEach(function(c){c.checked=false});this.form.insertAdjacentHTML('beforeend','<input type=hidden name=id value=<?= (int) $r['id'] ?>>')">تأیید</button><?php endif; ?>
        <?php if ($r['status'] !== 'rejected'): ?><button class="a-btn a-btn--sm" type="submit" name="action" value="reject" onclick="this.form.querySelectorAll('input[name=&quot;ids[]&quot;]').forEach(function(c){c.checked=false});this.form.insertAdjacentHTML('beforeend','<input type=hidden name=id value=<?= (int) $r['id'] ?>>')">رد</button><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?= shop_pager($page, $totalPages, array_filter(['status' => $status, 'product' => $product ?: '', 'rating' => $rating ?: '', 'q' => $q])) ?>
<?php endif; ?>
</form>
<?php foreach ($rows as $r): ?><form id="edit<?= (int) $r['id'] ?>" method="post" hidden><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>"></form><?php endforeach; ?>
<script src="assets/shop-admin.js?v=1"></script>
<?php require __DIR__ . '/_layout_bottom.php';
