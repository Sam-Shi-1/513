-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- 主机： sql103.infinityfree.com
-- 生成日期： 2025-11-18 01:17:36
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
-- 数据库： `if0_39913189_wp887`
--

-- --------------------------------------------------------

--
-- 表的结构 `wppw_fc_subscribers`
--

CREATE TABLE `wppw_fc_subscribers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `hash` varchar(90) DEFAULT NULL,
  `contact_owner` bigint(20) UNSIGNED DEFAULT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `prefix` varchar(192) DEFAULT NULL,
  `first_name` varchar(192) DEFAULT NULL,
  `last_name` varchar(192) DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `timezone` varchar(192) DEFAULT NULL,
  `address_line_1` varchar(192) DEFAULT NULL,
  `address_line_2` varchar(192) DEFAULT NULL,
  `postal_code` varchar(192) DEFAULT NULL,
  `city` varchar(192) DEFAULT NULL,
  `state` varchar(192) DEFAULT NULL,
  `country` varchar(192) DEFAULT NULL,
  `ip` varchar(40) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(10,8) DEFAULT NULL,
  `total_points` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `life_time_value` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `phone` varchar(50) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'subscribed',
  `contact_type` varchar(50) DEFAULT 'lead',
  `source` varchar(50) DEFAULT NULL,
  `avatar` varchar(192) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- 转存表中的数据 `wppw_fc_subscribers`
--

INSERT INTO `wppw_fc_subscribers` (`id`, `user_id`, `hash`, `contact_owner`, `company_id`, `prefix`, `first_name`, `last_name`, `email`, `password`, `timezone`, `address_line_1`, `address_line_2`, `postal_code`, `city`, `state`, `country`, `ip`, `latitude`, `longitude`, `total_points`, `life_time_value`, `phone`, `status`, `contact_type`, `source`, `avatar`, `date_of_birth`, `created_at`, `last_activity`, `updated_at`) VALUES
(1, NULL, '0fd6f3d9f9876d2c820c0c7682d5f6e4', NULL, NULL, NULL, 'Emily', 'Johnson', 'emily.johnson@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:20:12', NULL, '2025-11-11 08:20:12'),
(2, NULL, '16237c9128387a928983b7675018978c', NULL, NULL, NULL, 'Alan', NULL, '2956522720@qq.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:21:58', NULL, '2025-11-11 08:21:58'),
(3, NULL, '933394cf9610d8daa7bf9ad0e950c9f2', NULL, NULL, NULL, 'Hyman', NULL, '2901239635@qq.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:22:54', NULL, '2025-11-11 08:22:54'),
(4, NULL, '50738f4bde7a6844b66a9a910a783fd4', NULL, NULL, NULL, 'Jerrry', NULL, '2160502612@qq.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:23:34', NULL, '2025-11-11 08:23:34'),
(5, 1, '954fd619a0c835011cdcb2bd1aa375d5', NULL, NULL, NULL, 'Sam', '', '2433137795@qq.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '12345678', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:24:03', NULL, '2025-11-18 05:13:51'),
(6, NULL, '8885cd622845de44b2010389bb7f0c8d', NULL, NULL, NULL, 'Tom', NULL, '2248079166@qq.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'subscribed', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:26:19', '2025-11-12 08:49:57', '2025-11-12 08:49:57'),
(7, NULL, 'd0027b0f2a2423c99b7752ca5b32aff6', NULL, NULL, NULL, 'Michael', 'Chen', 'michael.chen@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:26:41', NULL, '2025-11-11 08:26:41'),
(8, NULL, '9b632e97b88366d5ab4f67b729b84ef6', NULL, NULL, NULL, 'Sofia', 'Garcia', 'sofia.garcia@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:27:00', NULL, '2025-11-11 08:27:00'),
(9, NULL, 'c78cf9e2888d45c1b50461cff77dcb93', NULL, NULL, NULL, 'David', 'Williams', 'd.williams@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:27:17', NULL, '2025-11-11 08:27:17'),
(10, NULL, '2af11d025fcdf3adec08c0c06354da43', NULL, NULL, NULL, 'Chloe', 'Smith', 'chloe_smith@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:27:34', NULL, '2025-11-11 08:27:34'),
(11, NULL, '225b88395aaca623b62c5dc25f29e840', NULL, NULL, NULL, 'James', 'Taylor', 'j.taylor@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:27:54', NULL, '2025-11-11 08:27:54'),
(12, NULL, 'cc9c345d1593db466caeb69ece25adb1', NULL, NULL, NULL, 'Olivia', 'Brown', 'olivia.brown@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:28:14', NULL, '2025-11-11 08:28:14'),
(13, NULL, '5d775217d30afa042a51aa01d5910a60', NULL, NULL, NULL, 'Benjamin', 'Davis', 'ben.davis@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:28:32', NULL, '2025-11-11 08:28:32'),
(14, NULL, 'f3ff751747a5d070998cef483762017c', NULL, NULL, NULL, 'Ava', 'Martinez', 'ava_martinez@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:28:53', NULL, '2025-11-11 08:28:53'),
(15, NULL, '33a46736e1236f7e4461b31413ff71b6', NULL, NULL, NULL, 'Daniel', 'Lee', 'daniel.lee@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:29:11', NULL, '2025-11-11 08:29:11'),
(16, NULL, '4c5ac6e7698d623a4b9aa354b2807508', NULL, NULL, NULL, 'Isabella', 'Wilson', 'isabella.wilson@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:29:31', NULL, '2025-11-11 08:29:31'),
(17, NULL, '4413d892aa26c8d5da2c7f4e72bee0e3', NULL, NULL, NULL, 'Alexander', 'Moore', 'alex.moore@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:29:50', NULL, '2025-11-11 08:29:50'),
(18, NULL, 'ce65338941426cc8ee3c51e697035e09', NULL, NULL, NULL, 'Mia', 'Anderson', 'mia.anderson@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:30:10', NULL, '2025-11-11 08:30:10'),
(19, NULL, 'b55f93265430468b02cfcc83019be24c', NULL, NULL, NULL, 'William', 'Thomas', 'william.thomas@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:30:33', NULL, '2025-11-11 08:30:33'),
(20, NULL, 'c20d8e4628b1cd2b869c233b7fd04ca5', NULL, NULL, NULL, 'Charlotte', 'Jackson', 'charlotte.jackson@email.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '39.184.255.66', NULL, NULL, 0, 0, '123456', 'pending', 'lead', 'FluentForms', NULL, NULL, '2025-11-11 08:30:54', NULL, '2025-11-18 04:58:20');

--
-- 转储表的索引
--

--
-- 表的索引 `wppw_fc_subscribers`
--
ALTER TABLE `wppw_fc_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `wppw_fc_index__subscriber_user_id_idx` (`user_id`),
  ADD KEY `wppw_fc_index__subscriber_status_idx` (`status`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `wppw_fc_subscribers`
--
ALTER TABLE `wppw_fc_subscribers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
