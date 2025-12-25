<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['order_id'])) {
    header("Location: orders.php");
    exit;
}

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$project_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__DIR__)));
$image_base_url = $base_url . $project_path . '/assets/images/products/';
$default_image_url = $image_base_url . 'default.jpg';
 
$json_file_path = __DIR__ . '/../data/products.json';
 
function getProductsFromJson($file_path) {
    if (!file_exists($file_path)) {
        error_log("JSON file does not exist at: " . $file_path);
        return array('error' => 'Products JSON file not found');
    }
    
    if (!is_readable($file_path)) {
        error_log("JSON file is not readable: " . $file_path);
        return array('error' => 'Products JSON file is not readable');
    }
    
    $json_content = file_get_contents($file_path);
    if ($json_content === false) {
        return array('error' => 'Failed to read JSON file');
    }
    
    $data = json_decode($json_content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array('error' => 'Error decoding JSON: ' . json_last_error_msg());
    }
    
    return $data;
}

// Obtain product data
$products_data = getProductsFromJson($json_file_path);
$all_products = isset($products_data['products']) ? $products_data['products'] : array();

// Create a mapping from product ID to product information
$product_map = array();
foreach ($all_products as $product) {
    $product_map[$product['product_id']] = $product;
}

$order_id = $_GET['order_id'];
$database = new Database();
$db = $database->getConnection();
$order = null;
$order_items = [];

