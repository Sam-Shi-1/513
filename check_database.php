<?php
// admin/check_database.php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();
?>

<div class="container">
    <h2>Database Status Check</h2>
    
    <?php if ($db): ?>
        <div class="alert alert-success">Database connection successful!</div>
        
        <h4>Table Status:</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th>Status</th>
                    <th>Row Count</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tables = ['categories', 'products', 'users', 'orders', 'order_items', 'cdk_keys'];
                foreach ($tables as $table) {
                    try {
                        $check = $db->query("SHOW TABLES LIKE '$table'");
                        if ($check->rowCount() > 0) {
                            $count = $db->query("SELECT COUNT(*) as count FROM $table")->fetch(PDO::FETCH_ASSOC);
                            echo "<tr class='table-success'>";
                            echo "<td>$table</td>";
                            echo "<td>✓ Exists</td>";
                            echo "<td>{$count['count']}</td>";
                            echo "</tr>";
                        } else {
                            echo "<tr class='table-warning'>";
                            echo "<td>$table</td>";
                            echo "<td>✗ Missing</td>";
                            echo "<td>-</td>";
                            echo "</tr>";
                        }
                    } catch (Exception $e) {
                        echo "<tr class='table-danger'>";
                        echo "<td>$table</td>";
                        echo "<td>Error: " . $e->getMessage() . "</td>";
                        echo "<td>-</td>";
                        echo "</tr>";
                    }
                }
                ?>
            </tbody>
        </table>
        
        <h4>Current Categories:</h4>
        <ul>
            <?php
            $categories = $db->query("SELECT * FROM categories")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($categories as $category) {
                echo "<li>{$category['category_name']} (ID: {$category['category_id']})</li>";
            }
            ?>
        </ul>
        
    <?php else: ?>
        <div class="alert alert-danger">Database connection failed!</div>
        <p>Please check your database configuration in config/database.php</p>
    <?php endif; ?>
    
    <a href="products.php" class="btn btn-primary">Back to Products</a>
</div>

<?php include '../includes/footer.php'; ?>