<?php
$current_dir_level = 1;
include '../includes/header.php';

require_once '../config/config.php';

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show">';
    echo $_SESSION['success'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show">';
    echo $_SESSION['error'];
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
    unset($_SESSION['error']);
}

$database = new Database();
$db = $database->getConnection();
$orders = [];
if ($db) {
    try {
        $orders_query = "SELECT o.*, 
                        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) as item_count
                        FROM orders o 
                        WHERE o.user_id = :user_id 
                        ORDER BY o.created_at DESC";
        $orders_stmt = $db->prepare($orders_query);
        $orders_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $orders_stmt->execute();
        $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in orders.php: " . $e->getMessage());
        echo '<div class="alert alert-danger">Error loading orders: ' . $e->getMessage() . '</div>';
    }
}
?>

<div class="row">
    <div class="col-12">
        <h2>My Orders</h2>
        
        <?php if(count($orders) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr id="order-<?php echo $order['order_id']; ?>">
                            <td>#<?php echo $order['order_id']; ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></td>
                            <td><?php echo $order['item_count']; ?> item(s)</td>
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
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="order_details.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-outline-primary">
                                        View Details
                                    </a>
                                    <button type="button" class="btn btn-outline-danger delete-btn" 
                                        data-order-id="<?php echo $order['order_id']; ?>">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <h4>No Orders Yet</h4>
                <p>You haven't placed any orders yet.</p>
                <a href="../products/index.php" class="btn btn-primary">Start Shopping</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.delete-btn');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            confirmDelete(orderId);
        });
    });
});

function confirmDelete(orderId) {
    if (confirm('Are you sure you want to delete order #' + orderId + '? This action cannot be undone.')) {
        const button = document.querySelector(`[data-order-id="${orderId}"]`);
        if (button) {
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Deleting...';
            button.disabled = true;
        }

        window.location.href = 'delete_order.php?order_id=' + orderId;
    }
}
</script>