if ($db) {
    try {
        // Query basic order information
        $order_query = "SELECT * FROM orders WHERE order_id = :order_id";
        $order_stmt = $db->prepare($order_query);
        $order_stmt->bindParam(':order_id', $order_id);
        $order_stmt->execute();
        
        if ($order_stmt->rowCount() > 0) {
            $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Retrieve user information from the wppw_fc_subscribers table
            $user_query = "SELECT first_name, last_name, email, phone FROM wppw_fc_subscribers WHERE id = :user_id";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->bindParam(':user_id', $order['user_id']);
            $user_stmt->execute();
            
            if ($user_stmt->rowCount() > 0) {
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Build customer name
                $first_name = $user['first_name'] ?? '';
                $last_name = $user['last_name'] ?? '';
                
                if (!empty(trim($first_name)) || !empty(trim($last_name))) {
                    $customer_name = trim($first_name . ' ' . $last_name);
                } else {
                    $customer_name = !empty(trim($user['email'])) ? $user['email'] : 'Customer';
                }
                
                // Add user information to order data
                $order['customer_name'] = $customer_name;
                $order['email'] = $user['email'] ?? 'N/A';
                $order['phone'] = $user['phone'] ?? 'N/A';
            } else {
                // If there are no records in the user table, use default values
                $order['customer_name'] = 'Customer #' . $order['user_id'];
                $order['email'] = 'Not available';
                $order['phone'] = 'N/A';
            }

            // Get order product information
            $items_query = "SELECT oi.* FROM order_items oi WHERE oi.order_id = :order_id";
            
            $items_stmt = $db->prepare($items_query);
            $items_stmt->bindParam(':order_id', $order_id);
            $items_stmt->execute();
            $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($order_items as &$item) {
                $product_id = $item['product_id'];
                
                if (isset($product_map[$product_id])) {
                    $product_info = $product_map[$product_id];
                    $item['product_name'] = $product_info['product_name'];
                    $item['product_type'] = $product_info['product_type'];
                    $item['image_url'] = $product_info['image_url'];
                } else {
                    // If the product is not found in the JSON file, use default values
                    $item['product_name'] = 'Product #' . $product_id . ' (Not Found in JSON)';
                    $item['product_type'] = 'unknown';
                    $item['image_url'] = '';
                }
            }
            unset($item); // Unset reference
            
        } else {
            header("Location: orders.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error in admin order_details.php: " . $e->getMessage());
            // Fallback: Try to get basic order info without user details
        try {
            $fallback_query = "SELECT * FROM orders WHERE order_id = :order_id";
            $fallback_stmt = $db->prepare($fallback_query);
            $fallback_stmt->bindParam(':order_id', $order_id);
            $fallback_stmt->execute();
            
            if ($fallback_stmt->rowCount() > 0) {
                $order = $fallback_stmt->fetch(PDO::FETCH_ASSOC);
                $order['customer_name'] = 'Customer #' . $order['user_id'];
                $order['email'] = 'Information not available';
                $order['phone'] = 'N/A';
                
                // Get order product information
                $items_query = "SELECT oi.* FROM order_items oi WHERE oi.order_id = :order_id";
                
                $items_stmt = $db->prepare($items_query);
                $items_stmt->bindParam(':order_id', $order_id);
                $items_stmt->execute();
                $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Add product information obtained from JSON for each order item
                foreach ($order_items as &$item) {
                    $product_id = $item['product_id'];
                    
                    if (isset($product_map[$product_id])) {
                        $product_info = $product_map[$product_id];
                        $item['product_name'] = $product_info['product_name'];
                        $item['product_type'] = $product_info['product_type'];
                        $item['image_url'] = $product_info['image_url'];
                    } else {
                        // If the product is not found in the JSON file, use default values
                        $item['product_name'] = 'Product #' . $product_id . ' (Not Found in JSON)';
                        $item['product_type'] = 'unknown';
                        $item['image_url'] = '';
                    }
                }
                unset($item); // Unset reference
                
            } else {
                header("Location: orders.php");
                exit;
            }
        } catch (Exception $fallback_e) {
            error_log("Fallback also failed: " . $fallback_e->getMessage());
            header("Location: orders.php");
            exit;
        }
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Order Details #<?php echo $order_id; ?></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="orders.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Order Items</h5>
            </div>
            <div class="card-body">
                <?php if(count($order_items) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $subtotal = 0;
                                foreach ($order_items as $item): 
                                    $item_total = $item['price_at_purchase'] * $item['quantity'];
                                    $subtotal += $item_total;

                                    $item_image_url = !empty($item['image_url']) ? 
                                        $image_base_url . $item['image_url'] : 
                                        $default_image_url;
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo $item_image_url; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                 class="me-3" 
                                                 style="width: 50px; height: 50px; object-fit: cover;"
                                                 onerror="this.onerror=null; this.src='<?php echo $default_image_url; ?>';">
                                            <div>
                                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">Product ID: <?php echo $item['product_id']; ?></small>
                                                <?php if (strpos($item['product_name'], 'Not Found in JSON') !== false): ?>
                                                    <br><small class="text-danger">Product data loaded from JSON file</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            switch($item['product_type']) {
                                                case 'cdk': echo 'info'; break;
                                                case 'physical': echo 'primary'; break;
                                                case 'virtual': echo 'success'; break;
                                                default: echo 'secondary';
                                            }
                                        ?>">
                                            <?php echo ucfirst($item['product_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>$<?php echo number_format($item['price_at_purchase'], 2); ?></td>
                                    <td><strong>$<?php echo number_format($item_total, 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                    <td><strong>$<?php echo number_format($subtotal, 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Tax (10%):</strong></td>
                                    <td><strong>$<?php echo number_format($subtotal * 0.1, 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Total Amount:</strong></td>
                                    <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No items found in this order.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Order Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Order ID:</strong><br>
                    <span class="text-muted">#<?php echo $order['order_id']; ?></span>
                </div>
                <div class="mb-3">
                    <strong>Order Date:</strong><br>
                    <span class="text-muted"><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="mb-3">
                    <strong>Status:</strong><br>
                    <span class="badge bg-<?php 
                        switch($order['status']) {
                            case 'pending': echo 'warning'; break;
                            case 'processing': echo 'info'; break;
                            case 'completed': echo 'success'; break;
                            case 'cancelled': echo 'danger'; break;
                            default: echo 'secondary';
                        }
                    ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>
                <div class="mb-3">
                    <strong>Total Amount:</strong><br>
                    <span class="text-muted">$<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Customer Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Customer Name:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                </div>
                <div class="mb-3">
                    <strong>Email:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($order['email']); ?></span>
                </div>
                <div class="mb-3">
                    <strong>Phone:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="mb-3">
                    <strong>User ID:</strong><br>
                    <span class="text-muted"><?php echo $order['user_id']; ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Admin Actions</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="update_order_status.php" class="mb-3">
                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                    <div class="mb-3">
                        <label for="status" class="form-label"><strong>Update Order Status</strong></label>
                        <select name="status" class="form-select" id="status">
                            <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Status</button>
                </form>
                
                <button type="button" class="btn btn-danger w-100" 
                        onclick="if(confirm('Are you sure you want to delete order #<?php echo $order['order_id']; ?>? This action cannot be undone!')) { window.location.href='?delete_order=<?php echo $order['order_id']; ?>'; }">
                    <i class="fas fa-trash"></i> Delete This Order
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>