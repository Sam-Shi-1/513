<?php
$current_dir_level = 1;
include '../includes/header.php';

// Remove DB config include since we no longer need a database connection
// require_once '../config/config.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$product_id = intval($_GET['id']);

// Read product data from JSON file
$json_file = '../data/products.json';
$product = null;
$all_products = [];

if (file_exists($json_file)) {
    $json_content = file_get_contents($json_file);
    $data = json_decode($json_content, true);
    
    if ($data && isset($data['products'])) {
        $all_products = $data['products'];
        
        // Find the product with the specified ID
        foreach ($all_products as $item) {
            if (intval($item['product_id']) === $product_id) {
                $product = $item;
                break;
            }
        }
    }
}

// If the product is not found or is inactive
if (!$product || ($product['is_active'] ?? 1) == 0) {
    echo "<div class='alert alert-danger'>Product not found.</div>";
    include '../includes/footer.php';
    exit;
}

//Simulate the category_name field and set it based on the category_id
$category_names = [
    3 => 'Gaming Peripherals',
    4 => 'Game Points',
    6 => 'Game Currency',
    7 => 'Collectibles',
    9 => 'Game Items'
];

$category_id = $product['category_id'] ?? 0;
$product['category_name'] = $category_names[$category_id] ?? 'Unknown Category';

//Calculate discount price
$hasDiscount = isset($product['discount_percent']) && floatval($product['discount_percent']) > 0;
$originalPrice = floatval($product['price']);
$discountedPrice = $hasDiscount ? 
    $originalPrice * (1 - floatval($product['discount_percent']) / 100) : 
    $originalPrice;
?>

