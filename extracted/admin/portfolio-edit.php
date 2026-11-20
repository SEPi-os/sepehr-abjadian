<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$conn = db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$item = $id ? $conn->query("SELECT * FROM portfolio_items WHERE id=$id")->fetch_assoc() : null;
if ($id && !$item) {
    flash_set('error', 'نمونه‌کار پیدا نشد.');
    redirect('portfolio.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();

    $data = [
        'category' => $_POST['category'] === 'ai' ? 'ai' : 'web',
        'title_fa' => trim($_POST['title_fa'] ?? ''),
        'title_en' => trim($_POST['title_en'] ?? ''),
        'title_ar' => trim($_POST['title_ar'] ?? ''),
        'tag_fa' => trim($_POST['tag_fa'] ?? '') ?: null,
        'tag_en' => trim($_POST['tag_en'] ?? '') ?: null,
        'tag_ar' => trim($_POST['tag_ar'] ?? '') ?: null,
        'description_fa' => trim($_POST['description_fa'] ?? ''),
        'description_en' => trim($_POST['description_en'] ?? ''),
        'description_ar' => trim($_POST['description_ar'] ?? ''),
        'tech_stack' => trim($_POST['tech_stack'] ?? '') ?: null,
        'glyph' => trim($_POST['glyph'] ?? '') ?: '📦',
        'project_url' => trim($_POST['project_url'] ?? '') ?: null,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'display_order' => (int) ($_POST['display_order'] ?? 0),
        'status' => $_POST['status'] === 'draft' ? 'draft' : 'published',
    ];

    foreach (['title_fa', 'title_en', 'title_ar', 'description_fa', 'description_en', 'description_ar'] as $req) {
        if ($data[$req] === '') {
            $errors[] = 'عنوان و توضیح برای هر ۳ زبان الزامیه.';
            break;
        }
    }

    if (!$errors) {
        try {
            $uploadedPath = upload_image($_FILES['cover_image'] ?? [], 'portfolio');
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
            $uploadedPath = null;
        }
    }

    if (!$errors) {
        $slugSource = trim($_POST['slug'] ?? '') !== '' ? $_POST['slug'] : $data['title_en'];
        $baseSlug = slugify($slugSource);

        if ($item) {
            $slug = unique_slug('portfolio_items', $baseSlug, $id);
            $image = $uploadedPath ?: $item['cover_image'];
            $stmt = $conn->prepare(
                'UPDATE portfolio_items SET category=?, slug=?, title_fa=?, title_en=?, title_ar=?,
                 tag_fa=?, tag_en=?, tag_ar=?, description_fa=?, description_en=?, description_ar=?,
                 tech_stack=?, cover_image=?, glyph=?, project_url=?, is_featured=?, display_order=?, status=? WHERE id=?'
            );
            $stmt->bind_param(
                'sssssssssssssssiisi',
                $data['category'], $slug, $data['title_fa'], $data['title_en'], $data['title_ar'],
                $data['tag_fa'], $data['tag_en'], $data['tag_ar'], $data['description_fa'], $data['description_en'], $data['description_ar'],
                $data['tech_stack'], $image, $data['glyph'], $data['project_url'], $data['is_featured'], $data['display_order'], $data['status'], $id
            );
            $stmt->execute();
            $stmt->close();
            flash_set('ok', 'نمونه‌کار ذخیره شد.');
            redirect('portfolio-edit.php?id=' . $id);
        } else {
            $slug = unique_slug('portfolio_items', $baseSlug);
            $stmt = $conn->prepare(
                'INSERT INTO portfolio_items (category, slug, title_fa, title_en, title_ar, tag_fa, tag_en, tag_ar,
                 description_fa, description_en, description_ar, tech_stack, cover_image, glyph, project_url,
                 is_featured, display_order, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->bind_param(
                'sssssssssssssssiis',
                $data['category'], $slug, $data['title_fa'], $data['title_en'], $data['title_ar'],
                $data['tag_fa'], $data['tag_en'], $data['tag_ar'], $data['description_fa'], $data['description_en'], $data['description_ar'],
                $data['tech_stack'], $uploadedPath, $data['glyph'], $data['project_url'], $data['is_featured'], $data['display_order'], $data['status']
            );
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            flash_set('ok', 'نمونه‌کار ایجاد شد.');
            redirect('portfolio-edit.php?id=' . $newId);
        }
    }
}

$v = fn($field) => e($item[$field] ?? ($_POST[$field] ?? ''));
$pageTitle = $item ? 'ویرایش نمونه‌کار' : 'نمونه‌کار جدید';
require __DIR__ . '/_layout_top.php';
?>

<?php if ($errors): ?>
  <div class="a-alert a-alert--error"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <div class="a-card" style="margin-bottom:16px;">
    <div class="a-form-grid">
      <div class="a-field"><label>دسته (برای فیلتر صفحه‌ی نمونه‌کارها)</label>
        <select name="category">
          <option value="web" <?= ($item['category'] ?? '') === 'web' ? 'selected' : '' ?>>وب و فرانت‌اند</option>
          <option value="ai" <?= ($item['category'] ?? '') === 'ai' ? 'selected' : '' ?>>هوش مصنوعی و اتوماسیون</option>
        </select>
      </div>
      <div class="a-field"><label>Slug (خالی = خودکار از عنوان انگلیسی)</label><input type="text" name="slug" value="<?= e($item['slug'] ?? '') ?>"></div>
      <div class="a-field"><label>ایموجی کاور (اگه تصویر آپلود نکنی، همین نشون داده می‌شه)</label><input type="text" name="glyph" value="<?= $v('glyph') ?: '📦' ?>" maxlength="10"></div>
      <div class="a-field"><label>تصویر کاور (اختیاری)</label><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif">
        <?php if (!empty($item['cover_image'])): ?><small>فعلی: <?= e($item['cover_image']) ?></small><?php endif; ?>
      </div>
      <div class="a-field"><label>پشته‌ی فنی (با کاما جدا کن)</label><input type="text" name="tech_stack" value="<?= e($item['tech_stack'] ?? '') ?>" placeholder="Python, PySide6, faster-whisper"></div>
      <div class="a-field"><label>لینک پروژه (اختیاری)</label><input type="url" name="project_url" value="<?= e($item['project_url'] ?? '') ?>" placeholder="https://t.me/sepehrworld_bot"></div>
      <div class="a-field"><label>ترتیب نمایش (عدد کمتر = بالاتر)</label><input type="number" name="display_order" value="<?= $v('display_order') ?: 0 ?>"></div>
      <div class="a-field"><label>وضعیت</label>
        <select name="status">
          <option value="published" <?= ($item['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>منتشرشده</option>
          <option value="draft" <?= ($item['status'] ?? '') === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
        </select>
      </div>
      <div class="a-field a-field--check"><input type="checkbox" name="is_featured" id="is_featured" <?= !empty($item['is_featured']) ? 'checked' : '' ?>><label for="is_featured" style="margin:0;">نمایش در بخش نمونه‌کارهای صفحه‌ی اصلی</label></div>
    </div>
  </div>

  <?php foreach (['fa' => 'فارسی', 'en' => 'انگلیسی', 'ar' => 'عربی'] as $lang => $label): ?>
  <div class="a-card" style="margin-bottom:16px;">
    <strong style="font-size:14px;display:block;margin-bottom:14px;">محتوای <?= $label ?></strong>
    <div class="a-field"><label>عنوان</label><input type="text" name="title_<?= $lang ?>" value="<?= $v("title_$lang") ?>" required></div>
    <div class="a-field"><label>برچسب کوچک (kicker) — مثلاً «دستیار هوش مصنوعی»</label><input type="text" name="tag_<?= $lang ?>" value="<?= $v("tag_$lang") ?>"></div>
    <div class="a-field"><label>توضیح کوتاه (۱-۲ جمله)</label><textarea name="description_<?= $lang ?>" required><?= $v("description_$lang") ?></textarea></div>
  </div>
  <?php endforeach; ?>

  <div class="a-btn-row">
    <button type="submit" class="a-btn a-btn--primary">ذخیره</button>
    <a href="portfolio.php" class="a-btn">انصراف</a>
  </div>
</form>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
