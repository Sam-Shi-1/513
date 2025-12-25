<?php
$current_dir_level = 1;
include '../includes/header.php';
require_once '../config/config.php';

if (!isAdmin()) {
    header("Location: ../index.php");
    exit;
}

// JSON file path
$json_file_path = __DIR__ . '/../data/products.json';

// Categories array
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

//Read data from JSON file
function readProductsFromJson($file_path) {
    if (!file_exists($file_path)) {
        // If the file does not exist, create an empty one
        return ['products' => []];
    }
    
    $json_content = file_get_contents($file_path);
    if ($json_content === false) {
        return ['products' => []];
    }
    
    $data = json_decode($json_content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['products' => []];
    }
    
    return $data;
}

// Write data to JSON file
function writeProductsToJson($file_path, $data) {
    $json_content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (file_put_contents($file_path, $json_content, LOCK_EX) === false) {
        return ['error' => 'Failed to write to JSON file'];
    }
    
    return ['success' => true];
}

// Read existing product data
$products_data = readProductsFromJson($json_file_path);
$products = isset($products_data['products']) ? $products_data['products'] : [];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_name = $_POST['product_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $category_id = (int)($_POST['category_id'] ?? 0);
    $product_type = $_POST['product_type'] ?? 'cdk';
    $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $discount_percent = $_POST['discount_percent'] ?? 0;
    $supplier = $_POST['supplier'] ?? '';
    $region = $_POST['region'] ?? 'Global';
    $platform = $_POST['platform'] ?? 'Multi-platform';
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $image_url = '';

    if (empty($product_name) || empty($price) || empty($category_id)) {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($price) || $price <= 0) {
        $error = "Please enter a valid price.";
    } elseif (!is_numeric($stock_quantity) || $stock_quantity < 0) {
        $error = "Please enter a valid stock quantity.";
    } elseif (!is_numeric($discount_percent) || $discount_percent < 0 || $discount_percent > 100) {
        $error = "Please enter a valid discount percentage (0-100).";
    } else {
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleImageUpload($_FILES['product_image']);
            if ($upload_result['success']) {
                $image_url = $upload_result['filename'];
            } else {
                $error = $upload_result['error'];
            }
        }

        if (empty($error)) {
            // Generate a new product ID
            $new_product_id = 1;
            if (!empty($products)) {
                // Find the largest product_id and add 1
                $product_ids = array_column($products, 'product_id');
                $new_product_id = max($product_ids) + 1;
            }

            // Create new product array
            $new_product = [
                'product_id' => $new_product_id,
                'product_name' => $product_name,
                'description' => $description,
                'price' => (string)$price,
                'category_id' => $category_id,
                'product_type' => $product_type,
                'stock_quantity' => $stock_quantity,
                'image_url' => $image_url,
                'is_active' => $is_active,
                'created_at' => date('Y-m-d H:i:s'),
                'discount_percent' => (string)$discount_percent,
                'supplier' => $supplier,
                'region' => $region,
                'platform' => $platform,
                'is_featured' => $is_featured
            ];

            // Append new product to products array
            $products[] = $new_product;
            
            // Write updated products back to JSON file
            $write_result = writeProductsToJson($json_file_path, ['products' => $products]);
            
            if (isset($write_result['success'])) {
                $success = "Product added successfully!" . (!empty($image_url) ? " Product image uploaded successfully." : "");
                // Clear form data
                $_POST = [];
            } else {
                $error = "Failed to save product to JSON file: " . ($write_result['error'] ?? 'Unknown error');
            }
        }
    }
}

