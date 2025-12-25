<?php
require_once 'config/config.php';

echo "<h2>Database Initialization</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<div class='alert alert-success'>Database connection successful!</div>";

        $tables = ['users', 'products', 'categories', 'orders', 'order_items', 'cdk_keys'];
        
        foreach ($tables as $table) {
            $check_table = $db->query("SHOW TABLES LIKE '$table'");
            if ($check_table->rowCount() > 0) {
                echo "<div class='alert alert-info'>Table '$table' exists</div>";
            } else {
                echo "<div class='alert alert-warning'>Table '$table' does not exist</div>";
            }
        }
        
    } else {
        echo "<div class='alert alert-danger'>Database connection failed!</div>";
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}

echo "<h3>Session Information</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>