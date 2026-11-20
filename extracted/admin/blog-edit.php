<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();
$conn = db();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$post = $id ? $conn->query("SELECT * FROM blog_posts WHERE id=$id")->fetch_assoc() : null;
if ($id && !$post) {
    flash_set('error', 'مقاله پیدا نشد.');
    redirect('blog.php');
}

$errors = [];

// IMPORTANT: POST handling (and any redirect()) must run before
// _layout_top.php prints any HTML — see blog-categories.php for why.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_or_die();

    $data = [
        'category_id' => (int) ($_POST['category_id'] ?? 0) ?: null,
        'glyph' => trim($_POST['glyph'] ?? '📝') ?: '📝',
        'title_fa' => trim($_POST['title_fa'] ?? ''),
        'title_en' => trim($_POST['title_en'] ?? ''),
        'title_ar' => trim($_POST['title_ar'] ?? ''),
        'excerpt_fa' => trim($_POST['excerpt_fa'] ?? ''),
        'excerpt_en' => trim($_POST['excerpt_en'] ?? ''),
        'excerpt_ar' => trim($_POST['excerpt_ar'] ?? ''),
        'content_fa' => trim($_POST['content_fa'] ?? ''),
        'content_en' => trim($_POST['content_en'] ?? ''),
        'content_ar' => trim($_POST['content_ar'] ?? ''),
        'tags' => trim($_POST['tags'] ?? ''),
        'video_url' => trim($_POST['video_url'] ?? '') ?: null,
        'meta_title_fa' => trim($_POST['meta_title_fa'] ?? '') ?: null,
        'meta_title_en' => trim($_POST['meta_title_en'] ?? '') ?: null,
        'meta_title_ar' => trim($_POST['meta_title_ar'] ?? '') ?: null,
        'meta_description_fa' => trim($_POST['meta_description_fa'] ?? '') ?: null,
        'meta_description_en' => trim($_POST['meta_description_en'] ?? '') ?: null,
        'meta_description_ar' => trim($_POST['meta_description_ar'] ?? '') ?: null,
        'read_time_min' => max(1, (int) ($_POST['read_time_min'] ?? 4)),
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'status' => $_POST['status'] === 'published' ? 'published' : 'draft',
    ];

    foreach (['title_fa', 'title_en', 'title_ar', 'excerpt_fa', 'excerpt_en', 'excerpt_ar', 'content_fa', 'content_en', 'content_ar'] as $req) {
        if ($data[$req] === '') {
            $errors[] = 'همه‌ی فیلدهای عنوان، خلاصه و متن (هر ۳ زبان) الزامی‌ان.';
            break;
        }
    }

    if (!$errors) {
        try {
            $uploadedPath = upload_image($_FILES['featured_image'] ?? [], 'blog');
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
            $uploadedPath = null;
        }
    }

    if (!$errors) {
        $slugSource = trim($_POST['slug'] ?? '') !== '' ? $_POST['slug'] : $data['title_en'];
        $baseSlug = slugify($slugSource);

        if ($data['is_featured']) {
            $conn->query('UPDATE blog_posts SET is_featured = 0'); // only one featured post at a time
        }

        if ($post) {
            $slug = unique_slug('blog_posts', $baseSlug, $id);
            $image = $uploadedPath ?: $post['featured_image'];
            $publishedAt = ($data['status'] === 'published' && !$post['published_at']) ? date('Y-m-d H:i:s') : $post['published_at'];

            $stmt = $conn->prepare(
                'UPDATE blog_posts SET category_id=?, slug=?, glyph=?, title_fa=?, title_en=?, title_ar=?,
                 excerpt_fa=?, excerpt_en=?, excerpt_ar=?, content_fa=?, content_en=?, content_ar=?, tags=?, video_url=?,
                 featured_image=?, meta_title_fa=?, meta_title_en=?, meta_title_ar=?,
                 meta_description_fa=?, meta_description_en=?, meta_description_ar=?,
                 read_time_min=?, is_featured=?, status=?, published_at=? WHERE id=?'
            );
            $stmt->bind_param(
                'issssssssssssssssssssiissi',
                $data['category_id'], $slug, $data['glyph'], $data['title_fa'], $data['title_en'], $data['title_ar'],
                $data['excerpt_fa'], $data['excerpt_en'], $data['excerpt_ar'], $data['content_fa'], $data['content_en'], $data['content_ar'], $data['tags'], $data['video_url'],
                $image, $data['meta_title_fa'], $data['meta_title_en'], $data['meta_title_ar'],
                $data['meta_description_fa'], $data['meta_description_en'], $data['meta_description_ar'],
                $data['read_time_min'], $data['is_featured'], $data['status'], $publishedAt, $id
            );
            $stmt->execute();
            $stmt->close();
            flash_set('ok', 'مقاله ذخیره شد.');
            redirect('blog-edit.php?id=' . $id);
        } else {
            $slug = unique_slug('blog_posts', $baseSlug);
            $publishedAt = $data['status'] === 'published' ? date('Y-m-d H:i:s') : null;
            $authorId = $admin['id'];

            $stmt = $conn->prepare(
                'INSERT INTO blog_posts (category_id, author_id, slug, glyph, title_fa, title_en, title_ar,
                 excerpt_fa, excerpt_en, excerpt_ar, content_fa, content_en, content_ar, tags, video_url, featured_image,
                 meta_title_fa, meta_title_en, meta_title_ar, meta_description_fa, meta_description_en, meta_description_ar,
                 read_time_min, is_featured, status, published_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->bind_param(
                'iissssssssssssssssssssiiss',
                $data['category_id'], $authorId, $slug, $data['glyph'], $data['title_fa'], $data['title_en'], $data['title_ar'],
                $data['excerpt_fa'], $data['excerpt_en'], $data['excerpt_ar'], $data['content_fa'], $data['content_en'], $data['content_ar'], $data['tags'], $data['video_url'],
                $uploadedPath, $data['meta_title_fa'], $data['meta_title_en'], $data['meta_title_ar'],
                $data['meta_description_fa'], $data['meta_description_en'], $data['meta_description_ar'],
                $data['read_time_min'], $data['is_featured'], $data['status'], $publishedAt
            );
            $stmt->execute();
            $newId = $stmt->insert_id;
            $stmt->close();
            flash_set('ok', 'مقاله ایجاد شد.');
            redirect('blog-edit.php?id=' . $newId);
        }
    }
}

