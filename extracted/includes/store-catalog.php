<?php
/**
 * store-catalog.php — everything about READING the catalog: public
 * visibility rules, server-side pricing (the single source of truth for
 * every price shown or charged), search/sort, cards, sanitizer, JSON-LD.
 */
require_once __DIR__ . '/store-core.php';

/** One SQL fragment = one definition of "visible in the public shop". */
const STORE_PUBLIC_WHERE = "p.status = 'published' AND p.is_active = 1 AND p.deleted_at IS NULL AND c.is_active = 1";

/** SQL expression for the price actually charged right now (mirrors store_price()). */
const STORE_EFF_PRICE_SQL = "IF(p.is_free = 1, 0, IF(p.sale_price IS NOT NULL AND p.sale_price < p.price AND (p.sale_starts_at IS NULL OR p.sale_starts_at <= NOW()) AND (p.sale_ends_at IS NULL OR p.sale_ends_at >= NOW()), p.sale_price, p.price))";

/**
 * Authoritative price for a product row. Used by the product page, cards,
 * cart, checkout, order snapshot — so the price is identical everywhere.
 * @return array{list:int,final:int,on_sale:bool,pct:int,free:bool}
 */
function store_price(array $p): array
{
    $list = (int) $p['price'];
    if (!empty($p['is_free'])) {
        return ['list' => $list, 'final' => 0, 'on_sale' => false, 'pct' => 0, 'free' => true];
    }
    $final = $list;
    $onSale = false;
    if ($p['sale_price'] !== null && $p['sale_price'] !== '' && (int) $p['sale_price'] < $list) {
        $now = time();
        $startOk = empty($p['sale_starts_at']) || strtotime((string) $p['sale_starts_at']) <= $now;
        $endOk = empty($p['sale_ends_at']) || strtotime((string) $p['sale_ends_at']) >= $now;
        if ($startOk && $endOk) {
            $final = (int) $p['sale_price'];
            $onSale = true;
        }
    }
    $pct = ($onSale && $list > 0) ? (int) round((1 - $final / $list) * 100) : 0;
    return ['list' => $list, 'final' => $final, 'on_sale' => $onSale, 'pct' => $pct, 'free' => $final === 0];
}

function store_license_stock(int $productId): int
{
    return (int) store_val("SELECT COUNT(*) FROM shop_licenses WHERE product_id = ? AND status = 'available'", 'i', [$productId]);
}

function store_product_needs_license(array $p): bool
{
    return in_array($p['delivery_method'], ['license', 'file_license'], true);
}

function store_product_needs_file(array $p): bool
{
    return in_array($p['delivery_method'], ['file', 'file_license'], true);
}

// ---- Categories ---------------------------------------------------------------
function store_active_categories(): array
{
    return store_rows(
        "SELECT c.id, c.slug, c.name_fa, c.name_en, c.name_ar, c.short_desc_fa, c.short_desc_en, c.short_desc_ar, c.image, c.glyph,
                (SELECT COUNT(*) FROM shop_products p WHERE p.category_id = c.id AND p.status = 'published' AND p.is_active = 1 AND p.deleted_at IS NULL) AS product_count
         FROM shop_categories c WHERE c.is_active = 1 ORDER BY c.display_order, c.id"
    );
}

function store_category_by_slug(string $slug, bool $publicOnly = true): ?array
{
    return store_row('SELECT * FROM shop_categories WHERE slug = ?' . ($publicOnly ? ' AND is_active = 1' : '') . ' LIMIT 1', 's', [$slug]);
}

// ---- Product lists / search ------------------------------------------------------
function store_like_escape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

/**
 * @param array{q?:string,category_id?:int,type?:string,sort?:string,featured?:bool,exclude_id?:int} $f
 * @return array{rows:array,total:int}
 */
