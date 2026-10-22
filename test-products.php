<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/includes/db.php';

echo '<h2>ZARO WEAR - Product Database Test</h2>';

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit('<p style="color:red">ERROR: Database connection variable $conn is missing.</p>');
}

if ($conn->connect_error) {
    exit('<p style="color:red">Database connection failed: '
        . htmlspecialchars($conn->connect_error) . '</p>');
}

echo '<p style="color:green">Database connection successful.</p>';

$sql = "SELECT Id, Name, price, category, description,
               stock, image_url, featured
        FROM products
        ORDER BY Id DESC";

$result = $conn->query($sql);

if (!$result) {
    exit('<p style="color:red">SQL Error: '
        . htmlspecialchars($conn->error) . '</p>');
}

echo '<p>Products found: ' . $result->num_rows . '</p>';

while ($product = $result->fetch_assoc()) {
    echo '<hr>';
    echo '<h3>' . htmlspecialchars($product['Name'] ?? '') . '</h3>';
    echo '<p>Price: ' . htmlspecialchars((string)$product['price']) . '</p>';
    echo '<p>Category: ' . htmlspecialchars($product['category'] ?? '') . '</p>';
    echo '<p>Image path: ' . htmlspecialchars($product['image_url'] ?? '') . '</p>';

    $image = trim((string)($product['image_url'] ?? ''));

    if ($image !== '') {
        $imageUrl = '/zaro-wear-frontend/' . ltrim($image, '/');

        echo '<img src="' . htmlspecialchars($imageUrl) .
             '" alt="Product image" style="width:150px;height:160px;object-fit:cover">';
    } else {
        echo '<p>No image path saved.</p>';
    }
}
?>
