<?php
/**
 * db.php — one shared mysqli connection for the whole app.
 * Every other file gets the connection by calling db(), never by
 * opening its own — one place to change if the connection ever needs
 * to change.
 */
require_once __DIR__ . '/config.php';

function db(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_OFF); // we check errors ourselves below
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_errno) {
        error_log('DB connection failed: ' . $conn->connect_error);
        http_response_code(500);
        if (APP_DEBUG) {
            die('Database connection failed: ' . htmlspecialchars($conn->connect_error));
        }
        die('Something went wrong. Please try again later.');
    }

    $conn->set_charset(DB_CHARSET);
    return $conn;
}
