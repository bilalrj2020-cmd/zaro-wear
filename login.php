<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    if (($_SESSION['user_role'] ?? 'customer') === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

$error = '';
$email = trim((string) ($_GET['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Session expired. Please refresh the page.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password === '' || strlen($password) > 4096) {
            $error = 'Please enter your password.';
        } else {
            try {
                $stmt = $conn->prepare(
                    'SELECT `Id`, `name`, `email`, `password`, `role`
                     FROM `users`
                     WHERE `email` = ?
                     LIMIT 1'
                );

                $stmt->bind_param('s', $email);
                $stmt->execute();

                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                $stmt->close();

                if (
                    $user &&
                    password_verify($password, (string) $user['password'])
                ) {
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int) $user['Id'];
                    $_SESSION['user_name'] = (string) $user['name'];
                    $_SESSION['user_email'] = (string) $user['email'];
                    $_SESSION['user_role'] = (string) ($user['role'] ?? 'customer');

                    if ($_SESSION['user_role'] === 'admin') {
                        header('Location: admin/index.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit;
                }

                $error = 'Email or password is incorrect.';
            } catch (Throwable $e) {
                error_log('ZARO WEAR login error: ' . $e->getMessage());
                http_response_code(500);
                $error = 'Login is temporarily unavailable. Please try again.';
            }
        }
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ZARO WEAR</title>
    <link rel="stylesheet" href="style.css">

    <style>
        .auth-container {
            max-width: 460px;
            margin: 45px auto;
            padding: 30px;
            background: #fff;
            color: #222;
            border: 1px solid #eee;
        }

        .auth-container h1 {
            margin-bottom: 10px;
        }

        .auth-container > p {
            margin-bottom: 22px;
        }

        .auth-form label {
            display: block;
            margin: 16px 0 7px;
        }

        .auth-form input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            font: inherit;
        }

        .auth-form button {
            width: 100%;
            margin-top: 22px;
        }

        .auth-error {
            padding: 12px;
            margin-bottom: 16px;
            background: #fff0f0;
            color: #a00000;
        }

        .auth-links {
            margin-top: 20px;
            line-height: 1.9;
        }
    </style>
</head>
<body>

<div class="announcement">ZARO WEAR — FIND YOUR STYLE</div>

<header class="header">
    <a class="logo" href="index.php">ZARO WEAR</a>

    <nav class="nav">
        <a href="index.php">Home</a>
        <a href="products.php?category=Women">Women</a>
        <a href="products.php?category=Men">Men</a>
        <a href="products.php?category=Accessories">Accessories</a>
    </nav>

    <div class="header-actions">
        <a class="icon-btn" href="cart.php">Bag</a>
        <a class="text-link" href="register.php">Register</a>
    </div>
</header>

<main class="section">
    <div class="auth-container">
        <h1>WELCOME BACK</h1>
        <p>Login to your ZARO WEAR account.</p>

        <?php if ($error !== ''): ?>
            <div class="auth-error" role="alert">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="login.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($csrf) ?>"
            >

            <label for="email">Email address</label>
            <input
                id="email"
                type="email"
                name="email"
                value="<?= h($email) ?>"
                autocomplete="email"
                maxlength="254"
                required
            >

            <label for="password">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                autocomplete="current-password"
                maxlength="4096"
                required
            >

            <button class="btn btn-dark" type="submit">
                LOGIN
            </button>
        </form>

        <div class="auth-links">
            Don't have an account?
            <a href="register.php">Create an account</a>
            <br>
            <a href="products.php">Continue shopping</a>
        </div>
    </div>
</main>

<footer class="footer">
    <div class="footer-bottom">
        <span>&copy; <?= date('Y') ?> ZARO WEAR</span>
        <a href="index.php">HOME ↑</a>
    </div>
</footer>

</body>
</html>
