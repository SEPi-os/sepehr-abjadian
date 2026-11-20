<?php
/**
 * store-images.php — product/category image pipeline.
 * Every uploaded image is validated by its REAL bytes, decoded with GD,
 * re-encoded as WebP in three sizes (full / card / thumb) and stored under
 * a random name. Re-encoding strips EXIF/metadata and any embedded payload.
 * If this server's GD has no WebP support we say so clearly — we never
 * pretend a conversion happened.
 */
require_once __DIR__ . '/store-core.php';

function store_webp_supported(): bool
{
    return function_exists('imagewebp') && function_exists('imagecreatefromstring')
        && (gd_info()['WebP Support'] ?? false);
}

/** [[tmp_name,error,size,name], ...] from a multi-file <input name="x[]"> */
function store_normalize_files(?array $f): array
{
    if (!$f || !isset($f['name'])) {
        return [];
    }
    if (!is_array($f['name'])) {
        return ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => $name, 'type' => $f['type'][$i] ?? '', 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

function store_upload_error_text(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم فایل از محدودیت سرور (upload_max_filesize / post_max_size در php.ini) بیشتره.',
        UPLOAD_ERR_PARTIAL => 'آپلود ناقص انجام شد؛ دوباره تلاش کن.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'سرور نتونست فایل موقت رو بنویسه (پوشه‌ی tmp یا دسترسی نوشتن).',
        default => 'آپلود با خطا مواجه شد (کد ' . $code . ').',
    };
}

/**
 * Validates + converts one uploaded image. Returns
 * ['path','card_path','thumb_path','width','height','card_width','card_height'] (site-relative URLs).
 * @throws RuntimeException with a Persian, user-presentable message.
 */
function store_process_image(array $file, string $subdir = ''): array
{
    if (!store_webp_supported()) {
        throw new RuntimeException('کتابخانه‌ی GD سرور از WebP پشتیبانی نمی‌کنه، پس تبدیل تصویر ممکن نیست. از هاست بخواه GD با WebP یا Imagick فعال کنه.');
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(store_upload_error_text((int) ($file['error'] ?? 4)));
    }
    if (($file['size'] ?? 0) > STORE_MAX_IMAGE_MB * 1024 * 1024) {
        throw new RuntimeException('حجم هر تصویر حداکثر ' . STORE_MAX_IMAGE_MB . ' مگابایت می‌تونه باشه.');
    }
    $tmp = $file['tmp_name'];
    if (!is_uploaded_file($tmp) && !(defined('STORE_TESTING') && STORE_TESTING && is_file($tmp))) {
        throw new RuntimeException('فایل آپلودشده معتبر نیست.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $tmp);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        throw new RuntimeException('فقط تصویر JPG، PNG، WEBP یا GIF قابل قبوله.');
    }
    $info = @getimagesize($tmp);
    if (!$info || $info[0] < 16 || $info[1] < 16) {
        throw new RuntimeException('فایل یک تصویر معتبر نیست.');
    }
    if ($info[0] > 8000 || $info[1] > 8000 || ($info[0] * $info[1]) > 40_000_000) {
        throw new RuntimeException('ابعاد تصویر بیش از حد بزرگه (حداکثر ۸۰۰۰×۸۰۰۰ و ۴۰ مگاپیکسل).');
    }

    $data = file_get_contents($tmp);
    $src = @imagecreatefromstring($data);
    unset($data);
    if (!$src) {
        throw new RuntimeException('خوندن تصویر ممکن نشد؛ فایل خراب یا ناسازگاره.');
    }

    // Respect EXIF orientation of camera JPEGs (metadata itself is dropped on re-encode).
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $orient = (int) ($exif['Orientation'] ?? 1);
        $angle = [3 => 180, 6 => -90, 8 => 90][$orient] ?? 0;
        if ($angle !== 0) {
            $rot = imagerotate($src, $angle, 0);
            if ($rot) {
                imagedestroy($src);
                $src = $rot;
            }
        }
    }
    imagepalettetotruecolor($src);
    imagealphablending($src, true);
    imagesavealpha($src, true);

    $dir = STORE_IMAGE_DIR . ($subdir !== '' ? '/' . trim($subdir, '/') : '');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        imagedestroy($src);
        throw new RuntimeException('پوشه‌ی ' . STORE_IMAGE_URL . ' قابل ساخت/نوشتن نیست؛ دسترسی پوشه‌ی uploads رو بررسی کن.');
    }

    $base = bin2hex(random_bytes(16));
    $sw = imagesx($src);
    $sh = imagesy($src);
    $out = [];
    $written = [];
    try {
        foreach (['path' => [1600, 82, ''], 'card_path' => [600, 80, '-c'], 'thumb_path' => [160, 76, '-t']] as $key => [$maxW, $quality, $suffix]) {
            $scale = min(1, $maxW / max(1, $sw));
            $tw = max(1, (int) round($sw * $scale));
            $th = max(1, (int) round($sh * $scale));
            $dst = imagecreatetruecolor($tw, $th);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $sw, $sh);
            $file = $dir . '/' . $base . $suffix . '.webp';
            if (!imagewebp($dst, $file, $quality)) {
                imagedestroy($dst);
                throw new RuntimeException('نوشتن فایل WebP ناموفق بود (دسترسی نوشتن یا فضای دیسک).');
            }
            @chmod($file, 0644);
            $written[] = $file;
            imagedestroy($dst);
            $out[$key] = STORE_IMAGE_URL . ($subdir !== '' ? '/' . trim($subdir, '/') : '') . '/' . $base . $suffix . '.webp';
            if ($key === 'path') {
                $out['width'] = $tw;
                $out['height'] = $th;
            }
            if ($key === 'card_path') {
                $out['card_width'] = $tw;
                $out['card_height'] = $th;
            }
        }
    } catch (Throwable $ex) {
        foreach ($written as $w) {
            @unlink($w);
        }
        imagedestroy($src);
        throw $ex instanceof RuntimeException ? $ex : new RuntimeException($ex->getMessage());
    }
    imagedestroy($src);
    return $out;
}

