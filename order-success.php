<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$order = $_SESSION['last_order'] ?? null;

function escapeOutput(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation | ZARO WEAR</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #f5f5f5;
            color: #171717;
            font-family: Arial, sans-serif;
        }
        .card {
            max-width: 650px;
            margin: 50px auto;
            padding: 32px;
            background: white;
            border-radius: 14px;
            text-align: center;
            box-shadow: 0 5px 25px #0000000d;
        }
        .success {
            color: #16803c;
            font-size: 48px;
        }
        .details {
            margin-top: 24px;
            padding: 18px;
            background: #f7f7f7;
            border-radius: 8px;
            text-align: left;
            overflow-wrap: anywhere;
        }
        .button {
            display: inline-block;
            margin-top: 22px;
            padding: 14px 22px;
            background: #111;
            color: white;
            text-decoration: none;
            border-radius: 7px;
        }
        .muted { color: #666; }
    </style>
</head>
<body>
    <main class="card">
        <?php if (is_array($order)): ?>
            <div class="success">&#10003;</div>
            <h1>Order Placed Successfully!</h1>
            <p>Thank you for shopping with ZARO WEAR.</p>

            <div class="details">
                <p>
                    <strong>Order Number:</strong>
                    <?= escapeOutput($order['order_id'] ?? 'Pending') ?>
                </p>
                <p>
                    <strong>Customer:</strong>
                    <?= escapeOutput($order['customer_name'] ?? '') ?>
                </p>
                <p>
                    <strong>Total:</strong>
                    Rs. <?= number_format((float) ($order['total'] ?? 0), 2) ?>
                </p>
                <p><strong>Payment:</strong> Cash on Delivery</p>
                <p class="muted">Please keep your order number for reference.</p>
            </div>

            <a class="button" href="products.php">Continue Shopping</a>
        <?php else: ?>
            <h1>Order Confirmation</h1>
            <p>Your recent order details are not available in this session.</p>
            <p class="muted">
                Please check your order in the database before placing another order.
            </p>
            <a class="button" href="products.php">Continue Shopping</a>
        <?php endif; ?>
    </main>
</body>
</html>
