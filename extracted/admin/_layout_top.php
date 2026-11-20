<?php
/**
 * _layout_top.php — included at the top of every protected admin page.
 * Expects $pageTitle to be set before the include. Handles auth
 * (require_admin), opens <html>...<body>, and renders the sidebar.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$admin = require_admin();

$current = basename($_SERVER['SCRIPT_NAME']);
function nav_active(string $file, string $current): string
{
    return $file === $current ? ' is-active' : '';
}
$flash = flash_get();
// Store badges (pending reviews / orders needing attention) — never allowed to break an admin page.
$shopCounters = ['pending_reviews' => 0, 'attention_orders' => 0];
try {
    require_once __DIR__ . '/../includes/store-admin.php';
    $shopCounters = shop_counters();
} catch (Throwable $shopEx) {
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? 'پنل مدیریت') ?> | Admin</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;800&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css?v=20260913">
</head>
<body class="admin-body">

<button type="button" class="a-menu-btn" id="adminMenuBtn" aria-label="باز کردن منو" aria-expanded="false" aria-controls="adminSidebar">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
</button>
<div class="a-sidebar-backdrop" id="adminBackdrop"></div>

<aside class="a-sidebar" id="adminSidebar">
  <div class="a-sidebar__brand">
    <img src="../assets/brand/logo-sm.jpeg" alt="">
    <span>Admin Panel</span>
  </div>
  <nav class="a-nav">
    <a href="index.php" class="<?= nav_active('index.php', $current) ?>">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
      داشبورد
    </a>
    <div class="a-nav__section">محتوا</div>
    <a href="blog.php" class="<?= nav_active('blog.php', $current) . nav_active('blog-edit.php', $current) ?>">
      <svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
      وبلاگ
    </a>
    <a href="blog-categories.php" class="<?= nav_active('blog-categories.php', $current) ?>">
      <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h7"/></svg>
      دسته‌بندی‌ها
    </a>
    <a href="portfolio.php" class="<?= nav_active('portfolio.php', $current) . nav_active('portfolio-edit.php', $current) ?>">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
      نمونه‌کارها
    </a>
    <div class="a-nav__section">فروشگاه</div>
    <a href="shop-dashboard.php" class="<?= nav_active('shop-dashboard.php', $current) . nav_active('shop-migrate.php', $current) ?>">
      <svg viewBox="0 0 24 24"><path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9"/><path d="M9 20v-6h6v6"/></svg>
      داشبورد فروشگاه<?= $shopCounters['attention_orders'] ? ' <span class="a-nav__count">' . (int) $shopCounters['attention_orders'] . '</span>' : '' ?>
    </a>
    <a href="shop-products.php" class="<?= nav_active('shop-products.php', $current) . nav_active('shop-product-edit.php', $current) ?>">
      <svg viewBox="0 0 24 24"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>
      محصولات
    </a>
    <a href="shop-categories.php" class="<?= nav_active('shop-categories.php', $current) . nav_active('shop-category-edit.php', $current) ?>">
      <svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h7"/></svg>
      دسته‌بندی محصولات
    </a>
    <a href="shop-orders.php" class="<?= nav_active('shop-orders.php', $current) . nav_active('shop-order.php', $current) ?>">
      <svg viewBox="0 0 24 24"><path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6"/></svg>
      سفارش‌ها و پرداخت‌ها
    </a>
    <a href="shop-reviews.php" class="<?= nav_active('shop-reviews.php', $current) ?>">
      <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 9h8M8 13h5"/></svg>
      نظرات مشتریان<?= $shopCounters['pending_reviews'] ? ' <span class="a-nav__count">' . (int) $shopCounters['pending_reviews'] . '</span>' : '' ?>
    </a>
    <a href="shop-licenses.php" class="<?= nav_active('shop-licenses.php', $current) ?>">
      <svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2 20 3M17 6l3 3M14 9l2 2"/></svg>
      لایسنس‌ها
    </a>
    <a href="shop-reports.php" class="<?= nav_active('shop-reports.php', $current) ?>">
      <svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
      گزارش فروش
    </a>
    <a href="shop-settings.php" class="<?= nav_active('shop-settings.php', $current) ?>">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2"/></svg>
      تنظیمات فروشگاه
    </a>
    <div class="a-nav__section">ارتباطات</div>
    <a href="requests.php" class="<?= nav_active('requests.php', $current) ?>">
      <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      درخواست پروژه
    </a>
    <a href="messages.php" class="<?= nav_active('messages.php', $current) ?>">
      <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
      پیام‌های تماس
    </a>
    <div class="a-nav__section">مدیریت</div>
    <a href="users.php" class="<?= nav_active('users.php', $current) . nav_active('user-edit.php', $current) ?>">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg>
      کاربران سایت
    </a>
    <a href="admins.php" class="<?= nav_active('admins.php', $current) ?>">
      <svg viewBox="0 0 24 24"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/></svg>
      مدیران
    </a>
    <a href="settings.php" class="<?= nav_active('settings.php', $current) ?>">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.7.28 1.51.7 1.51 1.51H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      تنظیمات سایت
    </a>
    <a href="logout.php" style="margin-top:10px;color:var(--a-danger);">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
      خروج
    </a>
  </nav>
</aside>

<main class="a-main">
  <div class="a-topbar">
    <h1><?= e($pageTitle ?? '') ?></h1>
    <div class="a-topbar__who">
      <span><?= e($admin['name']) ?> (<?= e($admin['role']) ?>)</span>
      <a href="../index.php" target="_blank">مشاهده‌ی سایت ↗</a>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="a-alert a-alert--<?= $flash['type'] === 'error' ? 'error' : 'ok' ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>
