<?php
/**
 * user-auth.php — registration and login for regular site visitors
 * (the `users` table). Uses PHP's default (unnamed) session, which is
 * also the session contact.php uses for its CSRF token — they coexist
 * fine as different keys in the same $_SESSION array.
 *
 * The public login form (account.php) checks ONE set of credentials
 * against BOTH the admins and users tables: a match in admins switches
 * to the admin-named session (see login_as_admin_and_redirect() at the
 * bottom) and sends them to the admin panel; a match in users logs them
 * in as a normal visitor and keeps them on the public site.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php'; // reuses is_rate_limited(), record_login_attempt(), find_admin_by_credentials(), establish_admin_session()

const USER_IDLE_TIMEOUT_SECONDS = 3600; // 1 hour of inactivity -> forced logout, same as admin

function public_session_start(): void
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
        'samesite' => 'Lax',
    ]);
    session_start(); // default session name — shared with contact.php's CSRF token
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function find_user_by_credentials(string $identifier, string $password): ?array
{
    $conn = db();
    $stmt = $conn->prepare('SELECT id, name, username, email, phone, password, status FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->bind_param('ss', $identifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password'])) {
        return null;
    }

    if (password_needs_rehash($user['password'], PASSWORD_ARGON2ID)) {
        $newHash = password_hash($password, PASSWORD_ARGON2ID);
        $upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $upd->bind_param('si', $newHash, $user['id']);
        $upd->execute();
        $upd->close();
    }

    unset($user['password']);
    return $user;
}

function establish_user_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = $user;

    $conn = db();
    $upd = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
    $upd->bind_param('i', $user['id']);
    $upd->execute();
    $upd->close();
}

/**
 * The single login entry point for account.php. Checks the admins table
 * first, then users. Returns one of:
 *   ['ok'=>true, 'type'=>'admin']  — caller should redirect to admin/index.php
 *   ['ok'=>true, 'type'=>'user']   — caller should redirect to index.html
 *   ['ok'=>false, 'error'=>'empty'|'locked'|'invalid']
 */
function attempt_public_login(string $identifier, string $password, string $recaptchaToken = ''): array
{
    $identifier = trim($identifier);

    if ($identifier === '' || $password === '') {
        return ['ok' => false, 'error' => 'empty'];
    }
    if (is_rate_limited($identifier)) {
        return ['ok' => false, 'error' => 'locked'];
    }
    if (!recaptcha_verify($recaptchaToken, client_ip())) {
        return ['ok' => false, 'error' => 'captcha'];
    }

    $admin = find_admin_by_credentials($identifier, $password);
    if ($admin) {
        clear_login_attempts($identifier);
        // Switch from the public (default) session to the admin-named
        // session in the same request — see includes/auth.php for the
        // cookie/session-name setup this relies on.
        session_write_close();
        admin_session_start();
        establish_admin_session($admin);
        return ['ok' => true, 'type' => 'admin'];
    }

    $user = find_user_by_credentials($identifier, $password);
    if ($user) {
        clear_login_attempts($identifier);
        establish_user_session($user);
        return ['ok' => true, 'type' => 'user'];
    }

    record_login_attempt($identifier);
    return ['ok' => false, 'error' => 'invalid'];
}

/**
 * Registers a new regular user and logs them in immediately. Returns
 * ['ok'=>true] or ['ok'=>false,'error'=>'...','field'=>'...'] — 'field'
 * (when present) names which input the error applies to, for inline
 * display next to that field instead of a generic banner.
 */
