<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

/* Only admins can access this page */
if (
    empty($_SESSION['user_id']) ||
    ($_SESSION['user_role'] ?? '') !== 'admin'
) {
    header('Location: ../login.php');
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection error. Check includes/db.php');
}

$conn->set_charset('utf8mb4');

function productEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$error = '';
$editProduct = null;

/* Add, update or delete product */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verify_csrf()) {
            throw new RuntimeException(
                'Session expired. Please refresh the page and try again.'
            );
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = filter_var(
                $_POST['Id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id < 1) {
                throw new RuntimeException('Invalid product ID.');
            }

            $stmt = $conn->prepare(
                'DELETE FROM products WHERE `Id` = ?'
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            header('Location: products.php?status=deleted');
            exit;
        }

        if ($action === 'save') {
            $id = (int)($_POST['Id'] ?? 0);
            $name = trim($_POST['Name'] ?? '');
            $price = trim($_POST['price'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $stock = filter_var(
                $_POST['stock'] ?? '0',
                FILTER_VALIDATE_INT
            );
            $imageUrl = trim($_POST['image_url'] ?? '');
            $featured = isset($_POST['featured']) ? 1 : 0;

            if ($name === '' || strlen($name) > 100) {
                throw new RuntimeException(
                    'Product name is required and must be under 100 characters.'
                );
            }

            if (
                $price === '' ||
                !is_numeric($price) ||
                (float)$price < 0
            ) {
                throw new RuntimeException(
                    'Please enter a valid product price.'
                );
            }

            if (strlen($category) > 100) {
                throw new RuntimeException(
                    'Category must be under 100 characters.'
                );
            }

            if ($stock === false || $stock < 0) {
                throw new RuntimeException(
                    'Stock must be zero or a positive whole number.'
                );
            }

            if (strlen($imageUrl) > 500) {
                throw new RuntimeException(
                    'Image path must be under 500 characters.'
                );
            }

            if (
                $imageUrl !== '' &&
                !preg_match('/^(https?:\/\/|assets\/)/i', $imageUrl)
            ) {
                throw new RuntimeException(
                    'Use an image URL or a path beginning with assets/.'
                );
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    'UPDATE products
                     SET `Name` = ?, `price` = ?, `category` = ?,
                         `description` = ?, `stock` = ?,
                         `image_url` = ?, `featured` = ?
                     WHERE `Id` = ?'
                );

                $priceString = (string)$price;

                
$stmt->bind_param(
    'ssssisii',
    $name,
    $priceString,
    $category,
    $description,
    $stock,
    $imageUrl,
    $featured,
    $id
);

            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO products
                     (`Name`, `price`, `category`, `description`,
                      `stock`, `image_url`, `featured`)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );

                $priceString = (string)$price;

                $stmt->bind_param(
                    'ssssisi',
                    $name,
                    $priceString,
                    $category,
                    $description,
                    $stock,
                    $imageUrl,
                    $featured
                );
            }

            $stmt->execute();
            $stmt->close();

            header(
                'Location: products.php?status=' .
                ($id > 0 ? 'updated' : 'added')
            );
            exit;
        }

        throw new RuntimeException('Invalid request.');
    } catch (Throwable $e) {
        error_log('ZARO WEAR admin products error: ' . $e->getMessage());
        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Something went wrong. Check the PHP error log.';
    }
}

/* Success messages */
$status = $_GET['status'] ?? '';

if ($status === 'added') {
    $message = 'Product added successfully.';
} elseif ($status === 'updated') {
    $message = 'Product updated successfully.';
} elseif ($status === 'deleted') {
    $message = 'Product deleted successfully.';
}

/* Load product for editing */
$editId = filter_var(
    $_GET['edit'] ?? null,
    FILTER_VALIDATE_INT
);

if ($editId && $editId > 0) {
    $stmt = $conn->prepare(
        'SELECT `Id`, `Name`, `price`, `category`, `description`,
                `stock`, `image_url`, `featured`
         FROM products WHERE `Id` = ?'
    );
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editProduct = $result->fetch_assoc();
    $stmt->close();

    if (!$editProduct) {
        $error = 'Product not found.';
    }
}

/* Load all products */
$result = $conn->query(
    'SELECT `Id`, `Name`, `price`, `category`, `description`,
            `stock`, `image_url`, `featured`
     FROM products ORDER BY `Id` DESC'
);

