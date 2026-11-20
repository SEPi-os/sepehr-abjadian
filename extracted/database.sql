-- =====================================================================
-- Sepehr Abjadian — Portfolio CMS Database
-- Import this file directly in phpMyAdmin (Import tab -> choose file ->
-- Go). It creates the database, every table, and seed data in one pass.
--
-- Engine: InnoDB (foreign keys + transactions)
-- Charset: utf8mb4 / utf8mb4_unicode_ci (full Persian, Arabic, English,
-- and emoji support throughout — every text column needs this, not just
-- the obviously-Persian ones, since fa/en/ar text all lives side by side)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `abjadian_portfolio`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `abjadian_portfolio`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- admins — site administrators (you). Separate from `users` on purpose:
-- an admin session and a public-user session should never share a table,
-- so a bug in one can't accidentally grant access to the other.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `username` VARCHAR(60) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'password_hash(), PASSWORD_ARGON2ID',
  `role` ENUM('owner','editor') NOT NULL DEFAULT 'editor',
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed admin — username: admin / password: ChangeMe#2026!
-- CHANGE THIS PASSWORD the moment you log in for the first time
-- (Admin Panel -> Settings -> Change Password). The hash below is a real
-- Argon2id hash of that exact password, generated with PHP's own
-- password_hash(), so it will verify correctly on first login.
INSERT INTO `admins` (`name`, `username`, `email`, `password`, `role`) VALUES
('Sepehr Abjadian', 'admin', 'i88020023@gmail.com',
 '$argon2id$v=19$m=65536,t=4,p=1$ZzhiaVowbDd3eFBLWUdPcA$ErVpLx5d7YlJga2IMn3JZQlB0EvGGqGJomWh58HgXoo',
 'owner');

