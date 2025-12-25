<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: products.php");
    exit;
}

$product_id = (int)$_GET['id'];

$json_file_path = __DIR__ . '/../data/products.json'; 
$product = null;
$products = [];

error_log("Current directory: " . __DIR__);
error_log("JSON file path: " . $json_file_path);
error_log("JSON file exists: " . (file_exists($json_file_path) ? 'Yes' : 'No'));

// Read data from JSON file
function readProductsFromJson($file_path) {
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

// Write data to JSON file
function writeProductsToJson($file_path, $data) {
    $json_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (file_put_contents($file_path, $json_content, LOCK_EX) === false) {
        return array('error' => 'Failed to write to JSON file');
    }
    
    return array('success' => true);
}

// Read product data
$products_data = readProductsFromJson($json_file_path);
if (isset($products_data['error'])) {
    // Using JavaScript jump to avoid header errors
    echo '<script>alert("' . addslashes($products_data['error']) . '"); window.location.href = "products.php";</script>';
    exit;
}

$products = $products_data['products'];

// Search for products with the specified ID
$product_found = false;
$product_key = null;
foreach ($products as $key => $prod) {
    if ((int)$prod['product_id'] === $product_id) {
        $product = $products[$key];
        $product_key = $key;
        $product_found = true;
        break;
    }
}

if (!$product_found) {
    echo '<script>alert("Product not found."); window.location.href = "products.php";</script>';
    exit;
}

// Hard coded classification mapping
$categories = [
    ['category_id' => 1, 'category_name' => 'Action'],
    ['category_id' => 2, 'category_name' => 'RPG'],
    ['category_id' => 3, 'category_name' => 'Gaming Gear'],
    ['category_id' => 4, 'category_name' => 'Game Currency'],
    ['category_id' => 5, 'category_name' => 'Strategy'],
    ['category_id' => 6, 'category_name' => 'Battle Royale'],
    ['category_id' => 7, 'category_name' => 'Collectibles'],
    ['category_id' => 8, 'category_name' => 'Adventure'],
    ['category_id' => 9, 'category_name' => 'CSGO Items']
];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_name = $_POST['product_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $category_id = (int)($_POST['category_id'] ?? 0);
    $product_type = $_POST['product_type'] ?? 'cdk';
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $discount_percent = $_POST['discount_percent'] ?? 0;
    $supplier = $_POST['supplier'] ?? '';
    $region = $_POST['region'] ?? 'Global';
    $platform = $_POST['platform'] ?? 'Multi-platform';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $image_url = $product['image_url'];
    $delete_existing_image = isset($_POST['delete_existing_image']) ? true : false;

        // Validate inputs
    if (empty($product_name) || empty($price) || empty($category_id)) {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = "Please enter a valid price.";
    } elseif (!is_numeric($stock_quantity) || $stock_quantity < 0) {
        $error = "Please enter a valid stock quantity.";
    } elseif (!is_numeric($discount_percent) || $discount_percent < 0 || $discount_percent > 100) {
        $error = "Please enter a valid discount percentage (0-100).";
    } else {
        // Process image upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleImageUpload($_FILES['product_image'], $product['image_url']);
            if ($upload_result['success']) {
                $image_url = $upload_result['filename'];
                $success .= " Product image uploaded successfully.";
            } else {
                $error = $upload_result['error'];
            }
        } 
        
        // Process deleting existing images
        elseif ($delete_existing_image && !empty($product['image_url'])) {
            deleteProductImage($product['image_url']);
            $image_url = '';
            $success .= " Product image deleted.";
        }

        if (empty($error)) {
            // Prepare updated product data
            $updated_product = [
                'product_id' => $product_id,
                'product_name' => $product_name,
                'description' => $description,
                'price' => (string)$price,
                'category_id' => $category_id,
                'product_type' => $product_type,
                'stock_quantity' => $stock_quantity,
                'image_url' => $image_url,
                'is_active' => $is_active,
                'created_at' => $product['created_at'], // keep original creation time
                'discount_percent' => (string)$discount_percent,
                'supplier' => $supplier,
                'region' => $region,
                'platform' => $platform,
                'is_featured' => $is_featured
            ];

            // Update product in the array
            if (isset($product_key)) {
                $products[$product_key] = $updated_product;
                
                // Write back to JSON file
                $write_result = writeProductsToJson($json_file_path, ['products' => $products]);
                
                if (isset($write_result['success'])) {
                    $success = "Product updated successfully!" . (isset($success) ? $success : '');
                    // Update current product variables
                    $product = $updated_product;
                } else {
                    $error = "Failed to save changes to JSON file: " . $write_result['error'];
                }
            } else {
                $error = "Could not find product in array.";
            }
        }
    }
}

