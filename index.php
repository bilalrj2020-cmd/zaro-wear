<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (($_SESSION['user_role'] ?? 'customer') !== 'admin') {
    http_response_code(403);
    exit('Access denied. Please login with an admin account.');
}

require_once __DIR__ . '/../includes/db.php';

$productCount = 0;
$orderCount = 0;
$customerCount = 0;
$statsError = '';

try {
    $result = $conn->query('SELECT COUNT(*) AS total FROM products');
    $productCount = (int) $result->fetch_assoc()['total'];

    $result = $conn->query('SELECT COUNT(*) AS total FROM orders');
    $orderCount = (int) $result->fetch_assoc()['total'];

    $result = $conn->query(
        "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'"
    );
    $customerCount = (int) $result->fetch_assoc()['total'];
} catch (Throwable $e) {
    error_log('ZARO WEAR admin dashboard: ' . $e->getMessage());
    $statsError = 'Some statistics are unavailable. Check your database tables.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | ZARO WEAR</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f3f5f9;
            color: #172033;
            font-family: Arial, sans-serif;
        }

        header {
            background: #111827;
            color: white;
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        header a { color: white; text-decoration: none; }

        .brand { font-size: 22px; font-weight: bold; }
        .brand span { color: #9278ff; }

        main {
            max-width: 1100px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .welcome { margin-bottom: 25px; }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin: 25px 0;
        }

        .card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 18px #1018280c;
        }

        .number {
            display: block;
            font-size: 32px;
            font-weight: bold;
            margin-top: 12px;
        }

        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action {
            display: block;
            padding: 20px;
            background: white;
            border: 1px solid #e0e5ee;
            border-radius: 10px;
            text-decoration: none;
            color: #172033;
            font-weight: bold;
        }

        .action:hover { border-color: #795cff; }

        .muted { color: #657087; font-size: 14px; }
        .notice {
            padding: 12px;
            background: #fff3cd;
            border-radius: 8px;
        }

        @media (max-width: 500px) {
            header { padding: 18px; }
            main { margin-top: 22px; }
        }
    </style>
</head>
<body>
<header>
    <a class="brand" href="index.php">ZARO<span>.</span> ADMIN</a>
    <a href="../index.php">View Store ↗</a>
</header>

<main>
    <section class="welcome">
        <h1>Admin Dashboard</h1>
        <p>
            Welcome,
            <?= htmlspecialchars(
                (string) ($_SESSION['user_name'] ?? 'Administrator'),
                ENT_QUOTES,
                'UTF-8'
            ) ?>.
        </p>
        <p class="muted">Manage your ZARO WEAR store from here.</p>
    </section>

    <?php if ($statsError !== ''): ?>
        <p class="notice">
            <?= htmlspecialchars($statsError, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <section class="stats">
        <div class="card">
            <span class="muted">Total Products</span>
            <span class="number"><?= $productCount ?></span>
        </div>

        <div class="card">
            <span class="muted">Total Orders</span>
            <span class="number"><?= $orderCount ?></span>
        </div>

        <div class="card">
            <span class="muted">Customers</span>
            <span class="number"><?= $customerCount ?></span>
        </div>
    </section>

    <h2>Store Management</h2>

    <section class="actions">
        <a class="action" href="products.php">
            Manage Products →
            <p class="muted">Add, edit and remove products</p>
        </a>

        <a class="action" href="orders.php">
            Manage Orders →
            <p class="muted">View customer orders</p>
        </a>

        <a class="action" href="../products.php">
            View Store →
            <p class="muted">Open the customer storefront</p>
        </a>

        <a class="action" href="../logout.php">
            Logout →
            <p class="muted">Sign out of your account</p>
        </a>
    </section>
</main>

</body>
</html>
