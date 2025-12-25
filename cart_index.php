<?php
$current_dir_level = 1;
include '../includes/header.php';

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$project_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__DIR__)));
$image_base_url = $base_url . $project_path . '/assets/images/products/';
$default_image_url = $image_base_url . 'default.jpg';

$cart_total_quantity = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_total_quantity += $item['quantity'];
    }
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_GET['clear_cart']) && $_GET['clear_cart'] === 'true') {
    $_SESSION['cart'] = [];
    $_SESSION['success_message'] = "Shopping cart has been cleared successfully.";
    header("Location: index.php");
    exit;
}

// Handle quantity updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_quantity'])) {
    $product_id = $_POST['product_id'];
    $quantity = intval($_POST['quantity']);
    
    if ($quantity <= 0) {
        // Remove item from cart
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $product_id) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['success_message'] = "Item removed from cart";
                break;
            }
        }
    } else {
        // Update quantity
        $itemFound = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $product_id) {
                $item['quantity'] = $quantity;
                $itemFound = true;
                $_SESSION['success_message'] = "Quantity updated successfully";
                break;
            }
        }
        
        if (!$itemFound) {
            $_SESSION['error_message'] = "Item not found in cart";
        }
    }
    
    // Reindex array
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    header("Location: index.php");
    exit;
}

// Handle item removal
if (isset($_GET['remove'])) {
    $remove_id = $_GET['remove'];
    $itemFound = false;
    
    foreach ($_SESSION['cart'] as $key => $item) {
        if ($item['product_id'] == $remove_id) {
            unset($_SESSION['cart'][$key]);
            $itemFound = true;
            $_SESSION['success_message'] = "Item removed from cart";
            break;
        }
    }
    
    if (!$itemFound) {
        $_SESSION['error_message'] = "Item not found in cart";
    }
    
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    header("Location: index.php");
    exit;
}

$unique_cart = [];
foreach ($_SESSION['cart'] as $item) {
    $product_id = $item['product_id'];
    if (isset($unique_cart[$product_id])) {
        $unique_cart[$product_id]['quantity'] += $item['quantity'];
    } else {
        $unique_cart[$product_id] = $item;
    }
}
if (count($unique_cart) != count($_SESSION['cart'])) {
    $_SESSION['cart'] = array_values($unique_cart);
}

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}
$tax = $subtotal * 0.1; // 10% tax
$total = $subtotal + $tax;
?>

<div class="row">
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Shopping Cart</h2>
            <?php if(!empty($_SESSION['cart'])): ?>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearCartModal">
                <i class="fas fa-trash me-2"></i>Clear Cart
            </button>
            <?php endif; ?>
        </div>
        
        <?php 
        // Display success/error messages
        if (isset($_SESSION['success_message'])): 
        ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if(empty($_SESSION['cart'])): ?>
            <div class="alert alert-info">
                <div class="text-center py-4">
                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                    <h4>Your cart is empty</h4>
                    <p class="text-muted">Add some items to your cart to continue shopping</p>
                    <a href="../products/index.php" class="btn btn-primary mt-2">Continue shopping</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($_SESSION['cart'] as $item): ?>
            <div class="card mb-3 cart-item" data-product-id="<?php echo $item['product_id']; ?>">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <?php
                            $product_image_url = !empty($item['product_image']) ? 
                                $image_base_url . htmlspecialchars($item['product_image']) : 
                                $default_image_url;
                            ?>
                            <img src="<?php echo $product_image_url; ?>" 
                                class="img-fluid rounded" 
                                alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                onerror="this.src='<?php echo $default_image_url; ?>'">
                        </div>
                        <div class="col-md-4">
                            <h5><?php echo htmlspecialchars($item['product_name']); ?></h5>
                            <p class="text-muted">Product ID: <?php echo $item['product_id']; ?></p>
                            <p class="text-primary">$<?php echo number_format($item['price'], 2); ?> each</p>
                        </div>
                        <div class="col-md-3">
                            <form method="POST" class="quantity-form">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <div class="input-group">
                                    <button type="button" class="btn btn-outline-secondary decrement-btn">-</button>
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                        min="1" max="99" class="form-control text-center quantity-input">
                                    <button type="button" class="btn btn-outline-secondary increment-btn">+</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-3">
                            <div class="text-end">
                                <strong class="h5">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong>
                                <div class="mt-2">
                                    <a href="?remove=<?php echo $item['product_id']; ?>" class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to remove this item from your cart?')">
                                        <i class="fas fa-times me-1"></i>Remove
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <div class="card sticky-top" style="top: 20px;">
            <div class="card-header">
                <h5 class="mb-0">Order Summary</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Items (<?php echo $total_items; ?>):</span>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tax (10%):</span>
                    <span>$<?php echo number_format($tax, 2); ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <strong>Total:</strong>
                    <strong class="h5">$<?php echo number_format($total, 2); ?></strong>
                </div>
                
                <?php if(!empty($_SESSION['cart'])): ?>
                    <a href="checkout.php" class="btn btn-primary btn-lg w-100">Proceed to Checkout</a>
                <?php else: ?>
                    <button class="btn btn-secondary btn-lg w-100" disabled>Proceed to Checkout</button>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="../products/index.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="clearCartModal" tabindex="-1" aria-labelledby="clearCartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="clearCartModalLabel">Clear Shopping Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-3">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
                </div>
                <h5>Are you sure?</h5>
                <p class="text-muted">This will remove all items from your shopping cart. This action cannot be undone.</p>
                <div class="alert alert-info">
                    <small>
                        <i class="fas fa-info-circle me-2"></i>
                        You have <strong><?php echo count($_SESSION['cart']); ?> item(s)</strong> in your cart totaling 
                        <strong>$<?php echo number_format($total, 2); ?></strong>
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="?clear_cart=true" class="btn btn-danger">
                    <i class="fas fa-trash me-2"></i>Clear Cart
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>