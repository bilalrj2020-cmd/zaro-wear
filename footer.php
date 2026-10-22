<?php
if (!isset($baseUrl)) {
    $baseUrl = '/zaro-wear-frontend';
}
?>

<footer class="site-footer">
    <div class="footer-container">

        <div class="footer-brand">
            <a href="<?= htmlspecialchars($baseUrl) ?>/index.php"
               class="footer-logo">
                ZARO<span>WEAR</span>
            </a>

            <p>
                Modern essentials, timeless style.
                Discover fashion made for everyday.
            </p>
        </div>

        <div class="footer-links">
            <h3>EXPLORE</h3>

            <a href="<?= htmlspecialchars($baseUrl) ?>/index.php">
                Home
            </a>

            <a href="<?= htmlspecialchars($baseUrl) ?>/products.php">
                Shop
            </a>

            <a href="<?= htmlspecialchars($baseUrl) ?>/cart.php">
                Shopping Cart
            </a>
        </div>

        <div class="footer-links">
            <h3>YOUR ACCOUNT</h3>

            <a href="<?= htmlspecialchars($baseUrl) ?>/login.php">
                Login
            </a>

            <a href="<?= htmlspecialchars($baseUrl) ?>/register.php">
                Create Account
            </a>
        </div>

    </div>

    <div class="footer-bottom">
        <p>
            &copy; <?= date('Y') ?> ZARO WEAR.
            All rights reserved.
        </p>
    </div>
</footer>
</body>
</html>
