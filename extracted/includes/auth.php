<?php
/**
 * auth.php — admin session handling and login.
 * Regular site `users` (see users.php in the admin panel) are managed
 * data, not an auth system of their own — there's no public login form
 * on the site yet, so only admins ever authenticate.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 15;
const ADMIN_IDLE_TIMEOUT_SECONDS = 3600; // 1 hour of inactivity -> forced logout

/** Starts the session with hardened cookie params. Call this once, before
 *  any output, at the top of every admin page (require_admin() does it
 *  for you on protected pages). */
function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        // Strict, not Lax: the admin panel is never linked to from
        // outside this site, so there's no legitimate top-level
        // cross-site navigation that needs the cookie attached.
        'samesite' => 'Strict',
    ]);
    session_name('abjadian_admin_sid');
    session_start();
}

function current_admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

/** Call at the very top of every protected admin/*.php page. */
function require_admin(): array
{
    admin_session_start();
    $admin = current_admin();
    if (!$admin) {
        redirect('login.php');
    }
    // 1-hour idle timeout: someone actively clicking around is never
    // affected (this timestamp refreshes on every protected page load)
    // — only a session left untouched for a full hour gets signed out.
    if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > ADMIN_IDLE_TIMEOUT_SECONDS) {
        admin_logout();
        redirect('login.php?timeout=1');
    }
    $_SESSION['admin_last_activity'] = time();
    return $admin;
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Locks by USERNAME ALONE (not username+IP) — 5 failed attempts against
 * one account blocks that account for 15 minutes regardless of which IP
 * they came from, per your request. Trade-off: someone could deliberately
 * trigger a 15-minute lockout on a known username; the reCAPTCHA/honeypot
 * checks in attempt_admin_login()/attempt_public_login() run BEFORE this
 * check is even reached, which is the real defense against that.
 */
function is_rate_limited(string $username): bool
{
    $conn = db();
    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS n FROM login_attempts
         WHERE username = ? AND attempted_at > (NOW() - INTERVAL ? MINUTE)'
    );
    $minutes = LOGIN_LOCKOUT_MINUTES;
    $stmt->bind_param('si', $username, $minutes);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['n'] ?? 0);
    $stmt->close();
    return $count >= MAX_LOGIN_ATTEMPTS;
}

function record_login_attempt(string $username): void
{
    $conn = db();
    $ip = client_ip();
    $stmt = $conn->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)');
    $stmt->bind_param('ss', $username, $ip);
    $stmt->execute();
    $stmt->close();
}

function clear_login_attempts(string $username): void
{
    $conn = db();
    // Clears every IP's record for this username, consistent with the
    // username-only lockout above — otherwise attempts from another IP
    // could keep counting toward the lockout after a successful login.
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $stmt->close();
}

/**
 * Looks up an admin by username AND verifies the password, without
 * touching any session — used both by attempt_admin_login() below and
 * by account.php (which needs to know whether credentials belong to an
 * admin or a regular user *before* deciding which session to start).
 * Returns the admin row (password removed) or null.
 */
function find_admin_by_credentials(string $username, string $password): ?array
{
    $conn = db();
    $stmt = $conn->prepare('SELECT id, name, username, email, password, role, status FROM admins WHERE username = ? OR email = ? LIMIT 1');
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$admin || $admin['status'] !== 'active' || !password_verify($password, $admin['password'])) {
        return null;
    }

    if (password_needs_rehash($admin['password'], PASSWORD_ARGON2ID)) {
        $newHash = password_hash($password, PASSWORD_ARGON2ID);
        $upd = $conn->prepare('UPDATE admins SET password = ? WHERE id = ?');
        $upd->bind_param('si', $newHash, $admin['id']);
        $upd->execute();
        $upd->close();
    }

    unset($admin['password']);
    return $admin;
}

/**
 * Finishes logging an already-verified admin in: must be called with the
 * admin-named session active (admin_session_start()). Regenerates the
 * session ID, stores the admin in $_SESSION, and records last_login.
 */
function establish_admin_session(array $admin): void
{
    session_regenerate_id(true); // new session ID on every login — blocks session fixation
    $_SESSION['admin'] = $admin;

    $conn = db();
    $upd = $conn->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?');
    $upd->bind_param('i', $admin['id']);
    $upd->execute();
    $upd->close();
}

/**
 * Attempts an admin login. Returns ['ok'=>true] on success (session is
 * already set up by the time this returns) or ['ok'=>false,'error'=>...]
 * with a translated-ready error key on failure. Deliberately returns the
 * SAME generic error for "no such user" and "wrong password" so a bad
 * actor can't use the message to enumerate valid usernames.
 */
function attempt_admin_login(string $username, string $password, string $recaptchaToken = ''): array
{
    $username = trim($username);

    if ($username === '' || $password === '') {
        return ['ok' => false, 'error' => 'empty'];
    }

    if (is_rate_limited($username)) {
        return ['ok' => false, 'error' => 'locked'];
    }

    if (!recaptcha_verify($recaptchaToken, client_ip())) {
        return ['ok' => false, 'error' => 'captcha'];
    }

    $admin = find_admin_by_credentials($username, $password);

    if (!$admin) {
        record_login_attempt($username);
        return ['ok' => false, 'error' => 'invalid'];
    }

    clear_login_attempts($username);
    establish_admin_session($admin);

    return ['ok' => true];
}

function admin_logout(): void
{
    admin_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
