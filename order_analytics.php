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

// Initialize variables
$total_orders = 0;
$total_revenue = 0;
$average_order_value = 0;
$pending_orders = 0;
$completed_orders = 0;
$cancelled_orders = 0;

$daily_stats = [];
$monthly_stats = [];
$customer_stats = [];
$status_stats = [];
$recent_orders = [];
$top_customers = [];

// Set time period (default: last 30 days)
$period_days = isset($_GET['period']) ? intval($_GET['period']) : 30;
$start_date = date('Y-m-d', strtotime("-$period_days days"));
$end_date = date('Y-m-d');

if ($db) {
    try {
        // 1. Overall Statistics
        $overall_query = "
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as completed_revenue,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(total_amount) as total_revenue_all,
                AVG(total_amount) as avg_order_value
            FROM orders
        ";
        $overall_stmt = $db->query($overall_query);
        $overall_data = $overall_stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_orders = $overall_data['total_orders'] ?? 0;
        $total_revenue = $overall_data['completed_revenue'] ?? 0;
        $completed_orders = $overall_data['completed_orders'] ?? 0;
        $pending_orders = $overall_data['pending_orders'] ?? 0;
        $cancelled_orders = $overall_data['cancelled_orders'] ?? 0;
        $average_order_value = $overall_data['avg_order_value'] ?? 0;

        // 2. Daily Statistics (last 30 days by default)
        $daily_query = "
            SELECT 
                DATE(created_at) as order_date,
                COUNT(*) as order_count,
                SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as daily_revenue,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
            FROM orders
            WHERE created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY order_date DESC
            LIMIT 30
        ";
        $daily_stmt = $db->prepare($daily_query);
        $daily_stmt->execute([$start_date . ' 00:00:00']);
        $daily_stats = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Monthly Statistics
        $monthly_query = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as order_count,
                SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as monthly_revenue,
                AVG(total_amount) as avg_order_value,
                MIN(created_at) as first_order,
                MAX(created_at) as last_order
            FROM orders
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ";
        $monthly_stmt = $db->query($monthly_query);
        $monthly_stats = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Customer Statistics
        $customer_query = "
            SELECT 
                o.user_id,
                COUNT(*) as order_count,
                SUM(o.total_amount) as total_spent,
                AVG(o.total_amount) as avg_order_value,
                MIN(o.created_at) as first_order,
                MAX(o.created_at) as last_order,
                s.first_name,
                s.last_name,
                s.email
            FROM orders o
            LEFT JOIN wppw_fc_subscribers s ON o.user_id = s.id
            GROUP BY o.user_id, s.first_name, s.last_name, s.email
            HAVING COUNT(*) > 0
            ORDER BY total_spent DESC
            LIMIT 20
        ";
        $customer_stmt = $db->query($customer_query);
        $customer_stats = $customer_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Top Customers (by total spent)
        $top_customers_query = "
            SELECT 
                o.user_id,
                SUM(o.total_amount) as total_spent,
                COUNT(*) as order_count,
                s.first_name,
                s.last_name,
                s.email
            FROM orders o
            LEFT JOIN wppw_fc_subscribers s ON o.user_id = s.id
            WHERE o.status = 'completed'
            GROUP BY o.user_id, s.first_name, s.last_name, s.email
            ORDER BY total_spent DESC
            LIMIT 10
        ";
        $top_customers_stmt = $db->query($top_customers_query);
        $top_customers = $top_customers_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. Order Status Distribution
        $status_query = "
            SELECT 
                status,
                COUNT(*) as count,
                SUM(total_amount) as total_amount,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM orders), 1) as percentage
            FROM orders
            GROUP BY status
            ORDER BY count DESC
        ";
        $status_stmt = $db->query($status_query);
        $status_stats = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 7. Recent Orders
        $recent_orders_query = "
            SELECT 
                o.*,
                s.first_name,
                s.last_name,
                s.email,
                CASE 
                    WHEN CONCAT(s.first_name, ' ', s.last_name) IS NOT NULL 
                    AND TRIM(CONCAT(s.first_name, ' ', s.last_name)) != '' 
                    THEN CONCAT(s.first_name, ' ', s.last_name)
                    ELSE 'Customer #' || o.user_id
                END as customer_name
            FROM orders o
            LEFT JOIN wppw_fc_subscribers s ON o.user_id = s.id
            ORDER BY o.created_at DESC
            LIMIT 10
        ";
        $recent_orders_stmt = $db->query($recent_orders_query);
        $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 8. Best Selling Days of Week
        $day_stats_query = "
            SELECT 
                DAYNAME(created_at) as day_name,
                DAYOFWEEK(created_at) as day_num,
                COUNT(*) as order_count,
                SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as revenue
            FROM orders
            GROUP BY DAYNAME(created_at), DAYOFWEEK(created_at)
            ORDER BY day_num
        ";
        $day_stats_stmt = $db->query($day_stats_query);
        $day_stats = $day_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 9. Hourly Distribution
        $hour_stats_query = "
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as order_count,
                SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as revenue
            FROM orders
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ";
        $hour_stats_stmt = $db->query($hour_stats_query);
        $hour_stats = $hour_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database error in order analytics: " . $e->getMessage());
        $error_message = "Failed to load data. Please try again later.";
    }
}

