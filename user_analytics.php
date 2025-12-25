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
$total_users = 0;
$active_users = 0;
$new_users_today = 0;
$new_users_week = 0;
$users_with_orders = 0;

$user_growth = [];
$user_orders_stats = [];
$user_status_stats = [];
$top_spending_users = [];
$recent_registrations = [];
$user_type_stats = [];

// Set time period (default: last 30 days)
$period_days = isset($_GET['period']) ? intval($_GET['period']) : 30;
$start_date = date('Y-m-d', strtotime("-$period_days days"));
$end_date = date('Y-m-d');

if ($db) {
    try {
        // 1. Overall User Statistics
        $overall_query = "
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN status = 'subscribed' THEN 1 ELSE 0 END) as subscribed_users,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_users,
                SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed_users,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_today,
                SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_week
            FROM wppw_fc_subscribers
        ";
        $overall_stmt = $db->query($overall_query);
        $overall_data = $overall_stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_users = $overall_data['total_users'] ?? 0;
        $subscribed_users = $overall_data['subscribed_users'] ?? 0;
        $pending_users = $overall_data['pending_users'] ?? 0;
        $unsubscribed_users = $overall_data['unsubscribed_users'] ?? 0;
        $new_users_today = $overall_data['new_today'] ?? 0;
        $new_users_week = $overall_data['new_week'] ?? 0;

        // 2. Users with orders (active users)
        $active_users_query = "
            SELECT COUNT(DISTINCT user_id) as users_with_orders
            FROM orders
        ";
        $active_stmt = $db->query($active_users_query);
        $active_data = $active_stmt->fetch(PDO::FETCH_ASSOC);
        $users_with_orders = $active_data['users_with_orders'] ?? 0;

        // 3. Daily User Growth
        $growth_query = "
            SELECT 
                DATE(created_at) as reg_date,
                COUNT(*) as new_users,
                SUM(CASE WHEN status = 'subscribed' THEN 1 ELSE 0 END) as subscribed_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
            FROM wppw_fc_subscribers
            WHERE created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY reg_date DESC
            LIMIT 30
        ";
        $growth_stmt = $db->prepare($growth_query);
        $growth_stmt->execute([$start_date . ' 00:00:00']);
        $user_growth = $growth_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. User Order Statistics
        $order_stats_query = "
            SELECT 
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.email,
                u.status as user_status,
                u.created_at as reg_date,
                COUNT(o.order_id) as order_count,
                SUM(o.total_amount) as total_spent,
                AVG(o.total_amount) as avg_order_value,
                MIN(o.created_at) as first_order_date,
                MAX(o.created_at) as last_order_date
            FROM wppw_fc_subscribers u
            LEFT JOIN orders o ON u.id = o.user_id
            GROUP BY u.id, u.first_name, u.last_name, u.email, u.status, u.created_at
            HAVING COUNT(o.order_id) > 0
            ORDER BY total_spent DESC, order_count DESC
            LIMIT 50
        ";
        $order_stats_stmt = $db->query($order_stats_query);
        $user_orders_stats = $order_stats_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Top Spending Users
        $top_spenders_query = "
            SELECT 
                u.id as user_id,
                u.first_name,
                u.last_name,
                u.email,
                u.status as user_status,
                COUNT(o.order_id) as order_count,
                SUM(o.total_amount) as total_spent,
                AVG(o.total_amount) as avg_order_value,
                MAX(o.created_at) as last_order_date
            FROM wppw_fc_subscribers u
            INNER JOIN orders o ON u.id = o.user_id
            WHERE o.status = 'completed'
            GROUP BY u.id, u.first_name, u.last_name, u.email, u.status
            ORDER BY total_spent DESC
            LIMIT 20
        ";
        $top_spenders_stmt = $db->query($top_spenders_query);
        $top_spending_users = $top_spenders_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. User Status Distribution
        $status_query = "
            SELECT 
                status,
                COUNT(*) as user_count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wppw_fc_subscribers), 1) as percentage,
                MIN(created_at) as first_registration,
                MAX(created_at) as last_registration
            FROM wppw_fc_subscribers
            GROUP BY status
            ORDER BY user_count DESC
        ";
        $status_stmt = $db->query($status_query);
        $user_status_stats = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 7. Recent User Registrations
        $recent_reg_query = "
            SELECT 
                id,
                first_name,
                last_name,
                email,
                status,
                phone,
                country,
                created_at,
                last_activity
            FROM wppw_fc_subscribers
            ORDER BY created_at DESC
            LIMIT 15
        ";
        $recent_reg_stmt = $db->query($recent_reg_query);
        $recent_registrations = $recent_reg_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 8. User Type Statistics (with/without orders)
        $type_query = "
            SELECT 
                'With Orders' as user_type,
                COUNT(DISTINCT o.user_id) as count,
                ROUND(COUNT(DISTINCT o.user_id) * 100.0 / (SELECT COUNT(*) FROM wppw_fc_subscribers), 1) as percentage
            FROM orders o
            UNION ALL
            SELECT 
                'Without Orders' as user_type,
                (SELECT COUNT(*) FROM wppw_fc_subscribers) - COUNT(DISTINCT o.user_id) as count,
                ROUND(((SELECT COUNT(*) FROM wppw_fc_subscribers) - COUNT(DISTINCT o.user_id)) * 100.0 / 
                (SELECT COUNT(*) FROM wppw_fc_subscribers), 1) as percentage
            FROM orders o
        ";
        $type_stmt = $db->query($type_query);
        $user_type_stats = $type_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 9. User Activity Statistics
        $activity_query = "
            SELECT 
                CASE 
                    WHEN last_activity IS NULL OR DATEDIFF(CURDATE(), last_activity) > 90 THEN 'Inactive (>90 days)'
                    WHEN DATEDIFF(CURDATE(), last_activity) > 30 THEN 'Inactive (30-90 days)'
                    WHEN DATEDIFF(CURDATE(), last_activity) > 7 THEN 'Active (7-30 days)'
                    WHEN DATEDIFF(CURDATE(), last_activity) <= 7 THEN 'Very Active (≤7 days)'
                    ELSE 'Never Active'
                END as activity_level,
                COUNT(*) as user_count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wppw_fc_subscribers), 1) as percentage
            FROM wppw_fc_subscribers
            GROUP BY activity_level
            ORDER BY user_count DESC
        ";
        $activity_stmt = $db->query($activity_query);
        $user_activity_stats = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 10. Monthly User Growth
        $monthly_growth_query = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as new_users,
                SUM(CASE WHEN status = 'subscribed' THEN 1 ELSE 0 END) as subscribed_count,
                MIN(created_at) as first_reg,
                MAX(created_at) as last_reg
            FROM wppw_fc_subscribers
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ";
        $monthly_growth_stmt = $db->query($monthly_growth_query);
        $monthly_growth = $monthly_growth_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 11. User Source Statistics (if source field exists)
        $source_query = "
            SELECT 
                COALESCE(source, 'Unknown') as source,
                COUNT(*) as user_count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wppw_fc_subscribers), 1) as percentage,
                AVG(CASE WHEN EXISTS (SELECT 1 FROM orders WHERE user_id = wppw_fc_subscribers.id) THEN 1 ELSE 0 END) * 100 as conversion_rate
            FROM wppw_fc_subscribers
            GROUP BY COALESCE(source, 'Unknown')
            ORDER BY user_count DESC
            LIMIT 10
        ";
        $source_stmt = $db->query($source_query);
        $user_source_stats = $source_stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database error in user analytics: " . $e->getMessage());
        $error_message = "Failed to load data. Please try again later.";
    }
}

