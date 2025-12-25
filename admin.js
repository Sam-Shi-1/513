// gamevault.js - Complete and Optimized JavaScript for GameVault
document.addEventListener('DOMContentLoaded', function() {
    console.log('GameVault website loaded successfully');
    
    // Initialize all core functionality
    initializeCart();
    initializeFormValidation();
    initializeAnimations();
    updateCartCountFromServer();

    // Initialize quantity controls depending on page type
    if (document.querySelector('.cart-item')) {
        initializeCartQuantityControls(); // Cart page uses auto-update
    } else {
        initializeProductQuantityControls(); // Other pages use default functionality
    }

    // Product edit page specific initialization
    if (document.getElementById('editProductForm')) {
        initializeProductEditPage();
    }
});

// ==================== CART FUNCTIONALITY ====================
function initializeCart() {
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    addToCartButtons.forEach(button => {
        button.replaceWith(button.cloneNode(true));
    });

    const newAddToCartButtons = document.querySelectorAll('.add-to-cart');
    newAddToCartButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); 

            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            const productPrice = parseFloat(this.dataset.productPrice);
            const productImage = this.dataset.productImage || 'default.jpg';

            let quantity = 1;
            const quantityInput = document.getElementById('quantity');
            
            if (quantityInput) {
                quantity = parseInt(quantityInput.value) || 1;
            }
            
            addToCart(productId, productName, productPrice, productImage, quantity, this);
        });
    });

    // Initialize quantity controls
    initializeQuantityControls();
}

function addToCart(productId, productName, productPrice, productImage, quantity = 1, button = null) {
    const data = {
        product_id: productId,
        product_name: productName,
        price: productPrice,
        product_image: productImage,
        quantity: quantity
    };

    if (button) {
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        button.disabled = true;
    }

    fetch('/513week7/cart/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`"${productName}" added to cart successfully!`, 'success');
            updateCartCounter(data.cart_count, data.total_quantity);

            if (button) {
                button.innerHTML = '<i class="fas fa-check"></i> Added!';
                button.classList.add('btn-success');
                button.classList.remove('btn-primary');
                
                setTimeout(() => {
                    button.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
                    button.classList.remove('btn-success');
                    button.classList.add('btn-primary');
                    button.disabled = false;
                }, 2000);
            }
        } else {
            showAlert('Failed to add product to cart: ' + data.message, 'danger');
            if (button) {
                button.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
                button.disabled = false;
            }
        }
    })
    .catch(error => {
        console.error('Error adding to cart:', error);
        showAlert('Network error, please try again', 'danger');
        if (button) {
            button.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
            button.disabled = false;
        }
    });
}

// ==================== QUANTITY CONTROLS ====================

// Main initializer - choose correct controls based on page type
function initializeQuantityControls() {
    if (document.querySelector('.cart-item')) {
        // Cart page: use auto-update
        initializeCartPageQuantityControls();
    } else {
        // Product pages: use simple controls
        initializeSimpleQuantityControls();
    }
}

