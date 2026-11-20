<?php
/**
 * migrator.php — tiny versioned, NON-destructive migration runner.
 * Files in database/migrations/NNN_name.sql run in order, each exactly once
 * (recorded in schema_migrations). Used by database/migrate.php (CLI) and
 * the admin page "به‌روزرسانی دیتابیس".
 */
require_once __DIR__ . '/db.php';

function migrator_status(): array
{
    $conn = db();
    $conn->query("CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `filename` VARCHAR(190) NOT NULL,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $applied = [];
    $res = $conn->query('SELECT filename FROM schema_migrations');
    while ($r = $res->fetch_row()) {
        $applied[$r[0]] = true;
    }
    $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
    sort($files, SORT_STRING);
    $out = [];
    foreach ($files as $f) {
        $name = basename($f);
        $out[] = ['file' => $f, 'name' => $name, 'applied' => isset($applied[$name])];
    }
    return $out;
}

/** Runs every pending migration. Returns ['applied'=>[names], 'error'=>?string]. */
function migrator_run(): array
{
    $conn = db();
    $applied = [];
    foreach (migrator_status() as $m) {
        if ($m['applied']) {
            continue;
        }
        $sql = file_get_contents($m['file']);
        if ($sql === false || trim($sql) === '') {
            return ['applied' => $applied, 'error' => 'Cannot read ' . $m['name']];
        }
        $conn->set_charset('utf8mb4');
        if (!$conn->multi_query($sql)) {
            return ['applied' => $applied, 'error' => $m['name'] . ': ' . $conn->error];
        }
        do {
            if ($r = $conn->store_result()) {
                $r->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        if ($conn->errno) {
            return ['applied' => $applied, 'error' => $m['name'] . ': ' . $conn->error];
        }
        $stmt = $conn->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
        $stmt->bind_param('s', $m['name']);
        $stmt->execute();
        $stmt->close();
        $applied[] = $m['name'];
    }
    return ['applied' => $applied, 'error' => null];
}