function register_user(string $name, string $username, string $email, string $password, string $phone, string $recaptchaToken = ''): array
{
    $name = trim($name);
    $username = trim($username);
    $email = trim($email);
    $phone = trim($phone);

    if ($name === '' || $username === '' || $email === '' || $password === '') {
        return ['ok' => false, 'error' => 'missing_fields'];
    }
    if (!recaptcha_verify($recaptchaToken, client_ip())) {
        return ['ok' => false, 'error' => 'captcha'];
    }
    if (!preg_match('/^[a-zA-Z0-9_.]{3,30}$/', $username)) {
        return ['ok' => false, 'error' => 'invalid_username', 'field' => 'username'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email', 'field' => 'email'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'weak_password', 'field' => 'password'];
    }

    $conn = db();
    $dup = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $dup->bind_param('ss', $username, $email);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        $dup->close();
        return ['ok' => false, 'error' => 'duplicate'];
    }
    $dup->close();

    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $stmt = $conn->prepare('INSERT INTO users (name, username, email, phone, password) VALUES (?,?,?,?,?)');
    $phoneOrNull = $phone !== '' ? $phone : null;
    $stmt->bind_param('sssss', $name, $username, $email, $phoneOrNull, $hash);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    $user = ['id' => $newId, 'name' => $name, 'username' => $username, 'email' => $email, 'phone' => $phoneOrNull, 'status' => 'active'];
    establish_user_session($user);

    return ['ok' => true];
}

function user_logout(): void
{
    public_session_start();
    unset($_SESSION['user']);
}

/** Call at the very top of every page that only signed-in visitors may see
 *  (currently just dashboard.php). Sends anonymous visitors to the
 *  login/register page instead of showing an empty/broken dashboard. */
function require_public_login(): array
{
    public_session_start();
    $user = current_user();
    if (!$user) {
        redirect('account.php');
    }
    // Same 1-hour idle timeout as the admin panel — only affects a
    // session that's genuinely been untouched for an hour.
    if (isset($_SESSION['user_last_activity']) && (time() - $_SESSION['user_last_activity']) > USER_IDLE_TIMEOUT_SECONDS) {
        user_logout();
        redirect('account.php?timeout=1');
    }
    $_SESSION['user_last_activity'] = time();
    return $user;
}

/**
 * Updates the signed-in user's own profile (name/email/phone; username is
 * intentionally left unchangeable here — it's used as a login identifier
 * and changing it has no UI need yet). Refreshes $_SESSION['user'] on
 * success so the header/dashboard reflect the new values immediately,
 * without waiting for the next login.
 * Returns ['ok'=>true] or ['ok'=>false,'error'=>...,'field'=>...].
 */
function update_user_profile(int $userId, string $name, string $email, string $phone): array
{
    $name = trim($name);
    $email = trim($email);
    $phone = trim($phone);

    if ($name === '' || $email === '') {
        return ['ok' => false, 'error' => 'missing_fields'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'invalid_email', 'field' => 'email'];
    }

    $conn = db();
    $dup = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $dup->bind_param('si', $email, $userId);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        $dup->close();
        return ['ok' => false, 'error' => 'email_taken', 'field' => 'email'];
    }
    $dup->close();

    $phoneOrNull = $phone !== '' ? $phone : null;
    $stmt = $conn->prepare('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?');
    $stmt->bind_param('sssi', $name, $email, $phoneOrNull, $userId);
    $stmt->execute();
    $stmt->close();

    $user = current_user();
    if ($user && (int) $user['id'] === $userId) {
        $user['name'] = $name;
        $user['email'] = $email;
        $user['phone'] = $phoneOrNull;
        $_SESSION['user'] = $user;
    }

    return ['ok' => true];
}

/**
 * Changes the signed-in user's password after verifying the current one.
 * Returns ['ok'=>true] or ['ok'=>false,'error'=>'wrong_current'|'weak_password'|'password_mismatch'].
 */
function change_user_password(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array
{
    if ($newPassword !== $confirmPassword) {
        return ['ok' => false, 'error' => 'password_mismatch'];
    }
    if (strlen($newPassword) < 8) {
        return ['ok' => false, 'error' => 'weak_password'];
    }

    $conn = db();
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($currentPassword, $row['password'])) {
        return ['ok' => false, 'error' => 'wrong_current'];
    }

    $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
    $upd = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $upd->bind_param('si', $hash, $userId);
    $upd->execute();
    $upd->close();

    return ['ok' => true];
}
