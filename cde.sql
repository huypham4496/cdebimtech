-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 02, 2025 at 01:56 PM
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
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `max_storage_gb` int(11) NOT NULL DEFAULT 0 COMMENT 'Max storage in GB',
  `max_projects` int(11) NOT NULL DEFAULT 0 COMMENT 'Max number of projects',
  `max_company_members` int(11) NOT NULL DEFAULT 0 COMMENT 'Max number of company members',
  `allow_organization_members` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable Organization Members feature',
  `allow_work_diary` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable Work Diary feature'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `name`, `price`, `description`, `created_at`, `updated_at`, `max_storage_gb`, `max_projects`, `max_company_members`, `allow_organization_members`, `allow_work_diary`) VALUES
(1, 'Free', 0.00, '1 GB chung toàn bộ tài khoản\r\nTối đa 1 dự án\r\nTối đa 1 thành viên\r\nServer dữ liệu tại Việt Nam', '2025-07-30 09:43:49', '2025-08-01 09:44:54', 1, 1, 1, 0, 0),
(2, 'Personal', 1000000.00, '15 GB chung toàn bộ tài khoản\r\nTối đa 2 dự án\r\nTối đa 3 thành viên\r\nServer dữ liệu tại Việt Nam', '2025-07-30 09:43:56', '2025-08-01 09:45:02', 15, 2, 3, 0, 0),
(3, 'Pro', 3000000.00, '150 GB chung toàn bộ tài khoản\r\nTối đa 10 dự án\r\nTối đa 12 thành viên\r\nBao gồm tính năng gói Personal\r\nThêm tính năng Organization Members', '2025-07-30 09:44:03', '2025-08-01 09:49:03', 150, 10, 12, 1, 0),
(4, 'Bussines', 7000000.00, '1 TB chung toàn bộ tài khoản\r\nKhông giới hạn dự án\r\nKhông giới hạn thành viên\r\nBao gồm tính năng gói Pro\r\nThêm tính năng Work Diary', '2025-07-30 09:45:44', '2025-08-01 09:46:41', 1024, 0, 0, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `subscription_orders`
--

CREATE TABLE `subscription_orders` (
  `id` int(11) NOT NULL COMMENT 'Order ID',
  `user_id` int(11) NOT NULL COMMENT 'ID of the user',
  `subscription_id` int(11) NOT NULL COMMENT 'ID of the subscription plan',
  `duration` varchar(20) NOT NULL COMMENT 'Duration selected (e.g. 5y, LT)',
  `voucher_code` varchar(50) DEFAULT NULL COMMENT 'Applied voucher code',
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Discount percentage',
  `amount_paid` decimal(12,2) NOT NULL COMMENT 'Final amount paid',
  `memo` varchar(100) NOT NULL COMMENT 'Payment memo',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Order timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_orders`
--

INSERT INTO `subscription_orders` (`id`, `user_id`, `subscription_id`, `duration`, `voucher_code`, `discount_percent`, `amount_paid`, `memo`, `status`, `created_at`) VALUES
(1, 4, 4, '5', '', 0.00, 28000000.00, 'BT_4_Bussines_5y_8MOY', 'approved', '2025-07-31 14:39:40'),
(2, 4, 4, '0', '', 0.00, 210000000.00, 'BT_4_Bussines_LT_H0LA', 'rejected', '2025-07-31 14:43:12'),
(3, 4, 4, '1', '', 0.00, 7000000.00, 'BT_4_Bussines_1y_U95Z', 'approved', '2025-07-31 15:25:18');

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
  `company_id` int(11) UNSIGNED DEFAULT NULL,
  `subscription_expires_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `first_name`, `last_name`, `dob`, `address`, `company`, `phone`, `invite_code`, `email`, `password_hash`, `role`, `avatar`, `subscription_id`, `company_id`, `subscription_expires_at`, `created_at`) VALUES
(1, 'huypham', 'Phạm Mạnh', 'Huy', '1996-04-04', 'Hạ Long', 'NCC', '0888121496', NULL, 'phamhuy.cngt@gmail.com', '$2y$10$YXa/ryYZhrQyEE6rBuORVugmQSIQgmwJxBdebsPapaDuzQDaeKPhy', 'admin', 'avatar_1.png', 3, NULL, NULL, '2025-07-30 01:18:51'),
(4, 'user1', 'user1a', 'user1b', '2025-07-31', 'Hạ Long', 'NCC', '0888121496', NULL, 'user1@bimtech.edu.vn', '$2y$10$wX/QF1V8VS2dHRDEeVQgFO6KFkZM.oAZaPl9ixMa.SbtH9CkGeicy', 'user', NULL, 4, NULL, '2025-12-01', '2025-07-31 04:47:19'),
(5, 'user2', 'user2a', 'user2b', '0000-00-00', 'Hạ Long', 'NCC', '000000000', NULL, 'user2@bimtech.edu.vn', '$2y$10$joRVpVd0LQDnrM7xYP5kYuoAl6BaFqcTkFcVkK3dJKPv3LSMA5bXq', 'user', NULL, 4, NULL, NULL, '2025-08-01 13:48:30');

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
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `discount`, `expiry_date`, `created_at`) VALUES
(1, 'NCCdemo', 10.00, '2025-12-31', '2025-07-31 14:03:07'),
(3, '767363', 50.00, '2025-07-31', '2025-07-31 14:48:56');

