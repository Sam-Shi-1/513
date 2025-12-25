<?php
$current_dir_level = 1;
include '../includes/header.php';

// Remove database config include since DB connection is not required
// require_once '../config/config.php';

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$project_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__DIR__)));
$image_base_url = $base_url . $project_path . '/assets/images/products/';
$default_image_url = $image_base_url . 'default.jpg';

// Read product data from JSON file
$json_file = '../data/products.json'; // Assume JSON file is located in the data directory
$products = [];

if (file_exists($json_file)) {
    $json_content = file_get_contents($json_file);
    $data = json_decode($json_content, true);
    
    if ($data && isset($data['products'])) {
        $products = $data['products'];
        
        // Mock the category_name field, set according to category_id
        $category_names = [
            3 => 'Gaming Peripherals',
            4 => 'Game Points',
            6 => 'Game Currency',
            7 => 'Collectibles',
            9 => 'Game Items'
        ];
        
        foreach ($products as &$product) {
            $category_id = $product['category_id'] ?? 0;
            $product['category_name'] = $category_names[$category_id] ?? 'Unknown Category';
        }
        unset($product); // clear reference
    }
}

// Simulate is_active filter
$active_products = array_filter($products, function($product) {
    return ($product['is_active'] ?? 1) == 1;
});

// Sort by created_at (descending)
usort($active_products, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$filtered_products = $active_products;

// Category filtering
if (isset($_GET['category'])) {
    $category = $_GET['category'];
    if ($category === 'cdk') {
        $filtered_products = array_filter($active_products, function($product) {
            return $product['product_type'] === 'cdk';
        });
    } elseif ($category === 'physical') {
        $filtered_products = array_filter($active_products, function($product) {
            return $product['product_type'] === 'physical';
        });
    } elseif ($category === 'accessories') {
        $filtered_products = array_filter($active_products, function($product) {
            return $product['category_name'] === 'Gaming Peripherals';
        });
    } elseif ($category === 'virtual') {
        $filtered_products = array_filter($active_products, function($product) {
            return $product['product_type'] === 'virtual';
        });
    }
}

// Search functionality
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = strtolower($_GET['search']);
    $filtered_products = array_filter($filtered_products, function($product) use ($search_term) {
        return (stripos(strtolower($product['product_name']), $search_term) !== false) ||
               (stripos(strtolower($product['description']), $search_term) !== false) ||
               (stripos(strtolower($product['supplier']), $search_term) !== false);
    });
}

// Pagination settings - 9 products per page
$items_per_page = 9;
$total_items = count($filtered_products);
$total_pages = ceil($total_items / $items_per_page);

// Get current page number
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$current_page = min($current_page, $total_pages);

// Calculate start index
$start_index = ($current_page - 1) * $items_per_page;
$paginated_products = array_slice($filtered_products, $start_index, $items_per_page);

// Build base URL for pagination links
function buildPageUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return 'index.php?' . http_build_query($params);
}
?>