function store_list_products(array $f, int $page, int $perPage): array
{
    $where = [STORE_PUBLIC_WHERE];
    $types = '';
    $params = [];

    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        // Every word must match somewhere (title in 3 languages, summary, tags, brand, sku, focus keyword).
        foreach (array_slice(preg_split('/\s+/u', mb_substr($q, 0, 100)), 0, 6) as $term) {
            $like = '%' . store_like_escape($term) . '%';
            $where[] = '(p.title_fa LIKE ? OR p.title_en LIKE ? OR p.title_ar LIKE ? OR p.summary_fa LIKE ? OR p.tags LIKE ? OR p.brand LIKE ? OR p.sku LIKE ? OR p.focus_keyword LIKE ?)';
            $types .= 'ssssssss';
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
        }
    }
    if (!empty($f['category_id'])) {
        $where[] = 'p.category_id = ?';
        $types .= 'i';
        $params[] = (int) $f['category_id'];
    }
    if (!empty($f['type']) && isset(store_product_types()[$f['type']])) {
        $where[] = 'p.product_type = ?';
        $types .= 's';
        $params[] = $f['type'];
    }
    if (!empty($f['featured'])) {
        $where[] = 'p.is_featured = 1';
    }
    if (!empty($f['exclude_id'])) {
        $where[] = 'p.id <> ?';
        $types .= 'i';
        $params[] = (int) $f['exclude_id'];
    }
    $whereSql = implode(' AND ', $where);

    $order = match ($f['sort'] ?? 'newest') {
        'price_asc' => 'eff_price ASC, p.id DESC',
        'price_desc' => 'eff_price DESC, p.id DESC',
        'rating' => 'p.rating_avg DESC, p.rating_count DESC, p.id DESC',
        'bestselling' => 'p.sales_count DESC, p.id DESC',
        'featured' => 'p.is_featured DESC, p.display_order ASC, p.id DESC',
        default => 'COALESCE(p.published_at, p.created_at) DESC, p.id DESC',
    };

    $total = (int) store_val("SELECT COUNT(*) FROM shop_products p JOIN shop_categories c ON c.id = p.category_id WHERE $whereSql", $types, $params);
    [, , $offset] = paginate_args($total, $perPage, $page);

    $rows = store_rows(
        "SELECT p.id, p.slug, p.sku, p.product_type, p.delivery_method, p.title_fa, p.title_en, p.title_ar,
                p.summary_fa, p.summary_en, p.summary_ar, p.price, p.sale_price, p.sale_starts_at, p.sale_ends_at, p.is_free, p.show_price,
                p.is_featured, p.rating_avg, p.rating_count, p.sales_count, p.max_quantity,
                c.slug AS category_slug, c.name_fa AS category_name_fa, c.name_en AS category_name_en, c.name_ar AS category_name_ar,
                i.card_path, i.card_width, i.card_height, i.alt AS image_alt,
                " . STORE_EFF_PRICE_SQL . " AS eff_price
         FROM shop_products p
         JOIN shop_categories c ON c.id = p.category_id
         LEFT JOIN shop_product_images i ON i.product_id = p.id AND i.is_main = 1
         WHERE $whereSql ORDER BY $order LIMIT ? OFFSET ?",
        $types . 'ii',
        array_merge($params, [$perPage, $offset])
    );
    return ['rows' => $rows, 'total' => $total];
}

function store_product_by_slug(string $slug, bool $publicOnly = true): ?array
{
    $sql = 'SELECT p.*, c.slug AS category_slug, c.name_fa AS category_name_fa, c.name_en AS category_name_en, c.name_ar AS category_name_ar, c.is_active AS category_active
            FROM shop_products p JOIN shop_categories c ON c.id = p.category_id WHERE p.slug = ?';
    if ($publicOnly) {
        $sql .= ' AND ' . STORE_PUBLIC_WHERE;
    }
    return store_row($sql . ' LIMIT 1', 's', [$slug]);
}

function store_product_gallery(int $productId): array
{
    return store_rows('SELECT id, path, card_path, thumb_path, width, height, alt, caption, is_main, sort_order FROM shop_product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order, id', 'i', [$productId]);
}

function store_product_specs(int $productId): array
{
    return store_rows('SELECT * FROM shop_product_specs WHERE product_id = ? ORDER BY sort_order, id', 'i', [$productId]);
}

function store_product_faq(array $p): array
{
    $raw = json_decode((string) ($p['faq_json'] ?? ''), true);
    $out = [];
    foreach (is_array($raw) ? $raw : [] as $row) {
        $q = trim((string) ($row['q'] ?? ''));
        $a = trim((string) ($row['a'] ?? ''));
        if ($q !== '' && $a !== '') {
            $out[] = ['q' => $q, 'a' => $a];
        }
    }
    return $out;
}

