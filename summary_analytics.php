<?php

date_default_timezone_set('Asia/Shanghai');

$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

// Set cache directory and file
$cache_dir = '../cache/';
$cache_file = $cache_dir . 'summary_cache.json';
$cache_time = 300; 

// Check if a force refresh was requested
$force_refresh = isset($_GET['refresh']) && $_GET['refresh'] == '1';

// Check if the cache directory exists; create it if missing
if (!file_exists($cache_dir)) {
    if (!mkdir($cache_dir, 0755, true)) {
        error_log("Failed to create cache directory: " . $cache_dir);
        $cache_error = "Unable to create cache directory. Please check directory permissions.";
    }
}

// Check whether data is being loaded from cache
$from_cache = false;
$cache_created = null;

// Check if cache exists and is still valid (not expired)
if (file_exists($cache_file) && !$force_refresh && (time() - filemtime($cache_file)) < $cache_time) {
    $data = json_decode(file_get_contents($cache_file), true);
    $from_cache = true;
    $cache_created = date('Y-m-d H:i:s', filemtime($cache_file));
} else {
    $database = new Database();
    $db = $database->getConnection();

    if ($db) {
    $db->exec("SET time_zone = '+08:00'");
    }
    
    $data = [];
    
    // Read product data from JSON file
    $json_file_path = __DIR__ . '/../data/products.json';
    
    // Read data from JSON file
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
    
    // Get products data
    $products_data = getProductsFromJson($json_file_path);
    $products = isset($products_data['products']) ? $products_data['products'] : array();
    
    // Build product ID -> product info map
    $product_map = array();
    foreach ($products as $product) {
        $product_map[$product['product_id']] = $product;
    }
    
    // Compute product statistics from JSON
    $active_products_count = 0;
    $low_stock_count_json = 0;
    $out_of_stock_count_json = 0;
    $total_products_json = 0;
    
    if (!isset($products_data['error']) && !empty($products)) {
        // Count active products
        $active_products = array_filter($products, function($product) {
            return isset($product['is_active']) && $product['is_active'] == 1;
        });
        
        $active_products_count = count($active_products);
        
        // Count low-stock products (stock ≤ 5)
        foreach ($active_products as $product) {
            $stock_quantity = intval($product['stock_quantity']);
            
            if ($stock_quantity <= 5) {
                $low_stock_count_json++;
            }
            
            if ($stock_quantity == 0) {
                $out_of_stock_count_json++;
            }
        }
        
        $total_products_json = count($products);
    }
    
    if ($db) {
        try {
            // 1. Fast retrieval of core statistics (using optimized queries)
            $core_query = "
                SELECT 
                    (SELECT COUNT(*) FROM wppw_fc_subscribers) as total_users,
                    (SELECT COUNT(*) FROM orders) as total_orders,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed') as total_revenue,
                    (SELECT COUNT(*) FROM orders WHERE status = 'pending') as pending_orders,
                    (SELECT COUNT(*) FROM orders WHERE status = 'completed') as completed_orders,
                    (SELECT COUNT(DISTINCT user_id) FROM orders) as active_customers
            ";
            $core_stmt = $db->query($core_query);
            $core_data = $core_stmt->fetch(PDO::FETCH_ASSOC);
            
            // 2. Today's data
            $today_query = "
                SELECT 
                    (SELECT COUNT(*) FROM wppw_fc_subscribers 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as new_users_today,
                    (SELECT COUNT(*) FROM orders 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as new_orders_today,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM orders 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                    AND status = 'completed') as today_revenue
            ";
            $today_stmt = $db->query($today_query);
            $today_data = $today_stmt->fetch(PDO::FETCH_ASSOC);
            
            // 3. This week's data
            $week_query = "
                SELECT 
                    (SELECT COUNT(*) FROM wppw_fc_subscribers WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)) as new_users_week,
                    (SELECT COUNT(*) FROM orders WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)) as new_orders_week,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) AND status = 'completed') as week_revenue
            ";
            $week_stmt = $db->query($week_query);
            $week_data = $week_stmt->fetch(PDO::FETCH_ASSOC);
            
            // 4. Top products
            $top_products_query = "
                SELECT 
                    oi.product_id,
                    SUM(oi.quantity) as total_sold
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.order_id AND o.status = 'completed'
                GROUP BY oi.product_id
                ORDER BY total_sold DESC
                LIMIT 5
            ";
            $top_products_stmt = $db->query($top_products_query);
            $top_products_raw = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);
            
                    // Process top-selling products, enrich with info from JSON
            $top_products = array();
            foreach ($top_products_raw as $item) {
                $product_id = $item['product_id'];
                
                if (isset($product_map[$product_id])) {
                    $product_info = $product_map[$product_id];
                    $top_products[] = array(
                        'product_id' => $product_id,
                        'product_name' => $product_info['product_name'],
                        'product_type' => $product_info['product_type'],
                        'total_sold' => $item['total_sold']
                    );
                } else {
                    // If product not found in JSON, use default values
                    $top_products[] = array(
                        'product_id' => $product_id,
                        'product_name' => 'Product #' . $product_id . ' (Not Found in JSON)',
                        'product_type' => 'unknown',
                        'total_sold' => $item['total_sold']
                    );
                }
            }
            
            // 5. Recent orders
            $recent_orders_query = "
                SELECT 
                    o.order_id,
                    o.total_amount,
                    o.status,
                    o.created_at,
                    COALESCE(s.first_name, 'Customer') as customer_name
                FROM orders o
                LEFT JOIN wppw_fc_subscribers s ON o.user_id = s.id
                ORDER BY o.created_at DESC
                LIMIT 5
            ";
            $recent_orders_stmt = $db->query($recent_orders_query);
            $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 6. System status
            $system_query = "
                SELECT 
                    (SELECT COUNT(*) FROM orders WHERE status = 'processing') as processing_orders,
                    (SELECT AVG(total_amount) FROM orders WHERE status = 'completed') as avg_order_value,
                    (SELECT COUNT(*) FROM categories) as total_categories
            ";
            $system_stmt = $db->query($system_query);
            $system_data = $system_stmt->fetch(PDO::FETCH_ASSOC);

            $data = array_merge($core_data, $today_data, $week_data, $system_data);

            $data['total_products'] = $active_products_count;
            $data['low_stock_products'] = $low_stock_count_json;
            $data['out_of_stock'] = $out_of_stock_count_json;
            
            $data['top_products'] = $top_products;
            $data['recent_orders'] = $recent_orders;
            $data['cache_time'] = date('Y-m-d H:i:s');
            
            // Save to cache (if cache directory exists and is writable)
            if (file_exists($cache_dir) && is_writable($cache_dir)) {
                file_put_contents($cache_file, json_encode($data));
            }
            
            $from_cache = false;
            $cache_created = $data['cache_time'];
            
        } catch (PDOException $e) {
            error_log("Database error in summary analytics: " . $e->getMessage());
            $error_message = "Failed to load data. Please try again.";
        }
    } else {
        $error_message = "Database connection failed.";
    }
}