// Calculate additional metrics
$active_users = $subscribed_users;
$conversion_rate = $total_users > 0 ? ($users_with_orders / $total_users * 100) : 0;
$avg_orders_per_user = $users_with_orders > 0 ? ($user_orders_stats[0]['order_count'] ?? 0) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Analytics - Admin Panel</title>
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
        .badge-status-subscribed { background-color: #28a745; }
        .badge-status-pending { background-color: #ffc107; }
        .badge-status-unsubscribed { background-color: #dc3545; }
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
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
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
                <h1 class="h2">User Analytics</h1>
                <p class="text-muted">User registration, activity, and behavior analysis</p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                    </a>
                    <a href="users.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-users"></i> Manage Users
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
                                <h4><?php echo $total_users; ?></h4>
                                <p class="mb-0">Total Users</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-users fa-2x"></i>
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
                                <h4><?php echo $active_users; ?></h4>
                                <p class="mb-0">Active Users</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-check fa-2x"></i>
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
                                <h4><?php echo number_format($conversion_rate, 1); ?>%</h4>
                                <p class="mb-0">Conversion Rate</p>
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
                                <h4><?php echo $new_users_today; ?></h4>
                                <p class="mb-0">New Today</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-user-plus fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-purple stat-card" style="background-color: #6f42c1;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4><?php echo $users_with_orders; ?></h4>
                                <p class="mb-0">Users with Orders</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-shopping-cart fa-2x"></i>
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
                                <h4><?php echo $new_users_week; ?></h4>
                                <p class="mb-0">New This Week</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-calendar-week fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: User Growth and Statistics -->
            <div class="col-md-8">
                <!-- Daily User Growth -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Daily User Growth (Last <?php echo $period_days == 0 ? 'All Time' : $period_days; ?> Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($user_growth) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>New Users</th>
                                            <th>Subscribed</th>
                                            <th>Pending</th>
                                            <th>Cumulative Total</th>
                                            <th>Growth Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $cumulative_total = 0;
                                        $prev_day_count = 0;
                                        foreach ($user_growth as $day): 
                                            $cumulative_total += $day['new_users'];
                                            $growth_rate = $prev_day_count > 0 ? 
                                                (($day['new_users'] - $prev_day_count) / $prev_day_count * 100) : 0;
                                            $prev_day_count = $day['new_users'];
                                        ?>
                                        <tr>
                                            <td><strong><?php echo date('M j, Y', strtotime($day['reg_date'])); ?></strong></td>
                                            <td><span class="badge bg-primary"><?php echo $day['new_users']; ?></span></td>
                                            <td><span class="badge bg-success"><?php echo $day['subscribed_count']; ?></span></td>
                                            <td><span class="badge bg-warning"><?php echo $day['pending_count']; ?></span></td>
                                            <td><?php echo $cumulative_total; ?></td>
                                            <td>
                                                <?php if ($growth_rate > 0): ?>
                                                    <span class="text-success"><i class="fas fa-arrow-up"></i> <?php echo number_format($growth_rate, 1); ?>%</span>
                                                <?php elseif ($growth_rate < 0): ?>
                                                    <span class="text-danger"><i class="fas fa-arrow-down"></i> <?php echo number_format(abs($growth_rate), 1); ?>%</span>
                                                <?php else: ?>
                                                    <span class="text-muted">0%</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No user growth data available for the selected period.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User Order Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">User Order Statistics</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($user_orders_stats) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Status</th>
                                            <th>Orders</th>
                                            <th>Total Spent</th>
                                            <th>Avg Order</th>
                                            <th>First Order</th>
                                            <th>Last Order</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_orders_stats as $user): 
                                            $user_name = !empty(trim($user['first_name'] . ' ' . $user['last_name'])) 
                                                ? trim($user['first_name'] . ' ' . $user['last_name'])
                                                : 'User #' . $user['user_id'];
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-2">
                                                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user_name); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-status-<?php echo $user['user_status']; ?>">
                                                    <?php echo ucfirst($user['user_status']); ?>
                                                </span>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo $user['order_count']; ?></span></td>
                                            <td><strong>$<?php echo number_format($user['total_spent'], 2); ?></strong></td>
                                            <td>$<?php echo number_format($user['avg_order_value'], 2); ?></td>
                                            <td><?php echo $user['first_order_date'] ? date('M j', strtotime($user['first_order_date'])) : 'N/A'; ?></td>
                                            <td><?php echo $user['last_order_date'] ? date('M j', strtotime($user['last_order_date'])) : 'N/A'; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No user order statistics available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: User Status and Top Spenders -->
            <div class="col-md-4">
                <!-- User Status Distribution -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">User Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($user_status_stats) > 0): ?>
                            <?php foreach ($user_status_stats as $status): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>
                                        <span class="badge badge-status-<?php echo $status['status']; ?> me-2">
                                            <?php echo ucfirst($status['status']); ?>
                                        </span>
                                        <strong><?php echo $status['user_count']; ?> users</strong>
                                    </span>
                                    <span><?php echo $status['percentage']; ?>%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar 
                                        <?php 
                                        switch($status['status']) {
                                            case 'subscribed': echo 'bg-success'; break;
                                            case 'pending': echo 'bg-warning'; break;
                                            case 'unsubscribed': echo 'bg-danger'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>" 
                                        role="progressbar" 
                                        style="width: <?php echo $status['percentage']; ?>%">
                                    </div>
                                </div>
                                <div class="text-muted small mt-1">
                                    First: <?php echo $status['first_registration'] ? date('M j, Y', strtotime($status['first_registration'])) : 'N/A'; ?> | 
                                    Last: <?php echo $status['last_registration'] ? date('M j, Y', strtotime($status['last_registration'])) : 'N/A'; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No user status data available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Spending Users -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Top Spending Users</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($top_spending_users) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Orders</th>
                                            <th>Total Spent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_spending_users as $index => $user): 
                                            $user_name = !empty(trim($user['first_name'] . ' ' . $user['last_name'])) 
                                                ? trim($user['first_name'] . ' ' . $user['last_name'])
                                                : 'User #' . $user['user_id'];
                                        ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <small><strong><?php echo htmlspecialchars($user_name); ?></strong></small>
                                                    <?php if (!empty($user['email'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo $user['order_count']; ?></span></td>
                                            <td><strong>$<?php echo number_format($user['total_spent'], 2); ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No spending data available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User Type Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">User Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($user_type_stats) > 0): ?>
                            <?php foreach ($user_type_stats as $type): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>
                                        <strong><?php echo $type['user_type']; ?></strong>
                                    </span>
                                    <span><?php echo $type['count']; ?> users (<?php echo $type['percentage']; ?>%)</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar 
                                        <?php 
                                        switch($type['user_type']) {
                                            case 'With Orders': echo 'bg-success'; break;
                                            case 'Without Orders': echo 'bg-secondary'; break;
                                            default: echo 'bg-info';
                                        }
                                        ?>" 
                                        role="progressbar" 
                                        style="width: <?php echo $type['percentage']; ?>%">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No user type data available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- User Activity Levels -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">User Activity Levels</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($user_activity_stats) > 0): ?>
                            <?php foreach ($user_activity_stats as $activity): ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <small><strong><?php echo $activity['activity_level']; ?></strong></small>
                                    <small><?php echo $activity['user_count']; ?> (<?php echo $activity['percentage']; ?>%)</small>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar 
                                        <?php 
                                        if (strpos($activity['activity_level'], 'Very Active') !== false) echo 'bg-success';
                                        elseif (strpos($activity['activity_level'], 'Active') !== false) echo 'bg-info';
                                        elseif (strpos($activity['activity_level'], 'Inactive (30-90') !== false) echo 'bg-warning';
                                        elseif (strpos($activity['activity_level'], 'Inactive (>90') !== false) echo 'bg-danger';
                                        else echo 'bg-secondary';
                                        ?>" 
                                        role="progressbar" 
                                        style="width: <?php echo $activity['percentage']; ?>%">
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No activity data available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent User Registrations -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent User Registrations</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_registrations) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Contact Info</th>
                                            <th>Status</th>
                                            <th>Registration Date</th>
                                            <th>Last Activity</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_registrations as $user): 
                                            $user_name = !empty(trim($user['first_name'] . ' ' . $user['last_name'])) 
                                                ? trim($user['first_name'] . ' ' . $user['last_name'])
                                                : 'User #' . $user['id'];
                                            $initials = !empty(trim($user_name)) ? strtoupper(substr($user_name, 0, 1)) : 'U';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-2">
                                                        <?php echo $initials; ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user_name); ?></strong><br>
                                                        <small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (!empty($user['email'])): ?>
                                                    <div><small><?php echo htmlspecialchars($user['email']); ?></small></div>
                                                <?php endif; ?>
                                                <?php if (!empty($user['phone'])): ?>
                                                    <div><small class="text-muted">Phone: <?php echo htmlspecialchars($user['phone']); ?></small></div>
                                                <?php endif; ?>
                                                <?php if (!empty($user['country'])): ?>
                                                    <div><small class="text-muted">Country: <?php echo htmlspecialchars($user['country']); ?></small></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-status-<?php echo $user['status']; ?>">
                                                    <?php echo ucfirst($user['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?>
                                            </td>
                                            <td>
                                                <?php echo $user['last_activity'] ? date('Y-m-d H:i', strtotime($user['last_activity'])) : 'Never'; ?>
                                            </td>
                                            <td>
                                                <a href="user_details.php?user_id=<?php echo $user['id']; ?>" 
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
                                <a href="users.php" class="btn btn-primary">
                                    <i class="fas fa-users"></i> View All Users
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No recent user registrations available.</p>
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
                            <a href="user_analytics.php?period=<?php echo $period_days; ?>" class="btn btn-sm btn-info">
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
        // Highlight today's date in user growth table
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            
            // Highlight today's date in user growth table
            document.querySelectorAll('tbody tr').forEach(row => {
                const dateCell = row.querySelector('td:first-child strong');
                if (dateCell) {
                    const cellDateText = dateCell.textContent.trim();
                    // Try to parse date (assuming format like "Dec 11, 2025")
                    const parsedDate = new Date(cellDateText);
                    if (parsedDate.toISOString().split('T')[0] === today) {
                        row.classList.add('table-success');
                    }
                }
            });

            // Add hover effect to user avatars
            document.querySelectorAll('.user-avatar').forEach(avatar => {
                avatar.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.1)';
                    this.style.transition = 'transform 0.3s';
                });
                avatar.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        });
    </script>
</body>
</html>