function store_related_products(array $p, int $limit = 4): array
{
    $res = store_list_products(['category_id' => (int) $p['category_id'], 'exclude_id' => (int) $p['id'], 'sort' => 'bestselling'], 1, $limit);
    return $res['rows'];
}

/** Product is purchasable right now (visible + shop open + deliverable + stock). Returns [bool, reasonCode]. */
function store_product_sellable(array $p): array
{
    if (!store_shop_enabled()) {
        return [false, 'shop_closed'];
    }
    if ($p['status'] !== 'published' || !(int) $p['is_active'] || $p['deleted_at'] !== null) {
        return [false, 'unavailable'];
    }
    if (isset($p['category_active']) && !(int) $p['category_active']) {
        return [false, 'unavailable'];
    }
    if (store_product_needs_license($p) && store_license_stock((int) $p['id']) < 1) {
        return [false, 'out_of_stock'];
    }
    if (store_product_needs_file($p) && (int) store_val('SELECT COUNT(*) FROM shop_product_files WHERE product_id = ? AND is_active = 1', 'i', [(int) $p['id']]) < 1) {
        return [false, 'not_deliverable'];
    }
    return [true, ''];
}

// ---- Rendering helpers ------------------------------------------------------------
function store_stars_html(float $avg, string $class = ''): string
{
    $pct = max(0, min(100, $avg / 5 * 100));
    $star = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4 6.1 20.5l1.2-6.5L2.5 9.4l6.6-.9z"/></svg>';
    return '<span class="stars ' . e($class) . '" role="img" aria-label="' . e(number_format($avg, 1)) . ' / 5"><span class="stars__bg">' . str_repeat($star, 5) . '</span><span class="stars__fg" style="width:' . round($pct, 1) . '%">' . str_repeat($star, 5) . '</span></span>';
}

function store_product_url(string $slug): string
{
    return '/shop/' . rawurlencode($slug);
}

function store_card_html(array $p): string
{
    $price = store_price($p);
    $url = store_product_url($p['slug']);
    $title = store_lf($p, 'title');
    $img = !empty($p['card_path'])
        ? '<img src="/' . e($p['card_path']) . '" alt="' . e($p['image_alt'] ?: $title) . '" width="' . (int) $p['card_width'] . '" height="' . (int) $p['card_height'] . '" loading="lazy" decoding="async">'
        : '<span class="product-card__ph" aria-hidden="true">🛍️</span>';

    $badges = '';
    if ($price['on_sale']) {
        $badges .= '<span class="badge badge--sale">' . fa_digits((string) $price['pct']) . '٪ <span data-i18n="store.off">تخفیف</span></span>';
    }
    if (!empty($p['is_featured'])) {
        $badges .= '<span class="badge badge--featured" data-i18n="store.featured">ویژه</span>';
    }
    $types = store_product_types();
    $rating = (int) $p['rating_count'] > 0
        ? '<div class="product-card__rating">' . store_stars_html((float) $p['rating_avg']) . '<span>' . fa_digits(number_format((float) $p['rating_avg'], 1)) . ' (' . fa_digits((string) (int) $p['rating_count']) . ')</span></div>'
        : '';

    $priceHtml = '';
    if ((int) $p['show_price'] || $price['free']) {
        $priceHtml = ($price['on_sale'] ? '<s class="price price--old" data-amount="' . $price['list'] . '">' . e(store_money($price['list'])) . '</s>' : '') . store_price_html($price['final']);
    }

    return '<article class="product-card glass-card reveal">'
        . '<a class="product-card__media" href="' . e($url) . '" tabindex="-1" aria-hidden="true">' . $img . '<div class="product-card__badges">' . $badges . '</div></a>'
        . '<div class="product-card__body">'
        . '<a class="product-card__cat" href="/shop/category/' . e($p['category_slug']) . '"' . store_l10n(['name_fa' => $p['category_name_fa'], 'name_en' => $p['category_name_en'], 'name_ar' => $p['category_name_ar']], 'name') . '>' . e($p['category_name_fa']) . '</a>'
        . '<h3 class="product-card__title"><a href="' . e($url) . '"' . store_l10n($p, 'title') . '>' . e($title) . '</a></h3>'
        . ($p['summary_fa'] !== '' ? '<p class="product-card__summary"' . store_l10n($p, 'summary') . '>' . e($p['summary_fa']) . '</p>' : '')
        . $rating
        . '<div class="product-card__foot"><div class="product-card__price">' . $priceHtml . '</div>'
        . '<div class="product-card__actions"><a class="btn-ghost" href="' . e($url) . '" data-i18n="store.details">جزئیات</a>'
        . '<button type="button" class="btn-cart" data-add-to-cart="' . (int) $p['id'] . '" data-price="' . $price['final'] . '" aria-label="افزودن به سبد خرید" data-i18n-aria-label="store.addToCart">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg></button></div></div>'
        . '</div></article>';
}

