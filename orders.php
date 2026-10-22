<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection error. Check includes/db.php.');
}

$conn->set_charset('utf8mb4');

function orderEscape($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$error = '';
$message = '';

/* Update payment/order information */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf()) {
            throw new RuntimeException(
                'Session expired. Refresh the page and try again.'
            );
        }

        $orderNumber = trim($_POST['order_number'] ?? '');
        $orderStatus = trim($_POST['order_status'] ?? '');

        if ($orderNumber === '' || strlen($orderNumber) > 100) {
            throw new RuntimeException('Invalid order number.');
        }

        if ($orderStatus === '' || strlen($orderStatus) > 100) {
            throw new RuntimeException(
                'Enter payment method and order status.'
            );
        }

        $stmt = $conn->prepare(
            'UPDATE orders
             SET `payment method aur order/payment status` = ?
             WHERE `Order number` = ?'
        );

        $stmt->bind_param(
            'ss',
            $orderStatus,
            $orderNumber
        );

        $stmt->execute();

        if ($stmt->affected_rows >= 0) {
            $stmt->close();

            header('Location: orders.php?status=updated');
            exit;
        }

        $stmt->close();

    } catch (Throwable $e) {
        error_log('ZARO WEAR orders admin error: ' . $e->getMessage());

        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Could not update the order. Check the PHP error log.';
    }
}

if (($_GET['status'] ?? '') === 'updated') {
    $message = 'Order information updated successfully.';
}