// Cart page quantity controls (with real-time updates)
function initializeCartPageQuantityControls() {
    console.log('Initializing cart page quantity controls');
    
    // Add auto-save feature
    let saveTimeout = null;
    
    // Auto-save function
    function autoSaveQuantity(inputElement) {
        // Clear previous timer
        if (saveTimeout) {
            clearTimeout(saveTimeout);
        }
        
        // Set a new timer to save after 1 second
        saveTimeout = setTimeout(() => {
            updateCartQuantity(inputElement);
        }, 1000);
    }
    
    // Increment button
    document.querySelectorAll('.cart-item .increment-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const input = this.closest('.input-group').querySelector('.quantity-input');
            const max = parseInt(input.getAttribute('max')) || 99;
            const currentValue = parseInt(input.value) || 1;
            if (currentValue < max) {
                input.value = currentValue + 1;
                updateCartItemPrice(input);
                autoSaveQuantity(input);
            }
        });
    });
    
    // Decrement button
    document.querySelectorAll('.cart-item .decrement-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const input = this.closest('.input-group').querySelector('.quantity-input');
            const min = parseInt(input.getAttribute('min')) || 1;
            const currentValue = parseInt(input.value) || 1;
            if (currentValue > min) {
                input.value = currentValue - 1;
                updateCartItemPrice(input);
                autoSaveQuantity(input);
            } else if (currentValue === 1) {
                removeCartItem(input);
            }
        });
    });

    // Quantity input fields
    document.querySelectorAll('.cart-item .quantity-input').forEach(input => {
        input.addEventListener('change', function() {
            const min = parseInt(this.getAttribute('min')) || 1;
            const max = parseInt(this.getAttribute('max')) || 99;
            let value = parseInt(this.value) || min;
            
            if (value < min) value = min;
            if (value > max) value = max;
            
            this.value = value;
            updateCartItemPrice(this);
            updateCartQuantity(this);
        });
        
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            // Real-time update of price display
            const value = parseInt(this.value) || 1;
            if (value >= 1 && value <= 99) {
                updateCartItemPrice(this);
            }
        });
        
        // Save when input loses focus
        input.addEventListener('blur', function() {
            const min = parseInt(this.getAttribute('min')) || 1;
            const max = parseInt(this.getAttribute('max')) || 99;
            let value = parseInt(this.value) || min;
            
            if (value < min) value = min;
            if (value > max) value = max;
            
            this.value = value;
            updateCartItemPrice(this);
            updateCartQuantity(this);
        });
    });

    // Update order summary once on initialization
    updateOrderSummary();
}

// Simple quantity controls (for product pages)
function initializeSimpleQuantityControls() {
    console.log('Initializing simple quantity controls');
    
    // Increment button
    document.querySelectorAll('.increment-btn:not(.cart-item .increment-btn)').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const input = this.closest('.input-group').querySelector('.quantity-input');
            const max = parseInt(input.getAttribute('max')) || 99;
            const currentValue = parseInt(input.value) || 1;
            if (currentValue < max) {
                input.value = currentValue + 1;
            }
        });
    });
    
    // Decrement button
    document.querySelectorAll('.decrement-btn:not(.cart-item .decrement-btn)').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const input = this.closest('.input-group').querySelector('.quantity-input');
            const min = parseInt(input.getAttribute('min')) || 1;
            const currentValue = parseInt(input.value) || 1;
            if (currentValue > min) {
                input.value = currentValue - 1;
            }
        });
    });

    // Quantity input fields
    document.querySelectorAll('.quantity-input:not(.cart-item .quantity-input)').forEach(input => {
        input.addEventListener('change', function() {
            const min = parseInt(this.getAttribute('min')) || 1;
            const max = parseInt(this.getAttribute('max')) || 99;
            let value = parseInt(this.value) || min;
            
            if (value < min) value = min;
            if (value > max) value = max;
            
            this.value = value;
        });
        
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    });
}

// Update cart item subtotal
function updateCartItemPrice(inputElement) {
    const cartItem = inputElement.closest('.cart-item');
    const quantity = parseInt(inputElement.value);
    
    // Get unit price
    const priceElement = cartItem.querySelector('.text-primary');
    if (!priceElement) return;
    
    const priceText = priceElement.textContent;
    const unitPrice = parseFloat(priceText.replace('$', '').replace(' each', ''));
    
    // Calculate item subtotal
    const itemSubtotal = unitPrice * quantity;
    
    // Update item subtotal display
    const subtotalElement = cartItem.querySelector('.h5');
    if (subtotalElement) {
        subtotalElement.textContent = `$${itemSubtotal.toFixed(2)}`;
    }
    
    // Update order total
    updateOrderSummary();
}