function store_pagination_html(int $page, int $totalPages, string $baseUrl, array $query = []): string
{
    if ($totalPages <= 1) {
        return '';
    }
    $link = function (int $p, string $label, string $cls = '') use ($baseUrl, $query) {
        $q = $query;
        if ($p > 1) {
            $q['page'] = $p;
        } else {
            unset($q['page']);
        }
        $href = $baseUrl . ($q ? '?' . http_build_query($q) : '');
        return '<a class="pg-link ' . $cls . '" href="' . e($href) . '">' . $label . '</a>';
    };
    $html = '<nav class="pagination" aria-label="pagination">';
    if ($page > 1) {
        $html .= $link($page - 1, '‹', 'pg-link--nav');
    }
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($start > 1) {
        $html .= $link(1, fa_digits('1')) . ($start > 2 ? '<span class="pg-gap">…</span>' : '');
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= $i === $page ? '<span class="pg-link is-active" aria-current="page">' . fa_digits((string) $i) . '</span>' : $link($i, fa_digits((string) $i));
    }
    if ($end < $totalPages) {
        $html .= ($end < $totalPages - 1 ? '<span class="pg-gap">…</span>' : '') . $link($totalPages, fa_digits((string) $totalPages));
    }
    if ($page < $totalPages) {
        $html .= $link($page + 1, '›', 'pg-link--nav');
    }
    return $html . '</nav>';
}

// ---- HTML sanitizer (admin-written descriptions) ------------------------------------
function store_text_to_html(string $text): string
{
    $paras = preg_split('/\R{2,}/u', trim($text));
    $out = '';
    foreach ($paras as $p) {
        $p = trim($p);
        if ($p !== '') {
            $out .= '<p>' . nl2br(e($p), false) . '</p>';
        }
    }
    return $out;
}

/**
 * Allow-list sanitizer. Plain text (no tags) becomes <p> paragraphs. HTML is
 * rebuilt through DOMDocument: only whitelisted tags/attributes survive,
 * scripts/styles/iframes/forms and every on*="" handler are removed, and
 * links may only be http(s)/mailto/tel or site-relative.
 */
function store_sanitize_html(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }
    if (!preg_match('/<[a-z!\/][^>]*>/i', $html)) {
        return store_text_to_html($html);
    }
    if (!class_exists('DOMDocument')) {
        return store_text_to_html(strip_tags($html));
    }
    $allowed = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'blockquote' => [], 'code' => [], 'pre' => [], 'hr' => [], 'a' => ['href', 'title'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [], 'span' => [], 'div' => [], 'small' => [], 'mark' => [],
    ];
    $drop = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math', 'link', 'meta', 'base', 'noscript', 'template', 'audio', 'video', 'canvas', 'frame', 'frameset', 'applet'];

    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="__root">' . $html . '</div></body></html>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('__root');
    if (!$root) {
        return store_text_to_html(strip_tags($html));
    }

    $clean = function (DOMNode $node) use (&$clean, $allowed, $drop, $doc): void {
        for ($child = $node->firstChild; $child; $child = $next) {
            $next = $child->nextSibling;
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (in_array($tag, $drop, true)) {
                $node->removeChild($child);
                continue;
            }
            $clean($child);
            if (!isset($allowed[$tag])) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            /** @var DOMElement $child */
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->nodeName);
                if (!in_array($name, $allowed[$tag], true)) {
                    $child->removeAttribute($attr->nodeName);
                }
            }
            if ($tag === 'a') {
                $href = trim((string) $child->getAttribute('href'));
                $okHref = preg_match('#^(https?://|mailto:|tel:|/(?!/)|\#)#i', $href) && !preg_match('/[\x00-\x1f]/', $href);
                if (!$okHref) {
                    $child->removeAttribute('href');
                } else {
                    $child->setAttribute('rel', 'noopener noreferrer nofollow');
                    if (preg_match('#^https?://#i', $href)) {
                        $child->setAttribute('target', '_blank');
                    }
                }
            }
        }
    };
    $clean($root);

    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }
    return trim($out);
}

