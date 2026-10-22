<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$loggedIn = !empty($_SESSION['user_id']);
?>
<style>
.site-header {
    background: #111;
    color: #fff;
    padding: 18px 5%;
}
.site-header .nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;
}
.site-header a {
    color: #fff;
    text-decoration: none;
}
.site-header .brand {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 2px;
}
.site-header .links {
    display: flex;
    gap: 18px;
    align-items: center;
    flex-wrap: wrap;
}
.site-header a:hover {
    color: #ccc;
}
</style>

<header class="site-header">
    <nav class="nav">
        <a class="brand" href="index.php">ZARO WEAR</a>

        <div class="links">
            <a href="index.php">Home</a>
            <a href="products.php">Shop</a>
            <a href="cart.php">Cart</a>

            <?php if ($loggedIn): ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </nav>
</header>
