<?php
$current_dir_level = 1;
include '../includes/header.php';

require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
$users_count = 0;
$products_count = 0;
$orders_count = 0;
$revenue = 0;
$recent_orders = [];

if ($db) {
    try {
        //Modify user queries and remove cross database prefixes
        $users_count_stmt = $db->query("SELECT COUNT(*) as count FROM wppw_fc_subscribers");
        $users_count_result = $users_count_stmt->fetch(PDO::FETCH_ASSOC);
        $users_count = $users_count_result['count'] ?? 0;

        $products_count_stmt = $db->query("SELECT COUNT(*) as count FROM products");
        $products_count_result = $products_count_stmt->fetch(PDO::FETCH_ASSOC);
        $products_count = $products_count_result['count'] ?? 0;

        $orders_count_stmt = $db->query("SELECT COUNT(*) as count FROM orders");
        $orders_count_result = $orders_count_stmt->fetch(PDO::FETCH_ASSOC);
        $orders_count = $orders_count_result['count'] ?? 0;

        $revenue_stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE status = 'completed'");
        $revenue_result = $revenue_stmt->fetch(PDO::FETCH_ASSOC);
        $revenue = $revenue_result['revenue'] ?? 0;

        //Modify recent order queries, remove cross database prefixes, and improve customer name construction
        $recent_orders_query = "SELECT o.*, 
                                       s.first_name, 
                                       s.last_name,
                                       s.email
                                FROM orders o 
                                LEFT JOIN wppw_fc_subscribers s ON o.user_id = s.id 
                                ORDER BY o.created_at DESC 
                                LIMIT 5";
        $recent_orders_stmt = $db->prepare($recent_orders_query);
        $recent_orders_stmt->execute();
        $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in admin dashboard: " . $e->getMessage());

        try {
            $recent_orders_query = "SELECT o.* FROM orders o ORDER BY o.created_at DESC LIMIT 5";
            $recent_orders_stmt = $db->prepare($recent_orders_query);
            $recent_orders_stmt->execute();
            $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $fallback_e) {
            error_log("Fallback query also failed: " . $fallback_e->getMessage());
            $recent_orders = [];
        }
    }
}

if (!$db) {
    $users_count = 2;
    $products_count = 6;
    $orders_count = 0;
    $revenue = 0;
    $recent_orders = [];
}
?>

<div class="row">
    <div class="col-12">
        <h2>Admin Dashboard</h2>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $users_count; ?></h4>
                        <p class="mb-0">Total Users</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $products_count; ?></h4>
                        <p class="mb-0">Total Products</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-gamepad fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $orders_count; ?></h4>
                        <p class="mb-0">Total Orders</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-shopping-cart fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4>$<?php echo number_format($revenue, 2); ?></h4>
                        <p class="mb-0">Total Revenue</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-dollar-sign fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Orders</h5>
            </div>
            <div class="card-body">
                <?php if(count($recent_orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): 
                                    // Build customer name
                                    $first_name = $order['first_name'] ?? '';
                                    $last_name = $order['last_name'] ?? '';
                                    $customer_name = '';
                                    
                                    if (!empty(trim($first_name)) || !empty(trim($last_name))) {
                                        // If there is name information, display it in combination
                                        $customer_name = trim($first_name . ' ' . $last_name);
                                    } else {
                                        // If there is no name information, check if there is an email address
                                        if (!empty(trim($order['email'] ?? ''))) {
                                            $customer_name = $order['email'];
                                        } else {
                                            $customer_name = 'Customer #' . $order['user_id'];
                                        }
                                    }
                                ?>
                                <tr>
                                    <td>#<?php echo $order['order_id']; ?></td>
                                    <td><?php echo htmlspecialchars($customer_name); ?></td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
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
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="orders.php" class="btn btn-outline-primary">View All Orders</a>
                <?php else: ?>
                    <p class="text-muted">No orders found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="product_add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Product
                    </a>
                    <a href="summary_analytics.php" class="btn btn-outline-primary">
                        </i> Summary Analytics
                    </a>
                    <a href="products.php" class="btn btn-outline-primary">
                        <i class="fas fa-edit"></i> Manage Products
                    </a>
                    <a href="product_analytics.php" class="btn btn-outline-primary">
                        <i class="fas fa-edit"></i> Products Analytics
                    </a>
                    <a href="orders.php" class="btn btn-outline-primary">
                        <i class="fas fa-shopping-cart"></i> Manage Orders
                    </a>
                    <a href="order_analytics.php" class="btn btn-outline-primary">
                        <i class="fas fa-shopping-cart"></i> Order Analytics
                    </a>
                    <a href="users.php" class="btn btn-outline-primary">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                    <a href="user_analytics.php" class="btn btn-outline-primary">
                        <i class="fas fa-users"></i> Users Analytics
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">System Info</h5>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                </div>
                <div class="mb-2">
                    <strong>Database:</strong> <?php echo $db ? 'Connected' : 'Not Connected'; ?>
                </div>
                <div class="mb-2">
                    <strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
                </div>
                <div class="mb-2">
                    <strong>Logged in as:</strong> <?php echo $_SESSION['username']; ?>
                </div>
                <div class="mb-2">
                    <strong>Total Users:</strong> <?php echo $users_count; ?> (from wppw_fc_subscribers)
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>