// Update order total
function updateOrderSummary() {
    let subtotal = 0;
    let totalItems = 0;
    
    // Calculate subtotals and total quantity for all items
    document.querySelectorAll('.cart-item').forEach(item => {
        const quantityInput = item.querySelector('.quantity-input');
        const priceElement = item.querySelector('.text-primary');
        
        if (quantityInput && priceElement) {
            const quantity = parseInt(quantityInput.value);
            const priceText = priceElement.textContent;
            const unitPrice = parseFloat(priceText.replace('$', '').replace(' each', ''));
            
            subtotal += unitPrice * quantity;
            totalItems += quantity;
        }
    });
    
    // Calculate tax and total
    const tax = subtotal * 0.1; // 10% tax
    const total = subtotal + tax;
    
    // Update order summary display
    const orderSummary = document.querySelector('.order-summary');
    if (!orderSummary) return;
    
    const summaryElements = orderSummary.querySelectorAll('.d-flex.justify-content-between');
    const totalElement = orderSummary.querySelector('.h5');
    
    if (summaryElements.length >= 2) {
        // Update subtotal
        const subtotalElement = summaryElements[0].querySelector('span:last-child');
        if (subtotalElement) subtotalElement.textContent = `$${subtotal.toFixed(2)}`;
        
        // Update tax
        const taxElement = summaryElements[1].querySelector('span:last-child');
        if (taxElement) taxElement.textContent = `$${tax.toFixed(2)}`;
    }
    
    // Update total
    if (totalElement) totalElement.textContent = `$${total.toFixed(2)}`;
    
    // Update items count display
    const itemsCountElement = orderSummary.querySelector('.d-flex.justify-content-between span:first-child');
    if (itemsCountElement) {
        itemsCountElement.textContent = `Items (${totalItems}):`;
    }
    
    // Update cart icon count
    updateCartCounter(totalItems, totalItems);
}

// Update cart quantity on server
function updateCartQuantity(inputElement) {
    const form = inputElement.closest('.quantity-form');
    if (!form) return;
    
    const productIdInput = form.querySelector('input[name="product_id"]');
    if (!productIdInput) return;
    
    const productId = productIdInput.value;
    const quantity = parseInt(inputElement.value);
    
    // Disable inputs and buttons to prevent duplicate submissions
    const inputs = form.querySelectorAll('input, button');
    inputs.forEach(input => input.disabled = true);
    
    // Show loading state
    const originalHTML = inputElement.closest('.input-group').innerHTML;
    inputElement.closest('.input-group').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Updating...</div>';
    
    // Send AJAX request to update cart
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `update_quantity=1&product_id=${productId}&quantity=${quantity}`
    })
    .then(response => {
        // Reload the page to ensure data synchronization
        window.location.reload();
    })
    .catch(error => {
        console.error('Error updating cart quantity:', error);
        // Restore original state
        inputElement.closest('.input-group').innerHTML = originalHTML;
        // Re-enable inputs
        inputs.forEach(input => input.disabled = false);
        // Re-initialize events
        initializeCartPageQuantityControls();
        showAlert('Error updating quantity. Please try again.', 'danger');
    });
}

function removeCartItem(inputElement) {
    const form = inputElement.closest('.quantity-form');
    if (!form) return;
    
    const productIdInput = form.querySelector('input[name="product_id"]');
    if (!productIdInput) return;
    
    const productId = productIdInput.value;
    
    if (confirm('Are you sure you want to remove this item from your cart?')) {
        window.location.href = `?remove=${productId}`;
    } else {
        // If user cancels, reset quantity to 1
        inputElement.value = 1;
        // Update item subtotal and order total
        updateCartItemPrice(inputElement);
    }
}

function updateCartCounter(cartCount, totalQuantity) {
    const displayCount = cartCount !== undefined ? cartCount : (totalQuantity !== undefined ? totalQuantity : 0);
    
    const cartCounters = document.querySelectorAll('.cart-counter, .navbar .badge.bg-danger');
    
    cartCounters.forEach(counter => {
        const oldCount = counter.textContent;
        counter.textContent = displayCount;

        if (oldCount !== displayCount.toString()) {
            counter.style.transform = 'scale(1.5)';
            setTimeout(() => {
                counter.style.transform = 'scale(1)';
            }, 300);
        }
    });

    // Create counter if it doesn't exist
    if (cartCounters.length === 0) {
        const cartLink = document.querySelector('a[href*="cart"]');
        if (cartLink) {
            let counter = cartLink.querySelector('.badge');
            if (!counter) {
                counter = document.createElement('span');
                counter.className = 'cart-counter badge bg-danger';
                cartLink.appendChild(counter);
            }
            counter.textContent = displayCount;
        }
    }
}

