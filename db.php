<?php
declare(strict_types=1);

/*
 * ZARO WEAR
 * Local XAMPP Database Connection
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'nexora_fashion';
$dbPort = 3306;

try {
    $conn = new mysqli(
        $dbHost,
        $dbUser,
        $dbPass,
        $dbName,
        $dbPort
    );

    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $e) {
    error_log('ZARO WEAR DB error: ' . $e->getMessage());

    http_response_code(500);
    exit('Database connection failed. Check local MySQL settings.');
}
