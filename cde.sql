-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2025 at 04:47 PM
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
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL COMMENT 'ID người gửi (users.id)',
  `receiver_id` int(11) NOT NULL COMMENT 'ID người nhận (users.id)',
  `entry_date` date NOT NULL COMMENT 'Ngày nhật ký đã gửi',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Thời gian gửi thông báo',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Đánh dấu đã đọc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `sender_id`, `receiver_id`, `entry_date`, `created_at`, `is_read`) VALUES
(5, 4, 1, '2025-08-01', '2025-08-03 11:07:53', 1),
(6, 4, 1, '2025-08-01', '2025-08-03 11:21:46', 1),
(7, 4, 1, '2025-08-03', '2025-08-03 11:22:42', 1),
(8, 1, 4, '2025-07-01', '2025-08-04 02:00:12', 1),
(9, 4, 1, '2025-08-04', '2025-08-04 03:03:47', 1),
(10, 1, 4, '2025-08-04', '2025-08-04 11:27:08', 1),
(11, 1, 4, '2025-08-04', '2025-08-04 11:27:11', 1);

-- --------------------------------------------------------

--
-- Table structure for table `organizations`
--

CREATE TABLE `organizations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `abbreviation` varchar(50) NOT NULL,
  `address` text NOT NULL,
  `department` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `name`, `abbreviation`, `address`, `department`, `created_by`, `created_at`) VALUES
(1, 'Công ty cổ phần Tư vấn miền Bắc', 'NCC', 'Tổ 5, khu 1, Phường Bãi Cháy, Tỉnh Quảng Ninh', 'Phòng thiết kế', 1, '2025-08-04 14:06:12');

-- --------------------------------------------------------

--
-- Table structure for table `organization_invitations`
--

CREATE TABLE `organization_invitations` (
  `id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `invited_user_email` varchar(255) NOT NULL,
  `token` char(64) NOT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organization_invitations`
--

INSERT INTO `organization_invitations` (`id`, `organization_id`, `invited_user_email`, `token`, `status`, `sent_at`, `responded_at`) VALUES
(6, 1, '', '934a83d3d54625fc9b6f004cc130edcd', 'accepted', '2025-08-04 14:29:08', '2025-08-04 14:43:48');

-- --------------------------------------------------------

--
-- Table structure for table `organization_members`
--

CREATE TABLE `organization_members` (
  `id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `subscribed_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organization_members`
--

INSERT INTO `organization_members` (`id`, `organization_id`, `user_id`, `role`, `subscribed_id`, `joined_at`) VALUES
(1, 1, 4, 'member', 1, '2025-08-04 14:29:26'),
(2, 1, 5, 'member', 1, '2025-08-04 14:43:48');

-- --------------------------------------------------------

--
-- Table structure for table `organization_member_profiles`
--