// If loaded from cache, ensure data exists
if (isset($from_cache) && $from_cache && empty($data)) {
    if (file_exists($cache_file)) {
        unlink($cache_file);
    }
    header("Location: summary_analytics.php?refresh=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary Analytics - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            border-radius: 10px;
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .stat-value {
            font-size: 2.2rem;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .quick-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .table-sm td, .table-sm th {
            padding: 0.5rem;
        }
        .badge-status-pending { background-color: #ffc107; }
        .badge-status-processing { background-color: #17a2b8; }
        .badge-status-completed { background-color: #28a745; }
        .cache-notice {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        .hover-effect:hover {
            background-color: rgba(0,0,0,0.02);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,0.125);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2">Summary Analytics</h1>
                <p class="text-muted">Quick overview of your store performance</p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    <a href="summary_analytics.php?refresh=1" class="btn btn-sm btn-outline-warning">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </a>
                    <a href="export_summary.php" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($cache_error)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?php echo $cache_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Cache Notice -->
        <?php if (isset($from_cache) && $from_cache): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-bolt me-1"></i> 
            Displaying cached data from <?php echo $cache_created; ?> 
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Quick Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-value"><?php echo number_format($data['total_users'] ?? 0); ?></div>
                                <div class="stat-label">Total Users</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="mt-2 text-white-50 small">
                            <i class="fas fa-user-plus me-1"></i> +<?php echo number_format($data['new_users_today'] ?? 0); ?> today
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-value"><?php echo number_format($data['total_orders'] ?? 0); ?></div>
                                <div class="stat-label">Total Orders</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                        </div>
                        <div class="mt-2 text-white-50 small">
                            <i class="fas fa-calendar-day me-1"></i> +<?php echo number_format($data['new_orders_today'] ?? 0); ?> today
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-value">$<?php echo number_format($data['total_revenue'] ?? 0, 2); ?></div>
                                <div class="stat-label">Total Revenue</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                        <div class="mt-2 text-white-50 small">
                            <i class="fas fa-calendar-week me-1"></i> $<?php echo number_format($data['week_revenue'] ?? 0, 2); ?> this week
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-purple stat-card" style="background-color: #6f42c1;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-value"><?php echo number_format($data['total_products'] ?? 0); ?></div>
                                <div class="stat-label">Active Products</div>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-box"></i>
                            </div>
                        </div>
                        <div class="mt-2 text-white-50 small">
                            <i class="fas fa-exclamation-triangle me-1"></i> <?php echo number_format($data['low_stock_products'] ?? 0); ?> low stock
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: System Stats -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">System Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body text-center">
                                        <div class="text-primary mb-2">
                                            <i class="fas fa-check-circle fa-2x"></i>
                                        </div>
                                        <h4 class="mb-1"><?php echo number_format($data['completed_orders'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0">Completed Orders</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body text-center">
                                        <div class="text-warning mb-2">
                                            <i class="fas fa-clock fa-2x"></i>
                                        </div>
                                        <h4 class="mb-1"><?php echo number_format($data['pending_orders'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0">Pending Orders</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body text-center">
                                        <div class="text-info mb-2">
                                            <i class="fas fa-cogs fa-2x"></i>
                                        </div>
                                        <h4 class="mb-1"><?php echo number_format($data['processing_orders'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0">Processing</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light h-100">
                                    <div class="card-body text-center">
                                        <div class="text-danger mb-2">
                                            <i class="fas fa-ban fa-2x"></i>
                                        </div>
                                        <h4 class="mb-1"><?php echo number_format($data['out_of_stock'] ?? 0); ?></h4>
                                        <p class="text-muted mb-0">Out of Stock</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <table class="table table-sm">
                                <tr class="hover-effect">
                                    <td><strong>Average Order Value</strong></td>
                                    <td class="text-end">$<?php echo number_format($data['avg_order_value'] ?? 0, 2); ?></td>
                                </tr>
                                <tr class="hover-effect">
                                    <td><strong>Active Customers</strong></td>
                                    <td class="text-end"><?php echo number_format($data['active_customers'] ?? 0); ?></td>
                                </tr>
                                <tr class="hover-effect">
                                    <td><strong>Product Categories</strong></td>
                                    <td class="text-end"><?php echo number_format($data['total_categories'] ?? 0); ?></td>
                                </tr>
                                <tr class="hover-effect">
                                    <td><strong>New Users This Week</strong></td>
                                    <td class="text-end"><?php echo number_format($data['new_users_week'] ?? 0); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top Products -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Top Selling Products</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($data['top_products'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Type</th>
                                            <th class="text-end">Units Sold</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['top_products'] as $product): ?>
                                        <tr class="hover-effect">
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                                <?php if (strpos($product['product_name'], 'Not Found in JSON') !== false): ?>
                                                    <br><small class="text-danger">Product data loaded from JSON file</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($product['product_type']) {
                                                        case 'cdk': echo 'bg-info'; break;
                                                        case 'physical': echo 'bg-primary'; break;
                                                        case 'virtual': echo 'bg-success'; break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($product['product_type']); ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <strong><?php echo number_format($product['total_sold']); ?></strong>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No product sales data available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Recent Orders and Quick Actions -->
            <div class="col-md-6">
                <!-- Recent Orders -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Orders</h5>
                            <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($data['recent_orders'])): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th class="text-end">Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['recent_orders'] as $order): ?>
                                        <tr class="hover-effect">
                                            <td>
                                                <strong>#<?php echo $order['order_id']; ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo date('M j', strtotime($order['created_at'])); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars(substr($order['customer_name'], 0, 20)); ?>...</td>
                                            <td class="text-end">
                                                <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($order['status']) {
                                                        case 'pending': echo 'bg-warning'; break;
                                                        case 'processing': echo 'bg-info'; break;
                                                        case 'completed': echo 'bg-success'; break;
                                                        default: echo 'bg-secondary';
                                                    }
                                                    ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No recent orders available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="product_add.php" class="btn btn-outline-primary w-100 d-flex align-items-center">
                                    <i class="fas fa-plus-circle me-2"></i>
                                    Add Product
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="products.php" class="btn btn-outline-success w-100 d-flex align-items-center">
                                    <i class="fas fa-edit me-2"></i>
                                    Manage Products
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="orders.php" class="btn btn-outline-info w-100 d-flex align-items-center">
                                    <i class="fas fa-shopping-cart me-2"></i>
                                    View Orders
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="users.php" class="btn btn-outline-warning w-100 d-flex align-items-center">
                                    <i class="fas fa-users me-2"></i>
                                    Manage Users
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daily Stats -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Today's Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3">
                                    <div class="text-success">
                                        <i class="fas fa-user-plus fa-2x mb-2"></i>
                                    </div>
                                    <h4 class="mb-1"><?php echo number_format($data['new_users_today'] ?? 0); ?></h4>
                                    <p class="text-muted mb-0">New Users</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3">
                                    <div class="text-primary">
                                        <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                    </div>
                                    <h4 class="mb-1"><?php echo number_format($data['new_orders_today'] ?? 0); ?></h4>
                                    <p class="text-muted mb-0">New Orders</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3">
                                    <div class="text-info">
                                        <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                                    </div>
                                    <h4 class="mb-1">$<?php echo number_format($data['today_revenue'] ?? 0, 2); ?></h4>
                                    <p class="text-muted mb-0">Today's Revenue</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cache Information -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-light border">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-info-circle text-primary me-1"></i>
                            <small class="text-muted">
                                <strong>Data Sources:</strong> 
                                Updated at: <?php echo $data['cache_time'] ?? 'N/A'; ?>
                                <?php if (isset($from_cache) && $from_cache): ?>
                                    <span class="badge bg-info ms-2">Cached (Refreshes every 5 minutes)</span>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div>
                            <a href="summary_analytics.php?refresh=1" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-sync-alt"></i> Force Refresh
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Options -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Export Data</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="export_summary.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-file-csv me-2"></i> Export Full Summary
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="export_summary.php?type=orders" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-shopping-cart me-2"></i> Export Orders
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="export_summary.php?type=products" class="btn btn-outline-info w-100">
                                    <i class="fas fa-box me-2"></i> Export Products
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="export_summary.php?type=users" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-users me-2"></i> Export Users
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="export_summary.php?type=churn_data" class="btn btn-outline-danger w-100">
                                    <i class="fas fa-user-times me-2"></i> Export Churn Data
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add loading animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add card hover effect
            const cards = document.querySelectorAll('.stat-card, .card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.cursor = 'pointer';
                });
            });

            // Auto-refresh cache (optional)
            const refreshInterval = 300000; // 5 minutes   
            setTimeout(() => {
                const refreshBtn = document.querySelector('a[href*="refresh=1"]');
                if (refreshBtn) {
                    // Display refresh prompt
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-warning alert-dismissible fade show cache-notice';
                    alert.innerHTML = `
                        <i class="fas fa-clock me-1"></i> 
                        Cache is 5 minutes old. <a href="${refreshBtn.href}" class="alert-link">Refresh now</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(alert);
                }
            }, refreshInterval);
        });
    </script>
</body>
</html>