<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function cartApiResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cartApiResponse(405, [
        'success' => false,
        'message' => 'POST request required.'
    ]);
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    verify_csrf();

    $items = json_decode(
        (string) ($_POST['items'] ?? ''),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($items)) {
        throw new RuntimeException('Cart data valid nahi hai.');
    }

    if (count($items) > 100) {
        throw new RuntimeException('Cart mein bohat zyada items hain.');
    }

    $newCart = [];

    $stmt = $conn->prepare(
        'SELECT `Id`, `Name`, `category`, `price`, `stock`
         FROM `products`
         WHERE `Id` = ?'
    );

    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new RuntimeException('Invalid cart item.');
        }

        $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
        $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
        $size = trim((string) ($item['size'] ?? ''));

        if (
            $id === false || $id === null || $id < 1 ||
            $quantity === false || $quantity === null ||
            $quantity < 1 || $quantity > 99 ||
            $size === '' || strlen($size) > 30
        ) {
            throw new RuntimeException('Product, size ya quantity valid nahi hai.');
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product) {
            throw new RuntimeException('Cart ka ek product database mein nahi mila.');
        }

        if (!is_numeric($product['price']) || (float) $product['price'] < 0) {
            throw new RuntimeException('Product price database mein valid nahi hai.');
        }

        if ($quantity > (int) $product['stock']) {
            throw new RuntimeException(
                $product['Name'] . ' ka available stock sirf ' .
                (int) $product['stock'] . ' hai.'
            );
        }

        $key = $id . '_' . $size;

        if (isset($newCart[$key])) {
            $combined = $newCart[$key]['quantity'] + $quantity;

            if ($combined > 99 || $combined > (int) $product['stock']) {
                throw new RuntimeException('Requested quantity available stock se zyada hai.');
            }

            $newCart[$key]['quantity'] = $combined;
        } else {
            $newCart[$key] = [
                'product_id' => (int) $id,
                'size' => $size,
                'quantity' => (int) $quantity
            ];
        }
    }

    $stmt->close();

    $_SESSION['cart'] = $newCart;

    cartApiResponse(200, [
        'success' => true,
        'message' => 'Cart synchronized.',
        'count' => array_sum(array_column($newCart, 'quantity'))
    ]);
    
} catch (Throwable $e) {
    error_log('ZARO WEAR cart API error: ' . $e->getMessage());

    $message = $e instanceof JsonException
        ? 'Cart data ka JSON format invalid hai.'
        : ($e instanceof RuntimeException
            ? $e->getMessage()
            : 'Cart sync nahi ho saka. PHP error log check karein.');

    cartApiResponse(400, [
        'success' => false,
        'message' => $message
    ]);
}