function updateCartCountFromServer() {
    fetch('/513week7/cart/get_cart_count.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateCartCounter(data.cart_count, data.total_quantity);
            }
        })
        .catch(error => {
            console.error('Error fetching cart count:', error);
            updateCartCounter(0, 0);
        });
}

// ==================== FORM VALIDATION ====================
function initializeFormValidation() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let valid = true;
            
            // Required fields validation
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('is-invalid');
                    
                    if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'This field is required';
                        field.parentNode.appendChild(errorDiv);
                    }
                } else {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                    
                    const errorDiv = field.nextElementSibling;
                    if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                        errorDiv.remove();
                    }
                }
            });
            
            // Email validation
            const emailFields = this.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value && !isValidEmail(field.value)) {
                    valid = false;
                    field.classList.add('is-invalid');
                    if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'Please enter a valid email address';
                        field.parentNode.appendChild(errorDiv);
                    }
                }
            });
            
            // Password confirmation validation
            const passwordFields = this.querySelectorAll('input[type="password"]');
            if (passwordFields.length >= 2) {
                const password = passwordFields[0].value;
                const confirmPassword = passwordFields[1].value;
                
                if (password !== confirmPassword && confirmPassword !== '') {
                    valid = false;
                    passwordFields[1].classList.add('is-invalid');
                    if (!passwordFields[1].nextElementSibling || !passwordFields[1].nextElementSibling.classList.contains('invalid-feedback')) {
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'invalid-feedback';
                        errorDiv.textContent = 'Passwords do not match';
                        passwordFields[1].parentNode.appendChild(errorDiv);
                    }
                }
            }
            
            if (!valid) {
                e.preventDefault();
                showAlert('Please fill in all required fields correctly', 'warning');
                
                // Scroll to first error
                const firstError = this.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                }
            }
        });
        
        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    validateField(this);
                }
            });
        });
    });
}

function validateField(field) {
    if (field.hasAttribute('required') && !field.value.trim()) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
    } else if (field.type === 'email' && field.value && !isValidEmail(field.value)) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
    } else {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        
        // Remove error message
        const errorDiv = field.nextElementSibling;
        if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
            errorDiv.remove();
        }
    }
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// ==================== ANIMATIONS ====================
function initializeAnimations() {
    // Fade-in animation for cards
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Hover effects for product cards
    const productCards = document.querySelectorAll('.card');
    productCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'transform 0.3s ease, box-shadow 0.3s ease';
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });
}

