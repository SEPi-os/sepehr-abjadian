<?php
/**
 * CLI migration runner:   php database/migrate.php
 * Safe to run repeatedly — only pending files in database/migrations/ run,
 * and none of them drops or truncates anything.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/migrator.php';

$status = migrator_status();
$pending = array_filter($status, fn($m) => !$m['applied']);
if (!$pending) {
    echo "Database is up to date (" . count($status) . " migration(s) already applied).\n";
    exit(0);
}
$result = migrator_run();
foreach ($result['applied'] as $name) {
    echo "applied: $name\n";
}
if ($result['error']) {
    fwrite(STDERR, "FAILED: " . $result['error'] . "\n");
    exit(1);
}
echo "Done.\n";
