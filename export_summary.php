<?php
require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

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

// Obtain product data
$products_data = getProductsFromJson($json_file_path);
$products = isset($products_data['products']) ? $products_data['products'] : array();

// Create a mapping from product ID to product information
$product_map = array();
foreach ($products as $product) {
    $product_map[$product['product_id']] = $product;
}

// Mapping from classification ID to name
$categories_map = array(
    3 => 'Gaming Gear',
    4 => 'Game Currency',
    6 => 'Battle Royale',
    7 => 'Collectibles',
    9 => 'CSGO Items'
);

// Set output as CSV file
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=summary_export_' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

fwrite($output, "\xEF\xBB\xBF");

// Get export type
$export_type = isset($_GET['type']) ? $_GET['type'] : 'full';

$database = new Database();
$db = $database->getConnection();

if ($db) {
    try {
        switch ($export_type) {
            case 'orders':
                // Export order data 
                fputcsv($output, ['Order ID', 'Customer ID', 'Amount', 'Status', 'Date', 'Items']);
                
                $orders_query = "
                    SELECT 
                        o.order_id,
                        o.user_id,
                        o.total_amount,
                        o.status,
                        o.created_at
                    FROM orders o
                    ORDER BY o.created_at DESC
                ";
                $orders_stmt = $db->query($orders_query);
                
                // Get order item information
                $order_items_query = "SELECT order_id, product_id, quantity FROM order_items";
                $order_items_stmt = $db->query($order_items_query);
                $order_items = array();
                while ($item = $order_items_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $order_id = $item['order_id'];
                    if (!isset($order_items[$order_id])) {
                        $order_items[$order_id] = array();
                    }
                    $order_items[$order_id][] = $item;
                }
                
                while ($order = $orders_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $order_id = $order['order_id'];
                    $items_description = '';
                    
                    if (isset($order_items[$order_id])) {
                        $item_descriptions = array();
                        foreach ($order_items[$order_id] as $item) {
                            $product_id = $item['product_id'];
                            $quantity = $item['quantity'];
                            
                            // Retrieve product name from JSON
                            $product_name = 'Product #' . $product_id;
                            if (isset($product_map[$product_id])) {
                                $product_name = $product_map[$product_id]['product_name'];
                            }
                            
                            $item_descriptions[] = $quantity . 'x ' . $product_name;
                        }
                        $items_description = implode(', ', $item_descriptions);
                    } else {
                        $items_description = 'No items';
                    }
                    
                    fputcsv($output, [
                        $order['order_id'],
                        $order['user_id'],
                        '$' . number_format($order['total_amount'], 2),
                        $order['status'],
                        $order['created_at'],
                        $items_description
                    ]);
                }
                break;
                
            case 'products':
                // Export product data (from JSON file)
                fputcsv($output, ['Product ID', 'Name', 'Category', 'Type', 'Price', 'Stock', 'Status', 'Supplier', 'Platform', 'Region', 'Discount %', 'Featured', 'Created Date']);
                
                if (!empty($products)) {
                    foreach ($products as $product) {
                        // Get category name
                        $category_name = isset($categories_map[$product['category_id']]) 
                            ? $categories_map[$product['category_id']] 
                            : 'Category ' . $product['category_id'];
                        
                        fputcsv($output, [
                            $product['product_id'],
                            $product['product_name'],
                            $category_name,
                            $product['product_type'],
                            '$' . number_format(floatval($product['price']), 2),
                            $product['stock_quantity'],
                            $product['is_active'] ? 'Active' : 'Inactive',
                            $product['supplier'] ?? 'N/A',
                            $product['platform'] ?? 'N/A',
                            $product['region'] ?? 'N/A',
                            $product['discount_percent'] ?? '0.00',
                            $product['is_featured'] ? 'Yes' : 'No',
                            $product['created_at']
                        ]);
                    }
                } else {
                    fputcsv($output, ['No products found in JSON file']);
                }
                break;
                
            case 'users':
                // Export user data (including churn information), sorted by user_id
                fputcsv($output, [
                    'customer_email', 
                    'months_as_customer', 
                    'order_count', 
                    'days_since_last_order', 
                    'churned',
                    'User ID',
                    'First Name', 
                    'Last Name', 
                    'Phone', 
                    'Status', 
                    'Registration Date', 
                    'Last Activity', 
                    'Total Spent ($)', 
                    'Last Order Date', 
                    'First Order Date'
                ]);
                
                $users_query = "
                    SELECT 
                        s.email as customer_email,
                        FLOOR(DATEDIFF(CURDATE(), s.created_at) / 30) as months_as_customer,
                        COALESCE(o.order_count, 0) as order_count,
                        COALESCE(o.days_since_last_order, DATEDIFF(CURDATE(), s.created_at)) as days_since_last_order,
                        CASE 
                            WHEN COALESCE(o.days_since_last_order, DATEDIFF(CURDATE(), s.created_at)) > 90 THEN '1'
                            ELSE '0'
                        END as churned,
                        s.id as user_id,
                        s.first_name,
                        s.last_name,
                        s.phone,
                        s.status,
                        s.created_at as registration_date,
                        s.last_activity,
                        COALESCE(o.total_spent, 0) as total_spent,
                        COALESCE(o.last_order_date, 'Never') as last_order_date,
                        COALESCE(o.first_order_date, 'Never') as first_order_date
                    FROM wppw_fc_subscribers s
                    LEFT JOIN (
                        SELECT 
                            user_id,
                            COUNT(*) as order_count,
                            SUM(total_amount) as total_spent,
                            DATEDIFF(CURDATE(), MAX(created_at)) as days_since_last_order,
                            MAX(created_at) as last_order_date,
                            MIN(created_at) as first_order_date
                        FROM orders 
                        WHERE status != 'cancelled'
                        GROUP BY user_id
                    ) o ON s.id = o.user_id
                    ORDER BY s.id ASC
                ";
                
                $users_stmt = $db->query($users_query);
                while ($row = $users_stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, [
                        $row['customer_email'],
                        $row['months_as_customer'],
                        $row['order_count'],
                        $row['days_since_last_order'],
                        $row['churned'],
                        $row['user_id'],
                        $row['first_name'] ?: 'N/A',
                        $row['last_name'] ?: 'N/A',
                        $row['phone'] ?: 'N/A',
                        $row['status'],
                        $row['registration_date'],
                        $row['last_activity'] ?: 'Never',
                        number_format($row['total_spent'], 2),
                        $row['last_order_date'],
                        $row['first_order_date']
                    ]);
                }
                break;
                
            case 'churn_data':
                // Specially export data in Churn.data.csv format
                fputcsv($output, [
                    'customer_email', 
                    'months_as_customer', 
                    'order_count', 
                    'days_since_last_order', 
                    'churned'
                ]);
                
                $churn_query = "
                    SELECT 
                        s.email as customer_email,
                        FLOOR(DATEDIFF(CURDATE(), s.created_at) / 30) as months_as_customer,
                        COALESCE(o.order_count, 0) as order_count,
                        COALESCE(o.days_since_last_order, DATEDIFF(CURDATE(), s.created_at)) as days_since_last_order,
                        CASE 
                            WHEN COALESCE(o.days_since_last_order, DATEDIFF(CURDATE(), s.created_at)) > 90 THEN '1'
                            ELSE '0'
                        END as churned
                    FROM wppw_fc_subscribers s
                    LEFT JOIN (
                        SELECT 
                            user_id,
                            COUNT(*) as order_count,
                            DATEDIFF(CURDATE(), MAX(created_at)) as days_since_last_order
                        FROM orders 
                        WHERE status != 'cancelled'
                        GROUP BY user_id
                    ) o ON s.id = o.user_id
                    ORDER BY s.email ASC
                ";
                
                $churn_stmt = $db->query($churn_query);
                while ($row = $churn_stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, [
                        $row['customer_email'],
                        $row['months_as_customer'],
                        $row['order_count'],
                        $row['days_since_last_order'],
                        $row['churned']
                    ]);
                }
                break;
                
            default:
                // Export complete summary
                fputcsv($output, ['Metric', 'Value', 'Description']);
                
                // Retrieve core statistical data from the database
                $core_query = "
                    SELECT 
                        (SELECT COUNT(*) FROM wppw_fc_subscribers) as total_users,
                        (SELECT COUNT(*) FROM orders) as total_orders,
                        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed') as total_revenue,
                        (SELECT COUNT(*) FROM orders WHERE status = 'pending') as pending_orders,
                        (SELECT COUNT(*) FROM orders WHERE status = 'completed') as completed_orders,
                        (SELECT COUNT(DISTINCT user_id) FROM orders) as active_customers,
                        (SELECT COUNT(*) FROM wppw_fc_subscribers WHERE DATE(created_at) = CURDATE()) as new_users_today,
                        (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()) as new_orders_today,
                        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed') as today_revenue,
                        (SELECT COUNT(*) FROM wppw_fc_subscribers WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)) as new_users_week,
                        (SELECT COUNT(*) FROM orders WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)) as new_orders_week,
                        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) AND status = 'completed') as week_revenue,
                        (SELECT AVG(total_amount) FROM orders WHERE status = 'completed') as avg_order_value,
                        (SELECT COUNT(*) FROM categories) as total_categories
                ";
                $core_stmt = $db->query($core_query);
                $core_data = $core_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Calculate product statistics from JSON
                $active_products_count = 0;
                $low_stock_count_json = 0;
                
                if (!empty($products)) {
                    // Calculate the number of active products
                    $active_products = array_filter($products, function($product) {
                        return isset($product['is_active']) && $product['is_active'] == 1;
                    });
                    
                    $active_products_count = count($active_products);
                    
                    // Calculate the number of low inventory items (inventory ≤ 5)
                    foreach ($active_products as $product) {
                        $stock_quantity = intval($product['stock_quantity']);
                        if ($stock_quantity <= 5) {
                            $low_stock_count_json++;
                        }
                    }
                }
                
                // Add core statistical data
                $metrics = [
                    ['Total Users', $core_data['total_users'], 'Total number of registered users'],
                    ['Total Products', $active_products_count, 'Active products in the store'],
                    ['Total Orders', $core_data['total_orders'], 'All orders placed'],
                    ['Total Revenue', '$' . number_format($core_data['total_revenue'], 2), 'Revenue from completed orders'],
                    ['Pending Orders', $core_data['pending_orders'], 'Orders waiting for processing'],
                    ['Completed Orders', $core_data['completed_orders'], 'Successfully completed orders'],
                    ['Low Stock Products', $low_stock_count_json, 'Products with 5 or less items in stock'],
                    ['Active Customers', $core_data['active_customers'], 'Users who have placed at least one order'],
                    ['New Users Today', $core_data['new_users_today'], 'Users registered today'],
                    ['New Orders Today', $core_data['new_orders_today'], 'Orders placed today'],
                    ['Today\'s Revenue', '$' . number_format($core_data['today_revenue'], 2), 'Revenue from today\'s orders'],
                    ['New Users This Week', $core_data['new_users_week'], 'Users registered this week'],
                    ['New Orders This Week', $core_data['new_orders_week'], 'Orders placed this week'],
                    ['Weekly Revenue', '$' . number_format($core_data['week_revenue'], 2), 'Revenue from this week\'s orders'],
                    ['Average Order Value', '$' . number_format($core_data['avg_order_value'], 2), 'Average amount per completed order'],
                    ['Product Categories', $core_data['total_categories'], 'Number of product categories']
                ];
                
                foreach ($metrics as $metric) {
                    fputcsv($output, $metric);
                }
                
                // Add blank lines
                fputcsv($output, []);
                fputcsv($output, ['Top Selling Products', 'Units Sold', 'Product ID']);
                
                // Get best-selling products
                $top_products_query = "
                    SELECT 
                        oi.product_id,
                        SUM(oi.quantity) as total_sold
                    FROM order_items oi
                    INNER JOIN orders o ON oi.order_id = o.order_id AND o.status = 'completed'
                    GROUP BY oi.product_id
                    ORDER BY total_sold DESC
                    LIMIT 10
                ";
                $top_products_stmt = $db->query($top_products_query);
                while ($product_data = $top_products_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $product_id = $product_data['product_id'];
                    $total_sold = $product_data['total_sold'];
                    
                    $product_name = 'Product #' . $product_id . ' (Not Found in JSON)';
                    if (isset($product_map[$product_id])) {
                        $product_name = $product_map[$product_id]['product_name'];
                    }
                    
                    fputcsv($output, [
                        $product_name,
                        $total_sold,
                        $product_id
                    ]);
                }

                fputcsv($output, []);
                fputcsv($output, ['Product List from JSON File (Sample - First 10)', '', '']);
                fputcsv($output, ['Product ID', 'Name', 'Price', 'Stock', 'Type', 'Status']);
                
                // Display the top 10 products as examples
                $sample_products = array_slice($products, 0, 10);
                foreach ($sample_products as $product) {
                    fputcsv($output, [
                        $product['product_id'],
                        $product['product_name'],
                        '$' . number_format(floatval($product['price']), 2),
                        $product['stock_quantity'],
                        $product['product_type'],
                        $product['is_active'] ? 'Active' : 'Inactive'
                    ]);
                }
                
                // Add data source description
                fputcsv($output, []);
                fputcsv($output, ['Data Sources:']);
                fputcsv($output, ['Database:', 'Users, Orders, Sales Data, Categories']);
                fputcsv($output, ['JSON File:', 'Product Information, Stock Levels, Product Names']);
                fputcsv($output, ['Export Generated:', date('Y-m-d H:i:s')]);
                fputcsv($output, ['Note:', 'Product information sourced from JSON file, sales data from database']);
                break;
        }
        
    } catch (PDOException $e) {
        // If there is an error, output an error message
        fputcsv($output, ['Error', 'Failed to generate export: ' . $e->getMessage()]);
    }
} else {
    fputcsv($output, ['Error', 'Database connection failed']);
}

fclose($output);
exit;
?>