// ==================== UTILITY FUNCTIONS ====================
function showAlert(message, type) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.auto-alert');
    existingAlerts.forEach(alert => alert.remove());
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `auto-alert alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// ==================== PRODUCT EDIT PAGE FUNCTIONS ====================
function confirmDeleteProduct(productId, productName) {
    if (confirm('Are you sure you want to delete "' + productName + '"? This action cannot be undone.')) {
        window.location.href = 'product_delete.php?id=' + productId;
    }
}

function initializeProductEditPage() {
    const form = document.getElementById('editProductForm');
    const priceInput = document.getElementById('price');
    const stockInput = document.getElementById('stock_quantity');
    const imageInput = document.getElementById('product_image_upload');
    const deleteCheckbox = document.getElementById('delete_existing_image');

    // Image preview functionality
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('imagePreview');
            const previewImage = document.getElementById('previewImage');
            
            if (file) {
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, PNG, GIF, or WebP).');
                    this.value = '';
                    return;
                }

                const maxSize = 5 * 1024 * 1024; 
                if (file.size > maxSize) {
                    alert('Image file size must be less than 5MB.');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    }

    // Delete image confirmation
    if (deleteCheckbox) {
        deleteCheckbox.addEventListener('change', function(e) {
            if (this.checked) {
                if (!confirm('Are you sure you want to delete the current product image?')) {
                    this.checked = false;
                }
            }
        });
    }

    // Form validation
    if (form) {
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

            // Price validation
            if (priceInput && priceInput.value && (parseFloat(priceInput.value) <= 0 || isNaN(parseFloat(priceInput.value)))) {
                valid = false;
                priceInput.classList.add('is-invalid');
            } else if (priceInput) {
                priceInput.classList.remove('is-invalid');
            }

            // Stock validation
            if (stockInput && stockInput.value && (parseInt(stockInput.value) < 0 || isNaN(parseInt(stockInput.value)))) {
                valid = false;
                stockInput.classList.add('is-invalid');
            } else if (stockInput) {
                stockInput.classList.remove('is-invalid');
            }
            
            // Image file validation
            if (imageInput && imageInput.files.length > 0) {
                const file = imageInput.files[0];
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                const maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!allowedTypes.includes(file.type)) {
                    valid = false;
                    imageInput.classList.add('is-invalid');
                    alert('Please select a valid image file (JPG, PNG, GIF, or WebP).');
                } else if (file.size > maxSize) {
                    valid = false;
                    imageInput.classList.add('is-invalid');
                    alert('Image file size must be less than 5MB.');
                } else {
                    imageInput.classList.remove('is-invalid');
                }
            }
            
            if (!valid) {
                e.preventDefault();
                alert('Please fix the errors in the form before submitting.');
            }
        });

        // Real-time validation
        if (priceInput) {
            priceInput.addEventListener('input', function() {
                if (this.value && (parseFloat(this.value) <= 0 || isNaN(parseFloat(this.value)))) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        }
        
        if (stockInput) {
            stockInput.addEventListener('input', function() {
                if (this.value && (parseInt(this.value) < 0 || isNaN(parseInt(this.value)))) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        }
    }
}

/* ===============================
   FORUM ADMIN FUNCTIONS
   =============================== */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize forum admin features
    initForumAdmin();
    
    // Initialize post actions
    initPostActions();
    
    // Initialize bulk actions
    initBulkActions();
});

/**
 * Initialize forum admin features
 */
function initForumAdmin() {
    // Category management
    const categoryForm = document.getElementById('forumCategoryForm');
    if (categoryForm) {
        categoryForm.addEventListener('submit', validateCategoryForm);
    }
    
    // Topic management
    initTopicManagement();
    
    // User management
    initUserForumManagement();
}

/**
 * Validate category form
 */
function validateCategoryForm(e) {
    const form = e.target;
    const categoryName = form.querySelector('#category_name');
    const categoryOrder = form.querySelector('#category_order');
    
    let isValid = true;
    
    // Validate category name
    if (!categoryName.value.trim()) {
        showValidationError(categoryName, 'Category name is required');
        isValid = false;
    } else if (categoryName.value.length > 100) {
        showValidationError(categoryName, 'Category name must be less than 100 characters');
        isValid = false;
    } else {
        clearValidationError(categoryName);
    }
    
    // Validate order value
    if (categoryOrder.value && isNaN(categoryOrder.value)) {
        showValidationError(categoryOrder, 'Order must be a number');
        isValid = false;
    } else {
        clearValidationError(categoryOrder);
    }
    
    if (!isValid) {
        e.preventDefault();
        showToast('Please fix the errors in the form', 'error');
    }
}

/**
 * Initialize topic management
 */
function initTopicManagement() {
    // Toggle sticky/unsticky
    document.querySelectorAll('.btn-sticky-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const topicId = this.dataset.topicId;
            const isSticky = this.dataset.sticky === 'true';
            
            toggleTopicSticky(topicId, !isSticky);
        });
    });
    
    // Toggle lock/unlock
    document.querySelectorAll('.btn-lock-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const topicId = this.dataset.topicId;
            const isLocked = this.dataset.locked === 'true';
            
            toggleTopicLock(topicId, !isLocked);
        });
    });
    
    // Delete topic
    document.querySelectorAll('.btn-delete-topic').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const topicId = this.dataset.topicId;
            const topicTitle = this.dataset.topicTitle;
            
            confirmDeleteTopic(topicId, topicTitle);
        });
    });
    
    // Move topic
    document.querySelectorAll('.btn-move-topic').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const topicId = this.dataset.topicId;
            showMoveTopicModal(topicId);
        });
    });
}

/**
 * Toggle topic sticky state
 */
function toggleTopicSticky(topicId, makeSticky) {
    const action = makeSticky ? 'sticky' : 'unsticky';
    
    if (confirm(`Are you sure you want to ${action} this topic?`)) {
        fetch(`../admin/forum_admin.php?action=toggle_sticky&topic_id=${topicId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Topic ${action} successfully`, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'Operation failed', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error, please try again', 'error');
        });
    }
}

/**
 * Toggle topic lock state
 */
function toggleTopicLock(topicId, makeLocked) {
    const action = makeLocked ? 'lock' : 'unlock';
    
    if (confirm(`Are you sure you want to ${action} this topic?`)) {
        fetch(`../admin/forum_admin.php?action=toggle_lock&topic_id=${topicId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Topic ${action}ed successfully`, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'Operation failed', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error, please try again', 'error');
        });
    }
}

/**
 * Confirm delete topic
 */
function confirmDeleteTopic(topicId, topicTitle) {
    if (confirm(`Are you sure you want to delete the topic "${topicTitle}"?\n\nThis will delete all replies and cannot be undone!`)) {
        fetch(`../admin/forum_admin.php?action=delete_topic&topic_id=${topicId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Topic deleted successfully', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'Delete failed', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error, please try again', 'error');
        });
    }
}