// Handle image upload
function handleImageUpload($file, $old_image = '') {
    // Use relative path
    $upload_dir = dirname(dirname(__DIR__)) . '/assets/images/products/';

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory.'];
        }
    }

    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, GIF, and WebP images are allowed.'];
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Image size must be less than 5MB.'];
    }

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        if (file_exists($file_path)) {
            if (!empty($old_image) && file_exists($upload_dir . $old_image)) {
                unlink($upload_dir . $old_image);
            }

            error_log("Image uploaded successfully: " . $filename . " to " . $file_path);
            return ['success' => true, 'filename' => $filename];
        } else {
            error_log("File not found after move: " . $file_path);
            return ['success' => false, 'error' => 'File upload failed - file not found after move.'];
        }
    } else {
        error_log("Failed to move uploaded file. Temp: " . $file['tmp_name'] . " -> Dest: " . $file_path);
        error_log("Upload error: " . $file['error']);
        error_log("Upload directory permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4));
        return ['success' => false, 'error' => 'Failed to upload image. Check directory permissions.'];
    }
}

function deleteProductImage($image_filename) {
    $upload_dir = dirname(dirname(__DIR__)) . '/assets/images/products/';
    
    if (!empty($image_filename)) {
        $image_path = $upload_dir . $image_filename;
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
}

// Get classification name function
function getCategoryName($category_id, $categories) {
    foreach ($categories as $category) {
        if ($category['category_id'] == $category_id) {
            return $category['category_name'];
        }
    }
    return "Category $category_id";
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Edit Product</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="products.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Products
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Product Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="editProductForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="product_name" class="form-label">Product Name *</label>
                                <input type="text" class="form-control" id="product_name" name="product_name" 
                                       value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-control" id="category_id" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>" 
                                            <?php echo ((int)$product['category_id'] == (int)$category['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="supplier" class="form-label">Supplier</label>
                                <input type="text" class="form-control" id="supplier" name="supplier" 
                                       value="<?php echo htmlspecialchars($product['supplier'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="platform" class="form-label">Platform</label>
                                <select class="form-control" id="platform" name="platform">
                                    <option value="Multi-platform" <?php echo (($product['platform'] ?? 'Multi-platform') == 'Multi-platform') ? 'selected' : ''; ?>>Multi-platform</option>
                                    <option value="PC" <?php echo (($product['platform'] ?? '') == 'PC') ? 'selected' : ''; ?>>PC</option>
                                    <option value="Steam" <?php echo (($product['platform'] ?? '') == 'Steam') ? 'selected' : ''; ?>>Steam</option>
                                    <option value="Mobile" <?php echo (($product['platform'] ?? '') == 'Mobile') ? 'selected' : ''; ?>>Mobile</option>
                                    <option value="PlayStation" <?php echo (($product['platform'] ?? '') == 'PlayStation') ? 'selected' : ''; ?>>PlayStation</option>
                                    <option value="Xbox" <?php echo (($product['platform'] ?? '') == 'Xbox') ? 'selected' : ''; ?>>Xbox</option>
                                    <option value="Nintendo" <?php echo (($product['platform'] ?? '') == 'Nintendo') ? 'selected' : ''; ?>>Nintendo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="region" class="form-label">Region</label>
                                <select class="form-control" id="region" name="region">
                                    <option value="Global" <?php echo (($product['region'] ?? 'Global') == 'Global') ? 'selected' : ''; ?>>Global</option>
                                    <option value="North America" <?php echo (($product['region'] ?? '') == 'North America') ? 'selected' : ''; ?>>North America</option>
                                    <option value="Europe" <?php echo (($product['region'] ?? '') == 'Europe') ? 'selected' : ''; ?>>Europe</option>
                                    <option value="Asia" <?php echo (($product['region'] ?? '') == 'Asia') ? 'selected' : ''; ?>>Asia</option>
                                    <option value="China" <?php echo (($product['region'] ?? '') == 'China') ? 'selected' : ''; ?>>China</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="discount_percent" class="form-label">Discount Percent</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="discount_percent" name="discount_percent" 
                                           min="0" max="100" step="0.01" value="<?php echo $product['discount_percent'] ?? '0'; ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="product_image" class="form-label">Product Image</label>

                        <?php if (!empty($product['image_url'])): ?>
                            <div class="mb-3">
                                <p class="text-muted mb-2">Current Image:</p>
                                <div class="current-image-container">
                                    <img src="../../assets/images/products/<?php echo htmlspecialchars($product['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                         class="img-thumbnail current-product-image" style="max-height: 200px;">
                                    <div class="mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="delete_existing_image" name="delete_existing_image" value="1">
                                            <label class="form-check-label text-danger" for="delete_existing_image">
                                                Delete current image
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No product image currently set.
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="product_image_upload" class="form-label">Upload New Image</label>
                            <input type="file" class="form-control" id="product_image_upload" name="product_image" 
                                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                            <div class="form-text">
                                Allowed formats: JPG, PNG, GIF, WebP. Maximum file size: 5MB.
                            </div>
                        </div>

                        <div id="imagePreview" class="mt-3" style="display: none;">
                            <p class="text-muted mb-2">New Image Preview:</p>
                            <img id="previewImage" class="img-thumbnail" style="max-height: 200px;">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="price" class="form-label">Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           step="0.01" min="0.01" value="<?php echo $product['price']; ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" 
                                       min="0" value="<?php echo $product['stock_quantity']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="product_type" class="form-label">Product Type *</label>
                                <select class="form-control" id="product_type" name="product_type" required>
                                    <option value="cdk" <?php echo ($product['product_type'] == 'cdk') ? 'selected' : ''; ?>>CD Key</option>
                                    <option value="physical" <?php echo ($product['product_type'] == 'physical') ? 'selected' : ''; ?>>Physical Product</option>
                                    <option value="virtual" <?php echo ($product['product_type'] == 'virtual') ? 'selected' : ''; ?>>Virtual Product</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" 
                                           <?php echo (($product['is_featured'] ?? 0) == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_featured">
                                        Featured Product
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                                           <?php echo ($product['is_active'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        Product is active and visible to customers
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <small class="text-muted">* Required fields</small>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="products.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Product Details</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Product ID:</strong> <?php echo $product['product_id']; ?>
                </div>
                <div class="mb-3">
                    <strong>Current Category:</strong> <?php echo htmlspecialchars(getCategoryName($product['category_id'], $categories)); ?>
                </div>
                <div class="mb-3">
                    <strong>Current Type:</strong> 
                    <span class="badge bg-<?php 
                        if ($product['product_type'] == 'cdk') {
                            echo 'info';
                        } elseif ($product['product_type'] == 'physical') {
                            echo 'secondary';
                        } else {
                            echo 'success';
                        }
                    ?>">
                        <?php 
                        if ($product['product_type'] == 'cdk') {
                            echo 'CD Key';
                        } elseif ($product['product_type'] == 'physical') {
                            echo 'Physical';
                        } else {
                            echo 'Virtual';
                        }
                        ?>
                    </span>
                </div>
                <?php if (!empty($product['supplier'])): ?>
                <div class="mb-3">
                    <strong>Supplier:</strong> <?php echo htmlspecialchars($product['supplier']); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($product['platform'])): ?>
                <div class="mb-3">
                    <strong>Platform:</strong> <?php echo htmlspecialchars($product['platform']); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($product['region'])): ?>
                <div class="mb-3">
                    <strong>Region:</strong> <?php echo htmlspecialchars($product['region']); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($product['discount_percent']) && floatval($product['discount_percent']) > 0): ?>
                <div class="mb-3">
                    <strong>Discount:</strong> <?php echo $product['discount_percent']; ?>%
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <strong>Current Status:</strong> 
                    <span class="badge bg-<?php echo $product['is_active'] ? 'success' : 'danger'; ?>">
                        <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
                <?php if (($product['is_featured'] ?? 0) == 1): ?>
                <div class="mb-3">
                    <strong>Featured:</strong> 
                    <span class="badge bg-warning">Yes</span>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <strong>Created:</strong> <?php echo date('M j, Y', strtotime($product['created_at'])); ?>
                </div>
                <?php if (!empty($product['image_url'])): ?>
                <div class="mb-3">
                    <strong>Image File:</strong> <?php echo htmlspecialchars($product['image_url']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Danger Zone</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Once you delete a product, there is no going back. Please be certain.
                </p>
                <button type="button" class="btn btn-outline-danger w-100" 
                        onclick="confirmDeleteProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')">
                    <i class="fas fa-trash"></i> Delete This Product
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageInput = document.getElementById('product_image_upload');
    const imagePreview = document.getElementById('imagePreview');
    const previewImage = document.getElementById('previewImage');
    const discountInput = document.getElementById('discount_percent');

    // Image preview functionality
    imageInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            
            reader.addEventListener('load', function() {
                previewImage.setAttribute('src', this.result);
                imagePreview.style.display = 'block';
            });
            
            reader.readAsDataURL(file);
        } else {
            imagePreview.style.display = 'none';
        }
    });

    // Form validation
    const form = document.getElementById('editProductForm');
    form.addEventListener('submit', function(e) {
        let valid = true;

        // Validate required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                valid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });

        // Validate discount percentage
        if (discountInput.value && (parseFloat(discountInput.value) < 0 || parseFloat(discountInput.value) > 100 || isNaN(parseFloat(discountInput.value)))) {
            valid = false;
            discountInput.classList.add('is-invalid');
        } else {
            discountInput.classList.remove('is-invalid');
        }

        if (!valid) {
            e.preventDefault();
            alert('Please fix the errors in the form before submitting.');
        }
    });

    // Real-time validation for discount
    discountInput.addEventListener('input', function() {
        if (this.value && (parseFloat(this.value) < 0 || parseFloat(this.value) > 100 || isNaN(parseFloat(this.value)))) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
});

function confirmDeleteProduct(productId, productName) {
    if (confirm('Are you sure you want to delete "' + productName + '"? This action cannot be undone.')) {
        window.location.href = 'product_delete.php?id=' + productId;
    }
}
</script>

<?php include '../includes/footer.php'; ?>