/** Deletes an image file only if it really is inside the products upload dir. */
function store_unlink_image(?string $url): void
{
    if (!$url || !str_starts_with($url, STORE_IMAGE_URL . '/')) {
        return;
    }
    $abs = BASE_PATH . '/' . $url;
    $real = realpath($abs);
    $root = realpath(STORE_IMAGE_DIR);
    if ($real && $root && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_file($real)) {
        @unlink($real);
    }
}

function store_delete_image_row(array $img): void
{
    foreach (['path', 'card_path', 'thumb_path'] as $k) {
        store_unlink_image($img[$k] ?? null);
    }
}

/** Adds one processed image to a product; the first image ever added becomes the main one. */
function store_add_product_image(int $productId, array $file, string $alt = '', bool $makeMain = false): int
{
    $proc = store_process_image($file);
    $hasMain = (int) store_val('SELECT COUNT(*) FROM shop_product_images WHERE product_id = ? AND is_main = 1', 'i', [$productId]) > 0;
    $isMain = ($makeMain || !$hasMain) ? 1 : 0;
    $sort = (int) store_val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM shop_product_images WHERE product_id = ?', 'i', [$productId]);
    try {
        if ($isMain) {
            store_exec('UPDATE shop_product_images SET is_main = 0 WHERE product_id = ?', 'i', [$productId]);
        }
        store_exec(
            'INSERT INTO shop_product_images (product_id, path, card_path, thumb_path, width, height, card_width, card_height, alt, is_main, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            'isssiiiisii',
            [$productId, $proc['path'], $proc['card_path'], $proc['thumb_path'], $proc['width'], $proc['height'], $proc['card_width'], $proc['card_height'], mb_substr($alt, 0, 255), $isMain, $sort]
        );
    } catch (Throwable $ex) {
        store_delete_image_row($proc);
        throw $ex;
    }
    return store_last_id();
}

/** Makes sure a product has exactly one main image (call after deletes). */
function store_ensure_main_image(int $productId): void
{
    $n = (int) store_val('SELECT COUNT(*) FROM shop_product_images WHERE product_id = ? AND is_main = 1', 'i', [$productId]);
    if ($n === 1) {
        return;
    }
    store_exec('UPDATE shop_product_images SET is_main = 0 WHERE product_id = ?', 'i', [$productId]);
    store_exec('UPDATE shop_product_images SET is_main = 1 WHERE product_id = ? ORDER BY sort_order, id LIMIT 1', 'i', [$productId]);
}
