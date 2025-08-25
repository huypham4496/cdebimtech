-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 25, 2025 at 11:53 AM
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
-- Table structure for table `file_versions`
--

CREATE TABLE `file_versions` (
  `id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `storage_path` varchar(500) NOT NULL,
  `size_bytes` bigint(20) NOT NULL DEFAULT 0,
  `uploaded_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `organization_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(32) NOT NULL,
  `status` enum('active','completed','onhold','archived') NOT NULL DEFAULT 'active',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `visibility` enum('private','org','public') NOT NULL DEFAULT 'org',
  `location` varchar(255) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `organization_id`, `name`, `code`, `status`, `start_date`, `end_date`, `manager_id`, `visibility`, `location`, `tags`, `description`, `created_by`, `created_at`, `updated_at`) VALUES
(6, 0, 'Dự án mẫu', 'PRJ00006', 'active', '2025-08-14', NULL, 1, '', 'Quảng Ninh', 'Construction Drawings', 'Dự án mẫu kiểm tra chức năng', 1, '2025-08-14 06:24:37', '2025-08-14 11:24:37');

-- --------------------------------------------------------

--
-- Table structure for table `project_activities`
--

CREATE TABLE `project_activities` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `detail` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_activities`
--

INSERT INTO `project_activities` (`id`, `project_id`, `user_id`, `action`, `detail`, `created_at`) VALUES
(1, 1, 1, 'project.create', 'Dự án Đường tỉnh 330', '2025-08-11 09:51:11'),
(2, 2, 1, 'project.create', 'Dự án Đường tỉnh 330', '2025-08-11 10:17:16'),
(3, 3, 1, 'project.create', 'Dự án Đường tỉnh 331', '2025-08-11 10:18:26'),
(8, 5, 1, 'delete', 'Cascade delete project_*', '2025-08-14 11:04:52'),
(9, 4, 1, 'delete', 'Cascade delete project_*', '2025-08-14 11:04:58'),
(10, 6, 1, 'create', 'Create via list modal', '2025-08-14 11:24:37'),
(11, 6, 1, 'folder.create', 'PRJ00006', '2025-08-14 11:24:45');

-- --------------------------------------------------------

--
-- Table structure for table `project_color_groups`
--

CREATE TABLE `project_color_groups` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_color_groups`
--

INSERT INTO `project_color_groups` (`id`, `project_id`, `name`, `created_by`, `created_at`) VALUES
(8, 6, 'Phần cầu', 1, '2025-08-14 15:02:23'),
(9, 6, 'Phần tuyến', 1, '2025-08-14 15:02:27'),
(10, 6, 'Vạch sơn', 1, '2025-08-14 15:02:33');

-- --------------------------------------------------------

--
-- Table structure for table `project_color_items`
--

CREATE TABLE `project_color_items` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `hex_color` varchar(9) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_color_items`
--

INSERT INTO `project_color_items` (`id`, `group_id`, `label`, `hex_color`, `sort_order`, `created_at`, `updated_at`) VALUES
(10, 10, 'Vạch 3.1', '#FFFFFF', 1, '2025-08-14 15:02:41', '2025-08-14 15:02:45'),
(11, 10, 'Vạch 2.1', '#FEE858', 2, '2025-08-14 15:02:50', '2025-08-14 15:03:17'),
(12, 8, 'Bệ móng', '#FF0000', 1, '2025-08-14 15:03:23', '2025-08-14 15:03:26'),
(13, 8, 'Cọc BTCT', '#77FF3D', 2, '2025-08-14 15:03:32', '2025-08-14 15:03:36'),
(14, 8, 'Cọc Khoan nhồi', '#002AFF', 3, '2025-08-14 15:03:43', '2025-08-14 15:03:50'),
(15, 9, 'Bê tông nhựa chặt C19', '#000000', 1, '2025-08-14 15:04:54', '2025-08-14 15:04:57'),
(16, 9, 'Đất K95', '#FF8C2E', 2, '2025-08-14 15:05:03', '2025-08-14 15:05:07');

-- --------------------------------------------------------

--
-- Table structure for table `project_daily_logs`
--

CREATE TABLE `project_daily_logs` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `code` varchar(64) NOT NULL,
  `entry_date` date NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `approval_group_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved') NOT NULL DEFAULT 'pending',
  `weather_morning` enum('sunny','cloudy','rain') DEFAULT NULL,
  `weather_afternoon` enum('sunny','cloudy','rain') DEFAULT NULL,
  `weather_evening` enum('cloudy','rain') DEFAULT NULL,
  `weather_night` enum('cloudy','rain') DEFAULT NULL,
  `site_cleanliness` enum('good','normal','poor') NOT NULL DEFAULT 'normal',
  `labor_safety` enum('good','normal','poor') NOT NULL DEFAULT 'normal',
  `work_detail` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_daily_logs`
--

INSERT INTO `project_daily_logs` (`id`, `project_id`, `code`, `entry_date`, `name`, `created_by`, `approval_group_id`, `status`, `weather_morning`, `weather_afternoon`, `weather_evening`, `weather_night`, `site_cleanliness`, `labor_safety`, `work_detail`, `created_at`, `updated_at`) VALUES
(5, 6, 'PRJ000006-DL01', '2025-08-14', 'Thi công mố cầu M1', 1, 4, 'pending', 'sunny', 'cloudy', 'rain', 'rain', 'normal', 'normal', 'Đào hố móng\r\nLắp đặt ván khuôn', '2025-08-14 18:11:24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_daily_log_equipment`
--

CREATE TABLE `project_daily_log_equipment` (
  `id` int(11) NOT NULL,
  `daily_log_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `qty` decimal(18,3) NOT NULL DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_daily_log_equipment`
--

INSERT INTO `project_daily_log_equipment` (`id`, `daily_log_id`, `item_name`, `qty`) VALUES
(16, 5, 'Máy ủi', 3.000),
(17, 5, 'Máy trộn', 1.000);

-- --------------------------------------------------------

--
-- Table structure for table `project_daily_log_images`
--

CREATE TABLE `project_daily_log_images` (
  `id` int(11) NOT NULL,
  `daily_log_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_daily_log_images`
--

INSERT INTO `project_daily_log_images` (`id`, `daily_log_id`, `file_path`, `file_name`) VALUES
(5, 5, 'uploads/PRJ00006/daily_logs/file_20250814_131124_eb1d63.jpg', 'file_20250814_131124_eb1d63.jpg'),
(6, 5, 'uploads/PRJ00006/daily_logs/file_20250814_131124_9846e6.jpg', 'file_20250814_131124_9846e6.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `project_daily_log_labor`
--

CREATE TABLE `project_daily_log_labor` (
  `id` int(11) NOT NULL,
  `daily_log_id` int(11) NOT NULL,
  `person_name` varchar(255) NOT NULL,
  `qty` decimal(18,3) NOT NULL DEFAULT 0.000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_daily_log_labor`
--

INSERT INTO `project_daily_log_labor` (`id`, `daily_log_id`, `person_name`, `qty`) VALUES
(9, 5, 'Nhóm nhân công làm thép', 1.000);

-- --------------------------------------------------------

--
-- Table structure for table `project_daily_notifications`
--

CREATE TABLE `project_daily_notifications` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `daily_log_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_daily_notifications`
--

INSERT INTO `project_daily_notifications` (`id`, `project_id`, `daily_log_id`, `sender_id`, `receiver_id`, `message`, `created_at`, `is_read`) VALUES
(1, 6, 5, 1, 12, 'Daily Log \"Thi công mố cầu M1\" dated 2025-08-14 requires your attention.', '2025-08-14 18:11:24', 0);

-- --------------------------------------------------------

--
-- Table structure for table `project_files`
--

CREATE TABLE `project_files` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `tag` enum('WIP','Shared','Published','Archived') NOT NULL DEFAULT 'WIP',
  `is_important` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_folders`
--

CREATE TABLE `project_folders` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_folders`
--

INSERT INTO `project_folders` (`id`, `project_id`, `parent_id`, `name`, `created_by`, `created_at`) VALUES
(2, 6, NULL, 'PRJ00006', 1, '2025-08-14 11:24:45');

-- --------------------------------------------------------

--
-- Table structure for table `project_groups`
--

CREATE TABLE `project_groups` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_groups`
--

INSERT INTO `project_groups` (`id`, `project_id`, `name`, `description`, `created_at`) VALUES
(4, 6, 'manager', NULL, '2025-08-14 04:25:02'),
(5, 6, 'chưa phân loại', NULL, '2025-08-14 04:25:02'),
(6, 6, 'Tư vấn thẩm tra', NULL, '2025-08-14 04:25:29');

-- --------------------------------------------------------

--
-- Table structure for table `project_group_members`
--

CREATE TABLE `project_group_members` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('deploy','control') DEFAULT 'deploy',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_group_members`
--

INSERT INTO `project_group_members` (`id`, `project_id`, `group_id`, `user_id`, `role`, `created_at`) VALUES
(6, 6, 4, 1, 'control', '2025-08-14 04:25:02'),
(7, 6, 6, 14, 'control', '2025-08-14 04:25:13'),
(8, 6, 5, 20, 'deploy', '2025-08-14 04:25:42'),
(9, 6, 4, 12, 'deploy', '2025-08-14 04:25:47');

-- --------------------------------------------------------

--
-- Table structure for table `project_invites`
--

CREATE TABLE `project_invites` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `status` enum('active','revoked','used','expired') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `used_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_invites`
--

INSERT INTO `project_invites` (`id`, `project_id`, `token`, `expires_at`, `status`, `created_by`, `used_by`, `created_at`, `used_at`) VALUES
(2, 6, '1eed873467a619169fec02b9d1b41624', '2025-09-13 10:05:19', 'active', 1, NULL, '2025-08-14 08:05:19', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_kmz`
--

CREATE TABLE `project_kmz` (
  `project_id` int(11) NOT NULL,
  `embed_html` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_material_in`
--

CREATE TABLE `project_material_in` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(64) NOT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `warehouse` varchar(255) DEFAULT NULL,
  `qty_in` decimal(18,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(32) NOT NULL,
  `received_date` date NOT NULL,
  `receiver_user_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_material_in`
--

INSERT INTO `project_material_in` (`id`, `project_id`, `name`, `code`, `supplier`, `warehouse`, `qty_in`, `unit`, `received_date`, `receiver_user_id`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(3, 6, 'Thép CB400V-D20', 'CB4VD20', 'Công ty gang thép Thái Nguyên', 'Kho C1', 1000.000, 'T', '2025-08-14', 1, 1, NULL, '2025-08-14 15:07:12', NULL),
(4, 6, 'Ống nước PVC D110', 'PVCD110', 'Công ty gang thép Thái Nguyên', 'Kho C1', 100.000, 'm', '2025-08-14', 1, 1, 14, '2025-08-14 15:07:54', '2025-08-14 18:01:51');

-- --------------------------------------------------------

--
-- Table structure for table `project_material_out`
--

CREATE TABLE `project_material_out` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(64) NOT NULL,
  `qty_out` decimal(18,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(32) NOT NULL,
  `content` text DEFAULT NULL,
  `out_date` date DEFAULT NULL,
  `issuer_user_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_material_out`
--

INSERT INTO `project_material_out` (`id`, `project_id`, `name`, `code`, `qty_out`, `unit`, `content`, `out_date`, `issuer_user_id`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(6, 6, 'Thép CB400V-D20', 'CB4VD20', 0.000, 'T', NULL, NULL, NULL, 1, NULL, '2025-08-14 15:07:12', NULL),
(7, 6, 'Thép CB400V-D20', 'CB4VD20', 23.000, 'T', 'Thi công Mố M1', '2025-08-14', 1, 1, 1, '2025-08-14 15:07:54', '2025-08-14 15:09:32'),
(8, 6, 'Thép CB400V-D20', 'CB4VD20', 30.000, 'T', 'Thi công bản mặt cầu', '2025-08-14', 1, 1, NULL, '2025-08-14 15:09:04', NULL),
(9, 6, 'Ống nước PVC D110', 'PVCD110', 0.000, 'm', NULL, NULL, NULL, 14, NULL, '2025-08-14 18:01:46', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_meetings`
--

CREATE TABLE `project_meetings` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `short_desc` varchar(500) DEFAULT NULL,
  `online_link` varchar(500) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_meetings`
--

INSERT INTO `project_meetings` (`id`, `project_id`, `title`, `short_desc`, `online_link`, `location`, `start_time`, `created_by`, `created_at`) VALUES
(1, 6, 'Hướng dẫn sử dụng CDE', 'Sử dụng CDE trên hệ thống Bimtech', '#', 'Quảng Ninh', '2025-08-20 16:55:00', 1, '2025-08-20 16:55:20'),
(2, 6, 'Demo1', 'Text nhanh', 'https://www.facebook.com/', 'NCC', '2025-08-30 16:55:00', 1, '2025-08-20 16:55:58');

-- --------------------------------------------------------

--
-- Table structure for table `project_meeting_attendees`
--

CREATE TABLE `project_meeting_attendees` (
  `id` int(11) NOT NULL,
  `meeting_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `external_name` varchar(255) DEFAULT NULL,
  `external_email` varchar(255) DEFAULT NULL,
  `is_external` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_meeting_attendees`
--

INSERT INTO `project_meeting_attendees` (`id`, `meeting_id`, `user_id`, `external_name`, `external_email`, `is_external`) VALUES
(50, 1, NULL, 'Test User', 'dovietduc18utc@gmail.com', 1),
(122, 2, 20, NULL, NULL, 0),
(123, 2, 1, NULL, NULL, 0),
(124, 2, 14, NULL, NULL, 0),
(125, 2, NULL, 'Hoàng Vũ A', '', 1),
(126, 2, NULL, 'Mai Hoàng B', '1213@gmail.com', 1);

-- --------------------------------------------------------

--
-- Table structure for table `project_meeting_details`
--

CREATE TABLE `project_meeting_details` (
  `meeting_id` int(11) NOT NULL,
  `content_html` longtext DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_meeting_details`
--

INSERT INTO `project_meeting_details` (`meeting_id`, `content_html`, `updated_by`, `updated_at`) VALUES
(1, '', 1, '2025-08-23 15:17:09'),
(2, '<p><br></p><p><img src=\"data:image/png;base64,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7TVVVlY6IzrGx8clSYcPH5YkrVixwtkGAAAAAHCKO/g41wIPLXTo4b5l7e7du/X5z39eX/nKVzxfhiORiD7ykY/os5/9rL7whS/oS1/60ozRCA0NDZLk+XI6m49+9KOqq6vTj370I+3du9esliRdeeWVuvPOO3XvvffqS1/6kh5++GGziSorK/OOprAsy/N45cqVkqSLL77YCXrs5ROf+IQ0HYzMxw9+8ANNTk7qqquucsouuugi584is7nmmmt0991367777tOXvvQl5xhyef31180iTUxMeMIMTc/R2r59uz772c/qS1/6kh588MEZX/7t52gGTZoOSUzFxcVatGjRjHP2yCOPqKKiQmVlZZ72o6OjOft5O5YuXSpJ6uvrM6t02WWX6e6771ZlZaX+7d/+TYcOHZKm76xz/PjxnGEMAAAAAODctqChh/uWtU8++eSM+TvLli3T/fffrw996EOKRqM6ceKEXnjhBb388suedrZkMmkW5WTfxtWcnmH77Gc/qz/8wz/U8uXLNTU1pQMHDmjPnj1mM0lSOp02i2b1+uuve4Ie9/Liiy+azWc1ODioQ4cOacWKFaqurtbNN9+ssrIydXV1mU0dkUhEX/jCF3TjjTcqFospmUzqhRdeeMfTQW688UbF43FdcsklCoVCeuONN/Tzn/9cAwMDZlNJ0sjIiFmU19DQ0IxzZS9meJLJZDyP3wn72GtqaswqffCDH1QoFNLf//3f6/vf/75THg6HZw3DAAAAAOB85p7S4p7qcq5Y0NDjdD760Y8qGo3qO9/5jr74xS8613Qwv0jbUwsuueQST3k+//7v/659+/Zp1apV+vSnP+2pu/zyy7Vy5Ur96le/0r333quHH35Y3/zmN/VP//RPnnaaDjyWLFliFkvTIxTc7KDFsixP0ONenn/+ec82c/GjH/1I4XBY119/vZqamnTixAn9y7/8i9nM8Xu/93vOdUg+//nP6+GHH9aTTz6p7u5us+m8bNq0SYlEQvfee68efPBB5xosU1NTnnbZbFaSPFNybLlGuqTTaRUVFc04V/bywx/+0NxkwRw7dkyWZeW81sr3v/99fetb39LPf/5zT/mKFSsUCoVyjg4BAAAAgPPZokWLPFNa3FNdFi1aZDZ/V5zV0GPx4sWSNGPkwsUXX+x5/OMf/1iJREJNTU1atmyZpy6fv/mbv1Fv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W2/13Or48ssv1x/90R9p+/bt+qM/+iNdfvnlTp3bNddco09+8pPO42uvvVZ33HGHNm/eLElavXq17rjjDl166aVOe7tfezSLHWrcdttt2rRpk9atWydJuuGGG3TBBReouLhYt912m2cECAAAAADMhtADmIPGxsacIzxMBw8edL6sL5Tx8XFJUigUMqskSS0tLUomk3rsscf0z//8z1q6dKmuvfZaafqCtP39/frGN76hxx9/XC+99JIuuugi1dfXS5Le//73KxgM6hvf+IYee+wxvfzyy074sGbNGr33ve/VCy+8oMcee0yHDh3S5Zdf7mzr9tZbbykYDDrBzOLFi5VOp1VbWytJuuCCCyRJv/rVryRJQ0NDeu655/TYY4/pO9/5jkpLS/U7v/M70vQol8nJSafv73//+3rrrbc0OTmpxx9/XN///vedOgAAAACYDaEHMEdzuVbHyZMnzaJ3rLKyUsrT95VXXqnS0lL99Kc/labDh2PHjnmuAbJ3716l02lJ0gsvvCBJWrlypVPvnjbyi1/8Qj/72c8kSevXr9fo6Kj2798vSfrpT3+qyclJNTU1Oe1t3d3dymQyWrFihSSpvLxcx44dU1lZmTT9HJLJpHMcL774ot566y1pOtRJJpMqLy939QgAAAAA7xyhBzAHBw4cyDu9xG39+vU6ePCgWfy2NTQ0aO3atTpx4oTefPNNs1rV1dUaHx/XyMiIUzYyMqJFixY5j7du3apbbrnFuTiq209/+lMFAgFn2ogdWkjSokWLNDo66mk/Pj6eN5w4efKkysvLnVCkq6tLpaWlWrx4sRYtWuQJbS666CJ9/OMfd45p8eLFrp4AAAAAYGHMKfT49t3/ZcYCnE/279+vdevWzTp15aqrrtK6dev0wx/+0KyaNzsMuPrqq9XX15d3SkcgEHCudWEvF110kVKplDR9bY3Vq1fr+eefdy6O6nb8+HH9/d//vfbs2aNQKKSWlhZdc801Tt8XXHCBp+9FixbJsixPH7bR0VGVl5ervr5eyWRSb775piYmJrRu3TqVlpZqeHhYklRfX6+tW7dqZGTEOSZ3aAMAAAAAC2VOoQdwvvvhD3+o/v5+7dixQ83NzWa11q1bp//0n/6T+vv79ZOf/MSsnjc7DPjWt76lH//4x860EFM6nVYqlXLauxdJqqio0OjoqF577TVzU49XXnlFTz/9tN566y0tX75cmu57cHBwRr/5ApihoSFFo1FVVFToxIkTkqSxsTFdcMEFKikpcaazrFy5UsFgUM8995zRAwAAAAAsLEIPYA5Onjyp++67TydPntSOHTt0//33O3c9uf/++3X//fcrkUjovvvu03XXXedcwPNM+9nPfqZgMOhcuFSS1q5dq4aGBknS5OSkSktLnet22BcLtV177bW66KKLnMfFxcWampqSJPX09Ki6utqpD4fDam5uznvr2EOHDikcDquystIJOIaHhxWLxZROp9Xd3S1NT7+R5EyDueiii/JOmbFls1kFg94/V+vWrXPuNBMOh/WBD3zAuZaJuw4AAADA+YvQA5ij/v5+ffrTn9b//J//U4sWLdLNN9+s6667TtlsVt/97nf153/+57rqqqu0fft2ffGLXzwjwcerr76qRCKhLVu2aMOGDRoZGdG//du/qaamRrfffrtuv/12vfe973UuILp3715NTU3pj/7oj3T77bcrFotpYmLC6e/kyZP67d/+bd1+++3avn27wuGwc1HUvXv36rXXXtPmzZt1++236xOf+ITq6+vzXn/jzTffdO668stf/lKSdOTIEQWDQSWTSafd/v37deTIEf3Wb/2Wbr/9dl111VVKJBJOfS6dnZ3KZrPavn27tm3bJkm69NJLtWHDBmd9zZo1uuyyy2bUAQAAADh/BeLxeLatrU2tra1mHVBQYrGYOjo6zOKzqra21gk8+vv7dd9996m/v99sBgAAAAAzNDQ0KBQKKRQKKRwOe37mKguHw5qamtLIyIhGR0f16quvml3Oybu133eqq6tL7e3tZrEHIz2ABeQOOtwBCAAAAADg7CP0ABaYGXzkuvApAAAAAODMI/QAzgA7+Pjbv/3bBbmFLQAAAABg/gg9gDOkv7+fwAMAAAAA3kVcyBS+cS5cyBQAAAAAcHZwIVMAAAAAAHDeIvQAAAAAAAC+ROgBAAAAAAB8idADAAAAAAD4EqEHAAAAAADwJe7eAt9YiLu33H333WYRAAAAgPPYV77yFbMI54i53L2F0AO+sRChBwAAAACgMMwl9GB6CwAAAAAA8CVCDwAAAAAA4EuEHgAAAAAAwJcIPQAAAAAAgC8RegAAAAAAAF8i9AAAAAAAAL5E6AEAAAAAAHyJ0AMAAAAAAPgSoQcAAAAAAPClBQ89tm7dqm3btnmW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zSYFJVfwRNgBoBAdP35c3d3damxs9ExfKS0t1YoVK9Td3f2uBR6SpHg8np2cnMz+wR/8AQtLQS+f+tSnspdccolnmfr75c5y/fXXv6Plphuvy/5r+6XZ//GZ92evv/767ItfXps98P+7OHv99ddn2z55TfbH/2ND9j/+QcuM7eazHD16NPvQQw/NKLfrTp48mT158mT26NGjeeu6u7s9dd3d3U7d9773vezJkyedupMnTzr7O3r0qFOfax979+516vbu3ZvzWB966CGnjftYzL7dx2D2bR6/2be7H7Otu/+9e/d66tznwTwu9zG4z4fZ3l7cdd/73vc858o8B7Nt5667/vrrZ/TlPmbz+Zj92fsy9+/ez0nX87P7vn76udvP0T4G92tiHqvZv3l+3Mfn3tb9fOx9u/uc7bWd7X1sHpP9HO3nYm9rvl/d29jn/XTv1XzvC3M79+uYa8n1XrB/r+w23d3d2e7u7jm992f7HXLXma+te//meydfH/l+/83loYcemnEeuru7s9/73vecfbnr3Pt29+1+7c3+8m2Tr9xezPetee737t3r/M7le827u7uze/funfF+yfW7ai75fhfs82Kfb/N37/rp52bX2/vK9/rnek3dj+3nbD4H+5yZx+M+FydzHF+u95b9e2i+h8znZO7bXMzX7HrXv5nm8zSPy6wz339Hjx51zsV8+3Ifk/s8mu9V92KfD/P52P273392e7vfXO9Bd725n1zb2du6n4e5rft52q+J/Vra2+Z7rVhYWM7f5eabb87ee++92ZtvvtmzbrZ7p4v7+97k5GQ2Ho/PuhB6sPhmOdOhx1/+p83Zb997ZfbGG67LXn/99dkftW/I/lv7huz111+f/f3rr8t+679emf3Sn26Zsd18FvsDnFnePf3lx37s/rD00EMPeT4c2R9gzHZ2P+4PNu4PLfaHI7vO/lB2fY4v4/aH11zHavY7377tD3Fmn/YHUffzMfuxn7fd1j6GXOfP3t7ezn18s51T8/iOuj4s223ND4i5tsu1uNubr12+xex37969nv3ner3tD8nubex92du4z6v52tnnwu4z33G625r7NF/3k8aXBPc5N8+F+T5278f9ZcY+dvfxmov5fI7O8l6d7X0x23bmYr5m7veMfTzu5zGX97773Ln7N897vm3M43DXm233nub3314emkfoke81tLdxt7Ofg/nazaUv92Luw32sR11fps3Xyu7bfZ5n24+5mK+J+/za/eR771yf43fOfH3cx5XvdTLPnbs/9/HlOh5zf+Zi1n9vHn9TZjuP35sl9Nh7mr977v7N9193jn8f8vVlnjf3a2Ee+95Z/obbx23/NLfZ6wo9TvcedL/G7ucy23bmsdn9uI8v12tinw/3OWBhYWExFzvsOFOBx/VzCD3+673/Lftfdtyb/bO/uCf7Z39xT5YLmQJzUFmSUsPipJ57fYmszKlbtfSfLNaJibAkKauAfvRGtS6pHlVVScrY+p25+uqrtXLlSmfuuKavwbBy5UpJ0p49e/TAAw84db29vc76xo0b9Y//+I/OY3cfuTz33HPOek9Pj6qqqiRJzc3N6uzsdOoeeOABDQ/P7zomc+375ZdfdupycT+Hzs5ONTQ0SNPXw7CvT7Jnzx7P8eU6f7alS5fq2muv1Ve/+lWnLN85tV8Ld923vvUtZ33Hjh3q7e3Vnj17pOljqqyslCQNDQ3N+rxMx44dU0VFhVl8Wg888IBn/6arr75aGzdunPUaHsPDw85ztPu4+uqrnaHY7n7dr+tsNm7c6DlX7n5t7vre3l4tXbpUOs372Dwm+7W3+x0eHs55HmaT772a731hy7edW673UG9vr9asWSNNn4Pm5mZdd911M85tvvf+bL9DDQ0NnvPq3q/bbO9ds/+38/uv6ddq5cqVM16P072G7ufd09PjrI+OjjrvEdvp+nIbHh522ldUVGh0dNQz3WDPnj2nfc3fjrn8LuR7nWzu3wfz9XG//sPDw857azbuc2y/B9xOdzynM9e/KbO9XiZ7Goj9GuX6u7d9+3b19vZ6+nfX79ixQxUVFTP+fcjXl1nm/h297rrr9NJLLzmP9+zZc9q/4T09Pbruuuucxw0NDZ7XVnP4u/PSSy85x/vyyy87+5xtu7P9NxUAzrSh5JQGEpPqGxnXUHJK9iU8yovDqooWcfcWYC5+a/mQDo9GNTwRccpeOlah/3Mk5jwemgjr0MgivbduyClbKLm+YLg/hDz44IPOhd3sMMSW6wPsXBw7dszz+NVXX/U8fifMvq+99lrn+K+99trTflC0mcf06KOPOv3YX9iuvvrqnOfPtnHjRs+HRlu+czpbX5oOWNwX2tP0MdgfPp944ok53fHhscceU09Pj5544olZL3iXi7l/t40bN874Mn067uc8OjrqqZsP8xyPjo7m/UI2NOT9PTK3dausrPQ858rKyrz9zpf5Xs33vjCZ25ncx7ty5Urny/uePXucczzbFwvzvZ/vd6iysnLWc+eW772rHPubK/drc+211+a9UO9sr+GOHTuc8o0bNzrb3HPPPWpoaNATxkUfZ+vLraenR0uXLtX27dvV09Ojl19+2Xnsfp/P9TWfD/M1me13YS7yvf6f+cxnnLpc14+wXe266OzZuE6G+2/KXF8vGe/RhoYGT3jr7sO2dOnSGX9L3MwAyparL83h79/GjRs959H+dyifB1z/gWG/78z3hubxHjR/T2fbLtd+bPN5TQDArbS0VBdeeKFef/11vf7667rwwgvPyi1qq6JFqikrVt3iUlVFi1QVLVJJJKRw6FTcwd1bgNNYuXhcm+pGtfdIlTLZU6M8JOkDK4/rd1YNOo8z2YB+drRKzctHtHLxwt6KKdcHJ/vLzIMPPqihoSHn4m7m/wK5/8dsLv97lo/5gSfXMb1dX/3qV53jv+WWW2YdheC2Zs0a50Poo48+qs7OTqcP80N1Ps8995w2btzo+UIw2zk1+zLPy0svveR5Lrfccovz4fKee+5xvvTN5WJ1DzzwgG655Rb19PTMKSjR9Id19/k0Pffcc+/oYpJmIGX+T/tszH1WVFTM+JCez2zvY/fFDe1ltsDg7ZrtfTEfw66Le9qLHYpdffXVqqioUEVFxaxfUt3vfZ3md8g8X/nM9t413+fm70E+5nPNJ99ruGPHDlVVVTll7v9J1/SX+ltuuUUNDQ3O+crXl2nPnj1qaGjQ0qVLtWfPHr366qvO45dffllawNfcZL4m8/ldyGW2198uy/d7f/XVV+vOO+902rlHvZ0Nc329ZLQ1A498f/dyjbiy5boY6mx9ne7v33PPPTfjuZzOSy+9pB07dqi5uXnGKA+9g/fg6bY7F/6mAvCXsrIyJ/AYHx/X+Pi4E3zkuqvLQnr54BFNTuUfbR8cSEyaZQCmFYcy+sT6o/rHV5dqIu0dGBUInFrcElMhfWP/Cv3RhsMqDi1MoGgPLXV/6bWHomv6Q5j7f5Xd/5vT29vrGTrrXp+Pnp4eNTc3O4/n8oV9rnp6enTrrbeaxXm5933ttdc6X04qKyudLw1XX32186Us3/lzs+8q4R7qnuuc2n25t3eflz179mjjxo0zPkCaztRUF3u/9hfVXF+aX331VeeD/umO0/TY9JQHd7/u/3mfTW9vr+d1tvuY7X8bbbO9jx977DGtXLky53NdaPneF/NhP1/zPWi79dZb1dnZqc7OTs97S7O892f7HTLPe779zvbePd3v/xPv8Nahs72GVVVVnv+pt6f0mOypLrP1ZdqzZ48qK0/dRm/P9FQWTe/D/oK3EK+5yXxN5vO7kMtsr7/bcJ6pLmvWrPGExO/ktZyv+bxe+cz2d2/Pnj2e/q92TanR9P5feuklZ0THbH2d7u/fyy+/rGuvvdZ5PFf27569bnq778HZtlvIv6nv9PcfgD8sWbJEF198sRN42MbHx3Xo0CFdfPHFWrJkiWebhbRq+VIdHTiRN/gI1i0+88NNgEL1WyuG9Mv+Cu07Vm5W6dFfNOjRn8/8AN47XKru4+W6avns0yDyufPOOz3DSjX9v5kVFRVOWVVVlTMH91vf+pZnaLP7f3Puuecez3b2l6T5euCBBzQ6Ouo5ptmmebz00ku688475zQ6wez7idMMw5Zr6PFLL73kfDmxv8g/8cQTuvXWWz3HZ54/0549e5wREDt27Jj1nH7mM5/xDGF2z6W3+3G/hvY5cA8zbmhoOO31VdxD+q+99tq8o1/+8R//0TmePXv2OB/gn3jiCb3nPe8xm0uuD/p33nnnac+1yQ6I7H3MdarMPffc43mdm5ub8z4n0+nex+YxzWc6UGdnp6699to5bTPb+2I+zPfQE9PvyQcffFCjo6N6bPr6NKOjozN+h+z27vf+bL9D5nm3wzZ7W7vtbO9ds3+d5vf/7cj3Gt5zzz2ec+Ue3eKezibXdSfy9ZVLb2+vp0/3NUM0j9d8z5496u3t1RPTU222b98+47Wzma/JfH4XcjFfnydcr7+7rKenx3nd3e97+71mtztdIGu+d0ynqzfN5/XKZba/e3v27PH0f+edd84YUfPAAw84r52m//3K1ZdyHKv77587QLEXMyDMxX7vuP8tcZvre9A023Zn8m8qgPPPkiVL1NDQoJ6eHk/gYUskEurp6VFDQ8MZCz6KiyJaXhPTWHJcR/tn3ho3EI/Hs21tbWptbTXrgIISi8XU0dHhKXtxx4izftNTV3jq5uK3V5zQTU1vScqaVbMKBQP6x1/X6kcHq80q33jiiSfmNHR3obiHYJ9Ltm/frve85z2nDTH8yv5Q775g3pl29dVX69Zbb31HXxQLybn63kd+O3bs0LFjx5wAAP70bvz9OxPOt7+pABZOaWmpmpqa1NPTo+PHZ4YNbnY40t3dnTMcmY/XXnvNWe/q6lJ7e7vzeHB4VJNTKS2v/U3AwoVMgVnsPRLTn//rJfrzf103r+Xu/+8SXwceDz744Jz/t8nvrnVNMzjfXD19J5hcQ7LPpFtvvXXG/8gD5xL3FBn407v19+9M4G8qgLdrfHxc3d3dpw08JOn48eMLEnicTnXlqSnh7hEfgT/+kz/NPvrIXzHSAwXvTIz0wCk7duzwzF0eHh4+6/8jdK78b/ejjz7quYjjSy+9VPD/yzcfTxjTg5577rkz/uXuwQcfnDEf/XwaWXOuvPeB89278ffvTDjf/6YCKHyzjfSwTU6ldHTghFYvX8r0FvgHoQcAAAAA+NtcQg9NBx8Hjx5jegsAAAAAAPCX4qKIVi1fSugBAAAAAAD8p7goQugBAAAAAAD8idADAAAAAAD4EqEHAAAAAAAoeGkro4mUpaHklIaSUxpITBJ6AAAAAACAwjQwNqmBxKllbDItSSqNhFQVLVJNWTGhBwAAAAAAKEw15cWqKTu1VEWLVBIJqSQScuoJPQAAAAAAgC8Fh5JTZhkAAAAAAEDBC5YXh80yAAAAAACAghe0LMssAwAAAAAAOOf1jYybRR7BowMnzDIAAAAAAIBzXk1ZsfpGxpW2MmaVJCm4evlSswwAAAAAAOCcFw4FVVNWrKHxVM7gg7u3AAAAAACAghUOBVVVGtFEytJEynsJD0IPAAAAAABQ0MKhoEoiIaWtjNx3qSX0AAAAAAAABS8cCqqsJCJJTvBB6AEAAAAAAHyjKlqkwHTwEZSkqakpBYPkHwAAAAAAoPBVRotUXhw+FXocPXpUFRUVZhug4B0ZLTaLAAAAAAAFKJPx3p1laGjI89gUDgUVTEyk9MsDL+sD1/wHsx4oeHsOlppFAAAAAIACdPLkSc/jV155RZKUtjKaSFkaSk5pKDmlgcSkBsYmNZScUmjL+9//34eP96vl/9/etSy3jVzRAxIgSEl0JI/lSUaTVM1UFvE/+AOSv0kWUzUb/VP+IbPMB2g240rJisuirQcJkgC6AWQBNNVsNkA87rXFFM7GNmmiGvd9T98G/vZX+L6P6+trSCm3LtSjxyFgMpng+vp667N//8fB67Mxvj8V+Oevv9/6rkePHj169OjRo0ePHj16PH+kaYogCDCbzZBl2ebzeOhjvo4h0wwOAN8dYjr2cDxycey7mHhDOJeXlxkADAYDvH37Fm/evMH5+bl+/R49evTo0aNHjx49evTo0aNHj6+Ou7s7XF39il9++Rfi+OnVtGXYkB42yCTF/Vpg6rsYe0Pz61YIQgEAm9fIdIVMUiwiibOjkflVa8yCCBO3eMdvmmEtks13Mskg03QjD2/gwB0O4BZ/2jBfrvDf2zv85Yfvza9a48PjGmdHIzK9hCLBWiTkcjw/6f5MDZnk57bu1wIyyWUv0yezdQcOAGBSyKKJTK7evSfVyyyISP1F+SCFHBU+PK7xh9+1O/YTxQKREIiFRBTnvhyKBEmSwB0O4HsuAODF8RFGngt/1M7Pf7v5iB8vvjU/bo0gFJv3dlOByr4VZkGEs4lXGkdMyCSFTDPIJIVIs9wnMmzFJ5lmpPZI7S/U+QDFE7op49j9KsbUdyv1onRh5go42/FJ5Qlq26H2l1AkkEn6rPVS5tOmLsxcoXK2+Tt0jI023Nx+xqvTF63joAmueofSFsNC7sruQ5FAFDpQ8UrJvm7OpradTw9zjDwXL46PzK9agSNPU95zFAvINMP9Ygkny+upSAjEscT0OLf36fGksTyobYdFLyuB8yndGsviTl2Y8SkUCWSaYVzkh6r4VBdNa4l9+PQwRxQLXLz+xvyqFWSSYhZEoIy1QSiQAZg2yFmmLsz4FIoE5yc+mRy72o6JKBb49DAn1cv9WpDajnpV7L5YtqMLo9eeeMO89ytsp61eSkkPjiBOXeB2KQCUgGXRMKAw+FAkm+CDBkm5DFEs8O7mI2mjwNFYUwegJqRMmS6QYdM0qEKqraHbcPXuPX64+JasIH3OxJHCvmbGRmpE4um4m18QGSPPhe958Ece+RqpCx8wyJE6ecFS5Jb6RYF9BVIQijyWEfkLGJpr6uIMFjl2hVqjrguZKH3sEuA2XeiQxXnTMh9sg5vbz2SFD2rEiTag8kHlF4tIO4ZrkH1t8zbVGhU4Yhk1MXO/ijcFZVPIYkNCJzZCkSAUCU4LH9TJvrZYhKJRM7MP1M0CGGynaX2r8vVitd7J3WozAq6Pi5cnTz/qCI44QZ1jqHN1nXrZ5hcqd9vyNscaqcke6lgmmXpMU45NdaGDeo3qepQ1DwchRb3Gh1WcE1J+HoeULlQd5Q53N4aqoHywTT9oJT2oFY0isXoDhyw4ViUEZeQ6a5R/nu+8wTB2JWSbw3QBR2KlXiMYmoSHVYyxVkSpIlUaO9JVujBBvUbqAA6GNXLo+uYuwNQfNCI1qsCxxude9ICowNWT8VqmkEma275G9lUl5H2gWKOOTw9zAMCrU7o3fVGvsS2xbssZqjAKQoHTo1EnXehou8YqUPsMRzPTRNcqZ5i6gJYvch/prg8dTdZYB/PlGkBGmmeo16g2FMrkuKOLkjpK//2XXmMbHKLPVG1KKGKjamLjS6yxK+bLNWIhnnWeCYsNrqnvbjfTlum+OjbL0XOpNVLVpFEscDO7I/WZIBQQaUayRpmkCCKJdbFxXTYp0BSUa4Sma0pS4bkRH7acIdP8s5Pi2RptdKGj7Rp3SA9Zg8VsihnDZML9WmDiDnaCDVoedziUQpQyiIFgp9XWLIQiP+ogtTExW2FUF02mRuqAI3h32S0rQ5sdPbMoyguivIvO4OB4PII7HJARPtTFBAcZRb3GurFC+YZZFLlDBzLNtuLU/SpGU13vQxCKvWtsAup4BgCzRUS6G1V1FMVMxPlnu0WqSbxSrzE4gAkcbqLQ9A1FhJtFKiry9yE0cBwbH9Tkuqqnpr67TTJ1IF+r/LANOOpSjlxDoRs9hy/DGL7nYhWGAJytTYm266a2cQ4/pI5nsmKTtApmnELhG+pzdzgg622UH1LqhjqOc9TOTdZo5nClCz1nuAOHPFbUPaJRFyqenbeYVCjDlyY+ynwDRQ9uq6U4CKRQnc6wrNGGLdIjLF7xQmksTQkPJUiTJVKEhjJ6pQhTqG3AFbRfnU5bJyYTX0s3VsMuIZhUYqFcY5OAWBfUCZWDjPrwuLYGRDupkUPt9PgjD77nbc7qKlAXO4eiG2rfVnLcF6uqGmkTh9BYUx+hqEse1YUqJKa++zQ66eSfKxtt2ryBqJHRQd0QgsFvKHzbLFCDSG6u13Qn1AZV8FDZDxhiJBfp0VRu1jyuNQyhlr+aXLcMIcNkxv+TblQeXyzX+b+F2Iy+KmLD9zxMxv6zb4TBpBvq5roq7pqxqqyZ1n8rD4Ck4Fgjd3NdFqtQMVlmguO+OXRDTc7c3H4GAHLdmIS46RtNZMJFfNTVzYb0+FJNdZVBY8+UBochczSsV+/e47vXL8kIDw7nUA7sDvJdZ1nx3ACUBH0T1IkvFAkWkSS95s3t58rxzzagbIqiWCAUCe4WK4wG2YbUiGOxITHa7PTMOk702NBmEqUKHDtvXddoFkMo7FL5DkpiVRNQJ1Mw+GIUCyxWa/KR4yY2qXKHWZzqO9KS+MGt1MQMGHSDr0R6lOVyvSDSC1Tq+5Ytd2+rUNUYtQW1bsoIBRWrtnK5ZcTblsc5dENdq9Wxyaag1o1uk7vExtPUZZOJjbpESl1w+E0oEogkJX3uCvVDgFU9qfJ2Xd+oArVNcvgNxzUpajVZNKtq0i8UCdyBs6WPLnLlaq7HhJtJHLppQ3yU5XIUPWBYHOWiqoOo71u3pX36di4vLzOq5lIJTqbZZhzIHQw2O5/5v/c/v8EGaiGBqammnvBAx4LELITU36EVTW30YcJGcHUFJZmA4i06i+W6UTDYB/P5JftgFkIoiiGd1HD9MSauQ2ZDHMQedSEGhkmCOvet/MM88mAWQyj8haMp6uLfZQiIj7Z8ephjejQhK0BRct9WfWjJF3uKU+qYwTEx05WIM8GxY63uG4X82+pDh03fXcDRvHHENcrGWhV3i2JqRukCDXZBbeC470PQd9fmzcznkZBwhh6yRGxNbJhTl03AUftSN+tg0HebaQ8zfyhSXD0o0SloJ0obor7vQ9C30s3F+cvKmsCmD1kyQcNx3/cMz5LUp1IowHHfZdM4O/qw1Lo2GzmU+65DdDn/+OnnbN9/UpAVbBC0IiiIJM4mHrmhUQqHg/DgmCKoCqhKHztFaclrdcfa634oi+6ynacuoH6OB4iLT5Q01WYRhGJ0VX81XNUOT1MSpQ6qbKgtqK/Ztfi0QRFxKHyF4sgDdVMdMhy/4WjUqV5Vq5Kuiuljd9hJHzoOZSqD2obmyxViIVtN4ZTl9FAkOCl8p60+TFDLUjI0wRy5rCmZu1OYGtNM3sDB/Vrgj2d0sZIjDh0CQVyHMDRzuu0oip67qBtMMNw3Vx1IbUO2mnrHP2o8WFeBo5fguCaXDVE2rVHxZso//+k7wJJDUEMfJuo0rU1BLUsO36HOZTJJcT17gDt0cHJ8vPXssib60KHsnNKGqO8b2ptiyq7p/P2nnzM1kqaCidTfsoHNRF6tnR3qZpUjoIQMR3k4mrYPj+tN02YGE+w5ClQG6uTJIUuOwpPiyFG0eVp6XgDFqbM5gqJPakyPJ3DgNN7dkQn9a8aoAz4YkidaNAYKVTFLEX9tg7wJrqaaWpbUkwRoQBhKCxFra9pEmpHuwOCAZEn9/JZ9pIfZJMAoSmHJ6YfQsILhmlyNmz6mr3zEbBJkyQ6oDdT3zVHIc+RxjomU324+4uL85c7EhoKN2KgCV81KbZccsqSyS+Ujj6sI8+Uapy9OSBo3MMmSus7isKGu1zTziEwzxLFAsFzi9TdnZLbEJUvK2kAy1epNCB+bPswcEiyXteNWHXDJkjr3VMnyf2cLXy0FhEN6AAAAAElFTkSuQmCC\"></p>', 1, '2025-08-25 16:46:58');

-- --------------------------------------------------------

--
-- Table structure for table `project_meeting_notifications`
--

CREATE TABLE `project_meeting_notifications` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `meeting_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_meeting_notifications`
--

INSERT INTO `project_meeting_notifications` (`id`, `project_id`, `meeting_id`, `sender_id`, `receiver_id`, `message`, `created_at`, `is_read`) VALUES
(1, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 14:19:41', 0),
(2, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 14:19:41', 0),
(3, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 14:19:41', 0),
(4, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 14:20:02', 0),
(5, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 14:20:02', 0),
(6, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 14:20:02', 0),
(7, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 14:21:19', 0),
(8, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 14:21:19', 0),
(9, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 14:21:53', 0),
(10, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 14:21:53', 0),
(11, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:02:05', 0),
(12, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:02:05', 0),
(13, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:02:05', 0),
(14, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:03:27', 0),
(15, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:03:27', 0),
(16, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:03:27', 0),
(17, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:08:26', 0),
(18, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:08:26', 0),
(19, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:08:36', 0),
(20, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:08:36', 0),
(21, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:08:36', 0),
(22, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:17:18', 0),
(23, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:17:18', 0),
(24, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:17:52', 0),
(25, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:17:52', 0),
(26, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:19:08', 0),
(27, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-22 15:19:08', 0),
(28, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 11:22:09', 0),
(29, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 11:22:18', 0),
(30, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 11:22:18', 0),
(31, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 11:25:31', 0),
(32, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 11:25:31', 0),
(33, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:19:43', 0),
(34, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:19:43', 0),
(35, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:19:43', 0),
(36, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:34:51', 0),
(37, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:34:51', 0),
(38, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:34:51', 0),
(39, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:34:52', 0),
(40, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:34:52', 0),
(41, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:34:52', 0),
(42, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:34:55', 0),
(43, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:34:55', 0),
(44, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 14:34:55', 0),
(45, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 15:14:15', 0),
(46, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 15:14:15', 0),
(47, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 15:14:15', 0),
(48, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:11:51', 0),
(49, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:11:51', 0),
(50, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:11:51', 0),
(51, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:12:57', 0),
(52, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:12:57', 0),
(53, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:12:57', 0),
(54, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:17:43', 0),
(55, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:17:43', 0),
(56, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:17:43', 0),
(57, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:17:57', 0),
(58, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:17:57', 0),
(59, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:17:57', 0),
(60, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:36:38', 0),
(61, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:36:38', 0),
(62, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:36:38', 0),
(63, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:42:32', 0),
(64, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:42:32', 0),
(65, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:42:32', 0),
(66, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:42:57', 0),
(67, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:42:57', 0),
(68, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:42:57', 0),
(69, 6, 2, 1, 20, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:46:58', 0),
(70, 6, 2, 1, 1, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:46:58', 0),
(71, 6, 2, 1, 14, 'Bạn đã được thêm vào cuộc họp: Demo1', '2025-08-25 16:46:58', 0);

-- --------------------------------------------------------

--
-- Table structure for table `project_members`
--

CREATE TABLE `project_members` (
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('owner','manager','contributor','viewer') NOT NULL DEFAULT 'viewer',
  `added_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_members`
--

INSERT INTO `project_members` (`project_id`, `user_id`, `role`, `added_at`) VALUES
(1, 1, 'owner', '2025-08-11 09:51:11'),
(2, 1, 'owner', '2025-08-11 10:17:16'),
(3, 1, 'owner', '2025-08-11 10:18:26');

-- --------------------------------------------------------

--
-- Table structure for table `project_naming_rules`
--

CREATE TABLE `project_naming_rules` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `project_name` varchar(64) NOT NULL,
  `originator` varchar(64) NOT NULL,
  `system_code` varchar(2) NOT NULL,
  `level_code` varchar(2) NOT NULL,
  `type_code` varchar(2) NOT NULL,
  `role_code` varchar(2) NOT NULL,
  `number_seq` int(11) NOT NULL DEFAULT 1,
  `file_title` varchar(255) NOT NULL,
  `extension` varchar(10) NOT NULL DEFAULT 'dwg',
  `computed_filename` varchar(300) NOT NULL,
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_naming_rules`
--

INSERT INTO `project_naming_rules` (`id`, `project_id`, `project_name`, `originator`, `system_code`, `level_code`, `type_code`, `role_code`, `number_seq`, `file_title`, `extension`, `computed_filename`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(9, 6, 'CKP', 'NCC', 'ZZ', 'ZZ', 'M3', 'S', 1, 'TruCauT1', 'dwg', 'CKP-NCC-ZZ-ZZ-M3-S-0001-TruCauT1.dwg', 1, 1, '2025-08-14 15:05:52', '2025-08-14 15:05:52'),
(10, 6, 'CKP', 'NCC', 'ZZ', 'ZZ', 'M3', 'S', 1, 'DamNhip1', 'pdf', 'CKP-NCC-ZZ-ZZ-M3-S-0001-DamNhip1.pdf', 1, 1, '2025-08-14 15:06:14', '2025-08-14 15:06:14');

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
(3, 'Pro', 3000000.00, '150 GB chung toàn bộ tài khoản\r\nTối đa 10 dự án\r\nTối đa 12 thành viên\r\nBao gồm tính năng gói Personal\r\nThêm tính năng Organization Members', '2025-07-30 09:44:03', '2025-08-11 03:25:53', 150, 1, 12, 1, 1, 1),
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
(20, 'thuyhm', 'Hoàng Minh', 'Thùy', '1992-01-10', 'Hạ Long', 'NCC', '0982153092', NULL, 'user2@bimtech.edu.vn', '$2y$10$qrbU2c6O5gei.jSZUFnD4eDq0YJuwY5yS8VMKCwDUPIRMNctr9KwO', 'user', NULL, NULL, NULL, NULL, '2025-08-07 11:22:43'),
(21, 'user3', '1', '2', '0000-00-00', 'Hạ Long', 'NCCC', '0888121496', NULL, 'user3@bimtech.edu.vn', '$2y$10$zOX6qi9eeUQZ4M99RSCkoOyK.MlL4N6THDy2nTPfLGafke9yvTZM6', 'user', NULL, NULL, NULL, NULL, '2025-08-11 04:33:51');

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
(1, '2025-08-02', 'morning', 'Tính năng gửi báo cáo khi viết xong nhật ký công việc', NULL, '2025-08-14 10:52:29', '2025-08-14 10:52:29'),
(1, '2025-08-02', 'afternoon', 'Tính năng gửi báo cáo khi viết xong nhật ký công việc', NULL, '2025-08-14 10:52:29', '2025-08-14 10:52:29'),
(1, '2025-08-02', 'evening', '17:00-19:30: Tính năng gửi báo cáo khi viết xong nhật ký công việc', NULL, '2025-08-14 10:52:29', '2025-08-14 10:52:29'),
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
(1, '2025-08-09', 'morning', 'Làm việc với UBND xã Quảng Thành', NULL, '2025-08-14 10:50:11', '2025-08-14 10:50:11'),
(1, '2025-08-09', 'afternoon', 'Làm việc với UBND xã Quảng Thành', NULL, '2025-08-14 10:50:11', '2025-08-14 10:50:11'),
(1, '2025-08-09', 'evening', '17:00-19:30: Nghỉ', NULL, '2025-08-14 10:50:11', '2025-08-14 10:50:11'),
(1, '2025-08-10', 'morning', 'Nghỉ', NULL, '2025-08-11 01:27:25', '2025-08-11 01:27:25'),
(1, '2025-08-10', 'afternoon', 'Nghỉ', NULL, '2025-08-11 01:27:25', '2025-08-11 01:27:25'),
(1, '2025-08-10', 'evening', 'Nghỉ', NULL, '2025-08-11 01:27:25', '2025-08-11 01:27:25'),
(1, '2025-08-11', 'morning', 'Phát triển tính năng quản lý dự án trong CDE phần tạo dự án', NULL, '2025-08-11 03:26:14', '2025-08-11 03:26:14'),
(1, '2025-08-11', 'afternoon', 'Phát triển tính năng quản lý dự án trong CDE phần tạo dự án', NULL, '2025-08-11 03:26:14', '2025-08-11 03:26:14'),
(1, '2025-08-11', 'evening', 'Nghỉ', NULL, '2025-08-11 03:26:14', '2025-08-11 03:26:14'),
(1, '2025-08-12', 'morning', 'Phát triển tính năng quản lý màu sắc cho IFC trong dự án', NULL, '2025-08-12 09:09:46', '2025-08-12 09:09:46'),
(1, '2025-08-12', 'afternoon', 'Phát triển tính năng quản lý quy tắc file trong dự án\r\nPhát triển tính năng quản lý vật liệu trong dự án', NULL, '2025-08-12 09:09:46', '2025-08-12 09:09:46'),
(1, '2025-08-12', 'evening', '17:00-19:30: Nghỉ', NULL, '2025-08-12 09:09:46', '2025-08-12 09:09:46'),
(1, '2025-08-14', 'morning', 'Phát triển tính năng Nhật ký thi công trong dự án', NULL, '2025-08-14 10:48:59', '2025-08-14 10:48:59'),
(1, '2025-08-14', 'afternoon', 'Phát triển tính năng Nhật ký thi công trong dự án', NULL, '2025-08-14 10:48:59', '2025-08-14 10:48:59'),
(1, '2025-08-14', 'evening', '17:00-19:00: Phát triển tính năng Nhật ký thi công trong dự án', NULL, '2025-08-14 10:48:59', '2025-08-14 10:48:59'),
(1, '2025-08-15', 'morning', 'Chỉnh sửa kè Vincom', NULL, '2025-08-15 08:07:08', '2025-08-15 08:07:08'),
(1, '2025-08-15', 'afternoon', 'Chỉnh sửa kè Vincom\r\nTiếp tục phát triển tính năng cuộc họp trong dự án CDE', NULL, '2025-08-15 08:07:08', '2025-08-15 08:07:08'),
(1, '2025-08-15', 'evening', '17:00-19:30: Nghỉ', NULL, '2025-08-15 08:07:08', '2025-08-15 08:07:08'),
(1, '2025-08-16', 'morning', 'Tiếp tục phát triển tính năng cuộc họp trong dự án CDE', NULL, '2025-08-16 01:29:02', '2025-08-16 01:29:02'),
(1, '2025-08-16', 'afternoon', 'Nghỉ', NULL, '2025-08-16 01:29:02', '2025-08-16 01:29:02'),
(1, '2025-08-16', 'evening', 'Nghỉ', NULL, '2025-08-16 01:29:02', '2025-08-16 01:29:02'),
(1, '2025-08-17', 'morning', 'Nghỉ', NULL, '2025-08-16 01:29:07', '2025-08-16 01:29:07'),
(1, '2025-08-17', 'afternoon', 'Nghỉ', NULL, '2025-08-16 01:29:07', '2025-08-16 01:29:07'),
(1, '2025-08-17', 'evening', 'Nghỉ', NULL, '2025-08-16 01:29:07', '2025-08-16 01:29:07'),
(1, '2025-08-18', 'morning', 'Tạo hướng dẫn sử dụng CDE', NULL, '2025-08-19 02:03:29', '2025-08-19 02:03:29'),
(1, '2025-08-18', 'afternoon', 'Sửa kè Vincom', NULL, '2025-08-19 02:03:29', '2025-08-19 02:03:29'),
(1, '2025-08-18', 'evening', '17:00-19:30: Sửa kè Vincom', NULL, '2025-08-19 02:03:29', '2025-08-19 02:03:29'),
(1, '2025-08-19', 'morning', 'Sửa kè Vincom\r\nCập nhật lại tính năng tạo văn bản cuộc họp trên CDE', NULL, '2025-08-19 02:04:01', '2025-08-19 02:04:01'),
(1, '2025-08-19', 'afternoon', 'Sửa kè Vincom', NULL, '2025-08-19 02:04:02', '2025-08-19 02:04:02'),
(1, '2025-08-19', 'evening', 'Nghỉ', NULL, '2025-08-19 02:04:02', '2025-08-19 02:04:02'),
(1, '2025-08-20', 'morning', 'Sửa kè Vincom', NULL, '2025-08-21 10:08:50', '2025-08-21 10:08:50'),
(1, '2025-08-20', 'afternoon', 'Cập nhật tính năng tạo văn bản cuộc họp trong dự án CDE', NULL, '2025-08-21 10:08:50', '2025-08-21 10:08:50'),
(1, '2025-08-20', 'evening', '17:00-19:30: Nghỉ', NULL, '2025-08-21 10:08:50', '2025-08-21 10:08:50'),
(1, '2025-08-21', 'morning', 'Cập nhật tính năng tạo văn bản cuộc họp trong dự án CDE', NULL, '2025-08-21 10:08:48', '2025-08-21 10:08:48'),
(1, '2025-08-21', 'afternoon', 'Trình bày bản vẽ bình đồ, trắc dọc DA 344 - 340', NULL, '2025-08-21 10:08:48', '2025-08-21 10:08:48'),
(1, '2025-08-21', 'evening', '17:00-19:30: Nghỉ', NULL, '2025-08-21 10:08:48', '2025-08-21 10:08:48'),
(1, '2025-08-22', 'morning', 'Mất điện', NULL, '2025-08-22 07:11:57', '2025-08-22 07:11:57'),
(1, '2025-08-22', 'afternoon', 'Cập nhật tính năng tạo văn bản cuộc họp trong dự án CDE', NULL, '2025-08-22 07:11:57', '2025-08-22 07:11:57'),
(1, '2025-08-22', 'evening', '17:00-19:30: Nghỉ', NULL, '2025-08-22 07:11:57', '2025-08-22 07:11:57'),
(1, '2025-08-23', 'morning', 'Bản vẽ BTC - Bình đồ cầu Quảng Chính', NULL, '2025-08-23 08:06:31', '2025-08-23 08:06:31'),
(1, '2025-08-23', 'afternoon', 'Bản vẽ BTC - Bình đồ cầu Quảng Chính', NULL, '2025-08-23 08:06:31', '2025-08-23 08:06:31'),
(1, '2025-08-23', 'evening', 'Nghỉ', NULL, '2025-08-23 08:06:31', '2025-08-23 08:06:31'),
(1, '2025-08-24', 'morning', 'Bình đồ - BTC cầu Mảy Nháu', NULL, '2025-08-24 11:18:40', '2025-08-24 11:18:40'),
(1, '2025-08-24', 'afternoon', 'Bình đồ - BTC cầu Mảy Nháu', NULL, '2025-08-24 11:18:40', '2025-08-24 11:18:40'),
(1, '2025-08-24', 'evening', '17:00-19:00: Bình đồ - BTC cầu Mảy Nháu', NULL, '2025-08-24 11:18:40', '2025-08-24 11:18:40'),
(1, '2025-08-25', 'morning', 'Ghép lại bình đồ, in PDF QL18B', NULL, '2025-08-25 09:02:57', '2025-08-25 09:02:57'),
(1, '2025-08-25', 'afternoon', 'Ghép lại bình đồ, in PDF QL18B', NULL, '2025-08-25 09:02:57', '2025-08-25 09:02:57'),
(1, '2025-08-25', 'evening', 'Nghỉ', NULL, '2025-08-25 09:02:57', '2025-08-25 09:02:57'),
(1, '2025-08-30', 'morning', 'Nghỉ', NULL, '2025-08-23 08:07:44', '2025-08-23 08:07:44'),
(1, '2025-08-30', 'afternoon', 'Nghỉ', NULL, '2025-08-23 08:07:44', '2025-08-23 08:07:44'),
(1, '2025-08-30', 'evening', 'Nghỉ', NULL, '2025-08-23 08:07:44', '2025-08-23 08:07:44'),
(1, '2025-08-31', 'morning', 'Nghỉ', NULL, '2025-08-23 08:07:40', '2025-08-23 08:07:40'),
(1, '2025-08-31', 'afternoon', 'Nghỉ', NULL, '2025-08-23 08:07:40', '2025-08-23 08:07:40'),
(1, '2025-08-31', 'evening', 'Nghỉ', NULL, '2025-08-23 08:07:40', '2025-08-23 08:07:40'),
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
(16, '2025-07-09', 'afternoon', 'Đọc tiêu chuẩn đường ô tô', NULL, '2025-08-07 09:33:02', '2025-08-07 09:33:02');
INSERT INTO `work_diary_entries` (`user_id`, `entry_date`, `period`, `content`, `note`, `created_at`, `updated_at`) VALUES
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
(16, '2025-07-29', 'afternoon', 'Đi hiện trường', NULL, '2025-08-07 09:35:44', '2025-08-07 09:35:44'),
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
-- Indexes for table `file_versions`
--
ALTER TABLE `file_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `file_id` (`file_id`,`version`);

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
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `project_activities`
--
ALTER TABLE `project_activities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_color_groups`
--
ALTER TABLE `project_color_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_project_group` (`project_id`,`name`);

--
-- Indexes for table `project_color_items`
--
ALTER TABLE `project_color_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_color_group` (`group_id`);

--
-- Indexes for table `project_daily_logs`
--
ALTER TABLE `project_daily_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `entry_date` (`entry_date`),
  ADD KEY `code` (`code`),
  ADD KEY `idx_pdl_status` (`status`);

--
-- Indexes for table `project_daily_log_equipment`
--
ALTER TABLE `project_daily_log_equipment`
  ADD PRIMARY KEY (`id`),
  ADD KEY `daily_log_id` (`daily_log_id`);

--
-- Indexes for table `project_daily_log_images`
--
ALTER TABLE `project_daily_log_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `daily_log_id` (`daily_log_id`);

--
-- Indexes for table `project_daily_log_labor`
--
ALTER TABLE `project_daily_log_labor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `daily_log_id` (`daily_log_id`);

--
-- Indexes for table `project_daily_notifications`
--
ALTER TABLE `project_daily_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `daily_log_id` (`daily_log_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `project_files`
--
ALTER TABLE `project_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`,`folder_id`);

--
-- Indexes for table `project_folders`
--
ALTER TABLE `project_folders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_groups`
--
ALTER TABLE `project_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_prj_name` (`project_id`,`name`),
  ADD KEY `prj_id` (`project_id`);

--
-- Indexes for table `project_group_members`
--
ALTER TABLE `project_group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_member` (`project_id`,`user_id`),
  ADD KEY `prj_grp` (`project_id`,`group_id`),
  ADD KEY `grp_id` (`group_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `project_invites`
--
ALTER TABLE `project_invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_token` (`token`),
  ADD KEY `prj_id` (`project_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `project_kmz`
--
ALTER TABLE `project_kmz`
  ADD PRIMARY KEY (`project_id`);

--
-- Indexes for table `project_material_in`
--
ALTER TABLE `project_material_in`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `code` (`code`),
  ADD KEY `received_date` (`received_date`);

--
-- Indexes for table `project_material_out`
--
ALTER TABLE `project_material_out`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `code` (`code`),
  ADD KEY `out_date` (`out_date`);

--
-- Indexes for table `project_meetings`
--
ALTER TABLE `project_meetings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prj` (`project_id`),
  ADD KEY `idx_start` (`start_time`),
  ADD KEY `fk_pm_user` (`created_by`);

--
-- Indexes for table `project_meeting_attendees`
--
ALTER TABLE `project_meeting_attendees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_meeting` (`meeting_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `project_meeting_details`
--
ALTER TABLE `project_meeting_details`
  ADD PRIMARY KEY (`meeting_id`),
  ADD KEY `fk_pmd_user` (`updated_by`);

--
-- Indexes for table `project_meeting_notifications`
--
ALTER TABLE `project_meeting_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_meeting` (`meeting_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `fk_pmn_sender` (`sender_id`);

--
-- Indexes for table `project_members`
--
ALTER TABLE `project_members`
  ADD PRIMARY KEY (`project_id`,`user_id`);

--
-- Indexes for table `project_naming_rules`
--
ALTER TABLE `project_naming_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project` (`project_id`),
  ADD KEY `idx_filename` (`computed_filename`);

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
-- AUTO_INCREMENT for table `file_versions`
--
ALTER TABLE `file_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

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
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `project_activities`
--
ALTER TABLE `project_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `project_color_groups`
--
ALTER TABLE `project_color_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `project_color_items`
--
ALTER TABLE `project_color_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `project_daily_logs`
--
ALTER TABLE `project_daily_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `project_daily_log_equipment`
--
ALTER TABLE `project_daily_log_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `project_daily_log_images`
--
ALTER TABLE `project_daily_log_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `project_daily_log_labor`
--
ALTER TABLE `project_daily_log_labor`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `project_daily_notifications`
--
ALTER TABLE `project_daily_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `project_files`
--
ALTER TABLE `project_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_folders`
--
ALTER TABLE `project_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `project_groups`
--
ALTER TABLE `project_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `project_group_members`
--
ALTER TABLE `project_group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `project_invites`
--
ALTER TABLE `project_invites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `project_material_in`
--
ALTER TABLE `project_material_in`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `project_material_out`
--
ALTER TABLE `project_material_out`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `project_meetings`
--
ALTER TABLE `project_meetings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `project_meeting_attendees`
--
ALTER TABLE `project_meeting_attendees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `project_meeting_notifications`
--
ALTER TABLE `project_meeting_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `project_naming_rules`
--
ALTER TABLE `project_naming_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

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
-- Constraints for table `project_color_items`
--
ALTER TABLE `project_color_items`
  ADD CONSTRAINT `fk_color_group` FOREIGN KEY (`group_id`) REFERENCES `project_color_groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_meetings`
--
ALTER TABLE `project_meetings`
  ADD CONSTRAINT `fk_pm_prj` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pm_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_meeting_attendees`
--
ALTER TABLE `project_meeting_attendees`
  ADD CONSTRAINT `fk_pma_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `project_meetings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pma_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_meeting_details`
--
ALTER TABLE `project_meeting_details`
  ADD CONSTRAINT `fk_pmd_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `project_meetings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pmd_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `project_meeting_notifications`
--
ALTER TABLE `project_meeting_notifications`
  ADD CONSTRAINT `fk_pmn_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `project_meetings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pmn_prj` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pmn_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pmn_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
