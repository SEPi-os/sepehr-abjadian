<?php
/** Shared renderer for /shop and /shop/category/{slug} (search, filters, sort, grid, pagination, SEO). */
require_once __DIR__ . '/store-layout.php';
require_once __DIR__ . '/store-catalog.php';

function store_render_listing(?array $category): void
{
    public_session_start();
    $user = current_user();
    $perPage = max(4, min(48, store_setting_int('shop_per_page', 12)));
    $q = trim((string) ($_GET['q'] ?? ''));
    $type = (string) ($_GET['type'] ?? '');
    $type = isset(store_product_types()[$type]) ? $type : '';
    $sort = (string) ($_GET['sort'] ?? 'newest');
    $sort = in_array($sort, ['newest', 'price_asc', 'price_desc', 'rating', 'bestselling', 'featured'], true) ? $sort : 'newest';
    $page = max(1, (int) ($_GET['page'] ?? 1));

    $filters = ['q' => $q, 'type' => $type, 'sort' => $sort];
    if ($category) {
        $filters['category_id'] = (int) $category['id'];
    }
    $res = store_list_products($filters, $page, $perPage);
    [$totalPages, $page] = paginate_args($res['total'], $perPage, $page);
    $categories = store_active_categories();

    $baseUrl = $category ? '/shop/category/' . $category['slug'] : '/shop';
    $isFiltered = $q !== '' || $type !== '' || $sort !== 'newest';
    $canonical = site_url($baseUrl) . (!$isFiltered && $page > 1 ? '?page=' . $page : '');
    $robots = $isFiltered ? 'noindex,follow' : 'index,follow';
    if ($category && !(int) $category['robots_index']) {
        $robots = 'noindex,follow';
    }
    if (!empty($category['canonical_url']) && preg_match('#^https?://#i', $category['canonical_url']) && !$isFiltered && $page === 1) {
        $canonical = $category['canonical_url'];
    }

    if ($category) {
        $title = ($category['seo_title'] ?: $category['name_fa'] . ' | فروشگاه سپهر ابجدیان');
        $desc = $category['seo_description'] ?: ($category['short_desc_fa'] ?: store_setting('shop_description'));
        $ogImage = $category['og_image'] ? site_url('/' . $category['og_image']) : ($category['image'] ? site_url('/' . $category['image']) : null);
    } else {
        $title = $q !== '' ? 'جستجوی «' . $q . '» در فروشگاه | سپهر ابجدیان' : store_setting('shop_name', 'فروشگاه سپهر ابجدیان') . ' — قالب، افزونه، سورس و دوره';
        $desc = store_setting('shop_description');
        $ogImage = store_setting('shop_default_og_image') !== '' ? site_url('/' . store_setting('shop_default_og_image')) : null;
    }

    $crumbs = ['خانه' => '/', 'فروشگاه' => '/shop'];
    $ld = [breadcrumb_ld($category ? ['خانه' => '/', 'فروشگاه' => '/shop', $category['name_fa'] => $baseUrl] : ['خانه' => '/', 'فروشگاه' => '/shop'])];
    if ($category) {
        $ld[] = store_category_jsonld($category, $res['rows']);
    } else {
        $ld[] = json_ld(['@context' => 'https://schema.org', '@type' => 'CollectionPage', '@id' => site_url('/shop') . '#collection', 'name' => 'فروشگاه سپهر ابجدیان', 'url' => site_url('/shop'), 'description' => $desc]);
    }

    store_layout_start([
        'title' => $title, 'description' => $desc, 'canonical' => $canonical, 'robots' => $robots,
        'og_image' => $ogImage ?: null, 'jsonld' => $ld, 'user' => $user, 'nav' => 'shop',
    ]);
    $qs = static function (array $over) use ($q, $type, $sort) {
        $a = array_filter(['q' => $q, 'type' => $type, 'sort' => $sort !== 'newest' ? $sort : ''] + [], static fn($v) => $v !== '');
        return http_build_query(array_filter($over + $a, static fn($v) => $v !== '' && $v !== null));
    };
    ?>
  <section class="page-hero store-hero">
    <div class="kicker page-hero__eyebrow">// <?= $category ? 'CATEGORY' : 'STORE' ?></div>
    <h1<?= $category ? store_l10n($category, 'name') : ' data-i18n="store.heroH1"' ?>><?= e($category ? $category['name_fa'] : 'فروشگاه') ?></h1>
    <p<?= $category ? store_l10n($category, 'short_desc') : ' data-i18n="store.heroP"' ?>><?= e($category ? ($category['short_desc_fa'] ?: store_setting('shop_description')) : 'قالب آماده وب‌سایت، سورس پروژه، افزونه وردپرس، اتوماسیون، مدل هوش مصنوعی و دوره‌ی آموزشی — تحویل فوری و امن پس از پرداخت.') ?></p>
  </section>

  <section class="pg-section store-section">
    <?= store_breadcrumb_html($category ? ['خانه' => '/', 'فروشگاه' => '/shop', e($category['name_fa']) => null] : ['خانه' => '/', 'فروشگاه' => null]) ?>
    <?php if (!store_shop_enabled()): ?><div class="store-flash store-flash--warn" data-i18n="store.closedNotice">فروشگاه موقتاً بسته است؛ امکان ثبت سفارش جدید وجود ندارد.</div><?php endif; ?>

    <div class="cat-chips reveal" role="navigation" aria-label="دسته‌بندی‌ها">
      <a class="chip<?= !$category ? ' is-active' : '' ?>" href="/shop" data-i18n="store.allCategories">همه</a>
      <?php foreach ($categories as $c): ?>
        <a class="chip<?= $category && (int) $category['id'] === (int) $c['id'] ? ' is-active' : '' ?>" href="/shop/category/<?= e($c['slug']) ?>">
          <span aria-hidden="true"><?= e($c['glyph']) ?></span> <span<?= store_l10n($c, 'name') ?>><?= e($c['name_fa']) ?></span>
          <small>(<?= fa_digits((string) (int) $c['product_count']) ?>)</small>
        </a>
      <?php endforeach; ?>
    </div>

    <form class="shop-toolbar glass-card reveal" method="get" action="<?= e($baseUrl) ?>" role="search">
      <label class="shop-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="جستجوی قالب، افزونه، دوره…" data-i18n-placeholder="store.searchPh" aria-label="جستجو">
      </label>
      <select name="type" aria-label="نوع محصول">
        <option value="" data-i18n="store.allTypes">همه‌ی انواع</option>
        <?php foreach (store_product_types() as $k => $label): ?><option value="<?= e($k) ?>"<?= $type === $k ? ' selected' : '' ?> data-i18n="store.type.<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <select name="sort" aria-label="مرتب‌سازی">
        <?php foreach (['newest' => ['جدیدترین', 'store.sortNewest'], 'bestselling' => ['پرفروش‌ترین', 'store.sortBest'], 'rating' => ['بیشترین امتیاز', 'store.sortRating'], 'price_asc' => ['ارزان‌ترین', 'store.sortPriceAsc'], 'price_desc' => ['گران‌ترین', 'store.sortPriceDesc'], 'featured' => ['منتخب', 'store.sortFeatured']] as $k => [$label, $key]): ?>
          <option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?> data-i18n="<?= $key ?>"><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <button class="cta-btn primary" type="submit" data-i18n="store.apply">اعمال</button>
      <?php if ($isFiltered): ?><a class="cta-btn ghost" href="<?= e($baseUrl) ?>" data-i18n="store.clear">پاک‌کردن</a><?php endif; ?>
    </form>

    <p class="result-count" aria-live="polite"><span data-i18n="store.resultsPrefix">تعداد نتایج:</span> <b><?= fa_digits((string) $res['total']) ?></b></p>

    <?php if ($res['rows']): ?>
      <div class="product-grid">
        <?php foreach ($res['rows'] as $p) { echo store_card_html($p); } ?>
      </div>
      <?= store_pagination_html($page, $totalPages, $baseUrl, array_filter(['q' => $q, 'type' => $type, 'sort' => $sort !== 'newest' ? $sort : ''], static fn($v) => $v !== '')) ?>
    <?php else: ?>
      <div class="empty-state glass-card">
        <div class="empty-state__icon" aria-hidden="true">🛍️</div>
        <h2 data-i18n="store.emptyTitle">محصولی پیدا نشد</h2>
        <p data-i18n="store.emptyText"><?= $isFiltered ? 'عبارت جستجو یا فیلترها را تغییر بده.' : 'هنوز محصولی در این بخش منتشر نشده است؛ به‌زودی.' ?></p>
        <?php if ($isFiltered): ?><a class="cta-btn" href="<?= e($baseUrl) ?>" data-i18n="store.clear">پاک‌کردن فیلترها</a><?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($category && trim((string) $category['description_fa']) !== ''): ?>
      <article class="glass-card prose cat-description reveal"<?= ' data-l10n-id="cat-desc"' ?>><?= store_sanitize_html($category['description_fa']) ?></article>
      <script type="application/json" id="l10nData"><?= json_encode(['en' => ['cat-desc' => store_sanitize_html((string) $category['description_en'])], 'ar' => ['cat-desc' => store_sanitize_html((string) $category['description_ar'])]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <?php endif; ?>
  </section>
<?php
    store_layout_end(['nav' => 'shop']);
}
