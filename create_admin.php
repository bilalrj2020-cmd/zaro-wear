<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

$message = '';

$adminName = 'IBRAR AHMED';
$adminEmail = 'ijazahmed03214@gmail.com';

// Is password ko apne strong, unique password se replace karein.
$adminPassword = 'ReplaceWithYourOwnStrongPassword!';

try {
    if ($adminPassword === 'Bilal@129!') {
        throw new RuntimeException(
            'Pehle file mein apna strong admin password set karein.'
        );
    }

    $check = $conn->prepare(
        'SELECT `Id` FROM `users` WHERE `email` = ? LIMIT 1'
    );
    $check->bind_param('s', $adminEmail);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        throw new RuntimeException(
            'Ye email pehle se registered hai. Naya admin email use karein.'
        );
    }

    $check->close();

    $passwordHash = password_hash(
        $adminPassword,
        PASSWORD_DEFAULT
    );

    $registrationLabel = 'Admin account';

    $stmt = $conn->prepare(
        'INSERT INTO `users`
        (`Customer registration`, `login`, `name`, `email`, `password`, `role`)
        VALUES (?, ?, ?, ?, ?, ?)'
    );

    $role = 'admin';

    $stmt->bind_param(
        'ssssss',
        $registrationLabel,
        $adminEmail,
        $adminName,
        $adminEmail,
        $passwordHash,
        $role
    );

    $stmt->execute();
    $stmt->close();

    $message = 'Admin account created successfully.';

} catch (Throwable $e) {
    error_log('ZARO WEAR admin setup: ' . $e->getMessage());
    $message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Setup</title>
</head>
<body>
    <h2><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></h2>
</body>
</html>
