<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/config.php';
?>

<?php 
$current_dir = '';
include 'includes/header.php'; 
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h1>About GameVault</h1>
                <p class="lead">Your trusted partner for genuine game products and CD keys.</p>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <h3>Our Mission</h3>
                        <p>At GameVault, we're passionate about gaming and committed to providing gamers with authentic, affordable, and instantly accessible game products. We believe every gamer deserves a reliable source for their gaming needs.</p>
                        
                        <h3>Our Values</h3>
                        <ul>
                            <li><strong>Authenticity:</strong> All our products are 100% genuine and sourced directly from publishers</li>
                            <li><strong>Speed:</strong> Instant delivery for digital products, fast shipping for physical goods</li>
                            <li><strong>Security:</strong> Your transactions and data are protected with industry-standard security</li>
                            <li><strong>Support:</strong> 24/7 customer support to help with any issues or questions</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h3>Why Choose Us?</h3>
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                <div>
                                    <h5 class="mb-1">Instant CD Key Delivery</h5>
                                    <p class="mb-0">Receive your CD keys immediately after purchase</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                <div>
                                    <h5 class="mb-1">100% Genuine Products</h5>
                                    <p class="mb-0">All products are official and authentic</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                <div>
                                    <h5 class="mb-1">Competitive Pricing</h5>
                                    <p class="mb-0">Best prices with regular discounts and promotions</p>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-check-circle text-success fa-2x me-3"></i>
                                <div>
                                    <h5 class="mb-1">Secure Payments</h5>
                                    <p class="mb-0">Safe and secure payment processing</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-5">
                    <div class="col-12">
                        <h3>Our Story</h3>
                        <p>Founded in 2024 by a team of passionate gamers, GameVault started as a small project to solve the problem of finding reliable sources for game CD keys. What began as a solution for our gaming community has grown into a trusted platform serving thousands of gamers worldwide.</p>
                        
                        <p>We understand the frustration of waiting for game keys or worrying about their authenticity. That's why we've built partnerships directly with game publishers and authorized distributors to ensure all our products are legitimate and fully supported.</p>
                        
                        <p>Our automated delivery system ensures you get your CD keys instantly, so you can get back to gaming without delay. For physical products, we've established efficient logistics networks to ensure fast and reliable shipping.</p>
                        
                        <h4 class="mt-4">Our Commitment</h4>
                        <p>We're committed to:</p>
                        <ul>
                            <li>Providing the fastest and most reliable CD key delivery service</li>
                            <li>Maintaining 100% authenticity for all our products</li>
                            <li>Offering competitive prices and regular promotions</li>
                            <li>Delivering exceptional customer service 24/7</li>
                            <li>Continuously expanding our product catalog</li>
                            <li>Ensuring a secure and user-friendly shopping experience</li>
                        </ul>
                    </div>
                </div>

                <div class="row mt-5">
                    <div class="col-12">
                        <h3>Our Location</h3>
                        <p>Visit our headquarters in North Sydney, Australia:</p>
                        
                        <!-- 嵌入式地图部分 -->
                        <div class="text-center mb-4">
                            <a href="https://maps.google.com/?q=Level+21/201+Miller+St,+North+Sydney+NSW+2060,+Australia" target="_blank">
                                <img 
                                    src="1.png" 
                                    alt="GameVault Location Map"
                                    class="img-fluid rounded shadow"
                                    style="max-width: 100%; height: auto;"
                                >
                            </a>
                            <p class="text-muted mt-2"><small>Click the map for directions</small></p>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Headquarters Address</h5>
                                <p>
                                    Level 21/201 Miller St<br>
                                    North Sydney NSW 2060<br>
                                    Australia
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h5>Contact Information</h5>
                                <p>
                                    <i class="fas fa-phone me-2"></i> +61 2 1234 5678<br>
                                    <i class="fas fa-envelope me-2"></i> contact@gamevault.com<br>
                                    <i class="fas fa-clock me-2"></i> Mon-Fri: 9:00 AM - 6:00 PM AEST
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-5">
                    <div class="col-md-4 text-center">
                        <div class="card border-0">
                            <div class="card-body">
                                <i class="fas fa-users fa-3x text-primary mb-3"></i>
                                <h4>10,000+</h4>
                                <p class="text-muted">Happy Customers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="card border-0">
                            <div class="card-body">
                                <i class="fas fa-gamepad fa-3x text-success mb-3"></i>
                                <h4>500+</h4>
                                <p class="text-muted">Game Titles</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="card border-0">
                            <div class="card-body">
                                <i class="fas fa-shipping-fast fa-3x text-warning mb-3"></i>
                                <h4>Instant</h4>
                                <p class="text-muted">CD Key Delivery</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-5">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h4><i class="fas fa-info-circle me-2"></i>Join Our Community</h4>
                            <p class="mb-0">Become part of our growing gaming community! Follow us on social media for the latest updates, exclusive deals, and gaming news.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>