<?php
$current_dir_level = 0;
include 'includes/header.php';
?>

<div class="hero-section bg-primary text-white py-5 rounded mb-4">
    <div class="container text-center">
        <h1 class="display-4">Welcome to GameVault</h1>
        <p class="lead">Genuine Game CD Keys • Premium Gaming Merchandise • Professional Gaming Gear</p>
        <a href="products/index.php" class="btn btn-light btn-lg mt-3">Shop Now</a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-key fa-3x text-primary mb-3"></i>
                <h5 class="card-title">Instant CD Key Delivery</h5>
                <p class="card-text">Receive CD keys immediately after purchase, fast and convenient</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-shield-alt fa-3x text-success mb-3"></i>
                <h5 class="card-title">100% Genuine Guarantee</h5>
                <p class="card-text">All products are official and authentic, safe and reliable</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="fas fa-shipping-fast fa-3x text-warning mb-3"></i>
                <h5 class="card-title">Fast Shipping</h5>
                <p class="card-text">Physical goods shipped within 24 hours, express delivery</p>
            </div>
        </div>
    </div>
</div>

<h2 class="mb-4">Popular Products</h2>
<div class="row">
    <?php
    $demo_products = [
        ['id' => 1, 'name' => 'League of Legends 1350 RP', 'price' => '68.00', 'type' => 'cdk', 'desc' => 'League of Legends game points CD Key, instant delivery'],
        ['id' => 2, 'name' => 'Genshin Impact Genesis Crystals', 'price' => '98.00', 'type' => 'cdk', 'desc' => 'Genshin Impact in-game currency CD Key'],
        ['id' => 3, 'name' => 'PUBG UC Coins', 'price' => '88.00', 'type' => 'cdk', 'desc' => 'PUBG game currency CD Key'],
        ['id' => 4, 'name' => 'Gaming Mechanical Keyboard', 'price' => '299.00', 'type' => 'physical', 'desc' => 'RGB backlit mechanical keyboard, blue switches'],
        ['id' => 5, 'name' => 'League of Legends Yasuo Figure', 'price' => '199.00', 'type' => 'physical', 'desc' => 'High-quality Yasuo figure model'],
        ['id' => 6, 'name' => 'Gaming Mouse', 'price' => '89.00', 'type' => 'physical', 'desc' => 'High-precision gaming mouse with RGB lighting']
    ];
    
    foreach ($demo_products as $product):
    ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h5 class="card-title"><?php echo $product['name']; ?></h5>
                <p class="card-text flex-grow-1"><?php echo $product['desc']; ?></p>
                <div class="mt-auto">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="h5 text-primary">$<?php echo $product['price']; ?></span>
                        <span class="badge bg-<?php echo $product['type'] == 'cdk' ? 'info' : 'secondary'; ?>">
                            <?php echo $product['type'] == 'cdk' ? 'CD Key' : 'Physical'; ?>
                        </span>
                    </div>
                    <a href="products/view.php?id=<?php echo $product['id']; ?>" class="btn btn-primary w-100 mt-2">View Details</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>