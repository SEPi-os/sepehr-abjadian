-- =====================================================================
-- 001_store_schema.sql — Digital store schema (NON-DESTRUCTIVE)
--
-- * Only CREATE TABLE IF NOT EXISTS / INSERT IGNORE. No DROP, no
--   TRUNCATE, no ALTER of existing tables: users, admins, blog_*,
--   portfolio_items, contact_messages, project_requests, site_settings
--   and login_attempts are never modified.
-- * Run via:  php database/migrate.php   (CLI)   or   admin -> فروشگاه ->
--   به‌روزرسانی دیتابیس. Applied files are recorded in schema_migrations,
--   so running it twice is a no-op.
-- * Money is always stored as BIGINT in the base currency unit chosen in
--   store settings (default: Toman). Never floats.
-- =====================================================================

SET NAMES utf8mb4;

-- ---- Categories ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(160) NOT NULL,
  `name_fa` VARCHAR(160) NOT NULL,
  `name_en` VARCHAR(160) NOT NULL DEFAULT '',
  `name_ar` VARCHAR(160) NOT NULL DEFAULT '',
  `short_desc_fa` VARCHAR(400) NOT NULL DEFAULT '',
  `short_desc_en` VARCHAR(400) NOT NULL DEFAULT '',
  `short_desc_ar` VARCHAR(400) NOT NULL DEFAULT '',
  `description_fa` MEDIUMTEXT NULL,
  `description_en` MEDIUMTEXT NULL,
  `description_ar` MEDIUMTEXT NULL,
  `image` VARCHAR(255) NULL DEFAULT NULL,
  `glyph` VARCHAR(16) NOT NULL DEFAULT '🛍️' COMMENT 'emoji used when no cover image is set',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` SMALLINT NOT NULL DEFAULT 0,
  `seo_title` VARCHAR(190) NULL DEFAULT NULL,
  `seo_description` VARCHAR(320) NULL DEFAULT NULL,
  `focus_keyword` VARCHAR(120) NULL DEFAULT NULL,
  `related_keywords` VARCHAR(400) NULL DEFAULT NULL,
  `canonical_url` VARCHAR(255) NULL DEFAULT NULL,
  `og_title` VARCHAR(190) NULL DEFAULT NULL,
  `og_description` VARCHAR(320) NULL DEFAULT NULL,
  `og_image` VARCHAR(255) NULL DEFAULT NULL,
  `robots_index` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_categories_slug` (`slug`),
  KEY `idx_shop_categories_active_order` (`is_active`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Products --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(60) NOT NULL,
  `slug` VARCHAR(190) NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `product_type` ENUM('website_template','source_code','wp_plugin','ui_component','automation','ai_model','app','course','bundle','other') NOT NULL DEFAULT 'other',
  `delivery_method` ENUM('file','license','file_license','manual') NOT NULL DEFAULT 'file',
  `title_fa` VARCHAR(255) NOT NULL,
  `title_en` VARCHAR(255) NOT NULL DEFAULT '',
  `title_ar` VARCHAR(255) NOT NULL DEFAULT '',
  `summary_fa` VARCHAR(500) NOT NULL DEFAULT '',
  `summary_en` VARCHAR(500) NOT NULL DEFAULT '',
  `summary_ar` VARCHAR(500) NOT NULL DEFAULT '',
  `description_fa` MEDIUMTEXT NULL COMMENT 'sanitized HTML (see store_sanitize_html)',
  `description_en` MEDIUMTEXT NULL,
  `description_ar` MEDIUMTEXT NULL,
  `features` TEXT NULL COMMENT 'one feature per line',
  `requirements` TEXT NULL,
  `compatibility` TEXT NULL,
  `install_guide` MEDIUMTEXT NULL,
  `usage_guide` MEDIUMTEXT NULL,
  `changelog` MEDIUMTEXT NULL,
  `faq_json` MEDIUMTEXT NULL COMMENT 'JSON array of {q,a}',
  `license_terms` TEXT NULL,
  `support_info` TEXT NULL,
  `version` VARCHAR(40) NULL DEFAULT NULL,
  `brand` VARCHAR(120) NULL DEFAULT NULL,
  `tags` VARCHAR(400) NULL DEFAULT NULL,
  `demo_url` VARCHAR(500) NULL DEFAULT NULL,
  `video_url` VARCHAR(500) NULL DEFAULT NULL,
  `price` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `sale_price` BIGINT UNSIGNED NULL DEFAULT NULL,
  `sale_starts_at` DATETIME NULL DEFAULT NULL,
  `sale_ends_at` DATETIME NULL DEFAULT NULL,
  `is_free` TINYINT(1) NOT NULL DEFAULT 0,
  `show_price` TINYINT(1) NOT NULL DEFAULT 1,
  `max_quantity` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'per order; file-only products are always 1',
  `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'sales switch; 0 hides the product and blocks purchase',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `display_order` SMALLINT NOT NULL DEFAULT 0,
  `published_at` DATETIME NULL DEFAULT NULL,
  `seo_title` VARCHAR(190) NULL DEFAULT NULL,
  `seo_description` VARCHAR(320) NULL DEFAULT NULL,
  `focus_keyword` VARCHAR(120) NULL DEFAULT NULL,
  `related_keywords` VARCHAR(400) NULL DEFAULT NULL,
  `canonical_url` VARCHAR(255) NULL DEFAULT NULL,
  `og_title` VARCHAR(190) NULL DEFAULT NULL,
  `og_description` VARCHAR(320) NULL DEFAULT NULL,
  `og_image` VARCHAR(255) NULL DEFAULT NULL,
  `robots_index` TINYINT(1) NOT NULL DEFAULT 1,
  `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 0.00 COMMENT 'cache — recomputed from APPROVED reviews only',
  `rating_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `sales_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'cache — incremented once per delivered order item',
  `deleted_at` DATETIME NULL DEFAULT NULL COMMENT 'soft delete (products with order history are never physically deleted)',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_products_slug` (`slug`),
  UNIQUE KEY `uq_shop_products_sku` (`sku`),
  KEY `idx_shop_products_public` (`status`, `is_active`, `deleted_at`, `display_order`),
  KEY `idx_shop_products_category` (`category_id`, `status`, `is_active`),
  KEY `idx_shop_products_published_at` (`published_at`),
  KEY `idx_shop_products_price` (`price`),
  KEY `idx_shop_products_rating` (`rating_avg`, `rating_count`),
  KEY `idx_shop_products_sales` (`sales_count`),
  CONSTRAINT `fk_shop_products_category` FOREIGN KEY (`category_id`) REFERENCES `shop_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Old slugs after a rename -> permanent 301 to the current URL
CREATE TABLE IF NOT EXISTS `shop_product_redirects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `old_slug` VARCHAR(190) NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_product_redirects_old` (`old_slug`),
  KEY `idx_shop_product_redirects_product` (`product_id`),
  CONSTRAINT `fk_shop_product_redirects_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Product images (all stored as WebP, 3 sizes) ---------------------
CREATE TABLE IF NOT EXISTS `shop_product_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `path` VARCHAR(255) NOT NULL COMMENT 'full size (max 1600px) .webp',
  `card_path` VARCHAR(255) NOT NULL COMMENT 'card size (600px) .webp',
  `thumb_path` VARCHAR(255) NOT NULL COMMENT 'thumbnail (160px) .webp',
  `width` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `height` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `card_width` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `card_height` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `alt` VARCHAR(255) NOT NULL DEFAULT '',
  `caption` VARCHAR(255) NOT NULL DEFAULT '',
  `is_main` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_product_images_product` (`product_id`, `is_main`, `sort_order`),
  CONSTRAINT `fk_shop_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Specs (admin adds as many rows as needed) -------------------------
CREATE TABLE IF NOT EXISTS `shop_product_specs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `label_fa` VARCHAR(160) NOT NULL,
  `label_en` VARCHAR(160) NOT NULL DEFAULT '',
  `label_ar` VARCHAR(160) NOT NULL DEFAULT '',
  `value_fa` VARCHAR(500) NOT NULL,
  `value_en` VARCHAR(500) NOT NULL DEFAULT '',
  `value_ar` VARCHAR(500) NOT NULL DEFAULT '',
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_shop_product_specs_product` (`product_id`, `sort_order`),
  CONSTRAINT `fk_shop_product_specs_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Deliverable files (ZIP/RAR/...) — PRIVATE storage ----------------
CREATE TABLE IF NOT EXISTS `shop_product_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(190) NOT NULL,
  `file_kind` ENUM('source','docs','install','sample','extra') NOT NULL DEFAULT 'source',
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `stored_name` CHAR(40) NOT NULL COMMENT 'random name inside the private storage dir',
  `original_name` VARCHAR(255) NOT NULL,
  `extension` VARCHAR(10) NOT NULL,
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `sha256` CHAR(64) NOT NULL DEFAULT '',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_product_files_stored` (`stored_name`),
  KEY `idx_shop_product_files_product` (`product_id`, `is_active`, `sort_order`),
  CONSTRAINT `fk_shop_product_files_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Orders ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(24) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `subtotal` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'sum of list prices',
  `discount_total` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `total` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'amount to pay, computed server-side',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'IRT',
  `status` ENUM('pending','processing','completed','cancelled','expired') NOT NULL DEFAULT 'pending',
  `payment_status` ENUM('unpaid','pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'unpaid',
  `delivery_status` ENUM('none','pending','delivered','partial','failed') NOT NULL DEFAULT 'none',
  `customer_name` VARCHAR(120) NOT NULL,
  `customer_email` VARCHAR(190) NOT NULL,
  `customer_phone` VARCHAR(30) NULL DEFAULT NULL,
  `customer_note` VARCHAR(1000) NULL DEFAULT NULL,
  `checkout_token` CHAR(32) NULL DEFAULT NULL COMMENT 'one-time form token — blocks duplicate orders from double clicks',
  `admin_note` TEXT NULL,
  `expires_at` DATETIME NULL DEFAULT NULL COMMENT 'license reservations are released after this',
  `paid_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_orders_number` (`order_number`),
  UNIQUE KEY `uq_shop_orders_checkout_token` (`checkout_token`),
  KEY `idx_shop_orders_user` (`user_id`, `created_at`),
  KEY `idx_shop_orders_payment` (`payment_status`, `created_at`),
  KEY `idx_shop_orders_status` (`status`, `created_at`),
  KEY `idx_shop_orders_created` (`created_at`),
  KEY `idx_shop_orders_expiry` (`payment_status`, `expires_at`),
  CONSTRAINT `fk_shop_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `product_title` VARCHAR(255) NOT NULL COMMENT 'snapshot at purchase time',
  `product_sku` VARCHAR(60) NOT NULL,
  `product_type` VARCHAR(30) NOT NULL,
  `delivery_method` VARCHAR(20) NOT NULL,
  `quantity` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` BIGINT UNSIGNED NOT NULL COMMENT 'list price at purchase time',
  `unit_discount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `line_total` BIGINT UNSIGNED NOT NULL,
  `delivery_note` TEXT NULL COMMENT 'shown to the buyer (used by manual delivery)',
  `delivered_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shop_order_items_order` (`order_id`),
  KEY `idx_shop_order_items_product` (`product_id`),
  CONSTRAINT `fk_shop_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shop_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Payments (one order may have several attempts) --------------------
CREATE TABLE IF NOT EXISTS `shop_payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `gateway` VARCHAR(40) NOT NULL,
  `amount` BIGINT UNSIGNED NOT NULL,
  `currency` VARCHAR(8) NOT NULL DEFAULT 'IRT',
  `status` ENUM('initiated','redirected','verifying','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'initiated',
  `gateway_ref` VARCHAR(190) NULL DEFAULT NULL COMMENT 'authority / token issued by the gateway',
  `transaction_id` VARCHAR(190) NULL DEFAULT NULL COMMENT 'final reference id after verification',
  `redirect_url` VARCHAR(500) NULL DEFAULT NULL,
  `card_mask` VARCHAR(40) NULL DEFAULT NULL,
  `error_message` VARCHAR(500) NULL DEFAULT NULL,
  `raw_response` TEXT NULL COMMENT 'gateway response for support; never holds merchant secrets',
  `verify_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `paid_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_payments_gateway_ref` (`gateway`, `gateway_ref`),
  UNIQUE KEY `uq_shop_payments_transaction` (`gateway`, `transaction_id`),
  KEY `idx_shop_payments_order` (`order_id`, `status`),
  KEY `idx_shop_payments_status` (`status`, `updated_at`),
  CONSTRAINT `fk_shop_payments_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Licenses (Spot Player etc.) — codes are ENCRYPTED at rest ---------
CREATE TABLE IF NOT EXISTS `shop_licenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `code_enc` TEXT NOT NULL COMMENT 'sodium secretbox, base64',
  `code_hash` CHAR(64) NOT NULL COMMENT 'HMAC-SHA256 of the code — duplicate guard without storing plaintext',
  `code_hint` VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'last 4 chars, for admin lists',
  `status` ENUM('available','reserved','assigned','disabled') NOT NULL DEFAULT 'available',
  `order_id` INT UNSIGNED NULL DEFAULT NULL,
  `order_item_id` INT UNSIGNED NULL DEFAULT NULL,
  `user_id` INT UNSIGNED NULL DEFAULT NULL,
  `reserved_at` DATETIME NULL DEFAULT NULL,
  `assigned_at` DATETIME NULL DEFAULT NULL,
  `note` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_licenses_product_hash` (`product_id`, `code_hash`),
  KEY `idx_shop_licenses_pool` (`product_id`, `status`, `id`),
  KEY `idx_shop_licenses_order` (`order_id`),
  KEY `idx_shop_licenses_item` (`order_item_id`, `status`),
  KEY `idx_shop_licenses_user` (`user_id`),
  CONSTRAINT `fk_shop_licenses_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Entitlements: "this user may access this purchased item" ----------
-- Independent of the product's visibility in the public shop.
CREATE TABLE IF NOT EXISTS `shop_entitlements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `order_item_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `kind` ENUM('file','license','manual') NOT NULL,
  `status` ENUM('active','revoked') NOT NULL DEFAULT 'active',
  `revoked_reason` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_entitlements_item_kind` (`order_item_id`, `kind`),
  KEY `idx_shop_entitlements_user` (`user_id`, `status`),
  KEY `idx_shop_entitlements_product` (`product_id`),
  CONSTRAINT `fk_shop_entitlements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_shop_entitlements_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shop_entitlements_item` FOREIGN KEY (`order_item_id`) REFERENCES `shop_order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_download_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entitlement_id` INT UNSIGNED NOT NULL,
  `file_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_download_log_ent_file` (`entitlement_id`, `file_id`),
  KEY `idx_shop_download_log_user` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Reviews (moderated) -----------------------------------------------
CREATE TABLE IF NOT EXISTS `shop_reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL,
  `title` VARCHAR(120) NOT NULL DEFAULT '',
  `body` TEXT NOT NULL COMMENT 'plain text only — escaped on output',
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `is_verified_purchase` TINYINT(1) NOT NULL DEFAULT 0,
  `admin_note` VARCHAR(500) NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `moderated_by` INT UNSIGNED NULL DEFAULT NULL,
  `moderated_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shop_reviews_product_user` (`product_id`, `user_id`),
  KEY `idx_shop_reviews_product_status` (`product_id`, `status`, `created_at`),
  KEY `idx_shop_reviews_status` (`status`, `created_at`),
  KEY `idx_shop_reviews_user` (`user_id`),
  CONSTRAINT `fk_shop_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `shop_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shop_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Order history + audit trail ----------------------------------------
CREATE TABLE IF NOT EXISTS `shop_order_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `event` VARCHAR(40) NOT NULL,
  `message` VARCHAR(500) NOT NULL DEFAULT '',
  `actor_type` ENUM('system','user','admin','gateway') NOT NULL DEFAULT 'system',
  `actor_id` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_order_events_order` (`order_id`, `id`),
  CONSTRAINT `fk_shop_order_events_order` FOREIGN KEY (`order_id`) REFERENCES `shop_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shop_audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_admin_id` INT UNSIGNED NULL DEFAULT NULL,
  `action` VARCHAR(60) NOT NULL,
  `entity_type` VARCHAR(40) NOT NULL DEFAULT '',
  `entity_id` INT UNSIGNED NULL DEFAULT NULL,
  `details` VARCHAR(1000) NOT NULL DEFAULT '',
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_audit_log_entity` (`entity_type`, `entity_id`),
  KEY `idx_shop_audit_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Generic rate-limit buckets (reviews, checkout, downloads, cart API) -
CREATE TABLE IF NOT EXISTS `shop_rate_hits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket` VARCHAR(40) NOT NULL,
  `ident` VARCHAR(80) NOT NULL,
  `hit_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_rate_hits_lookup` (`bucket`, `ident`, `hit_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Seed: the six initial categories (editable/deletable in admin) ----
INSERT IGNORE INTO `shop_categories`
  (`slug`, `name_fa`, `name_en`, `name_ar`, `short_desc_fa`, `short_desc_en`, `short_desc_ar`, `glyph`, `display_order`, `seo_title`, `seo_description`, `focus_keyword`)
VALUES
  ('courses', 'دوره‌های آموزشی', 'Online Courses', 'الدورات التعليمية', 'دوره‌های آموزشی برنامه‌نویسی، هوش مصنوعی و اتوماسیون — با لایسنس اسپات‌پلیر.', 'Programming, AI and automation courses delivered with a Spot Player license.', 'دورات البرمجة والذكاء الاصطناعي والأتمتة مع ترخيص Spot Player.', '🎓', 1, 'دوره‌های آموزشی برنامه‌نویسی و هوش مصنوعی | سپهر ابجدیان', 'خرید دوره‌های آموزشی برنامه‌نویسی، هوش مصنوعی و اتوماسیون با لایسنس اسپات‌پلیر و دسترسی دائمی.', 'دوره آموزشی'),
  ('website-templates', 'قالب‌های سایت', 'Website Templates', 'قوالب المواقع', 'قالب‌های آماده و حرفه‌ای وب‌سایت همراه با سورس کامل.', 'Ready-made professional website templates with full source code.', 'قوالب مواقع جاهزة واحترافية مع الكود المصدري الكامل.', '🎨', 2, 'خرید قالب آماده وب‌سایت با سورس کامل | سپهر ابجدیان', 'قالب‌های آماده وب‌سایت، ریسپانسیو و سئو‌شده همراه با سورس کامل و پشتیبانی.', 'قالب آماده سایت'),
  ('wordpress-plugins', 'افزونه‌های وردپرس', 'WordPress Plugins', 'إضافات ووردبريس', 'افزونه‌های کاربردی وردپرس برای فروشگاه، سئو و اتوماسیون.', 'Practical WordPress plugins for stores, SEO and automation.', 'إضافات ووردبريس عملية للمتاجر وتحسين محركات البحث والأتمتة.', '🔌', 3, 'خرید افزونه وردپرس | سپهر ابجدیان', 'افزونه‌های حرفه‌ای وردپرس با آپدیت و پشتیبانی — دانلود فوری پس از خرید.', 'افزونه وردپرس'),
  ('automations', 'اتوماسیون‌ها', 'Automations', 'الأتمتة', 'گردش‌کارهای آماده n8n و اتوماسیون‌های کسب‌وکار.', 'Ready-to-import n8n workflows and business automations.', 'مسارات عمل n8n جاهزة وأتمتة الأعمال.', '⚙️', 4, 'خرید اتوماسیون و ورک‌فلو n8n | سپهر ابجدیان', 'ورک‌فلوهای آماده n8n و اتوماسیون کسب‌وکار؛ قابل ایمپورت و راه‌اندازی سریع.', 'اتوماسیون n8n'),
  ('ai-models', 'مدل‌های هوش مصنوعی', 'AI Models', 'نماذج الذكاء الاصطناعي', 'مدل‌ها، ایجنت‌ها و پایپ‌لاین‌های آماده هوش مصنوعی.', 'Ready AI models, agents and pipelines.', 'نماذج ووكلاء ومسارات ذكاء اصطناعي جاهزة.', '🧠', 5, 'خرید مدل هوش مصنوعی آماده | سپهر ابجدیان', 'مدل‌ها و ایجنت‌های آماده هوش مصنوعی همراه با مستندات و سورس.', 'مدل هوش مصنوعی'),
  ('apps', 'اپلیکیشن‌ها', 'Applications', 'التطبيقات', 'اپلیکیشن‌های دسکتاپ و وب آماده — با سورس یا نسخه نصبی.', 'Ready desktop and web applications — source or installer.', 'تطبيقات سطح مكتب وويب جاهزة — مع الكود المصدري أو ملف التثبيت.', '📱', 6, 'خرید اپلیکیشن و سورس پروژه | سپهر ابجدیان', 'اپلیکیشن‌های آماده دسکتاپ و وب و سورس پروژه‌ها با تحویل فوری.', 'اپلیکیشن آماده');

-- ---- Seed: default store settings (stored in the existing site_settings) -
INSERT IGNORE INTO `site_settings` (`setting_key`, `setting_value`) VALUES
  ('shop_enabled', '1'),
  ('shop_name', 'فروشگاه سپهر ابجدیان'),
  ('shop_description', 'قالب آماده وب‌سایت، سورس پروژه، افزونه وردپرس، اتوماسیون، مدل هوش مصنوعی و دوره‌های آموزشی — تحویل فوری و امن پس از پرداخت.'),
  ('shop_currency_code', 'IRT'),
  ('shop_currency_fa', 'تومان'),
  ('shop_currency_en', 'Toman'),
  ('shop_currency_ar', 'تومان'),
  ('shop_price_digits', 'fa'),
  ('shop_per_page', '12'),
  ('shop_max_qty_per_item', '5'),
  ('shop_reservation_minutes', '30'),
  ('shop_download_expiry_minutes', '15'),
  ('shop_download_max_per_file', '10'),
  ('shop_reviews_enabled', '1'),
  ('shop_review_min_chars', '10'),
  ('shop_review_max_chars', '2000'),
  ('shop_seo_title_suffix', ' | فروشگاه سپهر ابجدیان'),
  ('shop_default_og_image', '');
