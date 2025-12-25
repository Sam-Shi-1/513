<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_dir = '';
if (isset($current_dir_level)) {
    $current_dir = str_repeat('../', $current_dir_level);
}

if (!function_exists('isLoggedIn') || !defined('SITE_NAME')) {
    $config_path = __DIR__ . '/../config/config.php';
    if (file_exists($config_path)) {
        require_once $config_path;
    }
}

if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'GameVault');
}

$cart_total_quantity = 0;
$cart_items_count = 0;
if (isset($_SESSION['cart'])) {
    $cart_items_count = count($_SESSION['cart']);
    foreach ($_SESSION['cart'] as $item) {
        $cart_total_quantity += $item['quantity'];
    }

    error_log("Header - Cart items: $cart_items_count, Total quantity: $cart_total_quantity");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Game Products Store</title>

    <link rel="preload" href="<?php echo $current_dir; ?>assets/css/bootstrap.min.css" as="style">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style">
    <link rel="preload" href="<?php echo $current_dir; ?>assets/css/style.css" as="style">

    <link href="<?php echo $current_dir; ?>assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $current_dir; ?>assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $current_dir; ?>index.php">
                <img src="<?php echo $current_dir; ?>assets/images/logo.png" alt="<?php echo SITE_NAME; ?>" height="30">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $current_dir; ?>index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $current_dir; ?>products/index.php">Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $current_dir; ?>about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $current_dir; ?>contact.php">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $current_dir; ?>forum/forum_index.php">Forum</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $current_dir; ?>Recruitment/careers.php">Recruitment</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $current_dir; ?>cart/index.php">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <?php if ($cart_total_quantity > 0): ?>
                                <span class="badge bg-danger cart-counter"><?php echo $cart_total_quantity; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <?php if(isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['username']; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo $current_dir; ?>user/profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="<?php echo $current_dir; ?>user/orders.php">My Orders</a></li>
                                <?php if(isAdmin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo $current_dir; ?>admin/dashboard.php">Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo $current_dir; ?>auth/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo $current_dir; ?>auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="https://sam4567.lovestoblog.com/WordPress/register/?i=1">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-4">