function handleImageUpload($file) {
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
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Add New Product</h1>
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
                <form method="POST" id="addProductForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="product_name" class="form-label">Product Name *</label>
                                <input type="text" class="form-control" id="product_name" name="product_name" 
                                       value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-control" id="category_id" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>" 
                                            <?php echo (($_POST['category_id'] ?? '') == $category['category_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="supplier" class="form-label">Supplier</label>
                                <input type="text" class="form-control" id="supplier" name="supplier" 
                                       value="<?php echo htmlspecialchars($_POST['supplier'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="platform" class="form-label">Platform</label>
                                <select class="form-control" id="platform" name="platform">
                                    <option value="Multi-platform" <?php echo (($_POST['platform'] ?? 'Multi-platform') == 'Multi-platform') ? 'selected' : ''; ?>>Multi-platform</option>
                                    <option value="PC" <?php echo (($_POST['platform'] ?? '') == 'PC') ? 'selected' : ''; ?>>PC</option>
                                    <option value="Steam" <?php echo (($_POST['platform'] ?? '') == 'Steam') ? 'selected' : ''; ?>>Steam</option>
                                    <option value="Mobile" <?php echo (($_POST['platform'] ?? '') == 'Mobile') ? 'selected' : ''; ?>>Mobile</option>
                                    <option value="PlayStation" <?php echo (($_POST['platform'] ?? '') == 'PlayStation') ? 'selected' : ''; ?>>PlayStation</option>
                                    <option value="Xbox" <?php echo (($_POST['platform'] ?? '') == 'Xbox') ? 'selected' : ''; ?>>Xbox</option>
                                    <option value="Nintendo" <?php echo (($_POST['platform'] ?? '') == 'Nintendo') ? 'selected' : ''; ?>>Nintendo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="region" class="form-label">Region</label>
                                <select class="form-control" id="region" name="region">
                                    <option value="Global" <?php echo (($_POST['region'] ?? 'Global') == 'Global') ? 'selected' : ''; ?>>Global</option>
                                    <option value="North America" <?php echo (($_POST['region'] ?? '') == 'North America') ? 'selected' : ''; ?>>North America</option>
                                    <option value="Europe" <?php echo (($_POST['region'] ?? '') == 'Europe') ? 'selected' : ''; ?>>Europe</option>
                                    <option value="Asia" <?php echo (($_POST['region'] ?? '') == 'Asia') ? 'selected' : ''; ?>>Asia</option>
                                    <option value="China" <?php echo (($_POST['region'] ?? '') == 'China') ? 'selected' : ''; ?>>China</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="discount_percent" class="form-label">Discount Percent</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="discount_percent" name="discount_percent" 
                                           min="0" max="100" step="0.01" value="<?php echo htmlspecialchars($_POST['discount_percent'] ?? '0'); ?>">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="product_image" class="form-label">Product Image</label>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No product image currently set. Upload an image to display it here.
                        </div>

                        <div class="mb-3">
                            <label for="product_image_upload" class="form-label">Upload Product Image</label>
                            <input type="file" class="form-control" id="product_image_upload" name="product_image" 
                                   accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                            <div class="form-text">
                                Allowed formats: JPG, PNG, GIF, WebP. Maximum file size: 5MB.
                            </div>
                        </div>

                        <div id="imagePreview" class="mt-3" style="display: none;">
                            <p class="text-muted mb-2">Image Preview:</p>
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
                                           step="0.01" min="0.01" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="stock_quantity" class="form-label">Stock Quantity *</label>
                                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" 
                                       min="0" value="<?php echo htmlspecialchars($_POST['stock_quantity'] ?? '0'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="product_type" class="form-label">Product Type *</label>
                                <select class="form-control" id="product_type" name="product_type" required>
                                    <option value="cdk" <?php echo (($_POST['product_type'] ?? 'cdk') == 'cdk') ? 'selected' : ''; ?>>CD Key</option>
                                    <option value="physical" <?php echo (($_POST['product_type'] ?? '') == 'physical') ? 'selected' : ''; ?>>Physical Product</option>
                                    <option value="virtual" <?php echo (($_POST['product_type'] ?? 'virtual') == 'virtual') ? 'selected' : ''; ?>>Virtual Product</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" 
                                           <?php echo (($_POST['is_featured'] ?? 0) == 1) ? 'checked' : ''; ?>>>
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
                                           <?php echo (($_POST['is_active'] ?? 1) == 1) ? 'checked' : ''; ?>>
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
                        <button type="reset" class="btn btn-secondary">Reset</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Tips</h5>
            </div>
            <div class="card-body">
                <h6>Product Types:</h6>
                <ul class="small">
                    <li><strong>CD Key:</strong> Digital product, instant delivery</li>
                    <li><strong>Physical Product:</strong> Requires shipping</li>
                    <li><strong>Virtual Product:</strong> In-game items or currency</li>
                </ul>
                
                <h6>Pricing:</h6>
                <ul class="small">
                    <li>Enter price in USD</li>
                    <li>Use decimal values (e.g., 29.99)</li>
                    <li>Discount percent: 0-100% range</li>
                </ul>
                
                <h6>Stock Management:</h6>
                <ul class="small">
                    <li>Set to 0 for out-of-stock items</li>
                    <li>CD Keys: Set high quantity for unlimited</li>
                </ul>

                <h6>Platform & Region:</h6>
                <ul class="small">
                    <li>Select appropriate platform for the product</li>
                    <li>Choose region availability</li>
                    <li>Global is default for wide availability</li>
                </ul>

                <h6>Image Upload:</h6>
                <ul class="small">
                    <li>Recommended size: 500x500 pixels</li>
                    <li>Use square images for best results</li>
                    <li>Images will be automatically optimized</li>
                </ul>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Recent Products</h5>
            </div>
            <div class="card-body">
                <?php
                // Retrieve recent products from JSON
                $recent_products = [];
                if (!empty($products)) {
                    //Sort by creation time to get the latest 3 products
                    usort($products, function($a, $b) {
                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                    });
                    $recent_products = array_slice($products, 0, 3);
                }
                
                // If there is no product, display example data
                if (empty($recent_products)) {
                    $recent_products = [
                        ['product_name' => 'League of Legends 1350 RP', 'price' => '68.00', 'created_at' => date('Y-m-d H:i:s')],
                        ['product_name' => 'Genshin Impact Genesis Crystals', 'price' => '98.00', 'created_at' => date('Y-m-d H:i:s')],
                        ['product_name' => 'Gaming Mechanical Keyboard', 'price' => '299.00', 'created_at' => date('Y-m-d H:i:s')]
                    ];
                }
                
                foreach ($recent_products as $product): 
                ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <div>
                            <strong class="small"><?php echo htmlspecialchars($product['product_name']); ?></strong>
                            <br>
                            <small class="text-muted">$<?php echo $product['price']; ?></small>
                        </div>
                        <small class="text-muted"><?php echo date('M j', strtotime($product['created_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addProductForm');
    const priceInput = document.getElementById('price');
    const stockInput = document.getElementById('stock_quantity');
    const discountInput = document.getElementById('discount_percent');
    const imageInput = document.getElementById('product_image_upload');
    const imagePreview = document.getElementById('imagePreview');
    const previewImage = document.getElementById('previewImage');

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

    form.addEventListener('submit', function(e) {
        let valid = true;

        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                valid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (priceInput.value && (parseFloat(priceInput.value) <= 0 || isNaN(parseFloat(priceInput.value)))) {
            valid = false;
            priceInput.classList.add('is-invalid');
        } else {
            priceInput.classList.remove('is-invalid');
        }

        if (stockInput.value && (parseInt(stockInput.value) < 0 || isNaN(parseInt(stockInput.value)))) {
            valid = false;
            stockInput.classList.add('is-invalid');
        } else {
            stockInput.classList.remove('is-invalid');
        }

        if (discountInput.value && (parseFloat(discountInput.value) < 0 || parseFloat(discountInput.value) > 100 || isNaN(parseFloat(discountInput.value)))) {
            valid = false;
            discountInput.classList.add('is-invalid');
        } else {
            discountInput.classList.remove('is-invalid');
        }

        if (imageInput.files.length > 0) {
            const file = imageInput.files[0];
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
            const maxSize = 5 * 1024 * 1024; 
            
            if (!allowedTypes.includes(file.type)) {
                valid = false;
                imageInput.classList.add('is-invalid');
                alert('Please select a valid image file (JPG, PNG, GIF, or WebP).');
            } else if (file.size > maxSize) {
                valid = false;
                imageInput.classList.add('is-invalid');
                alert('Image size must be less than 5MB.');
            } else {
                imageInput.classList.remove('is-invalid');
            }
        }
        
        if (!valid) {
            e.preventDefault();
            alert('Please fix the errors in the form before submitting.');
        }
    });

    priceInput.addEventListener('input', function() {
        if (this.value && (parseFloat(this.value) <= 0 || isNaN(parseFloat(this.value)))) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
    
    stockInput.addEventListener('input', function() {
        if (this.value && (parseInt(this.value) < 0 || isNaN(parseInt(this.value)))) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });

    discountInput.addEventListener('input', function() {
        if (this.value && (parseFloat(this.value) < 0 || parseFloat(this.value) > 100 || isNaN(parseFloat(this.value)))) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>