/**
 * Show move-topic modal
 */
function showMoveTopicModal(topicId) {
    // You can create a modal here to display category list for selection
    // Simplified: use prompt directly
    const newCategoryId = prompt('Enter new category ID:');
    
    if (newCategoryId && !isNaN(newCategoryId)) {
        moveTopicToCategory(topicId, newCategoryId);
    }
}

/**
 * Move topic to new category
 */
function moveTopicToCategory(topicId, newCategoryId) {
    fetch(`../admin/forum_admin.php?action=move_topic&topic_id=${topicId}&new_category=${newCategoryId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Topic moved successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Move failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error, please try again', 'error');
    });
}

/**
 * Initialize post actions
 */
function initPostActions() {
    // Edit post
    document.querySelectorAll('.btn-edit-post').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const postId = this.dataset.postId;
            const postType = this.dataset.postType; // 'topic' or 'reply'
            editPost(postId, postType);
        });
    });
    
    // Delete reply
    document.querySelectorAll('.btn-delete-reply').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const replyId = this.dataset.replyId;
            const replyContent = this.dataset.replyContent?.substring(0, 50) + '...';
            
            if (confirm(`Delete this reply?\n\n${replyContent}`)) {
                deleteReply(replyId);
            }
        });
    });
    
    // Report post
    document.querySelectorAll('.btn-report-post').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const postId = this.dataset.postId;
            const postType = this.dataset.postType;
            showReportModal(postId, postType);
        });
    });
}

/**
 * Edit post
 */
function editPost(postId, postType) {
    const contentElement = document.querySelector(`.post-content[data-${postType}-id="${postId}"]`);
    if (!contentElement) return;
    
    const originalContent = contentElement.textContent;
    
    // Create edit area
    const textarea = document.createElement('textarea');
    textarea.className = 'form-control mb-2';
    textarea.value = originalContent;
    textarea.rows = 5;
    
    // Create buttons
    const buttonGroup = document.createElement('div');
    buttonGroup.className = 'd-flex gap-2';
    
    const saveBtn = document.createElement('button');
    saveBtn.className = 'btn btn-sm btn-primary';
    saveBtn.textContent = 'Save';
    
    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'btn btn-sm btn-secondary';
    cancelBtn.textContent = 'Cancel';
    
    buttonGroup.appendChild(saveBtn);
    buttonGroup.appendChild(cancelBtn);
    
    // Replace content
    const container = contentElement.parentElement;
    container.innerHTML = '';
    container.appendChild(textarea);
    container.appendChild(buttonGroup);
    
    // Save event
    saveBtn.addEventListener('click', function() {
        const newContent = textarea.value.trim();
        if (newContent && newContent !== originalContent) {
            savePostEdit(postId, postType, newContent);
        } else {
            container.innerHTML = `<div class="post-content" data-${postType}-id="${postId}">${originalContent}</div>`;
        }
    });
    
    // Cancel event
    cancelBtn.addEventListener('click', function() {
        container.innerHTML = `<div class="post-content" data-${postType}-id="${postId}">${originalContent}</div>`;
    });
}

/**
 * Save post edit
 */
function savePostEdit(postId, postType, newContent) {
    fetch(`../admin/forum_admin.php?action=edit_post`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            post_id: postId,
            post_type: postType,
            content: newContent
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Post updated successfully', 'success');
            const container = document.querySelector(`[data-${postType}-id="${postId}"]`).parentElement;
            container.innerHTML = `<div class="post-content" data-${postType}-id="${postId}">${newContent}</div>`;
        } else {
            showToast(data.message || 'Update failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error, please try again', 'error');
    });
}

/**
 * Delete reply
 */
function deleteReply(replyId) {
    fetch(`../admin/forum_admin.php?action=delete_reply&reply_id=${replyId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Reply deleted successfully', 'success');
            document.querySelector(`.reply-${replyId}`)?.remove();
        } else {
            showToast(data.message || 'Delete failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error, please try again', 'error');
    });
}