-- --------------------------------------------------------

--
-- Table structure for table `work_diary_entries`
--

CREATE TABLE `work_diary_entries` (
  `user_id` int(11) NOT NULL COMMENT 'References users.id',
  `entry_date` date NOT NULL COMMENT 'The date of this entry',
  `period` enum('morning','afternoon','evening') NOT NULL COMMENT 'Which part of day',
  `content` text DEFAULT NULL COMMENT 'Tasks/work done',
  `note` text DEFAULT NULL COMMENT 'Additional notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_diary_entries`
--

INSERT INTO `work_diary_entries` (`user_id`, `entry_date`, `period`, `content`, `note`, `created_at`, `updated_at`) VALUES
(4, '2025-08-01', 'morning', 'Sửa QTBT cầu Làng Lốc, Khe Pụt', NULL, '2025-08-02 07:08:04', '2025-08-02 07:08:04'),
(4, '2025-08-01', 'afternoon', 'Sửa QTBT cầu Làng Lốc, Khe Pụt', NULL, '2025-08-02 07:08:04', '2025-08-02 07:08:04'),
(4, '2025-08-01', 'evening', 'Break', NULL, '2025-08-02 07:08:04', '2025-08-02 07:08:04'),
(4, '2025-08-02', 'morning', 'Cập nhật tính năng ghi nhật ký công việc trên CDE', NULL, '2025-08-02 11:40:36', '2025-08-02 11:40:36'),
(4, '2025-08-02', 'afternoon', 'Cập nhật tính năng ghi nhật ký công việc trên CDE', NULL, '2025-08-02 11:40:36', '2025-08-02 11:40:36'),
(4, '2025-08-02', 'evening', '17:00-19:30: Cập nhật tính năng xuất Nhật ký công việc ra Excel', NULL, '2025-08-02 11:40:36', '2025-08-02 11:40:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `subscription_orders`
--
ALTER TABLE `subscription_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_subscription` (`subscription_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `company_id` (`company_id`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_unique` (`code`);

--
-- Indexes for table `work_diary_entries`
--
ALTER TABLE `work_diary_entries`
  ADD PRIMARY KEY (`user_id`,`entry_date`,`period`),
  ADD KEY `idx_user_date` (`user_id`,`entry_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `subscription_orders`
--
ALTER TABLE `subscription_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Order ID', AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Voucher ID', AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `subscription_orders`
--
ALTER TABLE `subscription_orders`
  ADD CONSTRAINT `subscription_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscription_orders_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `work_diary_entries`
--
ALTER TABLE `work_diary_entries`
  ADD CONSTRAINT `fk_wde_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