-- ---------------------------------------------------------------------
-- users — regular site accounts. Note: the current frontend has no
-- public sign-up/login form yet (see delivery notes) — this table and
-- its admin panel exist so the CMS is ready the moment one is added,
-- and so you can add accounts by hand meanwhile.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `username` VARCHAR(60) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) NULL DEFAULT NULL,
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- blog_categories
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `blog_categories`;
CREATE TABLE `blog_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(80) NOT NULL COMMENT 'ai / web / backend — matches the existing filter chips',
  `name_fa` VARCHAR(120) NOT NULL,
  `name_en` VARCHAR(120) NOT NULL,
  `name_ar` VARCHAR(120) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `blog_categories` (`slug`, `name_fa`, `name_en`, `name_ar`) VALUES
('ai', 'هوش مصنوعی', 'AI', 'الذكاء الاصطناعي'),
('web', 'وب', 'Web', 'الويب'),
('backend', 'بک‌اند', 'Backend', 'الخلفية');

-- ---------------------------------------------------------------------
-- blog_posts — title/excerpt/content/meta are per-language columns
-- rather than a separate translations table: this site has exactly
-- 3 fixed languages (not an open-ended list), so flat columns keep every
-- query a plain SELECT with no joins, which is easier to read and
-- maintain in native PHP than a normalized i18n schema would be.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `blog_posts`;
CREATE TABLE `blog_posts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NULL DEFAULT NULL,
  `author_id` INT UNSIGNED NULL DEFAULT NULL,
  `slug` VARCHAR(190) NOT NULL,
  `glyph` VARCHAR(10) NOT NULL DEFAULT '📝' COMMENT 'emoji used as the card cover, matches the current design',
  `title_fa` VARCHAR(255) NOT NULL,
  `title_en` VARCHAR(255) NOT NULL,
  `title_ar` VARCHAR(255) NOT NULL,
  `excerpt_fa` VARCHAR(400) NOT NULL,
  `excerpt_en` VARCHAR(400) NOT NULL,
  `excerpt_ar` VARCHAR(400) NOT NULL,
  `content_fa` MEDIUMTEXT NOT NULL COMMENT 'stored as paragraphs separated by a blank line',
  `content_en` MEDIUMTEXT NOT NULL,
  `content_ar` MEDIUMTEXT NOT NULL,
  `tags` VARCHAR(255) NULL DEFAULT NULL COMMENT 'comma-separated, e.g. "Python,n8n,RAG"',
  `featured_image` VARCHAR(255) NULL DEFAULT NULL,
  `video_url` VARCHAR(500) NULL DEFAULT NULL COMMENT 'optional — YouTube/Vimeo embed link or a direct .mp4 URL; shown at the top of the article, before the body',
  `meta_title_fa` VARCHAR(255) NULL DEFAULT NULL,
  `meta_title_en` VARCHAR(255) NULL DEFAULT NULL,
  `meta_title_ar` VARCHAR(255) NULL DEFAULT NULL,
  `meta_description_fa` VARCHAR(300) NULL DEFAULT NULL,
  `meta_description_en` VARCHAR(300) NULL DEFAULT NULL,
  `meta_description_ar` VARCHAR(300) NULL DEFAULT NULL,
  `canonical_url` VARCHAR(255) NULL DEFAULT NULL,
  `read_time_min` TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blog_posts_slug` (`slug`),
  KEY `idx_blog_posts_status_pub` (`status`, `published_at`),
  KEY `idx_blog_posts_category` (`category_id`),
  CONSTRAINT `fk_blog_posts_category` FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_blog_posts_author` FOREIGN KEY (`author_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  FULLTEXT KEY `ft_blog_posts_search_fa` (`title_fa`, `excerpt_fa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- portfolio_items
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `portfolio_items`;
CREATE TABLE `portfolio_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(190) NOT NULL,
  `category` ENUM('ai','web') NOT NULL DEFAULT 'web' COMMENT 'matches the two filter chips on portfolio.html',
  `title_fa` VARCHAR(255) NOT NULL,
  `title_en` VARCHAR(255) NOT NULL,
  `title_ar` VARCHAR(255) NOT NULL,
  `tag_fa` VARCHAR(120) NULL DEFAULT NULL COMMENT 'small kicker label, e.g. "دستیار هوش مصنوعی"',
  `tag_en` VARCHAR(120) NULL DEFAULT NULL,
  `tag_ar` VARCHAR(120) NULL DEFAULT NULL,
  `description_fa` VARCHAR(500) NOT NULL,
  `description_en` VARCHAR(500) NOT NULL,
  `description_ar` VARCHAR(500) NOT NULL,
  `tech_stack` VARCHAR(255) NULL DEFAULT NULL COMMENT 'comma-separated chips, e.g. "Python,PySide6,faster-whisper"',
  `cover_image` VARCHAR(255) NULL DEFAULT NULL COMMENT 'if set, used instead of glyph',
  `glyph` VARCHAR(10) NULL DEFAULT NULL COMMENT 'emoji fallback cover when no image is uploaded',
  `project_url` VARCHAR(255) NULL DEFAULT NULL,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `display_order` SMALLINT NOT NULL DEFAULT 0,
  `status` ENUM('draft','published') NOT NULL DEFAULT 'published',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_portfolio_items_slug` (`slug`),
  KEY `idx_portfolio_items_status_order` (`status`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- contact_messages — a general-purpose inbox table. The current site's
-- one contact form is project-shaped (see project_requests below) and
-- posts there instead; this table is included per spec and ready the
-- moment a simpler "just say hi" form is added anywhere on the site.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(30) NULL DEFAULT NULL,
  `subject` VARCHAR(190) NULL DEFAULT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('unread','read') NOT NULL DEFAULT 'unread',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_messages_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- project_requests — what the real contact.html form on the site
-- submits to (name, email, phone, company, budget, type, message).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `project_requests`;
CREATE TABLE `project_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `phone` VARCHAR(30) NULL DEFAULT NULL,
  `company` VARCHAR(190) NULL DEFAULT NULL,
  `project_type` ENUM('web','ai','desktop','data','other') NOT NULL DEFAULT 'other',
  `budget` ENUM('small','medium','large','unknown') NOT NULL DEFAULT 'unknown',
  `message` TEXT NOT NULL,
  `status` ENUM('new','reviewing','contacted','in_progress','completed','rejected') NOT NULL DEFAULT 'new',
  `admin_note` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_requests_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- site_settings — simple key/value store for admin-editable site
-- values (contact phone/email shown in the footer, etc).
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE `site_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('contact_phone', '+989044691979'),
('contact_email', 'i88020023@gmail.com'),
('contact_instagram', 'https://www.instagram.com/s_abjadian'),
('contact_telegram_bot', 'https://t.me/sepehrworld_bot'),
('site_name', 'Sepehr Abjadian');

-- ---------------------------------------------------------------------
-- login_attempts — backs the login rate-limiter in includes/auth.php.
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_lookup` (`username`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Seed content — the 6 existing blog posts and 9 existing portfolio
-- pieces from the current site, so importing this file reproduces the
-- live site's real content immediately (nothing to re-type by hand).
-- Edit or add more anytime from the Admin Panel instead.
-- ---------------------------------------------------------------------
INSERT INTO `blog_posts` (`id`, `category_id`, `author_id`, `slug`, `glyph`, `title_fa`, `title_en`, `title_ar`, `excerpt_fa`, `excerpt_en`, `excerpt_ar`, `content_fa`, `content_en`, `content_ar`, `tags`, `featured_image`, `meta_title_fa`, `meta_title_en`, `meta_title_ar`, `meta_description_fa`, `meta_description_en`, `meta_description_ar`, `canonical_url`, `read_time_min`, `is_featured`, `status`, `published_at`, `created_at`, `updated_at`) VALUES (1,1,1,'atlas','🖥️','از ایده تا اجرا: ساخت آتلاس، دستیار هوش مصنوعی برای دسکتاپ ویندوز','From Idea to Execution: Building Atlas, an AI Desktop Assistant for Windows','من الفكرة إلى التنفيذ: بناء أطلس، مساعد ذكاء اصطناعي لسطح مكتب ويندوز','روایت ساخت آتلاس؛ دستیار دسکتاپی که با Python و PySide6 نوشتم، معماری پلاگین‌محور داره، دستور صوتی چندزبانه می‌فهمه و اجرای کارها رو به n8n می‌سپاره.','The story of building Atlas, a desktop assistant I wrote with Python and PySide6. It has a plugin-based architecture, understands multilingual voice commands, and hands off task execution to n8n.','قصة بناء أطلس؛ مساعد سطح المكتب الذي كتبته بلغة Python وPySide6، بنية قائمة على الإضافات، يفهم الأوامر الصوتية متعددة اللغات، ويعهد بتنفيذ المهام إلى n8n.','آتلاس از یک نیاز ساده شروع شد: می‌خواستم یک دستیار دسکتاپ داشته باشم که واقعاً روی سیستم من کار انجام بده، نه فقط جواب سؤال بده. بعد از تست چند فریم‌ورک، رابط کاربری رو با Python و PySide6 ساختم؛ چون هم نیتیو و سریع بود، هم برخلاف خیلی از راه‌حل‌های Electron، مصرف حافظه‌ی معقولی داشت.\n\nمهم‌ترین تصمیم معماری، پلاگین‌محور بودن آتلاس بود. هسته‌ی اصلی فقط مسئول مدیریت رابط کاربری، صف دستورها و چرخه‌ی عمر پلاگین‌هاست؛ هر قابلیت جدید — از کنترل فایل‌ها تا خواندن ایمیل — یک پلاگین مستقل با اینترفیس مشخصه. این یعنی می‌تونم بدون دست‌زدن به هسته، قابلیت اضافه یا حذف کنم.\n\nبرای تشخیص دستور صوتی از یک موتور wake-word سبک استفاده کردم که آفلاین کار می‌کنه، و پشتیبانی چندزبانه (فارسی و انگلیسی) رو با یک لایه‌ی تشخیص زبان جلوی موتور تبدیل گفتار به متن اضافه کردم. اجرای واقعی دستورها — یعنی زنجیره‌ی «کاربر گفت → باید چه کاری انجام بشه» — رو به n8n سپردم؛ این باعث شد بتونم workflowهای پیچیده رو بدون تغییر کد پایتون، فقط با ویرایش گراف n8n به‌روزرسانی کنم.\n\nبزرگ‌ترین درسی که گرفتم این بود که جدا کردن «فهمیدن دستور» از «اجرای دستور» ارزشش خیلی بیشتر از پیچیدگی اضافه‌ایه که ایجاد می‌کنه. وقتی این دو لایه از هم جدا شدن، دیباگ کردن، تست کردن و حتی توضیح دادن پروژه به بقیه خیلی ساده‌تر شد.','Atlas started from a simple need: I wanted a desktop assistant that could actually do work on my machine, not just answer questions. After testing a few frameworks, I built the interface with Python and PySide6 — it\'s native and fast, and unlike a lot of Electron-based solutions, it keeps memory usage reasonable.\n\nThe most important architectural decision was making Atlas plugin-based. The core is only responsible for managing the UI, the command queue, and the plugin lifecycle; every new capability — from file control to reading email — is an independent plugin with a defined interface. That means I can add or remove capabilities without touching the core.\n\nFor voice command recognition I used a lightweight wake-word engine that works fully offline, and added multilingual support (Persian and English) with a language-detection layer in front of the speech-to-text engine. I handed off the actual execution of commands — the chain of \'the user said X → what should happen next\' — to n8n; that let me update complex workflows just by editing the n8n graph, without touching any Python code.\n\nThe biggest lesson I learned was that separating \'understanding a command\' from \'executing a command\' is worth far more than the extra complexity it introduces. Once those two layers were properly split apart, debugging, testing, and even explaining the project to other people all got a lot simpler.','بدأ أطلس من حاجة بسيطة: أردت مساعد سطح مكتب يقوم فعلًا بتنفيذ مهام على جهازي، لا أن يكتفي بالإجابة عن الأسئلة. بعد تجربة عدة أطر عمل، بنيت الواجهة بلغة Python وPySide6؛ لأنها أصلية وسريعة، وعلى عكس كثير من حلول Electron، كان استهلاكها للذاكرة معقولًا.\n\nكان أهم قرار معماري هو جعل أطلس قائمًا على الإضافات (plugins). النواة الأساسية مسؤولة فقط عن إدارة الواجهة وقائمة الأوامر ودورة حياة الإضافات؛ كل قدرة جديدة — من التحكم بالملفات إلى قراءة البريد الإلكتروني — هي إضافة مستقلة بواجهة محددة. هذا يعني أنه بإمكاني إضافة أو حذف قدرات دون المساس بالنواة.\n\nلتمييز الأوامر الصوتية استخدمت محرك wake-word خفيفًا يعمل دون اتصال بالإنترنت، وأضفت دعمًا متعدد اللغات (الفارسية والإنجليزية) بطبقة للتعرف على اللغة أمام محرك تحويل الصوت إلى نص. أما التنفيذ الفعلي للأوامر — أي سلسلة «قال المستخدم كذا → ما الذي يجب فعله» — فقد أوكلته إلى n8n؛ ما مكّنني من تحديث سير عمل معقد دون تغيير كود بايثون، فقط بتعديل مخطط n8n.\n\nأهم درس تعلمته هو أن الفصل بين «فهم الأمر» و«تنفيذ الأمر» يستحق التعقيد الإضافي الذي يخلقه بأضعاف. فبمجرد فصل هاتين الطبقتين، أصبح تصحيح الأخطاء والاختبار وحتى شرح المشروع للآخرين أسهل بكثير.','هوش مصنوعی,پایتون,فول‌استک',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,7,1,'published','2026-06-23 09:00:00','2026-08-28 10:54:16','2026-08-28 10:54:16'),
(2,1,1,'worldcup','⚽','پیش‌بینی نتایج جام‌جهانی ۲۰۲۶ با ترکیب رتبه‌بندی الو و یادگیری ماشین','Predicting 2026 World Cup Results by Combining Elo Ratings with Machine Learning','التنبؤ بنتائج كأس العالم 2026 بدمج تصنيف إيلو مع التعلم الآلي','چطور یک موتور رتبه‌بندی الو رو با چند کلاسیفایر یادگیری‌ماشین و یک مدل گل‌زنی پواسون/دیکسون-کولز ترکیب کردم تا احتمال نتایج جام‌جهانی رو پیش‌بینی کنم.','How I combined an Elo rating engine with several machine-learning classifiers and a Poisson/Dixon-Coles scoring model to predict the probability of World Cup match outcomes.','كيف دمجت محرك تصنيف إيلو مع عدة مصنّفات تعلم آلي ونموذج تسجيل أهداف بواسون/ديكسون-كولز للتنبؤ باحتمالات نتائج كأس العالم.','ایده‌ی این پروژه ساده بود ولی اجراش نه: می‌خواستم برای جام‌جهانی ۲۰۲۶ سیستمی بسازم که فقط یک عدد «برد/باخت» ندهد، بلکه توزیع کامل احتمال نتایج هر بازی رو نشون بده. برای این کار سه لایه رو روی هم سوار کردم.\n\nلایه‌ی اول یک موتور رتبه‌بندی الو (Elo) اختصاصیه که با هر بازی به‌روزرسانی می‌شه و علاوه بر برد/باخت، اختلاف گل و اهمیت بازی (دوستانه در برابر رسمی) رو هم وزن‌دهی می‌کنه. لایه‌ی دوم چند کلاسیفایر یادگیری‌ماشین (از جمله مدل‌های درختی) هستن که روی ویژگی‌های آماری تیم‌ها — فرم اخیر، آمار حمله و دفاع، سابقه‌ی رودررو — آموزش دیدن. لایه‌ی سوم یک مدل پواسون/دیکسون-کولزه که تعداد گل‌های هر تیم رو به‌صورت جداگانه مدل می‌کنه تا بشه احتمال دقیق‌تر مثل «۲ بر ۱» رو هم محاسبه کرد، نه فقط نتیجه‌ی کلی.\n\nخروجی نهایی این سه لایه با هم ترکیب می‌شه و روی یک داشبورد فارسی و راست‌چین نمایش داده می‌شه؛ چون هدف اصلی این بود که کاربر فارسی‌زبان هم بتونه راحت باهاش کار کنه، نه فقط یک نوت‌بوک تحلیلی برای خودم.\n\nچالش اصلی، همیشه داده بود نه مدل. تیم‌های ملی برخلاف باشگاه‌ها بازی‌های کمی در سال دارن، پس مدل باید با داده‌ی کم هم قابل‌اعتماد بمونه — همین موضوع وادارم کرد به‌جای یک مدل «قدرتمند»، سراغ ترکیب چند مدل ساده‌تر و قابل‌تفسیرتر برم.','The idea behind this project was simple, but the execution wasn\'t: I wanted to build a system for the 2026 World Cup that wouldn\'t just spit out a single win/loss number, but show the full probability distribution of each match\'s outcome. To do that, I stacked three layers on top of each other.\n\nThe first layer is a custom Elo rating engine that updates after every match and weighs not just win/loss but also goal difference and match importance (friendly vs. official). The second layer is a set of machine-learning classifiers (including tree-based models) trained on teams\' statistical features — recent form, attack and defense stats, and head-to-head history. The third layer is a Poisson/Dixon-Coles model that models each team\'s goal count separately, so I can compute more precise probabilities like \'2-1\', not just the overall result.\n\nThe final output from all three layers is combined and shown on a Persian, right-to-left dashboard — because the main goal was for a Persian-speaking user to be able to work with it comfortably, not just to build an analytical notebook for myself.\n\nThe real challenge was always the data, not the model. National teams, unlike clubs, play very few matches per year, so the model had to stay reliable even with little data — which is exactly what pushed me toward combining several simpler, more interpretable models instead of chasing one \'powerful\' one.','كانت فكرة هذا المشروع بسيطة، لكن تنفيذها لم يكن كذلك: أردت أن أبني لكأس العالم 2026 نظامًا لا يقدّم رقمًا واحدًا فقط لـ«فوز/خسارة»، بل يعرض التوزيع الكامل لاحتمالات نتيجة كل مباراة. لتحقيق ذلك، بنيت ثلاث طبقات فوق بعضها.\n\nالطبقة الأولى محرك تصنيف إيلو (Elo) مخصص يتحدّث بعد كل مباراة، ويأخذ في الحسبان — إلى جانب الفوز والخسارة — فارق الأهداف وأهمية المباراة (ودية مقابل رسمية). الطبقة الثانية عدة مصنّفات تعلم آلي (منها نماذج شجرية) دُرِّبت على الخصائص الإحصائية للفرق — الأداء الأخير، إحصاءات الهجوم والدفاع، وتاريخ المواجهات المباشرة. الطبقة الثالثة نموذج بواسون/ديكسون-كولز الذي يُنمذج عدد أهداف كل فريق بشكل منفصل، بحيث يمكن حساب احتمال أدق مثل «2-1»، لا النتيجة العامة فقط.\n\nيُدمج الناتج النهائي لهذه الطبقات الثلاث ويُعرض على لوحة تحكم فارسية من اليمين إلى اليسار؛ لأن الهدف الأساسي كان تمكين المستخدم الناطق بالفارسية من التعامل معها بسهولة، لا مجرد بناء دفتر تحليل لنفسي.\n\nكان التحدي الأكبر دائمًا هو البيانات لا النموذج. فالمنتخبات الوطنية، على عكس الأندية، تخوض عددًا قليلًا من المباريات سنويًا، لذا يجب أن يظل النموذج موثوقًا حتى مع بيانات قليلة — وهو ما دفعني إلى الاعتماد على دمج عدة نماذج أبسط وأكثر قابلية للتفسير بدلًا من نموذج واحد «قوي».','هوش مصنوعی,یادگیری ماشین,پایتون',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,8,0,'published','2026-06-08 09:00:00','2026-08-28 10:54:16','2026-08-28 10:54:16'),
(3,1,1,'telegram-bot','🤖','چطور یک ربات تلگرام دوزبانه با n8n ساختم؛ بدون یک خط کد بک‌اند پیچیده','How I Built a Bilingual Telegram Bot with n8n — Without a Single Line of Complex Backend Code','كيف بنيت بوت تيليجرام ثنائي اللغة باستخدام n8n دون سطر واحد من كود خلفي معقد','ساخت یک دستیار شخصی روی تلگرام با n8n و Gemini Flash Lite؛ از طراحی سیستم‌پرامپت تا ساخت یک پایگاه‌دانش ۵۰۰ رکوردی و رفع باگ‌های حافظه‌ی مکالمه.','Building a personal assistant on Telegram with n8n and Gemini Flash Lite — from designing the system prompt to building a 500-record knowledge base and fixing conversation-memory bugs.','بناء مساعد شخصي على تيليجرام باستخدام n8n وGemini Flash Lite؛ من تصميم System Prompt إلى بناء قاعدة معرفة من 500 سجل وإصلاح أخطاء ذاكرة المحادثة.','برای دستیار شخصی‌ام نمی‌خواستم یک سرور جدا نگه دارم؛ می‌خواستم بشه سریع تغییرش داد. برای همین کل منطق ربات تلگرام رو به‌جای کدنویسی سنتی، به‌صورت workflow در n8n ساختم و از مدل Gemini Flash Lite به‌عنوان مغز مکالمه استفاده کردم.\n\nاولین قدم، نوشتن یک system prompt خودکفا بود که شخصیت، لحن و محدودیت‌های ربات رو دقیق مشخص می‌کرد. بعد یک پایگاه‌دانش JSONL با بیش از ۵۰۰ رکورد از روی فایل‌های بیوگرافی و قیمت‌گذاری تولید کردم تا ربات بتونه به سؤالات تکراری با اطلاعات درست جواب بده، نه فرضیات مدل زبانی.\n\nسخت‌ترین بخش، مدیریت حافظه‌ی مکالمه بود. n8n برای هر session یک شناسه می‌سازه، ولی وقتی این شناسه بین شاخه‌های مختلف workflow به‌درستی نرمالایز نمی‌شد، ربات مکالمه رو گم می‌کرد و انگار هر پیام یک مکالمه‌ی تازه بود. رفع این باگ یعنی یکسان‌سازی دقیق فرمت شناسه‌ی session قبل از رسیدن به نود حافظه — یک تغییر کوچک با تأثیر بزرگ روی کیفیت مکالمه.\n\nنسخه‌ی اول ربات حتی امکاناتی مثل رونویسی صدا با Whisper، پاسخ صوتی (TTS)، جست‌وجوی معنایی روی اسناد (RAG با Simple Vector Store) و دریافت فایل از تلگرام رو هم داشت. یاد گرفتم که در اتوماسیون‌های no-code، مدیریت state و شناسه‌ها معمولاً سخت‌تر از خودِ منطق هوش مصنوعیه.','For my personal assistant, I didn\'t want to maintain a separate server — I wanted something I could change quickly. So instead of traditional coding, I built the entire Telegram bot\'s logic as a workflow in n8n, using Gemini Flash Lite as the conversational brain.\n\nThe first step was writing a self-contained system prompt that precisely defined the bot\'s personality, tone, and limits. Then I generated a JSONL knowledge base with over 500 records from biography and pricing files, so the bot could answer repetitive questions with correct information instead of the language model\'s guesses.\n\nThe hardest part was managing conversation memory. n8n generates an ID for every session, but when that ID wasn\'t normalized correctly across different branches of the workflow, the bot would lose the thread of the conversation — as if every message were a brand-new chat. Fixing that bug meant precisely standardizing the session ID format before it ever reached the memory node — a small change with a big impact on conversation quality.\n\nThe first version of the bot even had features like voice transcription with Whisper, text-to-speech replies, semantic search over documents (RAG with a simple vector store), and receiving files from Telegram. I learned that in no-code automation, managing state and IDs is usually harder than the AI logic itself.','لم أرغب، من أجل مساعدي الشخصي، في الحفاظ على خادم منفصل؛ أردت شيئًا يمكن تعديله بسرعة. لذلك بنيت منطق بوت تيليجرام بالكامل على شكل سير عمل (workflow) في n8n بدلًا من البرمجة التقليدية، واستخدمت نموذج Gemini Flash Lite كعقل للمحادثة.\n\nكانت الخطوة الأولى كتابة System Prompt مكتفٍ ذاتيًا يحدد بدقة شخصية البوت ونبرته وحدوده. ثم أنشأت قاعدة معرفة بصيغة JSONL تضم أكثر من 500 سجل من ملفات السيرة الذاتية والتسعير، حتى يتمكن البوت من الإجابة عن الأسئلة المتكررة بمعلومات صحيحة، لا بتخمينات النموذج اللغوي.\n\nكان أصعب جزء هو إدارة ذاكرة المحادثة. يُنشئ n8n معرّفًا لكل جلسة، لكن عندما لم يكن هذا المعرّف يُوحَّد بشكل صحيح بين فروع سير العمل المختلفة، كان البوت يفقد سياق المحادثة وكأن كل رسالة محادثة جديدة. إصلاح هذا الخطأ يعني توحيد تنسيق معرّف الجلسة بدقة قبل وصوله إلى عقدة الذاكرة — تغيير صغير بأثر كبير على جودة المحادثة.\n\nحتى أن النسخة الأولى من البوت تضمنت ميزات مثل تفريغ الصوت إلى نص باستخدام Whisper، والرد الصوتي (TTS)، والبحث الدلالي في المستندات (RAG بمخزن متجهات بسيط)، واستقبال الملفات من تيليجرام. تعلمت أن إدارة الحالة والمعرّفات في الأتمتة بلا كود عادةً أصعب من منطق الذكاء الاصطناعي نفسه.','اتوماسیون,هوش مصنوعی,بک‌اند',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,6,0,'published','2026-05-14 09:00:00','2026-08-28 10:54:16','2026-08-28 10:54:16'),
(4,2,1,'threejs-bg','🌌','چرا Three.js رو برای پس‌زمینه‌ی سه‌بعدی همین سایت انتخاب کردم','Why I Chose Three.js for This Site\'s 3D Background','لماذا اخترت Three.js لخلفية ثلاثية الأبعاد لهذا الموقع','تصمیم پشت صحنه‌ی فضایی همین پورتفولیو؛ از مدل‌سازی زحل و کنترل دوربین با اسکرول تا نگه‌داشتن فریم‌ریت بالا روی موبایل.','The thinking behind this portfolio\'s cinematic space background — from modeling Saturn and scroll-driven camera control to keeping the frame rate high on mobile.','القرار وراء المشهد الفضائي السينمائي لهذا الموقع الشخصي؛ من نمذجة زحل والتحكم بالكاميرا عبر التمرير إلى الحفاظ على معدل إطارات مرتفع على الجوال.','وقتی تصمیم گرفتم پس‌زمینه‌ی همین سایت رو به یک صحنه‌ی سینمایی فضایی تبدیل کنم، اولین سؤال این بود: چه‌قدر سنگینه؟ خیلی از پیاده‌سازی‌های سه‌بعدی روی وب، روی موبایل کند می‌شن یا باتری رو خیلی سریع خالی می‌کنن. Three.js رو انتخاب کردم چون کنترل کامل روی geometry، متریال‌ها و render loop بهم می‌ده — می‌تونم دقیقاً همون چیزی که لازمه رو رندر کنم، نه بیشتر.\n\nحرکت دوربین با اسکرول رو با GSAP و ScrollTrigger پیاده کردم؛ یعنی موقعیت دوربین دور زحل، مستقیماً به درصد اسکرول کاربر گره خورده، مثل عقب‌وجلو بردن یک ویدیوی از پیش‌ضبط‌شده. نکته‌ی مهم اینجا interpolation نرمه — اگه موقعیت دوربین رو مستقیم به scroll position ست کنی، حرکت تکه‌تکه به نظر می‌رسه؛ باید یک لایه‌ی easing بینشون باشه.\n\nبرای عملکرد روی موبایل، چند تصمیم گرفتم: تعداد ذرات کهکشان رو بر اساس اندازه‌ی صفحه محاسبه کردم، سایه‌ها رو غیرفعال کردم و به‌جای shader واقعی برای نورپردازی زحل، از یک ترکیب texture و rim-light ساده استفاده کردم که تقریباً همون حس رو می‌ده با هزینه‌ی محاسباتی خیلی کمتر.\n\nنتیجه یک پس‌زمینه‌ست که هم روی دسکتاپ و هم موبایل با فریم‌ریت بالا اجرا می‌شه، بدون این‌که هیچ‌وقت حس بدهد داره «تظاهر» به سه‌بعدی بودن می‌کنه.','When I decided to turn this site\'s background into a cinematic space scene, the first question was: how heavy would it be? A lot of 3D implementations on the web slow down on mobile or drain the battery fast. I chose Three.js because it gives me full control over geometry, materials, and the render loop — I can render exactly what\'s needed, and nothing more.\n\nI built the scroll-driven camera movement with GSAP and ScrollTrigger, so the camera\'s position around Saturn is tied directly to the user\'s scroll percentage — like scrubbing back and forth through a pre-recorded video. The important part here is smooth interpolation — if you set the camera\'s position directly from the scroll position, the motion looks choppy; there needs to be an easing layer in between.\n\nFor mobile performance, I made a few decisions: I calculate the number of galaxy particles based on screen size, disabled shadows, and instead of a real shader for lighting Saturn, I used a simple combination of texture and rim-light that gives almost the same feel at a much lower computational cost.\n\nThe result is a background that runs at a high frame rate on both desktop and mobile, without ever feeling like it\'s \'pretending\' to be 3D.','عندما قررت تحويل خلفية هذا الموقع إلى مشهد فضائي سينمائي، كان أول سؤال: ما مدى ثِقَل هذا على الأداء؟ كثير من التطبيقات ثلاثية الأبعاد على الويب تصبح بطيئة على الجوال أو تستنزف البطارية بسرعة. اخترت Three.js لأنه يمنحني تحكمًا كاملًا في الأشكال الهندسية والمواد وحلقة العرض (render loop) — يمكنني عرض ما هو ضروري بالضبط، لا أكثر.\n\nنفّذت حركة الكاميرا المرتبطة بالتمرير باستخدام GSAP وScrollTrigger؛ أي أن موقع الكاميرا حول زحل مرتبط مباشرة بنسبة تمرير المستخدم، تمامًا مثل تحريك فيديو مسجَّل مسبقًا للأمام والخلف. النقطة المهمة هنا هي التدرّج السلس (interpolation) — فإذا ربطت موقع الكاميرا مباشرة بموضع التمرير، ستبدو الحركة متقطعة؛ يجب أن توجد طبقة تسهيل (easing) بينهما.\n\nمن أجل الأداء على الجوال، اتخذت عدة قرارات: حسبت عدد جسيمات المجرة بناءً على حجم الشاشة، وعطّلت الظلال، واستخدمت بدلًا من شيدر حقيقي لإضاءة زحل مزيجًا بسيطًا من الملمس (texture) وrim-light يعطي إحساسًا مماثلًا تقريبًا بتكلفة حسابية أقل بكثير.\n\nوالنتيجة خلفية تعمل بمعدل إطارات مرتفع على كل من سطح المكتب والجوال، دون أن تعطي أبدًا إحساسًا بأنها «تتظاهر» بأنها ثلاثية الأبعاد.','Three.js,وب,طراحی رابط',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,5,0,'published','2026-04-25 09:00:00','2026-08-28 10:54:16','2026-08-28 10:54:16'),
(5,3,1,'fullstack-arch','🏗️','معماری فول‌استک مدرن: وقتی React با پایتون در یک پروژه کنار هم می‌شینن','Modern Full-Stack Architecture: When React and Python Sit Together in One Project','بنية متكاملة حديثة: عندما يجتمع React وبايثون في مشروع واحد','الگویی که این روزها برای پروژه‌های فول‌استک استفاده می‌کنم؛ فرانت‌اند React یا Next.js، بک‌اند پایتون (FastAPI/Django) و مرز مشخص بین این دو دنیا.','The pattern I use these days for full-stack projects — a React or Next.js frontend, a Python backend (FastAPI/Django), and a clear boundary between the two worlds.','النمط الذي أستخدمه هذه الأيام للمشاريع المتكاملة؛ واجهة أمامية بـ React أو Next.js، وواجهة خلفية بايثون (FastAPI/Django)، وحدود واضحة بين هذين العالمين.','در پروژه‌های فول‌استکی که این روزها می‌سازم، تقریباً همیشه به یک الگو می‌رسم: فرانت‌اند با React یا Next.js، و بک‌اند با پایتون. دلیلش این نیست که این ترکیب «مد روزه»؛ دلیلش اینه که مرز تمیزی بین رابط کاربری و منطق سنگین (پردازش داده، مدل‌های یادگیری‌ماشین، اتوماسیون) ایجاد می‌کنه.\n\nReact/Next.js برای من یعنی رابط کاربری سریع، رندر ترکیبی (سرور و کلاینت) و تجربه‌ی توسعه‌ی خوب. پایتون برای من یعنی دسترسی مستقیم به کل اکوسیستم علم داده و هوش مصنوعی — از Pandas تا PyTorch — بدون این‌که مجبور باشم منطق مدل رو به جاوااسکریپت ترجمه کنم.\n\nنکته‌ی کلیدی، طراحی درست API بین این دو لایه‌ست. من ترجیح می‌دم قراردادهای API رو زود مشخص کنم — چه دیتایی رد و بدل می‌شه، چه خطاهایی ممکنه برگرده — تا فرانت‌اند و بک‌اند بتونن تقریباً مستقل از هم پیش برن. این یعنی می‌تونم بک‌اند رو با Django یا FastAPI بسازم، بدون این‌که فرانت‌اند منتظر بمونه.\n\nبرای دیتابیس و سرویس‌های ابری هم سعی می‌کنم از اول مقیاس‌پذیری رو در نظر بگیرم، نه این‌که بعداً معماری رو دوباره بنویسم. نتیجه پروژه‌هاییه که هم توسعه‌شون سریعه، هم نگه‌داری‌شون آسون.','In the full-stack projects I build these days, I almost always land on the same pattern: a React or Next.js frontend, and a Python backend. That\'s not because this combination is \'trendy\' — it\'s because it creates a clean boundary between the interface and heavy logic (data processing, machine-learning models, automation).\n\nReact/Next.js, to me, means a fast interface, hybrid rendering (server and client), and a good developer experience. Python, to me, means direct access to the entire data-science and AI ecosystem — from Pandas to PyTorch — without having to translate model logic into JavaScript.\n\nThe key is designing the API between these two layers properly. I prefer to nail down the API contracts early — what data goes back and forth, what errors might come back — so the frontend and backend can move almost independently of each other. That means I can build the backend with Django or FastAPI without the frontend having to wait around.\n\nFor databases and cloud services, I also try to think about scalability from the start, rather than rewriting the architecture later. The result is projects that are both fast to develop and easy to maintain.','في المشاريع المتكاملة التي أبنيها هذه الأيام، أصل تقريبًا دائمًا إلى نمط واحد: واجهة أمامية بـ React أو Next.js، وواجهة خلفية بايثون. والسبب ليس أن هذا المزيج «رائج»؛ بل لأنه يخلق حدًا نظيفًا بين الواجهة والمنطق الثقيل (معالجة البيانات، نماذج التعلم الآلي، الأتمتة).\n\nبالنسبة لي، يعني React/Next.js واجهة سريعة، وعرضًا هجينًا (على الخادم والعميل)، وتجربة تطوير جيدة. أما بايثون فيعني بالنسبة لي وصولًا مباشرًا إلى كامل منظومة علوم البيانات والذكاء الاصطناعي — من Pandas إلى PyTorch — دون الحاجة لترجمة منطق النموذج إلى جافاسكريبت.\n\nالنقطة الأساسية هي التصميم الصحيح لواجهة برمجة التطبيقات (API) بين هاتين الطبقتين. أفضّل تحديد عقود API مبكرًا — أي بيانات تُتبادل، وأي أخطاء قد تُعاد — حتى تتمكن الواجهتان الأمامية والخلفية من التقدم بشكل شبه مستقل. هذا يعني أنه بإمكاني بناء الواجهة الخلفية بـ Django أو FastAPI دون أن تنتظر الواجهة الأمامية.\n\nبالنسبة لقواعد البيانات والخدمات السحابية، أحاول أيضًا مراعاة قابلية التوسع منذ البداية، بدلًا من إعادة كتابة البنية لاحقًا. والنتيجة مشاريع سريعة التطوير وسهلة الصيانة في آنٍ معًا.','React,Next.js,فول‌استک,بک‌اند',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,7,0,'published','2026-04-11 09:00:00','2026-08-28 10:54:16','2026-08-28 10:54:16'),
(6,2,1,'performance','⚡','۷ تکنیک بهینه‌سازی عملکرد که هر توسعه‌دهنده‌ی وب باید بشناسه','7 Performance Optimization Techniques Every Web Developer Should Know','7 تقنيات لتحسين الأداء يجب أن يعرفها كل مطوّر ويب','از lazy loading واقعی گرفته تا کنترل backdrop-filter روی موبایل؛ تکنیک‌هایی که در پروژه‌های شخصی و پروژه‌های مشتری واقعاً فرق ایجاد کردن.','From genuine lazy loading to taming backdrop-filter on mobile — techniques that made a real difference in both personal and client projects.','من التحميل الكسول (lazy loading) الحقيقي إلى ضبط backdrop-filter على الجوال؛ تقنيات أحدثت فرقًا حقيقيًا في المشاريع الشخصية ومشاريع العملاء.','خیلی از توصیه‌های «بهینه‌سازی عملکرد» تئوریه و در عمل فرق محسوسی ایجاد نمی‌کنه. این‌ها هفت موردی هستن که در پروژه‌های واقعی، از سایت‌های شرکتی گرفته تا پورتفولیوهای شخصی، تفاوت واقعی ایجاد کردن.\n\nاول، lazy loading واقعی — نه فقط تصاویر، بلکه کامپوننت‌ها و کتابخانه‌های سنگین (مثل Three.js یا نمودارها) که فقط وقتی کاربر به اون بخش می‌رسه بارگذاری بشن. دوم، محدود کردن backdrop-filter روی موبایل؛ افکت شیشه‌ای زیبا به نظر می‌رسه ولی روی گوشی‌های ضعیف‌تر فریم‌ریت رو به‌شدت پایین می‌آره — راه‌حل من معمولاً کاهش شدت blur یا غیرفعال‌کردنش زیر یک حد مشخص از پیکسل‌دنسیتیه.\n\nسوم، استفاده از will-change فقط جایی که واقعاً لازمه، نه روی همه‌چیز. چهارم، debounce کردن رویدادهای scroll و resize. پنجم، فشرده‌سازی و تبدیل تصاویر به فرمت‌های مدرن مثل WebP. ششم، جدا کردن انیمیشن‌های مبتنی بر transform/opacity (که روی GPU اجرا می‌شن) از انیمیشن‌هایی که layout رو دوباره محاسبه می‌کنن.\n\nو هفتم، شاید مهم‌ترین: اندازه‌گیری قبل از بهینه‌سازی. چند بار پیش اومده که فکر می‌کردم فلان بخش کنده، ولی پروفایلر مرورگر نشون داده مشکل اصلی جای دیگه‌ایه. بدون اندازه‌گیری، بهینه‌سازی فقط حدس‌زنیه.','A lot of \'performance optimization\' advice is theoretical and doesn\'t make a noticeable difference in practice. These are seven that made a real difference in actual projects, from corporate sites to personal portfolios.\n\nFirst, genuine lazy loading — not just images, but heavy components and libraries (like Three.js or charts) that only load once the user actually reaches that section. Second, limiting backdrop-filter on mobile; the glass effect looks beautiful, but it tanks the frame rate hard on weaker phones — my usual fix is reducing the blur intensity or disabling it below a certain pixel-density threshold.\n\nThird, using will-change only where it\'s genuinely needed, not on everything. Fourth, debouncing scroll and resize events. Fifth, compressing images and converting them to modern formats like WebP. Sixth, separating transform/opacity-based animations (which run on the GPU) from animations that force layout recalculation.\n\nAnd seventh, maybe the most important one: measure before you optimize. More than once I was sure some section was slow, only for the browser profiler to show the real bottleneck was somewhere else entirely. Without measuring, optimization is just guessing.','كثير من نصائح «تحسين الأداء» نظرية ولا تُحدث فرقًا ملموسًا في الواقع. هذه سبع تقنيات أحدثت فرقًا حقيقيًا في مشاريع فعلية، من مواقع الشركات إلى ملفات الأعمال الشخصية.\n\nأولًا، التحميل الكسول الحقيقي — ليس للصور فقط، بل للمكوّنات والمكتبات الثقيلة (مثل Three.js أو الرسوم البيانية) بحيث تُحمَّل فقط عندما يصل المستخدم فعليًا إلى ذلك القسم. ثانيًا، تقييد backdrop-filter على الجوال؛ فتأثير الزجاج جميل المظهر، لكنه يخفض معدل الإطارات بشدة على الهواتف الأضعف — وحلّي المعتاد هو تقليل شدة التمويه أو تعطيله تحت حد معيّن من كثافة البكسل.\n\nثالثًا، استخدام will-change فقط حيث يكون ضروريًا فعلًا، لا على كل شيء. رابعًا، تأخير (debounce) أحداث التمرير وتغيير الحجم. خامسًا، ضغط الصور وتحويلها إلى صيغ حديثة مثل WebP. سادسًا، فصل الحركات المعتمدة على transform/opacity (التي تعمل على GPU) عن الحركات التي تُعيد حساب التخطيط (layout).\n\nوسابعًا، وربما الأهم: القياس قبل التحسين. حدث أكثر من مرة أن ظننت أن قسمًا معينًا بطيء، ليُظهر محلل المتصفح أن المشكلة الحقيقية في مكان آخر تمامًا. فبدون قياس، يبقى التحسين مجرد تخمين.','عملکرد,وب,طراحی رابط',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,6,0,'published','2026-03-19 09:00:00','2026-08-28 10:54:16','2026-08-28 10:54:16');
INSERT INTO `portfolio_items` (`id`, `slug`, `category`, `title_fa`, `title_en`, `title_ar`, `tag_fa`, `tag_en`, `tag_ar`, `description_fa`, `description_en`, `description_ar`, `tech_stack`, `cover_image`, `glyph`, `project_url`, `is_featured`, `display_order`, `status`, `created_at`, `updated_at`) VALUES (1,'atlas','ai','آتلاس — دستیار هوشمند دسکتاپ','Atlas — AI Desktop Assistant','آتلاس — مساعد ذكي لسطح المكتب','دستیار هوش مصنوعی','AI Assistant','مساعد ذكاء اصطناعي','دستیار هوشمند دسکتاپی شبیه جارویس برای ویندوز؛ معماری پلاگین‌محور، تشخیص صدای کاملاً آفلاین، حافظه‌ی معنایی و رابط کاربری انیمیشنی ۶۰ فریم بر ثانیه.','A Jarvis-style AI desktop assistant for Windows: modular plugin architecture, a fully offline voice pipeline, semantic memory, and a 60fps animated interface.','مساعد ذكاء اصطناعي على طراز جارفيس لنظام Windows: بنية إضافات معيارية، مسار صوتي بلا اتصال بالكامل، ذاكرة دلالية، وواجهة رسوم متحركة بمعدل 60 إطارًا.','Python,PySide6,faster-whisper',NULL,'🖥️',NULL,1,1,'published','2026-08-28 10:55:03','2026-08-28 10:55:03'),
(2,'world-cup-prediction','ai','پیش‌بینی نتایج جام‌جهانی ۲۰۲۶','2026 World Cup Prediction System','نظام التنبؤ بكأس العالم 2026','یادگیری ماشین','Machine Learning','تعلم آلي','سامانه‌ی پیش‌بینی فوتبال با ترکیب موتور امتیازدهی Elo، چند طبقه‌بند یادگیری ماشین و مدل گل‌زنی پواسون/Dixon-Coles؛ با داشبورد فارسی و توضیح‌پذیری SHAP.','A football prediction system combining an Elo rating engine, several ML classifiers, and a Poisson/Dixon-Coles goals model, with a Persian dashboard and SHAP explainability.','نظام للتنبؤ بكرة القدم يجمع بين محرك تصنيف Elo وعدة مصنّفات تعلم آلي ونموذج بواسون/ديكسون-كولز للأهداف، بلوحة تحكم فارسية وقابلية تفسير SHAP.','Python,scikit-learn,Flask,SHAP',NULL,'⚽',NULL,0,2,'published','2026-08-28 10:55:03','2026-08-28 10:55:03'),
(3,'gold-dollar-trading-analysis','ai','تحلیل معاملاتی طلا و دلار (XAU/USD · DXY)','Gold & Dollar Trading Analysis (XAU/USD · DXY)','تحليل تداول الذهب والدولار (XAU/USD · DXY)','یادگیری عمیق','Deep Learning','تعلم عميق','خط لوله‌ای با ۱۶۶ ویژگی برای تحلیل بازار طلا و شاخص دلار؛ ترکیب ۱۰ مدل کلاسیک و ۱۰ معماری یادگیری عمیق، با استراتژی آنسمبل و اعتبارسنجی walk-forward.','A 166-feature pipeline for analyzing the gold market and dollar index, combining 10 classical ML models and 10 deep learning architectures with ensemble strategies and walk-forward validation.','خط معالجة بـ166 خاصية لتحليل سوق الذهب ومؤشر الدولار، يجمع 10 نماذج تعلم آلي كلاسيكية و10 معماريات تعلم عميق مع استراتيجيات جماعية وتحقق walk-forward.','Python,PyTorch,Flask,SHAP',NULL,'📈',NULL,0,3,'published','2026-08-28 10:55:03','2026-08-28 10:55:03'),
(4,'mercedes-cls63-amg-landing','web','لندینگ سینمایی مرسدس‌بنز CLS63 AMG','Mercedes-Benz CLS63 AMG Cinematic Landing Page','صفحة هبوط مرسيدس-بنز CLS63 AMG السينمائية','وب سه‌بعدی','3D Web','ويب ثلاثي الأبعاد','لندینگ‌پیج سینمایی با Next.js 15 و React Three Fiber؛ ۲۳ بخش روایت فارسی و انیمیشن سه‌بعدی هم‌گام با اسکرول توسط GSAP ScrollTrigger.','A cinematic landing page built with Next.js 15 and React Three Fiber: 23 Persian narrative sections with scroll-driven 3D animation powered by GSAP ScrollTrigger.','صفحة هبوط سينمائية مبنية بـNext.js 15 وReact Three Fiber: 23 قسمًا سرديًا بالفارسية ورسوم ثلاثية الأبعاد مرتبطة بالتمرير عبر GSAP ScrollTrigger.','Next.js 15,React Three Fiber,GSAP',NULL,'🏎️',NULL,1,4,'published','2026-08-28 10:55:03','2026-08-28 10:55:03'),
(5,'tahrirdan-stationery-store','web','تحریردان — فروشگاه لوازم‌التحریر','Tahrirdan — Stationery Store','تحریردان — متجر القرطاسية','وب فول‌استک','Full-Stack Web','ويب متكامل','طراحی و پیاده‌سازی کامل فروشگاه آنلاین تحریردان از صفر؛ بک‌اند Flask، دیتابیس MySQL و رابط کاربری سه‌بعدی شیشه‌ای، همراه با سئوی فنی کامل.','Complete design and build of the Tahrirdan online store from scratch: Flask backend, MySQL database, and a 3D glass interface, plus full technical SEO.','تصميم وبناء متجر تحریردان الإلكتروني بالكامل من الصفر: خلفية Flask، قاعدة بيانات MySQL، وواجهة زجاجية ثلاثية الأبعاد، مع تحسين تقني كامل لمحركات البحث.','Flask,MySQL,Glass UI',NULL,NULL,NULL,0,5,'published','2026-08-28 10:55:03','2026-08-28 10:55:03'),
(6,'nova-cafe-3d-menu','web','نوا کافه — منوی دیجیتال سه‌بعدی','NOVA CAFE — 3D Digital Menu','نوفا كافيه — قائمة رقمية ثلاثية الأبعاد','وب فول‌استک','Full-Stack Web','ويب متكامل','منوی دیجیتال پرمیوم برای یک کافه با بک‌اند اختصاصی PHP 8 و MySQL، پنل مدیریت کامل و جلوه‌های سه‌بعدی Three.js — بدون وردپرس.','A premium digital menu for a café with a custom PHP 8 / MySQL backend, a full admin panel, and Three.js 3D effects on the frontend — no WordPress.','قائمة رقمية فاخرة لمقهى بخلفية PHP 8 / MySQL مخصّصة، ولوحة تحكم كاملة، وتأثيرات Three.js ثلاثية الأبعاد في الواجهة — دون ووردبريس.','PHP 8,MySQL,Three.js',NULL,'☕',NULL,0,6,'published','2026-08-28 10:55:03','2026-08-28 10:55:03'),
(7,'qr-digital-cafe-menu','web','منوی دیجیتال QR کافه','QR Digital Café Menu','قائمة رقمية بكود QR لمقهى','وردپرس','WordPress','ووردبريس','منوی دیجیتال QR تک‌صفحه‌ای، پیاده‌سازی‌شده کاملاً بومی روی وردپرس و المنتور با طراحی کارت‌های اختصاصیِ گوشه‌تورفته.','A one-page QR digital menu built entirely native on WordPress and Elementor, with a signature inset-corner card design.','قائمة رقمية بكود QR من صفحة واحدة، مبنية بالكامل بشكل أصلي على ووردبريس وElementor بتصميم بطاقات مميز ذي زوايا غائرة.','WordPress,Elementor',NULL,'📋',NULL,0,7,'published','2026-08-28 10:55:03','2026-08-28 10:55:03'),
(8,'siavash-birjani-coaching','web','پلتفرم مربی‌گری سیاوش بیرجانی','Siavash Birjani Coaching Platform','منصة سياوش بيرجاني للتدريب','وب فول‌استک','Full-Stack Web','ويب متكامل','پلتفرم فول‌استک مربی‌گری بدنسازی و فروشگاه مکمل‌های ورزشی؛ بک‌اند PHP/mysqli اختصاصی و فرانت‌اند Tailwind، بدون فریم‌ورک.','A full-stack fitness coaching platform and supplement store: a custom PHP/mysqli backend and a Tailwind frontend, with no frameworks.','منصة متكاملة للتدريب الرياضي ومتجر المكملات الغذائية: خلفية PHP/mysqli مخصصة وواجهة أمامية بـTailwind، دون أي أطر عمل.','PHP,mysqli,Tailwind CSS',NULL,'💪',NULL,0,8,'published','2026-08-28 10:55:03','2026-08-28 10:55:03'),
(9,'malake-beauty-salon','web','سالن و آکادمی زیبایی ملکه','Malake Beauty Salon & Academy','صالون وأكاديمية ملكة للتجميل','وب فول‌استک','Full-Stack Web','ويب متكامل','وب‌سایت فول‌استک برای یک سالن و آکادمی زیبایی؛ بک‌اند PHP/mysqli خالص و رابط کاربری HTML/Tailwind/JS، بدون هیچ فریم‌ورکی.','A full-stack website for a beauty salon and academy: a pure PHP/mysqli backend and an HTML/Tailwind/JS interface, with no frameworks at all.','موقع متكامل لصالون وأكاديمية تجميل: خلفية PHP/mysqli خالصة وواجهة HTML/Tailwind/JS، دون أي أطر عمل على الإطلاق.','PHP,mysqli,Tailwind CSS',NULL,'💇‍♀️',NULL,0,9,'published','2026-08-28 10:55:03','2026-08-28 10:55:03');

SET FOREIGN_KEY_CHECKS = 1;
