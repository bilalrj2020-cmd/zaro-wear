<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit('Database connection available nahi hai. includes/db.php check karein.');
}

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$errors = [];
$customerName = '';
$customerEmail = '';
$customerPhone = '';
$customerAddress = '';

/**
 * Cart ke products database se read karein.
 * Transaction ke andar rows lock ki ja sakti hain.
 */
function getCheckoutItems(
    mysqli $conn,
    array $cart,
    bool $lockRows = false
): array {
    $items = [];
    $total = 0.0;

    $sql = "SELECT `Id`, `Name`, `price`, `image_url`, `stock`
            FROM `products`
            WHERE `Id` = ?";

    if ($lockRows) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $conn->prepare($sql);

    try {
        foreach ($cart as $cartKey => $cartItem) {
            if (!is_array($cartItem)) {
                throw new RuntimeException(
                    'Cart mein invalid item hai. Cart dobara update karein.'
                );
            }

            $productId = filter_var(
                $cartItem['product_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $quantity = filter_var(
                $cartItem['quantity'] ?? null,
                FILTER_VALIDATE_INT
            );

            $size = trim((string) ($cartItem['size'] ?? ''));

            if (
                $productId === false ||
                $productId === null ||
                $quantity === false ||
                $quantity === null ||
                $productId < 1 ||
                $quantity < 1 ||
                $quantity > 99 ||
                $size === '' ||
                strlen($size) > 100
            ) {
                throw new RuntimeException(
                    'Cart mein invalid product, size ya quantity hai.'
                );
            }

            $stmt->bind_param('i', $productId);
            $stmt->execute();

            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            $result->free();

            if (!$product) {
                throw new RuntimeException(
                    'Cart ka ek product ab available nahi hai.'
                );
            }

            if (
                !is_numeric($product['price']) ||
                !is_finite((float) $product['price']) ||
                (float) $product['price'] < 0
            ) {
                throw new RuntimeException(
                    'Product ki price database mein valid nahi hai.'
                );
            }

            $price = (float) $product['price'];
            $stock = (int) $product['stock'];

            if ($stock < 0 || $quantity > $stock) {
                throw new RuntimeException(
                    $product['Name'] .
                    ' ka available stock sirf ' .
                    max(0, $stock) .
                    ' hai. Cart ki quantity update karein.'
                );
            }

            $lineTotal = round($price * $quantity, 2);
            $total = round($total + $lineTotal, 2);

            $items[] = [
                'cart_key' => (string) $cartKey,
                'product_id' => (int) $product['Id'],
                'name' => (string) $product['Name'],
                'price' => $price,
                'image_url' => (string) ($product['image_url'] ?? ''),
                'stock' => $stock,
                'size' => $size,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
        }
    } finally {
        $stmt->close();
    }

    if (!$items) {
        throw new RuntimeException('Aapka cart empty hai.');
    }

    return [
        'items' => $items,
        'total' => $total,
    ];
}

/**
 * Initial cart validation.
 */
try {
    $cartData = getCheckoutItems($conn, $_SESSION['cart']);
} catch (Throwable $e) {
    error_log('ZARO WEAR checkout validation: ' . $e->getMessage());

    $cartData = [
        'items' => [],
        'total' => 0.0,
    ];

    $errors[] = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Cart check nahi ho saka. Database configuration check karein.';
}

/**
 * Place order.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    try {
        verify_csrf();
    } catch (Throwable $e) {
        $errors[] = 'Security token verify nahi hua. Page refresh karke dobara try karein.';
    }

    $customerName = trim(
        (string) ($_POST['customer_name'] ?? '')
    );

    $customerEmail = trim(
        (string) ($_POST['customer_email'] ?? '')
    );

    $customerPhone = trim(
        (string) ($_POST['customer_phone'] ?? '')
    );

    $customerAddress = trim(
        (string) ($_POST['customer_address'] ?? '')
    );

    if (
        $customerName === '' ||
        strlen($customerName) > 100
    ) {
        $errors[] = 'Apna naam enter karein (maximum 100 characters).';
    }

    if (
        !filter_var($customerEmail, FILTER_VALIDATE_EMAIL) ||
        strlen($customerEmail) > 100
    ) {
        $errors[] = 'Valid email address enter karein.';
    }

    if (
        $customerPhone === '' ||
        strlen($customerPhone) > 30
    ) {
        $errors[] = 'Valid phone number enter karein.';
    }

    if (
        $customerAddress === '' ||
        strlen($customerAddress) > 1000
    ) {
        $errors[] = 'Delivery address enter karein.';
    }

    if (!$errors) {
        $transactionStarted = false;
        $itemStmt = null;
        $stockStmt = null;
        $orderStmt = null;

        try {
            $conn->begin_transaction();
            $transactionStarted = true;

            // Transaction ke andar latest prices aur stock dobara check karein.
            $cartData = getCheckoutItems(
                $conn,
                $_SESSION['cart'],
                true
            );

            $items = $cartData['items'];
            $total = $cartData['total'];

            $orderNumber = 'NX-' .
                date('YmdHis') . '-' .
                strtoupper(bin2hex(random_bytes(4)));

            $orderTotal = number_format(
                $total,
                2,
                '.',
                ''
            );

            $deliveryDetails = json_encode(
                [
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                    'address' => $customerAddress,
                ],
                JSON_UNESCAPED_UNICODE |
                JSON_THROW_ON_ERROR
            );

            $customerId = 'Guest';
            $paymentStatus = 'COD - Pending';

            // Existing orders table ke exact column names.
            $orderStmt = $conn->prepare(
                'INSERT INTO `orders`
                (
                    `Order number`,
                    `customer ID`,
                    `total amount`,
                    `delivery details`,
                    `payment method aur order/payment status`
                )
                VALUES (?, ?, ?, ?, ?)'
            );

            $orderStmt->bind_param(
                'sssss',
                $orderNumber,
                $customerId,
                $orderTotal,
                $deliveryDetails,
                $paymentStatus
            );

            $orderStmt->execute();

            $orderId = (string) $conn->insert_id;

            $orderStmt->close();
            $orderStmt = null;

            // Existing order_items table ke exact column names.
            $itemStmt = $conn->prepare(
                'INSERT INTO `order_items`
                (
                    `order id`,
                    `product id`,
                    `product name snapshot`,
                    `selected size`,
                    `quantity aur purchase-time price`
                )
                VALUES (?, ?, ?, ?, ?)'
            );

            $stockStmt = $conn->prepare(
                'UPDATE `products`
                 SET `stock` = `stock` - ?
                 WHERE `Id` = ?
                 AND `stock` >= ?'
            );

            foreach ($items as $item) {
                $productId = (string) $item['product_id'];

                $productName = substr(
                    $item['name'],
                    0,
                    100
                );

                $size = $item['size'];
                $quantity = (int) $item['quantity'];
                $unitPrice = (float) $item['price'];

                // Existing column VARCHAR hai, is liye purchase
                // quantity aur unit price ek string mein save honge.
                $purchaseInfo = $quantity .
                    ' | Rs. ' .
                    number_format($unitPrice, 2, '.', '') .
                    ' each';

                $itemStmt->bind_param(
                    'sssss',
                    $orderId,
                    $productId,
                    $productName,
                    $size,
                    $purchaseInfo
                );

                $itemStmt->execute();

                // Stock sirf available quantity par reduce hoga.
                $stockStmt->bind_param(
                    'iii',
                    $quantity,
                    $item['product_id'],
                    $quantity
                );

                $stockStmt->execute();

                if ($stockStmt->affected_rows !== 1) {
                    throw new RuntimeException(
                        $productName .
                        ' ka stock change ho gaya hai. Cart dobara check karein.'
                    );
                }
            }

            $itemStmt->close();
            $itemStmt = null;

            $stockStmt->close();
            $stockStmt = null;

            // Order aur stock dono ek transaction mein save honge.
            $conn->commit();
            $transactionStarted = false;

            $_SESSION['last_order'] = [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_phone' => $customerPhone,
                'customer_address' => $customerAddress,
                'total' => $total,
                'items' => $items,
                'placed_at' => date('Y-m-d H:i:s'),
            ];

            unset($_SESSION['cart']);

            header('Location: order-success.php');
            exit;

        } catch (Throwable $e) {
            if ($transactionStarted) {
                try {
                    $conn->rollback();
                } catch (Throwable $rollbackError) {
                    error_log(
                        'ZARO WEAR checkout rollback: ' .
                        $rollbackError->getMessage()
                    );
                }
            }

            error_log(
                'ZARO WEAR checkout error: ' .
                $e->getMessage()
            );

            $errors[] = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Order place nahi ho saka. PHP error log aur database table engines check karein.';

        } finally {
            if ($orderStmt instanceof mysqli_stmt) {
                $orderStmt->close();
            }

            if ($itemStmt instanceof mysqli_stmt) {
                $itemStmt->close();
            }

            if ($stockStmt instanceof mysqli_stmt) {
                $stockStmt->close();
            }
        }
    }
}

function checkoutEscape(mixed $value): string
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | ZARO WEAR</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f5f5f5;
            color: #171717;
            font-family: Arial, sans-serif;
        }

        header {
            background: #111;
            color: #fff;
            padding: 22px 5%;
        }

        header a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .container {
            width: min(1100px, 92%);
            margin: 35px auto;
        }

        h1 { margin-bottom: 8px; }
        .muted { color: #666; }

        .layout {
            display: grid;
            grid-template-columns: 1.3fr 0.7fr;
            gap: 24px;
            align-items: start;
        }

        .panel {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 18px #00000008;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-weight: bold;
        }

        input, textarea {
            display: block;
            width: 100%;
            padding: 13px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font: inherit;
        }

        textarea {
            min-height: 105px;
            resize: vertical;
        }

        button, .button {
            display: inline-block;
            width: 100%;
            border: 0;
            background: #111;
            color: #fff;
            padding: 15px;
            border-radius: 7px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            margin-top: 20px;
        }

        button:hover, .button:hover {
            background: #333;
        }

        .error {
            padding: 12px 15px;
            margin-bottom: 12px;
            background: #fff0f0;
            color: #a00000;
            border-radius: 7px;
            overflow-wrap: anywhere;
        }

        .item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 0;
            border-bottom: 1px solid #eee;
        }

        .item-name { font-weight: bold; }

        .total {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 20px;
            font-size: 20px;
            font-weight: bold;
        }

        @media (max-width: 750px) {
            .layout { grid-template-columns: 1fr; }
            .container { margin: 22px auto; }
            .panel { padding: 18px; }
        }
    </style>
</head>
<body>

<header>
    <a href="index.php">ZARO WEAR</a>
</header>

<main class="container">
    <h1>Checkout</h1>
    <p class="muted">
        Apni delivery details enter karke order place karein.
    </p>

    <?php foreach ($errors as $error): ?>
        <div class="error" role="alert">
            <?= checkoutEscape($error) ?>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($cartData['items'])): ?>
        <div class="layout">
            <section class="panel">
                <h2>Delivery Details</h2>

                <form method="POST" action="checkout.php">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= checkoutEscape(csrf_token()) ?>"
                    >

                    <label for="customer_name">Full Name</label>
                    <input
                        id="customer_name"
                        name="customer_name"
                        type="text"
                        maxlength="100"
                        autocomplete="name"
                        value="<?= checkoutEscape($customerName) ?>"
                        required
                    >

                    <label for="customer_email">Email Address</label>
                    <input
                        id="customer_email"
                        name="customer_email"
                        type="email"
                        maxlength="100"
                        autocomplete="email"
                        value="<?= checkoutEscape($customerEmail) ?>"
                        required
                    >

                    <label for="customer_phone">Phone Number</label>
                    <input
                        id="customer_phone"
                        name="customer_phone"
                        type="tel"
                        maxlength="30"
                        autocomplete="tel"
                        value="<?= checkoutEscape($customerPhone) ?>"
                        required
                    >

                    <label for="customer_address">
                        Full Delivery Address
                    </label>
                    <textarea
                        id="customer_address"
                        name="customer_address"
                        maxlength="1000"
                        autocomplete="street-address"
                        required
                    ><?= checkoutEscape($customerAddress) ?></textarea>

                    <p class="muted">
                        Payment method: Cash on Delivery (COD).
                    </p>

                    <button type="submit">Place Order</button>
                </form>
            </section>

            <aside class="panel">
                <h2>Your Order</h2>

                <?php foreach ($cartData['items'] as $item): ?>
                    <div class="item">
                        <div>
                            <div class="item-name">
                                <?= checkoutEscape($item['name']) ?>
                            </div>

                            <div class="muted">
                                Size: <?= checkoutEscape($item['size']) ?>
                                · Qty: <?= (int) $item['quantity'] ?>
                            </div>

                            <div class="muted">
                                <?= checkoutEscape(money($item['price'])) ?>
                                each
                            </div>
                        </div>

                        <div>
                            <?= checkoutEscape(money($item['line_total'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="total">
                    <span>Total</span>
                    <span>
                        <?= checkoutEscape(money($cartData['total'])) ?>
                    </span>
                </div>

                <a class="button" href="cart.php">Back to Cart</a>
            </aside>
        </div>
    <?php else: ?>
        <div class="panel">
            <p>
                Checkout ke liye cart mein available products hona zaroori hai.
            </p>
            <a class="button" href="products.php">Continue Shopping</a>
        </div>
    <?php endif; ?>
</main>

</body>
</html>