CREATE TABLE `organization_member_profiles` (
  `member_id` int(11) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `expertise` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `hometown` varchar(255) DEFAULT NULL,
  `residence` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `monthly_performance` decimal(5,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organization_member_profiles`
--

INSERT INTO `organization_member_profiles` (`member_id`, `full_name`, `expertise`, `position`, `dob`, `hometown`, `residence`, `phone`, `monthly_performance`) VALUES
(1, 'Hoàng Văn Đoàn', 'Kỹ sư Kỹ thuật GT', 'NVTK', '2001-10-22', 'Ba Chẽ - Quảng Ninh', 'Phường Bãi Cháy', '0329.112.707', 1.10),
(2, 'Nguyễn Tiến Thành', 'ThS. Quản lý xây dựng', 'Phó GĐ, TPTK', '1986-06-24', 'Khoái Châu - Hưng Yên', 'Phường Bãi Cháy', '0988.848.065', 1.18);

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
(1, 'huypham', 'Phạm Mạnh', 'Huy', '1996-04-04', 'Hạ Long', 'NCC', '0888121496', NULL, 'phamhuy.cngt@gmail.com', '$2y$10$YXa/ryYZhrQyEE6rBuORVugmQSIQgmwJxBdebsPapaDuzQDaeKPhy', 'admin', 'avatar_1.png', 4, NULL, NULL, '2025-07-30 01:18:51'),
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
(1, '2025-07-01', 'morning', 'Soạn văn đề nghị xác nhận áp dụng BIM dự án 330\r\nCấu hình CDE nội bộ cho các dự án BIM', NULL, '2025-08-04 02:29:06', '2025-08-04 02:29:06'),
(1, '2025-07-01', 'afternoon', 'Chỉnh sửa lại family cống hộp', NULL, '2025-08-04 02:29:06', '2025-08-04 02:29:06'),
(1, '2025-07-01', 'evening', 'Nghỉ', NULL, '2025-08-04 02:29:06', '2025-08-04 02:29:06'),
(1, '2025-07-02', 'morning', 'Tạo family cống tròn và cống hộp 2 khoang', NULL, '2025-08-04 02:29:14', '2025-08-04 02:29:14'),
(1, '2025-07-02', 'afternoon', 'Tạo family cống hộp 3 khoang và family hèm phai cống', NULL, '2025-08-04 02:29:14', '2025-08-04 02:29:14'),
(1, '2025-07-02', 'evening', 'Nghỉ', NULL, '2025-08-04 02:29:14', '2025-08-04 02:29:14'),
(1, '2025-07-03', 'morning', 'Tiếp tục chỉnh sửa family hèm phai cống', NULL, '2025-08-04 02:29:18', '2025-08-04 02:29:18'),
(1, '2025-07-03', 'afternoon', 'Cấu hình CDE nội bộ phục vụ dự án BIM', NULL, '2025-08-04 02:29:18', '2025-08-04 02:29:18'),
(1, '2025-07-03', 'evening', 'Nghỉ', NULL, '2025-08-04 02:29:18', '2025-08-04 02:29:18'),
(1, '2025-07-04', 'morning', 'Khắc phục lỗi thiếu Packages trong Dynamo Revit', NULL, '2025-08-04 02:29:20', '2025-08-04 02:29:20'),
(1, '2025-07-04', 'afternoon', 'Tiếp tục cấu hình CDE và phần mềm kết nối Revit với CDE', NULL, '2025-08-04 02:29:20', '2025-08-04 02:29:20'),
(1, '2025-07-04', 'evening', 'Nghỉ', NULL, '2025-08-04 02:29:20', '2025-08-04 02:29:20'),
(1, '2025-07-05', 'morning', 'Xây dựng thư viện thép Revit', NULL, '2025-08-04 02:29:24', '2025-08-04 02:29:24'),
(1, '2025-07-05', 'afternoon', 'Bản vẽ cốt thép ụ neo DA 4B QL18', NULL, '2025-08-04 02:29:24', '2025-08-04 02:29:24'),
(1, '2025-07-05', 'evening', 'Nghỉ', NULL, '2025-08-04 02:29:24', '2025-08-04 02:29:24'),
(1, '2025-07-06', 'morning', 'Nghỉ', NULL, '2025-08-04 02:29:28', '2025-08-04 02:29:28'),
(1, '2025-07-06', 'afternoon', 'Nghỉ', NULL, '2025-08-04 02:29:28', '2025-08-04 02:29:28'),
(1, '2025-07-06', 'evening', 'Nghỉ', NULL, '2025-08-04 02:29:28', '2025-08-04 02:29:28'),
(1, '2025-07-07', 'morning', 'Hoàn chỉnh thư viện thép Revit', NULL, '2025-08-04 02:29:31', '2025-08-04 02:29:31'),
(1, '2025-07-07', 'afternoon', 'Nghiên cứu cách khắc phục lỗi không triển khai cốt thép được trên dynamo 3.5 (BIM)', NULL, '2025-08-04 02:29:31', '2025-08-04 02:29:31'),
(1, '2025-07-07', 'evening', 'Nghỉ', NULL, '2025-08-04 02:29:31', '2025-08-04 02:29:31'),
(1, '2025-07-08', 'morning', 'Hạ cấp và dựng lại mô hình Khe Hố từ revit 2026 về 2025 để triển khai cốt thép', NULL, '2025-08-04 02:29:34', '2025-08-04 02:29:34'),
(1, '2025-07-08', 'afternoon', 'Viết node Dynamo hỗ trợ nhập dữ liệu excel vào Revit', NULL, '2025-08-04 02:29:34', '2025-08-04 02:29:34'),
(1, '2025-07-08', 'evening', 'Nghỉ', NULL, '2025-08-04 02:29:34', '2025-08-04 02:29:34'),
(1, '2025-07-09', 'morning', 'Viết node Dynamo hỗ trợ tạo cốt thép trong revit', NULL, '2025-08-04 02:29:38', '2025-08-04 02:29:38'),
(1, '2025-07-09', 'afternoon', 'Viết node Dynamo hỗ trợ tạo cốt thép trong revit', NULL, '2025-08-04 02:29:38', '2025-08-04 02:29:38'),
(1, '2025-07-09', 'evening', 'Nghỉ', NULL, '2025-08-04 02:29:38', '2025-08-04 02:29:38'),
(1, '2025-07-10', 'morning', 'Viết node Dynamo hỗ trợ tạo cốt thép trong revit', NULL, '2025-08-04 02:29:41', '2025-08-04 02:29:41'),
(1, '2025-07-10', 'afternoon', 'Viết node Dynamo hỗ trợ tạo cốt thép trong revit', NULL, '2025-08-04 02:29:41', '2025-08-04 02:29:41'),
(1, '2025-07-10', 'evening', 'Nghỉ', NULL, '2025-08-04 02:29:41', '2025-08-04 02:29:41'),
(1, '2025-07-11', 'morning', 'Viết node Dynamo hỗ trợ tạo cốt thép trong revit', NULL, '2025-08-04 02:29:44', '2025-08-04 02:29:44'),
(1, '2025-07-11', 'afternoon', 'Viết node Dynamo hỗ trợ tạo cốt thép trong revit', NULL, '2025-08-04 02:29:44', '2025-08-04 02:29:44'),
(1, '2025-07-11', 'evening', 'Nghỉ', NULL, '2025-08-04 02:29:44', '2025-08-04 02:29:44'),
(1, '2025-07-12', 'morning', 'Tường chắn đường nối VINCOM', NULL, '2025-08-04 02:31:52', '2025-08-04 02:31:52'),
(1, '2025-07-12', 'afternoon', 'Nghỉ Mát', NULL, '2025-08-04 02:31:52', '2025-08-04 02:31:52'),
(1, '2025-07-12', 'evening', 'Nghỉ', NULL, '2025-08-04 02:31:52', '2025-08-04 02:31:52'),
(1, '2025-07-13', 'morning', 'Nghỉ Mát', NULL, '2025-08-04 02:30:01', '2025-08-04 02:30:01'),
(1, '2025-07-13', 'afternoon', 'Nghỉ', NULL, '2025-08-04 02:30:01', '2025-08-04 02:30:01'),
(1, '2025-07-13', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:01', '2025-08-04 02:30:01'),
(1, '2025-07-14', 'morning', 'Tường chắn đường nối VINCOM', NULL, '2025-08-04 01:40:57', '2025-08-04 01:40:57'),
(1, '2025-07-14', 'afternoon', 'Tường chắn đường nối VINCOM', NULL, '2025-08-04 01:40:57', '2025-08-04 01:40:57'),
(1, '2025-07-14', 'evening', '17:00-19:00: Chi tiết khác và tính khối lượng', NULL, '2025-08-04 01:40:57', '2025-08-04 01:40:57'),
(1, '2025-07-15', 'morning', 'Tạo CDE BIM (tính năng login)', NULL, '2025-08-04 01:41:11', '2025-08-04 01:41:11'),
(1, '2025-07-15', 'afternoon', 'Tạo CDE BIM (tính năng tạo project)', NULL, '2025-08-04 01:41:12', '2025-08-04 01:41:12'),
(1, '2025-07-15', 'evening', '17:00-19:00: Bổ sung địa chất và chiều dài cọc tường chắn vincom', NULL, '2025-08-04 01:41:12', '2025-08-04 01:41:12'),
(1, '2025-07-16', 'morning', 'Tạo CDE BIM (tính năng tạo project)', NULL, '2025-08-04 02:30:06', '2025-08-04 02:30:06'),
(1, '2025-07-16', 'afternoon', 'Tạo CDE BIM (tính năng tạo mới và thay đổi thành viên)', NULL, '2025-08-04 02:30:06', '2025-08-04 02:30:06'),
(1, '2025-07-16', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:06', '2025-08-04 02:30:06'),
(1, '2025-07-17', 'morning', 'Tạo CDE BIM (tính năng nâng cấp chức năng cho thành viên)', NULL, '2025-08-04 02:30:09', '2025-08-04 02:30:09'),
(1, '2025-07-17', 'afternoon', 'Tạo CDE BIM (tính năng tạo văn bản cuộc họp)', NULL, '2025-08-04 02:30:09', '2025-08-04 02:30:09'),
(1, '2025-07-17', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:09', '2025-08-04 02:30:09'),
(1, '2025-07-18', 'morning', 'Tạo CDE BIM (tính năng tạo văn bản cuộc họp)', NULL, '2025-08-04 01:42:06', '2025-08-04 01:42:06'),
(1, '2025-07-18', 'afternoon', 'Tạo CDE BIM (tính năng tạo văn bản cuộc họp)', NULL, '2025-08-04 01:42:06', '2025-08-04 01:42:06'),
(1, '2025-07-18', 'evening', '17:00-19:00: Vẽ mặt cắt ngang QL10 và 279', NULL, '2025-08-04 01:42:06', '2025-08-04 01:42:06'),
(1, '2025-07-19', 'morning', 'Tạo CDE BIM (tính năng tạo văn bản cuộc họp)', NULL, '2025-08-04 02:30:13', '2025-08-04 02:30:13'),
(1, '2025-07-19', 'afternoon', 'Chỉnh sửa mặt cắt ngang QL10 và 279', NULL, '2025-08-04 02:30:13', '2025-08-04 02:30:13'),
(1, '2025-07-19', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:13', '2025-08-04 02:30:13'),
(1, '2025-07-20', 'morning', 'Nghỉ', NULL, '2025-08-04 02:23:50', '2025-08-04 02:23:50'),
(1, '2025-07-20', 'afternoon', 'Nghỉ', NULL, '2025-08-04 02:23:50', '2025-08-04 02:23:50'),
(1, '2025-07-20', 'evening', 'Nghỉ', NULL, '2025-08-04 02:23:50', '2025-08-04 02:23:50'),
(1, '2025-07-21', 'morning', 'Vẽ BTC cầu Đá Bạc và QL18B', NULL, '2025-08-04 02:30:18', '2025-08-04 02:30:18'),
(1, '2025-07-21', 'afternoon', 'Sửa lỗi CDE BIM  (tính năng tạo văn bản cuộc họp)', NULL, '2025-08-04 02:30:18', '2025-08-04 02:30:18'),
(1, '2025-07-21', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:18', '2025-08-04 02:30:18'),
(1, '2025-07-22', 'morning', 'Soát khối lượng làng lốc, làng dạ và in bản vẽ Đá Bạc', NULL, '2025-08-04 02:30:21', '2025-08-04 02:30:21'),
(1, '2025-07-22', 'afternoon', 'Họp trên ban II về thẩm định cầu Làng Lốc', NULL, '2025-08-04 02:30:21', '2025-08-04 02:30:21'),
(1, '2025-07-22', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:21', '2025-08-04 02:30:21'),
(1, '2025-07-23', 'morning', 'Sửa theo thẩm định Làng Lốc, Khe Pụt', NULL, '2025-08-04 02:30:24', '2025-08-04 02:30:24'),
(1, '2025-07-23', 'afternoon', 'Đi hiện trường cầu Sông Chanh', NULL, '2025-08-04 02:30:24', '2025-08-04 02:30:24'),
(1, '2025-07-23', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:24', '2025-08-04 02:30:24'),
(1, '2025-07-24', 'morning', 'Bản vẽ cầu Vũ Oai', NULL, '2025-08-04 02:30:26', '2025-08-04 02:30:26'),
(1, '2025-07-24', 'afternoon', 'Bản vẽ cầu Vũ Oai', NULL, '2025-08-04 02:30:26', '2025-08-04 02:30:26'),
(1, '2025-07-24', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:26', '2025-08-04 02:30:26'),
(1, '2025-07-25', 'morning', 'Sửa thẩm định Khe Pụt, slide báo cáo sông chanh, bản vẽ, khối lượng cầu Vũ Oai', NULL, '2025-08-04 02:30:33', '2025-08-04 02:30:33'),
(1, '2025-07-25', 'afternoon', 'Họp bên ban QLBT Công trình (cầu Vũ Oai, Sông Chanh)', NULL, '2025-08-04 02:30:33', '2025-08-04 02:30:33'),
(1, '2025-07-25', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:33', '2025-08-04 02:30:33'),
(1, '2025-07-26', 'morning', 'Thêm PA cầu Vũ Oai', NULL, '2025-08-04 02:30:35', '2025-08-04 02:30:35'),
(1, '2025-07-26', 'afternoon', 'Tiếp tục việc buổi sáng', NULL, '2025-08-04 02:30:35', '2025-08-04 02:30:35'),
(1, '2025-07-26', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:35', '2025-08-04 02:30:35'),
(1, '2025-07-27', 'morning', 'Sửa mặt cắt ngang kè Làng Lốc thẩm định', NULL, '2025-08-04 02:30:38', '2025-08-04 02:30:38'),
(1, '2025-07-27', 'afternoon', 'Sửa các nội dung còn lại của phần tuyến Làng Lốc', NULL, '2025-08-04 02:30:38', '2025-08-04 02:30:38'),
(1, '2025-07-27', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:38', '2025-08-04 02:30:38'),
(1, '2025-07-28', 'morning', 'Chỉnh lại 1 số bản vẽ Làng Lốc và chỉ dẫn kỹ thuật, thuyết minh Làng Lốc, Khe Pụt', NULL, '2025-08-04 02:30:46', '2025-08-04 02:30:46'),
(1, '2025-07-28', 'afternoon', 'Bố trí chung cầu Quảng Đức và chỉnh lại khối lượng làng lốc, khe pụt', NULL, '2025-08-04 02:30:46', '2025-08-04 02:30:46'),
(1, '2025-07-28', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:46', '2025-08-04 02:30:46'),
(1, '2025-07-29', 'morning', 'Tìm hiểu CDE ADS Civil', NULL, '2025-08-04 02:30:49', '2025-08-04 02:30:49'),
(1, '2025-07-29', 'afternoon', 'Nghỉ khám bệnh', NULL, '2025-08-04 02:30:49', '2025-08-04 02:30:49'),
(1, '2025-07-29', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:49', '2025-08-04 02:30:49'),
(1, '2025-07-30', 'morning', 'Sửa CDE nội bộ đã viết theo chuẩn mới', NULL, '2025-08-04 02:30:51', '2025-08-04 02:30:51'),
(1, '2025-07-30', 'afternoon', 'Sửa CDE nội bộ đã viết theo chuẩn mới', NULL, '2025-08-04 02:30:51', '2025-08-04 02:30:51'),
(1, '2025-07-30', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:51', '2025-08-04 02:30:51'),
(1, '2025-07-31', 'morning', 'Nghỉ lễ', NULL, '2025-08-04 02:30:53', '2025-08-04 03:44:54'),
(1, '2025-07-31', 'afternoon', 'Nghỉ lễ : Viết báo cáo giải trình thẩm định Làng Lốc', NULL, '2025-08-04 02:30:53', '2025-08-04 03:52:34'),
(1, '2025-07-31', 'evening', 'Nghỉ', NULL, '2025-08-04 02:30:53', '2025-08-04 02:30:53'),
(1, '2025-08-01', 'morning', 'Quy trình bảo trì cầu Làng Lốc, Khe Pụt', NULL, '2025-08-04 10:08:37', '2025-08-04 10:08:37'),
(1, '2025-08-01', 'afternoon', 'Quy trình bảo trì cầu Làng Lốc, Khe Pụt', NULL, '2025-08-04 10:08:37', '2025-08-04 10:08:37'),
(1, '2025-08-01', 'evening', 'Nghỉ', NULL, '2025-08-04 10:08:37', '2025-08-04 10:08:37'),
(1, '2025-08-02', 'morning', 'Tính năng gửi báo cáo khi viết xong nhật ký công việc', NULL, '2025-08-04 10:09:07', '2025-08-04 10:09:07'),
(1, '2025-08-02', 'afternoon', 'Tính năng gửi báo cáo khi viết xong nhật ký công việc', NULL, '2025-08-04 10:09:07', '2025-08-04 10:09:07'),
(1, '2025-08-02', 'evening', '17:00-19:30: Tính năng gửi báo cáo khi viết xong nhật ký công việc', NULL, '2025-08-04 10:09:07', '2025-08-04 10:09:07'),
(1, '2025-08-03', 'morning', 'Nghỉ', NULL, '2025-08-04 09:17:41', '2025-08-04 09:17:41'),
(1, '2025-08-03', 'afternoon', 'Nghỉ', NULL, '2025-08-04 09:17:41', '2025-08-04 09:17:41'),
(1, '2025-08-03', 'evening', 'Nghỉ', NULL, '2025-08-04 09:17:41', '2025-08-04 09:17:41'),
(1, '2025-08-04', 'morning', 'Cập nhật tính năng thống kê Nhật ký công việc\r\nViết giải trình thẩm định Khe Pụt', NULL, '2025-08-04 10:09:35', '2025-08-04 10:09:35'),
(1, '2025-08-04', 'afternoon', 'Cập nhật tính năng thống kê Nhật ký công việc', NULL, '2025-08-04 10:09:35', '2025-08-04 10:09:35'),
(1, '2025-08-04', 'evening', '17:00-19:30: Nghỉ', NULL, '2025-08-04 10:09:36', '2025-08-04 10:09:36'),
(4, '2025-08-01', 'morning', 'Sửa QTBT cầu Làng Lốc, Khe Pụt', NULL, '2025-08-04 03:02:42', '2025-08-04 03:02:42'),
(4, '2025-08-01', 'afternoon', 'Sửa QTBT cầu Làng Lốc, Khe Pụt', NULL, '2025-08-04 03:02:42', '2025-08-04 03:02:42'),
(4, '2025-08-01', 'evening', 'Nghỉ', NULL, '2025-08-04 03:02:42', '2025-08-04 03:02:42'),
(4, '2025-08-02', 'morning', 'Cập nhật tính năng ghi nhật ký công việc trên CDE', NULL, '2025-08-04 03:02:45', '2025-08-04 03:02:45'),
(4, '2025-08-02', 'afternoon', 'Cập nhật tính năng ghi nhật ký công việc trên CDE', NULL, '2025-08-04 03:02:45', '2025-08-04 03:02:45'),
(4, '2025-08-02', 'evening', '17:00-19:30: Cập nhật tính năng xuất Nhật ký công việc ra Excel', NULL, '2025-08-04 03:02:45', '2025-08-04 03:02:45'),
(4, '2025-08-03', 'morning', 'Nghỉ', NULL, '2025-08-04 03:03:06', '2025-08-04 03:03:06'),
(4, '2025-08-03', 'afternoon', 'Nghỉ', NULL, '2025-08-04 03:03:06', '2025-08-04 03:03:06'),
(4, '2025-08-03', 'evening', 'Nghỉ', NULL, '2025-08-04 03:03:06', '2025-08-04 03:03:06'),
(4, '2025-08-04', 'morning', 'Cập nhật tính năng thống kê nhật ký công việc trên CDE\r\nSoạn giải trình thẩm tra Khe Pụt', NULL, '2025-08-04 03:03:42', '2025-08-04 03:03:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `fk_notify_sender` (`sender_id`);

--
-- Indexes for table `organizations`
--
ALTER TABLE `organizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `organization_invitations`
--
ALTER TABLE `organization_invitations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organization_id` (`organization_id`);

--
-- Indexes for table `organization_members`
--
ALTER TABLE `organization_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organization_id` (`organization_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `organization_member_profiles`
--
ALTER TABLE `organization_member_profiles`
  ADD PRIMARY KEY (`member_id`);

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
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `organization_invitations`
--
ALTER TABLE `organization_invitations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `organization_members`
--
ALTER TABLE `organization_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notify_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notify_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `organizations`
--
ALTER TABLE `organizations`
  ADD CONSTRAINT `organizations_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `organization_invitations`
--
ALTER TABLE `organization_invitations`
  ADD CONSTRAINT `organization_invitations_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `organization_members`
--
ALTER TABLE `organization_members`
  ADD CONSTRAINT `organization_members_ibfk_1` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `organization_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `organization_member_profiles`
--
ALTER TABLE `organization_member_profiles`
  ADD CONSTRAINT `organization_member_profiles_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `organization_members` (`id`) ON DELETE CASCADE;

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
