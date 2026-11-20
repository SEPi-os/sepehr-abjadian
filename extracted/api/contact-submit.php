<?php
/**
 * api/contact-submit.php — receives the project-request form from
 * contact.php via fetch(), validates everything server-side (never
 * trust the client-side validation in js/contact-form.js — it's a nice
 * UX, not security), and stores it in project_requests. Returns JSON.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

session_start(); // anonymous visitor session, just for csrf_token() — separate from the admin session

header('Content-Type: application/json; charset=utf-8');

function respond(bool $ok, string $message = '', array $extra = []): never
{
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST; // fall back to normal form-encoded submission too
}

if (!csrf_verify_value($input['csrf_token'] ?? '')) {
    http_response_code(403);
    respond(false, 'invalid_csrf');
}

// ---- basic rate limiting: max 5 submissions per session per hour ------
$_SESSION['submit_log'] = array_filter($_SESSION['submit_log'] ?? [], fn($t) => $t > time() - 3600);
if (count($_SESSION['submit_log']) >= 5) {
    http_response_code(429);
    respond(false, 'rate_limited');
}

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '') ?: null;
$company = trim($input['company'] ?? '') ?: null;
$budget = in_array($input['budget'] ?? '', ['small', 'medium', 'large', 'unknown'], true) ? $input['budget'] : 'unknown';
$type = in_array($input['type'] ?? '', ['web', 'ai', 'desktop', 'data', 'other'], true) ? $input['type'] : 'other';
$message = trim($input['message'] ?? '');
// Honeypot: a hidden field real users never fill in; bots that
// auto-fill every field will trip it. Silently "succeed" so the bot
# doesn't learn anything, without touching the database.
$honeypot = trim($input['website'] ?? '');

if ($honeypot !== '') {
    respond(true, 'ok'); // pretend success, write nothing
}

if ($name === '' || $email === '' || $message === '') {
    respond(false, 'missing_fields');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'invalid_email');
}
if (mb_strlen($name) > 120 || mb_strlen($message) > 5000 || mb_strlen($email) > 190) {
    respond(false, 'too_long');
}

$conn = db();
$stmt = $conn->prepare(
    'INSERT INTO project_requests (name, email, phone, company, project_type, budget, message) VALUES (?,?,?,?,?,?,?)'
);
$stmt->bind_param('sssssss', $name, $email, $phone, $company, $type, $budget, $message);
$stmt->execute();
$newId = $stmt->insert_id;
$stmt->close();

$_SESSION['submit_log'][] = time();

respond(true, 'ok', ['id' => $newId]);
