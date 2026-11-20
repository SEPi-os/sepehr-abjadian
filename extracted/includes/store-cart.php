<?php
/**
 * store-cart.php — the browser's localStorage cart only ever supplies
 * [{id, qty}]. Everything else (existence, visibility, price, discount,
 * stock, deliverability, quantity limits) is re-derived here from the DB.
 */
require_once __DIR__ . '/store-catalog.php';

const STORE_CART_MAX_LINES = 30;

/** Max units of this product per order. File-only goods are strictly 1 (a second copy is meaningless). */
function store_max_qty_for(array $p): int
{
    if ($p['delivery_method'] === 'file' || $p['delivery_method'] === 'manual' || store_price($p)['free']) {
        return 1;
    }
    return max(1, min((int) $p['max_quantity'], store_setting_int('shop_max_qty_per_item', 5)));
}

/**
 * @param array<int,array{id:mixed,qty:mixed,seen?:mixed}> $raw
 * @return array{lines:array,subtotal:int,discount:int,total:int,count:int,ok:bool,has_issues:bool}
 */
function store_cart_resolve(array $raw): array
{
    $wanted = [];
    foreach ($raw as $it) {
        if (!is_array($it)) {
            continue;
        }
        $id = (int) ($it['id'] ?? 0);
        if ($id < 1 || count($wanted) >= STORE_CART_MAX_LINES && !isset($wanted[$id])) {
            continue;
        }
        $qty = (int) ($it['qty'] ?? 1);
        $wanted[$id] = [
            'qty' => max(1, min(999, ($wanted[$id]['qty'] ?? 0) + $qty)),
            'seen' => isset($it['seen']) ? (int) $it['seen'] : null,
        ];
    }

    $lines = [];
    $sub = $disc = $total = $count = 0;
    $hasIssues = false;

    if ($wanted) {
        $ids = array_keys($wanted);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = store_rows(
            "SELECT p.*, c.slug AS category_slug, c.name_fa AS category_name_fa, c.is_active AS category_active,
                    i.card_path, i.card_width, i.card_height
             FROM shop_products p JOIN shop_categories c ON c.id = p.category_id
             LEFT JOIN shop_product_images i ON i.product_id = p.id AND i.is_main = 1
             WHERE p.id IN ($ph)",
            str_repeat('i', count($ids)),
            $ids
        );
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }

        foreach ($wanted as $id => $w) {
            $issues = [];
            $p = $byId[$id] ?? null;
            if (!$p) {
                $lines[] = ['id' => $id, 'purchasable' => false, 'issues' => ['missing'], 'qty' => $w['qty']];
                $hasIssues = true;
                continue;
            }
            [$sellable, $why] = store_product_sellable($p);
            if (!$sellable) {
                $issues[] = $why;
            }

            $price = store_price($p);
            $max = store_max_qty_for($p);
            $qty = $w['qty'];
            if ($qty > $max) {
                $qty = $max;
                $issues[] = 'qty_adjusted';
            }
            if (store_product_needs_license($p) && $sellable) {
                $stock = store_license_stock($id);
                if ($qty > $stock) {
                    $qty = $stock;
                    $issues[] = 'qty_adjusted_stock';
                }
            }
            if ($w['seen'] !== null && $w['seen'] !== $price['final']) {
                $issues[] = 'price_changed';
            }

            $blocking = array_intersect($issues, ['missing', 'unavailable', 'out_of_stock', 'not_deliverable', 'shop_closed']);
            $purchasable = !$blocking && $qty >= 1;
            $lineList = $price['list'] * $qty;
            $lineFinal = $price['final'] * $qty;

            if ($purchasable) {
                $sub += $lineList;
                $total += $lineFinal;
                $disc += $lineList - $lineFinal;
                $count += $qty;
            }
            if ($issues) {
                $hasIssues = true;
            }
            $lines[] = [
                'id' => $id, 'slug' => $p['slug'], 'url' => store_product_url($p['slug']), 'sku' => $p['sku'],
                'title' => ['fa' => $p['title_fa'], 'en' => store_lf($p, 'title', 'en'), 'ar' => store_lf($p, 'title', 'ar')],
                'type' => $p['product_type'], 'delivery' => $p['delivery_method'],
                'qty' => $qty, 'max' => $max,
                'unit_list' => $price['list'], 'unit_final' => $price['final'], 'on_sale' => $price['on_sale'],
                'line_total' => $lineFinal, 'image' => $p['card_path'] ? '/' . $p['card_path'] : null,
                'purchasable' => $purchasable, 'issues' => array_values(array_unique($issues)),
            ];
        }
    }

    return ['lines' => $lines, 'subtotal' => $sub, 'discount' => $disc, 'total' => $total, 'count' => $count, 'ok' => $count > 0 && !array_filter($lines, fn($l) => !$l['purchasable']), 'has_issues' => $hasIssues];
}

/** Parses the cart JSON the checkout form submits ([{id,qty}] only). */
function store_cart_from_json(string $json): array
{
    if (strlen($json) > 8000) {
        return [];
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : $data;
    return array_values(array_filter($items, 'is_array'));
}
