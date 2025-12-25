<?php
$current_dir_level = 1;
include '../includes/header.php';

date_default_timezone_set('Asia/Shanghai');

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

if (empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

// Read product data from JSON file
$json_file = '../data/products.json';
$products_data = [];

if (file_exists($json_file)) {
    $json_content = file_get_contents($json_file);
    $data = json_decode($json_content, true);
    
    if ($data && isset($data['products'])) {
        $products_data = $data['products'];
    }
}

// Create mapping from product ID to product data
$products_by_id = [];
foreach ($products_data as $product) {
    $products_by_id[$product['product_id']] = $product;
}

// Validate product stock in the cart
$cart_items = [];
$subtotal = 0;
$all_in_stock = true;
$out_of_stock_items = [];

foreach ($_SESSION['cart'] as $cart_item) {
    $product_id = $cart_item['product_id'];
    
    if (!isset($products_by_id[$product_id])) {
        $all_in_stock = false;
        $out_of_stock_items[] = "Product ID {$product_id} not found in inventory";
        continue;
    }
    
    $product = $products_by_id[$product_id];
    
    if ($cart_item['quantity'] > $product['stock_quantity']) {
        $all_in_stock = false;
        $out_of_stock_items[] = "Insufficient stock for '{$product['product_name']}'. Available: {$product['stock_quantity']}, Requested: {$cart_item['quantity']}";
        continue;
    }
    
    $cart_items[] = [
        'cart_item' => $cart_item,
        'product_data' => $product
    ];
    
    $subtotal += $cart_item['price'] * $cart_item['quantity'];
}

if (!$all_in_stock) {
    $_SESSION['error_message'] = implode('<br>', $out_of_stock_items);
    header("Location: index.php");
    exit;
}

$tax = $subtotal * 0.1;
$total = $subtotal + $tax;

$user_data = [
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'first_name' => $_SESSION['first_name'] ?? '',
    'last_name' => $_SESSION['last_name'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $db->beginTransaction();

        $user_id = $_SESSION['user_id'];
        $order_time = date('Y-m-d H:i:s');

            // Insert order record - let database auto-generate order_id
        $order_query = "INSERT INTO orders (user_id, total_amount, status, created_at) VALUES (:user_id, :total_amount, 'pending', :created_at)";
        $order_stmt = $db->prepare($order_query);
        $order_stmt->bindParam(':user_id', $user_id);
        $order_stmt->bindParam(':total_amount', $total);
        $order_stmt->bindParam(':created_at', $order_time);
        
        if (!$order_stmt->execute()) {
            throw new Exception("Failed to insert order: " . implode(", ", $order_stmt->errorInfo()));
        }
        
        $order_id = $db->lastInsertId();
        
        if (!$order_id) {
            throw new Exception("Failed to get order ID");
        }

        // Insert order items and update stock
        foreach ($cart_items as $item) {
            $cart_item = $item['cart_item'];
            $product_data = $item['product_data'];
            
            // Insert order item
            $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) 
                          VALUES (:order_id, :product_id, :quantity, :price)";
            $item_stmt = $db->prepare($item_query);
            $item_stmt->bindParam(':order_id', $order_id);
            $item_stmt->bindParam(':product_id', $cart_item['product_id']);
            $item_stmt->bindParam(':quantity', $cart_item['quantity']);
            $item_stmt->bindParam(':price', $cart_item['price']);
            
            if (!$item_stmt->execute()) {
                throw new Exception("Failed to insert order item: " . implode(", ", $item_stmt->errorInfo()));
            }
            
            // Update stock quantities in JSON file
            $product_id = $cart_item['product_id'];
            foreach ($products_data as &$product) {
                if ($product['product_id'] == $product_id) {
                    $product['stock_quantity'] -= $cart_item['quantity'];
                    break;
                }
            }
        }
        
        // Save updated JSON data
        $updated_data = ['products' => $products_data];
        if (!file_put_contents($json_file, json_encode($updated_data, JSON_PRETTY_PRINT))) {
            throw new Exception("Failed to update JSON file");
        }
        
        $db->commit();

        // Clear the shopping cart
        $_SESSION['cart'] = [];
        
        $success = true;
        $order_success_id = $order_id;
        $order_success_time = $order_time; 
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Order failed: " . $e->getMessage();
        error_log("Checkout error: " . $e->getMessage());
    }
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userData = <?php echo json_encode($user_data); ?>;
    const cartData = <?php echo json_encode($_SESSION['cart']); ?>;
    const orderSummary = {
        subtotal: <?php echo $subtotal; ?>,
        tax: <?php echo $tax; ?>,
        total: <?php echo $total; ?>
    };

    localStorage.setItem('checkout_user_data', JSON.stringify(userData));
    localStorage.setItem('checkout_cart_data', JSON.stringify(cartData));
    localStorage.setItem('checkout_order_summary', JSON.stringify(orderSummary));

    const checkoutForm = document.querySelector('#checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            // Clean up local storage
            setTimeout(() => {
                if (<?php echo isset($success) && $success ? 'true' : 'false'; ?>) {
                    localStorage.removeItem('checkout_user_data');
                    localStorage.removeItem('checkout_cart_data');
                    localStorage.removeItem('checkout_order_summary');
                }
            }, 1000);
        });
    }
});

