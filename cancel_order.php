<?php
session_start();
require_once '../config/config.php';

if (!isLoggedIn()) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['order_id'])) {
    $_SESSION['error'] = "No order specified.";
    header("Location: orders.php");
    exit;
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];
$database = new Database();
$db = $database->getConnection();

if ($db) {
    try {
        $check_query = "SELECT * FROM orders WHERE order_id = :order_id AND user_id = :user_id";
        
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':order_id', $order_id);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            $order = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($order['status'] == 'pending') {
                $update_query = "UPDATE orders SET status = 'cancelled' WHERE order_id = :order_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':order_id', $order_id);
                $update_stmt->execute();

                $restore_query = "SELECT oi.product_id, oi.quantity 
                                 FROM order_items oi 
                                 WHERE oi.order_id = :order_id";
                $restore_stmt = $db->prepare($restore_query);
                $restore_stmt->bindParam(':order_id', $order_id);
                $restore_stmt->execute();
                $order_items = $restore_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($order_items as $item) {
                    $update_stock = "UPDATE products SET stock_quantity = stock_quantity + :quantity 
                                    WHERE product_id = :product_id";
                    $update_stock_stmt = $db->prepare($update_stock);
                    $update_stock_stmt->bindParam(':quantity', $item['quantity']);
                    $update_stock_stmt->bindParam(':product_id', $item['product_id']);
                    $update_stock_stmt->execute();
                }
                
                $_SESSION['success'] = "Order #{$order_id} has been cancelled successfully.";
            } else {
                $_SESSION['error'] = "Only pending orders can be cancelled. Current status: " . $order['status'];
            }
        } else {
            $_SESSION['error'] = "Order not found or you don't have permission to cancel this order.";
        }
    } catch (PDOException $e) {
        error_log("Database error in cancel_order.php: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while cancelling the order. Please try again.";
    }
} else {
    $_SESSION['error'] = "Database connection failed.";
}

header("Location: order_details.php?order_id=" . $order_id);
exit;
?>