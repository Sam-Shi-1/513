<?php
session_start();
header('Content-Type: application/json');

$cart_total_quantity = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_total_quantity += $item['quantity'];
    }
}

error_log("Cart count requested - Total quantity: $cart_total_quantity, Item types: " . (isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0));

echo json_encode([
    'success' => true,
    'cart_count' => $cart_total_quantity, 
    'total_quantity' => $cart_total_quantity, 
    'cart_items_count' => isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0 
]);
?>