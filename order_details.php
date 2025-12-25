<?php
$current_dir_level = 1;
include '../includes/header.php';

require_once '../config/config.php';

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['order_id'])) {
    header("Location: orders.php");
    exit;
}

$order_id = $_GET['order_id'];

// Set base URL for images
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$project_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__DIR__)));
$image_base_url = $base_url . $project_path . '/assets/images/products/';
$default_image_url = $image_base_url . 'default.jpg';

// Load products.json data
$products_json_path = __DIR__ . '/../data/products.json';
$products_data = [];

if (file_exists($products_json_path)) {
    $json_content = file_get_contents($products_json_path);
    $products_data = json_decode($json_content, true);
    
    if (isset($products_data['products'])) {
        // Create an array keyed by product_id for easy lookup
        $products_by_id = [];
        foreach ($products_data['products'] as $product) {
            $products_by_id[$product['product_id']] = $product;
        }
        $products_data = $products_by_id;
    }
} else {
    error_log("products.json file not found at: " . $products_json_path);
}

$database = new Database();
$db = $database->getConnection();
$order = null;
$order_items = [];

if ($db) {
    try {
        $user_id = $_SESSION['user_id'];
        
        $order_query = "SELECT o.*, 
                               s.first_name, 
                               s.last_name, 
                               s.email,
                               CONCAT(s.first_name, ' ', s.last_name) as customer_name
                        FROM orders o 
                        LEFT JOIN `if0_39913189_wp887`.`wppw_fc_subscribers` s ON o.user_id = s.id 
                        WHERE o.order_id = :order_id AND o.user_id = :user_id";
        
        $order_stmt = $db->prepare($order_query);
        $order_stmt->bindParam(':order_id', $order_id);
        $order_stmt->bindParam(':user_id', $user_id);
        $order_stmt->execute();
        
        if ($order_stmt->rowCount() > 0) {
            $order = $order_stmt->fetch(PDO::FETCH_ASSOC);

            // Note: fetch order items from the database, then enrich with products.json data
            $items_query = "SELECT oi.* FROM order_items oi WHERE oi.order_id = :order_id";
            
            $items_stmt = $db->prepare($items_query);
            $items_stmt->bindParam(':order_id', $order_id);
            $items_stmt->execute();
            $order_items_raw = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Retrieve product info from products.json and merge into order items
            foreach ($order_items_raw as $item) {
                $product_id = $item['product_id'];
                
                // Get product info from products.json
                if (isset($products_data[$product_id])) {
                    $product_info = $products_data[$product_id];
                    $order_items[] = array_merge($item, [
                        'product_name' => $product_info['product_name'] ?? 'Unknown Product',
                        'product_type' => $product_info['product_type'] ?? 'unknown',
                        'image_url' => $product_info['image_url'] ?? '',
                        'supplier' => $product_info['supplier'] ?? 'Unknown Supplier'
                    ]);
                } else {
                    // If the product is not in the JSON, use default values
                    $order_items[] = array_merge($item, [
                        'product_name' => 'Product #' . $product_id,
                        'product_type' => 'unknown',
                        'image_url' => '',
                        'supplier' => 'Unknown'
                    ]);
                }
            }

            $subtotal = 0;
            foreach ($order_items as $item) {
                $subtotal += $item['price_at_purchase'] * $item['quantity'];
            }
            
        } else {
            $_SESSION['error'] = "Order not found or you don't have permission to view this order.";
            header("Location: orders.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error in order_details.php: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while loading order details. Please try again.";

        try {
            $fallback_query = "SELECT o.* FROM orders o WHERE o.order_id = :order_id AND o.user_id = :user_id";
            
            $fallback_stmt = $db->prepare($fallback_query);
            $fallback_stmt->bindParam(':order_id', $order_id);
            $fallback_stmt->bindParam(':user_id', $user_id);
            $fallback_stmt->execute();
            
            if ($fallback_stmt->rowCount() > 0) {
                $order = $fallback_stmt->fetch(PDO::FETCH_ASSOC);
                $order['first_name'] = 'User';
                $order['last_name'] = '#' . $order['user_id'];
                $order['customer_name'] = 'User #' . $order['user_id'];
                $order['email'] = 'user' . $order['user_id'] . '@example.com';

                // Note: fetch order items from the database, then enrich with products.json data
                $items_query = "SELECT oi.* FROM order_items oi WHERE oi.order_id = :order_id";
                
                $items_stmt = $db->prepare($items_query);
                $items_stmt->bindParam(':order_id', $order_id);
                $items_stmt->execute();
                $order_items_raw = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Retrieve product info from products.json and merge into order items
                foreach ($order_items_raw as $item) {
                    $product_id = $item['product_id'];
                    
                    // Get product info from products.json
                    if (isset($products_data[$product_id])) {
                        $product_info = $products_data[$product_id];
                        $order_items[] = array_merge($item, [
                            'product_name' => $product_info['product_name'] ?? 'Unknown Product',
                            'product_type' => $product_info['product_type'] ?? 'unknown',
                            'image_url' => $product_info['image_url'] ?? '',
                            'supplier' => $product_info['supplier'] ?? 'Unknown Supplier'
                        ]);
                    } else {
                        // If the product is not in the JSON, use default values
                        $order_items[] = array_merge($item, [
                            'product_name' => 'Product #' . $product_id,
                            'product_type' => 'unknown',
                            'image_url' => '',
                            'supplier' => 'Unknown'
                        ]);
                    }
                }

                $subtotal = 0;
                foreach ($order_items as $item) {
                    $subtotal += $item['price_at_purchase'] * $item['quantity'];
                }
            }
        } catch (Exception $fallback_e) {
            error_log("Fallback query failed: " . $fallback_e->getMessage());
        }
    }
} else {
    $_SESSION['error'] = "Database connection failed.";
}

if (!$order) {
    echo '<div class="alert alert-danger">Unable to load order details. <a href="orders.php">Return to orders</a></div>';
    include '../includes/footer.php';
    exit;
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Order Details</h2>
            <a href="orders.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-1">Order #<?php echo $order['order_id']; ?></h5>
                        <p class="text-muted mb-0">Placed on <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-<?php 
                            switch($order['status']) {
                                case 'pending': echo 'warning'; break;
                                case 'processing': echo 'info'; break;
                                case 'completed': echo 'success'; break;
                                case 'cancelled': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                        ?> fs-6 p-2">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Order Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if(count($order_items) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): 
                                            $item_total = $item['price_at_purchase'] * $item['quantity'];
                                            $product_image_url = !empty($item['image_url']) ? 
                                                $image_base_url . $item['image_url'] : 
                                                $default_image_url;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0">
                                                        <img src="<?php echo $product_image_url; ?>" 
                                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                             class="rounded" width="60" height="60"
                                                             onerror="this.src='<?php echo $default_image_url; ?>'">
                                                    </div>
                                                    <div class="flex-grow-1 ms-3">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                        <span class="badge bg-<?php echo $item['product_type'] == 'cdk' ? 'info' : ($item['product_type'] == 'virtual' ? 'success' : 'secondary'); ?>">
                                                            <?php echo ucfirst($item['product_type']); ?>
                                                        </span>
                                                        <?php if(!empty($item['supplier'])): ?>
                                                            <div class="text-muted small mt-1">
                                                                Supplier: <?php echo htmlspecialchars($item['supplier']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>$<?php echo number_format($item['price_at_purchase'], 2); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>$<?php echo number_format($item_total, 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-muted">No items found in this order.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($order['status'] == 'completed'): ?>
                    <div class="alert alert-success">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="alert-heading">Order Completed</h5>
                                <p class="mb-0">Your order has been successfully completed. Thank you for shopping with us!</p>
                            </div>
                        </div>
                    </div>
                <?php elseif($order['status'] == 'pending'): ?>
                    <div class="alert alert-warning">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="alert-heading">Order Pending</h5>
                                <p class="mb-0">Your order is pending. You will receive an update soon.</p>
                            </div>
                        </div>
                    </div>
                <?php elseif($order['status'] == 'processing'): ?>
                    <div class="alert alert-info">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-cog fa-2x"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="alert-heading">Order Processing</h5>
                                <p class="mb-0">Your order is being processed. We'll notify you when it's ready.</p>
                            </div>
                        </div>
                    </div>
                <?php elseif($order['status'] == 'cancelled'): ?>
                    <div class="alert alert-danger">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-times-circle fa-2x"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="alert-heading">Order Cancelled</h5>
                                <p class="mb-0">This order has been cancelled. If this was a mistake, please contact our support team.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
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
                            <span>$<?php echo number_format($subtotal * 0.1, 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-0">
                            <strong>Total:</strong>
                            <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Customer Information</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $customer_name = !empty($order['customer_name']) && trim($order['customer_name']) != '' ? 
                            $order['customer_name'] : 
                            'User #' . $order['user_id'];
                        ?>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($customer_name); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                        <p><strong>Order ID:</strong> #<?php echo $order['order_id']; ?></p>
                        <p><strong>Order Date:</strong> <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Order Actions</h5>
                    </div>
                    <div class="card-body">
                        <button type="button" class="btn btn-outline-primary w-100 mb-2">
                            <i class="fas fa-download"></i> Download Invoice
                        </button>
                        <?php if($order['status'] == 'pending'): ?>
                            <button type="button" class="btn btn-outline-danger w-100" 
                                    onclick="confirmCancel(<?php echo $order['order_id']; ?>)">
                                <i class="fas fa-times"></i> Cancel Order
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmCancel(orderId) {
    if (confirm('Are you sure you want to cancel order #' + orderId + '?')) {
        window.location.href = 'cancel_order.php?order_id=' + orderId;
    }
}
</script>

<?php include '../includes/footer.php'; ?>