/* Get all orders */
try {
    $result = $conn->query(
        'SELECT
            `Order number`,
            `customer ID`,
            `total amount`,
            `delivery details`,
            `payment method aur order/payment status`
         FROM orders
         ORDER BY `Order number` DESC'
    );

    $orders = $result->fetch_all(MYSQLI_ASSOC);

    /* Get all order items */
    $itemsResult = $conn->query(
        'SELECT
            `order id`,
            `product id`,
            `product name snapshot`,
            `selected size`,
            `quantity aur purchase-time price`
         FROM order_items'
    );

    $itemsByOrder = [];

    while ($item = $itemsResult->fetch_assoc()) {
        $key = (string)$item['order id'];

        if (!isset($itemsByOrder[$key])) {
            $itemsByOrder[$key] = [];
        }

        $itemsByOrder[$key][] = $item;
    }

} catch (Throwable $e) {
    error_log('ZARO WEAR orders loading error: ' . $e->getMessage());

    $orders = [];
    $itemsByOrder = [];

    $error = 'Could not load orders. Please check the database tables.';
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Manage Orders | ZARO WEAR</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f5f9;
            color: #191b25;
            font-family: Arial, sans-serif;
        }

        header {
            background: #11131c;
            color: white;
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        header h1 {
            margin: 0;
            font-size: 21px;
        }

        header nav {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }

        header a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        main {
            width: 94%;
            max-width: 1250px;
            margin: 30px auto;
        }

        .panel {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 22px;
            box-shadow: 0 5px 22px #1416290b;
        }

        h2 {
            margin-top: 0;
            font-size: 20px;
        }

        .notice {
            background: #e0f7e8;
            color: #176638;
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 18px;
        }

        .error {
            background: #ffe5e5;
            color: #a51e2b;
        }

        .muted {
            color: #777b89;
            font-size: 13px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 13px 10px;
            border-bottom: 1px solid #eceef3;
            vertical-align: top;
            font-size: 13px;
        }

        th {
            background: #f8f8fb;
        }

        .order-card {
            border: 1px solid #e4e6ed;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 18px;
        }

        .order-heading {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .order-heading h3 {
            margin: 0;
            font-size: 17px;
        }

        .details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .detail {
            background: #f8f8fb;
            padding: 12px;
            border-radius: 7px;
            overflow-wrap: anywhere;
        }

        .detail strong {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .items-heading {
            font-size: 15px;
            margin: 20px 0 10px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input {
            width: 100%;
            padding: 11px;
            border: 1px solid #d9dce5;
            border-radius: 7px;
            font: inherit;
            margin-bottom: 10px;
        }

        button, .button {
            display: inline-block;
            border: 0;
            border-radius: 7px;
            padding: 11px 16px;
            background: #7357f5;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            padding: 35px 15px;
            color: #777b89;
        }

        @media (max-width: 650px) {
            header {
                padding: 18px 4%;
            }

            .panel {
                padding: 16px;
            }

            .details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<header>
    <h1>ZARO WEAR — ORDERS</h1>

    <nav>
        <a href="index.php">Dashboard</a>
        <a href="products.php">Products</a>
        <a href="../products.php">Shop</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<main>

    <?php if ($message !== ''): ?>
        <div class="notice">
            <?= orderEscape($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error">
            <?= orderEscape($error) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h2>Customer Orders</h2>

        <p class="muted">
            Total orders: <?= count($orders) ?>
        </p>

        <?php if (count($orders) === 0): ?>

            <div class="empty">
                <h3>No orders found</h3>
                <p>Customer orders will appear here after they are saved in the database.</p>
            </div>

        <?php else: ?>

            <?php foreach ($orders as $order): ?>

                <?php
                $orderNumber = (string)$order['Order number'];
                $orderItems = $itemsByOrder[$orderNumber] ?? [];
                ?>

                <article class="order-card">

                    <div class="order-heading">
                        <h3>
                            Order #<?= orderEscape($orderNumber) ?>
                        </h3>

                        <strong>
                            PKR <?= orderEscape($order['total amount']) ?>
                        </strong>
                    </div>

                    <div class="details">

                        <div class="detail">
                            <strong>Customer ID</strong>
                            <?= orderEscape($order['customer ID']) ?>
                        </div>

                        <div class="detail">
                            <strong>Payment / Order Status</strong>
                            <?= orderEscape(
                                $order['payment method aur order/payment status']
                            ) ?>
                        </div>

                        <div class="detail">
                            <strong>Delivery Details</strong>
                            <?= nl2br(orderEscape($order['delivery details'])) ?>
                        </div>

                        <div class="detail">
                            <strong>Order Items</strong>

                            <?php if (count($orderItems) === 0): ?>
                                No matching order items found.
                            <?php else: ?>
                                <?= count($orderItems) ?> item record(s)
                            <?php endif; ?>
                        </div>

                    </div>

                    <h4 class="items-heading">Products in this order</h4>

                    <?php if (count($orderItems) > 0): ?>

                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Product ID</th>
                                    <th>Product Name</th>
                                    <th>Size</th>
                                    <th>Quantity / Purchase Price</th>
                                </tr>
                                </thead>

                                <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td>
                                            <?= orderEscape($item['product id']) ?>
                                        </td>

                                        <td>
                                            <?= orderEscape($item['product name snapshot']) ?>
                                        </td>

                                        <td>
                                            <?= orderEscape($item['selected size']) ?>
                                        </td>

                                        <td>
                                            <?= orderEscape(
                                                $item['quantity aur purchase-time price']
                                            ) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php else: ?>

                        <p class="muted">
                            No items are linked to this order number.
                        </p>

                    <?php endif; ?>

                    <h4 class="items-heading">
                        Update Payment / Order Status
                    </h4>

                    <form method="POST" action="orders.php">
                        <input type="hidden"
                               name="csrf_token"
                               value="<?= orderEscape($csrf) ?>">

                        <input type="hidden"
                               name="order_number"
                               value="<?= orderEscape($orderNumber) ?>">

                        <label for="status-<?= orderEscape($orderNumber) ?>">
                            Payment method and status
                        </label>

                        <input
                            id="status-<?= orderEscape($orderNumber) ?>"
                            type="text"
                            name="order_status"
                            maxlength="100"
                            required
                            value="<?= orderEscape(
                                $order['payment method aur order/payment status']
                            ) ?>"
                            placeholder="Cash on Delivery | Pending">

                        <button type="submit">
                            Save Changes
                        </button>
                    </form>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

</main>

</body>
</html>