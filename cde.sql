-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 31, 2025 at 12:15 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cde`
--

-- --------------------------------------------------------

--
-- Table structure for table `payment_settings`
--

CREATE TABLE `payment_settings` (
  `id` int(11) NOT NULL,
  `account_name` varchar(255) NOT NULL COMMENT 'Account holder name',
  `bank_name` varchar(255) NOT NULL COMMENT 'Name of the bank',
  `account_number` varchar(50) NOT NULL,
  `amount` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Amount in VND',
  `note` text DEFAULT NULL COMMENT 'Optional payment note',
  `qr_url` text DEFAULT NULL COMMENT 'Generated QR code URL',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Record creation timestamp',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Record last update timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_settings`
--

INSERT INTO `payment_settings` (`id`, `account_name`, `bank_name`, `account_number`, `amount`, `note`, `qr_url`, `created_at`, `updated_at`) VALUES
(1, 'PHAM MANH HUY', 'BIDV', '2111396620', 5000, 'TestChucNangThanhToan', NULL, '2025-07-30 10:11:03', '2025-07-31 03:20:48');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `name`, `price`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Free', 0.00, '1 GB chung toàn bộ tài khoản\r\nTối đa 1 dự án\r\nTối đa 1 thành viên\r\nServer dữ liệu tại Việt Nam', '2025-07-30 09:43:49', '2025-07-30 09:48:35'),
(2, 'Personal', 1000000.00, '15 GB chung toàn bộ tài khoản\r\nTối đa 2 dự án\r\nTối đa 3 thành viên\r\nServer dữ liệu tại Việt Nam', '2025-07-30 09:43:56', '2025-07-30 09:50:22'),
(3, 'Pro', 3000000.00, '150 GB chung toàn bộ tài khoản\r\nTối đa 10 dự án\r\nTối đa 12 thành viên\r\nBao gồm tính năng gói Personal\r\nThêm tính năng Organization Members', '2025-07-30 09:44:03', '2025-07-30 09:51:17'),
(4, 'Bussines', 7000000.00, '1 TB chung toàn bộ tài khoản\r\nKhông giới hạn dự án\r\nKhông giới hạn thành viên\r\nBao gồm tính năng gói Pro\r\nThêm tính năng Work Diary', '2025-07-30 09:45:44', '2025-07-30 09:52:06');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `dob` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `invite_code` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `avatar` varchar(255) DEFAULT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `first_name`, `last_name`, `dob`, `address`, `company`, `phone`, `invite_code`, `email`, `password_hash`, `role`, `avatar`, `subscription_id`, `created_at`) VALUES
(1, 'huypham', 'Phạm Mạnh', 'Huy', '0000-00-00', 'Hạ Long', 'NCC', '0888121496', NULL, 'phamhuy.cngt@gmail.com', '$2y$10$YXa/ryYZhrQyEE6rBuORVugmQSIQgmwJxBdebsPapaDuzQDaeKPhy', 'admin', 'avatar_1.png', NULL, '2025-07-30 01:18:51'),
(4, 'user1', 'user1a', 'user1b', '2025-07-31', 'Hạ Long', 'NCC', '0888121496', NULL, 'user1@bimtech.edu.vn', '$2y$10$wX/QF1V8VS2dHRDEeVQgFO6KFkZM.oAZaPl9ixMa.SbtH9CkGeicy', 'user', NULL, 3, '2025-07-31 04:47:19');

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL COMMENT 'Voucher ID',
  `code` varchar(50) NOT NULL COMMENT 'Voucher code',
  `discount` decimal(5,2) NOT NULL COMMENT 'Discount percentage',
  `expiry_date` date NOT NULL COMMENT 'Expiry date',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Created timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `payment_settings`
--
ALTER TABLE `payment_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_unique` (`code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Voucher ID';
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