/**
 * Show report modal
 */
function showReportModal(postId, postType) {
    // Simplified: use prompt
    const reason = prompt('Please enter the reason for reporting this post:');
    
    if (reason) {
        reportPost(postId, postType, reason);
    }
}

/**
 * Report post
 */
function reportPost(postId, postType, reason) {
    fetch(`../admin/forum_admin.php?action=report_post`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            post_id: postId,
            post_type: postType,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Thank you for your report. Our moderators will review it.', 'success');
        } else {
            showToast(data.message || 'Report failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error, please try again', 'error');
    });
}

/**
 * Initialize bulk actions
 */
function initBulkActions() {
    const bulkActionForm = document.getElementById('bulkActionForm');
    if (!bulkActionForm) return;
    
    const bulkActionSelect = bulkActionForm.querySelector('.bulk-action-select');
    const applyBulkBtn = bulkActionForm.querySelector('.apply-bulk-action');
    
    applyBulkBtn.addEventListener('click', function() {
        const selectedAction = bulkActionSelect.value;
        const selectedItems = getSelectedItems();
        
        if (selectedItems.length === 0) {
            showToast('Please select at least one item', 'warning');
            return;
        }
        
        if (!selectedAction) {
            showToast('Please select an action', 'warning');
            return;
        }
        
        applyBulkAction(selectedAction, selectedItems);
    });
    
    // Select/Deselect all
    const selectAllCheckbox = bulkActionForm.querySelector('.select-all-checkbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const itemCheckboxes = bulkActionForm.querySelectorAll('.item-checkbox');
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
}

/**
 * Get selected items
 */
function getSelectedItems() {
    const selectedItems = [];
    document.querySelectorAll('.item-checkbox:checked').forEach(checkbox => {
        selectedItems.push(checkbox.value);
    });
    return selectedItems;
}

/**
 * Apply bulk action
 */
function applyBulkAction(action, items) {
    const actionMap = {
        'delete': 'delete',
        'move': 'move',
        'sticky': 'toggle_sticky',
        'lock': 'toggle_lock'
    };
    
    const actionText = {
        'delete': 'delete',
        'move': 'move',
        'sticky': 'sticky/unsticky',
        'lock': 'lock/unlock'
    };
    
    if (confirm(`Are you sure you want to ${actionText[action]} ${items.length} item(s)?`)) {
        fetch(`../admin/forum_admin.php?action=bulk_${action}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ items: items })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Bulk action completed successfully`, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'Bulk action failed', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error, please try again', 'error');
        });
    }
}

