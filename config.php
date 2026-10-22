<?php
declare(strict_types=1);

$host = 'localhost';
$dbname = 'YOUR_DATABASE_NAME';
$username = 'YOUR_DATABASE_USER';
$password = 'YOUR_DATABASE_PASSWORD';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('Database connection failed.');
}
