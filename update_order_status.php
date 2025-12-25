<?php
require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        try {
            $query = "UPDATE orders SET status = :status WHERE order_id = :order_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            
            $_SESSION['success'] = "Order status updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating order status: " . $e->getMessage();
        }
    }
    
    header("Location: order_details.php?order_id=" . $order_id);
    exit;
}

header("Location: orders.php");
exit;
?>