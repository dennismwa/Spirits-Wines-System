-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 07, 2025 at 12:07 AM
-- Server version: 8.0.42
-- PHP Version: 8.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vxjtgclw_Spirits`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(255) NOT NULL,
  `description` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'Login', 'User logged in successfully', '41.209.14.78', '2025-10-06 20:21:12'),
(2, 1, 'Category Added', 'Added category: Whisky', '41.209.14.78', '2025-10-06 20:28:59'),
(3, 1, 'Category Added', 'Added category: Vodka', '41.209.14.78', '2025-10-06 20:29:13'),
(4, 1, 'Category Added', 'Added category: Gin', '41.209.14.78', '2025-10-06 20:29:30'),
(5, 1, 'Category Added', 'Added category: Gin', '41.209.14.78', '2025-10-06 20:29:30'),
(6, 1, 'Category Added', 'Added category: Rum', '41.209.14.78', '2025-10-06 20:29:47'),
(7, 1, 'Category Added', 'Added category: Cognac', '41.209.14.78', '2025-10-06 20:30:03'),
(8, 1, 'Category Added', 'Added category: Wine', '41.209.14.78', '2025-10-06 20:30:11'),
(9, 1, 'Category Added', 'Added category: Beer', '41.209.14.78', '2025-10-06 20:30:27'),
(10, 1, 'Category Added', 'Added category: Champagne', '41.209.14.78', '2025-10-06 20:30:35'),
(11, 1, 'Category Added', 'Added category: Ready-to-Drink (RTD)', '41.209.14.78', '2025-10-06 20:30:50'),
(12, 1, 'Category Added', 'Added category: Non-Alcoholic Beverages', '41.209.14.78', '2025-10-06 20:31:03'),
(13, 1, 'Product Added', 'Added product: Four Cousins Sweet Red', '41.209.14.78', '2025-10-06 20:56:14'),
(14, 1, 'Sale Completed', 'Sale #ZWS-20251006-4B1E49 completed', '41.209.14.78', '2025-10-06 20:56:52'),
(15, 1, 'Sale Completed', 'Sale #ZWS-20251006-10357B completed', '41.209.14.78', '2025-10-06 20:57:37'),
(16, 1, 'Login', 'User logged in successfully', '41.209.14.78', '2025-10-06 21:02:49'),
(17, 1, 'User Updated', 'Updated user: Owner', '41.209.14.78', '2025-10-06 21:03:56'),
(18, 1, 'User Added', 'Added user: Juice', '41.209.14.78', '2025-10-06 21:04:31'),
(19, 1, 'Logout', 'User logged out', '41.209.14.78', '2025-10-06 21:05:21'),
(20, 2, 'Login', 'User logged in successfully', '41.209.14.78', '2025-10-06 21:05:32'),
(21, 2, 'Sale Completed', 'Sale #ZWS-20251007-58FBF3 completed', '41.209.14.78', '2025-10-06 21:06:29');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Whisky', '', 'active', '2025-10-06 20:28:59', '2025-10-06 20:28:59'),
(2, 'Vodka', '', 'active', '2025-10-06 20:29:13', '2025-10-06 20:29:13'),
(3, 'Gin', '', 'active', '2025-10-06 20:29:30', '2025-10-06 20:29:30'),
(4, 'Gin', '', 'active', '2025-10-06 20:29:30', '2025-10-06 20:29:30'),
(5, 'Rum', '', 'active', '2025-10-06 20:29:47', '2025-10-06 20:29:47'),
(6, 'Cognac', '', 'active', '2025-10-06 20:30:03', '2025-10-06 20:30:03'),
(7, 'Wine', '', 'active', '2025-10-06 20:30:11', '2025-10-06 20:30:11'),
(8, 'Beer', '', 'active', '2025-10-06 20:30:27', '2025-10-06 20:30:27'),
(9, 'Champagne', '', 'active', '2025-10-06 20:30:35', '2025-10-06 20:30:35'),
(10, 'Ready-to-Drink (RTD)', '', 'active', '2025-10-06 20:30:50', '2025-10-06 20:30:50'),
(11, 'Non-Alcoholic Beverages', '', 'active', '2025-10-06 20:31:03', '2025-10-06 20:31:03');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text,
  `expense_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `description` text,
  `cost_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(10,2) NOT NULL,
  `stock_quantity` int NOT NULL DEFAULT '0',
  `reorder_level` int NOT NULL DEFAULT '10',
  `supplier` varchar(255) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'bottle',
  `sku` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `barcode`, `description`, `cost_price`, `selling_price`, `stock_quantity`, `reorder_level`, `supplier`, `unit`, `sku`, `location`, `expiry_date`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 7, 'Four Cousins Sweet Red', 'FCSR001', 'Smooth and fruity red wine with a sweet finish.', 1100.00, 1300.00, 47, 10, 'Spritz Distributors', 'bottle', 'WNE-001', 'Ruiru', NULL, 'active', 1, '2025-10-06 20:56:14', '2025-10-06 21:06:29');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int NOT NULL,
  `sale_number` varchar(50) NOT NULL,
  `user_id` int NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','mpesa','mpesa_till') NOT NULL,
  `mpesa_reference` varchar(100) DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sale_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `sale_number`, `user_id`, `subtotal`, `tax_amount`, `discount_amount`, `total_amount`, `payment_method`, `mpesa_reference`, `amount_paid`, `change_amount`, `sale_date`, `created_at`) VALUES
(1, 'ZWS-20251006-4B1E49', 1, 1300.00, 0.00, 0.00, 1300.00, 'cash', NULL, 1300.00, 0.00, '2025-10-06 23:56:52', '2025-10-06 20:56:52'),
(2, 'ZWS-20251006-10357B', 1, 1300.00, 0.00, 0.00, 1300.00, 'cash', NULL, 1300.00, 0.00, '2025-10-06 23:57:37', '2025-10-06 20:57:37'),
(3, 'ZWS-20251007-58FBF3', 2, 1300.00, 0.00, 0.00, 1300.00, 'cash', NULL, 1300.00, 0.00, '2025-10-07 00:06:29', '2025-10-06 21:06:29');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int NOT NULL,
  `sale_id` int NOT NULL,
  `product_id` int NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `product_name`, `quantity`, `unit_price`, `subtotal`, `created_at`) VALUES
(1, 1, 1, 'Four Cousins Sweet Red', 1, 1300.00, 1300.00, '2025-10-06 20:56:52'),
(2, 2, 1, 'Four Cousins Sweet Red', 1, 1300.00, 1300.00, '2025-10-06 20:57:37'),
(3, 3, 1, 'Four Cousins Sweet Red', 1, 1300.00, 1300.00, '2025-10-06 21:06:29');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `login_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logout_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `login_time`, `last_activity`, `logout_time`) VALUES
(1, 1, '82ecef919b7fd00d60ac8fbd988e895f1c30b787da8c93ea0d8db205ef84aa09', '41.209.14.78', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-06 20:21:12', '2025-10-06 20:21:12', NULL),
(2, 1, '7f5f9f6cfa95761f37ec906c311d1bce542d21ae3012678d4d6d0615657afc72', '41.209.14.78', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-06 21:02:49', '2025-10-06 21:02:49', NULL),
(3, 2, '2c0544ac2c16c6e19e43c0cf53c15825f3887e232936f740b7275cf4dfd68613', '41.209.14.78', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-06 21:05:32', '2025-10-06 21:05:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `company_name` varchar(255) NOT NULL DEFAULT 'Zuri Wines & Spirits',
  `logo_path` varchar(255) NOT NULL DEFAULT '/logo.jpg',
  `primary_color` varchar(50) NOT NULL DEFAULT '#ea580c',
  `currency` varchar(10) NOT NULL DEFAULT 'KSh',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `receipt_footer` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `company_name`, `logo_path`, `primary_color`, `currency`, `tax_rate`, `receipt_footer`, `created_at`, `updated_at`) VALUES
(1, 'Zuri Wines & Spirits', '/logo.jpg', '#ea580c', 'KSh', 0.00, NULL, '2025-10-06 19:38:42', '2025-10-06 19:38:42');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `user_id` int NOT NULL,
  `movement_type` enum('in','out','adjustment','sale') NOT NULL,
  `quantity` int NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `user_id`, `movement_type`, `quantity`, `reference_type`, `reference_id`, `notes`, `created_at`) VALUES
(1, 1, 1, 'in', 50, NULL, NULL, 'Initial stock', '2025-10-06 20:56:14'),
(2, 1, 1, 'sale', 1, 'sale', 1, NULL, '2025-10-06 20:56:52'),
(3, 1, 1, 'sale', 1, 'sale', 2, NULL, '2025-10-06 20:57:37'),
(4, 1, 2, 'sale', 1, 'sale', 3, NULL, '2025-10-06 21:06:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `pin_code` varchar(6) NOT NULL,
  `role` enum('owner','seller') NOT NULL DEFAULT 'seller',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `pin_code`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Owner', '654321', 'owner', 'active', '2025-10-06 19:38:42', '2025-10-06 21:03:56'),
(2, 'Juice', '770823', 'seller', 'active', '2025-10-06 21:04:31', '2025-10-06 21:04:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `expense_date` (`expense_date`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `barcode` (`barcode`),
  ADD KEY `idx_sku` (`sku`),
  ADD KEY `idx_supplier` (`supplier`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sale_number` (`sale_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `sale_date` (`sale_date`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pin_code` (`pin_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sessions`
--
ALTER TABLE `sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