$products = $result->fetch_all(MYSQLI_ASSOC);
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products | ZARO WEAR</title>

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
            color: #fff;
            padding: 20px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        header h1 {
            margin: 0;
            font-size: 22px;
            letter-spacing: 1px;
        }

        header a {
            color: #fff;
            text-decoration: none;
            margin-left: 16px;
            font-size: 14px;
        }

        main {
            width: 94%;
            max-width: 1250px;
            margin: 30px auto;
        }

        .panel {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 25px;
            box-shadow: 0 5px 22px #1416290b;
        }

        h2 {
            margin-top: 0;
            font-size: 21px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 13px;
            font-weight: bold;
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d9dce5;
            border-radius: 7px;
            font: inherit;
        }

        textarea {
            resize: vertical;
            min-height: 85px;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-row input {
            width: auto;
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
            font-weight: bold;
            font-size: 13px;
        }

        .secondary {
            background: #343747;
        }

        .danger {
            background: #d83b4b;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .notice {
            padding: 13px 16px;
            margin-bottom: 18px;
            border-radius: 7px;
            background: #e0f7e8;
            color: #176638;
        }

        .error {
            background: #ffe5e5;
            color: #a51e2b;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        th, td {
            padding: 13px 10px;
            border-bottom: 1px solid #eceef3;
            text-align: left;
            font-size: 13px;
            vertical-align: middle;
        }

        th {
            background: #f8f8fb;
        }

        .product-image {
            width: 58px;
            height: 58px;
            object-fit: cover;
            border-radius: 7px;
            background: #eee;
        }

        .muted {
            color: #777b89;
            font-size: 12px;
        }

        @media (max-width: 650px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .panel {
                padding: 17px;
            }

            header {
                padding: 18px 4%;
            }
        }
    </style>
</head>

<body>
<header>
    <h1>ZARO WEAR — ADMIN</h1>
    <nav>
        <a href="index.php">Dashboard</a>
        <a href="../products.php">View Shop</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<main>
    <?php if ($message !== ''): ?>
        <div class="notice">
            <?= productEscape($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="notice error">
            <?= productEscape($error) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <h2>
            <?= $editProduct ? 'Edit Product' : 'Add New Product' ?>
        </h2>

        <form method="POST" action="products.php">
            <input type="hidden" name="csrf_token"
                   value="<?= productEscape($csrf) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="Id"
                   value="<?= productEscape($editProduct['Id'] ?? 0) ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="Name">Product Name *</label>
                    <input id="Name" name="Name" maxlength="100"
                           required
                           value="<?= productEscape($editProduct['Name'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="price">Price (PKR) *</label>
                    <input id="price" name="price" type="number"
                           min="0" step="0.01" required
                           value="<?= productEscape($editProduct['price'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="category">Category</label>
                    <input id="category" name="category" maxlength="100"
                           placeholder="Men, Women, Shoes..."
                           value="<?= productEscape($editProduct['category'] ?? '') ?>">
                </div>

                <div class="field">
                    <label for="stock">Stock Quantity *</label>
                    <input id="stock" name="stock" type="number"
                           min="0" step="1" required
                           value="<?= productEscape($editProduct['stock'] ?? 10) ?>">
                </div>

                <div class="field full">
                    <label for="image_url">Product Image Path or URL</label>
                    <input id="image_url" name="image_url" maxlength="500"
                           placeholder="assets/images/black-shirt.jpg"
                           value="<?= productEscape($editProduct['image_url'] ?? '') ?>">
                    <span class="muted">
                        Example: assets/images/black-shirt.jpg
                    </span>
                </div>

                <div class="field full">
                    <label for="description">Description</label>
                    <textarea id="description" name="description"><?= productEscape($editProduct['description'] ?? '') ?></textarea>
                </div>

                <div class="field full">
                    <label class="checkbox-row">
                        <input type="checkbox" name="featured" value="1"
                            <?= !empty($editProduct['featured']) ? 'checked' : '' ?>>
                        Featured product
                    </label>
                </div>

                <div class="field full">
                    <div class="actions">
                        <button type="submit">
                            <?= $editProduct ? 'Update Product' : 'Add Product' ?>
                        </button>

                        <?php if ($editProduct): ?>
                            <a class="button secondary" href="products.php">
                                Cancel Edit
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>All Products (<?= count($products) ?>)</h2>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Featured</th>
                    <th>Actions</th>
                </tr>
                </thead>

                <tbody>
                <?php if (!$products): ?>
                    <tr>
                        <td colspan="8">
                            No products yet. Use the form above to add your first product.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= (int)$product['Id'] ?></td>

                            <td>
                                <?php
                                $image = trim((string)($product['image_url'] ?? ''));
                                $safeImage = '';

                                if (
                                    preg_match('/^https?:\/\//i', $image) ||
                                    preg_match('/^assets\/[a-zA-Z0-9_\-\/.]+$/', $image)
                                ) {
                                    $safeImage = $image;
                                }
                                ?>

                                <?php if ($safeImage !== ''): ?>
                                    <img class="product-image"
                                         src="../<?= productEscape($safeImage) ?>"
                                         alt="<?= productEscape($product['Name']) ?>"
                                         onerror="this.style.display='none'">
                                <?php else: ?>
                                    <span class="muted">No image</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <strong><?= productEscape($product['Name']) ?></strong>
                            </td>

                            <td>PKR <?= productEscape($product['price']) ?></td>
                            <td><?= productEscape($product['category']) ?></td>
                            <td><?= (int)$product['stock'] ?></td>
                            <td><?= !empty($product['featured']) ? 'Yes' : 'No' ?></td>

                            <td>
                                <div class="actions">
                                    <a class="button"
                                       href="products.php?edit=<?= (int)$product['Id'] ?>">
                                        Edit
                                    </a>

                                    <form method="POST" action="products.php"
                                          onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= productEscape($csrf) ?>">
                                        <input type="hidden" name="action"
                                               value="delete">
                                        <input type="hidden" name="Id"
                                               value="<?= (int)$product['Id'] ?>">

                                        <button class="danger" type="submit">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>