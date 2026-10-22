<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$error = '';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verify_csrf()) {
        $error = 'Session expired. Please refresh the page.';
    } elseif ($name === '' || strlen($name) > 100) {
        $error = 'Please enter a name within 100 characters.';
    } elseif (
        $email === '' ||
        strlen($email) > 100 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = 'Please enter a valid email address.';
    } elseif (!is_string($password) || strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $check = $conn->prepare(
                'SELECT `Id` FROM `users` WHERE `email` = ? LIMIT 1'
            );

            $check->bind_param('s', $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = 'This email is already registered.';
            }

            $check->close();

            if ($error === '') {
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    'INSERT INTO `users` (`name`, `email`, `password`)
                     VALUES (?, ?, ?)'
                );

                $stmt->bind_param(
                    'sss',
                    $name,
                    $email,
                    $passwordHash
                );

                $stmt->execute();
                $stmt->close();

                header('Location: login.php?registered=1');
                exit;
            }
        } catch (Throwable $e) {
            error_log(
                'ZARO WEAR registration error: ' . $e->getMessage()
            );

            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | ZARO WEAR</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #090d18;
            color: #f5f7ff;
            font-family: Arial, sans-serif;
        }

        main {
            width: 100%;
            max-width: 420px;
            padding: 28px;
            border: 1px solid #29334b;
            border-radius: 14px;
            background: #141b2c;
        }

        h1, .intro { text-align: center; }
        .intro { color: #aab4cc; }
        label { display: block; margin: 16px 0 7px; }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #39445e;
            border-radius: 7px;
            background: #0b1120;
            color: white;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 13px;
            border: 0;
            border-radius: 7px;
            background: #795cff;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        .error {
            padding: 12px;
            border-radius: 7px;
            background: #48202b;
            color: #ffd9df;
        }

        a { color: #b5a5ff; }
        .footer { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
<main>
    <h1>ZARO WEAR</h1>
    <p class="intro">Create your account</p>

    <?php if ($error !== ''): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <form method="post" action="register.php">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= h(csrf_token()) ?>"
        >

        <label for="name">Full name</label>
        <input
            id="name"
            name="name"
            maxlength="100"
            value="<?= h($name) ?>"
            autocomplete="name"
            required
        >

        <label for="email">Email</label>
        <input
            id="email"
            name="email"
            type="email"
            maxlength="100"
            value="<?= h($email) ?>"
            autocomplete="email"
            required
        >

        <label for="password">Password</label>
        <input
            id="password"
            name="password"
            type="password"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <label for="confirm_password">Confirm password</label>
        <input
            id="confirm_password"
            name="confirm_password"
            type="password"
            minlength="8"
            autocomplete="new-password"
            required
        >

        <button type="submit">Create Account</button>
    </form>

    <p class="footer">
        Already registered? <a href="login.php">Login</a>
    </p>
</main>
</body>
</html>
