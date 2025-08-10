-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 10, 2025 at 04:49 PM
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
(35, 1, 12, '2025-07-01', '2025-08-08 08:00:19', 1);

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
  `share_subscription` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organizations`
--

INSERT INTO `organizations` (`id`, `name`, `abbreviation`, `address`, `department`, `created_by`, `share_subscription`, `created_at`) VALUES
(1, 'CÔNG TY CỔ PHẦN TƯ VẤN MIỀN BẮC', 'NCC', 'Tổ 5, khu 1, Phường Bãi Cháy, Tỉnh Quảng Ninh', 'PHÒNG THIẾT KẾ', 1, 0, '2025-08-04 14:06:12');

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
(1, 1, '', '7ccbdb357e36873612651c88fedf3c08', 'accepted', '2025-08-05 01:31:50', '2025-08-07 11:44:44');

-- --------------------------------------------------------

--
-- Table structure for table `organization_members`
--

CREATE TABLE `organization_members` (
  `id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `is_shared` tinyint(1) NOT NULL DEFAULT 0,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `subscribed_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `organization_members`
--

INSERT INTO `organization_members` (`id`, `organization_id`, `user_id`, `is_shared`, `role`, `subscribed_id`, `joined_at`) VALUES
(4, 1, 13, 0, 'member', 1, '2025-08-07 04:19:44'),
(5, 1, 12, 0, 'member', 1, '2025-08-07 04:21:06'),
(6, 1, 14, 0, 'member', 1, '2025-08-07 04:21:24'),
(7, 1, 15, 0, 'member', 1, '2025-08-07 04:21:46'),
(8, 1, 1, 0, 'admin', 1, '2025-08-07 04:21:59'),
(10, 1, 16, 0, 'member', 1, '2025-08-07 09:27:18'),
(12, 1, 17, 0, 'member', 1, '2025-08-07 10:59:50'),
(13, 1, 18, 0, 'member', 1, '2025-08-07 11:16:53'),
(14, 1, 19, 0, 'member', 1, '2025-08-07 11:24:22'),
(15, 1, 20, 0, 'member', 1, '2025-08-07 11:44:44');

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
(4, 'Nguyễn Tiến Thành', 'ThS. Quản lý XD', 'Phó GĐ', '1986-06-24', 'Khoái Châu, Hưng Yên', 'P.Bãi Cháy', '0988848065', 0.00),
(5, 'Nguyễn Văn Dũng', 'Kỹ sư cầu - đường', 'Phó PTK', '1989-04-07', 'Bãi Cháy, Quảng Ninh', 'P.Bãi Cháy', '0904057489', 0.00),
(6, 'Vũ Mạnh Tưởng', 'Kỹ sư cầu - Hầm', 'Phó PTK', '1988-10-07', 'Gia Lộc, Hải Phòng', 'P.Trần Hưng Đạo', '0366242442', 0.00),
(7, 'Nguyễn Quang Trường', 'Kỹ sư Kỹ thuật XD', 'NVTK', '1999-06-01', 'Cẩm Phả, Quảng Ninh', 'P.Bãi Cháy', '0365227188', 0.00),
(8, 'Phạm Mạnh Huy', 'ThS. Kỹ thuật XDCTGT', 'NVTK', '1996-04-04', 'Kiến An, Hải Phòng', 'P.Hồng Hà', '0888121496', 0.00),
(10, 'Trần Thành Ninh', 'Kỹ sư Kỹ thuật GT', 'NVTK', '2001-05-27', 'Ha Long', 'P.Hồng Hà', '0355318338', 0.00),
(12, 'Hoàng Văn Đoàn', 'Kỹ sư Kỹ thuật GT', 'NVTK', '2001-10-22', 'Ba Chẽ, Quảng Ninh', 'P.Bãi Cháy', '0329112707', 0.00),
(13, 'La Thị Bích Hòa', 'Kỹ sư XDCTGT', 'NVTK', '2000-07-09', 'Tiên Yên, Quảng Ninh', 'P.Bãi Cháy', '0346456537', 0.00),
(14, 'Nguyễn Thị Thu Thủy', 'Kỹ sư ĐG', 'NVDT', '1976-09-09', 'Bãi Cháy, Quảng Ninh', 'P.Bãi Cháy', '0977660976', 0.00),
(15, 'Hoàng Minh Thùy', 'Kỹ sư XDCTGT', 'NVTK', '1992-01-10', 'Gia Lộc, Hải Phòng', 'P.Yết Kiêu', '0982153092', 0.00);

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
  `allow_work_diary` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Enable Work Diary feature',
  `allow_organization_manage` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Allow manage org'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `name`, `price`, `description`, `created_at`, `updated_at`, `max_storage_gb`, `max_projects`, `max_company_members`, `allow_organization_members`, `allow_work_diary`, `allow_organization_manage`) VALUES
(1, 'Free', 0.00, '1 GB chung toàn bộ tài khoản\r\nTối đa 1 dự án\r\nTối đa 1 thành viên\r\nServer dữ liệu tại Việt Nam', '2025-07-30 09:43:49', '2025-08-01 09:44:54', 1, 1, 1, 0, 0, 0),
(2, 'Personal', 1000000.00, '15 GB chung toàn bộ tài khoản\r\nTối đa 2 dự án\r\nTối đa 3 thành viên\r\nServer dữ liệu tại Việt Nam', '2025-07-30 09:43:56', '2025-08-01 09:45:02', 15, 2, 3, 0, 0, 0),
(3, 'Pro', 3000000.00, '150 GB chung toàn bộ tài khoản\r\nTối đa 10 dự án\r\nTối đa 12 thành viên\r\nBao gồm tính năng gói Personal\r\nThêm tính năng Organization Members', '2025-07-30 09:44:03', '2025-08-08 16:04:00', 150, 10, 12, 0, 0, 0),
(4, 'Bussines', 7000000.00, '1 TB chung toàn bộ tài khoản\r\nKhông giới hạn dự án\r\nKhông giới hạn thành viên\r\nBao gồm tính năng gói Pro\r\nThêm tính năng Work Diary', '2025-07-30 09:45:44', '2025-08-08 02:55:31', 1024, 0, 0, 1, 1, 1);

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
(1, 'huypham', 'Phạm Mạnh', 'Huy', '1996-04-04', 'Hạ Long', 'NCC', '0888121496', NULL, 'phamhuy.cngt@gmail.com', '$2y$10$eWJmpFzHs1w4JSCwZ.ElWukVdOJ16hb9RPmcfdXXETZCz3gqXvlbq', 'admin', 'avatar_1.png', 3, NULL, NULL, '2025-07-30 01:18:51'),
(12, 'dungnguyen', 'Nguyễn Văn', 'Dũng', '1989-04-07', 'Hạ Long', 'NCC', '0904057489', NULL, 'dungtvmb@gmail.com', '$2y$10$rsk2.FqXwNO7d33vf2ll2uo8HcCM9lbjhVFRPUREU3vfjnVCUSMZq', 'user', NULL, NULL, NULL, NULL, '2025-08-07 04:08:57'),
(13, 'thanhtvmb', 'Nguyễn Tiến', 'Thành', '1986-06-24', 'Hạ Long', 'NCC', '0988848065', NULL, 'thanhtvmb@gmail.com', '$2y$10$GSkjFC40XAYI944BTf5STuWaPFcVDYKR8m6DHYLDhTs70.xPpanve', 'user', NULL, NULL, NULL, NULL, '2025-08-07 04:08:57'),
(14, 'tuongtvmb', 'Vũ Mạnh', 'Tưởng', '1988-10-07', 'Hạ Long', 'NCC', '0366242442', NULL, 'tuongtvmb@gmail.com', '$2y$10$YhTRsTu9ige0fLxuJZykg.266T5i6CPxLzK5xxwMTngnbzFJN7jzq', 'user', NULL, NULL, NULL, NULL, '2025-08-07 04:08:57'),
(15, 'truongtvmb', 'Nguyễn Quang', 'Trường', '1999-06-01', 'Hạ Long', 'NCC', '0365227188', NULL, 'quangtruongnguyen035@gmail.com', '$2y$10$UHWk7VWvg.q/DpCwNicyuu/MbDbVE83Fc0n7zrdYyvOwcX7.urhxe', 'user', NULL, NULL, NULL, NULL, '2025-08-07 04:08:57'),
(16, 'ninhtvmb', 'Trần Thành ', 'Ninh', '2001-05-27', 'Hạ Long', 'NCC', '0355318338', NULL, 'thanhninh7000@gmail.com', '$2y$10$FFkJz0nleKLiKLefSFWOlOaGCC08Ir6G1QGEEURM5yYglyBDt.xem', 'user', NULL, NULL, NULL, NULL, '2025-08-07 09:25:43'),
(17, 'doantvmb', 'Hoàng Văn', 'Đoàn', '2001-10-22', 'Hạ Long', 'NCC', '0329112707', NULL, 'doantvmb2625@gmail.com', '$2y$10$Uo4MkPdJ7i7lRGv/vRH7QeVcDjC6UkH7t7/NG85.BFSReT86U2eOG', 'user', NULL, NULL, NULL, NULL, '2025-08-07 10:43:16'),
(18, 'hoatvmb', 'La Thị Bích', 'Hòa', '2000-07-09', 'Hạ Long', 'NCC', '0346456537', NULL, 'lathibichhoa12sinh@gmail.com', '$2y$10$ghHAswrEF017sc3jpkU3H.dQQc.gwFxJFV4n5rUbgpv7bzNfnrGH.', 'user', NULL, NULL, NULL, NULL, '2025-08-07 11:00:56'),
(19, 'hangtvmb', 'Nguyễn Thị Thu', 'Thủy', '1976-09-09', 'Hạ Long', 'NCC', '0977660976', NULL, 'user1@bimtech.edu.vn', '$2y$10$IB1rwpJqMjHtcZ16b/e5qu.wnIVyzXxYP5bgbBaVeICYwmbU4AoeG', 'user', NULL, NULL, NULL, NULL, '2025-08-07 11:21:42'),
(20, 'thuyhm', 'Hoàng Minh', 'Thùy', '1992-01-10', 'Hạ Long', 'NCC', '0982153092', NULL, 'user2@bimtech.edu.vn', '$2y$10$qrbU2c6O5gei.jSZUFnD4eDq0YJuwY5yS8VMKCwDUPIRMNctr9KwO', 'user', NULL, NULL, NULL, NULL, '2025-08-07 11:22:43');

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
(1, '2025-07-31', 'morning', 'Sửa CDE nội bộ đã viết theo chuẩn mới', NULL, '2025-08-05 03:24:58', '2025-08-05 03:24:58'),
(1, '2025-07-31', 'afternoon', 'Viết báo cáo giải trình thẩm định Làng Lốc', NULL, '2025-08-05 03:24:58', '2025-08-07 01:32:23'),
(1, '2025-07-31', 'evening', '17:00-19:30: Nghỉ', NULL, '2025-08-05 03:24:58', '2025-08-05 03:24:58'),
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
(1, '2025-08-05', 'morning', 'Hoàn thiện tính năng thống kê nhật ký công việc theo cá nhân\r\nHoàn thiện tính năng quản lý thông tin thành viên trong phòng', NULL, '2025-08-05 03:35:32', '2025-08-05 03:35:32'),
(1, '2025-08-05', 'afternoon', 'Phát triển tính năng thống kê nhật ký công việc toàn bộ thành viên và xuất excel', NULL, '2025-08-05 03:35:32', '2025-08-05 03:35:32'),
(1, '2025-08-05', 'evening', 'Nghỉ', NULL, '2025-08-05 03:35:32', '2025-08-05 03:35:32'),
(1, '2025-08-06', 'morning', 'Cập nhật tính năng xuất thống kê làm thêm giờ và ngày CN', NULL, '2025-08-07 08:44:30', '2025-08-07 08:44:30'),
(1, '2025-08-06', 'afternoon', 'Đi hiện trường cầu Vũ Oai', NULL, '2025-08-07 08:44:30', '2025-08-07 08:44:30'),
(1, '2025-08-07', 'morning', 'Hoàn thiện tính năng xuất file nhật ký công việc và file thống kê', NULL, '2025-08-07 12:15:23', '2025-08-07 12:15:23'),
(1, '2025-08-07', 'afternoon', 'Hoàn thiện tính năng xuất file nhật ký công việc và file thống kê', NULL, '2025-08-07 12:15:23', '2025-08-07 12:15:23'),
(1, '2025-08-07', 'evening', '17:00-19:30: Cập nhật dữ liệu thành viên trong phòng và kiểm tra tính năng xuất thống kê nhật ký công việc', NULL, '2025-08-07 12:15:23', '2025-08-07 12:15:23'),
(1, '2025-08-08', 'morning', 'Chỉnh sửa tính năng quan lý tổ chức và phân quyền thành viên', NULL, '2025-08-08 07:18:27', '2025-08-08 07:18:27'),
(1, '2025-08-08', 'afternoon', 'Chỉnh sửa tính năng quan lý tổ chức và phân quyền thành viên', NULL, '2025-08-08 07:18:28', '2025-08-08 07:18:28'),
(1, '2025-08-08', 'evening', 'Nghỉ', NULL, '2025-08-08 07:18:28', '2025-08-08 07:18:28'),
(12, '2025-07-01', 'morning', 'làm', NULL, '2025-08-07 04:29:54', '2025-08-07 04:29:54'),
(12, '2025-07-01', 'afternoon', 'làm', NULL, '2025-08-07 04:29:54', '2025-08-07 04:29:54'),
(12, '2025-07-02', 'morning', 'làm', NULL, '2025-08-07 04:30:01', '2025-08-07 04:30:01'),
(12, '2025-07-02', 'afternoon', 'làm', NULL, '2025-08-07 04:30:01', '2025-08-07 04:30:01'),
(12, '2025-07-03', 'morning', 'làm', NULL, '2025-08-07 04:30:05', '2025-08-07 04:30:05'),
(12, '2025-07-03', 'afternoon', 'làm', NULL, '2025-08-07 04:30:05', '2025-08-07 04:30:05'),
(12, '2025-07-04', 'morning', 'làm', NULL, '2025-08-07 04:30:08', '2025-08-07 04:30:08'),
(12, '2025-07-04', 'afternoon', 'làm', NULL, '2025-08-07 04:30:08', '2025-08-07 04:30:08'),
(12, '2025-07-05', 'morning', 'làm', NULL, '2025-08-07 04:32:51', '2025-08-07 04:32:51'),
(12, '2025-07-05', 'afternoon', 'làm', NULL, '2025-08-07 04:32:51', '2025-08-07 04:32:51'),
(12, '2025-07-07', 'morning', 'làm', NULL, '2025-08-07 04:31:47', '2025-08-07 04:31:47'),
(12, '2025-07-07', 'afternoon', 'làm', NULL, '2025-08-07 04:31:47', '2025-08-07 04:31:47'),
(12, '2025-07-07', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:31:47', '2025-08-07 04:31:47'),
(12, '2025-07-08', 'morning', 'làm', NULL, '2025-08-07 04:30:21', '2025-08-07 04:30:21'),
(12, '2025-07-08', 'afternoon', 'làm', NULL, '2025-08-07 04:30:21', '2025-08-07 04:30:21'),
(12, '2025-07-09', 'morning', 'làm', NULL, '2025-08-07 04:30:24', '2025-08-07 04:30:24'),
(12, '2025-07-09', 'afternoon', 'làm', NULL, '2025-08-07 04:30:24', '2025-08-07 04:30:24'),
(12, '2025-07-10', 'morning', 'làm', NULL, '2025-08-07 04:30:26', '2025-08-07 04:30:26'),
(12, '2025-07-10', 'afternoon', 'làm', NULL, '2025-08-07 04:30:26', '2025-08-07 04:30:26'),
(12, '2025-07-11', 'morning', 'làm', NULL, '2025-08-07 04:30:30', '2025-08-07 04:30:30'),
(12, '2025-07-11', 'afternoon', 'làm', NULL, '2025-08-07 04:30:30', '2025-08-07 04:30:30'),
(12, '2025-07-12', 'morning', 'làm', NULL, '2025-08-07 04:30:32', '2025-08-07 04:30:32'),
(12, '2025-07-14', 'morning', 'làm', NULL, '2025-08-07 04:31:53', '2025-08-07 04:31:53'),
(12, '2025-07-14', 'afternoon', 'làm', NULL, '2025-08-07 04:31:53', '2025-08-07 04:31:53'),
(12, '2025-07-14', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:31:53', '2025-08-07 04:31:53'),
(12, '2025-07-15', 'morning', 'làm', NULL, '2025-08-07 04:31:54', '2025-08-07 04:31:54'),
(12, '2025-07-15', 'afternoon', 'làm', NULL, '2025-08-07 04:31:54', '2025-08-07 04:31:54'),
(12, '2025-07-15', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:31:54', '2025-08-07 04:31:54'),
(12, '2025-07-16', 'morning', 'làm', NULL, '2025-08-07 04:31:56', '2025-08-07 04:31:56'),
(12, '2025-07-16', 'afternoon', 'làm', NULL, '2025-08-07 04:31:56', '2025-08-07 04:31:56'),
(12, '2025-07-16', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:31:56', '2025-08-07 04:31:56'),
(12, '2025-07-17', 'morning', 'làm', NULL, '2025-08-07 04:31:59', '2025-08-07 04:31:59'),
(12, '2025-07-17', 'afternoon', 'làm', NULL, '2025-08-07 04:31:59', '2025-08-07 04:31:59'),
(12, '2025-07-17', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:31:59', '2025-08-07 04:31:59'),
(12, '2025-07-18', 'morning', 'làm', NULL, '2025-08-07 04:30:55', '2025-08-07 04:30:55'),
(12, '2025-07-18', 'afternoon', 'Nghỉ', NULL, '2025-08-07 04:30:55', '2025-08-07 07:02:24'),
(12, '2025-07-19', 'morning', 'làm', NULL, '2025-08-07 07:23:02', '2025-08-07 07:23:02'),
(12, '2025-07-19', 'afternoon', 'Nghỉ', NULL, '2025-08-07 07:23:02', '2025-08-07 07:23:02'),
(12, '2025-07-19', 'evening', '17:00-19:30: Nghỉ', NULL, '2025-08-07 07:23:02', '2025-08-07 07:23:02'),
(12, '2025-07-20', 'morning', 'Nghỉ', NULL, '2025-08-07 07:14:19', '2025-08-07 07:14:19'),
(12, '2025-07-20', 'afternoon', 'Nghỉ', NULL, '2025-08-07 07:14:19', '2025-08-07 07:14:19'),
(12, '2025-07-20', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 07:14:19', '2025-08-07 07:14:19'),
(12, '2025-07-21', 'morning', 'làm', NULL, '2025-08-07 04:31:01', '2025-08-07 04:31:01'),
(12, '2025-07-21', 'afternoon', 'làm', NULL, '2025-08-07 04:31:01', '2025-08-07 04:31:01'),
(12, '2025-07-22', 'morning', 'làm', NULL, '2025-08-07 04:32:15', '2025-08-07 04:32:15'),
(12, '2025-07-22', 'afternoon', 'làm', NULL, '2025-08-07 04:32:15', '2025-08-07 04:32:15'),
(12, '2025-07-22', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:32:15', '2025-08-07 04:32:15'),
(12, '2025-07-23', 'morning', 'làm', NULL, '2025-08-07 04:32:17', '2025-08-07 04:32:17'),
(12, '2025-07-23', 'afternoon', 'làm', NULL, '2025-08-07 04:32:17', '2025-08-07 04:32:17'),
(12, '2025-07-23', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:32:17', '2025-08-07 04:32:17'),
(12, '2025-07-24', 'morning', 'làm', NULL, '2025-08-07 04:32:19', '2025-08-07 04:32:19'),
(12, '2025-07-24', 'afternoon', 'làm', NULL, '2025-08-07 04:32:19', '2025-08-07 04:32:19'),
(12, '2025-07-24', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:32:19', '2025-08-07 04:32:19'),
(12, '2025-07-25', 'morning', 'làm', NULL, '2025-08-07 04:32:20', '2025-08-07 04:32:20'),
(12, '2025-07-25', 'afternoon', 'làm', NULL, '2025-08-07 04:32:20', '2025-08-07 04:32:20'),
(12, '2025-07-25', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:32:20', '2025-08-07 04:32:20'),
(12, '2025-07-26', 'morning', 'làm', NULL, '2025-08-07 04:31:15', '2025-08-07 04:31:15'),
(12, '2025-07-27', 'morning', 'làm', NULL, '2025-08-07 04:33:22', '2025-08-07 04:33:22'),
(12, '2025-07-27', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:33:22', '2025-08-07 04:33:22'),
(12, '2025-07-28', 'morning', 'làm', NULL, '2025-08-07 04:32:29', '2025-08-07 04:32:29'),
(12, '2025-07-28', 'afternoon', 'làm', NULL, '2025-08-07 04:32:29', '2025-08-07 04:32:29'),
(12, '2025-07-28', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:32:29', '2025-08-07 04:32:29'),
(12, '2025-07-29', 'morning', 'làm', NULL, '2025-08-07 04:32:31', '2025-08-07 04:32:31'),
(12, '2025-07-29', 'afternoon', 'làm', NULL, '2025-08-07 04:32:31', '2025-08-07 04:32:31'),
(12, '2025-07-29', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:32:31', '2025-08-07 04:32:31'),
(12, '2025-07-30', 'morning', 'làm', NULL, '2025-08-07 04:32:33', '2025-08-07 04:32:33'),
(12, '2025-07-30', 'afternoon', 'làm', NULL, '2025-08-07 04:32:33', '2025-08-07 04:32:33'),
(12, '2025-07-30', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:32:33', '2025-08-07 04:32:33'),
(12, '2025-07-31', 'morning', 'làm', NULL, '2025-08-07 04:31:26', '2025-08-07 04:31:26'),
(12, '2025-07-31', 'afternoon', 'làm', NULL, '2025-08-07 04:31:26', '2025-08-07 04:31:26'),
(13, '2025-07-01', 'morning', 'làm', NULL, '2025-08-07 04:34:49', '2025-08-07 04:34:49'),
(13, '2025-07-01', 'afternoon', 'làm', NULL, '2025-08-07 04:34:49', '2025-08-07 04:34:49'),
(13, '2025-07-02', 'morning', 'làm', NULL, '2025-08-07 04:34:52', '2025-08-07 04:34:52'),
(13, '2025-07-02', 'afternoon', 'làm', NULL, '2025-08-07 04:34:52', '2025-08-07 04:34:52'),
(13, '2025-07-03', 'morning', 'làm', NULL, '2025-08-07 04:34:55', '2025-08-07 04:34:55'),
(13, '2025-07-03', 'afternoon', 'làm', NULL, '2025-08-07 04:34:55', '2025-08-07 04:34:55'),
(13, '2025-07-04', 'morning', 'làm', NULL, '2025-08-07 04:34:57', '2025-08-07 04:34:57'),
(13, '2025-07-04', 'afternoon', 'làm', NULL, '2025-08-07 04:34:57', '2025-08-07 04:34:57'),
(13, '2025-07-05', 'morning', 'làm', NULL, '2025-08-07 04:37:06', '2025-08-07 04:37:06'),
(13, '2025-07-05', 'afternoon', 'làm', NULL, '2025-08-07 04:37:06', '2025-08-07 04:37:06'),
(13, '2025-07-07', 'morning', 'làm', NULL, '2025-08-07 04:35:13', '2025-08-07 04:35:13'),
(13, '2025-07-07', 'afternoon', 'làm', NULL, '2025-08-07 04:35:13', '2025-08-07 04:35:13'),
(13, '2025-07-08', 'morning', 'làm', NULL, '2025-08-07 04:35:15', '2025-08-07 04:35:15'),
(13, '2025-07-08', 'afternoon', 'làm', NULL, '2025-08-07 04:35:15', '2025-08-07 04:35:15'),
(13, '2025-07-09', 'morning', 'làm', NULL, '2025-08-07 04:35:17', '2025-08-07 04:35:17'),
(13, '2025-07-09', 'afternoon', 'làm', NULL, '2025-08-07 04:35:17', '2025-08-07 04:35:17'),
(13, '2025-07-10', 'morning', 'làm', NULL, '2025-08-07 04:35:20', '2025-08-07 04:35:20'),
(13, '2025-07-10', 'afternoon', 'làm', NULL, '2025-08-07 04:35:20', '2025-08-07 04:35:20'),
(13, '2025-07-11', 'morning', 'làm', NULL, '2025-08-07 04:35:23', '2025-08-07 04:35:23'),
(13, '2025-07-11', 'afternoon', 'làm', NULL, '2025-08-07 04:35:23', '2025-08-07 04:35:23'),
(13, '2025-07-12', 'morning', 'làm', NULL, '2025-08-07 04:35:06', '2025-08-07 04:35:06'),
(13, '2025-07-14', 'morning', 'làm', NULL, '2025-08-07 04:36:15', '2025-08-07 04:36:15'),
(13, '2025-07-14', 'afternoon', 'làm', NULL, '2025-08-07 04:36:15', '2025-08-07 04:36:15'),
(13, '2025-07-14', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:36:15', '2025-08-07 04:36:15'),
(13, '2025-07-15', 'morning', 'làm', NULL, '2025-08-07 04:35:28', '2025-08-07 04:35:28'),
(13, '2025-07-15', 'afternoon', 'làm', NULL, '2025-08-07 04:35:28', '2025-08-07 04:35:28'),
(13, '2025-07-16', 'morning', 'làm', NULL, '2025-08-07 04:35:30', '2025-08-07 04:35:30'),
(13, '2025-07-16', 'afternoon', 'làm', NULL, '2025-08-07 04:35:30', '2025-08-07 04:35:30'),
(13, '2025-07-17', 'morning', 'làm', NULL, '2025-08-07 04:35:32', '2025-08-07 04:35:32'),
(13, '2025-07-17', 'afternoon', 'làm', NULL, '2025-08-07 04:35:32', '2025-08-07 04:35:32'),
(13, '2025-07-18', 'morning', 'làm', NULL, '2025-08-07 04:36:18', '2025-08-07 04:36:18'),
(13, '2025-07-18', 'afternoon', 'làm', NULL, '2025-08-07 04:36:18', '2025-08-07 04:36:18'),
(13, '2025-07-18', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:36:18', '2025-08-07 04:36:18'),
(13, '2025-07-19', 'morning', 'làm', NULL, '2025-08-07 04:37:10', '2025-08-07 04:37:10'),
(13, '2025-07-19', 'afternoon', 'làm', NULL, '2025-08-07 04:37:10', '2025-08-07 04:37:10'),
(13, '2025-07-20', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:36:19', '2025-08-07 04:36:19'),
(13, '2025-07-21', 'morning', 'làm', NULL, '2025-08-07 04:35:37', '2025-08-07 04:35:37'),
(13, '2025-07-21', 'afternoon', 'làm', NULL, '2025-08-07 04:35:37', '2025-08-07 04:35:37'),
(13, '2025-07-22', 'morning', 'làm', NULL, '2025-08-07 04:36:29', '2025-08-07 04:36:29'),
(13, '2025-07-22', 'afternoon', 'làm', NULL, '2025-08-07 04:36:30', '2025-08-07 04:36:30'),
(13, '2025-07-22', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:36:30', '2025-08-07 04:36:30'),
(13, '2025-07-23', 'morning', 'làm', NULL, '2025-08-07 04:36:31', '2025-08-07 04:36:31'),
(13, '2025-07-23', 'afternoon', 'làm', NULL, '2025-08-07 04:36:31', '2025-08-07 04:36:31'),
(13, '2025-07-23', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:36:31', '2025-08-07 04:36:31'),
(13, '2025-07-24', 'morning', 'làm', NULL, '2025-08-07 04:36:34', '2025-08-07 04:36:34'),
(13, '2025-07-24', 'afternoon', 'làm', NULL, '2025-08-07 04:36:34', '2025-08-07 04:36:34'),
(13, '2025-07-24', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:36:34', '2025-08-07 04:36:34'),
(13, '2025-07-25', 'morning', 'làm', NULL, '2025-08-07 04:36:37', '2025-08-07 04:36:37'),
(13, '2025-07-25', 'afternoon', 'làm', NULL, '2025-08-07 04:36:37', '2025-08-07 04:36:37'),
(13, '2025-07-25', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:36:37', '2025-08-07 04:36:37'),
(13, '2025-07-26', 'morning', 'làm', NULL, '2025-08-07 04:37:15', '2025-08-07 04:37:15'),
(13, '2025-07-26', 'afternoon', 'làm', NULL, '2025-08-07 04:37:15', '2025-08-07 04:37:15'),
(13, '2025-07-27', 'morning', 'làm', NULL, '2025-08-07 04:37:26', '2025-08-07 04:37:26'),
(13, '2025-07-27', 'afternoon', 'làm', NULL, '2025-08-07 04:37:26', '2025-08-07 04:37:26'),
(13, '2025-07-28', 'morning', 'làm', NULL, '2025-08-07 04:36:45', '2025-08-07 04:36:45'),
(13, '2025-07-28', 'afternoon', 'làm', NULL, '2025-08-07 04:36:45', '2025-08-07 04:36:45'),
(13, '2025-07-28', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:36:45', '2025-08-07 04:36:45'),
(13, '2025-07-29', 'morning', 'làm', NULL, '2025-08-07 04:36:48', '2025-08-07 04:36:48'),
(13, '2025-07-29', 'afternoon', 'làm', NULL, '2025-08-07 04:36:48', '2025-08-07 04:36:48'),
(13, '2025-07-29', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:36:48', '2025-08-07 04:36:48'),
(13, '2025-07-30', 'morning', 'làm', NULL, '2025-08-07 04:35:57', '2025-08-07 04:35:57'),
(13, '2025-07-30', 'afternoon', 'làm', NULL, '2025-08-07 04:35:57', '2025-08-07 04:35:57'),
(13, '2025-07-31', 'morning', 'làm', NULL, '2025-08-07 04:36:50', '2025-08-07 04:36:50'),
(13, '2025-07-31', 'afternoon', 'làm', NULL, '2025-08-07 04:36:50', '2025-08-07 04:36:50'),
(13, '2025-07-31', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:36:50', '2025-08-07 04:36:50'),
(14, '2025-07-03', 'morning', 'làm', NULL, '2025-08-07 04:38:07', '2025-08-07 04:38:07'),
(14, '2025-07-03', 'afternoon', 'làm', NULL, '2025-08-07 04:38:07', '2025-08-07 04:38:07'),
(14, '2025-07-04', 'morning', 'làm', NULL, '2025-08-07 04:38:10', '2025-08-07 04:38:10'),
(14, '2025-07-04', 'afternoon', 'làm', NULL, '2025-08-07 04:38:10', '2025-08-07 04:38:10'),
(14, '2025-07-05', 'morning', 'làm', NULL, '2025-08-07 04:40:26', '2025-08-07 04:40:26'),
(14, '2025-07-05', 'afternoon', 'làm', NULL, '2025-08-07 04:40:26', '2025-08-07 04:40:26'),
(14, '2025-07-07', 'morning', 'làm', NULL, '2025-08-07 04:39:29', '2025-08-07 04:39:29'),
(14, '2025-07-07', 'afternoon', 'làm', NULL, '2025-08-07 04:39:29', '2025-08-07 04:39:29'),
(14, '2025-07-07', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:39:29', '2025-08-07 04:39:29'),
(14, '2025-07-08', 'morning', 'làm', NULL, '2025-08-07 04:38:30', '2025-08-07 04:38:30'),
(14, '2025-07-08', 'afternoon', 'làm', NULL, '2025-08-07 04:38:30', '2025-08-07 04:38:30'),
(14, '2025-07-09', 'morning', 'làm', NULL, '2025-08-07 04:39:32', '2025-08-07 04:39:32'),
(14, '2025-07-09', 'afternoon', 'làm', NULL, '2025-08-07 04:39:32', '2025-08-07 04:39:32'),
(14, '2025-07-09', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:39:32', '2025-08-07 04:39:32'),
(14, '2025-07-10', 'morning', 'làm', NULL, '2025-08-07 04:38:35', '2025-08-07 04:38:35'),
(14, '2025-07-10', 'afternoon', 'làm', NULL, '2025-08-07 04:38:35', '2025-08-07 04:38:35'),
(14, '2025-07-11', 'morning', 'làm', NULL, '2025-08-07 04:38:37', '2025-08-07 04:38:37'),
(14, '2025-07-11', 'afternoon', 'làm', NULL, '2025-08-07 04:38:37', '2025-08-07 04:38:37'),
(14, '2025-07-12', 'morning', 'làm', NULL, '2025-08-07 04:38:21', '2025-08-07 04:38:21'),
(14, '2025-07-14', 'morning', 'làm', NULL, '2025-08-07 04:39:38', '2025-08-07 04:39:38'),
(14, '2025-07-14', 'afternoon', 'làm', NULL, '2025-08-07 04:39:38', '2025-08-07 04:39:38'),
(14, '2025-07-14', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:39:38', '2025-08-07 04:39:38'),
(14, '2025-07-15', 'morning', 'làm', NULL, '2025-08-07 04:39:40', '2025-08-07 04:39:40'),
(14, '2025-07-15', 'afternoon', 'làm', NULL, '2025-08-07 04:39:40', '2025-08-07 04:39:40'),
(14, '2025-07-15', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:39:40', '2025-08-07 04:39:40'),
(14, '2025-07-16', 'morning', 'làm', NULL, '2025-08-07 04:39:42', '2025-08-07 04:39:42'),
(14, '2025-07-16', 'afternoon', 'làm', NULL, '2025-08-07 04:39:42', '2025-08-07 04:39:42'),
(14, '2025-07-16', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:39:42', '2025-08-07 04:39:42'),
(14, '2025-07-17', 'morning', 'làm', NULL, '2025-08-07 04:39:43', '2025-08-07 04:39:43'),
(14, '2025-07-17', 'afternoon', 'làm', NULL, '2025-08-07 04:39:43', '2025-08-07 04:39:43'),
(14, '2025-07-17', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:39:43', '2025-08-07 04:39:43'),
(14, '2025-07-18', 'morning', 'làm', NULL, '2025-08-07 04:39:45', '2025-08-07 04:39:45'),
(14, '2025-07-18', 'afternoon', 'làm', NULL, '2025-08-07 04:39:45', '2025-08-07 04:39:45'),
(14, '2025-07-18', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:39:45', '2025-08-07 04:39:45'),
(14, '2025-07-19', 'morning', 'làm', NULL, '2025-08-07 04:40:35', '2025-08-07 04:40:35'),
(14, '2025-07-19', 'afternoon', 'làm', NULL, '2025-08-07 04:40:35', '2025-08-07 04:40:35'),
(14, '2025-07-21', 'morning', 'làm', NULL, '2025-08-07 04:39:51', '2025-08-07 04:39:51'),
(14, '2025-07-21', 'afternoon', 'làm', NULL, '2025-08-07 04:39:51', '2025-08-07 04:39:51'),
(14, '2025-07-21', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:39:51', '2025-08-07 04:39:51'),
(14, '2025-07-22', 'morning', 'làm', NULL, '2025-08-07 04:39:52', '2025-08-07 04:39:52'),
(14, '2025-07-22', 'afternoon', 'làm', NULL, '2025-08-07 04:39:52', '2025-08-07 04:39:52'),
(14, '2025-07-22', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:39:52', '2025-08-07 04:39:52'),
(14, '2025-07-23', 'morning', 'làm', NULL, '2025-08-07 04:39:54', '2025-08-07 04:39:54'),
(14, '2025-07-23', 'afternoon', 'làm', NULL, '2025-08-07 04:39:54', '2025-08-07 04:39:54'),
(14, '2025-07-23', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:39:54', '2025-08-07 04:39:54'),
(14, '2025-07-24', 'morning', 'làm', NULL, '2025-08-07 04:39:58', '2025-08-07 04:39:58'),
(14, '2025-07-24', 'afternoon', 'làm', NULL, '2025-08-07 04:39:58', '2025-08-07 04:39:58'),
(14, '2025-07-24', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:39:58', '2025-08-07 04:39:58'),
(14, '2025-07-25', 'morning', 'làm', NULL, '2025-08-07 04:40:00', '2025-08-07 04:40:00'),
(14, '2025-07-25', 'afternoon', 'làm', NULL, '2025-08-07 04:40:00', '2025-08-07 04:40:00'),
(14, '2025-07-25', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:40:00', '2025-08-07 04:40:00'),
(14, '2025-07-26', 'morning', 'làm', NULL, '2025-08-07 04:40:39', '2025-08-07 04:40:39'),
(14, '2025-07-26', 'afternoon', 'làm', NULL, '2025-08-07 04:40:39', '2025-08-07 04:40:39'),
(14, '2025-07-27', 'morning', 'làm', NULL, '2025-08-07 04:40:43', '2025-08-07 04:40:43'),
(14, '2025-07-27', 'afternoon', 'làm', NULL, '2025-08-07 04:40:43', '2025-08-07 04:40:43'),
(14, '2025-07-28', 'morning', 'làm', NULL, '2025-08-07 04:40:08', '2025-08-07 04:40:08'),
(14, '2025-07-28', 'afternoon', 'làm', NULL, '2025-08-07 04:40:08', '2025-08-07 04:40:08'),
(14, '2025-07-28', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:40:08', '2025-08-07 04:40:08'),
(14, '2025-07-29', 'morning', 'làm', NULL, '2025-08-07 04:39:08', '2025-08-07 04:39:08'),
(14, '2025-07-29', 'afternoon', 'làm', NULL, '2025-08-07 04:39:08', '2025-08-07 04:39:08'),
(14, '2025-07-30', 'morning', 'làm', NULL, '2025-08-07 04:40:10', '2025-08-07 04:40:10'),
(14, '2025-07-30', 'afternoon', 'làm', NULL, '2025-08-07 04:40:10', '2025-08-07 04:40:10'),
(14, '2025-07-30', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:40:10', '2025-08-07 04:40:10'),
(14, '2025-07-31', 'morning', 'làm', NULL, '2025-08-07 04:40:12', '2025-08-07 04:40:12'),
(14, '2025-07-31', 'afternoon', 'làm', NULL, '2025-08-07 04:40:12', '2025-08-07 04:40:12'),
(14, '2025-07-31', 'evening', '17:00-19:30: làm', NULL, '2025-08-07 04:40:12', '2025-08-07 04:40:12'),
(15, '2025-07-01', 'morning', 'Bình đồ thoát nước - ĐT 330', NULL, '2025-08-07 04:10:32', '2025-08-07 04:10:32'),
(15, '2025-07-01', 'afternoon', 'Bình đồ thoát nước - ĐT 330', NULL, '2025-08-07 04:10:32', '2025-08-07 04:10:32'),
(15, '2025-07-01', 'evening', 'Nghỉ', NULL, '2025-08-07 04:10:32', '2025-08-07 04:10:32'),
(15, '2025-07-02', 'morning', 'Bình đồ thoát nước - Chợ HL', NULL, '2025-08-07 04:11:00', '2025-08-07 04:11:00'),
(15, '2025-07-02', 'afternoon', 'Bình đồ thoát nước - Chợ HL', NULL, '2025-08-07 04:11:00', '2025-08-07 04:11:00'),
(15, '2025-07-02', 'evening', 'Nghỉ', NULL, '2025-08-07 04:11:00', '2025-08-07 04:11:00'),
(15, '2025-07-03', 'morning', 'Bình đồ thoát nước - Chợ HL', NULL, '2025-08-07 04:11:08', '2025-08-07 04:11:08'),
(15, '2025-07-03', 'afternoon', 'Bình đồ thoát nước - Chợ HL', NULL, '2025-08-07 04:11:08', '2025-08-07 04:11:08'),
(15, '2025-07-03', 'evening', 'Nghỉ', NULL, '2025-08-07 04:11:08', '2025-08-07 04:11:08'),
(15, '2025-07-04', 'morning', 'Bình đồ thoát nước - Chợ HL', NULL, '2025-08-07 04:11:13', '2025-08-07 04:11:13'),
(15, '2025-07-04', 'afternoon', 'Bình đồ thoát nước - Chợ HL', NULL, '2025-08-07 04:11:13', '2025-08-07 04:11:13'),
(15, '2025-07-04', 'evening', 'Nghỉ', NULL, '2025-08-07 04:11:13', '2025-08-07 04:11:13'),
(15, '2025-07-05', 'morning', 'Bình đồ thoát nước - Chợ HL', NULL, '2025-08-07 04:11:19', '2025-08-07 04:11:19'),
(15, '2025-07-05', 'afternoon', 'Bình đồ thoát nước - Chợ HL', NULL, '2025-08-07 04:11:19', '2025-08-07 04:11:19'),
(15, '2025-07-05', 'evening', 'Nghỉ', NULL, '2025-08-07 04:11:19', '2025-08-07 04:11:19'),
(15, '2025-07-06', 'morning', 'Nghỉ', NULL, '2025-08-07 04:11:24', '2025-08-07 04:11:24'),
(15, '2025-07-06', 'afternoon', 'Nghỉ', NULL, '2025-08-07 04:11:24', '2025-08-07 04:11:24'),
(15, '2025-07-06', 'evening', 'Nghỉ', NULL, '2025-08-07 04:11:24', '2025-08-07 04:11:24'),
(15, '2025-07-07', 'morning', 'BVĐH thoát nước - CHL', NULL, '2025-08-07 04:12:06', '2025-08-07 04:12:06'),
(15, '2025-07-07', 'afternoon', 'BVĐH thoát nước - CHL', NULL, '2025-08-07 04:12:06', '2025-08-07 04:12:06'),
(15, '2025-07-07', 'evening', 'Nghỉ', NULL, '2025-08-07 04:12:06', '2025-08-07 04:12:06'),
(15, '2025-07-08', 'morning', 'BVĐH thoát nước - CHL', NULL, '2025-08-07 04:12:10', '2025-08-07 04:12:10'),
(15, '2025-07-08', 'afternoon', 'BVĐH thoát nước - CHL', NULL, '2025-08-07 04:12:10', '2025-08-07 04:12:10'),
(15, '2025-07-08', 'evening', 'Nghỉ', NULL, '2025-08-07 04:12:10', '2025-08-07 04:12:10'),
(15, '2025-07-09', 'morning', 'BĐ thoát nước - CHL', NULL, '2025-08-07 04:12:30', '2025-08-07 04:12:30'),
(15, '2025-07-09', 'afternoon', 'BĐ thoát nước - CHL', NULL, '2025-08-07 04:12:30', '2025-08-07 04:12:30'),
(15, '2025-07-09', 'evening', 'Nghỉ', NULL, '2025-08-07 04:12:30', '2025-08-07 04:12:30'),
(15, '2025-07-10', 'morning', 'BĐ thoát nước - CHL', NULL, '2025-08-07 04:12:38', '2025-08-07 04:12:38'),
(15, '2025-07-10', 'afternoon', 'BĐ thoát nước - CHL', NULL, '2025-08-07 04:12:38', '2025-08-07 04:12:38'),
(15, '2025-07-10', 'evening', 'Nghỉ', NULL, '2025-08-07 04:12:38', '2025-08-07 04:12:38'),
(15, '2025-07-11', 'morning', 'BĐ thoát nước - CHL', NULL, '2025-08-07 04:12:45', '2025-08-07 04:12:45'),
(15, '2025-07-11', 'afternoon', 'BĐ thoát nước - CHL', NULL, '2025-08-07 04:12:45', '2025-08-07 04:12:45'),
(15, '2025-07-11', 'evening', 'Nghỉ', NULL, '2025-08-07 04:12:45', '2025-08-07 04:12:45'),
(15, '2025-07-12', 'morning', 'BĐ thoát nước - CHL', NULL, '2025-08-07 04:12:55', '2025-08-07 04:12:55'),
(15, '2025-07-12', 'afternoon', 'Nghỉ', NULL, '2025-08-07 04:12:55', '2025-08-07 04:12:55'),
(15, '2025-07-12', 'evening', 'Nghỉ', NULL, '2025-08-07 04:12:55', '2025-08-07 04:12:55'),
(15, '2025-07-13', 'morning', 'Nghỉ', NULL, '2025-08-07 04:13:00', '2025-08-07 04:13:00'),
(15, '2025-07-13', 'afternoon', 'Nghỉ', NULL, '2025-08-07 04:13:00', '2025-08-07 04:13:00'),
(15, '2025-07-13', 'evening', 'Nghỉ', NULL, '2025-08-07 04:13:00', '2025-08-07 04:13:00'),
(15, '2025-07-14', 'morning', 'Cống D100 - Chợ HL', NULL, '2025-08-07 04:13:23', '2025-08-07 04:13:23'),
(15, '2025-07-14', 'afternoon', 'Cống D100 - Chợ HL', NULL, '2025-08-07 04:13:23', '2025-08-07 04:13:23'),
(15, '2025-07-14', 'evening', '17:00-19:30: Cống D100 - Chợ HL', NULL, '2025-08-07 04:13:23', '2025-08-07 04:13:23'),
(15, '2025-07-15', 'morning', 'CHL 3x2.5m - Chợ HL', NULL, '2025-08-07 04:13:45', '2025-08-07 04:13:45'),
(15, '2025-07-15', 'afternoon', 'CHL 3x2.5m - Chợ HL', NULL, '2025-08-07 04:13:45', '2025-08-07 04:13:45'),
(15, '2025-07-15', 'evening', '17:00-19:30: CHL 3x2.5m - Chợ HL', NULL, '2025-08-07 04:13:45', '2025-08-07 04:13:45'),
(15, '2025-07-16', 'morning', 'BĐ thoát nước - Chợ HL', NULL, '2025-08-07 04:14:03', '2025-08-07 04:14:03'),
(15, '2025-07-16', 'afternoon', 'BĐ thoát nước - Chợ HL', NULL, '2025-08-07 04:14:03', '2025-08-07 04:14:03'),
(15, '2025-07-16', 'evening', 'Nghỉ', NULL, '2025-08-07 04:14:03', '2025-08-07 04:14:03'),
(15, '2025-07-17', 'morning', 'TDTN - chợ HL', NULL, '2025-08-07 04:14:18', '2025-08-07 04:14:18'),
(15, '2025-07-17', 'afternoon', 'TDTN - chợ HL', NULL, '2025-08-07 04:14:18', '2025-08-07 04:14:18'),
(15, '2025-07-17', 'evening', 'Nghỉ', NULL, '2025-08-07 04:14:18', '2025-08-07 04:14:18'),
(15, '2025-07-18', 'morning', 'TDTN - chợ HL', NULL, '2025-08-07 04:14:27', '2025-08-07 04:14:27'),
(15, '2025-07-18', 'afternoon', 'TDTN - chợ HL', NULL, '2025-08-07 04:14:27', '2025-08-07 04:14:27'),
(15, '2025-07-18', 'evening', 'Nghỉ', NULL, '2025-08-07 04:14:27', '2025-08-07 04:14:27'),
(15, '2025-07-19', 'morning', 'TDTN - chợ HL', NULL, '2025-08-07 04:14:33', '2025-08-07 04:14:33'),
(15, '2025-07-19', 'afternoon', 'TDTN - chợ HL', NULL, '2025-08-07 04:14:33', '2025-08-07 04:14:33'),
(15, '2025-07-19', 'evening', 'Nghỉ', NULL, '2025-08-07 04:14:33', '2025-08-07 04:14:33'),
(15, '2025-07-20', 'morning', 'GPMB 279', NULL, '2025-08-07 04:14:42', '2025-08-07 04:14:42'),
(15, '2025-07-20', 'afternoon', 'GPMB 279', NULL, '2025-08-07 04:14:42', '2025-08-07 04:14:42'),
(15, '2025-07-20', 'evening', 'Nghỉ', NULL, '2025-08-07 04:14:42', '2025-08-07 04:14:42'),
(15, '2025-07-21', 'morning', 'GPMB 279', NULL, '2025-08-07 04:14:54', '2025-08-07 04:14:54'),
(15, '2025-07-21', 'afternoon', 'GPMB 279', NULL, '2025-08-07 04:14:54', '2025-08-07 04:14:54'),
(15, '2025-07-21', 'evening', 'Nghỉ', NULL, '2025-08-07 04:14:54', '2025-08-07 04:14:54'),
(15, '2025-07-22', 'morning', 'GPMB 279', NULL, '2025-08-07 04:14:58', '2025-08-07 04:14:58'),
(15, '2025-07-22', 'afternoon', 'GPMB 279', NULL, '2025-08-07 04:14:58', '2025-08-07 04:14:58'),
(15, '2025-07-22', 'evening', '17:00-19:30: GPMB 279', NULL, '2025-08-07 04:14:58', '2025-08-07 04:14:58'),
(15, '2025-07-23', 'morning', 'In BV màu', NULL, '2025-08-07 04:15:10', '2025-08-07 04:15:10'),
(15, '2025-07-23', 'afternoon', 'In BV màu', NULL, '2025-08-07 04:15:10', '2025-08-07 04:15:10'),
(15, '2025-07-23', 'evening', 'Nghỉ', NULL, '2025-08-07 04:15:10', '2025-08-07 04:15:10'),
(15, '2025-07-24', 'morning', 'BĐ tuyến - ĐT 342', NULL, '2025-08-07 04:15:27', '2025-08-07 04:15:27'),
(15, '2025-07-24', 'afternoon', 'BĐ tuyến - ĐT 342', NULL, '2025-08-07 04:15:27', '2025-08-07 04:15:27'),
(15, '2025-07-24', 'evening', 'Nghỉ', NULL, '2025-08-07 04:15:27', '2025-08-07 04:15:27'),
(15, '2025-07-25', 'morning', 'BĐ tuyến - ĐT 342', NULL, '2025-08-07 04:15:31', '2025-08-07 04:15:31'),
(15, '2025-07-25', 'afternoon', 'BĐ tuyến - ĐT 342', NULL, '2025-08-07 04:15:31', '2025-08-07 04:15:31'),
(15, '2025-07-25', 'evening', 'Nghỉ', NULL, '2025-08-07 04:15:31', '2025-08-07 04:15:31'),
(15, '2025-07-26', 'morning', 'BĐ tuyến - ĐT 342', NULL, '2025-08-07 04:15:38', '2025-08-07 04:15:38'),
(15, '2025-07-26', 'afternoon', 'BĐ tuyến - ĐT 342', NULL, '2025-08-07 04:15:38', '2025-08-07 04:15:38'),
(15, '2025-07-26', 'evening', 'Nghỉ', NULL, '2025-08-07 04:15:38', '2025-08-07 04:15:38'),
(15, '2025-07-27', 'morning', 'Nghỉ', NULL, '2025-08-07 04:15:42', '2025-08-07 04:15:42'),
(15, '2025-07-27', 'afternoon', 'Nghỉ', NULL, '2025-08-07 04:15:42', '2025-08-07 04:15:42'),
(15, '2025-07-27', 'evening', 'Nghỉ', NULL, '2025-08-07 04:15:42', '2025-08-07 04:15:42'),
(15, '2025-07-28', 'morning', 'BĐ tuyến - QL 18B', NULL, '2025-08-07 04:16:11', '2025-08-07 04:16:11'),
(15, '2025-07-28', 'afternoon', 'BĐ tuyến - QL 18B', NULL, '2025-08-07 04:16:11', '2025-08-07 04:16:11'),
(15, '2025-07-28', 'evening', '17:00-19:30: GPMB - QL 18B', NULL, '2025-08-07 04:16:11', '2025-08-07 04:16:11'),
(15, '2025-07-29', 'morning', 'In BV màu ĐT 342', NULL, '2025-08-07 04:16:22', '2025-08-07 04:16:22'),
(15, '2025-07-29', 'afternoon', 'In BV màu ĐT 342', NULL, '2025-08-07 04:16:22', '2025-08-07 04:16:22'),
(15, '2025-07-29', 'evening', 'Nghỉ', NULL, '2025-08-07 04:16:22', '2025-08-07 04:16:22'),
(15, '2025-07-30', 'morning', 'GPMB - QL 18B', NULL, '2025-08-07 04:16:31', '2025-08-07 04:16:31'),
(15, '2025-07-30', 'afternoon', 'GPMB - QL 18B', NULL, '2025-08-07 04:16:32', '2025-08-07 04:16:32'),
(15, '2025-07-30', 'evening', '17:00-19:30: GPMB - QL 18B', NULL, '2025-08-07 04:16:32', '2025-08-07 04:16:32'),
(15, '2025-07-31', 'morning', 'DCBS thoát nước, vỉa hè', NULL, '2025-08-07 04:16:58', '2025-08-07 04:16:58'),
(15, '2025-07-31', 'afternoon', 'DCBS thoát nước, vỉa hè', NULL, '2025-08-07 04:16:58', '2025-08-07 04:16:58'),
(15, '2025-07-31', 'evening', 'Nghỉ', NULL, '2025-08-07 04:16:58', '2025-08-07 04:16:58'),
(16, '2025-07-01', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:32:14', '2025-08-07 09:32:14'),
(16, '2025-07-01', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:32:14', '2025-08-07 09:32:14'),
(16, '2025-07-01', 'evening', 'Nghỉ', NULL, '2025-08-07 09:32:14', '2025-08-07 09:32:14'),
(16, '2025-07-02', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:32:18', '2025-08-07 09:32:18'),
(16, '2025-07-02', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:32:18', '2025-08-07 09:32:18'),
(16, '2025-07-02', 'evening', 'Nghỉ', NULL, '2025-08-07 09:32:18', '2025-08-07 09:32:18'),
(16, '2025-07-03', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:32:21', '2025-08-07 09:32:21'),
(16, '2025-07-03', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:32:21', '2025-08-07 09:32:21'),
(16, '2025-07-03', 'evening', 'Nghỉ', NULL, '2025-08-07 09:32:21', '2025-08-07 09:32:21'),
(16, '2025-07-04', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:32:23', '2025-08-07 09:32:23'),
(16, '2025-07-04', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:32:23', '2025-08-07 09:32:23'),
(16, '2025-07-04', 'evening', 'Nghỉ', NULL, '2025-08-07 09:32:23', '2025-08-07 09:32:23'),
(16, '2025-07-05', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:32:26', '2025-08-07 09:32:26'),
(16, '2025-07-05', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:32:26', '2025-08-07 09:32:26'),
(16, '2025-07-05', 'evening', 'Nghỉ', NULL, '2025-08-07 09:32:26', '2025-08-07 09:32:26'),
(16, '2025-07-06', 'morning', 'Nghỉ', NULL, '2025-08-07 09:32:11', '2025-08-07 09:32:11'),
(16, '2025-07-06', 'afternoon', 'Nghỉ', NULL, '2025-08-07 09:32:11', '2025-08-07 09:32:11'),
(16, '2025-07-06', 'evening', 'Nghỉ', NULL, '2025-08-07 09:32:11', '2025-08-07 09:32:11'),
(16, '2025-07-07', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:07', '2025-08-07 09:33:07'),
(16, '2025-07-07', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:07', '2025-08-07 09:33:07'),
(16, '2025-07-07', 'evening', 'Nghỉ', NULL, '2025-08-07 09:33:07', '2025-08-07 09:33:07'),
(16, '2025-07-08', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:05', '2025-08-07 09:33:05'),
(16, '2025-07-08', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:05', '2025-08-07 09:33:05'),
(16, '2025-07-08', 'evening', 'Nghỉ', NULL, '2025-08-07 09:33:05', '2025-08-07 09:33:05'),
(16, '2025-07-09', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:02', '2025-08-07 09:33:02'),
(16, '2025-07-09', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:02', '2025-08-07 09:33:02'),
(16, '2025-07-09', 'evening', 'Nghỉ', NULL, '2025-08-07 09:33:02', '2025-08-07 09:33:02'),
(16, '2025-07-10', 'morning', 'Nghỉ', NULL, '2025-08-07 09:32:59', '2025-08-07 09:32:59'),
(16, '2025-07-10', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:32:59', '2025-08-07 09:32:59'),
(16, '2025-07-10', 'evening', 'Nghỉ', NULL, '2025-08-07 09:32:59', '2025-08-07 09:32:59'),
(16, '2025-07-11', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:16', '2025-08-07 09:33:16'),
(16, '2025-07-11', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:16', '2025-08-07 09:33:16'),
(16, '2025-07-11', 'evening', 'Nghỉ', NULL, '2025-08-07 09:33:16', '2025-08-07 09:33:16'),
(16, '2025-07-12', 'morning', 'Nghỉ', NULL, '2025-08-07 09:33:21', '2025-08-07 09:33:21'),
(16, '2025-07-12', 'afternoon', 'Nghỉ', NULL, '2025-08-07 09:33:21', '2025-08-07 09:33:21'),
(16, '2025-07-12', 'evening', 'Nghỉ', NULL, '2025-08-07 09:33:21', '2025-08-07 09:33:21'),
(16, '2025-07-13', 'morning', 'Nghỉ', NULL, '2025-08-07 09:33:26', '2025-08-07 09:33:26'),
(16, '2025-07-13', 'afternoon', 'Nghỉ', NULL, '2025-08-07 09:33:26', '2025-08-07 09:33:26'),
(16, '2025-07-13', 'evening', 'Nghỉ', NULL, '2025-08-07 09:33:26', '2025-08-07 09:33:26'),
(16, '2025-07-14', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:48', '2025-08-07 09:33:48'),
(16, '2025-07-14', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:48', '2025-08-07 09:33:48'),
(16, '2025-07-14', 'evening', 'Nghỉ', NULL, '2025-08-07 09:33:48', '2025-08-07 09:33:48'),
(16, '2025-07-15', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:57', '2025-08-07 09:33:57'),
(16, '2025-07-15', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:57', '2025-08-07 09:33:57'),
(16, '2025-07-15', 'evening', 'Nghỉ', NULL, '2025-08-07 09:33:57', '2025-08-07 09:33:57'),
(16, '2025-07-16', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:03', '2025-08-07 09:34:03'),
(16, '2025-07-16', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:03', '2025-08-07 09:34:03'),
(16, '2025-07-16', 'evening', 'Nghỉ', NULL, '2025-08-07 09:34:03', '2025-08-07 09:34:03'),
(16, '2025-07-17', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:06', '2025-08-07 09:34:06'),
(16, '2025-07-17', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:06', '2025-08-07 09:34:06'),
(16, '2025-07-17', 'evening', 'Nghỉ', NULL, '2025-08-07 09:34:06', '2025-08-07 09:34:06'),
(16, '2025-07-18', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:10', '2025-08-07 09:34:10'),
(16, '2025-07-18', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:10', '2025-08-07 09:34:10'),
(16, '2025-07-18', 'evening', 'Nghỉ', NULL, '2025-08-07 09:34:10', '2025-08-07 09:34:10'),
(16, '2025-07-19', 'morning', 'Nghỉ', NULL, '2025-08-07 09:34:15', '2025-08-07 09:34:15'),
(16, '2025-07-19', 'afternoon', 'Nghỉ', NULL, '2025-08-07 09:34:15', '2025-08-07 09:34:15'),
(16, '2025-07-19', 'evening', 'Nghỉ', NULL, '2025-08-07 09:34:15', '2025-08-07 09:34:15'),
(16, '2025-07-20', 'morning', 'Nghỉ', NULL, '2025-08-07 09:34:19', '2025-08-07 09:34:19'),
(16, '2025-07-20', 'afternoon', 'Nghỉ', NULL, '2025-08-07 09:34:19', '2025-08-07 09:34:19'),
(16, '2025-07-20', 'evening', 'Nghỉ', NULL, '2025-08-07 09:34:19', '2025-08-07 09:34:19'),
(16, '2025-07-21', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:33', '2025-08-07 09:34:33'),
(16, '2025-07-21', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:33', '2025-08-07 09:34:33'),
(16, '2025-07-21', 'evening', 'Nghỉ', NULL, '2025-08-07 09:34:33', '2025-08-07 09:34:33'),
(16, '2025-07-22', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:37', '2025-08-07 09:34:37'),
(16, '2025-07-22', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:37', '2025-08-07 09:34:37'),
(16, '2025-07-22', 'evening', 'Nghỉ', NULL, '2025-08-07 09:34:37', '2025-08-07 09:34:37'),
(16, '2025-07-23', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:46', '2025-08-07 09:34:46'),
(16, '2025-07-23', 'afternoon', 'Nghỉ', NULL, '2025-08-07 09:34:46', '2025-08-07 09:34:46'),
(16, '2025-07-23', 'evening', 'Nghỉ', NULL, '2025-08-07 09:34:46', '2025-08-07 09:34:46'),
(16, '2025-07-24', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:52', '2025-08-07 09:34:52'),
(16, '2025-07-24', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:52', '2025-08-07 09:34:52'),
(16, '2025-07-24', 'evening', '17:00-19:30: Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:34:52', '2025-08-07 09:34:52'),
(16, '2025-07-25', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:35:07', '2025-08-07 09:35:07'),
(16, '2025-07-25', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:35:07', '2025-08-07 09:35:07'),
(16, '2025-07-25', 'evening', 'Nghỉ', NULL, '2025-08-07 09:35:07', '2025-08-07 09:35:07'),
(16, '2025-07-26', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:35:13', '2025-08-07 09:35:13'),
(16, '2025-07-26', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:35:13', '2025-08-07 09:35:13'),
(16, '2025-07-26', 'evening', 'Nghỉ', NULL, '2025-08-07 09:35:13', '2025-08-07 09:35:13'),
(16, '2025-07-27', 'morning', 'Nghỉ', NULL, '2025-08-07 09:35:18', '2025-08-07 09:35:18'),
(16, '2025-07-27', 'afternoon', 'Nghỉ', NULL, '2025-08-07 09:35:18', '2025-08-07 09:35:18'),
(16, '2025-07-27', 'evening', 'Nghỉ', NULL, '2025-08-07 09:35:18', '2025-08-07 09:35:18'),
(16, '2025-07-28', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:35:32', '2025-08-07 09:35:32'),
(16, '2025-07-28', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:35:32', '2025-08-07 09:35:32'),
(16, '2025-07-28', 'evening', 'Nghỉ', NULL, '2025-08-07 09:35:32', '2025-08-07 09:35:32'),
(16, '2025-07-29', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:35:44', '2025-08-07 09:35:44'),
(16, '2025-07-29', 'afternoon', 'Đi hiện trường', NULL, '2025-08-07 09:35:44', '2025-08-07 09:35:44');
INSERT INTO `work_diary_entries` (`user_id`, `entry_date`, `period`, `content`, `note`, `created_at`, `updated_at`) VALUES
(16, '2025-07-29', 'evening', 'Nghỉ', NULL, '2025-08-07 09:35:44', '2025-08-07 09:35:44'),
(16, '2025-07-30', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:35:58', '2025-08-07 09:35:58'),
(16, '2025-07-30', 'afternoon', 'Đi thực địa', NULL, '2025-08-07 09:35:58', '2025-08-07 09:35:58'),
(16, '2025-07-30', 'evening', 'Nghỉ', NULL, '2025-08-07 09:35:58', '2025-08-07 09:35:58'),
(16, '2025-07-31', 'morning', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:36:03', '2025-08-07 09:36:03'),
(16, '2025-07-31', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:36:03', '2025-08-07 09:36:03'),
(16, '2025-07-31', 'evening', 'Nghỉ', NULL, '2025-08-07 09:36:03', '2025-08-07 09:36:03'),
(17, '2025-07-01', 'morning', 'Vỉa hè cây xanh, ATGT Chợ HL1', NULL, '2025-08-07 10:47:33', '2025-08-07 10:47:33'),
(17, '2025-07-01', 'afternoon', 'Vỉa hè cây xanh, ATGT Chợ HL1', NULL, '2025-08-07 10:47:33', '2025-08-07 10:47:33'),
(17, '2025-07-01', 'evening', '17:00-19:30: Vỉa hè cây xanh, ATGT Chợ HL1', NULL, '2025-08-07 10:47:33', '2025-08-07 10:47:33'),
(17, '2025-07-02', 'morning', 'Vỉa hè cây xanh, ATGT Chợ HL1', NULL, '2025-08-07 10:47:36', '2025-08-07 10:47:36'),
(17, '2025-07-02', 'afternoon', 'Vỉa hè cây xanh, ATGT Chợ HL1', NULL, '2025-08-07 10:47:36', '2025-08-07 10:47:36'),
(17, '2025-07-02', 'evening', '17:00-19:30: Vỉa hè cây xanh, ATGT Chợ HL1', NULL, '2025-08-07 10:47:36', '2025-08-07 10:47:36'),
(17, '2025-07-03', 'morning', 'Bình đồ tổng thể, bình đồ tuyến Chợ HL1', NULL, '2025-08-07 10:48:15', '2025-08-07 10:48:15'),
(17, '2025-07-03', 'afternoon', 'Bình đồ vị trí, bình đồ vị trí trong quy hoạch phân khu - Chợ HL1', NULL, '2025-08-07 10:48:15', '2025-08-07 10:48:15'),
(17, '2025-07-03', 'evening', '17:00-19:30: Bình đồ vị trí, bình đồ vị trí trong quy hoạch phân khu - Chợ HL1', NULL, '2025-08-07 10:48:15', '2025-08-07 10:48:15'),
(17, '2025-07-04', 'morning', 'Bình đồ vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:48:46', '2025-08-07 10:48:46'),
(17, '2025-07-04', 'afternoon', 'Bình đồ vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:48:46', '2025-08-07 10:48:46'),
(17, '2025-07-04', 'evening', 'Nghỉ', NULL, '2025-08-07 10:48:46', '2025-08-07 10:48:46'),
(17, '2025-07-05', 'morning', 'Bình đồ ranh GPMB - Làng Mới', NULL, '2025-08-07 10:49:02', '2025-08-07 10:49:02'),
(17, '2025-07-05', 'afternoon', 'Bình đồ ranh GPMB - Làng Mới', NULL, '2025-08-07 10:49:02', '2025-08-07 10:49:02'),
(17, '2025-07-05', 'evening', 'Nghỉ', NULL, '2025-08-07 10:49:02', '2025-08-07 10:49:02'),
(17, '2025-07-06', 'morning', 'Nghỉ', NULL, '2025-08-07 10:49:05', '2025-08-07 10:49:05'),
(17, '2025-07-06', 'afternoon', 'Nghỉ', NULL, '2025-08-07 10:49:05', '2025-08-07 10:49:05'),
(17, '2025-07-06', 'evening', 'Nghỉ', NULL, '2025-08-07 10:49:05', '2025-08-07 10:49:05'),
(17, '2025-07-07', 'morning', 'Vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:49:32', '2025-08-07 10:49:32'),
(17, '2025-07-07', 'afternoon', 'Vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:49:32', '2025-08-07 10:49:32'),
(17, '2025-07-07', 'evening', '17:00-19:30: Vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:49:32', '2025-08-07 10:49:32'),
(17, '2025-07-08', 'morning', 'Vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:49:38', '2025-08-07 10:49:38'),
(17, '2025-07-08', 'afternoon', 'Vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:49:38', '2025-08-07 10:49:38'),
(17, '2025-07-08', 'evening', '17:00-19:30: Vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:49:38', '2025-08-07 10:49:38'),
(17, '2025-07-09', 'morning', 'Vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:49:42', '2025-08-07 10:49:42'),
(17, '2025-07-09', 'afternoon', 'Vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:49:42', '2025-08-07 10:49:42'),
(17, '2025-07-09', 'evening', '17:00-19:30: Vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:49:42', '2025-08-07 10:49:42'),
(17, '2025-07-10', 'morning', 'Bình đồ vị trí, bình đồ tuyến hồ, bình đồ tổng thể hồ Yết Kiêu', NULL, '2025-08-07 10:50:05', '2025-08-07 10:50:05'),
(17, '2025-07-10', 'afternoon', 'Bình đồ vị trí, bình đồ tuyến hồ, bình đồ tổng thể hồ Yết Kiêu', NULL, '2025-08-07 10:50:05', '2025-08-07 10:50:05'),
(17, '2025-07-10', 'evening', 'Nghỉ', NULL, '2025-08-07 10:50:05', '2025-08-07 10:50:05'),
(17, '2025-07-11', 'morning', 'Bình đồ vị trí, bình đồ tuyến hồ, bình đồ tổng thể hồ Yết Kiêu', NULL, '2025-08-07 10:50:25', '2025-08-07 10:50:25'),
(17, '2025-07-11', 'afternoon', 'DCBS - Vân Phong', NULL, '2025-08-07 10:50:25', '2025-08-07 10:50:25'),
(17, '2025-07-11', 'evening', 'Nghỉ', NULL, '2025-08-07 10:50:25', '2025-08-07 10:50:25'),
(17, '2025-07-12', 'morning', 'Vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:50:45', '2025-08-07 10:50:45'),
(17, '2025-07-12', 'afternoon', 'Vỉa hè cây xanh, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:50:45', '2025-08-07 10:50:45'),
(17, '2025-07-12', 'evening', 'Nghỉ', NULL, '2025-08-07 10:50:45', '2025-08-07 10:50:45'),
(17, '2025-07-13', 'morning', 'Nghỉ', NULL, '2025-08-07 10:50:50', '2025-08-07 10:50:50'),
(17, '2025-07-13', 'afternoon', 'Nghỉ', NULL, '2025-08-07 10:50:50', '2025-08-07 10:50:50'),
(17, '2025-07-13', 'evening', 'Nghỉ', NULL, '2025-08-07 10:50:50', '2025-08-07 10:50:50'),
(17, '2025-07-14', 'morning', 'DCBS - Vân Phong', NULL, '2025-08-07 10:51:34', '2025-08-07 10:51:34'),
(17, '2025-07-14', 'afternoon', 'Di chuyển hoàn trả ống nước GPMB - Làng Mới', NULL, '2025-08-07 10:51:34', '2025-08-07 10:51:34'),
(17, '2025-07-14', 'evening', '17:00-19:30: Di chuyển hoàn trả ống nước GPMB - Làng Mới', NULL, '2025-08-07 10:51:34', '2025-08-07 10:51:34'),
(17, '2025-07-15', 'morning', 'Di chuyển hoàn trả ống nước GPMB - Làng Mới', NULL, '2025-08-07 10:51:47', '2025-08-07 10:51:47'),
(17, '2025-07-15', 'afternoon', 'Di chuyển hoàn trả ống nước GPMB - Làng Mới', NULL, '2025-08-07 10:51:47', '2025-08-07 10:51:47'),
(17, '2025-07-15', 'evening', 'Nghỉ', NULL, '2025-08-07 10:51:47', '2025-08-07 10:51:47'),
(17, '2025-07-16', 'morning', 'Mặt bằng GPMB - Làng Mới', NULL, '2025-08-07 10:52:10', '2025-08-07 10:52:10'),
(17, '2025-07-16', 'afternoon', 'Mặt bằng GPMB - Làng Mới', NULL, '2025-08-07 10:52:10', '2025-08-07 10:52:10'),
(17, '2025-07-16', 'evening', '17:00-19:30: Mặt bằng GPMB - Làng Mới', NULL, '2025-08-07 10:52:10', '2025-08-07 10:52:10'),
(17, '2025-07-17', 'morning', 'Di chuyển hoàn trả ống nước GPMB - Làng Mới', NULL, '2025-08-07 10:52:28', '2025-08-07 10:52:28'),
(17, '2025-07-17', 'afternoon', 'Mặt bằng GPMB - Làng Mới', NULL, '2025-08-07 10:52:28', '2025-08-07 10:52:28'),
(17, '2025-07-17', 'evening', '17:00-19:30: Mặt bằng GPMB - Làng Mới', NULL, '2025-08-07 10:52:29', '2025-08-07 10:52:29'),
(17, '2025-07-18', 'morning', 'Vỉa hè, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:52:47', '2025-08-07 10:52:47'),
(17, '2025-07-18', 'afternoon', 'Vỉa hè, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:52:47', '2025-08-07 10:52:47'),
(17, '2025-07-18', 'evening', 'Nghỉ', NULL, '2025-08-07 10:52:47', '2025-08-07 10:52:47'),
(17, '2025-07-19', 'morning', 'Vỉa hè, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:53:07', '2025-08-07 10:53:07'),
(17, '2025-07-19', 'afternoon', 'Vỉa hè, ATGT hồ Yết Kiêu, Lấy ảnh gg earth QL279', NULL, '2025-08-07 10:53:07', '2025-08-07 10:53:07'),
(17, '2025-07-19', 'evening', 'Nghỉ', NULL, '2025-08-07 10:53:07', '2025-08-07 10:53:07'),
(17, '2025-07-20', 'morning', 'Ranh giới mặt bằng QL279', NULL, '2025-08-07 10:53:24', '2025-08-07 10:53:24'),
(17, '2025-07-20', 'afternoon', 'Ranh giới mặt bằng QL279', NULL, '2025-08-07 10:53:24', '2025-08-07 10:53:24'),
(17, '2025-07-20', 'evening', 'Nghỉ', NULL, '2025-08-07 10:53:24', '2025-08-07 10:53:24'),
(17, '2025-07-21', 'morning', 'Khoanh vùng, thống kê sử dụng đất QL279', NULL, '2025-08-07 10:54:06', '2025-08-07 10:54:06'),
(17, '2025-07-21', 'afternoon', 'Khoanh vùng, thống kê sử dụng đất QL279', NULL, '2025-08-07 10:54:06', '2025-08-07 10:54:06'),
(17, '2025-07-21', 'evening', '17:00-19:30: Khoanh vùng, thống kê sử dụng đất QL279', NULL, '2025-08-07 10:54:06', '2025-08-07 10:54:06'),
(17, '2025-07-22', 'morning', 'Ghép khung tim tuyến QL 279', NULL, '2025-08-07 10:54:23', '2025-08-07 10:54:23'),
(17, '2025-07-22', 'afternoon', 'Ghép khung tim tuyến QL 279', NULL, '2025-08-07 10:54:23', '2025-08-07 10:54:23'),
(17, '2025-07-22', 'evening', 'Nghỉ', NULL, '2025-08-07 10:54:23', '2025-08-07 10:54:23'),
(17, '2025-07-23', 'morning', 'Sửa Ranh giới GPMB - Làng Mới', NULL, '2025-08-07 10:55:35', '2025-08-07 10:55:35'),
(17, '2025-07-23', 'afternoon', 'Sửa Ranh giới GPMB - Làng Mới', NULL, '2025-08-07 10:55:35', '2025-08-07 10:55:35'),
(17, '2025-07-23', 'evening', '17:00-19:30: Sửa Ranh giới GPMB - Làng Mới', NULL, '2025-08-07 10:55:35', '2025-08-07 10:55:35'),
(17, '2025-07-24', 'morning', 'Xuất KMZ bình đồ giao thông - Đường 10', NULL, '2025-08-07 10:56:04', '2025-08-07 10:56:04'),
(17, '2025-07-24', 'afternoon', 'Vỉa hè, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:56:04', '2025-08-07 10:56:04'),
(17, '2025-07-24', 'evening', 'Nghỉ', NULL, '2025-08-07 10:56:04', '2025-08-07 10:56:04'),
(17, '2025-07-25', 'morning', 'Vỉa hè, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:56:19', '2025-08-07 10:56:19'),
(17, '2025-07-25', 'afternoon', 'Vỉa hè, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:56:19', '2025-08-07 10:56:19'),
(17, '2025-07-25', 'evening', 'Nghỉ', NULL, '2025-08-07 10:56:19', '2025-08-07 10:56:19'),
(17, '2025-07-26', 'morning', 'Đi học BIM', NULL, '2025-08-07 10:56:28', '2025-08-07 10:56:28'),
(17, '2025-07-26', 'afternoon', 'Đi học BIM', NULL, '2025-08-07 10:56:28', '2025-08-07 10:56:28'),
(17, '2025-07-26', 'evening', 'Nghỉ', NULL, '2025-08-07 10:56:28', '2025-08-07 10:56:28'),
(17, '2025-07-27', 'morning', 'Mặt bằng ranh giới KCN Con Ong - Hòn Nét', NULL, '2025-08-07 10:56:47', '2025-08-07 10:56:47'),
(17, '2025-07-27', 'afternoon', 'Mặt bằng ranh giới KCN Con Ong - Hòn Nét', NULL, '2025-08-07 10:56:47', '2025-08-07 10:56:47'),
(17, '2025-07-27', 'evening', 'Nghỉ', NULL, '2025-08-07 10:56:47', '2025-08-07 10:56:47'),
(17, '2025-07-28', 'morning', 'Mặt bằng ranh giới KCN Con Ong - Hòn Nét', NULL, '2025-08-07 10:57:02', '2025-08-07 10:57:02'),
(17, '2025-07-28', 'afternoon', 'Mặt bằng ranh giới KCN Con Ong - Hòn Nét', NULL, '2025-08-07 10:57:02', '2025-08-07 10:57:02'),
(17, '2025-07-28', 'evening', 'Nghỉ', NULL, '2025-08-07 10:57:02', '2025-08-07 10:57:02'),
(17, '2025-07-29', 'morning', 'Mặt bằng ranh giới KCN Con Ong - Hòn Nét', NULL, '2025-08-07 10:57:26', '2025-08-07 10:57:26'),
(17, '2025-07-29', 'afternoon', 'Vỉa hè, ATGT hồ Yết Kiêu', NULL, '2025-08-07 10:57:26', '2025-08-07 10:57:26'),
(17, '2025-07-29', 'evening', 'Nghỉ', NULL, '2025-08-07 10:57:26', '2025-08-07 10:57:26'),
(17, '2025-07-30', 'morning', 'Thống kê sử dụng đất QL18B', NULL, '2025-08-07 10:57:45', '2025-08-07 10:57:45'),
(17, '2025-07-30', 'afternoon', 'Thống kê sử dụng đất QL18B', NULL, '2025-08-07 10:57:45', '2025-08-07 10:57:45'),
(17, '2025-07-30', 'evening', 'Nghỉ', NULL, '2025-08-07 10:57:45', '2025-08-07 10:57:45'),
(17, '2025-07-31', 'morning', 'Di chuyển nước - Vân Phong', NULL, '2025-08-07 10:57:59', '2025-08-07 10:57:59'),
(17, '2025-07-31', 'afternoon', 'Di chuyển nước - Vân Phong', NULL, '2025-08-07 10:57:59', '2025-08-07 10:57:59'),
(17, '2025-07-31', 'evening', 'Nghỉ', NULL, '2025-08-07 10:57:59', '2025-08-07 10:57:59'),
(18, '2025-07-01', 'morning', 'Cắt ngang điển hình tuyến hồ Yết Kiêu', NULL, '2025-08-07 11:02:49', '2025-08-07 11:02:49'),
(18, '2025-07-01', 'afternoon', 'Cắt ngang điển hình tuyến hồ Yết Kiêu', NULL, '2025-08-07 11:02:49', '2025-08-07 11:02:49'),
(18, '2025-07-01', 'evening', 'Nghỉ', NULL, '2025-08-07 11:02:49', '2025-08-07 11:02:49'),
(18, '2025-07-02', 'morning', 'Cập nhật khối lượng các bản vẽ điển hình và cống hộp (Yết Kiêu)', NULL, '2025-08-07 11:03:15', '2025-08-07 11:03:15'),
(18, '2025-07-02', 'afternoon', 'Cập nhật khối lượng các bản vẽ điển hình và cống hộp (Yết Kiêu)', NULL, '2025-08-07 11:03:15', '2025-08-07 11:03:15'),
(18, '2025-07-02', 'evening', 'Nghỉ', NULL, '2025-08-07 11:03:15', '2025-08-07 11:03:15'),
(18, '2025-07-03', 'morning', 'Chỉnh sửa bản vẽ cống hộp 60x80 và 80x80 (Yết Kiêu)', NULL, '2025-08-07 11:03:43', '2025-08-07 11:03:43'),
(18, '2025-07-03', 'afternoon', 'Chỉnh sửa bản vẽ cống hộp 60x80 và 80x80 (Yết Kiêu)', NULL, '2025-08-07 11:03:43', '2025-08-07 11:03:43'),
(18, '2025-07-03', 'evening', 'Nghỉ', NULL, '2025-08-07 11:03:43', '2025-08-07 11:03:43'),
(18, '2025-07-04', 'morning', 'Cập nhật điển hình bó vỉa, vỉa hè (Yết Kiêu)', NULL, '2025-08-07 11:04:06', '2025-08-07 11:04:06'),
(18, '2025-07-04', 'afternoon', 'Cập nhật điển hình bó vỉa, vỉa hè (Yết Kiêu)', NULL, '2025-08-07 11:04:07', '2025-08-07 11:04:07'),
(18, '2025-07-04', 'evening', 'Nghỉ', NULL, '2025-08-07 11:04:07', '2025-08-07 11:04:07'),
(18, '2025-07-05', 'morning', 'Bổ sung điển hình thoát nước tuyến hồ Yết Kiêu', NULL, '2025-08-07 11:04:27', '2025-08-07 11:04:27'),
(18, '2025-07-05', 'afternoon', 'Bổ sung điển hình thoát nước tuyến hồ Yết Kiêu', NULL, '2025-08-07 11:04:27', '2025-08-07 11:04:27'),
(18, '2025-07-05', 'evening', 'Nghỉ', NULL, '2025-08-07 11:04:27', '2025-08-07 11:04:27'),
(18, '2025-07-06', 'morning', 'Nghỉ', NULL, '2025-08-07 11:04:32', '2025-08-07 11:04:32'),
(18, '2025-07-06', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:04:32', '2025-08-07 11:04:32'),
(18, '2025-07-06', 'evening', 'Nghỉ', NULL, '2025-08-07 11:04:32', '2025-08-07 11:04:32'),
(18, '2025-07-07', 'morning', 'Bổ sung phương án cống D300 qua đường (Yết Kiêu)', NULL, '2025-08-07 11:05:24', '2025-08-07 11:05:24'),
(18, '2025-07-07', 'afternoon', 'Bổ sung phương án cống D300 qua đường (Yết Kiêu)', NULL, '2025-08-07 11:05:24', '2025-08-07 11:05:24'),
(18, '2025-07-07', 'evening', 'Nghỉ', NULL, '2025-08-07 11:05:24', '2025-08-07 11:05:24'),
(18, '2025-07-08', 'morning', 'Chỉnh sửa điển hình thoát nước trực tiếp (Yết Kiêu)', NULL, '2025-08-07 11:05:53', '2025-08-07 11:05:53'),
(18, '2025-07-08', 'afternoon', 'Chỉnh sửa điển hình thoát nước trực tiếp (Yết Kiêu)', NULL, '2025-08-07 11:05:53', '2025-08-07 11:05:53'),
(18, '2025-07-08', 'evening', 'Nghỉ', NULL, '2025-08-07 11:05:53', '2025-08-07 11:05:53'),
(18, '2025-07-09', 'morning', 'Chỉnh sửa điển hình thoát nước trực tiếp (Yết Kiêu)', NULL, '2025-08-07 11:06:14', '2025-08-07 11:06:14'),
(18, '2025-07-09', 'afternoon', 'Cập nhật khối lượng thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:06:14', '2025-08-07 11:06:14'),
(18, '2025-07-09', 'evening', 'Nghỉ', NULL, '2025-08-07 11:06:14', '2025-08-07 11:06:14'),
(18, '2025-07-10', 'morning', 'Hoàn thiện bản in BVDH thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:06:50', '2025-08-07 11:06:50'),
(18, '2025-07-10', 'afternoon', 'Hoàn thiện bản in BVDH thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:06:50', '2025-08-07 11:06:50'),
(18, '2025-07-10', 'evening', 'Nghỉ', NULL, '2025-08-07 11:06:50', '2025-08-07 11:06:50'),
(18, '2025-07-11', 'morning', 'Hoàn thiện bản in BVDH thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:06:57', '2025-08-07 11:06:57'),
(18, '2025-07-11', 'afternoon', 'Hoàn thiện bản in BVDH thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:06:57', '2025-08-07 11:06:57'),
(18, '2025-07-11', 'evening', 'Nghỉ', NULL, '2025-08-07 11:06:57', '2025-08-07 11:06:57'),
(18, '2025-07-12', 'morning', 'Cập nhật KL hố thu nước trực tiếp và hố thu nước cống hộp', NULL, '2025-08-07 11:07:22', '2025-08-07 11:07:22'),
(18, '2025-07-12', 'afternoon', 'Cập nhật KL hố thu nước trực tiếp và hố thu nước cống hộp', NULL, '2025-08-07 11:07:22', '2025-08-07 11:07:22'),
(18, '2025-07-12', 'evening', 'Nghỉ', NULL, '2025-08-07 11:07:22', '2025-08-07 11:07:22'),
(18, '2025-07-13', 'morning', 'Nghỉ', NULL, '2025-08-07 11:07:26', '2025-08-07 11:07:26'),
(18, '2025-07-13', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:07:26', '2025-08-07 11:07:26'),
(18, '2025-07-13', 'evening', 'Nghỉ', NULL, '2025-08-07 11:07:26', '2025-08-07 11:07:26'),
(18, '2025-07-14', 'morning', 'Chỉnh sửa phương án khung chắn rác hố thu cống hộp (Yết Kiêu)', NULL, '2025-08-07 11:10:04', '2025-08-07 11:10:04'),
(18, '2025-07-14', 'afternoon', 'Chỉnh sửa phương án khung chắn rác hố thu cống hộp (Yết Kiêu)', NULL, '2025-08-07 11:10:04', '2025-08-07 11:10:04'),
(18, '2025-07-14', 'evening', 'Nghỉ', NULL, '2025-08-07 11:10:04', '2025-08-07 11:10:04'),
(18, '2025-07-15', 'morning', 'Bổ sung bản vẽ điển hình cải tạo hố thu và nạo vét rãnh (Yết Kiêu)', NULL, '2025-08-07 11:10:31', '2025-08-07 11:10:31'),
(18, '2025-07-15', 'afternoon', 'Bổ sung bản vẽ điển hình cải tạo hố thu và nạo vét rãnh (Yết Kiêu)', NULL, '2025-08-07 11:10:31', '2025-08-07 11:10:31'),
(18, '2025-07-15', 'evening', 'Nghỉ', NULL, '2025-08-07 11:10:31', '2025-08-07 11:10:31'),
(18, '2025-07-16', 'morning', 'Hoàn thiện bản in BVDH thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:10:56', '2025-08-07 11:10:56'),
(18, '2025-07-16', 'afternoon', 'Hoàn thiện bản in BVDH thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:10:56', '2025-08-07 11:10:56'),
(18, '2025-07-16', 'evening', 'Nghỉ', NULL, '2025-08-07 11:10:56', '2025-08-07 11:10:56'),
(18, '2025-07-17', 'morning', 'Hoàn thiện bản in BVDH thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:11:01', '2025-08-07 11:11:01'),
(18, '2025-07-17', 'afternoon', 'Hoàn thiện bản in BVDH thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:11:01', '2025-08-07 11:11:01'),
(18, '2025-07-17', 'evening', 'Nghỉ', NULL, '2025-08-07 11:11:01', '2025-08-07 11:11:01'),
(18, '2025-07-18', 'morning', 'Bổ sung BTC cống hộp tại cọc 29 (Yết Kiêu)', NULL, '2025-08-07 11:11:21', '2025-08-07 11:11:21'),
(18, '2025-07-18', 'afternoon', 'Bổ sung BTC cống hộp tại cọc 29 (Yết Kiêu)', NULL, '2025-08-07 11:11:21', '2025-08-07 11:11:21'),
(18, '2025-07-18', 'evening', 'Nghỉ', NULL, '2025-08-07 11:11:21', '2025-08-07 11:11:21'),
(18, '2025-07-19', 'morning', 'Chỉnh sửa BTC cống hộp tại cọc 29 (Yết Kiêu)', NULL, '2025-08-07 11:12:09', '2025-08-07 11:12:09'),
(18, '2025-07-19', 'afternoon', 'Khối lượng cống hộp cọc 29 (Yết Kiêu)', NULL, '2025-08-07 11:12:09', '2025-08-07 11:12:09'),
(18, '2025-07-19', 'evening', 'Nghỉ', NULL, '2025-08-07 11:12:09', '2025-08-07 11:12:09'),
(18, '2025-07-20', 'morning', 'Thống kê đất sử dụng (QL.279)', NULL, '2025-08-07 11:12:39', '2025-08-07 11:12:39'),
(18, '2025-07-20', 'afternoon', 'Thống kê đất sử dụng (QL.279)', NULL, '2025-08-07 11:12:39', '2025-08-07 11:12:39'),
(18, '2025-07-20', 'evening', 'Nghỉ', NULL, '2025-08-07 11:12:39', '2025-08-07 11:12:39'),
(18, '2025-07-21', 'morning', 'Cập nhật khối lượng thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:13:06', '2025-08-07 11:13:06'),
(18, '2025-07-21', 'afternoon', 'Cập nhật khối lượng thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:13:06', '2025-08-07 11:13:06'),
(18, '2025-07-21', 'evening', 'Nghỉ', NULL, '2025-08-07 11:13:06', '2025-08-07 11:13:06'),
(18, '2025-07-22', 'morning', 'Nghỉ', NULL, '2025-08-07 11:13:15', '2025-08-07 11:13:15'),
(18, '2025-07-22', 'afternoon', 'Cập nhật khối lượng thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:13:15', '2025-08-07 11:13:15'),
(18, '2025-07-22', 'evening', 'Nghỉ', NULL, '2025-08-07 11:13:15', '2025-08-07 11:13:15'),
(18, '2025-07-23', 'morning', 'Bình đồ thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:13:32', '2025-08-07 11:13:32'),
(18, '2025-07-23', 'afternoon', 'Bình đồ thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:13:32', '2025-08-07 11:13:32'),
(18, '2025-07-23', 'evening', 'Nghỉ', NULL, '2025-08-07 11:13:32', '2025-08-07 11:13:32'),
(18, '2025-07-24', 'morning', 'Bình đồ thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:13:39', '2025-08-07 11:13:39'),
(18, '2025-07-24', 'afternoon', 'Bình đồ thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:13:40', '2025-08-07 11:13:40'),
(18, '2025-07-24', 'evening', 'Nghỉ', NULL, '2025-08-07 11:13:40', '2025-08-07 11:13:40'),
(18, '2025-07-25', 'morning', 'Thống kê thoát nước và cập nhật khối lượng', NULL, '2025-08-07 11:14:19', '2025-08-07 11:14:19'),
(18, '2025-07-25', 'afternoon', 'Thống kê thoát nước và cập nhật khối lượng', NULL, '2025-08-07 11:14:19', '2025-08-07 11:14:19'),
(18, '2025-07-25', 'evening', 'Nghỉ', NULL, '2025-08-07 11:14:19', '2025-08-07 11:14:19'),
(18, '2025-07-26', 'morning', 'Thống kê đất sử dụng (xã Minh Cầm, Lương Mông)', NULL, '2025-08-07 11:14:45', '2025-08-07 11:14:45'),
(18, '2025-07-26', 'afternoon', 'Thống kê đất sử dụng (xã Minh Cầm, Lương Mông)', NULL, '2025-08-07 11:14:45', '2025-08-07 11:14:45'),
(18, '2025-07-26', 'evening', 'Nghỉ', NULL, '2025-08-07 11:14:45', '2025-08-07 11:14:45'),
(18, '2025-07-27', 'morning', 'Tổng hợp khối lượng thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:15:06', '2025-08-07 11:15:06'),
(18, '2025-07-27', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:15:06', '2025-08-07 11:15:06'),
(18, '2025-07-27', 'evening', 'Nghỉ', NULL, '2025-08-07 11:15:06', '2025-08-07 11:15:06'),
(18, '2025-07-28', 'morning', 'Tổng hợp khối lượng thoát nước (Yết Kiêu)', NULL, '2025-08-07 11:15:59', '2025-08-07 11:15:59'),
(18, '2025-07-28', 'afternoon', 'Thống kê đất sử dụng (xã Phương Đông)', NULL, '2025-08-07 11:15:59', '2025-08-07 11:15:59'),
(18, '2025-07-28', 'evening', 'Nghỉ', NULL, '2025-08-07 11:15:59', '2025-08-07 11:15:59'),
(18, '2025-07-29', 'morning', 'Nghỉ', NULL, '2025-08-07 11:16:19', '2025-08-07 11:16:19'),
(18, '2025-07-29', 'afternoon', 'Thống kê đất sử dụng (xã Phương Đông)', NULL, '2025-08-07 11:16:19', '2025-08-07 11:16:19'),
(18, '2025-07-29', 'evening', 'Nghỉ', NULL, '2025-08-07 11:16:19', '2025-08-07 11:16:19'),
(18, '2025-07-30', 'morning', 'Khối lượng vỉa hè tuyến Mạo Khê', NULL, '2025-08-07 11:16:33', '2025-08-07 11:16:33'),
(18, '2025-07-30', 'afternoon', 'Khối lượng vỉa hè tuyến Mạo Khê', NULL, '2025-08-07 11:16:33', '2025-08-07 11:16:33'),
(18, '2025-07-30', 'evening', '17:00-19:30: Khối lượng vỉa hè tuyến Mạo Khê', NULL, '2025-08-07 11:16:33', '2025-08-07 11:16:33'),
(18, '2025-07-31', 'morning', 'Khối lượng vỉa hè tuyến Mạo Khê', NULL, '2025-08-07 11:16:39', '2025-08-07 11:16:39'),
(18, '2025-07-31', 'afternoon', 'Khối lượng vỉa hè tuyến Mạo Khê', NULL, '2025-08-07 11:16:39', '2025-08-07 11:16:39'),
(18, '2025-07-31', 'evening', 'Nghỉ', NULL, '2025-08-07 11:16:39', '2025-08-07 11:16:39'),
(19, '2025-07-01', 'morning', 'Điều chỉnh đường ven sông (G10) - Đê Hồng Phong', NULL, '2025-08-07 11:25:21', '2025-08-07 11:25:21'),
(19, '2025-07-01', 'afternoon', 'Điều chỉnh đường ven sông (G10) - Đê Hồng Phong', NULL, '2025-08-07 11:25:21', '2025-08-07 11:25:21'),
(19, '2025-07-01', 'evening', 'Nghỉ', NULL, '2025-08-07 11:25:21', '2025-08-07 11:25:21'),
(19, '2025-07-02', 'morning', 'Điều chỉnh đường ven sông (G10) - Đê Hồng Phong', NULL, '2025-08-07 11:25:52', '2025-08-07 11:25:52'),
(19, '2025-07-02', 'afternoon', 'In dự toán BVTC Vũ Phi Hổ theo duyệt', NULL, '2025-08-07 11:25:52', '2025-08-07 11:25:52'),
(19, '2025-07-02', 'evening', 'Nghỉ', NULL, '2025-08-07 11:25:52', '2025-08-07 11:25:52'),
(19, '2025-07-03', 'morning', 'In dự toán điều chỉnh BVTC đường Sơn Dương', NULL, '2025-08-07 11:26:11', '2025-08-07 11:26:11'),
(19, '2025-07-03', 'afternoon', 'In dự toán điều chỉnh BVTC đường Sơn Dương', NULL, '2025-08-07 11:26:11', '2025-08-07 11:26:11'),
(19, '2025-07-03', 'evening', 'Nghỉ', NULL, '2025-08-07 11:26:11', '2025-08-07 11:26:11'),
(19, '2025-07-04', 'morning', 'VL đầu vào Yết Kiêu', NULL, '2025-08-07 11:26:56', '2025-08-07 11:26:56'),
(19, '2025-07-04', 'afternoon', 'In dự toán BVTC cầu Khe Pụt (Theo biến động giá) gửi sở XD', NULL, '2025-08-07 11:26:57', '2025-08-07 11:26:57'),
(19, '2025-07-04', 'evening', 'Nghỉ', NULL, '2025-08-07 11:26:57', '2025-08-07 11:26:57'),
(19, '2025-07-05', 'morning', 'In dự toán BVTC cầu Làng Lốc (Theo biến động giá) gửi sở XD', NULL, '2025-08-07 11:27:16', '2025-08-07 11:27:16'),
(19, '2025-07-05', 'afternoon', 'VL đầu vào Yết Kiêu', NULL, '2025-08-07 11:27:16', '2025-08-07 11:27:16'),
(19, '2025-07-05', 'evening', 'Nghỉ', NULL, '2025-08-07 11:27:16', '2025-08-07 11:27:16'),
(19, '2025-07-06', 'morning', 'Nghỉ', NULL, '2025-08-07 11:27:21', '2025-08-07 11:27:21'),
(19, '2025-07-06', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:27:21', '2025-08-07 11:27:21'),
(19, '2025-07-06', 'evening', 'Nghỉ', NULL, '2025-08-07 11:27:21', '2025-08-07 11:27:21'),
(19, '2025-07-07', 'morning', 'Nghỉ', NULL, '2025-08-07 11:27:39', '2025-08-07 11:27:39'),
(19, '2025-07-07', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:27:39', '2025-08-07 11:27:39'),
(19, '2025-07-07', 'evening', 'Nghỉ', NULL, '2025-08-07 11:27:39', '2025-08-07 11:27:39'),
(19, '2025-07-08', 'morning', 'Nghỉ', NULL, '2025-08-07 11:27:45', '2025-08-07 11:27:45'),
(19, '2025-07-08', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:27:45', '2025-08-07 11:27:45'),
(19, '2025-07-08', 'evening', 'Nghỉ', NULL, '2025-08-07 11:27:45', '2025-08-07 11:27:45'),
(19, '2025-07-09', 'morning', 'Nghỉ', NULL, '2025-08-07 11:27:49', '2025-08-07 11:27:49'),
(19, '2025-07-09', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:27:49', '2025-08-07 11:27:49'),
(19, '2025-07-09', 'evening', 'Nghỉ', NULL, '2025-08-07 11:27:49', '2025-08-07 11:27:49'),
(19, '2025-07-10', 'morning', 'Nghỉ', NULL, '2025-08-07 11:27:52', '2025-08-07 11:27:52'),
(19, '2025-07-10', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:27:52', '2025-08-07 11:27:52'),
(19, '2025-07-10', 'evening', 'Nghỉ', NULL, '2025-08-07 11:27:52', '2025-08-07 11:27:52'),
(19, '2025-07-11', 'morning', 'Nghỉ', NULL, '2025-08-07 11:27:55', '2025-08-07 11:27:55'),
(19, '2025-07-11', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:27:55', '2025-08-07 11:27:55'),
(19, '2025-07-11', 'evening', 'Nghỉ', NULL, '2025-08-07 11:27:55', '2025-08-07 11:27:55'),
(19, '2025-07-12', 'morning', 'Nghỉ', NULL, '2025-08-07 11:27:59', '2025-08-07 11:27:59'),
(19, '2025-07-12', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:27:59', '2025-08-07 11:27:59'),
(19, '2025-07-12', 'evening', 'Nghỉ', NULL, '2025-08-07 11:27:59', '2025-08-07 11:27:59'),
(19, '2025-07-13', 'morning', 'Nghỉ', NULL, '2025-08-07 11:28:02', '2025-08-07 11:28:02'),
(19, '2025-07-13', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:28:02', '2025-08-07 11:28:02'),
(19, '2025-07-13', 'evening', 'Nghỉ', NULL, '2025-08-07 11:28:02', '2025-08-07 11:28:02'),
(19, '2025-07-14', 'morning', 'Cập nhật giá và in hồ sơ cầu Làng Lốc (BVTC)', NULL, '2025-08-07 11:28:40', '2025-08-07 11:28:40'),
(19, '2025-07-14', 'afternoon', 'Cập nhật thông tư, nghị định mới', NULL, '2025-08-07 11:28:40', '2025-08-07 11:28:40'),
(19, '2025-07-14', 'evening', 'Nghỉ', NULL, '2025-08-07 11:28:40', '2025-08-07 11:28:40'),
(19, '2025-07-15', 'morning', 'Sửa DT BVTC 2 cầu theo định mức TT 08/2025', NULL, '2025-08-07 11:29:10', '2025-08-07 11:29:10'),
(19, '2025-07-15', 'afternoon', 'DT điều chỉnh cầu Quảng Lợi', NULL, '2025-08-07 11:29:10', '2025-08-07 11:29:10'),
(19, '2025-07-15', 'evening', 'Nghỉ', NULL, '2025-08-07 11:29:10', '2025-08-07 11:29:10'),
(19, '2025-07-16', 'morning', 'DT điều chỉnh cầu Quảng Lợi', NULL, '2025-08-07 11:29:38', '2025-08-07 11:29:38'),
(19, '2025-07-16', 'afternoon', 'Điềuc hỉnh và so sánh 2 PA đê Hồng Phong (G10 ven sông)', NULL, '2025-08-07 11:29:38', '2025-08-07 11:29:38'),
(19, '2025-07-16', 'evening', 'Nghỉ', NULL, '2025-08-07 11:29:38', '2025-08-07 11:29:38'),
(19, '2025-07-17', 'morning', 'DT monman chi phí tư vấn bổ sung Vân Phong', NULL, '2025-08-07 11:30:15', '2025-08-07 11:30:15'),
(19, '2025-07-17', 'afternoon', 'Sửa DT chi phí Con Ong- hòn nét theo yktd Ban', NULL, '2025-08-07 11:30:15', '2025-08-07 11:30:15'),
(19, '2025-07-17', 'evening', 'Nghỉ', NULL, '2025-08-07 11:30:15', '2025-08-07 11:30:15'),
(19, '2025-07-18', 'morning', 'Sửa DT chi phí Con Ong- hòn nét theo yktd Ban', NULL, '2025-08-07 11:30:27', '2025-08-07 11:30:27'),
(19, '2025-07-18', 'afternoon', 'DT điều chỉnh cầu Quảng Lợi', NULL, '2025-08-07 11:30:27', '2025-08-07 11:30:27'),
(19, '2025-07-18', 'evening', 'Nghỉ', NULL, '2025-08-07 11:30:27', '2025-08-07 11:30:27'),
(19, '2025-07-19', 'morning', 'DA đường vào chợ Hạ Long 1', NULL, '2025-08-07 11:30:43', '2025-08-07 11:30:43'),
(19, '2025-07-19', 'afternoon', 'DA đường vào chợ Hạ Long 1', NULL, '2025-08-07 11:30:43', '2025-08-07 11:30:43'),
(19, '2025-07-19', 'evening', 'Nghỉ', NULL, '2025-08-07 11:30:43', '2025-08-07 11:30:43'),
(19, '2025-07-20', 'morning', 'Học lớp triển khai BIM', NULL, '2025-08-07 11:31:11', '2025-08-07 11:31:11'),
(19, '2025-07-20', 'afternoon', 'Sửa chi phí BIM dự án COn ong - Hòn nét', NULL, '2025-08-07 11:31:11', '2025-08-07 11:31:11'),
(19, '2025-07-20', 'evening', 'Nghỉ', NULL, '2025-08-07 11:31:11', '2025-08-07 11:31:11'),
(19, '2025-07-21', 'morning', 'DA đường chợ Hạ Long 1', NULL, '2025-08-07 11:31:27', '2025-08-07 11:31:27'),
(19, '2025-07-21', 'afternoon', 'DA đường chợ Hạ Long 1', NULL, '2025-08-07 11:31:27', '2025-08-07 11:31:27'),
(19, '2025-07-21', 'evening', 'Nghỉ', NULL, '2025-08-07 11:31:27', '2025-08-07 11:31:27'),
(19, '2025-07-22', 'morning', 'Nghỉ', NULL, '2025-08-07 11:32:06', '2025-08-07 11:32:06'),
(19, '2025-07-22', 'afternoon', 'Sửa DT KS nhiệm vụ lập BCNCKT Hoa lợi Đạt (theo yktđ)', NULL, '2025-08-07 11:32:06', '2025-08-07 11:32:06'),
(19, '2025-07-22', 'evening', 'Nghỉ', NULL, '2025-08-07 11:32:06', '2025-08-07 11:32:06'),
(19, '2025-07-23', 'morning', 'Cập nhật KL và giá VL, XD 2 cầu Khe Pụt, Làng Lốc', NULL, '2025-08-07 11:32:37', '2025-08-07 11:32:37'),
(19, '2025-07-23', 'afternoon', 'Cập nhật KL và giá VL, XD 2 cầu Khe Pụt, Làng Lốc', NULL, '2025-08-07 11:32:37', '2025-08-07 11:32:37'),
(19, '2025-07-23', 'evening', 'Nghỉ', NULL, '2025-08-07 11:32:37', '2025-08-07 11:32:37'),
(19, '2025-07-24', 'morning', 'Khái toán chủ trương 2 đoạn Cải tạo QL279 +GPMB', NULL, '2025-08-07 11:33:00', '2025-08-07 11:33:00'),
(19, '2025-07-24', 'afternoon', 'Khái toán chủ trương 2 đoạn Cải tạo QL279 +GPMB', NULL, '2025-08-07 11:33:00', '2025-08-07 11:33:00'),
(19, '2025-07-24', 'evening', 'Nghỉ', NULL, '2025-08-07 11:33:00', '2025-08-07 11:33:00'),
(19, '2025-07-25', 'morning', 'Cập nhật thông tư 08 và giá Q2-2025 vào 2 cầu Ba Chẽ', NULL, '2025-08-07 11:33:22', '2025-08-07 11:33:22'),
(19, '2025-07-25', 'afternoon', 'Cập nhật thông tư 08 và giá Q2-2025 vào 2 cầu Ba Chẽ', NULL, '2025-08-07 11:33:22', '2025-08-07 11:33:22'),
(19, '2025-07-25', 'evening', 'Nghỉ', NULL, '2025-08-07 11:33:22', '2025-08-07 11:33:22'),
(19, '2025-07-26', 'morning', 'Nghỉ', NULL, '2025-08-07 11:33:27', '2025-08-07 11:33:27'),
(19, '2025-07-26', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:33:27', '2025-08-07 11:33:27'),
(19, '2025-07-26', 'evening', 'Nghỉ', NULL, '2025-08-07 11:33:27', '2025-08-07 11:33:27'),
(19, '2025-07-27', 'morning', 'Nghỉ', NULL, '2025-08-07 11:33:30', '2025-08-07 11:33:30'),
(19, '2025-07-27', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:33:30', '2025-08-07 11:33:30'),
(19, '2025-07-27', 'evening', 'Nghỉ', NULL, '2025-08-07 11:33:30', '2025-08-07 11:33:30'),
(19, '2025-07-28', 'morning', 'Sửa chủ trương các DA của Ban', NULL, '2025-08-07 11:33:58', '2025-08-07 11:33:58'),
(19, '2025-07-28', 'afternoon', 'Sửa 2 cầu Ba Chẽ theo KL thiết kế sửa', NULL, '2025-08-07 11:33:58', '2025-08-07 11:33:58'),
(19, '2025-07-28', 'evening', 'Nghỉ', NULL, '2025-08-07 11:33:58', '2025-08-07 11:33:58'),
(19, '2025-07-29', 'morning', 'Sửa 2 cầu Ba Chẽ theo KL thiết kế sửa', NULL, '2025-08-07 11:34:28', '2025-08-07 11:34:28'),
(19, '2025-07-29', 'afternoon', 'Chủ trương DA 18B', NULL, '2025-08-07 11:34:28', '2025-08-07 11:34:28'),
(19, '2025-07-29', 'evening', 'Nghỉ', NULL, '2025-08-07 11:34:28', '2025-08-07 11:34:28'),
(19, '2025-07-30', 'morning', 'Sang ban (chủ trương DA 18B)', NULL, '2025-08-07 11:34:52', '2025-08-07 11:34:52'),
(19, '2025-07-30', 'afternoon', 'Sửa chủ trương 18B theo yk ban', NULL, '2025-08-07 11:34:52', '2025-08-07 11:34:52'),
(19, '2025-07-30', 'evening', 'Nghỉ', NULL, '2025-08-07 11:34:52', '2025-08-07 11:34:52'),
(19, '2025-07-31', 'morning', 'In DT điều chỉnh cầu Quảng Lợi (theo yktđ)', NULL, '2025-08-07 11:35:19', '2025-08-07 11:35:19'),
(19, '2025-07-31', 'afternoon', 'Sửa 2 cầu Ba Chẽ theo KL thiết kế sửa', NULL, '2025-08-07 11:35:19', '2025-08-07 11:35:19'),
(19, '2025-07-31', 'evening', 'Nghỉ', NULL, '2025-08-07 11:35:19', '2025-08-07 11:35:19'),
(20, '2025-07-01', 'morning', 'Nghỉ', NULL, '2025-08-07 11:36:26', '2025-08-07 11:36:26'),
(20, '2025-07-01', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:36:26', '2025-08-07 11:36:26'),
(20, '2025-07-01', 'evening', 'Nghỉ', NULL, '2025-08-07 11:36:26', '2025-08-07 11:36:26'),
(20, '2025-07-02', 'morning', 'ĐCBS đường Sơn Dương', NULL, '2025-08-07 11:36:42', '2025-08-07 11:36:42'),
(20, '2025-07-02', 'afternoon', 'ĐCBS đường Sơn Dương', NULL, '2025-08-07 11:36:42', '2025-08-07 11:36:42'),
(20, '2025-07-02', 'evening', 'Nghỉ', NULL, '2025-08-07 11:36:42', '2025-08-07 11:36:42'),
(20, '2025-07-03', 'morning', 'ĐCBS đường Sơn Dương', NULL, '2025-08-07 11:36:54', '2025-08-07 11:36:54'),
(20, '2025-07-03', 'afternoon', 'ĐCBS đường Sơn Dương', NULL, '2025-08-07 11:36:54', '2025-08-07 11:36:54'),
(20, '2025-07-03', 'evening', 'Nghỉ', NULL, '2025-08-07 11:36:54', '2025-08-07 11:36:54'),
(20, '2025-07-04', 'morning', 'ĐCBS đường Sơn Dương', NULL, '2025-08-07 11:36:58', '2025-08-07 11:36:58'),
(20, '2025-07-04', 'afternoon', 'ĐCBS đường Sơn Dương', NULL, '2025-08-07 11:36:58', '2025-08-07 11:36:58'),
(20, '2025-07-04', 'evening', 'Nghỉ', NULL, '2025-08-07 11:36:58', '2025-08-07 11:36:58'),
(20, '2025-07-05', 'morning', 'ĐCBS đường Sơn Dương', NULL, '2025-08-07 11:37:07', '2025-08-07 11:37:07'),
(20, '2025-07-05', 'afternoon', 'ĐCBS đường Sơn Dương', NULL, '2025-08-07 11:37:07', '2025-08-07 11:37:07'),
(20, '2025-07-05', 'evening', 'Nghỉ', NULL, '2025-08-07 11:37:07', '2025-08-07 11:37:07'),
(20, '2025-07-06', 'morning', 'ĐCBS đường Sơn Dương', NULL, '2025-08-07 11:37:12', '2025-08-07 11:37:12'),
(20, '2025-07-06', 'afternoon', 'ĐCBS đường Sơn Dương', NULL, '2025-08-07 11:37:12', '2025-08-07 11:37:12'),
(20, '2025-07-06', 'evening', 'Nghỉ', NULL, '2025-08-07 11:37:12', '2025-08-07 11:37:12'),
(20, '2025-07-07', 'morning', 'Đi hiện trường Trại Thuyền', NULL, '2025-08-07 11:37:53', '2025-08-07 11:37:53'),
(20, '2025-07-07', 'afternoon', 'ĐCBS Trại tuyền', NULL, '2025-08-07 11:37:53', '2025-08-07 11:37:53'),
(20, '2025-07-07', 'evening', 'Nghỉ', NULL, '2025-08-07 11:37:53', '2025-08-07 11:37:53'),
(20, '2025-07-08', 'morning', 'ĐCBS Trại tuyền', NULL, '2025-08-07 11:37:59', '2025-08-07 11:37:59'),
(20, '2025-07-08', 'afternoon', 'ĐCBS Trại tuyền', NULL, '2025-08-07 11:37:59', '2025-08-07 11:37:59'),
(20, '2025-07-08', 'evening', 'Nghỉ', NULL, '2025-08-07 11:37:59', '2025-08-07 11:37:59'),
(20, '2025-07-09', 'morning', 'ĐCBS đường nội thị Đầm Hà', NULL, '2025-08-07 11:38:13', '2025-08-07 11:38:13'),
(20, '2025-07-09', 'afternoon', 'ĐCBS đường nội thị Đầm Hà', NULL, '2025-08-07 11:38:13', '2025-08-07 11:38:13'),
(20, '2025-07-09', 'evening', 'Nghỉ', NULL, '2025-08-07 11:38:13', '2025-08-07 11:38:13'),
(20, '2025-07-10', 'morning', 'ĐCBS đường nội thị Đầm Hà', NULL, '2025-08-07 11:38:16', '2025-08-07 11:38:16'),
(20, '2025-07-10', 'afternoon', 'ĐCBS đường nội thị Đầm Hà', NULL, '2025-08-07 11:38:16', '2025-08-07 11:38:16'),
(20, '2025-07-10', 'evening', 'Nghỉ', NULL, '2025-08-07 11:38:16', '2025-08-07 11:38:16'),
(20, '2025-07-11', 'morning', 'ĐCBS đường nội thị Đầm Hà', NULL, '2025-08-07 11:38:31', '2025-08-07 11:38:31'),
(20, '2025-07-11', 'afternoon', 'Đi hiện trường đường Sơn Dương', NULL, '2025-08-07 11:38:31', '2025-08-07 11:38:31'),
(20, '2025-07-11', 'evening', 'Nghỉ', NULL, '2025-08-07 11:38:31', '2025-08-07 11:38:31'),
(20, '2025-07-12', 'morning', 'ĐCBS đường đường Sơn Dương', NULL, '2025-08-07 11:38:51', '2025-08-07 11:38:51'),
(20, '2025-07-12', 'afternoon', 'ĐCBS đường đường Sơn Dương', NULL, '2025-08-07 11:38:51', '2025-08-07 11:38:51'),
(20, '2025-07-12', 'evening', 'Nghỉ', NULL, '2025-08-07 11:38:51', '2025-08-07 11:38:51'),
(20, '2025-07-13', 'morning', 'Nghỉ', NULL, '2025-08-07 11:38:56', '2025-08-07 11:38:56'),
(20, '2025-07-13', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:38:56', '2025-08-07 11:38:56'),
(20, '2025-07-13', 'evening', 'Nghỉ', NULL, '2025-08-07 11:38:56', '2025-08-07 11:38:56'),
(20, '2025-07-14', 'morning', 'Thẩm tra ĐCBS ĐT.333', NULL, '2025-08-07 11:39:15', '2025-08-07 11:39:15'),
(20, '2025-07-14', 'afternoon', 'Thẩm tra ĐCBS ĐT.333', NULL, '2025-08-07 11:39:15', '2025-08-07 11:39:15'),
(20, '2025-07-14', 'evening', 'Nghỉ', NULL, '2025-08-07 11:39:15', '2025-08-07 11:39:15'),
(20, '2025-07-15', 'morning', 'ĐCBS Cầu Quảng Lợi', NULL, '2025-08-07 11:39:28', '2025-08-07 11:39:28'),
(20, '2025-07-15', 'afternoon', 'ĐCBS Cầu Quảng Lợi', NULL, '2025-08-07 11:39:28', '2025-08-07 11:39:28'),
(20, '2025-07-15', 'evening', 'Nghỉ', NULL, '2025-08-07 11:39:28', '2025-08-07 11:39:28'),
(20, '2025-07-16', 'morning', 'ĐCBS Cầu Quảng Lợi', NULL, '2025-08-07 11:39:34', '2025-08-07 11:39:34'),
(20, '2025-07-16', 'afternoon', 'ĐCBS Cầu Quảng Lợi', NULL, '2025-08-07 11:39:34', '2025-08-07 11:39:34'),
(20, '2025-07-16', 'evening', 'Nghỉ', NULL, '2025-08-07 11:39:34', '2025-08-07 11:39:34'),
(20, '2025-07-17', 'morning', 'ĐCBS Cầu Quảng Lợi', NULL, '2025-08-07 11:39:39', '2025-08-07 11:39:39'),
(20, '2025-07-17', 'afternoon', 'ĐCBS Cầu Quảng Lợi', NULL, '2025-08-07 11:39:39', '2025-08-07 11:39:39'),
(20, '2025-07-17', 'evening', 'Nghỉ', NULL, '2025-08-07 11:39:39', '2025-08-07 11:39:39'),
(20, '2025-07-18', 'morning', 'ĐCBS gói 10 ven sông', NULL, '2025-08-07 11:39:53', '2025-08-07 11:39:53'),
(20, '2025-07-18', 'afternoon', 'ĐCBS gói 10 ven sông', NULL, '2025-08-07 11:39:53', '2025-08-07 11:39:53'),
(20, '2025-07-18', 'evening', 'Nghỉ', NULL, '2025-08-07 11:39:53', '2025-08-07 11:39:53'),
(20, '2025-07-19', 'morning', 'Học BIM', NULL, '2025-08-07 11:40:03', '2025-08-07 11:40:03'),
(20, '2025-07-19', 'afternoon', 'Học BIM', NULL, '2025-08-07 11:40:03', '2025-08-07 11:40:03'),
(20, '2025-07-19', 'evening', 'Nghỉ', NULL, '2025-08-07 11:40:03', '2025-08-07 11:40:03'),
(20, '2025-07-20', 'morning', 'Nghỉ', NULL, '2025-08-07 11:40:08', '2025-08-07 11:40:08'),
(20, '2025-07-20', 'afternoon', 'Nghỉ', NULL, '2025-08-07 11:40:08', '2025-08-07 11:40:08'),
(20, '2025-07-20', 'evening', 'Nghỉ', NULL, '2025-08-07 11:40:08', '2025-08-07 11:40:08'),
(20, '2025-07-21', 'morning', 'Điều chỉnh nâng đường đỏ Sơn Dương', NULL, '2025-08-07 11:40:37', '2025-08-07 11:40:37'),
(20, '2025-07-21', 'afternoon', 'Điều chỉnh nâng đường đỏ Sơn Dương', NULL, '2025-08-07 11:40:37', '2025-08-07 11:40:37'),
(20, '2025-07-21', 'evening', 'Nghỉ', NULL, '2025-08-07 11:40:37', '2025-08-07 11:40:37'),
(20, '2025-07-22', 'morning', 'Điều chỉnh nâng đường đỏ Sơn Dương', NULL, '2025-08-07 11:40:47', '2025-08-07 11:40:47'),
(20, '2025-07-22', 'afternoon', 'Điều chỉnh nâng đường đỏ Sơn Dương', NULL, '2025-08-07 11:40:47', '2025-08-07 11:40:47'),
(20, '2025-07-22', 'evening', 'Nghỉ', NULL, '2025-08-07 11:40:47', '2025-08-07 11:40:47'),
(20, '2025-07-23', 'morning', 'Chủ trương 342-330', NULL, '2025-08-07 11:40:59', '2025-08-07 11:40:59'),
(20, '2025-07-23', 'afternoon', 'Chủ trương 342-330', NULL, '2025-08-07 11:41:00', '2025-08-07 11:41:00'),
(20, '2025-07-23', 'evening', '17:00-19:30: Chủ trương 342-330', NULL, '2025-08-07 11:41:00', '2025-08-07 11:41:00'),
(20, '2025-07-24', 'morning', 'Chủ trương 342-330', NULL, '2025-08-07 11:41:04', '2025-08-07 11:41:04'),
(20, '2025-07-24', 'afternoon', 'Chủ trương 342-330', NULL, '2025-08-07 11:41:04', '2025-08-07 11:41:04'),
(20, '2025-07-24', 'evening', '17:00-19:30: Chủ trương 342-330', NULL, '2025-08-07 11:41:04', '2025-08-07 11:41:04'),
(20, '2025-07-25', 'morning', 'Chủ trương 342-330', NULL, '2025-08-07 11:41:07', '2025-08-07 11:41:07'),
(20, '2025-07-25', 'afternoon', 'Chủ trương 342-330', NULL, '2025-08-07 11:41:08', '2025-08-07 11:41:08'),
(20, '2025-07-25', 'evening', '17:00-19:30: Chủ trương 342-330', NULL, '2025-08-07 11:41:08', '2025-08-07 11:41:08'),
(20, '2025-07-26', 'morning', 'Chủ trương 342-330', NULL, '2025-08-07 11:41:13', '2025-08-07 11:41:13'),
(20, '2025-07-26', 'afternoon', 'Chủ trương 342-330', NULL, '2025-08-07 11:41:13', '2025-08-07 11:41:13'),
(20, '2025-07-26', 'evening', '17:00-19:30: Chủ trương 342-330', NULL, '2025-08-07 11:41:13', '2025-08-07 11:41:13'),
(20, '2025-07-27', 'morning', 'Học BIM', NULL, '2025-08-07 11:41:24', '2025-08-07 11:41:24'),
(20, '2025-07-27', 'afternoon', 'Chủ trương 342-330', NULL, '2025-08-07 11:41:24', '2025-08-07 11:41:24'),
(20, '2025-07-27', 'evening', 'Nghỉ', NULL, '2025-08-07 11:41:24', '2025-08-07 11:41:24'),
(20, '2025-07-28', 'morning', 'Chủ trương QL18B', NULL, '2025-08-07 11:41:41', '2025-08-07 11:41:41'),
(20, '2025-07-28', 'afternoon', 'Chủ trương QL18B', NULL, '2025-08-07 11:41:41', '2025-08-07 11:41:41'),
(20, '2025-07-28', 'evening', '17:00-19:30: Chủ trương QL18B', NULL, '2025-08-07 11:41:41', '2025-08-07 11:41:41'),
(20, '2025-07-29', 'morning', 'Chủ trương QL18B', NULL, '2025-08-07 11:41:47', '2025-08-07 11:41:47'),
(20, '2025-07-29', 'afternoon', 'Chủ trương QL18B', NULL, '2025-08-07 11:41:47', '2025-08-07 11:41:47'),
(20, '2025-07-29', 'evening', '17:00-19:30: Chủ trương QL18B', NULL, '2025-08-07 11:41:47', '2025-08-07 11:41:47'),
(20, '2025-07-30', 'morning', 'Chủ trương QL18B', NULL, '2025-08-07 11:41:51', '2025-08-07 11:41:51'),
(20, '2025-07-30', 'afternoon', 'Chủ trương QL18B', NULL, '2025-08-07 11:41:51', '2025-08-07 11:41:51'),
(20, '2025-07-30', 'evening', '17:00-19:30: Chủ trương QL18B', NULL, '2025-08-07 11:41:51', '2025-08-07 11:41:51'),
(20, '2025-07-31', 'morning', 'Chủ trương QL18B', NULL, '2025-08-07 11:41:56', '2025-08-07 11:41:56'),
(20, '2025-07-31', 'afternoon', 'Chủ trương QL18B', NULL, '2025-08-07 11:41:56', '2025-08-07 11:41:56'),
(20, '2025-07-31', 'evening', 'Nghỉ', NULL, '2025-08-07 11:41:56', '2025-08-07 11:41:56');

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
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_share` (`share_subscription`);

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
  ADD UNIQUE KEY `uq_org_member` (`organization_id`,`user_id`),
  ADD KEY `organization_id` (`organization_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_org_user` (`organization_id`,`user_id`),
  ADD KEY `idx_user_shared` (`user_id`,`is_shared`);

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
  ADD KEY `company_id` (`company_id`),
  ADD KEY `idx_sub` (`subscription_id`,`subscription_expires_at`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `organizations`
--
ALTER TABLE `organizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `organization_invitations`
--
ALTER TABLE `organization_invitations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `organization_members`
--
ALTER TABLE `organization_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