/**
 * Initialize user forum management
 */
function initUserForumManagement() {
    // Ban/unban user
    document.querySelectorAll('.btn-user-ban').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const userId = this.dataset.userId;
            const userName = this.dataset.userName;
            const isBanned = this.dataset.banned === 'true';
            
            const action = isBanned ? 'unban' : 'ban';
            const duration = prompt(`Enter ban duration in days (0 for permanent):`);
            
            if (duration !== null) {
                if (isNaN(duration) || duration < 0) {
                    showToast('Please enter a valid number', 'error');
                    return;
                }
                
                toggleUserBan(userId, userName, action, duration);
            }
        });
    });
    
    // Reset user post count
    document.querySelectorAll('.btn-reset-stats').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const userId = this.dataset.userId;
            
            if (confirm('Reset user forum statistics?')) {
                resetUserStats(userId);
            }
        });
    });
}

/**
 * Toggle user ban state
 */
function toggleUserBan(userId, userName, action, duration) {
    fetch(`../admin/forum_admin.php?action=user_${action}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: userId,
            duration: duration
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const actionText = action === 'ban' ? 'banned' : 'unbanned';
            showToast(`User ${userName} ${actionText} successfully`, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Operation failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error, please try again', 'error');
    });
}

/**
 * Reset user stats
 */
function resetUserStats(userId) {
    fetch(`../admin/forum_admin.php?action=reset_user_stats`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: userId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('User statistics reset successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Reset failed', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Network error, please try again', 'error');
    });
}

/**
 * Show validation error
 */
function showValidationError(element, message) {
    element.classList.add('is-invalid');
    
    let feedback = element.nextElementSibling;
    if (!feedback || !feedback.classList.contains('invalid-feedback')) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        element.parentElement.appendChild(feedback);
    }
    feedback.textContent = message;
}

/**
 * Clear validation error
 */
function clearValidationError(element) {
    element.classList.remove('is-invalid');
    
    const feedback = element.nextElementSibling;
    if (feedback && feedback.classList.contains('invalid-feedback')) {
        feedback.remove();
    }
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info') {
    // Check if toast container exists
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        `;
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    const toast = document.createElement('div');
    toast.className = `toast show border-0 shadow`;
    toast.style.cssText = `
        min-width: 300px;
        margin-bottom: 10px;
        background-color: ${getToastColor(type)};
        color: white;
    `;
    
    const toastBody = document.createElement('div');
    toastBody.className = 'toast-body d-flex align-items-center';
    
    const icon = document.createElement('i');
    icon.className = getToastIcon(type);
    icon.style.marginRight = '10px';
    
    const text = document.createElement('span');
    text.textContent = message;
    
    toastBody.appendChild(icon);
    toastBody.appendChild(text);
    toast.appendChild(toastBody);
    
    toastContainer.appendChild(toast);
    
    // Auto-remove after 5s
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

/**
 * Get toast color
 */
function getToastColor(type) {
    const colors = {
        'success': '#28a745',
        'error': '#dc3545',
        'warning': '#ffc107',
        'info': '#17a2b8'
    };
    return colors[type] || '#6c757d';
}

/**
 * Get toast icon
 */
function getToastIcon(type) {
    const icons = {
        'success': 'fas fa-check-circle',
        'error': 'fas fa-exclamation-circle',
        'warning': 'fas fa-exclamation-triangle',
        'info': 'fas fa-info-circle'
    };
    return icons[type] || 'fas fa-info-circle';
}

/* ===============================
   FORUM ADMIN AJAX ENDPOINT
   =============================== */

/**
 * Forum admin AJAX endpoint (requires forum_admin.php)
 * This function serves as a template for forum_admin.php
 */
function handleForumAdminAjax() {
    // This should be implemented in forum_admin.php
    // This is a client-side placeholder for calls
    console.log('Forum admin AJAX functions initialized');
}