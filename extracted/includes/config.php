<?php
/**
 * config.php — the ONLY file you need to edit to deploy this on your
 * own hosting. Fill in your real database credentials below (from your
 * host's cPanel / phpMyAdmin / hosting panel), then leave everything
 * else in this project alone.
 *
 * IMPORTANT: this file holds secrets. Keep it outside any web-accessible
 * "public"/"htdocs" folder if your host supports that; if it doesn't
 * (most shared hosting serves the whole project folder directly), at
 * minimum make sure your host's .htaccess/web server config denies
 * direct HTTP access to *.php files that aren't meant to be visited —
 * ask your host if you're unsure, since this varies by provider.
 */

// ---- Database connection ------------------------------------------------
define('DB_HOST', 'localhost');       // usually 'localhost' on shared hosting
define('DB_NAME', 'abjadian_portfolio');
define('DB_USER', 'root');            // <-- replace with your real DB username
define('DB_PASS', '');                // <-- replace with your real DB password
define('DB_CHARSET', 'utf8mb4');

// ---- Site -----------------------------------------------------------------
define('SITE_URL', 'https://abjadian.ir'); // canonical production domain — every canonical/OG/JSON-LD URL is built from this
define('SITE_NAME', 'Sepehr Abjadian');

// ---- Paths ------------------------------------------------------------
// BASE_PATH: absolute filesystem path to the project root (auto-detected,
// no need to edit). UPLOAD_PATH/UPLOAD_URL: where uploaded images are
// stored on disk vs. the URL used to display them.
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL', 'uploads');

// ---- reCAPTCHA (bot protection on admin login + public login/register) ---
// Free — get your own keys at https://www.google.com/recaptcha/admin
// (choose "reCAPTCHA v2 — I'm not a robot checkbox", register your real
// domain). Paste the Site key and Secret key below.
// Until you fill these in, login/register still have a honeypot +
// minimum-time-to-submit bot check (see includes/functions.php), so
// they're never left unprotected — reCAPTCHA on top makes it far
// stronger and is what actually stops solver bots/scripted attempts.
define('RECAPTCHA_SITE_KEY', '');   // <-- paste your Site key here
define('RECAPTCHA_SECRET_KEY', ''); // <-- paste your Secret key here

// ---- Environment ------------------------------------------------------
// Set to false once this is live on real hosting: true shows full PHP
// errors (useful while you're setting it up), false hides them from
// visitors and only writes them to the server's error log instead.
define('APP_DEBUG', true);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

date_default_timezone_set('Asia/Tehran');