// Calculate additional metrics
$conversion_rate = $total_orders > 0 ? ($completed_orders / $total_orders * 100) : 0;
$cancellation_rate = $total_orders > 0 ? ($cancelled_orders / $total_orders * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Analytics - Admin Panel</title>
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
        .badge-status-pending { background-color: #ffc107; }
        .badge-status-processing { background-color: #17a2b8; }
        .badge-status-completed { background-color: #28a745; }
        .badge-status-cancelled { background-color: #dc3545; }
        .period-selector {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2">Order Analytics</h1>
                <p class="text-muted">Sales, revenue, and customer order analysis</p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                    </a>
                    <a href="orders.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-list"></i> Manage Orders
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Time Period Selector -->
        <div class="period-selector mb-4">
            <div class="row">
                <div class="col-md-8">
                    <h5>Select Time Period:</h5>
                    <div class="btn-group" role="group">
                        <a href="?period=7" class="btn btn-outline-primary <?php echo $period_days == 7 ? 'active' : ''; ?>">Last 7 Days</a>
                        <a href="?period=30" class="btn btn-outline-primary <?php echo $period_days == 30 ? 'active' : ''; ?>">Last 30 Days</a>
                        <a href="?period=90" class="btn btn-outline-primary <?php echo $period_days == 90 ? 'active' : ''; ?>">Last 90 Days</a>
                        <a href="?period=365" class="btn btn-outline-primary <?php echo $period_days == 365 ? 'active' : ''; ?>">Last Year</a>
                        <a href="?period=0" class="btn btn-outline-primary <?php echo $period_days == 0 ? 'active' : ''; ?>">All Time</a>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="text-muted">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo $period_days == 0 ? 'All Time Data' : "Data from $start_date to $end_date"; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-white bg-primary stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $total_orders; ?></h4>
                                <p class="mb-0">Total Orders</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-shopping-cart fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-success stat-card">
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
            <div class="col-md-2">
                <div class="card text-white bg-info stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4>$<?php echo number_format($average_order_value, 2); ?></h4>
                                <p class="mb-0">Avg Order Value</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-chart-line fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-warning stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo number_format($conversion_rate, 1); ?>%</h4>
                                <p class="mb-0">Completion Rate</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-danger stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo number_format($cancellation_rate, 1); ?>%</h4>
                                <p class="mb-0">Cancellation Rate</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-times-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-secondary stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo count($customer_stats); ?></h4>
                                <p class="mb-0">Active Customers</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Order Trends -->
            <div class="col-md-8">
                <!-- Daily Orders Chart -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Daily Order Trends (Last <?php echo $period_days == 0 ? 'All Time' : $period_days; ?> Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($daily_stats) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Total Orders</th>
                                            <th>Completed Orders</th>
                                            <th>Pending Orders</th>
                                            <th>Daily Revenue</th>
                                            <th>Avg Order Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($daily_stats as $day): 
                                            $avg_value = $day['order_count'] > 0 ? ($day['daily_revenue'] / $day['order_count']) : 0;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo date('M j, Y', strtotime($day['order_date'])); ?></strong></td>
                                            <td><?php echo $day['order_count']; ?></td>
                                            <td><span class="badge bg-success"><?php echo $day['completed_count']; ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo $day['pending_count']; ?></span></td>
                                            <td><strong>$<?php echo number_format($day['daily_revenue'], 2); ?></strong></td>
                                            <td>$<?php echo number_format($avg_value, 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No order data available for the selected period.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Monthly Performance</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($monthly_stats) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Orders</th>
                                            <th>Revenue</th>
                                            <th>Avg Order Value</th>
                                            <th>First Order</th>
                                            <th>Last Order</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthly_stats as $month): ?>
                                        <tr>
                                            <td><strong><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></strong></td>
                                            <td><?php echo $month['order_count']; ?></td>
                                            <td><strong>$<?php echo number_format($month['monthly_revenue'], 2); ?></strong></td>
                                            <td>$<?php echo number_format($month['avg_order_value'], 2); ?></td>
                                            <td><?php echo date('M j', strtotime($month['first_order'])); ?></td>
                                            <td><?php echo date('M j', strtotime($month['last_order'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No monthly data available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Customer Stats and Status Distribution -->
            <div class="col-md-4">
                <!-- Order Status Distribution -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Order Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($status_stats) > 0): ?>
                            <?php foreach ($status_stats as $status): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>
                                        <span class="badge badge-status-<?php echo $status['status']; ?> me-2">
                                            <?php echo ucfirst($status['status']); ?>
                                        </span>
                                        <strong><?php echo $status['count']; ?> orders</strong>
                                    </span>
                                    <span><?php echo $status['percentage']; ?>%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar 
                                        <?php 
                                        switch($status['status']) {
                                            case 'pending': echo 'bg-warning'; break;
                                            case 'processing': echo 'bg-info'; break;
                                            case 'completed': echo 'bg-success'; break;
                                            case 'cancelled': echo 'bg-danger'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>" 
                                        role="progressbar" 
                                        style="width: <?php echo $status['percentage']; ?>%">
                                    </div>
                                </div>
                                <div class="text-muted small mt-1">
                                    Total Amount: $<?php echo number_format($status['total_amount'], 2); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No status data available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Customers -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Top Customers (by Total Spent)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($top_customers) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Orders</th>
                                            <th>Total Spent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_customers as $index => $customer): 
                                            $customer_name = !empty(trim($customer['first_name'] . ' ' . $customer['last_name'])) 
                                                ? trim($customer['first_name'] . ' ' . $customer['last_name'])
                                                : 'Customer #' . $customer['user_id'];
                                        ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <small><strong><?php echo htmlspecialchars($customer_name); ?></strong></small>
                                                    <?php if (!empty($customer['email'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo $customer['order_count']; ?></span></td>
                                            <td><strong>$<?php echo number_format($customer['total_spent'], 2); ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No customer data available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Day of Week Performance -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Best Performing Days</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($day_stats) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($day_stats as $day): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo $day['day_name']; ?></h6>
                                        <span class="badge bg-primary"><?php echo $day['order_count']; ?> orders</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">
                                            Revenue: $<?php echo number_format($day['revenue'], 2); ?>
                                        </small>
                                        <small class="text-muted">
                                            Avg: $<?php echo $day['order_count'] > 0 ? number_format($day['revenue'] / $day['order_count'], 2) : '0.00'; ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No day statistics available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hourly Distribution -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Hourly Order Distribution</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($hour_stats) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Hour (24h)</th>
                                            <th>Order Count</th>
                                            <th>Total Revenue</th>
                                            <th>Average per Order</th>
                                            <th>Percentage of Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $total_hour_orders = array_sum(array_column($hour_stats, 'order_count'));
                                        foreach ($hour_stats as $hour): 
                                            $hour_percentage = $total_hour_orders > 0 ? ($hour['order_count'] / $total_hour_orders * 100) : 0;
                                            $avg_hour_value = $hour['order_count'] > 0 ? ($hour['revenue'] / $hour['order_count']) : 0;
                                        ?>
                                        <tr>
                                            <td><strong><?php echo sprintf('%02d:00 - %02d:59', $hour['hour'], $hour['hour']); ?></strong></td>
                                            <td><?php echo $hour['order_count']; ?></td>
                                            <td><strong>$<?php echo number_format($hour['revenue'], 2); ?></strong></td>
                                            <td>$<?php echo number_format($avg_hour_value, 2); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                        <div class="progress-bar bg-info" role="progressbar" 
                                                             style="width: <?php echo $hour_percentage; ?>%"></div>
                                                    </div>
                                                    <span><?php echo number_format($hour_percentage, 1); ?>%</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No hourly statistics available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Orders</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_orders) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): 
                                            // Processing customer name display
                                            $customer_name = '';
                                            if (!empty(trim($order['first_name'] . ' ' . $order['last_name']))) {
                                                $customer_name = trim($order['first_name'] . ' ' . $order['last_name']);
                                            } elseif (!empty($order['email'])) {
                                                $customer_name = explode('@', $order['email'])[0]; // Use email username section
                                            } else {
                                                $customer_name = 'Customer #' . $order['user_id'];
                                            }
                                            
                                            $status_badge = '';
                                            switch($order['status']) {
                                                case 'pending': $status_badge = 'warning'; break;
                                                case 'processing': $status_badge = 'info'; break;
                                                case 'completed': $status_badge = 'success'; break;
                                                case 'cancelled': $status_badge = 'danger'; break;
                                                default: $status_badge = 'secondary';
                                            }
                                        ?>
                                        <tr>
                                            <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($customer_name); ?></div>
                                                <?php if (!empty($order['email'])): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                                            <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo $status_badge; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="order_details.php?order_id=<?php echo $order['order_id']; ?>" 
                                                class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center">
                                <a href="orders.php" class="btn btn-primary">
                                    <i class="fas fa-list"></i> View All Orders
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No recent orders available.</p>
                        <?php endif; ?>
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
                            Data updated at: <?php echo date('Y-m-d H:i:s'); ?> | 
                            Period: <?php echo $period_days == 0 ? 'All Time' : "Last $period_days days"; ?>
                        </div>
                        <div>
                            <a href="order_analytics.php?period=<?php echo $period_days; ?>" class="btn btn-sm btn-info">
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
        // Highlight today's date in tables
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            
            // Highlight today's date in daily stats
            document.querySelectorAll('tbody tr').forEach(row => {
                const dateCell = row.querySelector('td:first-child strong');
                if (dateCell) {
                    const cellDate = dateCell.textContent.trim();
                    // Try to parse date (assuming format like "Dec 11, 2025")
                    const parsedDate = new Date(cellDate);
                    if (parsedDate.toISOString().split('T')[0] === today) {
                        row.classList.add('table-success');
                    }
                }
            });
        });
    </script>
</body>
</html>