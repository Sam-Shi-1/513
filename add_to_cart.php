<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input'
        ]);
        exit;
    }
    
    $product_id = $input['product_id'] ?? null;
    $product_name = $input['product_name'] ?? '';
    $price = $input['price'] ?? 0;
    $quantity = isset($input['quantity']) ? intval($input['quantity']) : 1;
    $product_image = $input['product_image'] ?? 'default.jpg';

    if (!$product_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Product ID is required'
        ]);
        exit;
    }
    
    if (!$product_name) {
        echo json_encode([
            'success' => false,
            'message' => 'Product name is required'
        ]);
        exit;
    }

    if ($quantity <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Quantity must be greater than 0'
        ]);
        exit;
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $item_index = -1;
    foreach ($_SESSION['cart'] as $index => $item) {
        if ($item['product_id'] == $product_id) {
            $item_index = $index;
            break;
        }
    }
    
    if ($item_index >= 0) {
        $_SESSION['cart'][$item_index]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][] = [
            'product_id' => $product_id,
            'product_name' => $product_name,
            'price' => floatval($price),
            'quantity' => $quantity,
            'product_image' => $product_image
        ];
    }

    $_SESSION['cart'] = array_values($_SESSION['cart']);

    $cart_total_quantity = 0;
    foreach ($_SESSION['cart'] as $item) {
        $cart_total_quantity += $item['quantity'];
    }

    error_log("Cart update - Product ID: $product_id, Added quantity: $quantity, Total items in cart: $cart_total_quantity");
    
    echo json_encode([
        'success' => true,
        'cart_count' => $cart_total_quantity, 
        'total_quantity' => $cart_total_quantity, 
        'cart_items_count' => count($_SESSION['cart']),
        'message' => 'Product added to cart successfully'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>