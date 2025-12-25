<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

$orders_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $orders_per_page;

// Handle order deletion
if (isset($_GET['delete_order'])) {
    $order_id = intval($_GET['delete_order']);
    
    try {
        $db->beginTransaction();
        
        // First delete order items
        $delete_items = "DELETE FROM order_items WHERE order_id = ?";
        $stmt = $db->prepare($delete_items);
        $stmt->execute([$order_id]);
        
        // Then delete the order
        $delete_order = "DELETE FROM orders WHERE order_id = ?";
        $stmt = $db->prepare($delete_order);
        $stmt->execute([$order_id]);
        
        $db->commit();
        $success_message = "Order deleted successfully!";
    } catch (Exception $e) {
        $db->rollBack();
        $error_message = "Failed to delete order: " . $e->getMessage();
    }
}

$total_orders = 0;
$total_pages = 1;
if ($db) {
    try {
        $count_stmt = $db->query("SELECT COUNT(*) as total FROM orders");
        $total_orders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $total_pages = ceil($total_orders / $orders_per_page);
    } catch (Exception $e) {
        $total_orders = 0;
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2">Order Management</h1>
        <p class="text-muted">View and manage all customer orders</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-tachometer-alt"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php if (isset($success_message)): ?>
    <div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <span class="text-muted">
                    Showing <?php echo min($orders_per_page, $total_orders - $offset); ?> of <?php echo $total_orders; ?> orders
                </span>
            </div>
            <div>
                <span class="text-muted">Page <?php echo $current_page; ?> of <?php echo max(1, $total_pages); ?></span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($db) {
                        try {
                           //Query order and user information
                            $query = "SELECT o.*, 
                                             s.first_name, 
                                             s.last_name, 
                                             s.email
                                      FROM orders o 
                                      LEFT JOIN wppw_fc_subscribers s ON o.user_id = s.id 
                                      ORDER BY o.created_at DESC
                                      LIMIT :limit OFFSET :offset";
                            $stmt = $db->prepare($query);
                            $stmt->bindValue(':limit', $orders_per_page, PDO::PARAM_INT);
                            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                            $stmt->execute();
                            
                            if ($stmt->rowCount() > 0) {
                                while ($order = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $status_badge = '';
                                    switch($order['status']) {
                                        case 'pending': 
                                            $status_badge = 'bg-warning'; 
                                            break;
                                        case 'processing': 
                                            $status_badge = 'bg-info'; 
                                            break;
                                        case 'completed': 
                                            $status_badge = 'bg-success'; 
                                            break;
                                        case 'cancelled': 
                                            $status_badge = 'bg-danger'; 
                                            break;
                                        default: 
                                            $status_badge = 'bg-secondary';
                                    }
                                    
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
                        <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($customer_name); ?></strong>
                            </div>
                            <?php if (!empty(trim($order['email'] ?? ''))): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                <br>
                            <?php endif; ?>
                            <small class="text-info">User ID: <?php echo $order['user_id']; ?></small>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                        <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                        <td>
                            <span class="badge <?php echo $status_badge; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="order_details.php?order_id=<?php echo $order['order_id']; ?>" 
                                   class="btn btn-outline-primary" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?delete_order=<?php echo $order['order_id']; ?>&page=<?php echo $current_page; ?>" 
                                   class="btn btn-outline-danger" 
                                   title="Delete Order"
                                   onclick="return confirm('Are you sure you want to delete order #<?php echo $order['order_id']; ?>? This action cannot be undone!')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="6" class="text-center text-muted py-4">No order data available</td></tr>';
                            }
                        } catch (PDOException $e) {
                            echo '<tr><td colspan="6" class="text-center text-danger py-4">Error loading orders: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                            
                            // Fallback: try to show orders without user info if query fails
                            try {
                                echo '<tr><td colspan="6" class="text-center text-warning py-4">Attempting fallback query...</td></tr>';
                                $fallback_query = "SELECT o.* FROM orders o ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset";
                                $fallback_stmt = $db->prepare($fallback_query);
                                $fallback_stmt->bindValue(':limit', $orders_per_page, PDO::PARAM_INT);
                                $fallback_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                                $fallback_stmt->execute();
                                
                                while ($order = $fallback_stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $status_badge = 'bg-secondary';
                    ?>
                    <tr>
                        <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                        <td>
                            <div>
                                <strong>Customer #<?php echo $order['user_id']; ?></strong>
                            </div>
                            <small class="text-muted">User information not available</small>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                        <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                        <td>
                            <span class="badge <?php echo $status_badge; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="order_details.php?order_id=<?php echo $order['order_id']; ?>" 
                                   class="btn btn-outline-primary" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?delete_order=<?php echo $order['order_id']; ?>&page=<?php echo $current_page; ?>" 
                                   class="btn btn-outline-danger" 
                                   title="Delete Order"
                                   onclick="return confirm('Are you sure you want to delete order #<?php echo $order['order_id']; ?>? This action cannot be undone!')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php
                                }
                            } catch (Exception $fallback_e) {
                                echo '<tr><td colspan="6" class="text-center text-danger py-4">Fallback also failed: ' . htmlspecialchars($fallback_e->getMessage()) . '</td></tr>';
                            }
                        }
                    } else {
                        echo '<tr><td colspan="6" class="text-center text-warning py-4">Database connection not available</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <nav aria-label="Order pagination">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <li class="page-item active">
                            <span class="page-link"><?php echo $i; ?></span>
                        </li>
                    <?php else: ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endif; ?>
                <?php endfor; ?>

                <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Add statistics information -->
<div class="row mt-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">Total Orders</h5>
                <?php
                $total_orders = 0;
                if ($db) {
                    try {
                        $stmt = $db->query("SELECT COUNT(*) as total FROM orders");
                        $total_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    } catch (Exception $e) {
                        $total_orders = 'Error';
                    }
                }
                ?>
                <h2><?php echo $total_orders; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">Total Revenue</h5>
                <?php
                $total_revenue = 0;
                if ($db) {
                    try {
                        $stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'");
                        $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                    } catch (Exception $e) {
                        $total_revenue = 'Error';
                    }
                }
                ?>
                <h2>$<?php echo is_numeric($total_revenue) ? number_format($total_revenue, 2) : $total_revenue; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">Pending Orders</h5>
                <?php
                $pending_orders = 0;
                if ($db) {
                    try {
                        $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
                        $pending_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    } catch (Exception $e) {
                        $pending_orders = 'Error';
                    }
                }
                ?>
                <h2><?php echo $pending_orders; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">Completed Orders</h5>
                <?php
                $completed_orders = 0;
                if ($db) {
                    try {
                        $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE status = 'completed'");
                        $completed_orders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    } catch (Exception $e) {
                        $completed_orders = 'Error';
                    }
                }
                ?>
                <h2><?php echo $completed_orders; ?></h2>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>