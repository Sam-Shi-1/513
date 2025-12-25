-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- 主机： sql103.infinityfree.com
-- 生成日期： 2025-11-18 02:51:17
-- 服务器版本： 11.4.7-MariaDB
-- PHP 版本： 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `if0_39913189_4`
--

-- --------------------------------------------------------

--
-- 表的结构 `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `parent_category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- 转存表中的数据 `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `parent_category_id`) VALUES
(1, 'Game CD Keys', NULL),
(2, 'Physical Goods', NULL),
(3, 'Gaming Peripherals', NULL),
(4, 'League of Legends', 1),
(6, 'PUBG', 1),
(7, 'Figures & Models', 2),
(8, 'Gaming Apparel', 2),
(9, 'CSGO', 1),
(10, 'Game Items', NULL);

-- --------------------------------------------------------

--
-- 表的结构 `cdk_keys`
--

CREATE TABLE `cdk_keys` (
  `cdk_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `cdk_code` varchar(255) NOT NULL,
  `status` enum('available','sold','used') DEFAULT 'available',
  `order_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- 转存表中的数据 `cdk_keys`
--

INSERT INTO `cdk_keys` (`cdk_id`, `product_id`, `cdk_code`, `status`, `order_id`, `created_at`) VALUES
(1, 1, 'LOL-1350-ABC123XYZ', 'available', NULL, '2025-10-24 08:43:18'),
(2, 1, 'LOL-1350-DEF456UVW', 'available', NULL, '2025-10-24 08:43:18'),
(3, 2, 'GENSHIN-1000-GHI789RST', 'available', NULL, '2025-10-24 08:43:18'),
(4, 3, 'PUBG-600-JKL012MNO', 'available', NULL, '2025-10-24 08:43:18');

-- --------------------------------------------------------

--
-- 表的结构 `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ;

