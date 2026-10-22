<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

/*
|--------------------------------------------------------------------------
| ZARO WEAR - Product Details
|--------------------------------------------------------------------------
| Uses the existing products table without changing the database.
| Actual columns: Id, Name, price, category, description, stock, image_url
*/

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id < 1) {
    http_response_code(404);
    exit('Invalid product ID. <a href="products.php">Browse products</a>');
}

try {
    $stmt = $conn->prepare(
        'SELECT `Id`, `Name`, `price`, `image_url`,
                `description`, `category`, `stock`
         FROM `products`
         WHERE `Id` = ?
         LIMIT 1'
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('ZARO WEAR product query failed: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to load this product right now.');
}

if (!$product) {
    http_response_code(404);
    exit('Product not found. <a href="products.php">Browse products</a>');
}

/*
|--------------------------------------------------------------------------
| Product Information
|--------------------------------------------------------------------------
*/

$productId = (int) $product['Id'];
$productName = (string) ($product['Name'] ?? 'Product');
$productCategory = trim((string) ($product['category'] ?? ''));
$productDescription = trim((string) ($product['description'] ?? ''));
$productStock = max(0, (int) ($product['stock'] ?? 0));

$price = filter_var($product['price'], FILTER_VALIDATE_FLOAT);

if (
    $price === false ||
    !is_finite((float) $price) ||
    (float) $price < 0
) {
    error_log('ZARO WEAR: Invalid price for product ID ' . $productId);
    http_response_code(500);
    exit('This product has an invalid price. Please contact the store administrator.');
}

$productPrice = (float) $price;
$outOfStock = $productStock < 1;

if ($productDescription === '') {
    $productDescription =
        'Discover this piece from the ZARO WEAR collection.';
}

/*
|--------------------------------------------------------------------------
| Product Image
|--------------------------------------------------------------------------
| Accept HTTP(S) image URLs or approved local image paths.
*/

$image = trim((string) ($product['image_url'] ?? ''));

if (
    preg_match('#^https?://#i', $image) &&
    filter_var($image, FILTER_VALIDATE_URL)
) {
    $productImage = $image;
} elseif (
    (
        str_starts_with($image, 'assets/images/') ||
        str_starts_with($image, 'uploads/')
    ) &&
    !str_contains($image, '..') &&
    !str_contains($image, '\\')
) {
    $productImage = $image;
} else {
    $productImage = 'assets/images/placeholder.jpg';
}

/*
|--------------------------------------------------------------------------
| Available Sizes
|--------------------------------------------------------------------------
*/

$isAccessory = strtolower($productCategory) === 'accessories';

$sizes = $isAccessory
    ? ['One Size']
    : ['S', 'M', 'L', 'XL'];

$cartError = '';

/*
|--------------------------------------------------------------------------
| Optional Add-to-Bag Feedback
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['cart_message'])) {
    $cartError = (string) $_SESSION['cart_message'];
    unset($_SESSION['cart_message']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="<?= h($productName) ?> at ZARO WEAR."
    >

    <title><?= h($productName) ?> | ZARO WEAR</title>

    <link rel="stylesheet" href="style.css">

    <style>
        .detail-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.9fr);
            gap: 48px;
            align-items: start;
            margin-top: 30px;
        }

        .detail-image {
            display: block;
            width: 100%;
            max-height: 700px;
            min-height: 320px;
            object-fit: cover;
            background: #f4f4f4;
        }

        .detail-copy h1 {
            margin: 12px 0;
        }

        .detail-copy > p {
            margin: 18px 0;
            line-height: 1.8;
        }

        .detail-copy form {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
            margin: 24px 0;
        }

        .detail-copy form label {
            font-weight: 600;
        }

        .detail-copy form select,
        .detail-copy form input[type="number"] {
            width: 100%;
            max-width: 320px;
            min-height: 44px;
            padding: 10px;
            border: 1px solid #ccc;
            background: #fff;
            color: #111;
            border-radius: 3px;
        }

        .detail-copy form .btn {
            margin-top: 10px;
            cursor: pointer;
        }

        .stock-status {
            margin-top: 12px;
            font-size: 14px;
        }

        .stock-available {
            color: #18723c;
        }

        .stock-unavailable {
            color: #b42318;
        }

        .product-notice {
            padding: 12px;
            margin: 15px 0;
            background: #fff0f0;
            color: #a00000;
            border-radius: 4px;
        }

        .product-price {
            font-size: 22px;
            font-weight: 700;
            margin: 15px 0;
        }

        @media (max-width: 768px) {
            .detail-layout {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .detail-image {
                max-height: 500px;
            }

            .detail-copy form,
            .detail-copy form select,
            .detail-copy form input[type="number"],
            .detail-copy form .btn {
                width: 100%;
                max-width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="announcement">
    ZARO WEAR — FIND YOUR STYLE
</div>  

<header class="header">
    <a class="logo" href="index.php">
        ZARO WEAR
    </a>

    <nav class="nav">
        <a href="index.php">Home</a>
        <a href="products.php?category=Women">Women</a>
        <a href="products.php?category=Men">Men</a>
        <a href="products.php?category=Accessories">Accessories</a>
    </nav>

    <div class="header-actions">
        <a
            class="icon-btn"
            href="products.php"
            aria-label="Browse products"
        >⌕</a>

        <a
            class="icon-btn"
            href="cart.php"
            aria-label="Shopping cart"
        >♧</a>

        <a class="text-link" href="login.php">Login</a>
    </div>
</header>

<main class="section">

    <p class="section-description">
        <a href="index.php">Home</a> /
        <a href="products.php">Shop</a> /
        <?= h($productName) ?>
    </p>

    <?php if ($cartError !== ''): ?>
        <div class="product-notice">
            <?= h($cartError) ?>
        </div>
    <?php endif; ?>

    <div class="detail-layout">

        <div>
            <img
                class="detail-image"
                src="<?= h($productImage) ?>"
                alt="<?= h($productName) ?>"
                loading="eager"
                onerror="this.onerror=null;this.src='assets/images/placeholder.jpg';"
            >
        </div>

        <div class="detail-copy">

            <span class="eyebrow">
                <?= h($productCategory) ?>
            </span>

            <h1><?= h($productName) ?></h1>

            <div class="product-price">
                <?= h(money($productPrice)) ?>
            </div>

            <p><?= nl2br(h($productDescription)) ?></p>

            <?php if ($outOfStock): ?>

                <p class="stock-status stock-unavailable">
                    Currently out of stock.
                </p>

            <?php else: ?>

                <p class="stock-status stock-available">
                    In stock: <?= $productStock ?>
                </p>

                <form action="cart.php" method="post">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= h(csrf_token()) ?>"
                    >

                    <input
                        type="hidden"
                        name="product_id"
                        value="<?= $productId ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="add"
                    >

                    <label for="size">Choose size</label>

                    <select id="size" name="size" required>
                        <?php foreach ($sizes as $size): ?>
                            <option value="<?= h($size) ?>">
                                <?= h($size) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="quantity">Quantity</label>

                    <input
                        id="quantity"
                        name="quantity"
                        type="number"
                        value="1"
                        min="1"
                        max="<?= min(10, $productStock) ?>"
                        required
                    >

                    <button class="btn btn-dark" type="submit">
                        ADD TO BAG ↗
                    </button>

                </form>

            <?php endif; ?>

            <p>
                Delivery and payment options are shown during checkout.
            </p>

            <a class="text-link" href="products.php">
                ← Continue shopping
            </a>

        </div>
    </div>
</main>

<footer class="footer">
    <div class="footer-bottom">
        <span>
            &copy; <?= date('Y') ?> ZARO WEAR
        </span>

        <a href="index.php">HOME ↑</a>
    </div>
</footer>

</body>
</html>