$categories = $conn->query('SELECT id, name_fa FROM blog_categories ORDER BY name_fa');
$v = fn($field) => e($post[$field] ?? ($_POST[$field] ?? ''));

$pageTitle = $post ? 'ویرایش مقاله' : 'مقاله‌ی جدید';
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
      <div class="a-field"><label>دسته‌بندی</label>
        <select name="category_id">
          <option value="">— بدون دسته —</option>
          <?php while ($c = $categories->fetch_assoc()): ?>
            <option value="<?= (int) $c['id'] ?>" <?= ($post['category_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= e($c['name_fa']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="a-field"><label>ایموجی کاور (glyph)</label><input type="text" name="glyph" value="<?= $v('glyph') ?: '📝' ?>" maxlength="10"></div>
      <div class="a-field"><label>Slug (خالی = خودکار از عنوان انگلیسی)</label><input type="text" name="slug" value="<?= e($post['slug'] ?? '') ?>"></div>
      <div class="a-field"><label>زمان مطالعه (دقیقه)</label><input type="number" name="read_time_min" value="<?= $v('read_time_min') ?: 4 ?>" min="1"></div>
      <div class="a-field"><label>برچسب‌ها (با کاما جدا کن)</label><input type="text" name="tags" value="<?= e($post['tags'] ?? '') ?>" placeholder="Python, n8n, RAG"></div>
      <div class="a-field"><label>تصویر کاور (اختیاری — وسط مقاله نمایش داده می‌شه)</label><input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp,image/gif">
        <?php if (!empty($post['featured_image'])): ?><small>فعلی: <?= e($post['featured_image']) ?></small><?php endif; ?>
      </div>
      <div class="a-field"><label>لینک ویدیو (اختیاری — بالای مقاله نمایش داده می‌شه)</label><input type="url" name="video_url" value="<?= e($post['video_url'] ?? '') ?>" placeholder="لینک YouTube/Aparat یا فایل mp4">
        <small>لینک یوتیوب/آپارات embed یا مستقیم به یک فایل mp4.</small>
      </div>
      <div class="a-field"><label>وضعیت</label>
        <select name="status">
          <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
          <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>منتشرشده</option>
        </select>
      </div>
      <div class="a-field a-field--check"><input type="checkbox" name="is_featured" id="is_featured" <?= !empty($post['is_featured']) ? 'checked' : '' ?>><label for="is_featured" style="margin:0;">مقاله‌ی ویژه (بالای صفحه‌ی وبلاگ)</label></div>
    </div>
  </div>

  <?php foreach (['fa' => 'فارسی', 'en' => 'انگلیسی', 'ar' => 'عربی'] as $lang => $label): ?>
  <div class="a-card" style="margin-bottom:16px;">
    <strong style="font-size:14px;display:block;margin-bottom:14px;">محتوای <?= $label ?></strong>
    <div class="a-field"><label>عنوان</label><input type="text" name="title_<?= $lang ?>" value="<?= $v("title_$lang") ?>" required></div>
    <div class="a-field"><label>خلاصه (برای کارت‌ها)</label><textarea name="excerpt_<?= $lang ?>" style="min-height:60px;" required><?= $v("excerpt_$lang") ?></textarea></div>
    <div class="a-field"><label>متن کامل (هر پاراگراف رو با یک خط خالی از بعدی جدا کن)</label><textarea name="content_<?= $lang ?>" style="min-height:220px;" required><?= $v("content_$lang") ?></textarea></div>
    <div class="a-form-grid">
      <div class="a-field"><label>Meta Title (سئو — اختیاری)</label><input type="text" name="meta_title_<?= $lang ?>" value="<?= $v("meta_title_$lang") ?>"></div>
      <div class="a-field"><label>Meta Description (سئو — اختیاری)</label><input type="text" name="meta_description_<?= $lang ?>" value="<?= $v("meta_description_$lang") ?>"></div>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="a-btn-row">
    <button type="submit" class="a-btn a-btn--primary">ذخیره‌ی مقاله</button>
    <a href="blog.php" class="a-btn">انصراف</a>
    <?php if ($post): ?><a href="../blog-post.php?slug=<?= urlencode($post['slug']) ?>" target="_blank" class="a-btn">مشاهده در سایت ↗</a><?php endif; ?>
  </div>
</form>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
