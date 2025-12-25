<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/config.php';

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    $_SESSION['error'] = "No order specified for deletion.";
    header("Location: orders.php");
    exit;
}

$order_id = $_GET['order_id'];

if (!is_numeric($order_id)) {
    $_SESSION['error'] = "Invalid order ID.";
    header("Location: orders.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

$success = false;
$error = '';

if ($db) {
    try {
        $check_query = "SELECT user_id FROM orders WHERE order_id = :order_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $order = $check_stmt->fetch(PDO::FETCH_ASSOC);
            if ($order['user_id'] == $_SESSION['user_id'] || (isset($_SESSION['role']) && $_SESSION['role'] == 'admin')) {
                
                $db->beginTransaction();
                
                try {
                    $delete_items_query = "DELETE FROM order_items WHERE order_id = :order_id";
                    $delete_items_stmt = $db->prepare($delete_items_query);
                    $delete_items_stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                    $delete_items_stmt->execute();

                    $delete_order_query = "DELETE FROM orders WHERE order_id = :order_id";
                    $delete_order_stmt = $db->prepare($delete_order_query);
                    $delete_order_stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                    $delete_order_stmt->execute();
                    
                    $db->commit();
                    $success = true;
                    $_SESSION['success'] = "Order #$order_id has been successfully deleted.";
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Failed to delete order: " . $e->getMessage();
                    $_SESSION['error'] = $error;
                    error_log("Order deletion failed: " . $e->getMessage());
                }
            } else {
                $error = "You don't have permission to delete this order.";
                $_SESSION['error'] = $error;
            }
        } else {
            $error = "Order not found.";
            $_SESSION['error'] = $error;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
        $_SESSION['error'] = $error;
        error_log("Database error in delete_order.php: " . $e->getMessage());
    }
} else {
    $_SESSION['error'] = "Database connection not available.";
}

header("Location: orders.php");
exit;
?>