--
-- 转存表中的数据 `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `total_amount`, `status`, `created_at`, `summary`, `user_info`, `products`) VALUES
(19, 5, '1935.92', 'pending', '2025-11-18 07:25:06', NULL, NULL, NULL),
(20, 5, '6225.38', 'pending', '2025-11-18 07:29:02', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_purchase` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- 转存表中的数据 `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `product_id`, `quantity`, `price_at_purchase`) VALUES
(12, 19, 12, 5, '299.99'),
(13, 19, 11, 1, '159.99'),
(14, 19, 10, 1, '99.99'),
(15, 20, 11, 1, '159.99'),
(16, 20, 10, 55, '99.99');

-- --------------------------------------------------------

--
-- 表的结构 `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `product_type` enum('cdk','physical','virtual') NOT NULL,
  `stock_quantity` int(11) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `supplier` varchar(100) DEFAULT '',
  `region` varchar(50) DEFAULT 'Global',
  `platform` varchar(50) DEFAULT 'Multi-platform',
  `is_featured` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- 转存表中的数据 `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `description`, `price`, `category_id`, `product_type`, `stock_quantity`, `image_url`, `is_active`, `created_at`, `discount_percent`, `supplier`, `region`, `platform`, `is_featured`) VALUES
(1, 'League of Legends 1350 RP', 'League of Legends game points CD Key, instant delivery', '68.00', 4, 'cdk', 85, 'product_1762161300_6908729473a2c.jpg', 1, '2025-10-24 08:43:06', '10.00', 'Riot Games', 'Global', 'PC', 1),
(2, 'CSGO Weapon Case Key', 'Counter-Strike: Global Offensive Weapon Case Key CD Key\r\n\r\nUsed to open CSGO weapon cases to obtain rare skins, knives, and other items', '2.00', 9, 'virtual', 99, 'product_1761579942_68ff93a603bb3.webp', 1, '2025-10-24 08:43:06', '0.00', 'Valve', 'Global', 'Steam', 1),
(3, 'PUBG UC Coins', 'PUBG game currency CD Key', '88.00', 6, 'cdk', 72, 'product_1761579588_68ff9244dcfd2.jpg', 1, '2025-10-24 08:43:06', '15.00', 'Krafton', 'Global', 'PC/Mobile', 0),
(4, 'Gaming Mechanical Keyboard', 'RGB backlit mechanical keyboard, blue switches', '299.00', 3, 'physical', 19, 'product_1761579750_68ff92e6db53f.jpg', 1, '2025-10-24 08:43:06', '20.00', 'Razer', 'Global', 'PC', 1),
(5, 'League of Legends Yasuo Figure', 'High-quality Yasuo figure model', '199.00', 7, 'physical', 15, 'product_1761579856_68ff9350bfd0b.jpg', 1, '2025-10-24 08:43:06', '0.00', 'Riot Merch', 'Global', 'Physical', 0),
(8, 'Cracked weapon box', 'When the horizon of the battlefield is torn apart by an invisible force, a new era of tactics begins. The \'Crackdown\' weapon box has descended upon the arsenal, inspired by the moment of dimension fracture, combining futuristic geometric lines, energetic neon colors, and sturdy industrial textures. Every weapon skin is like an energy crystal seeping out from a crack in another dimension, waiting for you to control it.', '5.00', 9, 'virtual', 90, 'product_1762169421_6908924dd8848.webp', 1, '2025-11-03 10:39:38', '0.00', 'Valve', 'Global', 'Steam', 1),
(9, 'Snake Devoured Weapon Box', 'In the shadows and whispers, a deadly temptation quietly descended. The snake devouring weapon box winds around and injects its venom like color and scale like texture into your arsenal. The design inspiration for this weapon box comes from the world\'s most dangerous reptiles, perfectly blending the elegance, cunning, and lethality of cold-blooded animals. Each skin seems to be possessed by the spirit of a snake, lurking silently and completing a final kill in an instant.', '5.00', 9, 'virtual', 99, 'product_1762169537_690892c109b2d.webp', 1, '2025-11-03 10:53:27', '0.00', 'Valve', 'Global', 'Steam', 0),
(10, 'Hurricane Three Earphones', 'Experience audio perfection with the Hurricane 3 Headphones. Engineered for elite gamers and audio enthusiasts, these headphones deliver immersive, high-fidelity sound that lets you hear every critical in-game detail, from subtle footsteps to explosive action.', '99.99', 3, 'physical', 42, 'product_1762235330_690993c24b493.webp', 1, '2025-11-04 05:48:50', '0.00', 'HyperX', 'Global', 'PC', 1),
(11, 'gpw3', 'Meet the Logitech G Pro X Superlight – the pinnacle of esports engineering, designed in collaboration with top professionals. Experience absolute freedom with LIGHTSPEED wireless technology and relentless performance with the HERO 25K sensor. Weighing in at a mere 63 grams, its ultra-lightweight construction allows for faster, more precise movements and reduces fatigue during long competitions. The zero-additive PTFE feet ensure smooth gliding, while the long-lasting battery provides over 70 hours of continuous play. Dominate the competition with the tool built for victory.', '159.99', 3, 'physical', 96, 'product_1762235887_690995ef3b891.webp', 1, '2025-11-04 05:58:07', '0.00', 'Logitech', 'Global', 'PC', 1),
(12, 'AOC 27\" 144Hz Gaming Monitor - G2790VX', 'Elevate your gameplay with the AOC 27-inch Gaming Monitor. Featuring a blazing-fast 144Hz refresh rate and a 1ms response time, this monitor delivers incredibly smooth and fluid visuals, eliminating motion blur and ghosting for a decisive competitive edge. The immersive 3-sided frameless design and Full HD resolution offer stunning clarity and a expansive field of view. With AMD FreeSync Premium technology, say goodbye to screen tearing and stuttering for seamless gameplay. Whether you\'re in the heat of battle or enjoying multimedia content, this AOC monitor provides exceptional performance and value.', '299.99', 3, 'physical', -3, 'product_1762236097_690996c1bc22e.webp', 1, '2025-11-04 06:01:37', '0.00', 'AOC', 'Global', 'PC', 1);

--
-- 转储表的索引
--

--
-- 表的索引 `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `parent_category_id` (`parent_category_id`);

--
-- 表的索引 `cdk_keys`
--
ALTER TABLE `cdk_keys`
  ADD PRIMARY KEY (`cdk_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `order_id` (`order_id`);

--
-- 表的索引 `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- 表的索引 `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `category_id` (`category_id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- 使用表AUTO_INCREMENT `cdk_keys`
--
ALTER TABLE `cdk_keys`
  MODIFY `cdk_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用表AUTO_INCREMENT `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- 使用表AUTO_INCREMENT `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- 限制导出的表
--

--
-- 限制表 `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_category_id`) REFERENCES `categories` (`category_id`);

--
-- 限制表 `cdk_keys`
--
ALTER TABLE `cdk_keys`
  ADD CONSTRAINT `cdk_keys_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`),
  ADD CONSTRAINT `cdk_keys_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`);

--
-- 限制表 `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- 限制表 `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
