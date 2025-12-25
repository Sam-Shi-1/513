<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No product specified for deletion.";
    header("Location: products.php");
    exit;
}

$product_id = (int)$_GET['id'];

//JSON file path
$json_file_path = dirname(__DIR__) . '/data/products.json';

//Read data from JSON file
function readProductsFromJson($file_path) {
    if (!file_exists($file_path)) {
        return array('error' => 'Products JSON file not found');
    }
    
    $json_content = file_get_contents($file_path);
    $data = json_decode($json_content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array('error' => 'Error decoding JSON: ' . json_last_error_msg());
    }
    
    return $data;
}

//Write data to JSON file
function writeProductsToJson($file_path, $data) {
    $json_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (file_put_contents($file_path, $json_content, LOCK_EX) === false) {
        return array('error' => 'Failed to write to JSON file');
    }
    
    return array('success' => true);
}

//Delete product images
function deleteProductImage($image_filename) {
    if (!empty($image_filename)) {
        $upload_dir = dirname(dirname(__DIR__)) . '/assets/images/products/';
        $image_path = $upload_dir . $image_filename;
        
        if (file_exists($image_path)) {
            if (unlink($image_path)) {
                error_log("Deleted product image: " . $image_path);
                return true;
            } else {
                error_log("Failed to delete product image: " . $image_path);
                return false;
            }
        }
    }
    return true; 
}

//Read product data
$products_data = readProductsFromJson($json_file_path);

if (isset($products_data['error'])) {
    $_SESSION['error'] = $products_data['error'];
    header("Location: products.php");
    exit;
}

$products = $products_data['products'];
$deleted_product = null;
$product_found = false;
$success = false;
$error = '';

//Find and delete products with the specified ID
foreach ($products as $key => $product) {
    if ((int)$product['product_id'] === $product_id) {
        $deleted_product = $product;
        
        // Record product information for deleting images
        $product_image = $product['image_url'] ?? '';
        
        // Remove products from the array
        unset($products[$key]);
        
        // Re index the array to maintain continuous indexing
        $products = array_values($products);
        $product_found = true;
        break;
    }
}

if (!$product_found) {
    $_SESSION['error'] = "Product not found.";
    header("Location: products.php");
    exit;
}



if ($product_found) {
    // Delete product images
    $delete_image_result = deleteProductImage($product_image);
    
    // Write back to JSON file
    $write_result = writeProductsToJson($json_file_path, ['products' => $products]);
    
    if (isset($write_result['success'])) {
        $success = true;
        $message = "Product '{$deleted_product['product_name']}' has been successfully deleted.";
        
        if (!$delete_image_result) {
            $message .= " Note: Could not delete product image.";
        }
        
        $_SESSION['success'] = $message;
    } else {
        $error = "Failed to save changes to JSON file: " . $write_result['error'];
        $_SESSION['error'] = $error;
    }
} else {
    $_SESSION['error'] = "Failed to delete product.";
}

header("Location: products.php");
exit;
?>