<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">
                <h6>Product Categories</h6>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="index.php" class="list-group-item list-group-item-action">All Products</a>
                    <a href="?category=cdk" class="list-group-item list-group-item-action">Game CD Keys</a>
                    <a href="?category=physical" class="list-group-item list-group-item-action">Physical Goods</a>
                    <a href="?category=accessories" class="list-group-item list-group-item-action">Gaming Peripherals</a>
                    <a href="?category=virtual" class="list-group-item list-group-item-action">Game Items</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="row mb-3">
            <div class="col">
                <form method="GET" class="d-flex">
                    <input type="text" name="search" class="form-control me-2" placeholder="Search products..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                </form>
            </div>
        </div>
        
        <div class="row">
            <?php
            if (count($paginated_products) > 0):
                foreach ($paginated_products as $product):
                    $hasDiscount = isset($product['discount_percent']) && floatval($product['discount_percent']) > 0;
                    $originalPrice = floatval($product['price']);
                    $discountedPrice = $hasDiscount ? 
                        $originalPrice * (1 - floatval($product['discount_percent']) / 100) : 
                        $originalPrice;

                    $product_image_url = !empty($product['image_url']) ? 
                        $image_base_url . $product['image_url'] : 
                        $default_image_url;
            ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card product-card h-100">
                    <!-- Product Image -->
                    <div class="product-image-container">
                        <img src="<?php echo $product_image_url; ?>" 
                             class="card-img-top product-image" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                             onerror="this.src='<?php echo $default_image_url; ?>'">
                        <?php if(isset($product['is_featured']) && $product['is_featured']): ?>
                            <div class="featured-badge">
                                <i class="fas fa-star" title="Featured Product"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($hasDiscount): ?>
                            <div class="discount-badge">
                                -<?php echo $product['discount_percent']; ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body d-flex flex-column">
                        <!-- Product Name and Badge -->
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                            <?php if($product['product_type'] == 'cdk'): ?>
                                <span class="badge bg-info">CD Key</span>
                            <?php elseif($product['product_type'] == 'virtual'): ?>
                                <span class="badge bg-success">Virtual</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Physical</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Product Description -->
                        <div class="card-text text-muted mb-2 description-container flex-grow-1">
                            <?php
                            $description = $product['description'] ?? $product['product_name'];
                            $maxLength = 100;
                            
                            if (strlen($description) > $maxLength):
                                $shortDescription = substr($description, 0, $maxLength) . '...';
                            ?>
                                <span class="description-short"><?php echo htmlspecialchars($shortDescription); ?></span>
                                <span class="description-full" style="display: none;"><?php echo htmlspecialchars($description); ?></span>
                                <a href="javascript:void(0);" class="read-more-link">Show more</a>
                            <?php else: ?>
                                <span><?php echo htmlspecialchars($description); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Price Information -->
                        <div class="mb-2">
                            <?php if ($hasDiscount): ?>
                                <div class="d-flex align-items-center">
                                    <span class="text-muted text-decoration-line-through me-2">$<?php echo number_format($originalPrice, 2); ?></span>
                                    <strong class="text-primary h5">$<?php echo number_format($discountedPrice, 2); ?></strong>
                                </div>
                            <?php else: ?>
                                <strong class="text-primary h5">$<?php echo number_format($originalPrice, 2); ?></strong>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Product Meta Information -->
                        <div class="product-meta mb-3">
                            <div class="row text-sm text-muted">
                                <div class="col-6">
                                    <strong>Stock:</strong> <?php echo $product['stock_quantity']; ?>
                                </div>
                                <div class="col-6">
                                    <strong>Category:</strong> <?php echo $product['category_name']; ?>
                                </div>
                            </div>
                            <div class="text-sm text-muted mt-1">
                                <strong>Supplier:</strong> <?php echo htmlspecialchars($product['supplier'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mt-auto">
                            <div class="d-grid gap-2">
                                <a href="view.php?id=<?php echo $product['product_id']; ?>" class="btn btn-outline-primary">View Details</a>
                                <?php if($product['stock_quantity'] > 0): ?>
                                    <button type="button" class="btn btn-primary add-to-cart" 
                                            data-product-id="<?php echo $product['product_id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                            data-product-price="<?php echo $discountedPrice; ?>"
                                            data-product-image="<?php echo $product['image_url']; ?>">
                                        Add to Cart
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>Out of Stock</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php 
                endforeach;
            else:
            ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">No products found</h5>
                        <p class="card-text">Try adjusting your search or filter criteria</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination navigation -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <!-- Previous page link -->
                <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $current_page > 1 ? buildPageUrl($current_page - 1) : '#'; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <!-- Page number links -->
                <?php
                // Show up to 5 page links
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $start_page + 4);
                
                // Adjust the start page to ensure 5 links are shown (if possible)
                if ($end_page - $start_page < 4 && $start_page > 1) {
                    $start_page = max(1, $end_page - 4);
                }
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo buildPageUrl($i); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                
                <!-- Next page link -->
                <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $current_page < $total_pages ? buildPageUrl($current_page + 1) : '#'; ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
            
            <!-- Pagination info -->
            <div class="text-center text-muted mt-2">
                Showing <?php echo ($start_index + 1); ?> to <?php echo min($start_index + $items_per_page, $total_items); ?> of <?php echo $total_items; ?> products
            </div>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            const productPrice = this.dataset.productPrice;
            const productImage = this.dataset.productImage;

            showAlert('"' + productName + '" added to cart!', 'success');
            updateCartCounter();
            addToCartOnServer(productId, productName, productPrice, productImage);
        });
    });
    
    // Add expand/collapse functionality for product descriptions
    const readMoreLinks = document.querySelectorAll('.read-more-link');
    readMoreLinks.forEach(link => {
        link.addEventListener('click', function() {
            const container = this.parentElement;
            const short = container.querySelector('.description-short');
            const full = container.querySelector('.description-full');
            
            if (short.style.display !== 'none') {
                short.style.display = 'none';
                full.style.display = 'inline';
                this.textContent = 'Show less';
            } else {
                short.style.display = 'inline';
                full.style.display = 'none';
                this.textContent = 'Show more';
            }
        });
    });
    
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
    
    function updateCartCounter() {
        const cartCounter = document.querySelector('.navbar .badge');
        if (cartCounter) {
            const currentCount = parseInt(cartCounter.textContent) || 0;
            cartCounter.textContent = currentCount + 1;

            cartCounter.style.transform = 'scale(1.5)';
            setTimeout(() => {
                cartCounter.style.transform = 'scale(1)';
            }, 300);
        } else {
            const cartLink = document.querySelector('a[href*="cart/index.php"]');
            if (cartLink) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-danger';
                badge.textContent = '1';
                cartLink.appendChild(badge);
            }
        }
    }
    
    function addToCartOnServer(productId, productName, productPrice, productImage) {
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
                quantity: 1
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Failed to add to cart:', data.message);
            }
        })
        .catch(error => {
            console.error('Error adding to cart:', error);
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>