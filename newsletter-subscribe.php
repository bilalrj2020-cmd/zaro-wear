<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verify_csrf()) {
    $_SESSION['newsletter_message'] = [
        'type' => 'error',
        'text' => 'Session expired. Please refresh the page and try again.'
    ];
    header('Location: index.php#newsletter');
    exit;
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    $_SESSION['newsletter_message'] = [
        'type' => 'error',
        'text' => 'Please enter a valid email address.'
    ];
    header('Location: index.php#newsletter');
    exit;
}

try {
    $stmt = $conn->prepare(
        "INSERT INTO newsletter_subscribers (email)
         VALUES (?)
         ON DUPLICATE KEY UPDATE
         status = 'subscribed'"
    );

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->close();

    $_SESSION['newsletter_message'] = [
        'type' => 'success',
        'text' => 'Thank you! You are subscribed to ZARO WEAR updates.'
    ];
} catch (Throwable $e) {
    error_log(
        'ZARO WEAR newsletter error: ' .
        get_class($e) . ': ' . $e->getMessage()
    );

    $_SESSION['newsletter_message'] = [
        'type' => 'error',
        'text' => 'Subscription is temporarily unavailable. Please try again.'
    ];
}

header('Location: index.php#newsletter');
exit;