// ---- JSON-LD -------------------------------------------------------------------------
function store_product_jsonld(array $p, array $gallery, array $reviews, bool $inStock): string
{
    $url = site_url(store_product_url($p['slug']));
    $price = store_price($p);
    [$schemaPrice, $cur] = store_schema_price($price['final']);
    $images = array_map(fn($g) => site_url('/' . $g['path']), $gallery);
    $desc = trim(strip_tags(store_lf($p, 'summary') ?: store_lf($p, 'description')));

    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        '@id' => $url . '#product',
        'name' => store_lf($p, 'title'),
        'url' => $url,
        'sku' => $p['sku'],
        'category' => $p['category_name_fa'],
    ];
    if ($desc !== '') {
        $data['description'] = mb_substr($desc, 0, 500);
    }
    if ($images) {
        $data['image'] = $images;
    }
    $data['brand'] = ['@type' => 'Brand', 'name' => $p['brand'] ?: SITE_NAME];
    if (!empty($p['version'])) {
        $data['version'] = $p['version'];
    }
    $offer = [
        '@type' => 'Offer', 'url' => $url, 'price' => $schemaPrice, 'priceCurrency' => $cur,
        'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'itemCondition' => 'https://schema.org/NewCondition',
        'seller' => ['@type' => 'Person', 'name' => SITE_NAME],
    ];
    if ($price['on_sale'] && !empty($p['sale_ends_at'])) {
        $offer['priceValidUntil'] = substr((string) $p['sale_ends_at'], 0, 10);
    }
    $data['offers'] = $offer;
    // Only REAL, APPROVED ratings ever reach the schema.
    if ((int) $p['rating_count'] > 0) {
        $data['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => number_format((float) $p['rating_avg'], 1, '.', ''), 'reviewCount' => (int) $p['rating_count'], 'bestRating' => 5, 'worstRating' => 1];
        $rv = [];
        foreach (array_slice($reviews, 0, 10) as $r) {
            $rv[] = [
                '@type' => 'Review',
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $r['rating'], 'bestRating' => 5, 'worstRating' => 1],
                'author' => ['@type' => 'Person', 'name' => store_public_name((string) $r['user_name'])],
                'datePublished' => substr((string) $r['created_at'], 0, 10),
                'reviewBody' => mb_substr((string) $r['body'], 0, 500),
            ] + ($r['title'] !== '' ? ['name' => $r['title']] : []);
        }
        if ($rv) {
            $data['review'] = $rv;
        }
    }
    return json_ld($data);
}

/** Privacy: show first name + initial only. */
function store_public_name(string $full): string
{
    $parts = preg_split('/\s+/u', trim($full));
    $first = $parts[0] ?? '';
    return $first !== '' ? $first . (isset($parts[1]) ? ' ' . mb_substr($parts[1], 0, 1) . '.' : '') : '—';
}

function store_category_jsonld(array $cat, array $products): string
{
    $url = site_url('/shop/category/' . $cat['slug']);
    return json_ld([
        '@context' => 'https://schema.org', '@type' => 'CollectionPage', '@id' => $url . '#collection',
        'name' => $cat['name_fa'], 'url' => $url,
        'description' => $cat['seo_description'] ?: $cat['short_desc_fa'],
        'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => array_values(array_map(fn($i, $p) => [
            '@type' => 'ListItem', 'position' => $i + 1, 'url' => site_url(store_product_url($p['slug'])), 'name' => $p['title_fa'],
        ], array_keys($products), $products))],
    ]);
}

/** Meta description fallback when the admin left the field empty. */
function store_meta_description(array $p): string
{
    $d = trim((string) ($p['seo_description'] ?? ''));
    if ($d !== '') {
        return $d;
    }
    $src = trim(strip_tags((string) ($p['summary_fa'] ?: $p['description_fa'])));
    return mb_substr(preg_replace('/\s+/u', ' ', $src), 0, 155);
}