<div class="row">
    <div class="col-md-6">
    <?php
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $project_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__DIR__)));
    $image_url = $base_url . $project_path . '/assets/images/products/' . $product['image_url'];
    $default_image_url = $base_url . $project_path . '/assets/images/products/default.jpg';

    if (!empty($product['image_url'])) {
        $product_image = $image_url;
    } else {
        $product_image = $default_image_url;
    }
    ?>
    <img src="<?php echo $product_image; ?>" 
         class="img-fluid rounded" 
         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
         onerror="this.src='<?php echo $default_image_url; ?>'">
    </div>
    <div class="col-md-6">
        <h1><?php echo htmlspecialchars($product['product_name']); ?></h1>
        <p class="text-muted">Category: <?php echo htmlspecialchars($product['category_name']); ?></p>
        
        <div class="mb-3">
            <?php if ($hasDiscount): ?>
                <div class="d-flex align-items-center">
                    <span class="text-muted text-decoration-line-through me-2 h4">$<?php echo number_format($originalPrice, 2); ?></span>
                    <span class="h2 text-primary">$<?php echo number_format($discountedPrice, 2); ?></span>
                    <span class="badge bg-danger ms-2">-<?php echo $product['discount_percent']; ?>%</span>
                </div>
            <?php else: ?>
                <span class="h2 text-primary">$<?php echo number_format($originalPrice, 2); ?></span>
            <?php endif; ?>
            
            <?php if($product['product_type'] == 'cdk'): ?>
                <span class="badge bg-info ms-2">CD Key Product</span>
            <?php elseif($product['product_type'] == 'virtual'): ?>
                <span class="badge bg-success ms-2">Virtual Product</span>
            <?php else: ?>
                <span class="badge bg-secondary ms-2">Physical Product</span>
            <?php endif; ?>
        </div>
        
        <div class="mb-3">
            <span class="<?php echo $product['stock_quantity'] > 0 ? 'text-success' : 'text-danger'; ?>">
                <i class="fas fa-<?php echo $product['stock_quantity'] > 0 ? 'check' : 'times'; ?>"></i>
                <?php echo $product['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock'; ?>
                (<?php echo $product['stock_quantity']; ?> available)
            </span>
        </div>
        
        <!-- Product details -->
        <div class="product-details mb-4">
            <div class="row mb-2">
                <div class="col-6">
                    <strong>Supplier:</strong> <?php echo htmlspecialchars($product['supplier'] ?? 'N/A'); ?>
                </div>
                <div class="col-6">
                    <strong>Region:</strong> <?php echo htmlspecialchars($product['region'] ?? 'Global'); ?>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-6">
                    <strong>Platform:</strong> <?php echo htmlspecialchars($product['platform'] ?? 'Multi-platform'); ?>
                </div>
                <div class="col-6">
                    <strong>Type:</strong> <?php echo ucfirst($product['product_type']); ?>
                </div>
            </div>
            <?php if(isset($product['is_featured']) && $product['is_featured']): ?>
                <div class="mb-2">
                    <span class="badge bg-warning text-dark"><i class="fas fa-star"></i> Featured Product</span>
                </div>
            <?php endif; ?>
        </div>
        
        <p class="mb-4"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
        
        <?php if($product['stock_quantity'] > 0): ?>
            <div class="row">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-lg add-to-cart" 
                    data-product-id="<?php echo $product['product_id']; ?>"
                    data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                    data-product-price="<?php echo $discountedPrice; ?>"
                    data-product-image="<?php echo $product['image_url']; ?>">
                <i class="fas fa-cart-plus"></i> Add to Cart
            </button>
        <?php else: ?>
            <button class="btn btn-secondary btn-lg" disabled>Out of Stock</button>
        <?php endif; ?>
        
        <div class="mt-4">
            <h5>Product Features</h5>
            <ul>
                <li>Instant delivery for CD Keys</li>
                <li>100% genuine products</li>
                <li>Secure payment processing</li>
                <li>24/7 customer support</li>
                <?php if($product['product_type'] == 'cdk'): ?>
                    <li>Digital delivery - no shipping required</li>
                <?php elseif($product['product_type'] == 'virtual'): ?>
                    <li>Virtual items delivered instantly</li>
                <?php else: ?>
                    <li>Fast shipping worldwide</li>
                    <li>High-quality physical merchandise</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<div class="row mt-5">
    <div class="col-12">
        <h3>Related Products</h3>
        <div class="row">
            <?php
            $related_products = [];
            
            // Find related products from all products (same category, exclude current product)
            if (!empty($all_products) && !empty($product['category_id'])) {
                foreach ($all_products as $item) {
                    // Exclude the current product and inactive products
                    if (intval($item['product_id']) === $product_id || 
                        ($item['is_active'] ?? 1) == 0) {
                        continue;
                    }
                    
                    // If it is the same category
                    if ($item['category_id'] == $product['category_id']) {
                        // Add category_name
                        $item_category_id = $item['category_id'] ?? 0;
                        $item['category_name'] = $category_names[$item_category_id] ?? 'Unknown Category';
                        
                        $related_products[] = $item;
                        
                        // Show up to 3
                        if (count($related_products) >= 3) {
                            break;
                        }
                    }
                }
            }
            
            foreach ($related_products as $related):
                $related_image_url = $base_url . $project_path . '/assets/images/products/' . $related['image_url'];
                $related_image = !empty($related['image_url']) ? $related_image_url : $default_image_url;
                
                // Calculate discount price for the related product
                $relatedHasDiscount = isset($related['discount_percent']) && floatval($related['discount_percent']) > 0;
                $relatedOriginalPrice = floatval($related['price']);
                $relatedDiscountedPrice = $relatedHasDiscount ? 
                    $relatedOriginalPrice * (1 - floatval($related['discount_percent']) / 100) : 
                    $relatedOriginalPrice;
            ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 product-card">
                    <div class="product-image-container">
                        <img src="<?php echo $related_image; ?>" 
                             class="card-img-top product-image" 
                             alt="<?php echo htmlspecialchars($related['product_name']); ?>"
                             onerror="this.src='<?php echo $default_image_url; ?>'">
                        <?php if(isset($related['is_featured']) && $related['is_featured']): ?>
                            <div class="featured-badge">
                                <i class="fas fa-star" title="Featured Product"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($relatedHasDiscount): ?>
                            <div class="discount-badge">
                                -<?php echo $related['discount_percent']; ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title"><?php echo htmlspecialchars($related['product_name']); ?></h5>
                            <?php if($related['product_type'] == 'cdk'): ?>
                                <span class="badge bg-info">CD Key</span>
                            <?php elseif($related['product_type'] == 'virtual'): ?>
                                <span class="badge bg-success">Virtual</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Physical</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-2">
                            <?php if ($relatedHasDiscount): ?>
                                <div class="d-flex align-items-center">
                                    <span class="text-muted text-decoration-line-through me-2">$<?php echo number_format($relatedOriginalPrice, 2); ?></span>
                                    <strong class="text-primary">$<?php echo number_format($relatedDiscountedPrice, 2); ?></strong>
                                </div>
                            <?php else: ?>
                                <strong class="text-primary">$<?php echo number_format($relatedOriginalPrice, 2); ?></strong>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-sm text-muted mb-2">
                            <strong>Category:</strong> <?php echo $related['category_name']; ?>
                        </div>
                        
                        <div class="mt-auto">
                            <a href="view.php?id=<?php echo $related['product_id']; ?>" class="btn btn-outline-primary w-100">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($related_products)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> No related products found.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add-to-cart functionality
    const addToCartButton = document.querySelector('.add-to-cart');
    
    if (addToCartButton) {
        addToCartButton.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            const productPrice = this.dataset.productPrice;
            const productImage = this.dataset.productImage;
            const quantityInput = document.getElementById('quantity');
            const quantity = quantityInput ? parseInt(quantityInput.value) : 1;

            if (quantity < 1) {
                showAlert('Please select a valid quantity', 'danger');
                return;
            }

            showAlert('"' + productName + '" added to cart!', 'success');
            updateCartCounter(quantity);
            addToCartOnServer(productId, productName, productPrice, productImage, quantity);
        });
    }
    
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '300px';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);

        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 3000);
    }
    
    function updateCartCounter(quantity = 1) {
        const cartCounter = document.querySelector('.navbar .badge');
        if (cartCounter) {
            const currentCount = parseInt(cartCounter.textContent) || 0;
            cartCounter.textContent = currentCount + quantity;

            cartCounter.style.transform = 'scale(1.5)';
            setTimeout(() => {
                cartCounter.style.transform = 'scale(1)';
            }, 300);
        } else {
            const cartLink = document.querySelector('a[href*="cart/index.php"]');
            if (cartLink) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-danger';
                badge.textContent = quantity;
                cartLink.appendChild(badge);
            }
        }
    }
    
    function addToCartOnServer(productId, productName, productPrice, productImage, quantity) {
        fetch('../cart/add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                product_name: productName,
                price: productPrice,
                product_image: productImage,
                quantity: quantity
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Failed to add to cart:', data.message);
                showAlert('Failed to add to cart: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error adding to cart:', error);
            showAlert('Error adding to cart. Please try again.', 'danger');
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>