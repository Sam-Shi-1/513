<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$project_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__DIR__)));
$image_base_url = $base_url . $project_path . '/assets/images/products/';
$default_image_url = $image_base_url . 'default.jpg';

$json_file_path = __DIR__ . '/../data/products.json'; // from admin/products directory to data directory

//Read data from JSON file
function getProductsFromJson($file_path) {
    // Check if the file exists
    if (!file_exists($file_path)) {
        error_log("JSON file does not exist at: " . $file_path);
        return array('error' => 'Products JSON file not found at: ' . basename($file_path));
    }
    
    // Check if the file is readable
    if (!is_readable($file_path)) {
        error_log("JSON file is not readable: " . $file_path);
        return array('error' => 'Products JSON file is not readable');
    }
    
    $json_content = file_get_contents($file_path);
    if ($json_content === false) {
        return array('error' => 'Failed to read JSON file');
    }
    
    $data = json_decode($json_content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array('error' => 'Error decoding JSON: ' . json_last_error_msg());
    }
    
    return $data;
}

// Get products data
$products_data = getProductsFromJson($json_file_path);
$products = isset($products_data['products']) ? $products_data['products'] : array();

// Category ID to name mapping 
$categories_map = array(
    3 => 'Gaming Gear',
    4 => 'Game Currency',
    6 => 'Battle Royale',
    7 => 'Collectibles',
    9 => 'CSGO Items'
);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2">Product Management</h1>
        <p class="text-muted">Data loaded from JSON file</p>
    </div>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-tachometer-alt"></i> Back to Dashboard
            </a>
            <a href="product_add.php" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-plus"></i> Add New Product
            </a>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Discount</th>
                <th>Stock</th>
                <th>Type</th>
                <th>Supplier</th>
                <th>Platform</th>
                <th>Region</th>
                <th>Featured</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (isset($products_data['error'])) {
                echo '<tr><td colspan="14" class="text-center text-danger">' . htmlspecialchars($products_data['error']) . '</td></tr>';
            } elseif (empty($products)) {
                echo '<tr><td colspan="14" class="text-center text-warning">No products found in JSON file</td></tr>';
            } else {
                foreach ($products as $product) {
                    $product_image_url = !empty($product['image_url']) ? 
                        $image_base_url . $product['image_url'] : 
                        $default_image_url;
                    
                    $hasDiscount = isset($product['discount_percent']) && floatval($product['discount_percent']) > 0;
                    $originalPrice = floatval($product['price']);
                    $discountPercent = floatval($product['discount_percent']);
                    $discountedPrice = $hasDiscount ? 
                        $originalPrice * (1 - $discountPercent / 100) : 
                        $originalPrice;
                    
                    // Get category name
                    $category_name = isset($categories_map[$product['category_id']]) 
                        ? $categories_map[$product['category_id']] 
                        : 'Category ' . $product['category_id'];
            ?>
            <tr>
                <td><?php echo $product['product_id']; ?></td>
                <td>
                    <img src="<?php echo $product_image_url; ?>" 
                         class="img-thumbnail" 
                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                         style="width: 50px; height: 50px; object-fit: cover;"
                         onerror="this.src='<?php echo $default_image_url; ?>'">
                </td>
                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                <td><?php echo htmlspecialchars($category_name); ?> (ID: <?php echo $product['category_id']; ?>)</td>
                <td>
                    <?php if ($hasDiscount): ?>
                        <span class="text-muted text-decoration-line-through d-block" style="font-size: 0.8rem;">
                            $<?php echo number_format($originalPrice, 2); ?>
                        </span>
                        <strong class="text-primary">$<?php echo number_format($discountedPrice, 2); ?></strong>
                    <?php else: ?>
                        $<?php echo number_format($originalPrice, 2); ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($hasDiscount): ?>
                        <span class="badge bg-danger">-<?php echo $discountPercent; ?>%</span>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="<?php echo $product['stock_quantity'] < 10 ? 'text-warning fw-bold' : ''; ?>">
                        <?php echo $product['stock_quantity']; ?>
                    </span>
                </td>
                <td><span class="badge bg-<?php echo $product['product_type'] == 'cdk' ? 'info' : ($product['product_type'] == 'virtual' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($product['product_type']); ?></span></td>
                <td><?php echo htmlspecialchars($product['supplier'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($product['platform'] ?? 'Multi-platform'); ?></td>
                <td><?php echo htmlspecialchars($product['region'] ?? 'Global'); ?></td>
                <td>
                    <?php if ($product['is_featured']): ?>
                        <span class="badge bg-warning text-dark">Featured</span>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge bg-<?php echo $product['is_active'] ? 'success' : 'danger'; ?>"><?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="product_edit.php?id=<?php echo $product['product_id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="confirmDeleteProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php
                }
            }
            ?>
        </tbody>
    </table>
</div>

<script>
function confirmDeleteProduct(productId, productName) {
    if (confirm('Are you sure you want to delete "' + productName + '"?')) {
        window.location.href = 'product_delete.php?id=' + productId;
    }
}
</script>

<?php include '../includes/footer.php'; ?>