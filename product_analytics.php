<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

//Read product data from JSON file
$json_file_path = __DIR__ . '/../data/products.json';

function getProductsFromJson($file_path) {
    if (!file_exists($file_path)) {
        error_log("JSON file does not exist at: " . $file_path);
        return array('error' => 'Products JSON file not found at: ' . basename($file_path));
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

// Read product data from JSON file
$products_data = getProductsFromJson($json_file_path);
$products = isset($products_data['products']) ? $products_data['products'] : array();

//Create a mapping from product ID to product information
$product_map = array();
foreach ($products as $product) {
    $product_map[$product['product_id']] = $product;
}

//Mapping from classification ID to name
$categories_map = array(
    3 => 'Gaming Gear',
    4 => 'Game Currency',
    6 => 'Battle Royale',
    7 => 'Collectibles',
    9 => 'CSGO Items'
);

//Calculate product statistics from JSON
$total_products = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;

$category_stats = array();
$product_type_stats = array();
$low_stock_products = array();
$recent_products = array();

//Retrieve sales data from the database
$database = new Database();
$db = $database->getConnection();

//Sales related variables
$total_revenue = 0;
$top_selling_products = array();

if (!isset($products_data['error']) && !empty($products)) {
    //Calculate product statistics from JSON
    $active_products = array_filter($products, function($product) {
        return $product['is_active'] == 1;
    });
    
    $total_products = count($active_products);
    
    // Calculate inventory statistics
    foreach ($active_products as $product) {
        $stock_quantity = intval($product['stock_quantity']);
        
        if ($stock_quantity <= 5) {
            $low_stock_count++;
        }
        
        if ($stock_quantity == 0) {
            $out_of_stock_count++;
        }
        
        // List of low inventory products
        if ($stock_quantity > 0 && $stock_quantity <= 10) {
            $category_name = isset($categories_map[$product['category_id']]) 
                ? $categories_map[$product['category_id']] 
                : 'Category ' . $product['category_id'];
            
            $low_stock_products[] = array(
                'product_id' => $product['product_id'],
                'product_name' => $product['product_name'],
                'product_type' => $product['product_type'],
                'category_id' => $product['category_id'],
                'price' => floatval($product['price']),
                'stock_quantity' => $stock_quantity,
                'category_name' => $category_name
            );
        }
    }
    
    // Sort low inventory products by inventory
    usort($low_stock_products, function($a, $b) {
        return $a['stock_quantity'] - $b['stock_quantity'];
    });
    
    $low_stock_products = array_slice($low_stock_products, 0, 10);
    
    // classified statistics
    $category_counts = array();
    $category_stock = array();
    $category_prices = array();
    
    foreach ($active_products as $product) {
        $category_id = $product['category_id'];
        $price = floatval($product['price']);
        $stock = intval($product['stock_quantity']);
        
        if (!isset($category_counts[$category_id])) {
            $category_counts[$category_id] = 0;
            $category_stock[$category_id] = 0;
            $category_prices[$category_id] = array();
        }
        
        $category_counts[$category_id]++;
        $category_stock[$category_id] += $stock;
        $category_prices[$category_id][] = $price;
    }
    
    foreach ($category_counts as $category_id => $count) {
        $avg_price = 0;
        if (isset($category_prices[$category_id]) && count($category_prices[$category_id]) > 0) {
            $avg_price = array_sum($category_prices[$category_id]) / count($category_prices[$category_id]);
        }
        
        $category_stats[] = array(
            'category_name' => isset($categories_map[$category_id]) 
                ? $categories_map[$category_id] 
                : 'Category ' . $category_id,
            'product_count' => $count,
            'total_stock' => $category_stock[$category_id],
            'avg_price' => $avg_price
        );
    }
    
    usort($category_stats, function($a, $b) {
        return $b['product_count'] - $a['product_count'];
    });
    
    // Product type statistics
    $type_counts = array();
    $type_stock = array();
    $type_prices = array();
    $type_values = array();
    
    foreach ($active_products as $product) {
        $product_type = $product['product_type'];
        $price = floatval($product['price']);
        $stock = intval($product['stock_quantity']);
        $value = $price * $stock;
        
        if (!isset($type_counts[$product_type])) {
            $type_counts[$product_type] = 0;
            $type_stock[$product_type] = 0;
            $type_prices[$product_type] = array();
            $type_values[$product_type] = 0;
        }
        
        $type_counts[$product_type]++;
        $type_stock[$product_type] += $stock;
        $type_prices[$product_type][] = $price;
        $type_values[$product_type] += $value;
    }
    
    foreach ($type_counts as $product_type => $count) {
        $avg_price = 0;
        if (isset($type_prices[$product_type]) && count($type_prices[$product_type]) > 0) {
            $avg_price = array_sum($type_prices[$product_type]) / count($type_prices[$product_type]);
        }
        
        $product_type_stats[] = array(
            'product_type' => $product_type,
            'count' => $count,
            'total_stock' => $type_stock[$product_type],
            'avg_price' => $avg_price,
            'total_value' => $type_values[$product_type]
        );
    }
    
    usort($product_type_stats, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    // Total categories
    $category_ids = array_unique(array_column($active_products, 'category_id'));
    $total_categories = count($category_ids);
    
    // Recently added products
    $recent_products_all = $active_products;
    usort($recent_products_all, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    $recent_products = array_slice($recent_products_all, 0, 5);
    
    $formatted_recent_products = array();
    foreach ($recent_products as $product) {
        $formatted_recent_products[] = array(
            'product_id' => $product['product_id'],
            'product_name' => $product['product_name'],
            'product_type' => $product['product_type'],
            'price' => floatval($product['price']),
            'stock_quantity' => intval($product['stock_quantity']),
            'created_at' => $product['created_at']
        );
    }
    $recent_products = $formatted_recent_products;
}

//Retrieve sales data from the database
if ($db) {
    try {
        // Total revenue
        $revenue_query = "
            SELECT 
                SUM(oi.price_at_purchase * oi.quantity) as total_revenue
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.order_id
            WHERE o.status = 'completed'
        ";
        $revenue_stmt = $db->query($revenue_query);
        $revenue_data = $revenue_stmt->fetch(PDO::FETCH_ASSOC);
        $total_revenue = $revenue_data['total_revenue'] ?? 0;
        
        // Top selling products
        $top_selling_query = "
            SELECT 
                oi.product_id,
                SUM(oi.quantity) as total_sold,
                SUM(oi.price_at_purchase * oi.quantity) as total_revenue
            FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.order_id
            WHERE o.status = 'completed'
            GROUP BY oi.product_id
            ORDER BY total_sold DESC
            LIMIT 10
        ";
        $top_selling_stmt = $db->query($top_selling_query);
        $top_selling_data = $top_selling_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add product information obtained from JSON for each best-selling product
        foreach ($top_selling_data as $item) {
            $product_id = $item['product_id'];
            
            if (isset($product_map[$product_id])) {
                $product_info = $product_map[$product_id];
                $top_selling_products[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product_info['product_name'],
                    'product_type' => $product_info['product_type'],
                    'price' => floatval($product_info['price']),
                    'stock_quantity' => intval($product_info['stock_quantity']),
                    'total_sold' => $item['total_sold'],
                    'total_revenue' => $item['total_revenue']
                );
            } else {
                // If the product cannot be found in the JSON file, use the default value
                $top_selling_products[] = array(
                    'product_id' => $product_id,
                    'product_name' => 'Product #' . $product_id . ' (Not Found in JSON)',
                    'product_type' => 'unknown',
                    'price' => 0,
                    'stock_quantity' => 0,
                    'total_sold' => $item['total_sold'],
                    'total_revenue' => $item['total_revenue']
                );
            }
        }
        
    } catch (PDOException $e) {
        error_log("Database error in product analytics (sales data): " . $e->getMessage());
        // If database error, sales data remains 0/empty
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Analytics - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            border-radius: 10px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        .badge-type-cdk { background-color: #17a2b8; }
        .badge-type-physical { background-color: #007bff; }
        .badge-type-virtual { background-color: #28a745; }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-danger { background-color: #f8d7da; }
        .table-warning { background-color: #fff3cd; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2">Product Analytics</h1>
                <p class="text-muted">Product inventory and category analysis report</p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                    </a>
                    <a href="products.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-list"></i> Manage Products
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($products_data['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($products_data['error']); ?></div>
        <?php endif; ?>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $total_products; ?></h4>
                                <p class="mb-0">Active Products</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-box fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $total_categories; ?></h4>
                                <p class="mb-0">Product Categories</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tags fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>$<?php echo number_format($total_revenue, 2); ?></h4>
                                <p class="mb-0">Total Revenue</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-dollar-sign fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $low_stock_count; ?></h4>
                                <p class="mb-0">Low Stock Items (≤5)</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left: Category Stats -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Category Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Category Name</th>
                                        <th>Product Count</th>
                                        <th>Total Stock</th>
                                        <th>Average Price</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($category_stats as $category): 
                                        $percentage = $total_products > 0 ? ($category['product_count'] / $total_products * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($category['category_name']); ?></strong></td>
                                        <td><?php echo $category['product_count']; ?></td>
                                        <td><?php echo $category['total_stock']; ?></td>
                                        <td>$<?php echo number_format($category['avg_price'], 2); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div class="progress-bar bg-info" role="progressbar" 
                                                         style="width: <?php echo $percentage; ?>%" 
                                                         aria-valuenow="<?php echo $percentage; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100"></div>
                                                </div>
                                                <span><?php echo number_format($percentage, 1); ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top Selling Products -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Top Selling Products</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($top_selling_products) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Product Name</th>
                                            <th>Type</th>
                                            <th>Quantity Sold</th>
                                            <th>Revenue</th>
                                            <th>Current Stock</th>
                                            <th>Current Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_selling_products as $index => $product): ?>
                                        <tr>
                                            <td><span class="badge bg-primary">#<?php echo $index + 1; ?></span></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                                <?php if (strpos($product['product_name'], 'Not Found in JSON') !== false): ?>
                                                    <br><small class="text-danger">Product data loaded from JSON file</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-type-<?php echo $product['product_type']; ?>">
                                                    <?php echo strtoupper($product['product_type']); ?>
                                                </span>
                                            </td>
                                            <td><span class="badge bg-success"><?php echo $product['total_sold']; ?></span></td>
                                            <td><strong>$<?php echo number_format($product['total_revenue'], 2); ?></strong></td>
                                            <td><?php echo $product['stock_quantity']; ?></td>
                                            <td>$<?php echo number_format($product['price'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No sales data available in database</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Product Type Stats and Low Stock Alerts -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Product Type Statistics</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($product_type_stats as $type): 
                            $type_percentage = $total_products > 0 ? ($type['count'] / $total_products * 100) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>
                                    <span class="badge badge-type-<?php echo $type['product_type']; ?> me-2">
                                        <?php echo strtoupper($type['product_type']); ?>
                                    </span>
                                    <strong><?php echo $type['count']; ?> products</strong>
                                </span>
                                <span><?php echo number_format($type_percentage, 1); ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar 
                                    <?php 
                                    switch($type['product_type']) {
                                        case 'cdk': echo 'bg-info'; break;
                                        case 'physical': echo 'bg-primary'; break;
                                        case 'virtual': echo 'bg-success'; break;
                                        default: echo 'bg-secondary';
                                    }
                                    ?>" 
                                    role="progressbar" 
                                    style="width: <?php echo $type_percentage; ?>%">
                                </div>
                            </div>
                            <div class="text-muted small mt-1">
                                Stock: <?php echo $type['total_stock']; ?> | 
                                Avg Price: $<?php echo number_format($type['avg_price'], 2); ?> |
                                Total Value: $<?php echo number_format($type['total_value'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Low Stock Warnings -->
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0 text-white">Low Stock Alerts (≤10 items)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($low_stock_products) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product Name</th>
                                            <th>Category</th>
                                            <th>Stock</th>
                                            <th>Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($low_stock_products as $product): 
                                            $stock_class = $product['stock_quantity'] <= 3 ? 'danger' : 'warning';
                                        ?>
                                        <tr>
                                            <td>
                                                <small><?php echo htmlspecialchars($product['product_name']); ?></small>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $stock_class; ?>">
                                                    <?php echo $product['stock_quantity']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>$<?php echo number_format($product['price'], 2); ?></small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="products.php" class="btn btn-sm btn-warning w-100">
                                <i class="fas fa-exclamation-circle"></i> View All Low Stock Products
                            </a>
                        <?php else: ?>
                            <p class="text-success text-center">
                                <i class="fas fa-check-circle"></i><br>
                                No low stock products (≤10 items)
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recently Added Products -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recently Added Products</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_products) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($recent_products as $product): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo date('m-d', strtotime($product['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">
                                            <span class="badge badge-type-<?php echo $product['product_type']; ?>">
                                                <?php echo strtoupper($product['product_type']); ?>
                                            </span>
                                        </small>
                                        <div>
                                            <small class="text-success me-2">
                                                $<?php echo number_format($product['price'], 2); ?>
                                            </small>
                                            <small class="text-info">
                                                Stock: <?php echo $product['stock_quantity']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No recently added products</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Status Summary -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Stock Status Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="p-3 border rounded">
                                    <h3 class="text-primary"><?php echo $total_products; ?></h3>
                                    <p class="mb-0">Total Active Products</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 border rounded">
                                    <h3 class="text-success"><?php echo $total_products - $out_of_stock_count; ?></h3>
                                    <p class="mb-0">In Stock Products</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 border rounded">
                                    <h3 class="text-warning"><?php echo $low_stock_count; ?></h3>
                                    <p class="mb-0">Low Stock (≤5)</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3 border rounded">
                                    <h3 class="text-danger"><?php echo $out_of_stock_count; ?></h3>
                                    <p class="mb-0">Out of Stock</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Update Time -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-info-circle"></i>
                            <strong>Data Sources:</strong> 
                            Updated at: <?php echo date('Y-m-d H:i:s'); ?>
                        </div>
                        <div>
                            <a href="product_analytics.php" class="btn btn-sm btn-info">
                                <i class="fas fa-sync-alt"></i> Refresh Data
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Highlight low stock products after page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Add warning styles to low stock product rows
            document.querySelectorAll('tbody tr').forEach(row => {
                const stockTd = row.querySelector('td:nth-child(6)'); // Stock column
                if (stockTd) {
                    const stock = parseInt(stockTd.textContent);
                    if (stock <= 3) {
                        row.classList.add('table-danger');
                    } else if (stock <= 10) {
                        row.classList.add('table-warning');
                    }
                }
            });
        });
    </script>
</body>
</html>