window.addEventListener('beforeunload', function() {
    if (<?php echo isset($success) && $success ? 'true' : 'false'; ?>) {
        localStorage.removeItem('checkout_user_data');
        localStorage.removeItem('checkout_cart_data');
        localStorage.removeItem('checkout_order_summary');
    }
});
</script>

<div class="row">
    <div class="col-md-8">
        <h2>Checkout</h2>
        
        <?php if(isset($success) && $success): ?>
            <div class="alert alert-success">
                <h4>Order Placed Successfully!</h4>
                <p>Thank you for your purchase. Your order ID is: <strong>#<?php echo $order_success_id; ?></strong></p>
                <p>Order Time: <strong><?php echo date('Y-m-d H:i:s', strtotime($order_success_time)); ?></strong></p>
                <p>You will receive a confirmation email shortly.</p>
                <a href="../user/orders.php" class="btn btn-primary">View Your Orders</a>
                <a href="../products/index.php" class="btn btn-outline-primary">Continue Shopping</a>
            </div>
        <?php else: ?>
        
            <?php if(isset($error)): ?>
                <div class="alert alert-danger">
                    <h5>Order Failed</h5>
                    <p><?php echo $error; ?></p>
                    <p>Please try again or contact support if the problem persists.</p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Order Review</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                    <div class="row mb-3 pb-3 border-bottom">
                        <div class="col-8">
                            <h6><?php echo htmlspecialchars($item['product_name']); ?></h6>
                            <small class="text-muted">Quantity: <?php echo $item['quantity']; ?></small>
                        </div>
                        <div class="col-4 text-end">
                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="row">
                        <div class="col-6">
                            <strong>Subtotal:</strong>
                        </div>
                        <div class="col-6 text-end">
                            $<?php echo number_format($subtotal, 2); ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <strong>Tax (10%):</strong>
                        </div>
                        <div class="col-6 text-end">
                            $<?php echo number_format($tax, 2); ?>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <strong>Total:</strong>
                        </div>
                        <div class="col-6 text-end">
                            <strong>$<?php echo number_format($total, 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Payment Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="checkout-form">
                        <div class="mb-3">
                            <label for="card_number" class="form-label">Card Number</label>
                            <input type="text" class="form-control" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="expiry_date" class="form-label">Expiry Date</label>
                                    <input type="text" class="form-control" id="expiry_date" name="expiry_date" placeholder="MM/YY" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cvv" class="form-label">CVV</label>
                                    <input type="text" class="form-control" id="cvv" name="cvv" placeholder="123" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="card_holder" class="form-label">Card Holder Name</label>
                            <input type="text" class="form-control" id="card_holder" name="card_holder" required>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#">Terms and Conditions</a>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100">Place Order</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Order Summary</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span>$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tax (10%):</span>
                    <span>$<?php echo number_format($tax, 2); ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <strong>Total:</strong>
                    <strong>$<?php echo number_format($total, 2); ?></strong>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Customer Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Name:</strong> <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email']); ?></p>
                <small class="text-muted">This information is stored locally for your convenience.</small>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Need Help?</h5>
            </div>
            <div class="card-body">
                <p>Contact our customer support:</p>
                <ul class="list-unstyled">
                    <li><i class="fas fa-envelope"></i> support@gamevault.com</li>
                    <li><i class="fas fa-phone"></i> +1 (555) 123-4567</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>