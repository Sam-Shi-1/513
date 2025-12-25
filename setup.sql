-- Create database
CREATE DATABASE IF NOT EXISTS gamevault;
USE gamevault;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin') DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL,
    parent_category_id INT NULL,
    FOREIGN KEY (parent_category_id) REFERENCES categories(category_id)
);

-- Products table
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category_id INT,
    product_type ENUM('cdk', 'physical') NOT NULL,
    stock_quantity INT NOT NULL,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
);

-- CDK keys table
CREATE TABLE cdk_keys (
    cdk_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    cdk_code VARCHAR(255) NOT NULL,
    status ENUM('available', 'sold', 'used') DEFAULT 'available',
    order_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Orders table
CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Order items table
CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT NOT NULL,
    price_at_purchase DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Insert sample categories
INSERT INTO categories (category_name, parent_category_id) VALUES
('Game CD Keys', NULL),
('Physical Goods', NULL),
('Gaming Peripherals', NULL),
('League of Legends', 1),
('Genshin Impact', 1),
('PUBG', 1),
('Figures & Models', 2),
('Gaming Apparel', 2);

-- Insert sample products
INSERT INTO products (product_name, description, price, category_id, product_type, stock_quantity, image_url) VALUES
('League of Legends 1350 RP', 'League of Legends game points CD Key, instant delivery', 68.00, 4, 'cdk', 100, 'lol_points.jpg'),
('Genshin Impact Genesis Crystals', 'Genshin Impact in-game currency CD Key', 98.00, 5, 'cdk', 50, 'genshin_crystal.jpg'),
('PUBG UC Coins', 'PUBG game currency CD Key', 88.00, 6, 'cdk', 75, 'pubg_uc.jpg'),
('Gaming Mechanical Keyboard', 'RGB backlit mechanical keyboard, blue switches', 299.00, 3, 'physical', 20, 'mechanical_keyboard.jpg'),
('League of Legends Yasuo Figure', 'High-quality Yasuo figure model', 199.00, 7, 'physical', 15, 'yasuo_figure.jpg');

-- Insert sample CDK keys
INSERT INTO cdk_keys (product_id, cdk_code, status) VALUES
(1, 'LOL-1350-ABC123XYZ', 'available'),
(1, 'LOL-1350-DEF456UVW', 'available'),
(2, 'GENSHIN-1000-GHI789RST', 'available'),
(3, 'PUBG-600-JKL012MNO', 'available');

-- Insert sample users (password for both is 'password')
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@gamevault.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('testuser', 'user@gamevault.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer');