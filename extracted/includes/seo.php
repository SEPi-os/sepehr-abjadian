<?php
/**
 * Small, focused SEO helpers. Existing pages already had good per-page
 * <head> blocks (title/description/canonical/OG/Twitter/JSON-LD) — this
 * file doesn't replace that, it just gives every page (old and new) one
 * place to build a clean canonical URL and one place to encode JSON-LD
 * safely, instead of hand-rolling "https://abjadian.ir/" + path strings
 * and json_encode() flags in a dozen files.
 */

require_once __DIR__ . '/config.php';

/** Build an absolute, clean-URL canonical link from a site-relative path.
 *  site_url('/blog/atlas') -> https://abjadian.ir/blog/atlas
 *  site_url('/')           -> https://abjadian.ir/  */
function site_url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    if ($path === '/') {
        return SITE_URL . '/';
    }
    return SITE_URL . rtrim($path, '/');
}

/** Safe JSON-LD encoding — the exact flag set that keeps Persian/Arabic
 *  text readable and prevents `</script>`-breakout or stray escaping. */
function json_ld(array $data): string
{
    return json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
}

/** BreadcrumbList JSON-LD from an ordered [label => path] list. */
function breadcrumb_ld(array $crumbs): string
{
    $items = [];
    $i = 1;
    foreach ($crumbs as $label => $path) {
        $items[] = ['@type' => 'ListItem', 'position' => $i++, 'name' => $label, 'item' => site_url($path)];
    }
    return json_ld(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items]);
}
