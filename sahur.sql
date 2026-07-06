-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 10, 2026 at 02:58 AM
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
-- Database: `sahur`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `admin_emp_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(500) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `admin_emp_id`, `message`, `link`, `type`, `is_read`, `created_at`) VALUES
(197, 32, 'Password changed for Lech Fidel PANTE (ID: 164959)', NULL, 'password_change', 0, '2026-03-17 14:55:19'),
(198, 64, 'Password changed for Lech Fidel PANTE (ID: 164959)', NULL, 'password_change', 0, '2026-03-17 14:55:19'),
(199, 32, 'Password reset requested for Lech Fidel PANTE (ID: 164959). <button onclick=\"window.location.href=\'admin_approve_reset.php\'\" style=\'color: #007bff; background: none; border: none; text-decoration: underline; cursor: pointer; padding: 0;\'>Click to review</button>', NULL, 'password_reset_request', 0, '2026-03-17 14:56:18'),
(200, 64, 'Password reset requested for Lech Fidel PANTE (ID: 164959). <button onclick=\"window.location.href=\'admin_approve_reset.php\'\" style=\'color: #007bff; background: none; border: none; text-decoration: underline; cursor: pointer; padding: 0;\'>Click to review</button>', NULL, 'password_reset_request', 0, '2026-03-17 14:56:18'),
(201, 54, 'Your password reset has been approved. You can now login using your ID Number: <strong>164959</strong> as both your username and temporary password. Please change your password after logging in for security.', NULL, 'password_reset_approved', 0, '2026-03-17 14:56:35');

-- --------------------------------------------------------

--
-- Table structure for table `applicant`
--

CREATE TABLE `applicant` (
  `applicant_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `position_applied` varchar(100) NOT NULL,
  `resume_file` varchar(255) DEFAULT NULL,
  `cover_letter_file` varchar(255) DEFAULT NULL,
  `other_documents` varchar(255) DEFAULT NULL,
  `application_date` date NOT NULL,
  `status` enum('Pending','For Review','For Interview','Accepted','Rejected') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_status`
--

CREATE TABLE `appointment_status` (
  `appointment_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `color` varchar(20) DEFAULT '#007bff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_status`
--

INSERT INTO `appointment_status` (`appointment_id`, `status_name`, `created_at`, `updated_at`, `color`) VALUES
(5, 'Casual - SP', '2025-05-20 02:59:18', '2025-05-21 02:38:04', '#00ff62'),
(6, 'Casual - PC', '2025-05-20 02:59:18', '2025-05-20 02:59:18', '#007bff'),
(7, 'Job Order', '2025-05-20 02:59:18', '2025-05-21 03:00:39', '#fff700'),
(8, 'Regular', '2025-05-20 02:59:18', '2025-05-21 02:38:04', '#033b77'),
(9, 'CARP Co-Terminus', '2025-05-20 02:59:18', '2025-05-21 02:37:29', '#ff0000'),
(10, 'Permanent', '2025-05-20 02:59:18', '2025-06-03 02:51:08', '#c989ec'),
(11, 'Temp-Regular', '2025-05-20 02:59:18', '2025-05-23 01:45:57', '#bbcee2'),
(39, 'CARP-Contractual', '2025-07-11 05:37:13', '2025-07-11 05:37:13', '#ff00ae');

-- --------------------------------------------------------

--
-- Table structure for table `attachments_monitoring`
--

CREATE TABLE `attachments_monitoring` (
  `monitoring_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `payroll_period` varchar(50) NOT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `status` enum('COMPLETE','INCOMPLETE','COMPLETE AND LATE','NOT SUBMITTED') DEFAULT 'NOT SUBMITTED',
  `filing_status` enum('FORWARDED','NOT FORWARDED') DEFAULT 'NOT FORWARDED',
  `submission_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attachments_monitoring`
--

INSERT INTO `attachments_monitoring` (`monitoring_id`, `emp_id`, `payroll_period`, `period_start`, `period_end`, `status`, `filing_status`, `submission_date`, `remarks`, `created_at`, `updated_at`) VALUES
(21, 43, 'Apr 1 - Apr 15', '2026-04-01', '2026-04-15', 'COMPLETE AND LATE', 'FORWARDED', '2026-04-13', '', '2026-04-10 01:38:22', '2026-05-13 07:27:47');

-- --------------------------------------------------------

--
-- Table structure for table `carousel_images`
--

CREATE TABLE `carousel_images` (
  `id` int(11) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `caption` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carousel_images`
--

INSERT INTO `carousel_images` (`id`, `image_name`, `image_path`, `caption`, `is_active`, `display_order`, `created_at`) VALUES
(19, '1000023285.jpg', '../uploads/carousel/1760255615_1000023285.jpg', 'SOUTH QUINALE DAM', 1, 1, '2025-10-12 07:53:35'),
(20, 'DJI_20250416100217_0065_D.JPG', '../uploads/carousel/1760255658_DJI_20250416100217_0065_D.JPG', 'ALOBO-GAPO SPIP', 1, 2, '2025-10-12 07:54:18'),
(21, 'Screenshot 2025-04-23 105313.png', '../uploads/carousel/1760255673_Screenshot 2025-04-23 105313.png', 'BON-BON SPIP', 1, 3, '2025-10-12 07:54:33'),
(22, 'Screenshot 2025-04-23 110421.png', '../uploads/carousel/1760255689_Screenshot 2025-04-23 110421.png', 'BULUSAN SPIP', 1, 4, '2025-10-12 07:54:49'),
(23, 'Screenshot 2025-04-23 110853.png', '../uploads/carousel/1760255709_Screenshot 2025-04-23 110853.png', 'CAGBACONG SPIP', 1, 6, '2025-10-12 07:55:09');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'CLEANING EQUIPMENT AND SUPPLIES', 'N/A', '2025-09-17 02:40:43'),
(2, 'COLOR COMPOUNDS AND DISPERSIONS', '', '2025-09-17 02:40:54'),
(3, 'FIRE FIGHTING EQUIPMENT', '', '2025-09-17 02:41:01'),
(4, 'FLAG OR ACCESSORIES', '', '2025-09-17 02:41:08'),
(5, 'FURNITURE AND FURNISHINGS', '', '2025-09-17 02:41:13'),
(6, 'ICT EQUIPMENT AND DEVICES AND ACCESSORIES', '', '2025-09-17 02:41:24'),
(7, 'MANUFACTURING COMPONENTS AND SUPPLIES', '', '2025-09-17 02:41:29'),
(8, 'OFFICE EQUIPMENT AND ACCESSORIES AND SUPPLIES', '', '2025-09-17 02:41:33'),
(9, 'PAPER MATERIALS AND PRODUCTS', '', '2025-09-17 02:41:37'),
(10, 'PRINTED PUBLICATIONS', '', '2025-09-17 02:41:42');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `message_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `message_type` enum('text','file','image') DEFAULT 'text',
  `file_path` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_name` varchar(255) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `reactions_count` int(11) DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = soft-deleted by sender (shown as "message deleted" to both parties)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`message_id`, `room_id`, `sender_id`, `message`, `message_type`, `file_path`, `is_read`, `created_at`, `file_name`, `file_type`, `file_size`, `reactions_count`, `is_deleted`) VALUES
(143, 80, 32, 'HOY', 'text', NULL, 1, '2026-05-11 06:44:02', NULL, NULL, NULL, 1, 0),
(144, 80, 32, 'shdasghda', 'text', NULL, 1, '2026-05-12 06:25:41', NULL, NULL, NULL, 0, 0),
(145, 80, 26, 'ano man', 'text', NULL, 1, '2026-05-12 06:25:47', NULL, NULL, NULL, 0, 0),
(146, 80, 32, 'ANO NAILING MO NA? AHA', 'text', NULL, 1, '2026-05-13 10:10:04', NULL, NULL, NULL, 0, 0),
(147, 80, 32, 'asdksajhbaks\\', 'text', NULL, 1, '2026-05-18 05:41:44', NULL, NULL, NULL, 0, 0),
(148, 80, 26, 'kuya pa delete hahaha nag request nako daliii hahaha', 'text', NULL, 1, '2026-05-19 01:01:26', NULL, NULL, NULL, 1, 0),
(149, 80, 32, 'try mo na mag input', 'text', NULL, 1, '2026-05-19 01:52:41', NULL, NULL, NULL, 0, 0),
(150, 80, 32, 'ay wow naga input na sya aha', 'text', NULL, 1, '2026-05-20 01:02:29', NULL, NULL, NULL, 0, 0),
(151, 80, 26, 'hahaha idi na sana query ko', 'text', NULL, 1, '2026-05-20 01:54:34', NULL, NULL, NULL, 0, 0),
(152, 80, 32, 'alin', 'text', NULL, 1, '2026-05-20 02:32:34', NULL, NULL, NULL, 0, 0),
(153, 80, 32, 'aha', 'text', NULL, 1, '2026-05-20 02:32:35', NULL, NULL, NULL, 0, 0),
(154, 80, 26, 'why naga blink ang chat box', 'text', NULL, 1, '2026-05-20 02:39:00', NULL, NULL, NULL, 0, 0),
(155, 80, 32, 'haha ewan ko aha', 'text', NULL, 1, '2026-05-20 02:41:58', NULL, NULL, NULL, 0, 0),
(156, 80, 32, 'icheck ko aha', 'text', NULL, 1, '2026-05-20 02:42:08', NULL, NULL, NULL, 0, 0),
(157, 80, 32, 'okay na', 'text', NULL, 1, '2026-05-20 02:42:11', NULL, NULL, NULL, 0, 0),
(158, 80, 32, 'try mo iedit', 'text', NULL, 1, '2026-05-20 02:42:49', NULL, NULL, NULL, 0, 0),
(159, 80, 32, 'su mga documents mo', 'text', NULL, 1, '2026-05-20 02:42:53', NULL, NULL, NULL, 0, 0),
(160, 80, 26, 'oks', 'text', NULL, 1, '2026-05-20 02:44:48', NULL, NULL, NULL, 0, 0),
(161, 80, 26, 'pwede maka paste screenshot igdi? hha', 'text', NULL, 1, '2026-05-20 02:45:29', NULL, NULL, NULL, 0, 0),
(162, 80, 26, 'pano to', 'text', NULL, 1, '2026-05-20 02:45:33', NULL, NULL, NULL, 0, 0),
(163, 80, 26, 'delete mo muna lahat', 'text', NULL, 1, '2026-05-20 02:53:17', NULL, NULL, NULL, 1, 0),
(164, 80, 26, 'yung input ko', 'text', NULL, 1, '2026-05-20 02:53:22', NULL, NULL, NULL, 0, 0),
(165, 80, 32, 'check mo na', 'text', NULL, 1, '2026-05-20 03:16:09', NULL, NULL, NULL, 0, 0),
(166, 80, 32, 'pwd kan mag inpu', 'text', NULL, 1, '2026-05-20 03:16:20', NULL, NULL, NULL, 0, 0),
(167, 80, 26, 'pa arrange in order sa doc no. haha', 'text', NULL, 1, '2026-05-20 05:22:08', NULL, NULL, NULL, 0, 0),
(168, 80, 26, 'Su QR code kuta hahah tas resibo haha', 'text', NULL, 1, '2026-05-25 06:41:58', NULL, NULL, NULL, 0, 0),
(169, 80, 32, 'check mo daw', 'text', NULL, 1, '2026-05-25 07:29:37', NULL, NULL, NULL, 0, 0),
(170, 80, 26, 'arin', 'text', NULL, 1, '2026-05-25 07:36:11', NULL, NULL, NULL, 0, 0),
(171, 80, 32, 'maam rej aha', 'text', NULL, 1, '2026-05-25 07:49:00', NULL, NULL, NULL, 0, 0),
(172, 80, 26, 'pa delete ng existing input mo', 'text', NULL, 1, '2026-05-28 02:50:10', NULL, NULL, NULL, 0, 0),
(173, 80, 32, 'umpo', 'text', NULL, 1, '2026-05-28 02:58:19', NULL, NULL, NULL, 0, 0),
(174, 80, 26, 'haha galang', 'text', NULL, 1, '2026-05-28 03:17:39', NULL, NULL, NULL, 0, 0),
(175, 80, 32, 'alin pa', 'text', NULL, 1, '2026-05-28 03:30:55', NULL, NULL, NULL, 0, 0),
(176, 80, 32, 'pareceived', 'text', NULL, 1, '2026-05-29 07:08:50', NULL, NULL, NULL, 0, 0),
(177, 80, 26, 'pa delete ng 5', 'text', NULL, 1, '2026-06-02 02:53:10', NULL, NULL, NULL, 0, 0),
(178, 81, 32, 'te ams', 'text', NULL, 1, '2026-06-03 02:05:13', NULL, NULL, NULL, 0, 0),
(179, 80, 26, 'PA delete', 'text', NULL, 1, '2026-06-04 09:56:53', NULL, NULL, NULL, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `chat_message_reactions`
--

CREATE TABLE `chat_message_reactions` (
  `reaction_id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_message_reactions`
--

INSERT INTO `chat_message_reactions` (`reaction_id`, `message_id`, `emp_id`, `emoji`, `created_at`) VALUES
(6, 143, 26, '😮', '2026-05-12 06:25:29'),
(8, 148, 32, '👍', '2026-05-19 01:52:37'),
(9, 163, 26, '😂', '2026-05-20 03:03:37');

-- --------------------------------------------------------

--
-- Table structure for table `chat_rooms`
--

CREATE TABLE `chat_rooms` (
  `room_id` int(11) NOT NULL,
  `room_name` varchar(255) DEFAULT NULL,
  `room_type` enum('private','group') DEFAULT 'private',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_rooms`
--

INSERT INTO `chat_rooms` (`room_id`, `room_name`, `room_type`, `created_by`, `created_at`) VALUES
(80, 'Private Chat', 'private', 32, '2026-05-11 06:43:59'),
(81, 'Private Chat', 'private', 32, '2026-06-03 02:05:06');

-- --------------------------------------------------------

--
-- Table structure for table `chat_room_participants`
--

CREATE TABLE `chat_room_participants` (
  `participant_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_room_participants`
--

INSERT INTO `chat_room_participants` (`participant_id`, `room_id`, `emp_id`, `joined_at`) VALUES
(159, 80, 32, '2026-05-11 06:43:59'),
(160, 80, 26, '2026-05-11 06:43:59'),
(161, 81, 32, '2026-06-03 02:05:06'),
(162, 81, 29, '2026-06-03 02:05:06');

-- --------------------------------------------------------

--
-- Table structure for table `comment_likes`
--

CREATE TABLE `comment_likes` (
  `like_id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_forms`
--

CREATE TABLE `company_forms` (
  `id` int(11) NOT NULL,
  `form_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_forms`
--

INSERT INTO `company_forms` (`id`, `form_name`, `file_path`, `description`, `is_active`, `created_at`) VALUES
(9, 'NIAACIMO_QAR', '../uploads/forms/1780533629_NIAACIMO-ADM-INT-Form13_Rev04 QAR 2024 - ALL SECTION.xls', 'SAMPLE', 1, '2026-06-04 00:40:29');

-- --------------------------------------------------------

--
-- Table structure for table `company_info`
--

CREATE TABLE `company_info` (
  `id` int(11) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `content` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_info`
--

INSERT INTO `company_info` (`id`, `section_name`, `content`, `is_active`, `updated_at`) VALUES
(1, 'MISSION', 'An efficient and well-managed government corporation developing and managing modern, resilient, and multi-purpose Irrigation Systems to improve agricultural productivity and increase farmer\'s incom', 1, '2025-09-29 06:25:31'),
(2, 'VISION', 'By 2023, NIA is an advanced and innovative irrigation agency enabling highly competitive and sustainable Philippine farming communities', 1, '2025-09-29 06:23:49'),
(3, 'CORE VALUES', 'INTEGREGITY\r\nINNOVATION\r\nCOMMITMET\r\nEXCELLENCE', 1, '2025-10-11 02:34:21'),
(4, 'INTEGRATED MANAGEMENT SYSTEM POLICY', 'We commit to provide efficient, effective, and sustainable irrigation services aimed towards the highest satisfaction of the Filipino farmers.\r\n\r\nWe strive for the attainment of our strategic themes of Technical and Operational Excellence, and Good Governance through partnership with the farmers and other relevant interested parties.\r\n\r\nWe commit to establish programs that prevent work-related injury and ill health, and encourage participation and consultation of workers within the Agency.\r\n\r\nWe abide with applicable legal and international requirements and we remain dedicated to the core values of Integrity, Innovation, Commitment and Excellence, to continually improve the NIA’s Integrated Management System.', 1, '2025-10-11 02:34:19');

-- --------------------------------------------------------

--
-- Table structure for table `congressional_districts`
--

CREATE TABLE `congressional_districts` (
  `id` int(11) NOT NULL,
  `province_code` varchar(10) NOT NULL,
  `district_code` varchar(10) NOT NULL,
  `district_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `congressional_districts`
--

INSERT INTO `congressional_districts` (`id`, `province_code`, `district_code`, `district_name`) VALUES
(1, 'ALB', 'ALB-1', 'Albay 1st District'),
(2, 'ALB', 'ALB-2', 'Albay 2nd District'),
(3, 'ALB', 'ALB-3', 'Albay 3rd District'),
(4, 'CAN', 'CAN-1', 'Camarines Norte 1st District'),
(5, 'CAN', 'CAN-2', 'Camarines Norte 2nd District'),
(6, 'CAS', 'CAS-1', 'Camarines Sur 1st District'),
(7, 'CAS', 'CAS-2', 'Camarines Sur 2nd District'),
(8, 'CAS', 'CAS-3', 'Camarines Sur 3rd District'),
(9, 'CAS', 'CAS-4', 'Camarines Sur 4th District'),
(10, 'CAS', 'CAS-5', 'Camarines Sur 5th District'),
(11, 'CAT', 'CAT-1', 'Catanduanes Lone District'),
(12, 'MAS', 'MAS-1', 'Masbate 1st District'),
(13, 'MAS', 'MAS-2', 'Masbate 2nd District'),
(14, 'MAS', 'MAS-3', 'Masbate 3rd District'),
(15, 'SOR', 'SOR-1', 'Sorsogon 1st District'),
(16, 'SOR', 'SOR-2', 'Sorsogon 2nd District');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_items`
--

CREATE TABLE `delivery_items` (
  `id` int(11) NOT NULL,
  `delivery_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_items`
--

INSERT INTO `delivery_items` (`id`, `delivery_id`, `item_id`, `quantity`, `unit_cost`, `total_cost`, `created_at`) VALUES
(17, 24, 15, 10, 500.00, 5000.00, '2025-09-18 05:42:14'),
(18, 25, 9, 5, 25.00, 125.00, '2025-09-18 05:42:14'),
(19, 26, 10, 5, 40.00, 200.00, '2025-09-18 05:42:14'),
(20, 27, 21, 3, 300.00, 900.00, '2025-09-18 05:42:14'),
(21, 28, 16, 12, 500.00, 6000.00, '2025-09-18 05:42:14'),
(35, 44, 9, 2, 11.00, 22.00, '2025-09-18 08:27:16'),
(36, 45, 21, 5, 80.00, 400.00, '2025-09-18 08:28:28'),
(37, 46, 11, 20, 150.00, 3000.00, '2025-09-18 08:28:28'),
(38, 47, 12, 20, 150.00, 3000.00, '2025-09-18 08:28:28'),
(39, 48, 13, 20, 150.00, 3000.00, '2025-09-18 08:28:28'),
(40, 49, 14, 20, 150.00, 3000.00, '2025-09-18 08:28:28');

-- --------------------------------------------------------

--
-- Table structure for table `document_archive`
--

CREATE TABLE `document_archive` (
  `id` int(10) UNSIGNED NOT NULL,
  `original_id` int(10) UNSIGNED NOT NULL COMMENT 'document_records.id at time of archiving',
  `archive_date` date NOT NULL COMMENT 'The calendar day being archived (PHT)',
  `kind` varchar(20) NOT NULL COMMENT 'incoming | outgoing | internal',
  `document_number` varchar(100) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `forwarded_by` varchar(150) DEFAULT NULL,
  `from_section` varchar(150) DEFAULT NULL,
  `to_section` varchar(150) DEFAULT NULL,
  `date_forwarded` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `snapshot_json` longtext DEFAULT NULL COMMENT 'Full JSON snapshot of document_records row + joins',
  `archived_by_emp` int(10) UNSIGNED DEFAULT NULL COMMENT 'emp_id of who triggered the archive (0 = cron)',
  `archived_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily snapshots of document_records, archived each midnight PHT';

--
-- Dumping data for table `document_archive`
--

INSERT INTO `document_archive` (`id`, `original_id`, `archive_date`, `kind`, `document_number`, `document_name`, `document_type`, `status`, `forwarded_by`, `from_section`, `to_section`, `date_forwarded`, `remarks`, `snapshot_json`, `archived_by_emp`, `archived_at`) VALUES
(9, 84, '2026-05-20', 'incoming', 'IMO-05202026-0001', 'Gate Pass Vcls: 622,623,624-2026\r\nRIS: 446,447,448-2026\r\nGate Pass Mtrls: 056,057-2026 \r\nTO: OMS-TO 800,801', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'with Travel Order', '{\"id\":84,\"document_number\":\"IMO-05202026-0001\",\"document_name\":\"Gate Pass Vcls: 622,623,624-2026\\r\\nRIS: 446,447,448-2026\\r\\nGate Pass Mtrls: 056,057-2026 \\r\\nTO: OMS-TO 800,801\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:14:50\",\"remarks\":\"with Travel Order\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:14:50\",\"updated_at\":\"2026-05-20 11:14:50\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(10, 85, '2026-05-20', 'incoming', 'IMO-05202026-0002', '\'To COA: \r\nJEV Fund 501 (Mar & Aprl 2026)', 'Transmittal', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'transmittal only', '{\"id\":85,\"document_number\":\"IMO-05202026-0002\",\"document_name\":\"\'To COA: \\r\\nJEV Fund 501 (Mar & Aprl 2026)\",\"document_type_id\":25,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:15:35\",\"remarks\":\"transmittal only\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:15:35\",\"updated_at\":\"2026-05-20 11:15:35\",\"created_by_emp_id\":26,\"type_name\":\"Transmittal\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(11, 86, '2026-05-20', 'incoming', 'IMO-05202026-0003', 'RIS: 449-2026', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":86,\"document_number\":\"IMO-05202026-0003\",\"document_name\":\"RIS: 449-2026\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:16:15\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:16:15\",\"updated_at\":\"2026-05-20 11:16:15\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(12, 87, '2026-05-20', 'incoming', 'IMO-05202026-0004', '\'To COA: \r\nJEV Fund 501 (CARP) with supporting documents', 'Transmittal', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'transmittal only', '{\"id\":87,\"document_number\":\"IMO-05202026-0004\",\"document_name\":\"\'To COA: \\r\\nJEV Fund 501 (CARP) with supporting documents\",\"document_type_id\":25,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:16:00\",\"remarks\":\"transmittal only\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:16:51\",\"updated_at\":\"2026-05-20 11:58:14\",\"created_by_emp_id\":26,\"type_name\":\"Transmittal\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(13, 88, '2026-05-20', 'incoming', 'IMO-05202026-0005', 'Gate Pass Vlcs: 625-2026', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":88,\"document_number\":\"IMO-05202026-0005\",\"document_name\":\"Gate Pass Vlcs: 625-2026\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:24:15\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:24:15\",\"updated_at\":\"2026-05-20 11:24:15\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(14, 89, '2026-05-20', 'incoming', 'IMO-05202026-0006', 'Invitation to participate in the Live-In Refresher training on FMS for IA Treasurer under IMT Contract subsidy on May 27-28, 2026 at La Roca Veranda Suites, Gogon, Legazpi City', 'Letter', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":89,\"document_number\":\"IMO-05202026-0006\",\"document_name\":\"Invitation to participate in the Live-In Refresher training on FMS for IA Treasurer under IMT Contract subsidy on May 27-28, 2026 at La Roca Veranda Suites, Gogon, Legazpi City\",\"document_type_id\":15,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:24:34\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:24:34\",\"updated_at\":\"2026-05-20 11:24:34\",\"created_by_emp_id\":26,\"type_name\":\"Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(15, 90, '2026-05-20', 'incoming', 'IMO-05202026-0007', 'LIPA (Dry Season 2026)\r\nBalading Awang CIS', 'Others', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'List of Irrigated & Planted Area', '{\"id\":90,\"document_number\":\"IMO-05202026-0007\",\"document_name\":\"LIPA (Dry Season 2026)\\r\\nBalading Awang CIS\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:25:05\",\"remarks\":\"List of Irrigated & Planted Area\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:25:05\",\"updated_at\":\"2026-05-20 11:25:05\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(16, 91, '2026-05-20', 'incoming', 'IMO-05202026-0008', 'LIPA (Dry Season 2026)\r\nBonga Bacacay IA', 'Others', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'List of Irrigated & Planted Area', '{\"id\":91,\"document_number\":\"IMO-05202026-0008\",\"document_name\":\"LIPA (Dry Season 2026)\\r\\nBonga Bacacay IA\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:25:32\",\"remarks\":\"List of Irrigated & Planted Area\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:25:32\",\"updated_at\":\"2026-05-20 11:25:32\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(17, 92, '2026-05-20', 'incoming', 'IMO-05202026-0009', 'LIPA (Dry Season 2026)\r\nMNOH Hibiga RIS', 'Others', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'List of Irrigated & Planted Areas', '{\"id\":92,\"document_number\":\"IMO-05202026-0009\",\"document_name\":\"LIPA (Dry Season 2026)\\r\\nMNOH Hibiga RIS\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:25:59\",\"remarks\":\"List of Irrigated & Planted Areas\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:25:59\",\"updated_at\":\"2026-05-20 11:25:59\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(18, 93, '2026-05-20', 'incoming', 'IMO-05202026-0010', 'Travel Order: OMS-TO 804,802', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":93,\"document_number\":\"IMO-05202026-0010\",\"document_name\":\"Travel Order: OMS-TO 804,802\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:26:17\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:26:17\",\"updated_at\":\"2026-05-20 11:26:17\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(19, 94, '2026-05-20', 'external', 'IMO-05202026-0011', 'From Vinmor Const. & Supply Inc.:\r\nFormal request for indefinite work suspension due to unresolved right-of-way issues -Construction of Maninila SIP, Guinobatan Albay', 'Letter', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'From Vinmor Const. & Supply Inc.', '{\"id\":94,\"document_number\":\"IMO-05202026-0011\",\"document_name\":\"From Vinmor Const. & Supply Inc.:\\r\\nFormal request for indefinite work suspension due to unresolved right-of-way issues -Construction of Maninila SIP, Guinobatan Albay\",\"document_type_id\":15,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:27:45\",\"remarks\":\"From Vinmor Const. & Supply Inc.\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:27:45\",\"updated_at\":\"2026-05-20 11:27:45\",\"created_by_emp_id\":26,\"type_name\":\"Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(20, 95, '2026-05-20', 'incoming', 'IMO-05202026-0012', 'Travel Order: OMS-TO 805, ENG-TO 742', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":95,\"document_number\":\"IMO-05202026-0012\",\"document_name\":\"Travel Order: OMS-TO 805, ENG-TO 742\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:28:18\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:28:18\",\"updated_at\":\"2026-05-20 11:28:18\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(21, 96, '2026-05-20', 'incoming', 'IMO-05202026-0013', 'To RO:\r\nSubmission of validation report on the ongoing project at Purok 6, Brgy. Buyoan, Legazpi City', 'Inspection Report', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'Validation Report', '{\"id\":96,\"document_number\":\"IMO-05202026-0013\",\"document_name\":\"To RO:\\r\\nSubmission of validation report on the ongoing project at Purok 6, Brgy. Buyoan, Legazpi City\",\"document_type_id\":11,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:29:04\",\"remarks\":\"Validation Report\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:29:04\",\"updated_at\":\"2026-05-20 11:29:04\",\"created_by_emp_id\":26,\"type_name\":\"Inspection Report\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(22, 97, '2026-05-20', 'incoming', 'IMO-05202026-0014', 'To RO: \r\nReturn documents for condonation of writing off of loans of Unpaid ISF Back Accounts balance per 10969', 'Transmittal', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":97,\"document_number\":\"IMO-05202026-0014\",\"document_name\":\"To RO: \\r\\nReturn documents for condonation of writing off of loans of Unpaid ISF Back Accounts balance per 10969\",\"document_type_id\":25,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:30:03\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:30:03\",\"updated_at\":\"2026-05-20 11:30:03\",\"created_by_emp_id\":26,\"type_name\":\"Transmittal\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(23, 98, '2026-05-20', 'external', 'IMO-05202026-0015', 'From RO: \r\nReturned  Project Completion Report \r\n1. Right Quinale & 5', 'Purchase Order', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'for compliance', '{\"id\":98,\"document_number\":\"IMO-05202026-0015\",\"document_name\":\"From RO: \\r\\nReturned  Project Completion Report \\r\\n1. Right Quinale & 5\",\"document_type_id\":22,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:33:46\",\"remarks\":\"for compliance\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:33:46\",\"updated_at\":\"2026-05-20 11:33:46\",\"created_by_emp_id\":26,\"type_name\":\"Purchase Order\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(24, 99, '2026-05-20', 'external', 'IMO-05202026-0016', 'From RO:\r\nFIVC and final inspection Report\r\nRepair/Resto of Carangag CIS', 'FIVC / Final Inspection Report', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":99,\"document_number\":\"IMO-05202026-0016\",\"document_name\":\"From RO:\\r\\nFIVC and final inspection Report\\r\\nRepair\\/Resto of Carangag CIS\",\"document_type_id\":8,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:36:56\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:36:56\",\"updated_at\":\"2026-05-20 11:36:56\",\"created_by_emp_id\":26,\"type_name\":\"FIVC \\/ Final Inspection Report\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(25, 100, '2026-05-20', 'external', 'IMO-05202026-0017', 'From RO:\r\nFIVC and final inspection Report\r\nRepair/Resto of Caningag CIS', 'FIVC / Final Inspection Report', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":100,\"document_number\":\"IMO-05202026-0017\",\"document_name\":\"From RO:\\r\\nFIVC and final inspection Report\\r\\nRepair\\/Resto of Caningag CIS\",\"document_type_id\":8,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:37:10\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:37:10\",\"updated_at\":\"2026-05-20 11:37:10\",\"created_by_emp_id\":26,\"type_name\":\"FIVC \\/ Final Inspection Report\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(26, 102, '2026-05-20', 'incoming', 'IMO-05202026-0018', 'Travel Order: OMS-TO 803', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":102,\"document_number\":\"IMO-05202026-0018\",\"document_name\":\"Travel Order: OMS-TO 803\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-20 05:44:58\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-20 11:44:58\",\"updated_at\":\"2026-05-20 11:44:58\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-25 11:51:59'),
(27, 115, '2026-05-25', 'incoming', 'IMO-05252026-0001', 'Gate Pass Vlcs: 638 to 641-2026\r\nRIS: 454,455,456,457-2026\r\nTR: 271,272-2026', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":115,\"document_number\":\"IMO-05252026-0001\",\"document_name\":\"Gate Pass Vlcs: 638 to 641-2026\\r\\nRIS: 454,455,456,457-2026\\r\\nTR: 271,272-2026\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 07:53:13\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 13:53:13\",\"updated_at\":\"2026-05-25 13:53:13\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(28, 116, '2026-05-25', 'incoming', 'IMO-05252026-0002', '\'Check & attachments:\r\n1. HDMF (Php 1,000.00)\r\n2. HDMF (Php 3,000.00)\r\n3. NIA 501 COB (Php 31,155.57)', 'Cheques / JEV', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":116,\"document_number\":\"IMO-05252026-0002\",\"document_name\":\"\'Check & attachments:\\r\\n1. HDMF (Php 1,000.00)\\r\\n2. HDMF (Php 3,000.00)\\r\\n3. NIA 501 COB (Php 31,155.57)\",\"document_type_id\":5,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 07:53:28\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 13:53:28\",\"updated_at\":\"2026-05-25 13:53:28\",\"created_by_emp_id\":26,\"type_name\":\"Cheques \\/ JEV\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(29, 117, '2026-05-25', 'incoming', 'IMO-05252026-0003', 'Tarvel Order: \r\nOMS-TO 825,831\r\nGate Pass Mtrls: 062, 061-2026', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'with Travel Order', '{\"id\":117,\"document_number\":\"IMO-05252026-0003\",\"document_name\":\"Tarvel Order: \\r\\nOMS-TO 825,831\\r\\nGate Pass Mtrls: 062, 061-2026\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 07:53:55\",\"remarks\":\"with Travel Order\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 13:53:55\",\"updated_at\":\"2026-05-25 13:53:55\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(30, 118, '2026-05-25', 'incoming', 'IMO-05252026-0004', '\'DDTT: 230-2026\r\nGate Pass Vlcs: 642-2026', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":118,\"document_number\":\"IMO-05252026-0004\",\"document_name\":\"\'DDTT: 230-2026\\r\\nGate Pass Vlcs: 642-2026\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 07:54:12\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 13:54:12\",\"updated_at\":\"2026-05-25 13:54:12\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(31, 119, '2026-05-25', 'incoming', 'IMO-05252026-0005', 'LIPA (Dry Season 2026)\r\n1. Hacienda San Miguel IA Inc. \r\n2. Binisitahan/Cale Naga CIS \r\n3. Sta. Florentina Farmers IA Inc. \r\n4. Kimahat Manito IA Inc. \r\n5. Farmers Tanabega IA Inc. \r\n6. Tigbi Bolo Libjo IA Inc.', 'List of Irrigated & Planted Areas', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":119,\"document_number\":\"IMO-05252026-0005\",\"document_name\":\"LIPA (Dry Season 2026)\\r\\n1. Hacienda San Miguel IA Inc. \\r\\n2. Binisitahan\\/Cale Naga CIS \\r\\n3. Sta. Florentina Farmers IA Inc. \\r\\n4. Kimahat Manito IA Inc. \\r\\n5. Farmers Tanabega IA Inc. \\r\\n6. Tigbi Bolo Libjo IA Inc.\",\"document_type_id\":32,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:28:00\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:28:00\",\"updated_at\":\"2026-05-25 14:28:00\",\"created_by_emp_id\":26,\"type_name\":\"List of Irrigated & Planted Areas\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(32, 120, '2026-05-25', 'incoming', 'IMO-05252026-0006', 'Personnel Locator Slip:\r\nJVPeñaflor (2:00-3:00pm)', 'Locator Slip', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":120,\"document_number\":\"IMO-05252026-0006\",\"document_name\":\"Personnel Locator Slip:\\r\\nJVPe\\u00f1aflor (2:00-3:00pm)\",\"document_type_id\":12,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:28:29\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:28:29\",\"updated_at\":\"2026-05-25 14:28:29\",\"created_by_emp_id\":26,\"type_name\":\"Locator Slip\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(33, 121, '2026-05-25', 'incoming', 'IMO-05252026-0007', 'Certificate of Employment (COE):\r\nElla N. Ringad - Data Analyst Controller', 'Others', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'Personal request', '{\"id\":121,\"document_number\":\"IMO-05252026-0007\",\"document_name\":\"Certificate of Employment (COE):\\r\\nElla N. Ringad - Data Analyst Controller\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:29:02\",\"remarks\":\"Personal request\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:29:02\",\"updated_at\":\"2026-05-25 14:29:02\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(34, 122, '2026-05-25', 'incoming', 'IMO-05252026-0008', 'Travel Order: \r\nADM-TO 103\r\nItinerary: OMS-TO 836,835,828, 826, 829', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":122,\"document_number\":\"IMO-05252026-0008\",\"document_name\":\"Travel Order: \\r\\nADM-TO 103\\r\\nItinerary: OMS-TO 836,835,828, 826, 829\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:29:17\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:29:17\",\"updated_at\":\"2026-05-25 14:29:17\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(35, 123, '2026-05-25', 'outgoing', 'IMO-05252026-0009', 'To RO:\r\nDesignated as safety officers \r\nAP Francia\r\nAMDBalingasa', 'Others', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'designated as SO', '{\"id\":123,\"document_number\":\"IMO-05252026-0009\",\"document_name\":\"To RO:\\r\\nDesignated as safety officers \\r\\nAP Francia\\r\\nAMDBalingasa\",\"document_type_id\":29,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:30:40\",\"remarks\":\"designated as SO\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:30:40\",\"updated_at\":\"2026-05-25 14:30:40\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(36, 124, '2026-05-25', 'incoming', 'IMO-05252026-0010', 'Certificate of Employment (COE):\r\nJessica V. Peñaflor', 'Others', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'Personal request', '{\"id\":124,\"document_number\":\"IMO-05252026-0010\",\"document_name\":\"Certificate of Employment (COE):\\r\\nJessica V. Pe\\u00f1aflor\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:31:11\",\"remarks\":\"Personal request\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:31:11\",\"updated_at\":\"2026-05-25 14:31:11\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(37, 125, '2026-05-25', 'incoming', 'IMO-05252026-0011', 'Gate Pass Vlcs: 643-2026', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":125,\"document_number\":\"IMO-05252026-0011\",\"document_name\":\"Gate Pass Vlcs: 643-2026\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:31:23\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:31:23\",\"updated_at\":\"2026-05-25 14:31:23\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(38, 126, '2026-05-25', 'incoming', 'IMO-05252026-0012', 'Travel Order:\r\nOMS-TO 834\r\nItinerary: ENG-TO 765,766,765,763,764', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":126,\"document_number\":\"IMO-05252026-0012\",\"document_name\":\"Travel Order:\\r\\nOMS-TO 834\\r\\nItinerary: ENG-TO 765,766,765,763,764\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:31:36\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:31:36\",\"updated_at\":\"2026-05-25 14:31:36\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(39, 127, '2026-05-25', 'incoming', 'IMO-05252026-0013', 'Travel Order/Itinerary: \r\nENG-TO 745, 748,747,767, OMS-TO 834', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":127,\"document_number\":\"IMO-05252026-0013\",\"document_name\":\"Travel Order\\/Itinerary: \\r\\nENG-TO 745, 748,747,767, OMS-TO 834\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:31:53\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:31:53\",\"updated_at\":\"2026-05-25 14:31:53\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(40, 128, '2026-05-25', 'incoming', 'IMO-05252026-0014', 'Travel Order:\r\nOMS-TO 104,105', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":128,\"document_number\":\"IMO-05252026-0014\",\"document_name\":\"Travel Order:\\r\\nOMS-TO 104,105\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:32:04\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:32:04\",\"updated_at\":\"2026-05-25 14:32:04\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(41, 129, '2026-05-25', 'external', 'IMO-05252026-0015', 'From RO:\r\nTraining on POW preparation, Contract Management and Price escalation', 'Memorandum', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":129,\"document_number\":\"IMO-05252026-0015\",\"document_name\":\"From RO:\\r\\nTraining on POW preparation, Contract Management and Price escalation\",\"document_type_id\":13,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:32:24\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:32:24\",\"updated_at\":\"2026-05-25 14:32:24\",\"created_by_emp_id\":26,\"type_name\":\"Memorandum\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(42, 130, '2026-05-25', 'incoming', 'IMO-05252026-0016', '[advance copy]\r\nApplication for leave\r\nMaureen R. Duran', 'Application for Leave / Compensatory Time Out (CTO)', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":130,\"document_number\":\"IMO-05252026-0016\",\"document_name\":\"[advance copy]\\r\\nApplication for leave\\r\\nMaureen R. Duran\",\"document_type_id\":33,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:34:28\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:34:28\",\"updated_at\":\"2026-05-25 14:34:28\",\"created_by_emp_id\":26,\"type_name\":\"Application for Leave \\/ Compensatory Time Out (CTO)\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(43, 131, '2026-05-25', 'incoming', 'IMO-05252026-0017', 'Bill of Materials:\r\nMarigondon CIS\r\nPhp 93,038.14', 'Others', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":131,\"document_number\":\"IMO-05252026-0017\",\"document_name\":\"Bill of Materials:\\r\\nMarigondon CIS\\r\\nPhp 93,038.14\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:38:23\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:38:23\",\"updated_at\":\"2026-05-25 14:38:23\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(44, 132, '2026-05-25', 'incoming', 'IMO-05252026-0018', 'Itinerary: OMS-TO 839', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":132,\"document_number\":\"IMO-05252026-0018\",\"document_name\":\"Itinerary: OMS-TO 839\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:38:48\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:38:48\",\"updated_at\":\"2026-05-25 14:38:48\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(45, 133, '2026-05-25', 'incoming', 'IMO-05252026-0019', 'Gate Pass Vlcs: 644-2026\r\nRIS: 458-2026\r\nTravel Order: OMS-TO 838', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":133,\"document_number\":\"IMO-05252026-0019\",\"document_name\":\"Gate Pass Vlcs: 644-2026\\r\\nRIS: 458-2026\\r\\nTravel Order: OMS-TO 838\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:40:45\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:40:45\",\"updated_at\":\"2026-05-25 14:40:45\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(46, 134, '2026-05-25', 'outgoing', 'IMO-05252026-0020', 'To ___ Irrigator\'s Association President:\r\nInvitation to participate in the refresher system management workshop cum GAD Orientation for Bahamas, Ogsong and MAZOIA on May 26-27, 2026 at Refreshing Grace, Bagumbayan, Ligao City', 'Communication Letter', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":134,\"document_number\":\"IMO-05252026-0020\",\"document_name\":\"To ___ Irrigator\'s Association President:\\r\\nInvitation to participate in the refresher system management workshop cum GAD Orientation for Bahamas, Ogsong and MAZOIA on May 26-27, 2026 at Refreshing Grace, Bagumbayan, Ligao City\",\"document_type_id\":6,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:41:35\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:41:35\",\"updated_at\":\"2026-05-25 14:41:35\",\"created_by_emp_id\":26,\"type_name\":\"Communication Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(47, 135, '2026-05-25', 'incoming', 'IMO-05252026-0021', 'To RO:\r\nRequest for copies of appointment from CY 2015 to CY 2021', 'Request letter', 'received', 'Ella RINGAD', NULL, NULL, NULL, '', '{\"id\":135,\"document_number\":\"IMO-05252026-0021\",\"document_name\":\"To RO:\\r\\nRequest for copies of appointment from CY 2015 to CY 2021\",\"document_type_id\":34,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:43:40\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:43:40\",\"updated_at\":\"2026-05-25 14:43:40\",\"created_by_emp_id\":26,\"type_name\":\"Request letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(48, 136, '2026-05-25', 'incoming', 'IMO-05252026-0022', 'TEV: \r\nMark Angelo P. Polidario (March 2026) Php 4,570.00', 'Costing', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'for costing', '{\"id\":136,\"document_number\":\"IMO-05252026-0022\",\"document_name\":\"TEV: \\r\\nMark Angelo P. Polidario (March 2026) Php 4,570.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:50:09\",\"remarks\":\"for costing\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:50:09\",\"updated_at\":\"2026-05-25 14:50:09\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(49, 137, '2026-05-25', 'incoming', 'IMO-05252026-0023', 'TEV: \r\nNoel B. Oraye Jr. (March 2026) Php 4,130.00', 'Costing', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'for costing', '{\"id\":137,\"document_number\":\"IMO-05252026-0023\",\"document_name\":\"TEV: \\r\\nNoel B. Oraye Jr. (March 2026) Php 4,130.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:50:30\",\"remarks\":\"for costing\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:50:30\",\"updated_at\":\"2026-05-25 14:50:30\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(50, 138, '2026-05-25', 'incoming', 'IMO-05252026-0024', 'TEV: \r\nJennimel R. Dayupay (March 2026) Php 1,900.00', 'Costing', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'for costing', '{\"id\":138,\"document_number\":\"IMO-05252026-0024\",\"document_name\":\"TEV: \\r\\nJennimel R. Dayupay (March 2026) Php 1,900.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:50:56\",\"remarks\":\"for costing\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:50:56\",\"updated_at\":\"2026-05-25 14:50:56\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(51, 139, '2026-05-25', 'incoming', 'IMO-05252026-0025', 'TEV: \r\nRamon Aydalla (Feb. 2026) Php 3,560.00', 'Costing', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'for costing', '{\"id\":139,\"document_number\":\"IMO-05252026-0025\",\"document_name\":\"TEV: \\r\\nRamon Aydalla (Feb. 2026) Php 3,560.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 08:51:15\",\"remarks\":\"for costing\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:51:15\",\"updated_at\":\"2026-05-25 14:51:15\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(52, 140, '2026-05-25', 'incoming', 'IMO-05252026-0026', 'TEV: \r\nMark Angelo P. Polidario (Feb. 2026) Php 3,450.00', 'Costing', 'received', 'Ella RINGAD', NULL, NULL, NULL, 'for costing', '{\"id\":140,\"document_number\":\"IMO-05252026-0026\",\"document_name\":\"TEV: \\r\\nMark Angelo P. Polidario (Feb. 2026) Php 3,450.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"0000-00-00 00:00:00\",\"date_received\":\"2026-05-25 16:31:21\",\"remarks\":\"for costing\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 14:51:35\",\"updated_at\":\"2026-05-25 16:31:21\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46');
INSERT INTO `document_archive` (`id`, `original_id`, `archive_date`, `kind`, `document_number`, `document_name`, `document_type`, `status`, `forwarded_by`, `from_section`, `to_section`, `date_forwarded`, `remarks`, `snapshot_json`, `archived_by_emp`, `archived_at`) VALUES
(53, 143, '2026-05-25', 'incoming', 'IMO-05252026-0027', 'Itinerary: ENG-TO 768, 750', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-25 16:57:28', '', '{\"id\":143,\"document_number\":\"IMO-05252026-0027\",\"document_name\":\"Itinerary: ENG-TO 768, 750\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-25 16:57:28\",\"date_received\":\"2026-05-25 16:57:28\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 16:57:28\",\"updated_at\":\"2026-05-25 16:57:28\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(54, 144, '2026-05-25', 'incoming', 'IMO-05252026-0028', 'TEV: \r\nJordan P. Roncesvalles (Jan-March 2026) Php 4,500.00', 'Costing', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-25 17:03:09', 'for costing:', '{\"id\":144,\"document_number\":\"IMO-05252026-0028\",\"document_name\":\"TEV: \\r\\nJordan P. Roncesvalles (Jan-March 2026) Php 4,500.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-25 17:03:09\",\"date_received\":\"2026-05-25 17:03:09\",\"remarks\":\"for costing:\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 17:03:09\",\"updated_at\":\"2026-05-25 17:03:09\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(55, 145, '2026-05-25', 'incoming', 'IMO-05252026-0029', 'Travel Order:\r\nOMS-TO 841', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-25 17:03:24', '', '{\"id\":145,\"document_number\":\"IMO-05252026-0029\",\"document_name\":\"Travel Order:\\r\\nOMS-TO 841\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-25 17:03:24\",\"date_received\":\"2026-05-25 17:03:24\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 17:03:24\",\"updated_at\":\"2026-05-25 17:03:24\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(56, 146, '2026-05-25', 'incoming', 'IMO-05252026-0030', 'Travel Order:\r\nENG-TO 751,752,753,754\r\nItinerary: ENG-TO 755,756,757,758,759,760,761,762', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-25 17:04:00', '', '{\"id\":146,\"document_number\":\"IMO-05252026-0030\",\"document_name\":\"Travel Order:\\r\\nENG-TO 751,752,753,754\\r\\nItinerary: ENG-TO 755,756,757,758,759,760,761,762\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-25 17:04:00\",\"date_received\":\"2026-05-25 17:04:00\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 17:04:00\",\"updated_at\":\"2026-05-25 17:04:00\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(57, 147, '2026-05-25', 'external', 'IMO-05252026-0031', 'From Gabawan IFA (GIFA Inc):\r\nRequesting for an ocular site visit and inspection of SWIP and canal structures', 'Request letter', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-25 17:04:19', '', '{\"id\":147,\"document_number\":\"IMO-05252026-0031\",\"document_name\":\"From Gabawan IFA (GIFA Inc):\\r\\nRequesting for an ocular site visit and inspection of SWIP and canal structures\",\"document_type_id\":34,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-25 17:04:19\",\"date_received\":\"2026-05-25 17:04:19\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 17:04:19\",\"updated_at\":\"2026-05-25 17:04:19\",\"created_by_emp_id\":26,\"type_name\":\"Request letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(58, 148, '2026-05-25', 'external', 'IMO-05252026-0032', 'From Gabawan IFA (GIFA Inc):\r\nA resolution address to NIA requesting the allocation of a backhoe unit for continious work operations', 'Resolution', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-25 17:04:35', '', '{\"id\":148,\"document_number\":\"IMO-05252026-0032\",\"document_name\":\"From Gabawan IFA (GIFA Inc):\\r\\nA resolution address to NIA requesting the allocation of a backhoe unit for continious work operations\",\"document_type_id\":24,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-25 17:04:35\",\"date_received\":\"2026-05-25 17:04:35\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 17:04:35\",\"updated_at\":\"2026-05-25 17:04:35\",\"created_by_emp_id\":26,\"type_name\":\"Resolution\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(59, 149, '2026-05-25', 'incoming', 'IMO-05252026-0033', 'IMT Contract:\r\nItba CIS', 'Others', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-25 17:04:59', 'Contracts', '{\"id\":149,\"document_number\":\"IMO-05252026-0033\",\"document_name\":\"IMT Contract:\\r\\nItba CIS\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-25 17:04:59\",\"date_received\":\"2026-05-25 17:04:59\",\"remarks\":\"Contracts\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-25 17:04:59\",\"updated_at\":\"2026-05-25 17:04:59\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-26 16:46:46'),
(60, 152, '2026-05-28', 'incoming', 'IMO-05282026-0001', 'Travel Order: \r\nENG-TO 775, 771,772,773,774, 776\r\nGate Pass Vlcs: 653-2026, 654-2026\r\nRIS: 464,465-2026', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:49:38', '', '{\"id\":152,\"document_number\":\"IMO-05282026-0001\",\"document_name\":\"Travel Order: \\r\\nENG-TO 775, 771,772,773,774, 776\\r\\nGate Pass Vlcs: 653-2026, 654-2026\\r\\nRIS: 464,465-2026\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:49:38\",\"date_received\":\"2026-05-28 10:49:38\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:49:38\",\"updated_at\":\"2026-05-28 10:49:38\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(61, 153, '2026-05-28', 'incoming', 'IMO-05282026-0002', '\'OT Request:\r\n1. HDMatocinos (5/26/26) \r\n2. RCRenon & 1 (5/27/26)', 'Authority to Render Overtime Services', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:50:34', '', '{\"id\":153,\"document_number\":\"IMO-05282026-0002\",\"document_name\":\"\'OT Request:\\r\\n1. HDMatocinos (5\\/26\\/26) \\r\\n2. RCRenon & 1 (5\\/27\\/26)\",\"document_type_id\":2,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:50:34\",\"date_received\":\"2026-05-28 10:50:34\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:50:34\",\"updated_at\":\"2026-05-28 10:50:34\",\"created_by_emp_id\":26,\"type_name\":\"Authority to Render Overtime Services\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(62, 154, '2026-05-28', 'incoming', 'IMO-05282026-0003', '[Internal]\r\nInspection Report on the complaint of a landowner in Brgt. Pinit, Ligao City concerning the construction of a residential structure adjacent to the irrigation canal', 'Inspection Report', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:50:50', '', '{\"id\":154,\"document_number\":\"IMO-05282026-0003\",\"document_name\":\"[Internal]\\r\\nInspection Report on the complaint of a landowner in Brgt. Pinit, Ligao City concerning the construction of a residential structure adjacent to the irrigation canal\",\"document_type_id\":11,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:50:50\",\"date_received\":\"2026-05-28 10:50:50\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:50:50\",\"updated_at\":\"2026-05-28 10:50:50\",\"created_by_emp_id\":26,\"type_name\":\"Inspection Report\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(63, 155, '2026-05-28', 'incoming', 'IMO-05282026-0004', '\'[Internal]\r\nInspection Report regarding the request of the Balasbas IA Inc. for the inclusion of the proposed brush dam in Kinandingan area in the POW', 'Inspection Report', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:51:02', '', '{\"id\":155,\"document_number\":\"IMO-05282026-0004\",\"document_name\":\"\'[Internal]\\r\\nInspection Report regarding the request of the Balasbas IA Inc. for the inclusion of the proposed brush dam in Kinandingan area in the POW\",\"document_type_id\":11,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:51:02\",\"date_received\":\"2026-05-28 10:51:02\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:51:02\",\"updated_at\":\"2026-05-28 10:51:02\",\"created_by_emp_id\":26,\"type_name\":\"Inspection Report\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(64, 156, '2026-05-28', 'external', 'IMO-05282026-0005', 'From RO:\r\nApproved Variation Order No. 1 of Nabonton SIP \r\nAL-SIP-015-25INF (Jacknel Const.)', 'Variation Orders', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:51:25', '', '{\"id\":156,\"document_number\":\"IMO-05282026-0005\",\"document_name\":\"From RO:\\r\\nApproved Variation Order No. 1 of Nabonton SIP \\r\\nAL-SIP-015-25INF (Jacknel Const.)\",\"document_type_id\":27,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:51:25\",\"date_received\":\"2026-05-28 10:51:25\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:51:25\",\"updated_at\":\"2026-05-28 10:51:25\",\"created_by_emp_id\":26,\"type_name\":\"Variation Orders\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(65, 157, '2026-05-28', 'incoming', 'IMO-05282026-0006', 'Gate Pass Mtrls: 064,065-2026\r\nTO: OMS-TO 852', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:51:37', '', '{\"id\":157,\"document_number\":\"IMO-05282026-0006\",\"document_name\":\"Gate Pass Mtrls: 064,065-2026\\r\\nTO: OMS-TO 852\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:51:37\",\"date_received\":\"2026-05-28 10:51:37\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:51:37\",\"updated_at\":\"2026-05-28 10:51:37\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(66, 158, '2026-05-28', 'incoming', 'IMO-05282026-0007', 'Order of Payment: return of excess Cash Advance \r\nJessica B. Completo (Php 546.00)', 'Petty Cash', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:52:53', '', '{\"id\":158,\"document_number\":\"IMO-05282026-0007\",\"document_name\":\"Order of Payment: return of excess Cash Advance \\r\\nJessica B. Completo (Php 546.00)\",\"document_type_id\":37,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:52:53\",\"date_received\":\"2026-05-28 10:52:53\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:52:53\",\"updated_at\":\"2026-05-28 10:52:53\",\"created_by_emp_id\":26,\"type_name\":\"Petty Cash\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(67, 159, '2026-05-28', 'external', 'IMO-05282026-0008', 'From RO:\r\nSolar Powered Pump Irrigation System AI Monitoring System training on June 3-4, 2026', 'Memorandum', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:53:10', '', '{\"id\":159,\"document_number\":\"IMO-05282026-0008\",\"document_name\":\"From RO:\\r\\nSolar Powered Pump Irrigation System AI Monitoring System training on June 3-4, 2026\",\"document_type_id\":13,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:53:10\",\"date_received\":\"2026-05-28 10:53:10\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:53:10\",\"updated_at\":\"2026-05-28 10:53:10\",\"created_by_emp_id\":26,\"type_name\":\"Memorandum\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(68, 160, '2026-05-28', 'external', 'IMO-05282026-0009', 'From RO:\r\nObservance of the National Disaster Resilience month (NDRRM 2026)', 'Memorandum', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:53:24', '', '{\"id\":160,\"document_number\":\"IMO-05282026-0009\",\"document_name\":\"From RO:\\r\\nObservance of the National Disaster Resilience month (NDRRM 2026)\",\"document_type_id\":13,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:53:24\",\"date_received\":\"2026-05-28 10:53:24\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:53:24\",\"updated_at\":\"2026-05-28 10:53:24\",\"created_by_emp_id\":26,\"type_name\":\"Memorandum\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(69, 161, '2026-05-28', 'external', 'IMO-05282026-0010', 'From RO:\r\nSubmission of pictures of all project billboards for CY 2025 and CY 2026 Projects', 'Memorandum', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:53:40', '', '{\"id\":161,\"document_number\":\"IMO-05282026-0010\",\"document_name\":\"From RO:\\r\\nSubmission of pictures of all project billboards for CY 2025 and CY 2026 Projects\",\"document_type_id\":13,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:53:40\",\"date_received\":\"2026-05-28 10:53:40\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:53:40\",\"updated_at\":\"2026-05-28 10:53:40\",\"created_by_emp_id\":26,\"type_name\":\"Memorandum\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(70, 162, '2026-05-28', 'external', 'IMO-05282026-0011', 'From RO:\r\nFast tracking of water permit applications for Solar Projects', 'Memorandum', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:54:01', '', '{\"id\":162,\"document_number\":\"IMO-05282026-0011\",\"document_name\":\"From RO:\\r\\nFast tracking of water permit applications for Solar Projects\",\"document_type_id\":13,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:54:01\",\"date_received\":\"2026-05-28 10:54:01\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:54:01\",\"updated_at\":\"2026-05-28 10:54:01\",\"created_by_emp_id\":26,\"type_name\":\"Memorandum\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(71, 163, '2026-05-28', 'external', 'IMO-05282026-0012', 'From  RO:\r\nOffice memo no. 069 s 2026\r\nMonitoring of Project accomplishments and catch-up plans for delayed/expired contracts (as of May 15, 2026)', 'Memorandum', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:54:26', '', '{\"id\":163,\"document_number\":\"IMO-05282026-0012\",\"document_name\":\"From  RO:\\r\\nOffice memo no. 069 s 2026\\r\\nMonitoring of Project accomplishments and catch-up plans for delayed\\/expired contracts (as of May 15, 2026)\",\"document_type_id\":13,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:54:26\",\"date_received\":\"2026-05-28 10:54:26\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:54:26\",\"updated_at\":\"2026-05-28 10:54:26\",\"created_by_emp_id\":26,\"type_name\":\"Memorandum\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(72, 164, '2026-05-28', 'incoming', 'IMO-05282026-0013', 'IMT Contracts:\r\nLigban CIS', 'IMT Contracts', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:55:00', '', '{\"id\":164,\"document_number\":\"IMO-05282026-0013\",\"document_name\":\"IMT Contracts:\\r\\nLigban CIS\",\"document_type_id\":38,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:55:00\",\"date_received\":\"2026-05-28 10:55:00\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:55:00\",\"updated_at\":\"2026-05-28 10:55:00\",\"created_by_emp_id\":26,\"type_name\":\"IMT Contracts\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(73, 165, '2026-05-28', 'incoming', 'IMO-05282026-0014', 'IMT Contracts:\r\nNagas Maramba IA Inc.', 'IMT Contracts', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:55:12', '', '{\"id\":165,\"document_number\":\"IMO-05282026-0014\",\"document_name\":\"IMT Contracts:\\r\\nNagas Maramba IA Inc.\",\"document_type_id\":38,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:55:12\",\"date_received\":\"2026-05-28 10:55:12\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:55:12\",\"updated_at\":\"2026-05-28 10:55:12\",\"created_by_emp_id\":26,\"type_name\":\"IMT Contracts\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(74, 166, '2026-05-28', 'outgoing', 'IMO-05282026-0015', 'To RO:\r\nMemorandum of Agreement (MOA)\r\n1. Mabini CIS', 'Memorandum of Agreement (MOA)', 'received', 'Ella RINGAD', NULL, NULL, '2026-05-28 10:56:28', '', '{\"id\":166,\"document_number\":\"IMO-05282026-0015\",\"document_name\":\"To RO:\\r\\nMemorandum of Agreement (MOA)\\r\\n1. Mabini CIS\",\"document_type_id\":39,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-05-28 10:56:28\",\"date_received\":\"2026-05-28 10:56:28\",\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-05-28 10:56:28\",\"updated_at\":\"2026-05-28 10:56:28\",\"created_by_emp_id\":26,\"type_name\":\"Memorandum of Agreement (MOA)\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":null,\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-05-29 08:21:41'),
(75, 172, '2026-05-29', 'incoming', 'IMO-05292026-0001', 'Travel Order: ENG-TO 779, 778, OMS-TO 858\r\nItinerary: OMS-TO 847\r\nGate Pass Mtrls: 066,067-2026', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:24:36', '', '{}', 0, '2026-06-01 09:20:26'),
(76, 173, '2026-05-29', 'external', 'IMO-05292026-0002', '\'To Municipal Mayor of LGU: Poblacion, Camalig, Albay\r\nRequest for keyhole markup language zipped (KMZ) file of Existing irrigation systems in the Municipality of Camalig, Albay', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:24:55', '', '{}', 0, '2026-06-01 09:20:26'),
(77, 174, '2026-05-29', 'external', 'IMO-05292026-0003', 'To Goldex Const & Devt. Corporation:\r\nRequest any available documents that may serve as proof of ownership of the attached letter', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:25:17', '', '{}', 0, '2026-06-01 09:20:26'),
(78, 175, '2026-05-29', 'external', 'IMO-05292026-0004', '\'To Brgy. Saban, Oas:\r\nInforming the office that the portions of the proposed alignment may affect the existing project implemented by the Brgy within the same area', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:25:46', '', '{}', 0, '2026-06-01 09:20:26'),
(79, 176, '2026-05-29', 'external', 'IMO-05292026-0005', 'To Municipality of Oas:\r\nInforming the office that the portions of the proposed alignment may overlap with or affect the existing project implemented by the LGU', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:25:58', '', '{}', 0, '2026-06-01 09:20:26'),
(80, 177, '2026-05-29', 'incoming', 'IMO-05292026-0006', 'Gatepass Vcls: 661-2026\r\nTO: OMS-TO 862\r\nDDTT: 231 to 239-2026', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:26:11', '', '{}', 0, '2026-06-01 09:20:26'),
(81, 178, '2026-05-29', 'outgoing', 'IMO-05292026-0007', 'To RO:\r\nOperations and maintenance monthly report of ACIMO for Apr. 2026', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:26:27', '', '{}', 0, '2026-06-01 09:20:26'),
(82, 179, '2026-05-29', 'incoming', 'IMO-05292026-0008', 'Check & attachments:\r\n1. SSS (Php 750.00)\r\n2.HDMF (Php 2,22.72)\r\n3. NIA F-501 LFP (Php 5,220.12)\r\n4. NIA F501 COB (Php 26,48.75)\r\n5. NIA 501 COB (Php 98,594.80)\r\n6. HDMF (Php 23,700.00)\r\n7. SSS (Php 9,850.00)\r\n8. Ma. Dolores S. Belgado (Php 45,036.00)\r\n9.', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:26:42', '', '{}', 0, '2026-06-01 09:20:26'),
(83, 180, '2026-05-29', 'incoming', 'IMO-05292026-0009', 'LIPA Dry Season 2026\r\nTuliw Pawa IA inc.', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:26:56', '', '{}', 0, '2026-06-01 09:20:26'),
(84, 181, '2026-05-29', 'external', 'IMO-05292026-0010', 'From Magurang  Ubaliw Basud IA Inc.:\r\nRestoration of damaged river embankment', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:27:07', '', '{}', 0, '2026-06-01 09:20:26'),
(85, 182, '2026-05-29', 'external', 'IMO-05292026-0011', 'From BJMP:\r\nInquiring from the office regarding the mentioned contractor who participated or was awarded with any of projects', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:27:21', '', '{}', 0, '2026-06-01 09:20:26'),
(86, 183, '2026-05-29', 'external', 'IMO-05292026-0012', 'From DPWH 2nd DEO:\r\nRecommended design specifications for the restoration of the affected irrigation facilities', NULL, 'received', NULL, NULL, NULL, '2026-05-29 11:27:55', '', '{}', 0, '2026-06-01 09:20:26'),
(87, 196, '2026-06-02', 'incoming', 'IMO-06022026-0001', 'Gate Pass Vcls: 672-2026', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 08:24:49', '', '{\"id\":196,\"document_number\":\"IMO-06022026-0001\",\"document_name\":\"Gate Pass Vcls: 672-2026\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 08:24:49\",\"date_received\":\"2026-06-02 08:24:49\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 08:24:49\",\"updated_at\":\"2026-06-02 08:24:49\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(88, 197, '2026-06-02', 'incoming', 'IMO-06022026-0002', '\'List of Irrigated & Planted Area (LIPA): Dry Season 2026\r\nMabalod Tandarura CIS', 'List of Irrigated & Planted Areas', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 10:51:32', '', '{\"id\":197,\"document_number\":\"IMO-06022026-0002\",\"document_name\":\"\'List of Irrigated & Planted Area (LIPA): Dry Season 2026\\r\\nMabalod Tandarura CIS\",\"document_type_id\":32,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 10:51:32\",\"date_received\":\"2026-06-02 10:51:32\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 10:51:32\",\"updated_at\":\"2026-06-02 10:51:32\",\"created_by_emp_id\":26,\"type_name\":\"List of Irrigated & Planted Areas\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(89, 198, '2026-06-02', 'incoming', 'IMO-06022026-0003', 'TEV\r\nJhedson Cellano (March 2026) Php 1,998.00', 'Costing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 10:51:51', 'for costing:', '{\"id\":198,\"document_number\":\"IMO-06022026-0003\",\"document_name\":\"TEV\\r\\nJhedson Cellano (March 2026) Php 1,998.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 10:51:51\",\"date_received\":\"2026-06-02 10:51:51\",\"received_by_emp_id\":null,\"remarks\":\"for costing:\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 10:51:51\",\"updated_at\":\"2026-06-02 10:51:51\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(90, 199, '2026-06-02', 'incoming', 'IMO-06022026-0004', 'TEV\r\nNoel B. Nash (March 2026) Php 5,053.00', 'Costing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 10:52:28', '\'for costing', '{\"id\":199,\"document_number\":\"IMO-06022026-0004\",\"document_name\":\"TEV\\r\\nNoel B. Nash (March 2026) Php 5,053.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 10:52:28\",\"date_received\":\"2026-06-02 10:52:28\",\"received_by_emp_id\":null,\"remarks\":\"\'for costing\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 10:52:28\",\"updated_at\":\"2026-06-02 10:52:28\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(91, 201, '2026-06-02', 'external', 'IMO-06022026-0005', 'From  RO:\r\nASA# 5-501-2026-154\r\nMOOE Additional for 2nd Quarter CY 2026 (Php 1,452,246.80)', 'Letter', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 11:16:39', '', '{\"id\":201,\"document_number\":\"IMO-06022026-0005\",\"document_name\":\"From  RO:\\r\\nASA# 5-501-2026-154\\r\\nMOOE Additional for 2nd Quarter CY 2026 (Php 1,452,246.80)\",\"document_type_id\":15,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 11:16:39\",\"date_received\":\"2026-06-02 11:16:39\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 11:16:39\",\"updated_at\":\"2026-06-02 11:16:39\",\"created_by_emp_id\":26,\"type_name\":\"Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(92, 202, '2026-06-02', 'external', 'IMO-06022026-0006', 'From RO:\r\nOffice Memorandum No. 072 s 2026\r\nReminder/Guidelines on the issuance of Certificate as to availability of funds (CAF)', 'Memorandum', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 11:17:00', '', '{\"id\":202,\"document_number\":\"IMO-06022026-0006\",\"document_name\":\"From RO:\\r\\nOffice Memorandum No. 072 s 2026\\r\\nReminder\\/Guidelines on the issuance of Certificate as to availability of funds (CAF)\",\"document_type_id\":13,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 11:17:00\",\"date_received\":\"2026-06-02 11:17:00\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 11:17:00\",\"updated_at\":\"2026-06-02 11:17:00\",\"created_by_emp_id\":26,\"type_name\":\"Memorandum\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(93, 203, '2026-06-02', 'external', 'IMO-06022026-0007', 'From RO:\r\nSolar-Powered Pump Irrigation system AI monitoring system Monitoring', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 11:17:29', '', '{\"id\":203,\"document_number\":\"IMO-06022026-0007\",\"document_name\":\"From RO:\\r\\nSolar-Powered Pump Irrigation system AI monitoring system Monitoring\",\"document_type_id\":29,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 11:17:29\",\"date_received\":\"2026-06-02 11:17:29\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 11:17:29\",\"updated_at\":\"2026-06-02 11:17:29\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(94, 204, '2026-06-02', 'outgoing', 'IMO-06022026-0008', 'To COA:\r\nReport of accountability for accountable forms (May 1-31, 2026)', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 11:18:53', 'COA Reports', '{\"id\":204,\"document_number\":\"IMO-06022026-0008\",\"document_name\":\"To COA:\\r\\nReport of accountability for accountable forms (May 1-31, 2026)\",\"document_type_id\":29,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 11:18:53\",\"date_received\":\"2026-06-02 11:18:53\",\"received_by_emp_id\":null,\"remarks\":\"COA Reports\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 11:18:53\",\"updated_at\":\"2026-06-02 11:18:53\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(95, 205, '2026-06-02', 'external', 'IMO-06022026-0009', 'From RO:\r\nRevised plans of Tagas-Alcala Quilicao CIS Dam 1 & 2', 'Plans', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 11:19:16', 'revised plans', '{\"id\":205,\"document_number\":\"IMO-06022026-0009\",\"document_name\":\"From RO:\\r\\nRevised plans of Tagas-Alcala Quilicao CIS Dam 1 & 2\",\"document_type_id\":17,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 11:19:16\",\"date_received\":\"2026-06-02 11:19:16\",\"received_by_emp_id\":null,\"remarks\":\"revised plans\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 11:19:16\",\"updated_at\":\"2026-06-02 11:19:16\",\"created_by_emp_id\":26,\"type_name\":\"Plans\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(96, 206, '2026-06-02', 'incoming', 'IMO-06022026-0010', 'Travel Order: \r\nOMS-TO 884 to 887\r\nGate Pass Vlcs: 673-2026', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 11:19:29', '', '{\"id\":206,\"document_number\":\"IMO-06022026-0010\",\"document_name\":\"Travel Order: \\r\\nOMS-TO 884 to 887\\r\\nGate Pass Vlcs: 673-2026\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 11:19:29\",\"date_received\":\"2026-06-02 11:19:29\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 11:19:29\",\"updated_at\":\"2026-06-02 11:19:29\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(97, 207, '2026-06-02', 'outgoing', 'IMO-06022026-0011', 'To RO: transmittal only\r\nCompliance with Regional office remarks on the Variation Order No. 1 of Alobo-Gapo-Inarado CIS CY 2024', 'Transmittal', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 11:19:45', '', '{\"id\":207,\"document_number\":\"IMO-06022026-0011\",\"document_name\":\"To RO: transmittal only\\r\\nCompliance with Regional office remarks on the Variation Order No. 1 of Alobo-Gapo-Inarado CIS CY 2024\",\"document_type_id\":25,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 11:19:45\",\"date_received\":\"2026-06-02 11:19:45\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 11:19:45\",\"updated_at\":\"2026-06-02 11:19:45\",\"created_by_emp_id\":26,\"type_name\":\"Transmittal\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(98, 208, '2026-06-02', 'incoming', 'IMO-06022026-0012', 'TEV:\r\nJennimel R. Dayupay (Feb. 2026) Php 1,440.00', 'TEV', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:13:12', '', '{\"id\":208,\"document_number\":\"IMO-06022026-0012\",\"document_name\":\"TEV:\\r\\nJennimel R. Dayupay (Feb. 2026) Php 1,440.00\",\"document_type_id\":35,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:13:12\",\"date_received\":\"2026-06-02 16:13:12\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:13:12\",\"updated_at\":\"2026-06-02 16:13:12\",\"created_by_emp_id\":26,\"type_name\":\"TEV\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(99, 209, '2026-06-02', 'incoming', 'IMO-06022026-0013', 'TEV:\r\nJennimel R. Dayupay (Jan. 2026) Php 1,220.00', 'TEV', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:13:24', '', '{\"id\":209,\"document_number\":\"IMO-06022026-0013\",\"document_name\":\"TEV:\\r\\nJennimel R. Dayupay (Jan. 2026) Php 1,220.00\",\"document_type_id\":35,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:13:24\",\"date_received\":\"2026-06-02 16:13:24\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:13:24\",\"updated_at\":\"2026-06-02 16:13:24\",\"created_by_emp_id\":26,\"type_name\":\"TEV\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(100, 210, '2026-06-02', 'incoming', 'IMO-06022026-0014', 'TEV: \r\nMark Christian Marbella (Feb. 2026) Php 2,280.00', 'TEV', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:13:34', '', '{\"id\":210,\"document_number\":\"IMO-06022026-0014\",\"document_name\":\"TEV: \\r\\nMark Christian Marbella (Feb. 2026) Php 2,280.00\",\"document_type_id\":35,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:13:34\",\"date_received\":\"2026-06-02 16:13:34\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:13:34\",\"updated_at\":\"2026-06-02 16:13:34\",\"created_by_emp_id\":26,\"type_name\":\"TEV\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(101, 211, '2026-06-02', 'incoming', 'IMO-06022026-0015', 'To Mr. Eusebio:\r\nApproved resignation letter effective June 30, 2026', 'Letter', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:13:46', '', '{\"id\":211,\"document_number\":\"IMO-06022026-0015\",\"document_name\":\"To Mr. Eusebio:\\r\\nApproved resignation letter effective June 30, 2026\",\"document_type_id\":15,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:13:46\",\"date_received\":\"2026-06-02 16:13:46\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:13:46\",\"updated_at\":\"2026-06-02 16:13:46\",\"created_by_emp_id\":26,\"type_name\":\"Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(102, 212, '2026-06-02', 'incoming', 'IMO-06022026-0016', 'Application for leave:\r\nLFCPante (May 28-29, June 8, 2026)\r\nAJMDelgado (5/18/26)', 'Application for Leave / Compensatory Time Out (CTO)', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:17:31', '', '{\"id\":212,\"document_number\":\"IMO-06022026-0016\",\"document_name\":\"Application for leave:\\r\\nLFCPante (May 28-29, June 8, 2026)\\r\\nAJMDelgado (5\\/18\\/26)\",\"document_type_id\":33,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:17:31\",\"date_received\":\"2026-06-02 16:17:31\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:17:31\",\"updated_at\":\"2026-06-02 16:17:31\",\"created_by_emp_id\":26,\"type_name\":\"Application for Leave \\/ Compensatory Time Out (CTO)\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(103, 213, '2026-06-02', 'incoming', 'IMO-06022026-0017', 'Quincena Accomplishment Report:\r\nAJMDelgado  (May 1-15, 2026)', 'Quincena Accomplishment Report (QAR)', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:19:17', '', '{\"id\":213,\"document_number\":\"IMO-06022026-0017\",\"document_name\":\"Quincena Accomplishment Report:\\r\\nAJMDelgado  (May 1-15, 2026)\",\"document_type_id\":40,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:19:17\",\"date_received\":\"2026-06-02 16:19:17\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:19:17\",\"updated_at\":\"2026-06-02 16:19:17\",\"created_by_emp_id\":26,\"type_name\":\"Quincena Accomplishment Report (QAR)\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(104, 214, '2026-06-02', 'incoming', 'IMO-06022026-0018', 'Payroll & wages:\r\n1. Dmanlangit & others (May 1-15, 2026) Php 10,760.74', 'Payroll', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:19:29', '', '{\"id\":214,\"document_number\":\"IMO-06022026-0018\",\"document_name\":\"Payroll & wages:\\r\\n1. Dmanlangit & others (May 1-15, 2026) Php 10,760.74\",\"document_type_id\":16,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:19:29\",\"date_received\":\"2026-06-02 16:19:29\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:19:29\",\"updated_at\":\"2026-06-02 16:19:29\",\"created_by_emp_id\":26,\"type_name\":\"Payroll\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35');
INSERT INTO `document_archive` (`id`, `original_id`, `archive_date`, `kind`, `document_number`, `document_name`, `document_type`, `status`, `forwarded_by`, `from_section`, `to_section`, `date_forwarded`, `remarks`, `snapshot_json`, `archived_by_emp`, `archived_at`) VALUES
(105, 215, '2026-06-02', 'incoming', 'IMO-06022026-0019', 'Gate Pass Vlcs: 674-2026', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:19:44', '', '{\"id\":215,\"document_number\":\"IMO-06022026-0019\",\"document_name\":\"Gate Pass Vlcs: 674-2026\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:19:44\",\"date_received\":\"2026-06-02 16:19:44\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:19:44\",\"updated_at\":\"2026-06-02 16:19:44\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(106, 216, '2026-06-02', 'incoming', 'IMO-06022026-0020', 'Travel Order:\r\nOMS-TO 890,888, OMS-TO 883, 889', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:19:54', '', '{\"id\":216,\"document_number\":\"IMO-06022026-0020\",\"document_name\":\"Travel Order:\\r\\nOMS-TO 890,888, OMS-TO 883, 889\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:19:54\",\"date_received\":\"2026-06-02 16:19:54\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:19:54\",\"updated_at\":\"2026-06-02 16:19:54\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(107, 217, '2026-06-02', 'incoming', 'IMO-06022026-0021', 'To RO: transmittal only\r\nAgos Sta. Cruz CIS & 11', 'Transmittal', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:20:05', '', '{\"id\":217,\"document_number\":\"IMO-06022026-0021\",\"document_name\":\"To RO: transmittal only\\r\\nAgos Sta. Cruz CIS & 11\",\"document_type_id\":25,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:20:05\",\"date_received\":\"2026-06-02 16:20:05\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:20:05\",\"updated_at\":\"2026-06-02 16:20:05\",\"created_by_emp_id\":26,\"type_name\":\"Transmittal\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(108, 218, '2026-06-02', 'incoming', 'IMO-06022026-0022', 'Travel Order: ADM-TO 893, 797,796,894, OMS-TO 895\r\nItinerary: OMS-TO 891,892\r\nGate Pass Vlcs: 675-2026\r\nTR: 287-2026', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:20:16', '', '{\"id\":218,\"document_number\":\"IMO-06022026-0022\",\"document_name\":\"Travel Order: ADM-TO 893, 797,796,894, OMS-TO 895\\r\\nItinerary: OMS-TO 891,892\\r\\nGate Pass Vlcs: 675-2026\\r\\nTR: 287-2026\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:20:16\",\"date_received\":\"2026-06-02 16:20:16\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:20:16\",\"updated_at\":\"2026-06-02 16:20:16\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(109, 219, '2026-06-02', 'incoming', 'IMO-06022026-0023', 'List of Irrigated & Planted Area (LIPA): Dry Season 2026\r\nLungib IA Inc.', 'List of Irrigated & Planted Areas', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:20:27', '', '{\"id\":219,\"document_number\":\"IMO-06022026-0023\",\"document_name\":\"List of Irrigated & Planted Area (LIPA): Dry Season 2026\\r\\nLungib IA Inc.\",\"document_type_id\":32,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:20:27\",\"date_received\":\"2026-06-02 16:20:27\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:20:27\",\"updated_at\":\"2026-06-02 16:20:27\",\"created_by_emp_id\":26,\"type_name\":\"List of Irrigated & Planted Areas\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(110, 220, '2026-06-02', 'outgoing', 'IMO-06022026-0024', 'To COA:\r\nRequest for retrieval of files/documents', 'Letter', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:20:50', 'comm. to COA', '{\"id\":220,\"document_number\":\"IMO-06022026-0024\",\"document_name\":\"To COA:\\r\\nRequest for retrieval of files\\/documents\",\"document_type_id\":15,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:20:50\",\"date_received\":\"2026-06-02 16:20:50\",\"received_by_emp_id\":null,\"remarks\":\"comm. to COA\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:20:50\",\"updated_at\":\"2026-06-02 16:20:50\",\"created_by_emp_id\":26,\"type_name\":\"Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(111, 221, '2026-06-02', 'incoming', 'IMO-06022026-0025', 'TEV:\r\nMaria Beatrice Robas (Mar-May 2026) Php 3,520.00', 'TEV', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:21:01', '', '{\"id\":221,\"document_number\":\"IMO-06022026-0025\",\"document_name\":\"TEV:\\r\\nMaria Beatrice Robas (Mar-May 2026) Php 3,520.00\",\"document_type_id\":35,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:21:01\",\"date_received\":\"2026-06-02 16:21:01\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:21:01\",\"updated_at\":\"2026-06-02 16:21:01\",\"created_by_emp_id\":26,\"type_name\":\"TEV\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(112, 222, '2026-06-02', 'incoming', 'IMO-06022026-0026', 'Electric Consumption:\r\nALECO (Apr. 30-May 30, 2026) Php 36,696.41', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:21:34', 'bills', '{\"id\":222,\"document_number\":\"IMO-06022026-0026\",\"document_name\":\"Electric Consumption:\\r\\nALECO (Apr. 30-May 30, 2026) Php 36,696.41\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:21:34\",\"date_received\":\"2026-06-02 16:21:34\",\"received_by_emp_id\":null,\"remarks\":\"bills\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:21:34\",\"updated_at\":\"2026-06-02 16:21:34\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(113, 223, '2026-06-02', 'incoming', 'IMO-06022026-0027', 'Water Consumption:\r\nLWCD (May 4 to June 1, 2026) Php 1,343.50', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:21:51', 'bills', '{\"id\":223,\"document_number\":\"IMO-06022026-0027\",\"document_name\":\"Water Consumption:\\r\\nLWCD (May 4 to June 1, 2026) Php 1,343.50\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:21:51\",\"date_received\":\"2026-06-02 16:21:51\",\"received_by_emp_id\":null,\"remarks\":\"bills\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:21:51\",\"updated_at\":\"2026-06-02 16:21:51\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(114, 224, '2026-06-02', 'incoming', 'IMO-06022026-0028', 'Adjustment:\r\nJEVAC2026-04-130 (Php 1,000.00)', 'Report', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:22:14', '', '{\"id\":224,\"document_number\":\"IMO-06022026-0028\",\"document_name\":\"Adjustment:\\r\\nJEVAC2026-04-130 (Php 1,000.00)\",\"document_type_id\":23,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:22:14\",\"date_received\":\"2026-06-02 16:22:14\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:22:14\",\"updated_at\":\"2026-06-02 16:22:14\",\"created_by_emp_id\":26,\"type_name\":\"Report\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(115, 225, '2026-06-02', 'outgoing', 'IMO-06022026-0029', 'To RO:\r\nAs-stake plans of Tagas-Alcala-Quilicao CIS (4 Sets)', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:22:48', '', '{\"id\":225,\"document_number\":\"IMO-06022026-0029\",\"document_name\":\"To RO:\\r\\nAs-stake plans of Tagas-Alcala-Quilicao CIS (4 Sets)\",\"document_type_id\":29,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:22:48\",\"date_received\":\"2026-06-02 16:22:48\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:22:48\",\"updated_at\":\"2026-06-02 16:22:48\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(116, 226, '2026-06-02', 'incoming', 'IMO-06022026-0030', 'Remaining funds:\r\nF501-LFP CARP (Php 75,73.71)', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:23:47', 'funds', '{\"id\":226,\"document_number\":\"IMO-06022026-0030\",\"document_name\":\"Remaining funds:\\r\\nF501-LFP CARP (Php 75,73.71)\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:23:47\",\"date_received\":\"2026-06-02 16:23:47\",\"received_by_emp_id\":null,\"remarks\":\"funds\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:23:47\",\"updated_at\":\"2026-06-02 16:23:47\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(117, 227, '2026-06-02', 'external', 'IMO-06022026-0031', 'From RO:\r\nProperty accountability of Engr. Hilario P. Gorgonia', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:24:04', '', '{\"id\":227,\"document_number\":\"IMO-06022026-0031\",\"document_name\":\"From RO:\\r\\nProperty accountability of Engr. Hilario P. Gorgonia\",\"document_type_id\":29,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:24:04\",\"date_received\":\"2026-06-02 16:24:04\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:24:04\",\"updated_at\":\"2026-06-02 16:24:04\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(118, 228, '2026-06-02', 'incoming', 'IMO-06022026-0032', 'Payroll & wages: May 16-31, 2026\r\nJLAgripa & 5 (Php 44,339.40)\r\nMCAzutea & 8  (Php 45,010.39)\r\nNnayve & 1 (Php 15,546.19)\r\nANBanga & 3 (Php 26,079.46)\r\nMCMarbella & 2 (Php 23,410.36)', 'Payroll', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:24:14', '', '{\"id\":228,\"document_number\":\"IMO-06022026-0032\",\"document_name\":\"Payroll & wages: May 16-31, 2026\\r\\nJLAgripa & 5 (Php 44,339.40)\\r\\nMCAzutea & 8  (Php 45,010.39)\\r\\nNnayve & 1 (Php 15,546.19)\\r\\nANBanga & 3 (Php 26,079.46)\\r\\nMCMarbella & 2 (Php 23,410.36)\",\"document_type_id\":16,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:24:14\",\"date_received\":\"2026-06-02 16:24:14\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:24:14\",\"updated_at\":\"2026-06-02 16:24:14\",\"created_by_emp_id\":26,\"type_name\":\"Payroll\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(119, 229, '2026-06-02', 'incoming', 'IMO-06022026-0033', 'Gate Pass Vlcs:  676 to 678-2026\r\nRIS: 479,480-2026\r\nTR: 278,282,283-2026', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 16:44:16', '', '{\"id\":229,\"document_number\":\"IMO-06022026-0033\",\"document_name\":\"Gate Pass Vlcs:  676 to 678-2026\\r\\nRIS: 479,480-2026\\r\\nTR: 278,282,283-2026\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 16:44:16\",\"date_received\":\"2026-06-02 16:44:16\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 16:44:16\",\"updated_at\":\"2026-06-02 16:44:16\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(120, 230, '2026-06-02', 'incoming', 'IMO-06022026-0034', 'To RO: transmittal only\r\nAs-stake plans of Pajo San Isidro SPIP and  Aroyao SPIP (4 sets)', 'Transmittal', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 18:31:17', '', '{\"id\":230,\"document_number\":\"IMO-06022026-0034\",\"document_name\":\"To RO: transmittal only\\r\\nAs-stake plans of Pajo San Isidro SPIP and  Aroyao SPIP (4 sets)\",\"document_type_id\":25,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 18:31:17\",\"date_received\":\"2026-06-02 18:31:17\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 18:31:17\",\"updated_at\":\"2026-06-02 18:31:17\",\"created_by_emp_id\":26,\"type_name\":\"Transmittal\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(121, 231, '2026-06-02', 'incoming', 'IMO-06022026-0035', 'Report of Disbursement-ADA-PHIC \r\nMay 16-31, 2026', 'Report', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 18:31:35', '', '{\"id\":231,\"document_number\":\"IMO-06022026-0035\",\"document_name\":\"Report of Disbursement-ADA-PHIC \\r\\nMay 16-31, 2026\",\"document_type_id\":23,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 18:31:35\",\"date_received\":\"2026-06-02 18:31:35\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 18:31:35\",\"updated_at\":\"2026-06-02 18:31:35\",\"created_by_emp_id\":26,\"type_name\":\"Report\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(122, 232, '2026-06-02', 'incoming', 'IMO-06022026-0036', 'Payroll & wages: May 16-31, 2026\r\nJcellano & 3 (Php 35,316.78)', 'Payroll', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 18:31:48', '', '{\"id\":232,\"document_number\":\"IMO-06022026-0036\",\"document_name\":\"Payroll & wages: May 16-31, 2026\\r\\nJcellano & 3 (Php 35,316.78)\",\"document_type_id\":16,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 18:31:48\",\"date_received\":\"2026-06-02 18:31:48\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 18:31:48\",\"updated_at\":\"2026-06-02 18:31:48\",\"created_by_emp_id\":26,\"type_name\":\"Payroll\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(123, 233, '2026-06-02', 'incoming', 'IMO-06022026-0037', 'Check & attachments:\r\n1. Carlito Pongpong (Php 2,000.00)\r\n2. Jun Shane M. Peñafiel (Php 2,000.00)\r\n3. Mark Angelo P. Polidario (Php 4,570.00)\r\n4. Francisco B. Juarez Jr. (Php 2,000.00)\r\n5. Rey B. Lanuzo (Php 5,240.00)\r\n6. Aldrin P. Francia (Php 4,760.00)\r', 'Cheques / JEV', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 18:32:05', '', '{\"id\":233,\"document_number\":\"IMO-06022026-0037\",\"document_name\":\"Check & attachments:\\r\\n1. Carlito Pongpong (Php 2,000.00)\\r\\n2. Jun Shane M. Pe\\u00f1afiel (Php 2,000.00)\\r\\n3. Mark Angelo P. Polidario (Php 4,570.00)\\r\\n4. Francisco B. Juarez Jr. (Php 2,000.00)\\r\\n5. Rey B. Lanuzo (Php 5,240.00)\\r\\n6. Aldrin P. Francia (Php 4,760.00)\\r\\n7. Jennimel R. Dayupay (Php 1,900.00)\\r\\n8. Jordan P. Roncesvales (Php 4,540.00)\\r\\n9. Aldrin P. Francia (Php 2,010.00)\",\"document_type_id\":5,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 18:32:05\",\"date_received\":\"2026-06-02 18:32:05\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 18:32:05\",\"updated_at\":\"2026-06-02 18:32:05\",\"created_by_emp_id\":26,\"type_name\":\"Cheques \\/ JEV\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(124, 234, '2026-06-02', 'incoming', 'IMO-06022026-0038', 'To RO: transmittal only\r\nAs-built plans of MNOH Mahaba RIS TAPSSIA RIS (3 SETS)', 'Transmittal', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 18:32:23', '', '{\"id\":234,\"document_number\":\"IMO-06022026-0038\",\"document_name\":\"To RO: transmittal only\\r\\nAs-built plans of MNOH Mahaba RIS TAPSSIA RIS (3 SETS)\",\"document_type_id\":25,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 18:32:23\",\"date_received\":\"2026-06-02 18:32:23\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 18:32:23\",\"updated_at\":\"2026-06-02 18:32:23\",\"created_by_emp_id\":26,\"type_name\":\"Transmittal\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(125, 235, '2026-06-02', 'incoming', 'IMO-06022026-0039', 'To RO:\r\nVariation Order No. 1 of Ologon SPIP, Aroyao SPIP & Pajo San Isidro SPIP with three (3) sets of plan and Contract Time Adjustment No. 2 (EGPIP-AL-CAT-021-25)', 'Variation Orders', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 18:32:39', '', '{\"id\":235,\"document_number\":\"IMO-06022026-0039\",\"document_name\":\"To RO:\\r\\nVariation Order No. 1 of Ologon SPIP, Aroyao SPIP & Pajo San Isidro SPIP with three (3) sets of plan and Contract Time Adjustment No. 2 (EGPIP-AL-CAT-021-25)\",\"document_type_id\":27,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 18:32:39\",\"date_received\":\"2026-06-02 18:32:39\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 18:32:39\",\"updated_at\":\"2026-06-02 18:32:39\",\"created_by_emp_id\":26,\"type_name\":\"Variation Orders\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(126, 236, '2026-06-02', 'incoming', 'IMO-06022026-0040', 'Payroll & attachments: May 16-31, 2026\r\n1. Epeligan & 4 (Php 27,132.33)\r\n2. SMNEstrada & 1 (Php 15,551.86)\r\n3. JAPAtanante & 6 (Php 61,853.40)\r\n4. LKCalabines & 4 (Php 42,574.41)\r\n5. JADOlz & 3 (Php 33,128.16)\r\n6. MABantog & 3 (Php 34,275..34)\r\n7. RGAycar', 'Payroll', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 18:33:06', '', '{\"id\":236,\"document_number\":\"IMO-06022026-0040\",\"document_name\":\"Payroll & attachments: May 16-31, 2026\\r\\n1. Epeligan & 4 (Php 27,132.33)\\r\\n2. SMNEstrada & 1 (Php 15,551.86)\\r\\n3. JAPAtanante & 6 (Php 61,853.40)\\r\\n4. LKCalabines & 4 (Php 42,574.41)\\r\\n5. JADOlz & 3 (Php 33,128.16)\\r\\n6. MABantog & 3 (Php 34,275..34)\\r\\n7. RGAycardi & 2 (Php 18,848.90)\\r\\n8. JKPaje & 4 (Php 31,294.33)\",\"document_type_id\":16,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 18:33:06\",\"date_received\":\"2026-06-02 18:33:06\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 18:33:06\",\"updated_at\":\"2026-06-02 18:33:06\",\"created_by_emp_id\":26,\"type_name\":\"Payroll\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(127, 237, '2026-06-02', 'incoming', 'IMO-06022026-0041', 'Check & attachments:\r\n1. ALECO (Php 36,520.43)\r\n2. LCWD (Php 1,316.63)\r\n3. JCM Const. & Supply Inc. (Php 1,239,449.74)\r\n4. Kratos Devt. Corp (PHp 5,215,413.25)', 'Cheques / JEV', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 18:33:20', '', '{\"id\":237,\"document_number\":\"IMO-06022026-0041\",\"document_name\":\"Check & attachments:\\r\\n1. ALECO (Php 36,520.43)\\r\\n2. LCWD (Php 1,316.63)\\r\\n3. JCM Const. & Supply Inc. (Php 1,239,449.74)\\r\\n4. Kratos Devt. Corp (PHp 5,215,413.25)\",\"document_type_id\":5,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 18:33:20\",\"date_received\":\"2026-06-02 18:33:20\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 18:33:20\",\"updated_at\":\"2026-06-02 18:33:20\",\"created_by_emp_id\":26,\"type_name\":\"Cheques \\/ JEV\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(128, 238, '2026-06-02', 'incoming', 'IMO-06022026-0042', 'To BFP:\r\nInvitation as a resource speaker during our Celeb of Father\'s day on June 18, 2026', 'Letter', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 18:33:31', '', '{\"id\":238,\"document_number\":\"IMO-06022026-0042\",\"document_name\":\"To BFP:\\r\\nInvitation as a resource speaker during our Celeb of Father\'s day on June 18, 2026\",\"document_type_id\":15,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 18:33:31\",\"date_received\":\"2026-06-02 18:33:31\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 18:33:31\",\"updated_at\":\"2026-06-02 18:33:31\",\"created_by_emp_id\":26,\"type_name\":\"Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(129, 239, '2026-06-02', 'incoming', 'IMO-06022026-0043', 'Payroll & wages:\r\nMCRAlvarez & 6 (Php 45,871.24)\r\nMEArcilla & 7 (Php 70,447.47)', 'Payroll', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 18:33:51', '', '{\"id\":239,\"document_number\":\"IMO-06022026-0043\",\"document_name\":\"Payroll & wages:\\r\\nMCRAlvarez & 6 (Php 45,871.24)\\r\\nMEArcilla & 7 (Php 70,447.47)\",\"document_type_id\":16,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 18:33:51\",\"date_received\":\"2026-06-02 18:33:51\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 18:33:51\",\"updated_at\":\"2026-06-02 18:33:51\",\"created_by_emp_id\":26,\"type_name\":\"Payroll\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(130, 240, '2026-06-02', 'incoming', 'IMO-06022026-0044', 'Gate Pass Vcls: 679,680,681-2026\r\nRIS: 481 to 483-2026\r\nTR: 289,290-2026\r\nItinerary: ENG-TO 798 to 810', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-02 18:34:15', '', '{\"id\":240,\"document_number\":\"IMO-06022026-0044\",\"document_name\":\"Gate Pass Vcls: 679,680,681-2026\\r\\nRIS: 481 to 483-2026\\r\\nTR: 289,290-2026\\r\\nItinerary: ENG-TO 798 to 810\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-02 18:34:15\",\"date_received\":\"2026-06-02 18:34:15\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-02 18:34:15\",\"updated_at\":\"2026-06-02 18:34:15\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 29, '2026-06-03 09:39:35'),
(131, 244, '2026-06-03', 'incoming', 'IMO-06032026-0001', 'From Gabawan Irrig Farmers Assoc. Inc. (GIFA):\r\nResolution No. 05 s 2026\r\nA resolution requesting the NIA to provide three (3) units of Water pumps', 'Resolution', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:32:49', '', '{\"id\":244,\"document_number\":\"IMO-06032026-0001\",\"document_name\":\"From Gabawan Irrig Farmers Assoc. Inc. (GIFA):\\r\\nResolution No. 05 s 2026\\r\\nA resolution requesting the NIA to provide three (3) units of Water pumps\",\"document_type_id\":24,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:32:49\",\"date_received\":\"2026-06-03 15:32:49\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:32:49\",\"updated_at\":\"2026-06-03 15:32:49\",\"created_by_emp_id\":26,\"type_name\":\"Resolution\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(132, 245, '2026-06-03', 'external', 'IMO-06032026-0002', '\'From Gabawan Irrig Farmers Assoc. Inc. (GIFA):\r\nRequest for funding assistance slope protection/box culvert replacement', 'Letter', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:33:00', '', '{\"id\":245,\"document_number\":\"IMO-06032026-0002\",\"document_name\":\"\'From Gabawan Irrig Farmers Assoc. Inc. (GIFA):\\r\\nRequest for funding assistance slope protection\\/box culvert replacement\",\"document_type_id\":15,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:33:00\",\"date_received\":\"2026-06-03 15:33:00\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:33:00\",\"updated_at\":\"2026-06-03 15:33:00\",\"created_by_emp_id\":26,\"type_name\":\"Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(133, 246, '2026-06-03', 'external', 'IMO-06032026-0003', 'From RO:\r\nSubmission of POW for OM, desilting works, restoration of non-operational areas and pump operations', 'Memorandum', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:33:16', '', '{\"id\":246,\"document_number\":\"IMO-06032026-0003\",\"document_name\":\"From RO:\\r\\nSubmission of POW for OM, desilting works, restoration of non-operational areas and pump operations\",\"document_type_id\":13,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:33:16\",\"date_received\":\"2026-06-03 15:33:16\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:33:16\",\"updated_at\":\"2026-06-03 15:33:16\",\"created_by_emp_id\":26,\"type_name\":\"Memorandum\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(134, 247, '2026-06-03', 'external', 'IMO-06032026-0004', '\'From RO:\r\nMemorandum\r\nConduct of training-workshop on internal systems audit and root cause analysis (June 9-11, 2026)', 'Memorandum', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:33:29', '', '{\"id\":247,\"document_number\":\"IMO-06032026-0004\",\"document_name\":\"\'From RO:\\r\\nMemorandum\\r\\nConduct of training-workshop on internal systems audit and root cause analysis (June 9-11, 2026)\",\"document_type_id\":13,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:33:29\",\"date_received\":\"2026-06-03 15:33:29\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:33:29\",\"updated_at\":\"2026-06-03 15:33:29\",\"created_by_emp_id\":26,\"type_name\":\"Memorandum\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(135, 248, '2026-06-03', 'external', 'IMO-06032026-0005', 'From RO:\r\nResponse to the request for technical and legal assistance on the devolved CIS in Malilipot, Albay', 'Letter', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:33:50', '', '{\"id\":248,\"document_number\":\"IMO-06032026-0005\",\"document_name\":\"From RO:\\r\\nResponse to the request for technical and legal assistance on the devolved CIS in Malilipot, Albay\",\"document_type_id\":15,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:33:50\",\"date_received\":\"2026-06-03 15:33:50\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:33:50\",\"updated_at\":\"2026-06-03 15:33:50\",\"created_by_emp_id\":26,\"type_name\":\"Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(136, 249, '2026-06-03', 'external', 'IMO-06032026-0006', 'From RO:\r\nReturn for revision: reclassification documents of proposed quinale integrated IS', 'Letter', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:34:06', '', '{\"id\":249,\"document_number\":\"IMO-06032026-0006\",\"document_name\":\"From RO:\\r\\nReturn for revision: reclassification documents of proposed quinale integrated IS\",\"document_type_id\":15,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:34:06\",\"date_received\":\"2026-06-03 15:34:06\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:34:06\",\"updated_at\":\"2026-06-03 15:34:06\",\"created_by_emp_id\":26,\"type_name\":\"Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(137, 250, '2026-06-03', 'external', 'IMO-06032026-0007', 'From RO:\r\nVariation Order No. 1\r\nRepair/Rehab of Oco CIS', 'Variation Orders', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:34:16', '', '{\"id\":250,\"document_number\":\"IMO-06032026-0007\",\"document_name\":\"From RO:\\r\\nVariation Order No. 1\\r\\nRepair\\/Rehab of Oco CIS\",\"document_type_id\":27,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:34:16\",\"date_received\":\"2026-06-03 15:34:16\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:34:16\",\"updated_at\":\"2026-06-03 15:34:16\",\"created_by_emp_id\":26,\"type_name\":\"Variation Orders\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(138, 251, '2026-06-03', 'incoming', 'IMO-06032026-0008', 'Gate Pass Vlcs: 683, 684-2026\r\nRIS: 485,486-2026\r\nTO: OMS-TO 900,901', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:34:26', '', '{\"id\":251,\"document_number\":\"IMO-06032026-0008\",\"document_name\":\"Gate Pass Vlcs: 683, 684-2026\\r\\nRIS: 485,486-2026\\r\\nTO: OMS-TO 900,901\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:34:26\",\"date_received\":\"2026-06-03 15:34:26\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:34:26\",\"updated_at\":\"2026-06-03 15:34:26\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(139, 252, '2026-06-03', 'outgoing', 'IMO-06032026-0009', 'To ASC Const. & Concrete Products/Hugo (JV):\r\nFollow up on the signing of the Variation Order No. 1 for Ologon SPIP with Contract no. EGPIP-AL/CAT-021-25', 'Letter', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:34:36', '', '{\"id\":252,\"document_number\":\"IMO-06032026-0009\",\"document_name\":\"To ASC Const. & Concrete Products\\/Hugo (JV):\\r\\nFollow up on the signing of the Variation Order No. 1 for Ologon SPIP with Contract no. EGPIP-AL\\/CAT-021-25\",\"document_type_id\":15,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:34:36\",\"date_received\":\"2026-06-03 15:34:36\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:34:36\",\"updated_at\":\"2026-06-03 15:34:36\",\"created_by_emp_id\":26,\"type_name\":\"Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(140, 253, '2026-06-03', 'incoming', 'IMO-06032026-0010', 'Additional POWs:\r\nSolar Package B  attachments', 'Program of Works', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:35:11', '', '{\"id\":253,\"document_number\":\"IMO-06032026-0010\",\"document_name\":\"Additional POWs:\\r\\nSolar Package B  attachments\",\"document_type_id\":20,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:35:11\",\"date_received\":\"2026-06-03 15:35:11\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:35:11\",\"updated_at\":\"2026-06-03 15:35:11\",\"created_by_emp_id\":26,\"type_name\":\"Program of Works\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(141, 254, '2026-06-03', 'external', 'IMO-06032026-0011', 'Return of Final Billing of Alobo Gapo Inarado\r\nRREIS-AL-070-18', 'Billing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:36:00', '', '{\"id\":254,\"document_number\":\"IMO-06032026-0011\",\"document_name\":\"Return of Final Billing of Alobo Gapo Inarado\\r\\nRREIS-AL-070-18\",\"document_type_id\":3,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:36:00\",\"date_received\":\"2026-06-03 15:36:00\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:36:00\",\"updated_at\":\"2026-06-03 15:36:00\",\"created_by_emp_id\":26,\"type_name\":\"Billing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(142, 255, '2026-06-03', 'incoming', 'IMO-06032026-0012', 'From RO: returned for compliance\r\nCY 2026 POW (1 SET)\r\n1. Cabilogan-San Juan RIS & 4', 'Program of Works', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:36:21', '', '{\"id\":255,\"document_number\":\"IMO-06032026-0012\",\"document_name\":\"From RO: returned for compliance\\r\\nCY 2026 POW (1 SET)\\r\\n1. Cabilogan-San Juan RIS & 4\",\"document_type_id\":20,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:36:21\",\"date_received\":\"2026-06-03 15:36:21\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:36:21\",\"updated_at\":\"2026-06-03 15:36:21\",\"created_by_emp_id\":26,\"type_name\":\"Program of Works\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(143, 256, '2026-06-03', 'external', 'IMO-06032026-0013', 'From RO: for compliance \r\nCY 2026 POW with plans:\r\nMariroc CIS (1 set)', 'Program of Works', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:36:42', '', '{\"id\":256,\"document_number\":\"IMO-06032026-0013\",\"document_name\":\"From RO: for compliance \\r\\nCY 2026 POW with plans:\\r\\nMariroc CIS (1 set)\",\"document_type_id\":20,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:36:42\",\"date_received\":\"2026-06-03 15:36:42\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:36:42\",\"updated_at\":\"2026-06-03 15:36:42\",\"created_by_emp_id\":26,\"type_name\":\"Program of Works\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(144, 257, '2026-06-03', 'incoming', 'IMO-06032026-0014', 'Travel Order: \r\nOMS-TO 902, OMS-TO 904', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:36:52', '', '{\"id\":257,\"document_number\":\"IMO-06032026-0014\",\"document_name\":\"Travel Order: \\r\\nOMS-TO 902, OMS-TO 904\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:36:52\",\"date_received\":\"2026-06-03 15:36:52\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:36:52\",\"updated_at\":\"2026-06-03 15:36:52\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43');
INSERT INTO `document_archive` (`id`, `original_id`, `archive_date`, `kind`, `document_number`, `document_name`, `document_type`, `status`, `forwarded_by`, `from_section`, `to_section`, `date_forwarded`, `remarks`, `snapshot_json`, `archived_by_emp`, `archived_at`) VALUES
(145, 258, '2026-06-03', 'external', 'IMO-06032026-0015', 'From RO: returned for compliance\r\nCY 2026 POW of South Quinale RIS ( 3sets)', 'Program of Works', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:37:05', '', '{\"id\":258,\"document_number\":\"IMO-06032026-0015\",\"document_name\":\"From RO: returned for compliance\\r\\nCY 2026 POW of South Quinale RIS ( 3sets)\",\"document_type_id\":20,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:37:05\",\"date_received\":\"2026-06-03 15:37:05\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:37:05\",\"updated_at\":\"2026-06-03 15:37:05\",\"created_by_emp_id\":26,\"type_name\":\"Program of Works\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(146, 259, '2026-06-03', 'external', 'IMO-06032026-0016', 'From RO: returned for compliance\r\nCY 2026 POW of Tanabega CS with plans (1 set)', 'Program of Works', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:37:18', '', '{\"id\":259,\"document_number\":\"IMO-06032026-0016\",\"document_name\":\"From RO: returned for compliance\\r\\nCY 2026 POW of Tanabega CS with plans (1 set)\",\"document_type_id\":20,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:37:18\",\"date_received\":\"2026-06-03 15:37:18\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:37:18\",\"updated_at\":\"2026-06-03 15:37:18\",\"created_by_emp_id\":26,\"type_name\":\"Program of Works\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(147, 260, '2026-06-03', 'external', 'IMO-06032026-0017', 'From RO: returned for compliance\r\nCY 2026 POW with plans (1 set)', 'Program of Works', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:37:30', '', '{\"id\":260,\"document_number\":\"IMO-06032026-0017\",\"document_name\":\"From RO: returned for compliance\\r\\nCY 2026 POW with plans (1 set)\",\"document_type_id\":20,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:37:30\",\"date_received\":\"2026-06-03 15:37:30\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:37:30\",\"updated_at\":\"2026-06-03 15:37:30\",\"created_by_emp_id\":26,\"type_name\":\"Program of Works\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(148, 261, '2026-06-03', 'incoming', 'IMO-06032026-0018', 'Travel Order: \r\nOMS-TO 903', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:37:42', '', '{\"id\":261,\"document_number\":\"IMO-06032026-0018\",\"document_name\":\"Travel Order: \\r\\nOMS-TO 903\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:37:42\",\"date_received\":\"2026-06-03 15:37:42\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:37:42\",\"updated_at\":\"2026-06-03 15:37:42\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(149, 262, '2026-06-03', 'external', 'IMO-06032026-0019', 'From RO:\r\nASA# 260087 CARP\r\nCARP-IC under MOOE CARP Training \r\nPhp 988,434.00', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:37:59', '', '{\"id\":262,\"document_number\":\"IMO-06032026-0019\",\"document_name\":\"From RO:\\r\\nASA# 260087 CARP\\r\\nCARP-IC under MOOE CARP Training \\r\\nPhp 988,434.00\",\"document_type_id\":29,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:37:59\",\"date_received\":\"2026-06-03 15:37:59\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:37:59\",\"updated_at\":\"2026-06-03 15:37:59\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(150, 263, '2026-06-03', 'external', 'IMO-06032026-0020', 'From RO:\r\nASA # 5-501-2026-312 (May 28, 2026)\r\nLFP CIS (Restoration of CIS)\r\nPhp 26,667,979.20', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:38:20', '', '{\"id\":263,\"document_number\":\"IMO-06032026-0020\",\"document_name\":\"From RO:\\r\\nASA # 5-501-2026-312 (May 28, 2026)\\r\\nLFP CIS (Restoration of CIS)\\r\\nPhp 26,667,979.20\",\"document_type_id\":29,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:38:20\",\"date_received\":\"2026-06-03 15:38:20\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:38:20\",\"updated_at\":\"2026-06-03 15:38:20\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(151, 264, '2026-06-03', 'external', 'IMO-06032026-0021', 'From RO:\r\nNWRB \r\nWater permit application for posting\r\nV-AL-202-03-200\r\nN&N Rental/Norman C. Devora\r\nShallow Well: Brgy. Tagas Daraga, Albay', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:38:43', 'NWRB', '{\"id\":264,\"document_number\":\"IMO-06032026-0021\",\"document_name\":\"From RO:\\r\\nNWRB \\r\\nWater permit application for posting\\r\\nV-AL-202-03-200\\r\\nN&N Rental\\/Norman C. Devora\\r\\nShallow Well: Brgy. Tagas Daraga, Albay\",\"document_type_id\":29,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:38:43\",\"date_received\":\"2026-06-03 15:38:43\",\"received_by_emp_id\":null,\"remarks\":\"NWRB\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:38:43\",\"updated_at\":\"2026-06-03 15:38:43\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(152, 265, '2026-06-03', 'external', 'IMO-06032026-0022', 'From RO:\r\nMonitoring of contracts as of May 15, 2026', 'Report', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:38:58', '', '{\"id\":265,\"document_number\":\"IMO-06032026-0022\",\"document_name\":\"From RO:\\r\\nMonitoring of contracts as of May 15, 2026\",\"document_type_id\":23,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:38:58\",\"date_received\":\"2026-06-03 15:38:58\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:38:58\",\"updated_at\":\"2026-06-03 15:38:58\",\"created_by_emp_id\":26,\"type_name\":\"Report\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(153, 266, '2026-06-03', 'external', 'IMO-06032026-0023', 'From LGU Polangui:\r\nEndorsement of request for technical evaluation and ocular inspection (attached reso no. 2602 s. 2026)', 'Communication Letter', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:39:27', '', '{\"id\":266,\"document_number\":\"IMO-06032026-0023\",\"document_name\":\"From LGU Polangui:\\r\\nEndorsement of request for technical evaluation and ocular inspection (attached reso no. 2602 s. 2026)\",\"document_type_id\":6,\"kind\":\"external\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:39:27\",\"date_received\":\"2026-06-03 15:39:27\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:39:27\",\"updated_at\":\"2026-06-03 15:39:27\",\"created_by_emp_id\":26,\"type_name\":\"Communication Letter\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(154, 267, '2026-06-03', 'incoming', 'IMO-06032026-0024', 'Travel Order: ADM-TO 112, OMS-TO 903', 'Travel Order / Itinerary', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:39:38', '', '{\"id\":267,\"document_number\":\"IMO-06032026-0024\",\"document_name\":\"Travel Order: ADM-TO 112, OMS-TO 903\",\"document_type_id\":26,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:39:38\",\"date_received\":\"2026-06-03 15:39:38\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:39:38\",\"updated_at\":\"2026-06-03 15:39:38\",\"created_by_emp_id\":26,\"type_name\":\"Travel Order \\/ Itinerary\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(155, 268, '2026-06-03', 'incoming', 'IMO-06032026-0025', 'for costing: TEV\r\nMA. Robledo (Mar 2026) Php 1,278.00', 'Costing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:39:49', '', '{\"id\":268,\"document_number\":\"IMO-06032026-0025\",\"document_name\":\"for costing: TEV\\r\\nMA. Robledo (Mar 2026) Php 1,278.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:39:49\",\"date_received\":\"2026-06-03 15:39:49\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:39:49\",\"updated_at\":\"2026-06-03 15:39:49\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(156, 269, '2026-06-03', 'incoming', 'IMO-06032026-0026', 'for costing: TEV\r\nMHSNate (Apr. 21, 2026) Php 608.00', 'Costing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:40:00', '', '{\"id\":269,\"document_number\":\"IMO-06032026-0026\",\"document_name\":\"for costing: TEV\\r\\nMHSNate (Apr. 21, 2026) Php 608.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:40:00\",\"date_received\":\"2026-06-03 15:40:00\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:40:00\",\"updated_at\":\"2026-06-03 15:40:00\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(157, 270, '2026-06-03', 'incoming', 'IMO-06032026-0027', 'for costing: TEV\r\nJSCellano (Feb 18-Mar 1-7, 2026) Php 9,925.00', 'Costing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 15:40:10', '', '{\"id\":270,\"document_number\":\"IMO-06032026-0027\",\"document_name\":\"for costing: TEV\\r\\nJSCellano (Feb 18-Mar 1-7, 2026) Php 9,925.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 15:40:10\",\"date_received\":\"2026-06-03 15:40:10\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 15:40:10\",\"updated_at\":\"2026-06-03 15:40:10\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(158, 271, '2026-06-03', 'incoming', 'IMO-06032026-0028', 'Travel Order: ENG-TO 812,813,814\r\nGatepass Vlcs: 685,686,687,688-2026\r\nRIS: 487,488,489-2026\r\nTR: 293,294,292-2026', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 18:24:52', '', '{\"id\":271,\"document_number\":\"IMO-06032026-0028\",\"document_name\":\"Travel Order: ENG-TO 812,813,814\\r\\nGatepass Vlcs: 685,686,687,688-2026\\r\\nRIS: 487,488,489-2026\\r\\nTR: 293,294,292-2026\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 18:24:52\",\"date_received\":\"2026-06-03 18:24:52\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 18:24:52\",\"updated_at\":\"2026-06-03 18:24:52\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(159, 272, '2026-06-03', 'outgoing', 'IMO-06032026-0029', 'To Catanduanes:\r\nReturn of 3rd Billing of Agban CIS (CAT-RREIS-010-23INF)', 'Billing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 18:25:08', '', '{\"id\":272,\"document_number\":\"IMO-06032026-0029\",\"document_name\":\"To Catanduanes:\\r\\nReturn of 3rd Billing of Agban CIS (CAT-RREIS-010-23INF)\",\"document_type_id\":3,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 18:25:08\",\"date_received\":\"2026-06-03 18:25:08\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 18:25:08\",\"updated_at\":\"2026-06-03 18:25:08\",\"created_by_emp_id\":26,\"type_name\":\"Billing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(160, 273, '2026-06-03', 'incoming', 'IMO-06032026-0030', 'For costing: TEV:\r\nJun Shane Peñafiel (Dec. 2025) Php 1,960.00', 'Costing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 18:25:23', '', '{\"id\":273,\"document_number\":\"IMO-06032026-0030\",\"document_name\":\"For costing: TEV:\\r\\nJun Shane Pe\\u00f1afiel (Dec. 2025) Php 1,960.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 18:25:23\",\"date_received\":\"2026-06-03 18:25:23\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 18:25:23\",\"updated_at\":\"2026-06-03 18:25:23\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(161, 274, '2026-06-03', 'incoming', 'IMO-06032026-0031', 'For costing: TEV:\r\nJun Shane Peñafiel (Oct to Dec. 2025) Php 1,800.00', 'Costing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 18:25:35', '', '{\"id\":274,\"document_number\":\"IMO-06032026-0031\",\"document_name\":\"For costing: TEV:\\r\\nJun Shane Pe\\u00f1afiel (Oct to Dec. 2025) Php 1,800.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 18:25:35\",\"date_received\":\"2026-06-03 18:25:35\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 18:25:35\",\"updated_at\":\"2026-06-03 18:25:35\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(162, 275, '2026-06-03', 'incoming', 'IMO-06032026-0032', 'For costing: TEV:\r\nJB Peñaflor (Apr. 2026) Php 2,100.00', 'Costing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 18:25:47', '', '{\"id\":275,\"document_number\":\"IMO-06032026-0032\",\"document_name\":\"For costing: TEV:\\r\\nJB Pe\\u00f1aflor (Apr. 2026) Php 2,100.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 18:25:47\",\"date_received\":\"2026-06-03 18:25:47\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 18:25:47\",\"updated_at\":\"2026-06-03 18:25:47\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(163, 276, '2026-06-03', 'outgoing', 'IMO-06032026-0033', 'To COA:\r\nJEV Fund 501 LFP with supporting documents (May 2026)', 'Report', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 18:26:07', '', '{\"id\":276,\"document_number\":\"IMO-06032026-0033\",\"document_name\":\"To COA:\\r\\nJEV Fund 501 LFP with supporting documents (May 2026)\",\"document_type_id\":23,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 18:26:07\",\"date_received\":\"2026-06-03 18:26:07\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 18:26:07\",\"updated_at\":\"2026-06-03 18:26:07\",\"created_by_emp_id\":26,\"type_name\":\"Report\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(164, 277, '2026-06-03', 'incoming', 'IMO-06032026-0034', 'From NWRB: for posting \r\nV-AL-2026-04-115\r\nDeepwell: Brgy: Peñafrancia', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 18:29:10', '', '{\"id\":277,\"document_number\":\"IMO-06032026-0034\",\"document_name\":\"From NWRB: for posting \\r\\nV-AL-2026-04-115\\r\\nDeepwell: Brgy: Pe\\u00f1afrancia\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 18:29:10\",\"date_received\":\"2026-06-03 18:29:10\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 18:29:10\",\"updated_at\":\"2026-06-03 18:29:10\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(165, 278, '2026-06-03', 'incoming', 'IMO-06032026-0035', 'From NWRB: for posting\r\nV-ALB-2026-04-148\r\nBrgy. Cabraran Pequeño, Camalig, Albay', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 18:33:38', '', '{\"id\":278,\"document_number\":\"IMO-06032026-0035\",\"document_name\":\"From NWRB: for posting\\r\\nV-ALB-2026-04-148\\r\\nBrgy. Cabraran Peque\\u00f1o, Camalig, Albay\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 18:33:38\",\"date_received\":\"2026-06-03 18:33:38\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 18:33:38\",\"updated_at\":\"2026-06-03 18:33:38\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(166, 279, '2026-06-03', 'outgoing', 'IMO-06032026-0036', 'From NWRB: for posting\r\nV-ALB-2026-05-017- Paulog\r\nV-ALB-2026-05-018- Tinampo\r\nV-ALB-2026-05-019-20 &022- Maunon\r\nV-ALB-2026-05-021- Abella', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 18:33:54', '', '{\"id\":279,\"document_number\":\"IMO-06032026-0036\",\"document_name\":\"From NWRB: for posting\\r\\nV-ALB-2026-05-017- Paulog\\r\\nV-ALB-2026-05-018- Tinampo\\r\\nV-ALB-2026-05-019-20 &022- Maunon\\r\\nV-ALB-2026-05-021- Abella\",\"document_type_id\":29,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 18:33:54\",\"date_received\":\"2026-06-03 18:33:54\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 18:33:54\",\"updated_at\":\"2026-06-03 18:33:54\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(167, 280, '2026-06-03', 'incoming', 'IMO-06032026-0037', 'DTR & Attachments:\r\n1. MCGS \r\n2. MARobledo & others', 'Quincena Accomplishment Report (QAR)', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-03 18:35:08', '', '{\"id\":280,\"document_number\":\"IMO-06032026-0037\",\"document_name\":\"DTR & Attachments:\\r\\n1. MCGS \\r\\n2. MARobledo & others\",\"document_type_id\":40,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-03 18:35:08\",\"date_received\":\"2026-06-03 18:35:08\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-03 18:35:08\",\"updated_at\":\"2026-06-03 18:35:08\",\"created_by_emp_id\":26,\"type_name\":\"Quincena Accomplishment Report (QAR)\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-04 08:02:43'),
(168, 283, '2026-06-04', 'outgoing', 'IMO-06042026-0001', 'To RO:\r\nCY 2026 POW of SPIP Package A & B', 'Program of Works', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-04 18:24:01', '', '{\"id\":283,\"document_number\":\"IMO-06042026-0001\",\"document_name\":\"To RO:\\r\\nCY 2026 POW of SPIP Package A & B\",\"document_type_id\":20,\"kind\":\"outgoing\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-04 18:24:01\",\"date_received\":\"2026-06-04 18:24:01\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-04 18:24:01\",\"updated_at\":\"2026-06-04 18:24:01\",\"created_by_emp_id\":26,\"type_name\":\"Program of Works\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-09 14:51:13'),
(169, 284, '2026-06-04', 'incoming', 'IMO-06042026-0002', '\'Statement of Account: June 1-3, 2026\r\nAnne A. Santos\r\nMarilou O. Oatemar', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-04 18:24:18', '', '{\"id\":284,\"document_number\":\"IMO-06042026-0002\",\"document_name\":\"\'Statement of Account: June 1-3, 2026\\r\\nAnne A. Santos\\r\\nMarilou O. Oatemar\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-04 18:24:18\",\"date_received\":\"2026-06-04 18:24:18\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-04 18:24:18\",\"updated_at\":\"2026-06-04 18:24:18\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-09 14:51:13'),
(170, 285, '2026-06-04', 'incoming', 'IMO-06042026-0003', 'To COA:\r\nJEV Fund 501 LFP with supporting documents ao May 2026', 'Others', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-04 18:24:43', '', '{\"id\":285,\"document_number\":\"IMO-06042026-0003\",\"document_name\":\"To COA:\\r\\nJEV Fund 501 LFP with supporting documents ao May 2026\",\"document_type_id\":29,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-04 18:24:43\",\"date_received\":\"2026-06-04 18:24:43\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-04 18:24:43\",\"updated_at\":\"2026-06-04 18:24:43\",\"created_by_emp_id\":26,\"type_name\":\"Others\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-09 14:51:13'),
(171, 286, '2026-06-04', 'incoming', 'IMO-06042026-0004', '\'TEV: for costing\r\nJALumbao (May 2026) Php 3,986.00', 'Costing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-04 18:24:53', '', '{\"id\":286,\"document_number\":\"IMO-06042026-0004\",\"document_name\":\"\'TEV: for costing\\r\\nJALumbao (May 2026) Php 3,986.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-04 18:24:53\",\"date_received\":\"2026-06-04 18:24:53\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-04 18:24:53\",\"updated_at\":\"2026-06-04 18:24:53\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-09 14:51:13'),
(172, 287, '2026-06-04', 'incoming', 'IMO-06042026-0005', '\'TEV: for costing\r\nVJPeralta (Mar 2026) Php 7,151.00', 'Costing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-04 18:25:04', '', '{\"id\":287,\"document_number\":\"IMO-06042026-0005\",\"document_name\":\"\'TEV: for costing\\r\\nVJPeralta (Mar 2026) Php 7,151.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-04 18:25:04\",\"date_received\":\"2026-06-04 18:25:04\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-04 18:25:04\",\"updated_at\":\"2026-06-04 18:25:04\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-09 14:51:13'),
(173, 288, '2026-06-04', 'incoming', 'IMO-06042026-0006', 'Daily Driver\'s Trip Ticket:\r\n240 to 250-2026, 251-2026\r\nTO: OMS-TO 907\r\nGate Pass Vlcs: 609,689-2026', 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-04 18:25:15', '', '{\"id\":288,\"document_number\":\"IMO-06042026-0006\",\"document_name\":\"Daily Driver\'s Trip Ticket:\\r\\n240 to 250-2026, 251-2026\\r\\nTO: OMS-TO 907\\r\\nGate Pass Vlcs: 609,689-2026\",\"document_type_id\":10,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-04 18:25:15\",\"date_received\":\"2026-06-04 18:25:15\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-04 18:25:15\",\"updated_at\":\"2026-06-04 18:25:15\",\"created_by_emp_id\":26,\"type_name\":\"Gate Pass \\/ RIS \\/ Trip Ticket \\/ Transpo Req\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-09 14:51:13'),
(174, 289, '2026-06-04', 'incoming', 'IMO-06042026-0007', 'Application for leave: \r\nMRDuran (Inclusion of SL Credits) for twenty-five', 'Application for Leave / Compensatory Time Out (CTO)', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-04 18:25:25', '', '{\"id\":289,\"document_number\":\"IMO-06042026-0007\",\"document_name\":\"Application for leave: \\r\\nMRDuran (Inclusion of SL Credits) for twenty-five\",\"document_type_id\":33,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-04 18:25:25\",\"date_received\":\"2026-06-04 18:25:25\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-04 18:25:25\",\"updated_at\":\"2026-06-04 18:25:25\",\"created_by_emp_id\":26,\"type_name\":\"Application for Leave \\/ Compensatory Time Out (CTO)\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-09 14:51:13'),
(175, 290, '2026-06-04', 'incoming', 'IMO-06042026-0008', 'for costing: TEV\r\nJD Berzuela (Mar-Apr. 2026) Php 6,535.00', 'Costing', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-04 18:25:38', '', '{\"id\":290,\"document_number\":\"IMO-06042026-0008\",\"document_name\":\"for costing: TEV\\r\\nJD Berzuela (Mar-Apr. 2026) Php 6,535.00\",\"document_type_id\":36,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-04 18:25:38\",\"date_received\":\"2026-06-04 18:25:38\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-04 18:25:38\",\"updated_at\":\"2026-06-04 18:25:38\",\"created_by_emp_id\":26,\"type_name\":\"Costing\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-09 14:51:13'),
(176, 291, '2026-06-04', 'incoming', 'IMO-06042026-0009', 'Purchase Request:\r\n2026-06-0100 to 2026-060104', 'Purchase Order', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-04 18:25:58', '', '{\"id\":291,\"document_number\":\"IMO-06042026-0009\",\"document_name\":\"Purchase Request:\\r\\n2026-06-0100 to 2026-060104\",\"document_type_id\":22,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-04 18:25:58\",\"date_received\":\"2026-06-04 18:25:58\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-04 18:25:58\",\"updated_at\":\"2026-06-04 18:25:58\",\"created_by_emp_id\":26,\"type_name\":\"Purchase Order\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-09 14:51:13'),
(177, 292, '2026-06-04', 'incoming', 'IMO-06042026-0010', 'From RO:\r\nOM No. 73 s 2026\r\nCreation of occupational safety and health committee (OSHC)', 'Memorandum', 'received', 'Ella RINGAD', 'NIA-Albay Office', NULL, '2026-06-04 18:26:10', '', '{\"id\":292,\"document_number\":\"IMO-06042026-0010\",\"document_name\":\"From RO:\\r\\nOM No. 73 s 2026\\r\\nCreation of occupational safety and health committee (OSHC)\",\"document_type_id\":13,\"kind\":\"incoming\",\"forwarded_by_emp_id\":26,\"forwarded_to_emp_id\":null,\"forwarded_by_name\":null,\"from_section_id\":null,\"from_unit_id\":null,\"forwarded_to\":\"\",\"forwarded_to_section_id\":null,\"forwarded_to_unit_id\":null,\"forwarded_to_office_id\":null,\"date_forwarded\":\"2026-06-04 18:26:10\",\"date_received\":\"2026-06-04 18:26:10\",\"received_by_emp_id\":null,\"remarks\":\"\",\"status\":\"received\",\"created_by\":null,\"created_at\":\"2026-06-04 18:26:10\",\"updated_at\":\"2026-06-04 18:26:10\",\"created_by_emp_id\":26,\"type_name\":\"Memorandum\",\"resolved_fwd_by\":\"Ella RINGAD\",\"from_section_name\":\"NIA-Albay Office\",\"to_section_name\":null,\"from_unit_name\":null,\"to_unit_name\":null}', 32, '2026-06-09 14:51:13');

-- --------------------------------------------------------

--
-- Table structure for table `document_archive_log`
--

CREATE TABLE `document_archive_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `run_date` date NOT NULL COMMENT 'PHT date that was archived & reset',
  `archived` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'how many docs moved to archive',
  `triggered_by` varchar(20) NOT NULL DEFAULT 'auto' COMMENT 'auto | manual | cron',
  `run_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'when the run happened'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tracks daily midnight archive runs — prevents duplicate runs per day';

--
-- Dumping data for table `document_archive_log`
--

INSERT INTO `document_archive_log` (`id`, `run_date`, `archived`, `triggered_by`, `run_at`) VALUES
(10, '2026-05-20', 18, 'manual', '2026-05-25 11:51:59'),
(11, '2026-05-25', 33, 'manual', '2026-05-26 16:46:46'),
(12, '2026-05-27', 0, 'auto', '2026-05-28 10:31:46'),
(13, '2026-05-28', 15, 'auto', '2026-05-29 08:21:41'),
(14, '2026-05-31', 0, 'auto', '2026-06-01 09:06:47'),
(15, '2026-05-29', 0, 'manual', '2026-06-01 09:20:48'),
(16, '2026-06-01', 0, 'auto', '2026-06-02 08:24:31'),
(17, '2026-06-02', 44, 'auto', '2026-06-03 09:39:35'),
(18, '2026-06-03', 37, 'auto', '2026-06-04 08:02:43'),
(19, '2026-06-07', 0, 'auto', '2026-06-08 12:02:19'),
(20, '2026-06-08', 0, 'manual', '2026-06-08 12:02:56'),
(21, '2026-06-04', 10, 'manual', '2026-06-09 14:51:13');

-- --------------------------------------------------------

--
-- Table structure for table `document_delete_requests`
--

CREATE TABLE `document_delete_requests` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL COMMENT 'FK → document_records.id',
  `requested_by` int(11) NOT NULL COMMENT 'emp_id of the requester',
  `reason` varchar(500) NOT NULL DEFAULT '' COMMENT 'Reason supplied by requester',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'emp_id of the Masteradmin who acted',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `admin_note` varchar(500) DEFAULT NULL COMMENT 'Optional note from Masteradmin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tracks delete requests that require Masteradmin approval';

--
-- Dumping data for table `document_delete_requests`
--

INSERT INTO `document_delete_requests` (`id`, `document_id`, `requested_by`, `reason`, `status`, `reviewed_by`, `reviewed_at`, `admin_note`, `created_at`) VALUES
(13, 75, 32, 'dasdas', 'approved', 32, '2026-05-20 01:04:55', '', '2026-05-20 01:04:49'),
(14, 83, 26, 'agds', 'approved', 32, '2026-05-20 02:40:19', '', '2026-05-20 02:36:14'),
(15, 101, 32, 'asd', 'approved', 32, '2026-05-20 03:44:06', '', '2026-05-20 03:44:00'),
(16, 104, 32, 'asdas', 'approved', 32, '2026-05-20 04:08:18', '', '2026-05-20 04:08:02'),
(17, 103, 100, 'dasda', 'approved', 32, '2026-05-20 04:08:14', '', '2026-05-20 04:08:07'),
(18, 105, 26, 'error', 'approved', 32, '2026-05-20 05:32:46', '', '2026-05-20 05:17:03'),
(19, 106, 26, 'error', 'approved', 32, '2026-05-20 05:32:43', '', '2026-05-20 05:17:26'),
(20, 107, 26, 'error', 'approved', 32, '2026-05-20 05:32:41', '', '2026-05-20 05:17:35'),
(21, 108, 26, 'error', 'approved', 32, '2026-05-20 05:32:39', '', '2026-05-20 05:17:57'),
(22, 109, 26, 'error', 'approved', 32, '2026-05-20 05:32:37', '', '2026-05-20 05:18:50'),
(23, 110, 26, 'error', 'approved', 32, '2026-05-20 05:32:34', '', '2026-05-20 05:20:00'),
(24, 111, 26, 'error', 'approved', 32, '2026-05-20 05:32:31', '', '2026-05-20 05:20:10'),
(25, 114, 26, 'sgag', 'approved', 32, '2026-05-25 05:52:20', '', '2026-05-25 05:49:00'),
(26, 113, 26, 'sgsfdh', 'approved', 32, '2026-05-25 05:52:18', '', '2026-05-25 05:49:05'),
(27, 112, 26, 'dfhs', 'approved', 32, '2026-05-25 05:52:16', '', '2026-05-25 05:49:09'),
(28, 142, 32, 'dasda', 'approved', 32, '2026-05-25 07:59:37', '', '2026-05-25 07:59:20'),
(29, 141, 32, 'asda', 'approved', 32, '2026-05-25 07:59:35', '', '2026-05-25 07:59:27'),
(30, 150, 32, 'a', 'approved', 32, '2026-05-28 01:50:12', '', '2026-05-28 01:50:06'),
(31, 151, 32, 'dada', 'approved', 32, '2026-05-28 02:50:45', '', '2026-05-28 02:50:40'),
(32, 168, 32, 'a', 'approved', 32, '2026-05-29 02:10:49', '', '2026-05-29 02:10:45'),
(33, 167, 26, 'zsd', 'approved', 32, '2026-05-29 02:11:39', '', '2026-05-29 02:11:20'),
(34, 170, 26, 'asdas', 'approved', 32, '2026-05-29 03:10:49', '', '2026-05-29 03:09:36'),
(35, 169, 32, 'asd', 'approved', 32, '2026-05-29 03:11:04', '', '2026-05-29 03:10:52'),
(36, 171, 32, 'dasda', 'approved', 32, '2026-05-29 03:11:02', '', '2026-05-29 03:10:56'),
(37, 184, 32, 'ASDA', 'approved', 32, '2026-05-29 03:52:39', '', '2026-05-29 03:51:16'),
(38, 185, 32, 'asda', 'approved', 32, '2026-05-29 03:53:15', '', '2026-05-29 03:53:07'),
(39, 186, 32, 'a', 'approved', 32, '2026-05-29 03:55:54', '', '2026-05-29 03:55:39'),
(40, 187, 32, 'a', 'approved', 32, '2026-05-29 03:56:39', '', '2026-05-29 03:56:15'),
(41, 188, 32, 'sad', 'approved', 32, '2026-05-29 04:00:03', '', '2026-05-29 03:59:57'),
(42, 189, 32, 'dada', 'approved', 32, '2026-05-29 05:29:08', '', '2026-05-29 05:29:02'),
(43, 190, 32, 'asd', 'approved', 32, '2026-05-29 05:29:43', '', '2026-05-29 05:29:38'),
(44, 191, 32, 'a', 'approved', 32, '2026-05-29 05:31:34', '', '2026-05-29 05:31:21'),
(45, 192, 32, 'a', 'approved', 32, '2026-05-29 05:35:13', '', '2026-05-29 05:35:06'),
(46, 193, 32, 'asd', 'approved', 32, '2026-05-29 07:08:22', '', '2026-05-29 07:08:14'),
(47, 194, 32, 'vb', 'approved', 32, '2026-06-01 01:07:01', '', '2026-06-01 01:06:55'),
(48, 195, 32, 'a', 'approved', 32, '2026-06-01 01:07:38', '', '2026-06-01 01:07:33'),
(49, 200, 26, 'sdg', 'approved', 32, '2026-06-02 03:07:47', '', '2026-06-02 02:53:00'),
(50, 241, 29, 'ASDASDA', 'approved', 32, '2026-06-03 01:44:03', '', '2026-06-03 01:43:21'),
(51, 242, 29, 'ASDAS', 'approved', 32, '2026-06-03 01:44:03', '', '2026-06-03 01:43:29'),
(52, 243, 29, 'SADAS', 'approved', 32, '2026-06-03 01:46:19', '', '2026-06-03 01:45:48'),
(53, 282, 32, 'DSAD', 'approved', 32, '2026-06-04 05:25:16', '', '2026-06-04 05:25:12'),
(54, 281, 26, 'GDF', 'approved', 32, '2026-06-04 10:23:38', '', '2026-06-04 09:56:41');

-- --------------------------------------------------------

--
-- Table structure for table `document_files`
--

CREATE TABLE `document_files` (
  `id` int(11) UNSIGNED NOT NULL,
  `document_id` int(11) NOT NULL COMMENT 'FK → document_records.id',
  `original_name` varchar(260) NOT NULL COMMENT 'Original filename shown to users',
  `stored_name` varchar(260) NOT NULL COMMENT 'UUID-based name on disk',
  `mime_type` varchar(120) NOT NULL DEFAULT '',
  `file_size` bigint(20) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Bytes',
  `uploaded_by` int(11) DEFAULT NULL COMMENT 'emp_id of uploader',
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_forwards`
--

CREATE TABLE `document_forwards` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `fwd_by_emp_id` int(11) DEFAULT NULL,
  `fwd_to_emp_id` int(11) DEFAULT NULL,
  `fwd_to_section_id` int(11) DEFAULT NULL,
  `fwd_to_unit_id` int(11) DEFAULT NULL,
  `fwd_to_office_id` int(11) DEFAULT NULL,
  `fwd_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `fwd_remarks` text DEFAULT NULL,
  `received_by_emp_id` int(11) DEFAULT NULL COMMENT 'Employee who marked this specific forward as received',
  `received_at` timestamp NULL DEFAULT NULL COMMENT 'When this specific forward was marked as received'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_notifications`
--

CREATE TABLE `document_notifications` (
  `id` int(11) NOT NULL,
  `recipient_emp_id` int(11) NOT NULL COMMENT 'Who should see this notification',
  `type` varchar(50) NOT NULL COMMENT 'delete_request | delete_approved | delete_rejected',
  `reference_id` int(11) NOT NULL COMMENT 'document_delete_requests.id',
  `message` varchar(500) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='In-app notifications for the delete-request workflow';

--
-- Dumping data for table `document_notifications`
--

INSERT INTO `document_notifications` (`id`, `recipient_emp_id`, `type`, `reference_id`, `message`, `is_read`, `created_at`) VALUES
(37, 32, 'delete_request', 13, 'Marc David OROGO requested deletion of document \"ADM-05202026-0001\" — asds. Reason: dasdas', 1, '2026-05-20 01:04:49'),
(38, 64, 'delete_request', 13, 'Marc David OROGO requested deletion of document \"ADM-05202026-0001\" — asds. Reason: dasdas', 0, '2026-05-20 01:04:49'),
(39, 32, 'delete_approved', 13, 'Your delete request for document \"ADM-05202026-0001 — asds\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 01:04:55'),
(40, 32, 'delete_request', 14, 'Ella RINGAD requested deletion of document \"IMO-05202026-0009\" — agdasg. Reason: agds', 1, '2026-05-20 02:36:14'),
(41, 64, 'delete_request', 14, 'Ella RINGAD requested deletion of document \"IMO-05202026-0009\" — agdasg. Reason: agds', 0, '2026-05-20 02:36:14'),
(42, 26, 'delete_approved', 14, 'Your delete request for document \"IMO-05202026-0009 — agdasg\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 02:40:19'),
(43, 32, 'delete_request', 15, 'Marc David OROGO requested deletion of document \"ADM-05202026-0001\" — SAMPLE. Reason: asd', 1, '2026-05-20 03:44:00'),
(44, 64, 'delete_request', 15, 'Marc David OROGO requested deletion of document \"ADM-05202026-0001\" — SAMPLE. Reason: asd', 0, '2026-05-20 03:44:00'),
(45, 32, 'delete_approved', 15, 'Your delete request for document \"ADM-05202026-0001 — SAMPLE\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 03:44:06'),
(46, 32, 'delete_request', 16, 'Marc David OROGO requested deletion of document \"ADM-05202026-0001\" — SADA. Reason: asdas', 1, '2026-05-20 04:08:02'),
(47, 64, 'delete_request', 16, 'Marc David OROGO requested deletion of document \"ADM-05202026-0001\" — SADA. Reason: asdas', 0, '2026-05-20 04:08:02'),
(48, 32, 'delete_request', 17, 'Crisna PETILLA requested deletion of document \"ENG-05202026-0001\" — SA. Reason: dasda', 1, '2026-05-20 04:08:07'),
(49, 64, 'delete_request', 17, 'Crisna PETILLA requested deletion of document \"ENG-05202026-0001\" — SA. Reason: dasda', 0, '2026-05-20 04:08:07'),
(50, 100, 'delete_approved', 17, 'Your delete request for document \"ENG-05202026-0001 — SA\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 04:08:14'),
(51, 32, 'delete_approved', 16, 'Your delete request for document \"ADM-05202026-0001 — SADA\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 04:08:18'),
(52, 32, 'delete_request', 18, 'Ella RINGAD requested deletion of document \"IMO-05202026-0019\" — Gate Pass Vlcs: 626-2026\r\nTravel Order: EMG-TO 743. Reason: error', 1, '2026-05-20 05:17:03'),
(53, 64, 'delete_request', 18, 'Ella RINGAD requested deletion of document \"IMO-05202026-0019\" — Gate Pass Vlcs: 626-2026\r\nTravel Order: EMG-TO 743. Reason: error', 0, '2026-05-20 05:17:03'),
(54, 32, 'delete_request', 19, 'Ella RINGAD requested deletion of document \"IMO-05202026-0020\" — From Catantanduanes:\r\n2nd Resubmission of CY 2026 POW of San Roque CIS, Bato, Catanduanes. Reason: error', 1, '2026-05-20 05:17:26'),
(55, 64, 'delete_request', 19, 'Ella RINGAD requested deletion of document \"IMO-05202026-0020\" — From Catantanduanes:\r\n2nd Resubmission of CY 2026 POW of San Roque CIS, Bato, Catanduanes. Reason: error', 0, '2026-05-20 05:17:26'),
(56, 32, 'delete_request', 20, 'Ella RINGAD requested deletion of document \"IMO-05202026-0021\" — From RO:\r\nFIVC and final inspection Report\r\nRepair/Resto of Caningag CIS. Reason: error', 1, '2026-05-20 05:17:35'),
(57, 64, 'delete_request', 20, 'Ella RINGAD requested deletion of document \"IMO-05202026-0021\" — From RO:\r\nFIVC and final inspection Report\r\nRepair/Resto of Caningag CIS. Reason: error', 0, '2026-05-20 05:17:35'),
(58, 32, 'delete_request', 21, 'Ella RINGAD requested deletion of document \"IMO-05202026-0022\" — Travel Order: OMS-TO 803. Reason: error', 1, '2026-05-20 05:17:57'),
(59, 64, 'delete_request', 21, 'Ella RINGAD requested deletion of document \"IMO-05202026-0022\" — Travel Order: OMS-TO 803. Reason: error', 0, '2026-05-20 05:17:57'),
(60, 32, 'delete_request', 22, 'Ella RINGAD requested deletion of document \"IMO-05202026-0023\" — From RO: \r\nFIVC & Final Inspection Report\r\nRepair/Rehab of Tambo CIS. Reason: error', 1, '2026-05-20 05:18:50'),
(61, 64, 'delete_request', 22, 'Ella RINGAD requested deletion of document \"IMO-05202026-0023\" — From RO: \r\nFIVC & Final Inspection Report\r\nRepair/Rehab of Tambo CIS. Reason: error', 0, '2026-05-20 05:18:50'),
(62, 32, 'delete_request', 23, 'Ella RINGAD requested deletion of document \"IMO-05202026-0024\" — From RO:\r\nFIVC and final inspection Report\r\nRepair/Resto of Caningag CIS. Reason: error', 1, '2026-05-20 05:20:00'),
(63, 64, 'delete_request', 23, 'Ella RINGAD requested deletion of document \"IMO-05202026-0024\" — From RO:\r\nFIVC and final inspection Report\r\nRepair/Resto of Caningag CIS. Reason: error', 0, '2026-05-20 05:20:00'),
(64, 32, 'delete_request', 24, 'Ella RINGAD requested deletion of document \"IMO-05202026-0025\" — Travel Order: OMS-TO 803. Reason: error', 1, '2026-05-20 05:20:10'),
(65, 64, 'delete_request', 24, 'Ella RINGAD requested deletion of document \"IMO-05202026-0025\" — Travel Order: OMS-TO 803. Reason: error', 0, '2026-05-20 05:20:10'),
(66, 26, 'delete_approved', 24, 'Your delete request for document \"IMO-05202026-0025 — Travel Order: OMS-TO 803\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 05:32:31'),
(67, 26, 'delete_approved', 23, 'Your delete request for document \"IMO-05202026-0024 — From RO:\r\nFIVC and final inspection Report\r\nRepair/Resto of Caningag CIS\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 05:32:34'),
(68, 26, 'delete_approved', 22, 'Your delete request for document \"IMO-05202026-0023 — From RO: \r\nFIVC & Final Inspection Report\r\nRepair/Rehab of Tambo CIS\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 05:32:37'),
(69, 26, 'delete_approved', 21, 'Your delete request for document \"IMO-05202026-0022 — Travel Order: OMS-TO 803\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 05:32:39'),
(70, 26, 'delete_approved', 20, 'Your delete request for document \"IMO-05202026-0021 — From RO:\r\nFIVC and final inspection Report\r\nRepair/Resto of Caningag CIS\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 05:32:41'),
(71, 26, 'delete_approved', 19, 'Your delete request for document \"IMO-05202026-0020 — From Catantanduanes:\r\n2nd Resubmission of CY 2026 POW of San Roque CIS, Bato, Catanduanes\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 05:32:43'),
(72, 26, 'delete_approved', 18, 'Your delete request for document \"IMO-05202026-0019 — Gate Pass Vlcs: 626-2026\r\nTravel Order: EMG-TO 743\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-20 05:32:46'),
(73, 32, 'delete_request', 25, 'Ella RINGAD requested deletion of document \"IMO-05252026-0003\" — Personnel Locator Slip:\r\nJVPeñaflor (2:00-3:00pm). Reason: sgag', 1, '2026-05-25 05:49:00'),
(74, 64, 'delete_request', 25, 'Ella RINGAD requested deletion of document \"IMO-05252026-0003\" — Personnel Locator Slip:\r\nJVPeñaflor (2:00-3:00pm). Reason: sgag', 0, '2026-05-25 05:49:00'),
(75, 32, 'delete_request', 26, 'Ella RINGAD requested deletion of document \"IMO-05252026-0002\" — Check & attachments:\r\n1. HDMF (Php 1,000.00)\r\n2. HDMF (Php 3,000.00)\r\n3. NIA 501 COB (Php 31,155.57). Reason: sgsfdh', 1, '2026-05-25 05:49:05'),
(76, 64, 'delete_request', 26, 'Ella RINGAD requested deletion of document \"IMO-05252026-0002\" — Check & attachments:\r\n1. HDMF (Php 1,000.00)\r\n2. HDMF (Php 3,000.00)\r\n3. NIA 501 COB (Php 31,155.57). Reason: sgsfdh', 0, '2026-05-25 05:49:05'),
(77, 32, 'delete_request', 27, 'Ella RINGAD requested deletion of document \"IMO-05252026-0001\" — Gate Pass Vlcs: 638 to 641-2026\r\nRIS: 454,455,456,457-2026\r\nTR: 271,272-2026. Reason: dfhs', 1, '2026-05-25 05:49:09'),
(78, 64, 'delete_request', 27, 'Ella RINGAD requested deletion of document \"IMO-05252026-0001\" — Gate Pass Vlcs: 638 to 641-2026\r\nRIS: 454,455,456,457-2026\r\nTR: 271,272-2026. Reason: dfhs', 0, '2026-05-25 05:49:09'),
(79, 26, 'delete_approved', 27, 'Your delete request for document \"IMO-05252026-0001 — Gate Pass Vlcs: 638 to 641-2026\r\nRIS: 454,455,456,457-2026\r\nTR: 271,272-2026\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-25 05:52:16'),
(80, 26, 'delete_approved', 26, 'Your delete request for document \"IMO-05252026-0002 — Check & attachments:\r\n1. HDMF (Php 1,000.00)\r\n2. HDMF (Php 3,000.00)\r\n3. NIA 501 COB (Php 31,155.57)\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-25 05:52:18'),
(81, 26, 'delete_approved', 25, 'Your delete request for document \"IMO-05252026-0003 — Personnel Locator Slip:\r\nJVPeñaflor (2:00-3:00pm)\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-25 05:52:20'),
(82, 32, 'delete_request', 28, 'Marc David OROGO requested deletion of document \"ADM-05252026-0002\" — asdsa. Reason: dasda', 1, '2026-05-25 07:59:20'),
(83, 64, 'delete_request', 28, 'Marc David OROGO requested deletion of document \"ADM-05252026-0002\" — asdsa. Reason: dasda', 0, '2026-05-25 07:59:20'),
(84, 32, 'delete_request', 29, 'Marc David OROGO requested deletion of document \"ADM-05252026-0001\" — asda. Reason: asda', 1, '2026-05-25 07:59:27'),
(85, 64, 'delete_request', 29, 'Marc David OROGO requested deletion of document \"ADM-05252026-0001\" — asda. Reason: asda', 0, '2026-05-25 07:59:27'),
(86, 32, 'delete_approved', 29, 'Your delete request for document \"ADM-05252026-0001 — asda\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-25 07:59:35'),
(87, 32, 'delete_approved', 28, 'Your delete request for document \"ADM-05252026-0002 — asdsa\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-25 07:59:37'),
(88, 32, 'delete_request', 30, 'Marc David OROGO requested deletion of document \"ADM-05262026-0001\" — sa. Reason: a', 1, '2026-05-28 01:50:06'),
(89, 64, 'delete_request', 30, 'Marc David OROGO requested deletion of document \"ADM-05262026-0001\" — sa. Reason: a', 0, '2026-05-28 01:50:06'),
(90, 32, 'delete_approved', 30, 'Your delete request for document \"ADM-05262026-0001 — sa\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-28 01:50:12'),
(91, 32, 'delete_request', 31, 'Marc David OROGO requested deletion of document \"ADM-05282026-0001\" — dasd. Reason: dada', 1, '2026-05-28 02:50:40'),
(92, 64, 'delete_request', 31, 'Marc David OROGO requested deletion of document \"ADM-05282026-0001\" — dasd. Reason: dada', 0, '2026-05-28 02:50:40'),
(93, 32, 'delete_approved', 31, 'Your delete request for document \"ADM-05282026-0001 — dasd\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-28 02:50:45'),
(94, 32, 'delete_request', 32, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asdasd. Reason: a', 1, '2026-05-29 02:10:45'),
(95, 64, 'delete_request', 32, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asdasd. Reason: a', 0, '2026-05-29 02:10:45'),
(96, 32, 'delete_approved', 32, 'Your delete request for document \"ADM-05292026-0001 — asdasd\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 02:10:49'),
(97, 32, 'delete_request', 33, 'Ella RINGAD requested deletion of document \"IMO-05292026-0001\" — 652. Reason: zsd', 1, '2026-05-29 02:11:20'),
(98, 64, 'delete_request', 33, 'Ella RINGAD requested deletion of document \"IMO-05292026-0001\" — 652. Reason: zsd', 0, '2026-05-29 02:11:20'),
(99, 26, 'delete_approved', 33, 'Your delete request for document \"IMO-05292026-0001 — 652\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 02:11:39'),
(100, 32, 'delete_request', 34, 'Ella RINGAD requested deletion of document \"IMO-05292026-0001\" — dasdas. Reason: asdas', 1, '2026-05-29 03:09:36'),
(101, 64, 'delete_request', 34, 'Ella RINGAD requested deletion of document \"IMO-05292026-0001\" — dasdas. Reason: asdas', 0, '2026-05-29 03:09:36'),
(102, 26, 'delete_approved', 34, 'Your delete request for document \"IMO-05292026-0001 — dasdas\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 03:10:49'),
(103, 32, 'delete_request', 35, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — sa. Reason: asd', 1, '2026-05-29 03:10:52'),
(104, 64, 'delete_request', 35, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — sa. Reason: asd', 0, '2026-05-29 03:10:52'),
(105, 32, 'delete_request', 36, 'Marc David OROGO requested deletion of document \"ADM-05292026-0002\" — dasd. Reason: dasda', 1, '2026-05-29 03:10:56'),
(106, 64, 'delete_request', 36, 'Marc David OROGO requested deletion of document \"ADM-05292026-0002\" — dasd. Reason: dasda', 0, '2026-05-29 03:10:56'),
(107, 32, 'delete_approved', 36, 'Your delete request for document \"ADM-05292026-0002 — dasd\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 03:11:02'),
(108, 32, 'delete_approved', 35, 'Your delete request for document \"ADM-05292026-0001 — sa\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 03:11:04'),
(109, 32, 'delete_request', 37, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — ASD. Reason: ASDA', 1, '2026-05-29 03:51:16'),
(110, 64, 'delete_request', 37, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — ASD. Reason: ASDA', 0, '2026-05-29 03:51:16'),
(111, 32, 'delete_approved', 37, 'Your delete request for document \"ADM-05292026-0001 — ASD\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 03:52:39'),
(112, 32, 'delete_request', 38, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asdas. Reason: asda', 1, '2026-05-29 03:53:07'),
(113, 64, 'delete_request', 38, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asdas. Reason: asda', 0, '2026-05-29 03:53:07'),
(114, 32, 'delete_approved', 38, 'Your delete request for document \"ADM-05292026-0001 — asdas\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 03:53:15'),
(115, 32, 'delete_request', 39, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asdas. Reason: a', 1, '2026-05-29 03:55:39'),
(116, 64, 'delete_request', 39, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asdas. Reason: a', 0, '2026-05-29 03:55:39'),
(117, 32, 'delete_approved', 39, 'Your delete request for document \"ADM-05292026-0001 — asdas\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 03:55:54'),
(118, 32, 'delete_request', 40, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — dasd. Reason: a', 1, '2026-05-29 03:56:15'),
(119, 64, 'delete_request', 40, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — dasd. Reason: a', 0, '2026-05-29 03:56:15'),
(120, 32, 'delete_approved', 40, 'Your delete request for document \"ADM-05292026-0001 — dasd\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 03:56:39'),
(121, 32, 'delete_request', 41, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asd. Reason: sad', 1, '2026-05-29 03:59:57'),
(122, 64, 'delete_request', 41, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asd. Reason: sad', 0, '2026-05-29 03:59:57'),
(123, 32, 'delete_approved', 41, 'Your delete request for document \"ADM-05292026-0001 — asd\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 04:00:03'),
(124, 32, 'delete_request', 42, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asda. Reason: dada', 1, '2026-05-29 05:29:02'),
(125, 64, 'delete_request', 42, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asda. Reason: dada', 0, '2026-05-29 05:29:02'),
(126, 32, 'delete_approved', 42, 'Your delete request for document \"ADM-05292026-0001 — asda\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 05:29:08'),
(127, 32, 'delete_request', 43, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asd. Reason: asd', 1, '2026-05-29 05:29:38'),
(128, 64, 'delete_request', 43, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asd. Reason: asd', 0, '2026-05-29 05:29:38'),
(129, 32, 'delete_approved', 43, 'Your delete request for document \"ADM-05292026-0001 — asd\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 05:29:43'),
(130, 32, 'delete_request', 44, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asd. Reason: a', 1, '2026-05-29 05:31:21'),
(131, 64, 'delete_request', 44, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — asd. Reason: a', 0, '2026-05-29 05:31:21'),
(132, 32, 'delete_approved', 44, 'Your delete request for document \"ADM-05292026-0001 — asd\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 05:31:34'),
(133, 32, 'delete_request', 45, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — dsfdssdf. Reason: a', 1, '2026-05-29 05:35:06'),
(134, 64, 'delete_request', 45, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — dsfdssdf. Reason: a', 0, '2026-05-29 05:35:06'),
(135, 32, 'delete_approved', 45, 'Your delete request for document \"ADM-05292026-0001 — dsfdssdf\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 05:35:13'),
(136, 32, 'delete_request', 46, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — sadas. Reason: asd', 1, '2026-05-29 07:08:14'),
(137, 64, 'delete_request', 46, 'Marc David OROGO requested deletion of document \"ADM-05292026-0001\" — sadas. Reason: asd', 0, '2026-05-29 07:08:14'),
(138, 32, 'delete_approved', 46, 'Your delete request for document \"ADM-05292026-0001 — sadas\" has been APPROVED and the document has been permanently deleted.', 1, '2026-05-29 07:08:22'),
(139, 32, 'delete_request', 47, 'Marc David OROGO requested deletion of document \"ADM-05292026-0002\" — asdas. Reason: vb', 1, '2026-06-01 01:06:55'),
(140, 64, 'delete_request', 47, 'Marc David OROGO requested deletion of document \"ADM-05292026-0002\" — asdas. Reason: vb', 0, '2026-06-01 01:06:55'),
(141, 32, 'delete_approved', 47, 'Your delete request for document \"ADM-05292026-0002 — asdas\" has been APPROVED and the document has been permanently deleted.', 1, '2026-06-01 01:07:01'),
(142, 32, 'delete_request', 48, 'Marc David OROGO requested deletion of document \"ADM-06012026-0001\" — a. Reason: a', 1, '2026-06-01 01:07:33'),
(143, 64, 'delete_request', 48, 'Marc David OROGO requested deletion of document \"ADM-06012026-0001\" — a. Reason: a', 0, '2026-06-01 01:07:33'),
(144, 32, 'delete_approved', 48, 'Your delete request for document \"ADM-06012026-0001 — a\" has been APPROVED and the document has been permanently deleted.', 1, '2026-06-01 01:07:38'),
(145, 32, 'delete_request', 49, 'Ella RINGAD requested deletion of document \"IMO-06022026-0005\" — From RO:\r\nOffice Memorandum No. 072 s 2026\r\nReminder/Guidelines on the issuance of Certificate as to availability of funds (CAF). Reason: sdg', 1, '2026-06-02 02:53:00'),
(146, 64, 'delete_request', 49, 'Ella RINGAD requested deletion of document \"IMO-06022026-0005\" — From RO:\r\nOffice Memorandum No. 072 s 2026\r\nReminder/Guidelines on the issuance of Certificate as to availability of funds (CAF). Reason: sdg', 0, '2026-06-02 02:53:00'),
(147, 26, 'delete_approved', 49, 'Your delete request for document \"IMO-06022026-0005 — From RO:\r\nOffice Memorandum No. 072 s 2026\r\nReminder/Guidelines on the issuance of Certificate as to availability of funds (CAF)\" has been APPROVED and the document has been permanently deleted.', 1, '2026-06-02 03:07:47'),
(148, 32, 'delete_request', 50, 'Amy B. CALPE requested deletion of document \"ADM-06032026-0001\" — sada. Reason: ASDASDA', 1, '2026-06-03 01:43:21'),
(149, 64, 'delete_request', 50, 'Amy B. CALPE requested deletion of document \"ADM-06032026-0001\" — sada. Reason: ASDASDA', 0, '2026-06-03 01:43:21'),
(150, 32, 'delete_request', 51, 'Amy B. CALPE requested deletion of document \"ADM-06032026-0002\" — LETTER TO MR. ABRAHAM S. COLLADO REFFERING THE REQUIRED DOCUMENTS FOR. Reason: ASDAS', 1, '2026-06-03 01:43:29'),
(151, 64, 'delete_request', 51, 'Amy B. CALPE requested deletion of document \"ADM-06032026-0002\" — LETTER TO MR. ABRAHAM S. COLLADO REFFERING THE REQUIRED DOCUMENTS FOR. Reason: ASDAS', 0, '2026-06-03 01:43:29'),
(152, 29, 'delete_approved', 51, 'Your delete request for document \"ADM-06032026-0002 — LETTER TO MR. ABRAHAM S. COLLADO REFFERING THE REQUIRED DOCUMENTS FOR\" has been APPROVED and the document has been permanently deleted.', 1, '2026-06-03 01:44:03'),
(153, 29, 'delete_approved', 50, 'Your delete request for document \"ADM-06032026-0001 — sada\" has been APPROVED and the document has been permanently deleted.', 1, '2026-06-03 01:44:03'),
(154, 32, 'delete_request', 52, 'Amy B. CALPE requested deletion of document \"ADM-06032026-0001\" — ASDSA. Reason: SADAS', 1, '2026-06-03 01:45:48'),
(155, 64, 'delete_request', 52, 'Amy B. CALPE requested deletion of document \"ADM-06032026-0001\" — ASDSA. Reason: SADAS', 0, '2026-06-03 01:45:48'),
(156, 29, 'delete_approved', 52, 'Your delete request for document \"ADM-06032026-0001 — ASDSA\" has been APPROVED and the document has been permanently deleted.', 1, '2026-06-03 01:46:19'),
(157, 32, 'delete_request', 53, 'Marc David OROGO requested deletion of document \"ADM-06042026-0001\" — From Gabawan Irrig Farmers Assoc. Inc. (GIFA): Resolution No. 05 s 2026 A resolution requesting the NIA to provide three (3) units of Water pumps. Reason: DSAD', 1, '2026-06-04 05:25:12'),
(158, 64, 'delete_request', 53, 'Marc David OROGO requested deletion of document \"ADM-06042026-0001\" — From Gabawan Irrig Farmers Assoc. Inc. (GIFA): Resolution No. 05 s 2026 A resolution requesting the NIA to provide three (3) units of Water pumps. Reason: DSAD', 0, '2026-06-04 05:25:12'),
(159, 32, 'delete_approved', 53, 'Your delete request for document \"ADM-06042026-0001 — From Gabawan Irrig Farmers Assoc. Inc. (GIFA): Resolution No. 05 s 2026 A resolution requesting the NIA to provide three (3) units of Water pumps\" has been APPROVED and the document has been permanently deleted.', 1, '2026-06-04 05:25:16'),
(160, 32, 'delete_request', 54, 'Ella RINGAD requested deletion of document \"IMO-06042026-0001\" — adggd. Reason: GDF', 1, '2026-06-04 09:56:41'),
(161, 64, 'delete_request', 54, 'Ella RINGAD requested deletion of document \"IMO-06042026-0001\" — adggd. Reason: GDF', 0, '2026-06-04 09:56:41'),
(162, 26, 'delete_approved', 54, 'Your delete request for document \"IMO-06042026-0001 — adggd\" has been APPROVED and the document has been permanently deleted.', 1, '2026-06-04 10:23:38');

-- --------------------------------------------------------

--
-- Table structure for table `document_records`
--

CREATE TABLE `document_records` (
  `id` int(11) NOT NULL,
  `document_number` varchar(100) NOT NULL,
  `document_name` text NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `kind` enum('incoming','outgoing','external') NOT NULL,
  `forwarded_by_emp_id` int(11) DEFAULT NULL,
  `forwarded_to_emp_id` int(11) DEFAULT NULL,
  `forwarded_by_name` varchar(200) DEFAULT NULL,
  `from_section_id` int(11) DEFAULT NULL,
  `from_unit_id` int(11) DEFAULT NULL,
  `forwarded_to` varchar(255) NOT NULL,
  `forwarded_to_section_id` int(11) DEFAULT NULL,
  `forwarded_to_unit_id` int(11) DEFAULT NULL,
  `forwarded_to_office_id` int(11) DEFAULT NULL,
  `date_forwarded` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_received` timestamp NOT NULL DEFAULT current_timestamp(),
  `received_by_emp_id` int(11) DEFAULT NULL COMMENT 'Employee who marked this document as received',
  `remarks` text DEFAULT NULL,
  `status` enum('pending','received','returned','completed','archived') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by_emp_id` int(11) DEFAULT NULL COMMENT 'emp_id of the user who originally added this document'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_records`
--

INSERT INTO `document_records` (`id`, `document_number`, `document_name`, `document_type_id`, `kind`, `forwarded_by_emp_id`, `forwarded_to_emp_id`, `forwarded_by_name`, `from_section_id`, `from_unit_id`, `forwarded_to`, `forwarded_to_section_id`, `forwarded_to_unit_id`, `forwarded_to_office_id`, `date_forwarded`, `date_received`, `received_by_emp_id`, `remarks`, `status`, `created_by`, `created_at`, `updated_at`, `created_by_emp_id`) VALUES
(293, 'IMO-06092026-0001', 'Travel Order:\r\nOMS-TO 936, 828,926,927,932, 934, ENG-TO 830, OMS-TO 916\r\nItinerary: \r\n924,923,920,919,918,929,925,827,917,931,917, ENG-TO 831 to 840\r\nGate Pass Vlcs: 710-2026, 709-2026\r\nRIS: 505-2026\r\nTR: 301-2026', 10, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:09:58', '2026-06-09 09:09:58', NULL, '', 'received', NULL, '2026-06-09 09:09:58', '2026-06-09 09:09:58', 26),
(294, 'IMO-06092026-0002', 'LIPA Dry 2026\r\nFIALOTA', 32, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:10:08', '2026-06-09 09:10:08', NULL, '', 'received', NULL, '2026-06-09 09:10:08', '2026-06-09 09:10:08', 26),
(295, 'IMO-06092026-0003', 'LIPA Dry 2026\r\nBahamas IA Inc.', 32, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:10:18', '2026-06-09 09:10:18', NULL, '', 'received', NULL, '2026-06-09 09:10:18', '2026-06-09 09:10:18', 26),
(296, 'IMO-06092026-0004', '\'LIPA Dry 2026\r\nPandan Bongalon CIS', 32, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:10:30', '2026-06-09 09:10:30', NULL, '', 'received', NULL, '2026-06-09 09:10:30', '2026-06-09 09:10:30', 26),
(297, 'IMO-06092026-0005', '\'Purchase Order:\r\nBits & Bytes IT Solution (Php 1,650,720.00)', 22, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:10:41', '2026-06-09 09:10:41', NULL, '', 'received', NULL, '2026-06-09 09:10:41', '2026-06-09 09:10:41', 26),
(298, 'IMO-06092026-0006', '\'Salaries & Wages:\r\nPdinero May 16-31, 2026) Php 8,836.20)', 16, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:10:53', '2026-06-09 09:10:53', NULL, '', 'received', NULL, '2026-06-09 09:10:53', '2026-06-09 09:10:53', 26),
(299, 'IMO-06092026-0007', '\'Monetization:\r\nDante A. Buenaventura (Php 19,030.24)', 29, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:11:12', '2026-06-09 09:11:12', NULL, 'monetization', 'received', NULL, '2026-06-09 09:11:12', '2026-06-09 09:11:12', 26),
(300, 'IMO-06092026-0008', '\'Life and Retirement premium balances:\r\nGSIS (Php 22,760.97)', 23, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:11:24', '2026-06-09 09:11:24', NULL, '', 'received', NULL, '2026-06-09 09:11:24', '2026-06-09 09:11:24', 26),
(301, 'IMO-06092026-0009', '\'To RO:\r\nRescheduling of the 3-day capacity building on project preparation and implementation for irrigation devt.', 34, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:11:37', '2026-06-09 09:11:37', NULL, '', 'received', NULL, '2026-06-09 09:11:37', '2026-06-09 09:11:37', 26),
(302, 'IMO-06092026-0010', '\'Certification:\r\nCPR Const. & Supply of not incurring negative slippages for Lower Muladbucad Pequeño', 4, 'outgoing', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:11:51', '2026-06-09 09:11:51', NULL, '', 'received', NULL, '2026-06-09 09:11:51', '2026-06-09 09:11:51', 26),
(303, 'IMO-06092026-0011', '\'Travel Order: OMS-TO 937, ENG-TO 842', 26, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:12:12', '2026-06-09 09:12:12', NULL, '', 'received', NULL, '2026-06-09 09:12:12', '2026-06-09 09:12:12', 26),
(304, 'IMO-06092026-0012', '\'Payroll Register:\r\nTRN: 2446-276-26060602-45728', 16, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:12:23', '2026-06-09 09:12:23', NULL, '', 'received', NULL, '2026-06-09 09:12:23', '2026-06-09 09:12:23', 26),
(305, 'IMO-06092026-0013', '\'for costing: TEV\r\nMilany B. Dacillo (Mar 2026) Php', 36, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:12:32', '2026-06-09 09:12:32', NULL, '', 'received', NULL, '2026-06-09 09:12:32', '2026-06-09 09:12:32', 26),
(306, 'IMO-06092026-0014', '\'To RO:\r\nRequest issuance of NIA ICC \r\n1. Alberto Casaul & 8', 4, 'outgoing', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:12:43', '2026-06-09 09:12:43', NULL, '', 'received', NULL, '2026-06-09 09:12:43', '2026-06-09 09:12:43', 26),
(307, 'IMO-06092026-0015', '\'From DAR-PARCC:\r\nRegular PARCOM Meeting with Selected PCIT Members on June 25, 202 (Wednesday), 9:00 am to 3:00pm at BFD Catering Services, Barriada, Legazpi City', 6, 'external', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:12:58', '2026-06-09 09:12:58', NULL, '', 'received', NULL, '2026-06-09 09:12:58', '2026-06-09 09:12:58', 26),
(308, 'IMO-06092026-0016', '\'Travel Order:\r\nFIN-TO 011, ENG-TO 844, ADM-TO 114, OMS-TO 938', 26, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:13:08', '2026-06-09 09:13:08', NULL, '', 'received', NULL, '2026-06-09 09:13:08', '2026-06-09 09:13:08', 26),
(309, 'IMO-06092026-0017', '\'From NWRB: for posting:\r\nV-ALB-2026-014-148\r\nStar infinity spring resort Brgy. Cabraran Pequeño, Camalig, Albay', 6, 'external', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:13:19', '2026-06-09 09:13:19', NULL, '', 'received', NULL, '2026-06-09 09:13:19', '2026-06-09 09:13:19', 26),
(310, 'IMO-06092026-0018', 'Application for leave:\r\nMATAlnas (June 8-9, 2026)', 33, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:13:31', '2026-06-09 09:13:31', NULL, '', 'received', NULL, '2026-06-09 09:13:31', '2026-06-09 09:13:31', 26),
(311, 'IMO-06092026-0019', 'QAR: (May 16-31, 2026)\r\nAJMDelgado & 5', 40, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:13:41', '2026-06-09 09:13:41', NULL, '', 'received', NULL, '2026-06-09 09:13:41', '2026-06-09 09:13:41', 26),
(312, 'IMO-06092026-0020', 'From BFP ROV5:\r\nReply letter to NIA request for verification of the ccontractor\'s performance track record with regards to post-qualifcation conducted by CAVC Const. & BAM Construction  dated June 4, 2026', 6, 'external', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:13:54', '2026-06-09 09:13:54', NULL, '', 'received', NULL, '2026-06-09 09:13:54', '2026-06-09 09:13:54', 26),
(313, 'IMO-06092026-0021', 'From RO:\r\nMemorandum\r\nPost-Qualification Evaluation', 13, 'external', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:14:07', '2026-06-09 09:14:07', NULL, '', 'received', NULL, '2026-06-09 09:14:07', '2026-06-09 09:14:07', 26),
(314, 'IMO-06092026-0022', '3rd & Final Billing: 3 sets including Engg section\r\nRepair/Rehab of MNOH RIS TAPSSIA\r\nAL-REPNIS-023-24INF\r\nCPR Const. & Supply Inc', 3, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:14:18', '2026-06-09 09:14:18', NULL, '', 'received', NULL, '2026-06-09 09:14:18', '2026-06-09 09:14:18', 26),
(315, 'IMO-06092026-0023', 'To RO:\r\nReclassification documents of proposed quinale irrigation systems', 6, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:14:38', '2026-06-09 09:14:38', NULL, '', 'received', NULL, '2026-06-09 09:14:38', '2026-06-09 09:14:38', 26),
(316, 'IMO-06092026-0024', '[Internal]\r\nInspection report for Rigth Quinale CIS', 11, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:14:53', '2026-06-09 09:14:53', NULL, '', 'received', NULL, '2026-06-09 09:14:53', '2026-06-09 09:14:53', 26),
(317, 'IMO-06092026-0025', 'Gate Pass Vcls: 711 to 713-2026\r\nRIS: 506 to 508-2026\r\nTO: OMS-TO 941,942, OMS-TO 939, OMS-TO 940', 10, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:15:03', '2026-06-09 09:15:03', NULL, '', 'received', NULL, '2026-06-09 09:15:03', '2026-06-09 09:15:03', 26),
(318, 'IMO-06092026-0026', 'TEV:\r\nRichard S. Nacario (Oct-Dec. 2025) Php 1,745.00', 35, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:15:12', '2026-06-09 09:15:12', NULL, '', 'received', NULL, '2026-06-09 09:15:12', '2026-06-09 09:15:12', 26),
(319, 'IMO-06092026-0027', 'Activity Proposal:\r\nCY 2026 \"Gulayan sa Patubigan\" Program: Promoting community gardening for sustainable agriculture culminating activity', 21, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:15:27', '2026-06-09 09:15:27', NULL, '', 'received', NULL, '2026-06-09 09:15:27', '2026-06-09 09:15:27', 26),
(320, 'IMO-06092026-0028', 'To RO:\r\nIA Proposal for gulayan sa patbigan program of CY 2026', 21, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:15:42', '2026-06-09 09:15:42', NULL, '', 'received', NULL, '2026-06-09 09:15:42', '2026-06-09 09:15:42', 26),
(321, 'IMO-06092026-0029', '[Internal]\r\nOlogon SPIP Variation Order No. Absence of Contractor\'s signature and additional letter to contractor', 15, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:15:56', '2026-06-09 09:15:56', NULL, '', 'received', NULL, '2026-06-09 09:15:56', '2026-06-09 09:15:56', 26),
(322, 'IMO-06092026-0030', '[Internal]\r\nInspection Report for Right Quinale (Revised)', 11, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:16:07', '2026-06-09 09:16:07', NULL, '', 'received', NULL, '2026-06-09 09:16:07', '2026-06-09 09:16:07', 26),
(323, 'IMO-06092026-0031', 'Payroll & Wages: May 16-31, 2026\r\nVon Jayvee Peralta (Php 11,076.78)', 16, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:16:18', '2026-06-09 09:16:18', NULL, '', 'received', NULL, '2026-06-09 09:16:18', '2026-06-09 09:16:18', 26),
(324, 'IMO-06092026-0032', 'Cash Advance named to Vilma Manlangit \r\n(May 1-15, 2026) Php 40,909.00', 29, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:16:36', '2026-06-09 09:16:36', NULL, '', 'received', NULL, '2026-06-09 09:16:36', '2026-06-09 09:16:36', 26),
(325, 'IMO-06092026-0033', 'Cash Advance named to Vilma Manlangit \r\n(May 16-25, 2026) Php 24,545.40', 29, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:16:45', '2026-06-09 09:16:45', NULL, '', 'received', NULL, '2026-06-09 09:16:45', '2026-06-09 09:16:45', 26),
(326, 'IMO-06092026-0034', 'Remittance:\r\nBIR Tax (May 2026) Php 1,441,248.23', 29, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:18:10', '2026-06-09 09:18:10', NULL, '', 'received', NULL, '2026-06-09 09:18:10', '2026-06-09 09:18:10', 26),
(327, 'IMO-06092026-0035', 'Checks & attachments:\r\n1. Bits & Bytes IT Solution (Php 1,545,781.37)\r\n2. JCM Const. & Supply Inc. (Php 969,527.82)\r\n3. CPR Const & Supply Inc. (Php 5,389,419.66)', 5, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:18:24', '2026-06-09 09:18:24', NULL, '', 'received', NULL, '2026-06-09 09:18:24', '2026-06-09 09:18:24', 26),
(328, 'IMO-06092026-0036', 'Gate Pass Vlcs: 714 to 718-2026\r\nRIS: 509 to 511-2026\r\nTR: 300,291,302\r\nTO: OMS-TO 943', 10, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:18:35', '2026-06-09 09:18:35', NULL, '', 'received', NULL, '2026-06-09 09:18:35', '2026-06-09 09:18:35', 26),
(329, 'IMO-06092026-0037', '1st Progress Billing:\r\nJacknel Construction (Php 9,908,540.89)', 3, 'incoming', 26, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '2026-06-09 09:18:56', '2026-06-09 09:18:56', NULL, '', 'received', NULL, '2026-06-09 09:18:56', '2026-06-09 09:18:56', 26);

-- --------------------------------------------------------

--
-- Table structure for table `document_sections`
--

CREATE TABLE `document_sections` (
  `id` int(11) NOT NULL,
  `section_name` varchar(150) NOT NULL,
  `section_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`id`, `type_name`, `description`, `created_at`) VALUES
(1, 'Applicants', 'Job application documents and related records', '2026-04-30 04:02:21'),
(2, 'Authority to Render Overtime Services', 'Authorization documents for overtime work', '2026-04-30 04:02:21'),
(3, 'Billing', 'Billing statements and invoices', '2026-04-30 04:02:21'),
(4, 'Certifications', 'Certifications and attestations', '2026-04-30 04:02:21'),
(5, 'Cheques / JEV', 'Cheques and Inspection & Evaluation Vouchers', '2026-04-30 04:02:21'),
(6, 'Communication Letter', 'Official communication letters and correspondence', '2026-04-30 04:02:21'),
(7, 'Contract Time Adjustment', 'Documents related to contract time adjustment', '2026-04-30 04:02:21'),
(8, 'FIVC / Final Inspection Report', 'Final Inspection & Verification Certificate and related reports', '2026-04-30 04:02:21'),
(9, 'FSDE', 'Financial Statement of Daily Expenditure', '2026-04-30 04:02:21'),
(10, 'Gate Pass / RIS / Trip Ticket / Transpo Req', 'Gate passes, Requisition and Issuance Slips, trip tickets and transportation requests', '2026-04-30 04:02:21'),
(11, 'Inspection Report', 'Inspection reports and assessment documents', '2026-05-20 02:39:56'),
(12, 'Locator Slip', 'Employee locator slips', '2026-05-20 02:39:56'),
(13, 'Memorandum', 'Official internal communication', '2026-05-20 02:39:56'),
(14, 'Minutes', 'Minutes of meetings and proceedings', '2026-05-20 02:39:56'),
(15, 'Letter', 'Formal correspondence and letters', '2026-05-20 02:39:56'),
(16, 'Payroll', 'Payroll documents and salary-related records', '2026-05-20 02:39:56'),
(17, 'Plans', 'Project plans, blueprints, and design documents', '2026-05-20 02:39:56'),
(18, 'Post Qualification Evaluation', 'Post-qualification evaluation reports and documents', '2026-05-20 02:39:56'),
(19, 'Process Order', 'Process orders and work instructions', '2026-05-20 02:39:56'),
(20, 'Program of Works', 'Detailed program of works documents', '2026-05-20 02:39:56'),
(21, 'Project Proposal', 'Project proposals and concept papers', '2026-05-20 02:39:56'),
(22, 'Purchase Order', 'Purchase orders and procurement documents', '2026-05-20 02:39:56'),
(23, 'Report', 'Submitted reports or assessments', '2026-05-20 02:39:56'),
(24, 'Resolution', 'Official resolutions and decisions', '2026-05-20 02:39:56'),
(25, 'Transmittal', 'Transmittal letters and document routing slips', '2026-05-20 02:39:56'),
(26, 'Travel Order / Itinerary', 'Travel orders and itinerary of travel', '2026-05-20 02:39:56'),
(27, 'Variation Orders', 'Contract variation orders and change orders', '2026-05-20 02:39:56'),
(28, 'Verification of Performance / Track', 'Performance verification and tracking documents', '2026-05-20 02:39:56'),
(29, 'Others', 'Other document types not listed above', '2026-05-20 02:39:56'),
(32, 'List of Irrigated & Planted Areas', 'LIPA', '2026-05-25 06:25:21'),
(33, 'Application for Leave / Compensatory Time Out (CTO)', NULL, '2026-05-25 06:33:39'),
(34, 'Request letter', NULL, '2026-05-25 06:43:05'),
(35, 'TEV', NULL, '2026-05-25 06:49:19'),
(36, 'Costing', NULL, '2026-05-25 06:49:36'),
(37, 'Petty Cash', NULL, '2026-05-28 02:52:28'),
(38, 'IMT Contracts', NULL, '2026-05-28 02:54:48'),
(39, 'Memorandum of Agreement (MOA)', NULL, '2026-05-28 02:56:04'),
(40, 'Quincena Accomplishment Report (QAR)', NULL, '2026-06-02 08:18:56'),
(41, 'Email', NULL, '2026-06-03 01:49:55');

-- --------------------------------------------------------

--
-- Table structure for table `employee`
--

CREATE TABLE `employee` (
  `emp_id` int(11) NOT NULL,
  `picture` varchar(255) NOT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `ext_name` varchar(10) DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `address` text NOT NULL,
  `bday` date NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `employment_status_id` int(11) DEFAULT NULL,
  `appointment_status_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `office_id` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `unit_section_id` int(11) DEFAULT NULL,
  `is_manager` tinyint(1) DEFAULT 0,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `is_manager_office_staff` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`emp_id`, `picture`, `id_number`, `first_name`, `middle_name`, `last_name`, `ext_name`, `gender`, `address`, `bday`, `email`, `phone_number`, `employment_status_id`, `appointment_status_id`, `section_id`, `office_id`, `position_id`, `unit_section_id`, `is_manager`, `reset_token`, `reset_token_expiry`, `is_manager_office_staff`) VALUES
(23, '6860d112a681b_Sircloyd.png', '596165', 'Mark Cloyd ', 'G.', 'SO', '', 'Male', 'cam norte', '2000-06-26', 'email@example.com', '555-1002', 1, 10, NULL, 1, 3, NULL, 1, NULL, NULL, 0),
(24, '683e6ef11e368_2020-nia-logo.png', '104282', 'Mark', 'L', 'SALEM', '', 'Male', 'Albay', '1991-12-03', 'email@example.com', '555-1001', 1, 5, 4, 1, 23, NULL, 0, NULL, NULL, 0),
(25, '6860d355ae142_CREDO, P.png', '692846', 'Patricia Gillyn', 'L', 'CREDO', '', 'Female', 'Camsur', '2000-01-01', 'email@example.com', '09123456789', 1, 7, NULL, 1, 1, NULL, 0, NULL, NULL, 1),
(26, '6860ccfe1fd0c_30. ELLA N. RINGAD.JPG', '921488', 'Ella ', 'N', 'RINGAD', '', 'Female', 'Ligao', '1998-09-17', 'email@example.com', '987897', 1, 5, NULL, 1, 1, NULL, 0, '6ddddc8c24bbf94a7632f21574d46b55710cf6e32d757f9cc0711e19d1f865b0', '2025-10-20 09:37:53', 1),
(27, '6860d369e1eca_PEÑAFLOR, J.png', '785273', 'Jessica', 'V', 'PEÑAFLOR', '', 'Male', 'camsur', '2001-09-10', 'email@example.com', '09654800074', 1, 7, NULL, 1, 27, NULL, 0, '800876e752e564bfa2a1ec89993bd124d4d71e9d4b6830fda2c2c6f86eb15dce', '2025-10-11 09:58:12', 1),
(28, '6860e76730f25_10. MYRA M. ETCOBANEZ (B).JPG', '406321', 'Myra', 'M', 'ETCOBANEZ', '', 'Male', 'Camalig', '2000-01-01', 'email@example.com', '987898', 1, 5, 1, 1, 28, 46, 0, NULL, NULL, 0),
(29, '6a1f8828e15e1_9. AMY B. CALPE.JPG', '705491', 'Amy ', 'B.', 'CALPE', '', 'Female', 'legazpip', '2026-06-03', 'email@example.com', '987899', 1, 5, 1, 1, 7, 46, 0, NULL, NULL, 0),
(30, '69ae29f6750b5_14. RICHARD NACARRIO.JPG', '970465', 'Richard ', 'S', 'NACARIO', '', 'Male', 'ligao', '2000-08-08', 'email@example.com', '987900', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(31, '6860d1d88d7e2_BONAPOS, R.png', '677630', 'Reese', 'P.', 'BONAPOS', '', 'Female', 'Ligao', '1999-11-08', 'email@example.com', '987901', 1, 5, 1, 1, 1, 46, 0, '73cd50383b5d0b712d9c790f277461656e492f2d4ef354ca322f4065c02b3de2', '2025-10-20 09:44:36', 0),
(32, '6860cd253ebac_OROGO, MARC DAVID.png', '616630', 'Marc David ', 'O', 'OROGO', '', 'Male', 'Guinobatan', '1996-08-27', 'email@example.com', '09167334121', 1, 7, 1, 1, 37, 46, 0, '3b61e42caa8521acad8c7312348cb28b0f269dc4fe106885b074db17dff642d6', '2025-10-20 09:04:48', 0),
(33, '', '847101', 'Diana Rose P.', '', 'PAGAL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987903', 1, 5, 1, 1, 1, 46, 0, NULL, NULL, 0),
(34, '', '472890', 'Senen A.', '', 'BALONDO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987904', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(35, '', '578550', 'Jojo O.', '', 'PAJE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987905', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(36, '', '706109', 'Marcos B.', '', 'BALITA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987906', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(37, '', '413637', 'Dante A.', '', 'SAN BUENAVENTURA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987907', 1, 5, 1, 1, 30, NULL, 0, NULL, NULL, 0),
(38, '', '196770', 'Isagani C.', '', 'CULLAT', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987908', 1, 7, 1, 1, 32, NULL, 0, NULL, NULL, 0),
(39, '', '892078', 'Bryann Frederick R.', '', 'DINGLASAN', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987909', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(40, '', '853739', 'Christian Levy Jr. B.', '', 'LONTAC', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987910', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(41, '', '529551', 'Nando M.', '', 'NAYVE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987911', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(42, '6860e78f75c9a_27. LOUIE DEDASE.JPG', '411215', 'Luisito ', 'O', 'DEDASE', '', 'Male', 'Camsur', '2000-01-01', 'email@example.com', '987912', 1, 5, 1, 1, 31, 53, 0, NULL, NULL, 0),
(43, '68eb373195e29_26. ELA MAE S. ABILA.jpg', '990269', 'Ela Mae ', 'S.', 'ABILA', '', 'Female', 'qq', '2000-02-02', 'email@example.com', '987913', 1, 5, 1, 1, 29, 53, 0, NULL, NULL, 0),
(44, '', '515714', 'Mark Charl\'s N.', '', 'AZUTEA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987914', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(45, '6860d0aa6019a_PITALLANO, V.png', '771459', 'Vida ', 'E', 'PITALLANO', '', 'Female', 'Naga City', '2000-01-01', 'email@example.com', '987915', 1, 5, 2, 1, 1, NULL, 0, NULL, NULL, 0),
(46, '6860d15966f5d_21. ALEXANDRA JOY M. DELGADO.JPG', '489240', 'Alexandra Joy ', 'M', 'DELGADO', '', 'Female', 'legazpi', '2000-01-01', 'email@example.com', '987916', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(47, '6a0c330803371_ALVAREZ, MK.png', '199052', 'Ma. Cristina ', 'R.', 'ALVAREZ', '', 'Female', 'ph', '1999-10-17', 'email@example.com', '987917', 1, 7, 2, 1, 46, NULL, 0, NULL, NULL, 0),
(48, '', '416828', 'Darlene Mae ', 'C', 'MAYOR', '', 'Male', 'cam sur', '2000-05-01', 'email@example.com', '987918', 1, 7, 2, 1, 9, NULL, 0, NULL, NULL, 0),
(49, '', '862342', 'John Paul R.', '', 'PAPA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987919', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(50, '', '861143', 'Maria Beatrice', '', 'ROBAS', '', 'Male', 'aa', '2026-03-12', 'email@example.com', '987920', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(51, '', '282537', 'April Jane B.', '', 'RODA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987921', 1, 7, 2, 1, 13, NULL, 0, NULL, NULL, 0),
(52, '68e4bfdb564b1_4 VILMA M. MANLANGIT.JPG', '929272', 'Vilma ', 'M.', 'MANLANGIT', '', 'Female', 'Catanduanes', '2000-01-01', 'email@example.com', '987922', 1, 5, 2, 1, 1, 50, 0, NULL, NULL, 0),
(53, '', '243192 ', 'Rejean L.', '', 'MARIÑAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987923', 1, 7, 2, 1, 11, NULL, 0, NULL, NULL, 0),
(54, '6860d08955558_32. LECH FIDEL PANTE.JPG', '164959', 'Lech Fidel', 'C', 'PANTE', '', 'Male', 'Naga City', '2000-01-01', 'email@example.com', '987924', 1, 10, 3, 1, 1, NULL, 0, 'd646fc7d6f422bc1754059b004566519ad2431911bcbbe9e0f1440bb97a878c9', '2026-03-17 08:56:18', 0),
(55, '6860d19789058_JUAREZ, JA.png', '936322', 'Julie Anne ', 'D', 'Juarez', '', 'Female', 'Ligao', '0001-12-01', 'email@example.com', '987925', 1, 5, 3, 1, 1, NULL, 0, NULL, NULL, 0),
(56, '6860e7ddb8672_35. JESSICA B. COMPLETO.JPG', '488433', 'Jessica ', 'B', 'COMPLETO', '', 'Female', 'Tabaco', '2000-01-01', 'email@example.com', '987926', 1, 5, 3, 1, 1, 52, 0, 'baa85edcee1ac30228435d2a56c44f84dd0bf11f4efec7d2b0f91a7a00f3304b', '2025-10-15 09:18:59', 0),
(57, '', '568533', 'Jane Amy R. ', '', 'SARION', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987927', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(58, '', '505503', 'Roland O.', '', 'CLARIÑO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987928', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(59, '68c0dac31ff8d_EUSEBIO, W.png', '655711', 'Walter', 'P', 'EUSEBIO', '', 'Male', 'tuburan', '2002-01-10', 'email@example.com', '987929', 1, 5, 3, 1, 1, NULL, 0, '536a7b58e80359b4dde44276d451f23be7c2c06033f07aa543c0922d17fbec77', '2025-10-20 09:40:24', 0),
(60, '', '331046', 'Joel O.', '', 'OLAVIAGA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987930', 1, 5, 3, 1, 15, NULL, 0, NULL, NULL, 0),
(61, '', '300179 ', 'Richard R.', '', 'RESENTES', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987931', 1, 5, 3, 1, 19, NULL, 0, NULL, NULL, 0),
(62, '', '983758', 'Raymond Gil C.', '', 'AYCARDO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987932', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(63, '', '453727', 'Arnulfo Natividad B.', '', 'BANGA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987933', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(64, '68f303772fd2f_436382184_829285465286365_6559261396441469346_n.jpg', '864981', 'John Patrick', 'B.', 'CABILES', '', 'Male', 'asdas', '2000-01-02', 'email@example.com', '987934', 1, 5, 3, 1, 45, NULL, 0, NULL, NULL, 0),
(65, '', '298598', 'Hendryx D.', '', 'CAPINO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987935', 1, 7, 3, 1, 2, NULL, 0, NULL, NULL, 0),
(66, '', '778342', 'Don R.', '', 'CONCEPCION', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987936', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(67, '', '398357', 'Frederick V.', '', 'DAGUMBOY', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987937', 1, 7, 3, 1, 42, NULL, 0, NULL, NULL, 0),
(68, '', '556790', 'Froilan S.', '', 'GESTIADA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987938', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(69, '', '466181', 'Ronald A.', '', 'LLEVA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987939', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(70, '', '746795', 'Joseph', '', 'MORAL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987940', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(71, '', '865161', 'Chlowell Ferby B.', '', 'NASOL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987941', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(72, '', '522191', 'Mark Renen Q.', '', 'NAVARRO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987942', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(73, '', '587562', 'Gregory Mark', '', 'OCAMPO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987943', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(74, '', '719165', 'Eduardo J.', '', 'PELIGAN', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987944', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(75, '', '177646', 'Sammy P.', '', 'PELIGAN', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987945', 1, 7, 1, 1, 42, NULL, 0, NULL, NULL, 0),
(76, '', '382370', 'Raymond B.', '', 'PEPAÑO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987946', 1, 7, 3, 1, 15, NULL, 0, NULL, NULL, 0),
(77, '', '570364', 'Haji P.', '', 'POLIDARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987947', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(78, '', '570984', 'Rizaldy P.', '', 'POLIDARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987948', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(79, '', '200580', 'Luisito P.', '', 'PROPOGO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987949', 1, 7, 3, 1, 19, NULL, 0, NULL, NULL, 0),
(80, '686cb444b336a_SALIRE, MD.png', '359326', 'Mac Daryll ', 'c.', 'SALIRE', '', 'Male', 'OAS', '2000-05-22', 'email@example.com', '9305224889', 5, 7, NULL, 1, 15, NULL, 0, NULL, NULL, 0),
(81, '', '705059', 'Donel P.', '', 'VIBAR', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987951', 1, 5, 3, 1, 1, NULL, 0, NULL, NULL, 0),
(82, '6860e7fc7f05e_60. JORDAN P. RONCESVALLES.JPG', '740531', 'Jordan ', 'P', 'RONCESVALLES', '', 'Male', 'oas', '2000-01-01', 'email@example.com', '987952', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(83, '6878ab1175327_38. REGINE RENON.jpg', '429389', 'Regine ', 'Chavez', 'RENON', '', 'Female', 'Ligao', '1990-03-23', 'email@example.com', '987953', 1, 5, 3, 1, 18, NULL, 0, NULL, NULL, 0),
(84, '6878a957c38ec_39. ALEXANDRA MAE L. DELA CRUZ.JPG', '905404', 'Alexandra Mae', 'Lozada', 'DELA CRUZ', '', 'Female', 'San Jose, Pili, Camarines Sur', '1997-07-05', 'alexandramaedelacruz97@gmail.com', '09121341758', 1, 5, 3, 1, 16, NULL, 0, NULL, NULL, 0),
(85, '68c232aec4003_DOLZ, JEV.png', '134245', 'Jevielyn', 'A', 'DOLZ', '', 'Female', 'oas', '2000-07-01', 'email@example.com', '987955', 1, 7, 3, 1, 2, NULL, 0, 'f1ac547358fe58b25bbcbbd12be8a37381e49c8fd79c44cc85a2c41b25453567', '2025-09-11 05:25:16', 0),
(86, '', '318147', 'Harrish O.', '', 'MATOCINOS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987956', 1, 7, 3, 1, 43, NULL, 0, NULL, NULL, 0),
(87, '6860e7bda4197_56. ROSEMARIE A. PARAISO.JPG', '938818', ' Rosemarie', 'A', 'PARAISO  ', '', 'Female', 'Naga CIty', '2000-01-01', 'email@example.com', '987957', 1, 5, 3, 1, 1, 49, 0, NULL, NULL, 0),
(88, '', '135180', 'Gilbert S.', '', 'ARABACA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987958', 1, 5, 3, 1, 14, NULL, 0, NULL, NULL, 0),
(89, '', '250817', 'Rey B.', '', 'LANUZO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987959', 1, 5, 3, 1, 16, NULL, 0, NULL, NULL, 0),
(90, '', '935951 ', 'Noel B.', '', 'NASH', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987960', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(91, '6860d38da2eea_57. COLEEN M. RASTRULLO -2.JPG', '648335', 'Coleen', 'M.', 'RASTRULLO', '', 'Female', 'albay', '2000-10-01', 'email@example.com', '987961', 1, 5, 3, 1, 1, 49, 0, NULL, NULL, 1),
(92, '', '956528', ' Don A.', '', 'REBADAJO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987962', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(93, '', '500213', 'Lyle Kenneth A.', '', 'CALABINES', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987963', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(94, '', '660100', 'Jennimel R.', '', 'DAYUPAY', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987964', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(95, '', '136841 ', 'Francisco Jr. B.', '', 'JUAREZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987965', 1, 7, 3, 1, 16, NULL, 0, NULL, NULL, 0),
(96, '', '194697', 'Gio Dominick M.', '', 'MANLANGIT', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987966', 1, 7, 3, 1, 41, NULL, 0, NULL, NULL, 0),
(97, '', '333794', 'Mark Christian R.', '', 'MARBELLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987967', 1, 7, 3, 1, 41, NULL, 0, NULL, NULL, 0),
(98, '68467ce6d462d_sahur.png', '255154', ' Loewe Mae', 'B.', 'OLIVERA', '', 'Female', 'Guinobatan', '1997-05-29', 'email@example.com', '987968', 1, 7, 3, 1, 16, 49, 0, NULL, NULL, 0),
(99, '', '435914', 'Noel Jr. B.', '', 'ORAYE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987969', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(100, '68bfa30e8feae_PETILLA, CRISNA.png', '587844', ' Crisna', ' ', 'PETILLA', '', 'Female', 'P-3, TULA- TULA(GRANDE), LIGAO CITY', '2000-02-02', 'crisna.petilla1@gmail.com', '0916-501-3844', 1, 5, 3, 1, 16, NULL, 0, NULL, NULL, 0),
(101, '', '992299', 'Armando S.', '', 'PORTUGUEZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987971', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(102, '6870a1d6797fe_SIGUENZA, J.png', '710547', ' Jewel', 'A', 'SIGUENZA', '', 'Female', 'camsur', '2001-01-05', 'email@example.com', '987972', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(103, '', '608305 ', 'Bernardita P. ', '', 'BALINGASA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987973', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(104, '68e9b1d167a47_70. MA. DOLORES S. BELGADO.JPG', '287556', 'Ma. Dolores ', 'S.', 'BELGADO', '', 'Female', 'camsur', '2000-01-01', 'email@example.com', '987974', 1, 5, 4, 1, 3, 51, 0, NULL, NULL, 0),
(105, '6860d06a73b61_68. IAN FELICIANO III BERDIN.JPG', '587812', 'Ian Feliciano', 'P', 'BERDIN', 'III', 'Male', 'Camalig', '2000-01-01', 'email@example.com', '987975', 1, 10, 1, 1, 22, NULL, 0, NULL, NULL, 0),
(106, '', '817959', 'John  Bernard S.', '', 'NACARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987976', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(107, '', '669920 ', 'Dale Derick L.', '', 'DETERA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987977', 1, 5, 4, 1, 45, NULL, 0, NULL, NULL, 0),
(108, '', '620998', 'Ramon Jr. C.', '', 'AYDALLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987978', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(109, '', '659463', 'Marvin A.', '', 'MESTIOLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987979', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(110, '', '524286', 'Gerald M.', '', 'NAVARRO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987980', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(111, '', '136340', 'Sammy M.', '', 'OLI', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987981', 4, 7, 4, 1, 15, NULL, 0, NULL, NULL, 0),
(112, '', '534860', 'Von Jayvee A.', '', 'PERALTA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987982', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(113, '', '259436', 'Francis B.', '', 'ARCILLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987983', 1, 7, 4, 1, 42, NULL, 0, NULL, NULL, 0),
(114, '', '983424', 'Jay Ar P.', '', 'ATANANTE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987984', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(115, '', '837811', 'Jerwin P.', '', 'BOMBITA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987985', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(116, '', '595261', 'Ulysses ', '', 'GUADALUPE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987986', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(117, '', '323604', 'Ramon A.', '', 'RAMOS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987987', 1, 7, 4, 1, 42, NULL, 0, NULL, NULL, 0),
(118, '', '636146', 'Raynald R.', '', 'RAÑOLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987988', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(119, '', '793242', 'Cesar M.', '', 'REORA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987989', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(120, '', '740109', 'Conchita R.', '', 'REYES', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987990', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(121, '689162a174f31_96. MODESTO HARLEY S. NATE.jpg', '232531', 'Modesto Harley ', 'S', 'NATE', '', 'Male', 'ligao', '2000-01-01', 'email@example.com', '987991', 1, 5, 4, 1, 25, 48, 0, '0d2be1312f1cd481c55b3d7f27baf04f6d227bb2a46fef978cd4f0f604c92296', '2025-09-09 10:28:57', 0),
(122, '', '447264', 'Milany B.', '', 'DACILLO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987992', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(123, '', '478643', 'Elizabeth J.', '', 'JACOB', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987993', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(124, '', '429064', 'Jemar B.', '', 'PEÑAFLOR', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987994', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(125, '', '432411 ', 'Carlito C.', '', 'PONGPONG', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987995', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(126, '', '168325', 'Segfrido A.', '', 'PONTILLAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987996', 1, 5, 4, 1, 20, NULL, 0, NULL, NULL, 0),
(127, '6860d2123f6dd_103. MARILOU P. ANGUSTIA.JPG', '187727', 'Marilou', 'A', 'ROBLEDO', '', 'Female', 'Ligao', '2000-01-01', 'email@example.com', '987997', 1, 5, 4, 1, 20, NULL, 0, NULL, NULL, 0),
(128, '', '983454', 'Aliza May D.', '', 'BALINGASA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987998', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(129, '', '107996', 'Jhedson S.', '', 'CELLANO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987999', 1, 7, 4, 1, 39, NULL, 0, NULL, NULL, 0),
(130, '', '208506', 'Gail Nicole J.', '', 'JACOB', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988000', 1, 7, 4, 1, 39, NULL, 0, NULL, NULL, 0),
(131, '', '569250', ' Jun Shane M.', '', 'PEÑAFIEL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988001', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(132, '689162cc35a98_85. ALDRIN P. FRANCIA.JPG', '751102', 'Aldrin', 'P', 'FRANCIA', '', 'Male', 'naga', '2000-01-01', 'email@example.com', '988002', 1, 5, 4, 1, 1, 47, 0, '39d9963cd99164c5886415efeff07c963ec7c71935ab6473a4cd8bf4558a07bd', '2025-09-09 11:47:59', 0),
(133, '', '722450', 'Nestor S.', '', 'REODIQUE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988003', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(134, '6a014d36e82b7_AGRIPA, S.png', '521215', 'Salvador', 'H', 'AGRIPA', 'Jr.', 'Male', 'daraga', '2000-11-01', 'email@example.com', '988004', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(135, '', '348180', 'Alexander E.', '', 'RULL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988005', 1, 5, 4, 1, 23, NULL, 0, NULL, NULL, 0),
(136, '', '625407', 'Carl Louie B.', '', 'LONTAC', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988006', 1, 5, 4, 1, 23, NULL, 0, NULL, NULL, 0),
(137, '69ba0d2e2c723_91. JOHN LLOYD M. AGRIPA.JPG', '254906', 'John Lloyd', 'M.', 'AGRIPA', '', 'Male', 'g', '2026-03-18', 'email@example.com', '988007', 1, 7, 4, 1, 19, NULL, 0, NULL, NULL, 0),
(138, '', '503396', 'Jeric A.', '', 'AMADOS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988008', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(139, '', '799103', 'Jose Domingo C.', '', 'BERZUELA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988009', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(140, '', '405192', 'John Kenneth P.', '', 'PAJE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988010', 1, 7, 3, 1, 42, NULL, 0, NULL, NULL, 0),
(141, '', '775418', 'Patrick Jorge C.', '', 'PANTE.', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988011', 1, 5, 4, 1, 23, NULL, 0, NULL, NULL, 0),
(142, '', '556604', 'Mark Angelo P.', '', 'POLIDARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988012', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(143, '', '614317', ' Albert S.', '', 'RAPOSA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988013', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(144, '', '296935', ' Kenneth Christopher O.', '', 'REODIQUE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988014', 4, 7, 4, 1, 26, NULL, 0, NULL, NULL, 0),
(145, '', '979595', 'Raymond R.', '', 'VIÑAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988015', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(146, '', '723219', 'Angelo V. ', '', 'MARQUEZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988016', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(147, '6a014cf506ae2_8. Adelante, Isagani.jpg', '599880', 'Isagani', 'P.', 'ADELANTE', 'Jr.', 'Male', 'catanduanes', '1992-10-13', 'email@example.com', '988017', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(148, '', '910354', 'Elmerio B.', '', 'TENDENILLA,', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988018', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(149, '', '879431', 'John Learry A.', '', 'BRIOSO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988019', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(150, '', '578474', 'Jomel M.', '', 'TORIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988020', 1, 7, 1, 10, 28, NULL, 0, NULL, NULL, 0),
(151, '', '913614', 'Jomer L.', '', 'BEO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988021', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(152, '', '135884', 'Joshua A.', '', 'LUMBAO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988022', 4, 7, NULL, 1, 41, NULL, 0, NULL, NULL, 0),
(153, '', '299888', 'Edilberto Jr. R.', '', 'BEO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988023', 1, 7, NULL, 1, 39, NULL, 0, NULL, NULL, 0),
(154, '', '653144', 'Dindo Z.', '', 'MANLANGIT', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988024', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(155, '', '877653', 'Frederick T.', '', 'MELGAR', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988025', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(156, '', '545194', 'Mark Anjo T.', '', 'ALNAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988026', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(157, '', '553783', 'Richiel A.', '', 'MASAGCA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988027', 1, 5, NULL, 10, 1, NULL, 0, NULL, NULL, 0),
(158, '', '298653', 'Kayceelyn M.', '', 'TAPIA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988028', 1, 7, 3, 10, 2, NULL, 0, NULL, NULL, 0),
(159, '', '608312', 'Rodolfo Jr. G.', '', 'LLAVE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988029', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0),
(160, '68f82bd0dcb97_2020-nia-logo.png', '607452', 'Francis', 'B.', 'Olaguer', '', 'Male', 'Guinobatan', '2000-10-10', 'email@email.com', '12321312', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `employee_unit_sections`
--

CREATE TABLE `employee_unit_sections` (
  `emp_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `is_head` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_unit_sections`
--

INSERT INTO `employee_unit_sections` (`emp_id`, `unit_id`, `is_head`) VALUES
(29, 46, 0),
(32, 46, 0),
(43, 53, 0),
(47, 55, 0),
(59, 52, 0),
(64, 52, 0),
(81, 52, 0),
(85, 52, 0),
(91, 49, 0),
(100, 49, 0),
(107, 51, 0),
(111, 51, 0),
(136, 47, 0),
(144, 47, 0),
(150, 46, 0),
(158, 49, 0);

-- --------------------------------------------------------

--
-- Table structure for table `employment_status`
--

CREATE TABLE `employment_status` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `color` varchar(20) DEFAULT '#007bff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employment_status`
--

INSERT INTO `employment_status` (`status_id`, `status_name`, `created_at`, `updated_at`, `color`) VALUES
(1, 'Active', '2025-05-20 02:59:18', '2025-06-03 02:42:17', '#10841d'),
(2, 'Inactive', '2025-05-20 02:59:18', '2025-05-23 03:34:08', '#93999f'),
(3, 'Separated - Death', '2025-05-20 02:59:18', '2025-05-23 03:34:35', '#080808'),
(4, 'Non-renewal', '2025-05-20 02:59:18', '2025-05-23 03:34:08', '#fbff00'),
(5, 'Resigned', '2025-05-20 02:59:18', '2025-05-23 03:34:35', '#ffd500'),
(6, 'Retired', '2025-05-20 02:59:18', '2025-05-23 03:34:35', '#d575d7'),
(7, 'AWOL', '2025-05-20 02:59:18', '2025-05-23 03:33:48', '#ff1900');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('event','meeting','holiday','birthday') NOT NULL DEFAULT 'event',
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `type`, `start_date`, `end_date`, `description`) VALUES
(10, 'Edil Ad Ja', 'holiday', '2025-06-06 00:00:00', '2025-06-06 12:00:00', 'Holiday'),
(11, 'Independence Day', 'holiday', '2025-06-12 00:00:00', '2025-06-12 12:00:00', 'Regular Holiday'),
(12, 'TREE PLANTING', 'event', '2025-07-02 08:00:00', '2025-07-02 17:00:00', 'NIA TREE PLANTING'),
(13, 'NIA ANNIVERSARY CELEBRATION \"FIESTA SA NIA\"', 'event', '2025-07-03 08:00:00', '2025-07-04 17:00:00', 'CELEBRATING NIA ANNIVERSARY'),
(20, '3RD QUARTER REGIONAL ASSESSMENT', 'event', '2025-10-01 08:00:00', '2025-10-03 17:00:00', '@ VELA HOTEL'),
(25, 'MEETING WITH ALL SECTION UNITS', 'meeting', '2025-10-13 08:00:00', '2025-10-14 17:00:00', '');

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `file_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_size` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `status` enum('pending','approved','rejected','draft') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `is_starred` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`file_id`, `file_name`, `file_path`, `file_type`, `file_size`, `description`, `section_id`, `folder_id`, `uploaded_by`, `status`, `created_at`, `updated_at`, `is_deleted`, `deleted_at`, `deleted_by`, `is_starred`) VALUES
(138, 'ACIMO - Summary of Competency Result_2025 (JOB ORDER).xlsx', '6a014560e76bf_1778468192_0.xlsx', 'xlsx', 127098, '0', 1, NULL, 32, 'pending', '2026-05-11 02:56:32', '2026-05-11 02:56:32', 0, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `file_activity_logs`
--

CREATE TABLE `file_activity_logs` (
  `log_id` int(11) NOT NULL,
  `file_id` int(11) DEFAULT NULL,
  `emp_id` int(11) DEFAULT NULL,
  `activity_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `file_activity_logs`
--

INSERT INTO `file_activity_logs` (`log_id`, `file_id`, `emp_id`, `activity_type`, `description`, `ip_address`, `created_at`) VALUES
(116, 138, 32, 'uploaded', 'File \'ACIMO - Summary of Competency Result_2025 (JOB ORDER).xlsx\' uploaded to folder \'sample\'', '::1', '2026-05-11 02:56:32');

-- --------------------------------------------------------

--
-- Table structure for table `folders`
--

CREATE TABLE `folders` (
  `folder_id` int(11) NOT NULL,
  `folder_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `parent_folder_id` int(11) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_locked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `folder_access`
--

CREATE TABLE `folder_access` (
  `access_id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `access_level` enum('view','edit','full') DEFAULT 'view',
  `granted_by` int(11) NOT NULL,
  `granted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `folder_activity_logs`
--

CREATE TABLE `folder_activity_logs` (
  `log_id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `activity_type` enum('created','accessed','modified','deleted','file_uploaded','file_deleted') NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `folder_shares`
--

CREATE TABLE `folder_shares` (
  `share_id` int(11) NOT NULL,
  `folder_id` int(11) NOT NULL,
  `shared_by_emp_id` int(11) NOT NULL,
  `shared_with_emp_id` int(11) NOT NULL,
  `permission_level` enum('view','upload','edit','manage') NOT NULL DEFAULT 'view',
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `iar_items`
--

CREATE TABLE `iar_items` (
  `id` int(11) NOT NULL,
  `iar_id` int(11) DEFAULT NULL,
  `delivery_item_id` int(11) DEFAULT NULL,
  `stock_property_no` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `iar_items`
--

INSERT INTO `iar_items` (`id`, `iar_id`, `delivery_item_id`, `stock_property_no`, `description`, `unit`, `quantity`, `unit_price`, `total_price`) VALUES
(17, 9, 17, NULL, 'FLASH DRIVE', 'PCS', 10, 500.00, 5000.00),
(18, 9, 18, NULL, 'BROOM', 'PCS', 5, 25.00, 125.00),
(19, 9, 19, NULL, 'BROOM', 'PCS', 5, 40.00, 200.00),
(20, 9, 20, NULL, 'CALCULATOR', 'PCS', 3, 300.00, 900.00),
(21, 9, 21, NULL, 'MOUSE', 'PCS', 12, 500.00, 6000.00),
(35, 18, 35, NULL, 'BROOM', 'PCS', 2, 11.00, 22.00),
(36, 19, 36, NULL, 'CALCULATOR', 'PCS', 5, 80.00, 400.00),
(37, 19, 37, NULL, 'INK', 'BOTTLE', 20, 150.00, 3000.00),
(38, 19, 38, NULL, 'INK', 'BOTTLE', 20, 150.00, 3000.00),
(39, 19, 39, NULL, 'INK', 'BOTTLE', 20, 150.00, 3000.00),
(40, 19, 40, NULL, 'INK', 'BOTTLE', 20, 150.00, 3000.00);

-- --------------------------------------------------------

--
-- Table structure for table `iar_records`
--

CREATE TABLE `iar_records` (
  `id` int(11) NOT NULL,
  `iar_number` varchar(20) NOT NULL,
  `pr_number` varchar(20) DEFAULT NULL,
  `po_number` varchar(20) DEFAULT NULL,
  `po_date` date DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `requisition_office` varchar(255) DEFAULT NULL,
  `invoice_number` varchar(50) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `dr_number` varchar(50) DEFAULT NULL,
  `dr_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `iar_records`
--

INSERT INTO `iar_records` (`id`, `iar_number`, `pr_number`, `po_number`, `po_date`, `supplier`, `requisition_office`, `invoice_number`, `invoice_date`, `dr_number`, `dr_date`, `delivery_date`, `total_amount`, `created_by`, `created_at`) VALUES
(9, 'IAR-2025-09-0001', NULL, 'PO-2025-0001', '2025-09-15', 'PANDAYAN', 'Engineering Section', '', '0000-00-00', '5200', '2025-09-18', '2025-09-18', 12225.00, 10, '2025-09-18 05:42:14'),
(18, 'IAR-2025-20-0002', NULL, 'PO-2025-20-0002', '2025-09-18', 'PANDAYAN', 'Administrative Section', '121', '2025-09-25', '', '0000-00-00', '2025-09-18', 22.00, 10, '2025-09-18 08:27:16'),
(19, 'IAR-2025-11-0022', NULL, 'PO-2025-11-0022', '2025-09-14', 'BIT AND BYTES', 'Administrative Section', '', '0000-00-00', '5200', '2025-09-18', '2025-09-18', 12400.00, 10, '2025-09-18 08:28:28');

-- --------------------------------------------------------

--
-- Table structure for table `ia_officers`
--

CREATE TABLE `ia_officers` (
  `id` int(11) NOT NULL,
  `ia_profile_id` int(11) DEFAULT NULL,
  `officer_name` varchar(255) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ia_profiles`
--

CREATE TABLE `ia_profiles` (
  `id` int(11) NOT NULL,
  `ia_name` varchar(255) NOT NULL,
  `ia_code` varchar(50) DEFAULT NULL,
  `mailing_address` text DEFAULT NULL,
  `president_name` varchar(255) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `date_organized` date DEFAULT NULL,
  `sec_registration_date` date DEFAULT NULL,
  `sec_registration_number` varchar(100) DEFAULT NULL,
  `ia_tin` varchar(50) DEFAULT NULL,
  `service_area_ha` decimal(10,4) DEFAULT NULL,
  `fusa_ha` decimal(10,4) DEFAULT NULL,
  `farmer_beneficiaries` int(11) DEFAULT NULL,
  `actual_ia_members` int(11) DEFAULT NULL,
  `tsags_count` int(11) DEFAULT NULL,
  `existing_contract` varchar(100) DEFAULT NULL,
  `contract_effectivity_date` date DEFAULT NULL,
  `canal_length_km` decimal(8,3) DEFAULT NULL,
  `male_members` int(11) DEFAULT NULL,
  `female_members` int(11) DEFAULT NULL,
  `congressional_district` varchar(50) DEFAULT NULL,
  `region` varchar(50) DEFAULT NULL,
  `province` varchar(50) DEFAULT NULL,
  `imo` varchar(100) DEFAULT NULL,
  `nis` varchar(100) DEFAULT NULL,
  `status` enum('operational','non-operational') DEFAULT 'operational',
  `assigned_employee_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ia_profile_history`
--

CREATE TABLE `ia_profile_history` (
  `id` int(11) NOT NULL,
  `ia_profile_id` int(11) NOT NULL,
  `action` enum('created','updated','deleted','assigned','officer_added','officer_updated','officer_deleted') NOT NULL,
  `field_name` varchar(100) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `description` text NOT NULL,
  `performed_by` int(11) NOT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ict_equipment`
--

CREATE TABLE `ict_equipment` (
  `equipment_id` int(11) NOT NULL,
  `asset_tag` varchar(50) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `specifications` text DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `purchase_cost` decimal(10,2) NOT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `status` enum('Available','Assigned','Under Maintenance','Retired','Lost') DEFAULT 'Available',
  `condition` enum('Excellent','Good','Fair','Poor') DEFAULT 'Good',
  `assigned_to` int(11) DEFAULT NULL,
  `assigned_date` date DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ict_equipment`
--

INSERT INTO `ict_equipment` (`equipment_id`, `asset_tag`, `serial_number`, `category_id`, `equipment_name`, `brand`, `model`, `specifications`, `purchase_date`, `purchase_cost`, `warranty_expiry`, `status`, `condition`, `assigned_to`, `assigned_date`, `location`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(21, 'acer2024', 'asdas', 1, 'Acer Laptop', 'Acer', 'asdsad', '', '0000-00-00', 0.00, NULL, 'Assigned', 'Excellent', 32, '2025-10-17', NULL, NULL, 32, '2025-10-17 07:01:20', '2025-10-17 07:38:17'),
(22, 'HP2024', '2342343d2', 2, 'HP Desktop', 'HP', 'asd3d23', 'asdada', '0000-00-00', 0.00, NULL, 'Assigned', 'Excellent', 32, '2025-10-17', NULL, NULL, 32, '2025-10-17 07:01:48', '2025-10-17 07:39:38'),
(23, 'Epson2024', 'dad32d23', 4, 'Epson L3210', 'Epson', 'asd3d', 'asc xcasdsa as adfas', '0000-00-00', 0.00, NULL, 'Assigned', 'Excellent', 32, '2025-10-20', NULL, NULL, 32, '2025-10-17 07:54:14', '2025-10-20 03:21:59'),
(24, 'A4TECH2024', 'FSDFDF235412', 6, 'A4TECH MOUSE', 'A4TECH', 'A4TECHASDAWDASD', '', '0000-00-00', 0.00, NULL, 'Assigned', 'Excellent', 31, '2025-10-27', NULL, NULL, 32, '2025-10-17 08:41:54', '2025-10-27 02:25:07'),
(25, 'aw', '12121', 2, 'LG Desktop', 'LG', 'asda', 'N/A\r\n', '0000-00-00', 0.00, NULL, 'Assigned', 'Excellent', 31, '2025-10-27', NULL, NULL, 32, '2025-10-27 02:26:06', '2025-10-27 02:30:34');

-- --------------------------------------------------------

--
-- Table structure for table `ict_equipment_categories`
--

CREATE TABLE `ict_equipment_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ict_equipment_categories`
--

INSERT INTO `ict_equipment_categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Laptop', 'Portable computers and notebooks', '2025-10-16 07:09:00'),
(2, 'Desktop', 'Desktop computers and workstations', '2025-10-16 07:09:00'),
(3, 'Monitor', 'Computer monitors and displays', '2025-10-16 07:09:00'),
(4, 'Printer', 'Printers and multifunction devices', '2025-10-16 07:09:00'),
(5, 'Network Equipment', 'Routers, switches, access points', '2025-10-16 07:09:00'),
(6, 'Peripherals', 'Keyboards, mouse, webcams', '2025-10-16 07:09:00'),
(7, 'Mobile Devices', 'Tablets, smartphones', '2025-10-16 07:09:00'),
(8, 'Server', 'Server equipment', '2025-10-16 07:09:00');

-- --------------------------------------------------------

--
-- Table structure for table `ict_equipment_logs`
--

CREATE TABLE `ict_equipment_logs` (
  `log_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `action` enum('Created','Updated','Assigned','Unassigned','Maintenance','Retired') NOT NULL,
  `action_by` int(11) NOT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ict_equipment_logs`
--

INSERT INTO `ict_equipment_logs` (`log_id`, `equipment_id`, `action`, `action_by`, `action_date`, `notes`) VALUES
(136, 22, 'Assigned', 32, '2025-10-17 07:02:04', ''),
(137, 21, 'Assigned', 32, '2025-10-17 07:07:01', 'with bag\r\n'),
(138, 21, 'Unassigned', 32, '2025-10-17 07:38:09', 'Unassigned from Jessica PEÑAFLOR'),
(139, 21, 'Assigned', 32, '2025-10-17 07:38:17', ''),
(140, 22, 'Assigned', 32, '2025-10-17 07:39:38', ''),
(141, 23, 'Assigned', 32, '2025-10-17 07:54:21', ''),
(142, 24, 'Assigned', 32, '2025-10-17 08:42:01', ''),
(143, 23, 'Assigned', 32, '2025-10-20 03:21:59', ''),
(144, 24, 'Unassigned', 32, '2025-10-27 02:24:27', 'Unassigned from Marc David  OROGO'),
(145, 24, 'Assigned', 32, '2025-10-27 02:25:07', 'N/A'),
(146, 25, 'Assigned', 32, '2025-10-27 02:30:34', 'aa');

-- --------------------------------------------------------

--
-- Table structure for table `ict_maintenance`
--

CREATE TABLE `ict_maintenance` (
  `maintenance_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `issue_type` enum('Hardware','Software','Network','Performance','Other') NOT NULL,
  `description` text NOT NULL,
  `priority` enum('Low','Medium','High','Critical') NOT NULL,
  `status` enum('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `reported_by` int(11) NOT NULL,
  `report_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_technician` int(11) DEFAULT NULL,
  `assigned_date` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `resolved_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ict_maintenance`
--

INSERT INTO `ict_maintenance` (`maintenance_id`, `equipment_id`, `issue_type`, `description`, `priority`, `status`, `reported_by`, `report_date`, `assigned_technician`, `assigned_date`, `resolution_notes`, `resolved_date`) VALUES
(2, 21, 'Software', 'asdsa', 'Medium', 'Cancelled', 32, '2025-10-17 07:52:31', 59, '2025-10-17 15:53:00', '', NULL),
(3, 23, 'Hardware', 'asdas', 'High', 'Completed', 32, '2025-10-17 08:32:29', 59, '2025-10-17 16:32:58', '', '2025-10-20 11:20:30'),
(4, 24, 'Performance', 'urgently needed assistance ', 'Critical', 'In Progress', 31, '2025-10-27 02:31:28', 32, '2025-10-27 11:18:20', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ict_maintenance_logs`
--

CREATE TABLE `ict_maintenance_logs` (
  `log_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `maintenance_date` date NOT NULL,
  `maintenance_type` enum('Preventive','Corrective','Upgrade') NOT NULL,
  `description` text NOT NULL,
  `performed_by` varchar(255) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ict_maintenance_notes`
--

CREATE TABLE `ict_maintenance_notes` (
  `note_id` int(11) NOT NULL,
  `maintenance_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `added_by` int(11) NOT NULL,
  `added_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `intern`
--

CREATE TABLE `intern` (
  `intern_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `school` varchar(200) NOT NULL,
  `course` varchar(200) NOT NULL,
  `year_level` varchar(50) NOT NULL,
  `department_assigned` varchar(100) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `supervisor_name` varchar(200) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `resume_file` varchar(255) DEFAULT NULL,
  `recommendation_letter` varchar(255) DEFAULT NULL,
  `school_endorsement` varchar(255) DEFAULT NULL,
  `other_documents` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `status` enum('Active','Completed','Terminated','On Hold') DEFAULT 'Active',
  `performance_rating` int(50) DEFAULT NULL,
  `number_of_hours` int(255) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `intern`
--

INSERT INTO `intern` (`intern_id`, `first_name`, `middle_name`, `last_name`, `gender`, `email`, `phone_number`, `address`, `school`, `course`, `year_level`, `department_assigned`, `supervisor_id`, `supervisor_name`, `start_date`, `end_date`, `resume_file`, `recommendation_letter`, `school_endorsement`, `other_documents`, `profile_picture`, `status`, `performance_rating`, `number_of_hours`, `remarks`, `created_at`, `updated_at`) VALUES
(16, 'Francis', 'B.', 'Olaguer', 'Male', 'email@email.com', '1', '1', 'BU Polangui', 'BS IT', '4th Year', 'Administrative Section', NULL, 'Ian Feliciano P BERDIN', '2026-02-19', '0000-00-00', 'resume_1778030687_69fa985fdb874.docx', NULL, NULL, NULL, 'profile_1771638118_69990d66b789d.png', 'Active', 1, 500, '0', '2026-02-09 01:16:50', '2026-05-06 01:24:47');

-- --------------------------------------------------------

--
-- Table structure for table `ism_asset_types`
--

CREATE TABLE `ism_asset_types` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(80) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ism_asset_types`
--

INSERT INTO `ism_asset_types` (`id`, `code`, `name`, `icon`, `description`) VALUES
(1, 'MAIN_CANAL', 'Main Canal', 'ti-road', 'Primary conveyance canal from headworks.'),
(2, 'LAT_CANAL', 'Lateral Canal', 'ti-road', 'Secondary distribution canals off the main canal.'),
(3, 'SUB_LATERAL', 'Sub-lateral Canal', 'ti-road', 'Tertiary canals serving individual farm lots.'),
(4, 'DIV_DAM', 'Diversion Dam/Weir', 'ti-barrier-block', 'Headworks diverting water from source river.'),
(5, 'GATE', 'Gate / Control Str.', 'ti-settings', 'Control gates, division boxes, turnouts, flumes.'),
(6, 'PUMP_STN', 'Pump Station', 'ti-engine', 'Pumping unit with motor, discharge capacity.'),
(7, 'DRAINAGE', 'Drainage Works', 'ti-archive', 'Drainage canals and outlet structures.'),
(8, 'ACCESS_ROAD', 'Access / Service Road', 'ti-road-sign', 'Service roads along canal banks.');

-- --------------------------------------------------------

--
-- Table structure for table `ism_condition_ratings`
--

CREATE TABLE `ism_condition_ratings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `label` varchar(40) NOT NULL,
  `color` varchar(20) NOT NULL,
  `sort_order` tinyint(3) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ism_condition_ratings`
--

INSERT INTO `ism_condition_ratings` (`id`, `code`, `label`, `color`, `sort_order`) VALUES
(1, 'good', 'Good', 'success', 1),
(2, 'fair', 'Fair', 'warning', 2),
(3, 'poor', 'Poor', 'danger', 3),
(4, 'critical', 'Critical', 'dark', 4);

-- --------------------------------------------------------

--
-- Table structure for table `ism_crop_types`
--

CREATE TABLE `ism_crop_types` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(80) NOT NULL,
  `category` enum('Rice','Corn','Vegetable','HVC','Other') NOT NULL DEFAULT 'Other',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ism_crop_types`
--

INSERT INTO `ism_crop_types` (`id`, `code`, `name`, `category`, `is_active`) VALUES
(1, 'RICE_IRR', 'Rice (Irrigated)', 'Rice', 1),
(2, 'RICE_RF', 'Rice (Rain-fed)', 'Rice', 1),
(3, 'CORN', 'Corn', 'Corn', 1),
(4, 'VEG_HVC', 'Vegetables (HVC)', 'HVC', 1),
(5, 'EGGPLANT', 'Eggplant', 'Vegetable', 1),
(6, 'TOMATO', 'Tomato', 'Vegetable', 1),
(7, 'CABBAGE', 'Cabbage', 'Vegetable', 1),
(8, 'OTHER', 'Other Crops', 'Other', 1);

-- --------------------------------------------------------

--
-- Table structure for table `ism_infrastructure_assets`
--

CREATE TABLE `ism_infrastructure_assets` (
  `id` int(10) UNSIGNED NOT NULL,
  `asset_code` varchar(40) NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `asset_type_id` tinyint(3) UNSIGNED NOT NULL,
  `asset_name` varchar(150) NOT NULL,
  `location_description` varchar(200) DEFAULT NULL,
  `station_from` varchar(20) DEFAULT NULL,
  `station_to` varchar(20) DEFAULT NULL,
  `length_km` decimal(8,3) DEFAULT NULL,
  `lining_type` enum('Concrete','Earthen','Riprap','Unlined','Steel','N/A') DEFAULT 'N/A',
  `capacity` varchar(80) DEFAULT NULL,
  `year_constructed` year(4) DEFAULT NULL,
  `current_condition_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `last_inspection_date` date DEFAULT NULL,
  `last_inspected_by` int(10) UNSIGNED DEFAULT NULL,
  `estimated_rehab_cost` decimal(12,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(10) UNSIGNED NOT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_inspections`
--

CREATE TABLE `ism_inspections` (
  `id` int(10) UNSIGNED NOT NULL,
  `asset_id` int(10) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `inspection_date` date NOT NULL,
  `inspected_by` int(10) UNSIGNED NOT NULL,
  `condition_id` tinyint(3) UNSIGNED NOT NULL,
  `defects_found` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `photos_taken` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_inspection_photos`
--

CREATE TABLE `ism_inspection_photos` (
  `id` int(10) UNSIGNED NOT NULL,
  `inspection_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_irrigation_systems`
--

CREATE TABLE `ism_irrigation_systems` (
  `id` int(10) UNSIGNED NOT NULL,
  `system_code` varchar(30) NOT NULL,
  `system_name` varchar(150) NOT NULL,
  `system_type_id` tinyint(3) UNSIGNED NOT NULL,
  `status_id` tinyint(3) UNSIGNED NOT NULL,
  `province` varchar(80) NOT NULL,
  `municipalities_served` text DEFAULT NULL,
  `barangays_covered` smallint(5) UNSIGNED DEFAULT 0,
  `potential_service_area_ha` decimal(10,2) DEFAULT 0.00,
  `actual_service_area_ha` decimal(10,2) DEFAULT 0.00,
  `year_constructed` year(4) DEFAULT NULL,
  `funding_source` varchar(100) DEFAULT NULL,
  `main_canal_length_km` decimal(8,2) DEFAULT 0.00,
  `lateral_canal_length_km` decimal(8,2) DEFAULT 0.00,
  `diversion_structures` smallint(5) UNSIGNED DEFAULT 0,
  `gates_and_structures` smallint(5) UNSIGNED DEFAULT 0,
  `pump_stations` smallint(5) UNSIGNED DEFAULT 0,
  `last_major_rehab_year` year(4) DEFAULT NULL,
  `ia_profile_id` int(10) UNSIGNED DEFAULT NULL,
  `assigned_technician_id` int(10) UNSIGNED DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_production_entries`
--

CREATE TABLE `ism_production_entries` (
  `id` int(10) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `season_id` smallint(5) UNSIGNED NOT NULL,
  `crop_type_id` tinyint(3) UNSIGNED NOT NULL,
  `target_area_ha` decimal(10,2) DEFAULT 0.00,
  `target_yield_mt_ha` decimal(6,3) DEFAULT 0.000,
  `area_planted_ha` decimal(10,2) DEFAULT 0.00,
  `area_harvested_ha` decimal(10,2) DEFAULT 0.00,
  `area_lost_ha` decimal(10,2) DEFAULT 0.00,
  `loss_cause` enum('Typhoon','Flood','Drought','Pest','Crop Disease','Abandonment','Other') DEFAULT NULL,
  `yield_mt_ha` decimal(6,3) DEFAULT 0.000,
  `total_production_mt` decimal(12,3) GENERATED ALWAYS AS (`area_harvested_ha` * `yield_mt_ha`) STORED,
  `entry_status` enum('Draft','For Review','Approved') DEFAULT 'Draft',
  `submitted_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_production_report_exports`
--

CREATE TABLE `ism_production_report_exports` (
  `id` int(10) UNSIGNED NOT NULL,
  `report_type` enum('Seasonal Accomplishment','ISF Billing Basis','DA Crop Production','System Performance') NOT NULL,
  `season_id` smallint(5) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `generated_by` int(10) UNSIGNED NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_production_targets`
--

CREATE TABLE `ism_production_targets` (
  `id` int(10) UNSIGNED NOT NULL,
  `season_id` smallint(5) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `total_target_ha` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `set_by` int(10) UNSIGNED NOT NULL,
  `set_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_seasons`
--

CREATE TABLE `ism_seasons` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `season_type` enum('DS','WS','Special') NOT NULL,
  `year` year(4) NOT NULL,
  `label` varchar(60) NOT NULL,
  `date_start` date NOT NULL,
  `date_end` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_submission_status`
--

CREATE TABLE `ism_submission_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `season_id` smallint(5) UNSIGNED NOT NULL,
  `status` enum('Not Started','In Progress','For Review','Submitted','Approved') DEFAULT 'Not Started',
  `percent_complete` tinyint(3) UNSIGNED DEFAULT 0,
  `last_updated_by` int(10) UNSIGNED DEFAULT NULL,
  `last_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_system_attachments`
--

CREATE TABLE `ism_system_attachments` (
  `id` int(10) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(80) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_system_audit_log`
--

CREATE TABLE `ism_system_audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(20) NOT NULL,
  `field_changed` varchar(100) DEFAULT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` int(10) UNSIGNED NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_system_coverage`
--

CREATE TABLE `ism_system_coverage` (
  `id` int(10) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `province` varchar(80) NOT NULL,
  `municipality` varchar(80) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `area_ha` decimal(8,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_system_statuses`
--

CREATE TABLE `ism_system_statuses` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `label` varchar(60) NOT NULL,
  `color` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ism_system_statuses`
--

INSERT INTO `ism_system_statuses` (`id`, `code`, `label`, `color`) VALUES
(1, 'operational', 'Operational', 'success'),
(2, 'partially_operational', 'Partially Operational', 'warning'),
(3, 'under_rehabilitation', 'Under Rehabilitation', 'info'),
(4, 'non_operational', 'Non-Operational', 'danger'),
(5, 'decommissioned', 'Decommissioned', 'secondary');

-- --------------------------------------------------------

--
-- Table structure for table `ism_system_types`
--

CREATE TABLE `ism_system_types` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ism_system_types`
--

INSERT INTO `ism_system_types` (`id`, `code`, `name`, `description`, `created_at`) VALUES
(1, 'NIS', 'National Irrigation System', 'Large-scale NIA-managed. Service area typically above 1,000 ha.', '2026-06-10 00:52:56'),
(2, 'CIS', 'Communal Irrigation System', 'Smaller systems managed by IA with NIA supervision. 100–1,000 ha.', '2026-06-10 00:52:56'),
(3, 'SWIP', 'Small Water Impounding Project', 'Reservoir-based systems for upland or rain-fed areas.', '2026-06-10 00:52:56'),
(4, 'STW', 'Shallow Tube Well', 'Pump-driven groundwater systems for small service areas.', '2026-06-10 00:52:56');

-- --------------------------------------------------------

--
-- Table structure for table `ism_water_allocations`
--

CREATE TABLE `ism_water_allocations` (
  `id` int(10) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `season_id` smallint(5) UNSIGNED NOT NULL,
  `allocated_volume_cum` decimal(12,2) DEFAULT 0.00,
  `allocated_area_ha` decimal(10,2) DEFAULT 0.00,
  `source_type` enum('River','Reservoir','Pump','Rainwater','Combined') NOT NULL DEFAULT 'River',
  `water_source_name` varchar(150) DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_water_delivery_issues`
--

CREATE TABLE `ism_water_delivery_issues` (
  `id` int(10) UNSIGNED NOT NULL,
  `delivery_log_id` int(10) UNSIGNED NOT NULL,
  `issue_type` enum('Shortfall','Gate Malfunction','Siltation','Unauthorized Diversion','Drought','Other') NOT NULL,
  `description` text NOT NULL,
  `volume_lost_cum` decimal(10,2) DEFAULT 0.00,
  `reported_by` int(10) UNSIGNED NOT NULL,
  `resolved` tinyint(1) DEFAULT 0,
  `resolved_by` int(10) UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_water_delivery_logs`
--

CREATE TABLE `ism_water_delivery_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `season_id` smallint(5) UNSIGNED NOT NULL,
  `delivery_date` date NOT NULL,
  `scheduled_volume_cum` decimal(10,2) DEFAULT 0.00,
  `actual_volume_cum` decimal(10,2) DEFAULT 0.00,
  `area_served_ha` decimal(8,2) DEFAULT 0.00,
  `gate_opening_hours` decimal(5,2) DEFAULT 0.00,
  `flow_rate_cms` decimal(8,4) DEFAULT 0.0000,
  `delivery_officer_id` int(10) UNSIGNED DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_work_orders`
--

CREATE TABLE `ism_work_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `wo_number` varchar(30) NOT NULL,
  `asset_id` int(10) UNSIGNED NOT NULL,
  `system_id` int(10) UNSIGNED NOT NULL,
  `work_type` enum('Repair','Desilting','Replacement','Rehabilitation','Inspection','Routine Maintenance','Emergency') NOT NULL,
  `urgency` enum('Normal','Urgent','Emergency') DEFAULT 'Normal',
  `status_id` tinyint(3) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `estimated_cost` decimal(12,2) DEFAULT 0.00,
  `actual_cost` decimal(12,2) DEFAULT 0.00,
  `scheduled_date` date DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `requested_by` int(10) UNSIGNED NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_work_order_materials`
--

CREATE TABLE `ism_work_order_materials` (
  `id` int(10) UNSIGNED NOT NULL,
  `work_order_id` int(10) UNSIGNED NOT NULL,
  `material_name` varchar(150) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(12,2) GENERATED ALWAYS AS (`quantity` * `unit_cost`) STORED,
  `supply_request_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ism_work_order_statuses`
--

CREATE TABLE `ism_work_order_statuses` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `label` varchar(60) NOT NULL,
  `color` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ism_work_order_statuses`
--

INSERT INTO `ism_work_order_statuses` (`id`, `code`, `label`, `color`) VALUES
(1, 'for_approval', 'For Approval', 'info'),
(2, 'approved', 'Approved', 'primary'),
(3, 'scheduled', 'Scheduled', 'warning'),
(4, 'in_progress', 'In Progress', 'primary'),
(5, 'completed', 'Completed', 'success'),
(6, 'cancelled', 'Cancelled', 'secondary');

-- --------------------------------------------------------

--
-- Table structure for table `ism_work_order_status_history`
--

CREATE TABLE `ism_work_order_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `work_order_id` int(10) UNSIGNED NOT NULL,
  `old_status_id` tinyint(3) UNSIGNED DEFAULT NULL,
  `new_status_id` tinyint(3) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` int(10) UNSIGNED NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(100) NOT NULL,
  `unit_of_measure` varchar(50) NOT NULL,
  `current_stock` int(11) DEFAULT 0,
  `stock_quantity` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `category_id`, `name`, `description`, `unit_of_measure`, `current_stock`, `stock_quantity`, `created_at`, `updated_at`) VALUES
(9, 1, 'BROOM', 'Walis Tambo', 'PCS', 7, 0, '2025-09-18 05:17:53', '2025-09-18 08:27:16'),
(10, 1, 'BROOM', 'Walis Ting-ting', 'PCS', 5, 0, '2025-09-18 05:35:55', '2025-09-18 08:24:42'),
(11, 2, 'INK', '003 BLACK', 'BOTTLE', 10, 0, '2025-09-18 05:36:19', '2025-09-23 00:33:32'),
(12, 2, 'INK', '003 CYAN', 'BOTTLE', 20, 0, '2025-09-18 05:36:39', '2025-09-18 08:28:28'),
(13, 2, 'INK', '003 YELLOW', 'BOTTLE', 20, 0, '2025-09-18 05:36:53', '2025-09-18 08:28:28'),
(14, 2, 'INK', '003 MAGENTA', 'BOTTLE', 20, 0, '2025-09-18 05:37:09', '2025-09-18 08:28:28'),
(15, 6, 'FLASH DRIVE', '500GB', 'PCS', 8, 0, '2025-09-18 05:37:39', '2025-09-18 08:10:06'),
(16, 6, 'MOUSE', 'USB connection type', 'PCS', 10, 0, '2025-09-18 05:37:57', '2025-09-18 08:10:06'),
(17, 7, 'TAPE', 'ELECTRICAL 24MM', 'ROLL', 0, 0, '2025-09-18 05:39:10', '2025-09-18 05:39:10'),
(18, 7, 'TAPE', 'ELECTICAL 48MM', 'ROLL', 0, 0, '2025-09-18 05:39:27', '2025-09-18 05:39:27'),
(19, 7, 'TAPE', 'MASKING 24MM', 'ROLL', 0, 0, '2025-09-18 05:39:51', '2025-09-18 05:39:51'),
(20, 7, 'TAPE', 'MASKING 48MM', 'ROLL', 0, 0, '2025-09-18 05:40:03', '2025-09-18 05:40:03'),
(21, 8, 'CALCULATOR', 'COMPACT', 'PCS', 8, 0, '2025-09-18 05:40:17', '2025-09-18 08:28:28'),
(22, 9, 'DATA FILE BOX', 'N/A', 'PCS', 0, 0, '2025-09-18 05:40:34', '2025-09-18 05:40:34'),
(23, 2, 'INK 664', 'CYAN', 'BOT', 0, 0, '2026-01-22 05:45:42', '2026-01-22 05:45:42');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balance`
--

CREATE TABLE `leave_balance` (
  `balance_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` year(4) NOT NULL DEFAULT year(curdate()),
  `total_credits` decimal(6,3) NOT NULL DEFAULT 0.000 COMMENT 'Credits granted for the year',
  `used_days` decimal(6,3) NOT NULL DEFAULT 0.000 COMMENT 'Days consumed by approved requests',
  `remaining_days` decimal(6,3) GENERATED ALWAYS AS (`total_credits` - `used_days`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Normalized leave balance: one row per employee per leave type per year';

--
-- Dumping data for table `leave_balance`
--

INSERT INTO `leave_balance` (`balance_id`, `emp_id`, `leave_type_id`, `year`, `total_credits`, `used_days`, `created_at`, `updated_at`) VALUES
(5559, 54, 14, '2026', 5.000, 2.000, '2026-05-25 01:35:31', '2026-05-25 01:48:10'),
(5562, 43, 14, '2026', 5.000, 1.000, '2026-05-25 02:07:49', '2026-05-25 02:08:13'),
(5564, 43, 1, '2026', 5.000, 1.000, '2026-05-25 02:39:29', '2026-05-25 02:39:50'),
(5566, 43, 12, '2026', 5.000, 1.000, '2026-05-25 02:40:35', '2026-05-25 02:40:56');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balance_log`
--

CREATE TABLE `leave_balance_log` (
  `log_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `year` year(4) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `action` enum('add','deduct') NOT NULL,
  `days` decimal(6,3) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `performed_by` int(11) NOT NULL COMMENT 'emp_id of admin/unit head/focal person',
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Audit log for leave balance adjustments';

--
-- Dumping data for table `leave_balance_log`
--

INSERT INTO `leave_balance_log` (`log_id`, `emp_id`, `year`, `leave_type_id`, `action`, `days`, `reason`, `performed_by`, `performed_at`) VALUES
(9, 54, '2026', 14, 'add', 5.000, '', 32, '2026-05-25 01:35:31'),
(10, 43, '2026', 14, 'add', 5.000, '', 32, '2026-05-25 02:07:49'),
(11, 43, '2026', 1, 'add', 5.000, '', 32, '2026-05-25 02:39:29'),
(12, 43, '2026', 12, 'add', 5.000, '', 32, '2026-05-25 02:40:35');

-- --------------------------------------------------------

--
-- Table structure for table `leave_request`
--

CREATE TABLE `leave_request` (
  `leave_request_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `number_of_days` decimal(5,1) NOT NULL DEFAULT 0.0 COMMENT 'Working days computed from date range',
  `inclusive_dates` varchar(255) DEFAULT NULL COMMENT 'Human-readable date range label',
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `hr_remarks` text DEFAULT NULL COMMENT 'HR notes on approval or rejection',
  `approved_by` int(11) DEFAULT NULL COMMENT 'emp_id of HR who processed the request',
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_request`
--

INSERT INTO `leave_request` (`leave_request_id`, `emp_id`, `leave_type_id`, `date_from`, `date_to`, `number_of_days`, `inclusive_dates`, `reason`, `status`, `hr_remarks`, `approved_by`, `approved_at`, `created_at`, `updated_at`) VALUES
(45, 54, 14, '2026-05-28', '2026-05-28', 1.0, 'May 28, 2026', 'N/A', 'Approved', '', 32, '2026-05-25 09:36:06', '2026-05-25 01:35:56', '2026-05-25 01:36:06'),
(46, 54, 14, '2026-05-27', '2026-05-27', 1.0, 'May 27, 2026', 'fghfghfg', 'Approved', '', 32, '2026-05-25 09:48:10', '2026-05-25 01:48:06', '2026-05-25 01:48:10'),
(47, 43, 14, '2026-05-28', '2026-05-28', 1.0, 'May 28, 2026', 'asdsad', 'Approved', '', 32, '2026-05-25 10:08:13', '2026-05-25 02:08:09', '2026-05-25 02:08:13'),
(48, 43, 1, '2026-05-27', '2026-05-27', 1.0, 'May 27, 2026', '3', 'Approved', '', 32, '2026-05-25 10:39:50', '2026-05-25 02:39:47', '2026-05-25 02:39:50'),
(49, 43, 12, '2026-05-29', '2026-05-29', 1.0, 'May 29, 2026', 'sd', 'Approved', '', 32, '2026-05-25 10:40:56', '2026-05-25 02:40:53', '2026-05-25 02:40:56');

-- --------------------------------------------------------

--
-- Table structure for table `leave_type`
--

CREATE TABLE `leave_type` (
  `leave_type_id` int(11) NOT NULL,
  `leave_type_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `max_days` decimal(5,1) DEFAULT NULL COMMENT 'Max allowable days per year; NULL = unlimited',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_main` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Main/standard leave type | 0 = Other/supplemental leave type',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `default_credits` decimal(8,3) DEFAULT NULL COMMENT 'Default balance credits to seed per employee per year'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_type`
--

INSERT INTO `leave_type` (`leave_type_id`, `leave_type_name`, `description`, `max_days`, `is_active`, `is_main`, `created_at`, `updated_at`, `default_credits`) VALUES
(1, 'Vacation Leave', 'Used for personal travel, rest, or leisure.', 15.0, 1, 1, '2026-04-22 03:04:40', '2026-05-21 09:58:15', 15.000),
(2, 'Sick Leave', 'Used when the employee is ill or needs medical attention.', 15.0, 1, 1, '2026-04-22 03:04:40', '2026-05-21 09:58:20', 15.000),
(3, 'Maternity Leave', 'For female employees who gave birth or had a miscarriage.', 105.0, 1, 1, '2026-04-22 03:04:40', '2026-05-21 09:58:26', 105.000),
(4, 'Paternity Leave', 'For married male employees upon birth of a legitimate child.', 7.0, 1, 1, '2026-04-22 03:04:40', '2026-05-21 09:58:31', 7.000),
(5, 'Special Privilege Leave', 'For personal milestones: birthday, graduation, wedding, etc.', 3.0, 1, 1, '2026-04-22 03:04:40', '2026-05-21 09:58:35', 3.000),
(6, 'Solo Parent Leave', 'For solo parents under RA 8972.', 7.0, 1, 1, '2026-04-22 03:04:40', '2026-05-21 09:58:40', 7.000),
(7, 'Study Leave', 'For bar/board exam review or approved educational pursuits.', NULL, 1, 1, '2026-04-22 03:04:40', '2026-04-22 03:04:40', NULL),
(8, 'VAWC Leave', 'For victims of violence against women and their children.', 10.0, 1, 1, '2026-04-22 03:04:40', '2026-05-21 09:58:46', 10.000),
(9, 'Rehabilitation Leave', 'Due to work-related injuries or illness.', NULL, 1, 1, '2026-04-22 03:04:40', '2026-04-22 03:04:40', NULL),
(10, 'Special Emergency Leave', 'For calamity/disaster-affected employees.', 5.0, 1, 1, '2026-04-22 03:04:40', '2026-05-21 09:57:58', 5.000),
(11, 'Forced Leave', 'Mandatory leave consumption per CSC rules.', NULL, 1, 1, '2026-04-22 03:04:40', '2026-04-22 03:04:40', NULL),
(12, 'Terminal Leave', 'Commutation of unused leave credits upon separation.', 0.0, 1, 0, '2026-04-22 03:04:40', '2026-05-21 09:57:34', NULL),
(14, 'Wellness Leave', 'Granted to employees for health and wellness activities, preventive check-ups, or medical consultations as authorized by the agency.', 5.0, 1, 0, '2026-05-21 05:31:32', '2026-05-21 09:58:52', 5.000);

-- --------------------------------------------------------

--
-- Table structure for table `managers_office_staff`
--

CREATE TABLE `managers_office_staff` (
  `id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `position` varchar(100) NOT NULL,
  `responsibilities` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `managers_office_staff`
--

INSERT INTO `managers_office_staff` (`id`, `emp_id`, `position`, `responsibilities`, `created_at`) VALUES
(2, 26, 'Manager Staff', 'Document Controller', '2025-08-05 07:54:32'),
(4, 25, 'Manager Staff', 'Handle documents of Admin and Finance', '2025-08-07 01:55:42'),
(5, 27, 'Manager Staff', 'Public Relations Information', '2025-09-18 00:46:32');

-- --------------------------------------------------------

--
-- Table structure for table `message_reactions`
--

CREATE TABLE `message_reactions` (
  `reaction_id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `reaction_type` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('role_change','system','alert','message') NOT NULL DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `emp_id`, `title`, `message`, `type`, `is_read`, `created_at`, `read_at`) VALUES
(50, 32, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2026-05-11 06:02:39', NULL),
(51, 31, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2026-05-11 06:02:39', NULL),
(52, 29, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2026-05-11 06:02:49', NULL),
(53, 33, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2026-05-11 06:02:49', NULL),
(54, 32, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2026-05-11 06:02:49', NULL),
(55, 31, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2026-05-11 06:02:49', NULL),
(56, 32, 'New Role Assignment', 'You have been assigned as focal person of section Administrative Section', 'role_change', 0, '2026-05-11 06:03:13', NULL),
(57, 100, 'New Role Assignment', 'You have been assigned as head of unit a', 'role_change', 0, '2026-05-11 06:20:49', NULL),
(58, 98, 'New Assignment', 'You have been assigned to unit CONSTRUCTION UNIT', '', 0, '2026-05-13 03:10:40', NULL),
(59, 91, 'New Assignment', 'You have been assigned to unit CONSTRUCTION UNIT', '', 0, '2026-05-13 03:10:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `office`
--

CREATE TABLE `office` (
  `office_id` int(11) NOT NULL,
  `office_name` varchar(100) NOT NULL,
  `office_address` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `manager_emp_id` int(11) DEFAULT NULL,
  `is_main_office` tinyint(1) DEFAULT 0,
  `parent_office_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `office`
--

INSERT INTO `office` (`office_id`, `office_name`, `office_address`, `created_at`, `updated_at`, `manager_emp_id`, `is_main_office`, `parent_office_id`) VALUES
(1, 'NIA-Albay Office', 'Tuburan, Ligao City, Albay', '2025-05-20 03:00:21', '2025-07-24 07:42:01', 23, 1, NULL),
(10, 'NIA-Catanduanes Office', 'Virac, Catanduanes', '2025-10-11 03:06:59', '2025-10-11 03:06:59', NULL, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `others_leave_type`
--

CREATE TABLE `others_leave_type` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `default_credits` decimal(8,3) NOT NULL DEFAULT 0.000,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `others_leave_type`
--

INSERT INTO `others_leave_type` (`id`, `name`, `description`, `default_credits`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Monetization of Leave Credits', 'Application for monetization of fifty percent (50%) or more of the accumulated leave credits.', 0.000, 1, '2026-05-21 12:04:56', '2026-05-21 12:04:56'),
(2, 'Terminal Leave', 'Commutation of unused vacation and sick leave credits upon resignation, retirement, or separation from service.', 0.000, 1, '2026-05-21 12:04:56', '2026-05-21 12:04:56'),
(3, 'Adoption Leave', 'Granted to adopting parents under R.A. No. 8552 (Domestic Adoption Act).', 0.000, 1, '2026-05-21 12:04:56', '2026-05-21 12:04:56'),
(4, 'Wellness Leave', 'Granted to employees for health and wellness activities, preventive check-ups, or medical consultations as authorized by the agency.', 0.000, 1, '2026-05-21 12:04:56', '2026-05-21 12:04:56');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `reset_token` varchar(64) NOT NULL,
  `token_expiry` datetime NOT NULL,
  `status` enum('pending','approved','rejected','used') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_requests`
--

INSERT INTO `password_reset_requests` (`id`, `emp_id`, `reset_token`, `token_expiry`, `status`, `created_at`, `approved_by`, `approved_at`, `used_at`) VALUES
(10, 31, '5ad6438255c26f99eb0a4fddb2c0a08e2877b2e243ac889dbc4241098e2697e2', '2025-10-11 11:11:56', 'used', '2025-10-11 16:11:56', 32, '2025-10-11 16:11:59', '2025-10-11 16:17:09'),
(11, 26, '4c2c07179f42d70220ec0f49641a1d23c50c749c3a373bdc7ddd7627d250cf50', '2025-10-11 11:18:34', 'used', '2025-10-11 16:18:34', 32, '2025-10-11 16:19:09', '2025-10-11 16:21:11'),
(12, 31, 'af230d47e1f0e5f44e25b262dcaa2b780d9b2549e61f02dba395e30abdf5b2ab', '2025-10-11 11:19:03', 'used', '2025-10-11 16:19:03', 32, '2025-10-11 16:19:08', '2025-10-11 16:19:26'),
(13, 31, 'f9d571504a857cd9ec620b9cac845fa47307e56419d4bbd92171722ff6fb6702', '2025-10-11 11:23:45', 'approved', '2025-10-11 16:23:45', 32, '2025-10-11 16:23:47', NULL),
(14, 56, '66ca4725d98976393ae63000fdd6c67ecdd5e2e01f7a363f73eb4c3a610e57f0', '2025-10-15 08:29:44', 'rejected', '2025-10-15 13:29:45', 32, '2025-10-15 13:29:54', NULL),
(15, 56, '0595f103506374a7f1368557fd2942f4c9ea38d9cf66039b7f7352c689aceb92', '2025-10-15 09:18:03', 'approved', '2025-10-15 14:18:03', 32, '2025-10-20 14:05:00', NULL),
(16, 56, 'baa85edcee1ac30228435d2a56c44f84dd0bf11f4efec7d2b0f91a7a00f3304b', '2025-10-15 09:18:59', 'approved', '2025-10-15 14:18:59', 32, '2025-10-20 14:04:59', NULL),
(17, 32, '3b61e42caa8521acad8c7312348cb28b0f269dc4fe106885b074db17dff642d6', '2025-10-20 09:04:48', 'approved', '2025-10-20 14:04:48', 32, '2025-10-20 14:04:58', NULL),
(18, 26, '2687eede4ee47c51b6a0af467f60aeeac03488b31654951a470155f3ab0d8fc2', '2025-10-20 09:11:52', 'approved', '2025-10-20 14:11:52', 32, '2025-10-20 14:12:00', NULL),
(19, 26, '0b711681667c4533b82a41a4fb1c9b4857fac77f95aeb8cc8b4e4498c53efe8c', '2025-10-20 09:13:01', 'approved', '2025-10-20 14:13:01', 32, '2025-10-20 14:16:24', NULL),
(20, 54, '0af1ad755dc92e4a58696473b7469136903502e902102f9934364be8ae0b676a', '2025-10-20 09:16:15', 'approved', '2025-10-20 14:16:15', 32, '2025-10-20 14:16:24', NULL),
(21, 26, '6ddddc8c24bbf94a7632f21574d46b55710cf6e32d757f9cc0711e19d1f865b0', '2025-10-20 09:37:53', 'approved', '2025-10-20 14:37:53', 32, '2025-10-20 14:38:04', NULL),
(22, 59, '536a7b58e80359b4dde44276d451f23be7c2c06033f07aa543c0922d17fbec77', '2025-10-20 09:40:24', 'rejected', '2025-10-20 14:40:24', 32, '2025-10-20 14:47:01', NULL),
(23, 31, '0e87477e512f77a98b999ae855faa0fc86aed4467ddc07019001e2e593b19c8f', '2025-10-20 09:40:55', 'approved', '2025-10-20 14:40:55', 32, '2025-10-20 14:41:38', NULL),
(24, 31, '73cd50383b5d0b712d9c790f277461656e492f2d4ef354ca322f4065c02b3de2', '2025-10-20 09:44:36', 'approved', '2025-10-20 14:44:36', 32, '2025-10-20 14:44:43', NULL),
(25, 54, 'd646fc7d6f422bc1754059b004566519ad2431911bcbbe9e0f1440bb97a878c9', '2026-03-17 08:56:18', 'approved', '2026-03-17 14:56:18', 32, '2026-03-17 14:56:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'manage_users', 'Create, edit and delete users', '2025-06-03 08:12:39'),
(2, 'manage_roles', 'Manage roles and permissions', '2025-06-03 08:12:39'),
(3, 'view_dashboard', 'Access the dashboard', '2025-06-03 08:12:39'),
(4, 'manage_employees', 'Manage employee records', '2025-06-03 08:12:39'),
(5, 'view_reports', 'View system reports', '2025-06-03 08:12:39'),
(6, 'manage_settings', 'Change system settings', '2025-06-03 08:12:39'),
(14, 'manage_permissions', 'Manage Permissions', '2025-06-09 06:51:07'),
(15, 'view_calendar', 'View calendar reports', '2025-06-09 07:48:43'),
(16, 'create_employees', 'Create new employees', '2025-06-09 08:18:44'),
(17, 'view_employees', 'View employees details', '2025-06-09 08:19:15'),
(24, 'manage_appointment', 'Manage employee appointment', '2025-07-11 02:34:54'),
(25, 'manage_position', 'Manage employee position', '2025-07-11 02:35:24'),
(26, 'manage_offices', 'Manage employee offices', '2025-07-11 02:35:56'),
(27, 'manage_employmentstatus', 'Manage employee employment status', '2025-07-11 02:37:02'),
(28, 'delete_employees', 'Deleting existing employees', '2025-07-11 02:37:46'),
(29, 'edit_employees', 'Updating existing employee', '2025-07-11 02:38:16'),
(31, 'manage_transfer', 'Managing transfer documents', '2025-07-16 05:15:22'),
(32, 'view_any_document', 'View any document', '2025-07-18 03:26:23'),
(33, 'edit_any_document', 'Edit any document', '2025-07-18 03:26:36'),
(34, 'delete_any_document', 'Delete any document', '2025-07-18 03:26:51'),
(35, 'transfer_document', 'Transfer Document', '2025-07-18 03:27:37'),
(36, 'download_document', 'Download Document', '2025-07-18 06:17:12'),
(37, 'process_attachment', 'Processing DTR Attachments', '2025-10-08 06:21:14'),
(38, 'monitor_procurement', 'Monitoring the Procurement Procedure', '2025-10-09 05:40:22'),
(39, 'edit_content', 'Editing Content', '2025-10-11 06:35:16'),
(41, 'manage_ict_maintenance', 'Managing the ICT Equipments', '2025-10-17 06:28:33');

-- --------------------------------------------------------

--
-- Table structure for table `personal_locator_slips`
--

CREATE TABLE `personal_locator_slips` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `leave_time` time NOT NULL,
  `purpose_type` enum('personal','official') NOT NULL,
  `purpose_details` text NOT NULL,
  `expected_return` time DEFAULT NULL,
  `no_return` tinyint(1) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personal_locator_slips`
--

INSERT INTO `personal_locator_slips` (`id`, `employee_id`, `date`, `leave_time`, `purpose_type`, `purpose_details`, `expected_return`, `no_return`, `status`, `approved_by`, `approved_at`, `created_at`, `updated_at`) VALUES
(29, 160, '2025-10-22', '09:00:00', 'official', 'Go to notary for notarization of docs', '12:00:00', 0, 'approved', 32, '2025-10-22 08:58:21', '2025-10-22 00:58:09', '2025-10-22 00:58:21'),
(30, 160, '2025-10-22', '15:00:00', 'official', 'Go to Post Office ', '17:00:00', 0, 'approved', 32, '2025-10-22 15:19:29', '2025-10-22 07:19:25', '2025-10-22 07:19:29'),
(31, 32, '2025-11-03', '14:00:00', 'personal', 'Go to Landback', '15:00:00', 0, 'approved', 32, '2025-11-03 13:47:58', '2025-11-03 05:47:51', '2025-11-03 05:47:58'),
(32, 40, '2025-11-04', '08:18:00', 'official', 'Go to aleco', '17:18:00', 0, 'approved', 32, '2025-11-04 08:18:25', '2025-11-04 00:18:22', '2025-11-04 00:18:25'),
(33, 53, '2025-11-05', '14:00:00', 'official', 'Go to Landbank to deposit cash', '15:00:00', 0, 'approved', 32, '2025-11-05 11:41:18', '2025-11-05 03:41:12', '2025-11-05 03:41:18'),
(34, 160, '2025-11-07', '13:00:00', 'official', 'to pay utility bills (ALECO, PNB, Water District)', '17:00:00', 0, 'approved', 31, '2025-11-07 12:09:35', '2025-11-07 04:09:25', '2025-11-07 04:09:35'),
(35, 46, '2025-12-16', '14:00:00', 'personal', 'Personal', '15:00:00', 0, 'approved', 32, '2025-12-16 13:44:07', '2025-12-16 05:44:04', '2025-12-16 05:44:07'),
(36, 50, '2025-12-16', '15:20:00', 'official', 'N/A', '17:00:00', 0, 'approved', 32, '2025-12-16 15:03:12', '2025-12-16 07:03:10', '2025-12-16 07:03:12'),
(38, 82, '2026-01-06', '13:00:00', 'official', 'VISIT APE CONSULTATION', '17:00:00', 0, 'approved', 32, '2026-01-06 11:01:08', '2026-01-06 02:59:25', '2026-01-06 03:01:08'),
(39, 54, '2026-01-06', '13:00:00', 'official', 'VISIT APE CONSULTATION', '17:00:00', 0, 'approved', 32, '2026-01-06 11:01:08', '2026-01-06 03:00:51', '2026-01-06 03:01:08'),
(40, 29, '2026-01-06', '13:30:00', 'personal', 'Personal', '14:30:00', 0, 'approved', 32, '2026-01-06 13:22:42', '2026-01-06 05:22:40', '2026-01-06 05:22:42'),
(41, 40, '2026-01-06', '13:40:00', 'official', 'Pay utility bills: PNB and DCTV ', '17:00:00', 0, 'approved', 32, '2026-01-06 13:38:25', '2026-01-06 05:36:51', '2026-01-06 05:38:25'),
(42, 48, '2026-01-07', '15:00:00', 'personal', 'N/A', '16:00:00', 0, 'approved', 32, '2026-01-07 13:18:38', '2026-01-07 05:18:05', '2026-01-07 05:18:38'),
(43, 47, '2026-01-07', '15:00:00', 'personal', 'N/A', '16:00:00', 0, 'approved', 32, '2026-01-07 13:18:38', '2026-01-07 05:18:34', '2026-01-07 05:18:38'),
(44, 160, '2026-01-07', '15:00:00', 'personal', 'N/A', '16:00:00', 0, 'approved', 32, '2026-01-07 14:27:35', '2026-01-07 06:27:33', '2026-01-07 06:27:35'),
(45, 50, '2026-01-07', '15:00:00', 'personal', 'N/A', '16:00:00', 0, 'approved', 32, '2026-01-07 14:43:03', '2026-01-07 06:41:49', '2026-01-07 06:43:03'),
(46, 53, '2026-01-07', '15:00:00', 'personal', 'N/A', '16:00:00', 0, 'approved', 32, '2026-01-07 14:43:03', '2026-01-07 06:42:23', '2026-01-07 06:43:03'),
(47, 49, '2026-01-07', '15:00:00', 'personal', 'N/A', '16:00:00', 0, 'approved', 32, '2026-01-07 14:43:03', '2026-01-07 06:42:58', '2026-01-07 06:43:03'),
(48, 49, '2026-01-08', '13:10:00', 'official', 'Landbank to deposit cash.', '17:00:00', 0, 'approved', 32, '2026-01-08 13:05:30', '2026-01-08 05:05:28', '2026-01-08 05:05:30'),
(49, 96, '2026-01-08', '14:30:00', 'personal', 'N/A', '15:30:00', 0, 'approved', 32, '2026-01-08 14:05:09', '2026-01-08 06:04:38', '2026-01-08 06:05:09'),
(50, 102, '2026-01-08', '14:30:00', 'personal', 'N/A', '15:30:00', 0, 'approved', 32, '2026-01-08 14:05:09', '2026-01-08 06:05:04', '2026-01-08 06:05:09'),
(52, 160, '2026-01-13', '10:30:00', 'official', 'Pay Electrical Utility Bill ', '00:00:00', 0, 'approved', 31, '2026-01-13 09:58:21', '2026-01-13 01:58:06', '2026-01-13 01:58:21'),
(53, 26, '2026-01-21', '16:34:00', 'official', 'HFKJGJF', '17:35:00', 0, 'approved', 32, '2026-02-03 15:26:29', '2026-01-21 08:35:07', '2026-02-03 07:26:29'),
(54, 40, '2026-02-09', '10:37:00', 'official', 'TO pay utility bills', '17:00:00', 0, 'approved', 32, '2026-02-09 10:37:36', '2026-02-09 02:37:34', '2026-02-09 02:37:36'),
(55, 26, '2026-03-05', '14:24:00', 'personal', 'kjashhfnks ', '00:00:00', 1, 'approved', 32, '2026-03-09 11:44:17', '2026-03-05 06:24:22', '2026-03-09 03:44:17'),
(56, 96, '2026-05-18', '14:30:00', 'personal', 'personal', '15:30:00', 0, 'approved', 32, '2026-05-18 14:01:06', '2026-05-18 06:01:02', '2026-05-18 06:01:06'),
(57, 43, '2026-05-20', '13:40:00', 'personal', 'Personal', '14:40:00', 0, 'approved', 32, '2026-05-20 13:30:04', '2026-05-20 05:29:39', '2026-05-20 05:30:04'),
(58, 98, '2026-05-20', '13:40:00', 'personal', 'Personal', '14:40:00', 0, 'approved', 32, '2026-05-20 13:30:04', '2026-05-20 05:30:00', '2026-05-20 05:30:04'),
(59, 91, '2026-05-20', '13:40:00', 'personal', 'Personal', '14:40:00', 0, 'pending', NULL, NULL, '2026-05-20 05:33:58', '2026-05-20 05:33:58');

-- --------------------------------------------------------

--
-- Table structure for table `position`
--

CREATE TABLE `position` (
  `position_id` int(11) NOT NULL,
  `position_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `position`
--

INSERT INTO `position` (`position_id`, `position_name`, `created_at`, `updated_at`) VALUES
(1, 'Engineer A', '2025-05-20 02:55:08', '2025-05-20 02:55:08'),
(2, 'Engineer B', '2025-05-20 02:55:34', '2025-05-20 02:55:54'),
(3, 'Supervising Engineer A', '2025-05-20 02:55:36', '2025-05-22 02:00:14'),
(7, 'Data Encoder', '2025-05-21 08:45:25', '2025-05-22 06:07:27'),
(8, 'Corporate Accounts Analyst', '2025-05-22 02:00:26', '2025-05-22 02:00:26'),
(9, 'Sr. Accounting Processor B', '2025-05-22 02:00:35', '2025-05-22 02:00:35'),
(10, 'Clerk Processor B', '2025-05-22 02:00:47', '2025-05-22 02:00:47'),
(11, 'Cashiering Assistant', '2025-05-22 02:00:53', '2025-05-22 02:00:53'),
(12, 'Clerk Processor C', '2025-05-22 02:00:58', '2025-05-22 02:00:58'),
(13, 'Accounting Processor A	', '2025-05-22 02:01:04', '2025-05-22 02:01:04'),
(14, 'Senior Engineer A', '2025-05-22 02:01:17', '2025-05-22 02:01:17'),
(15, 'Survey Aide A', '2025-05-22 02:01:21', '2025-05-22 02:01:21'),
(16, 'Engineering Assistant A', '2025-05-22 02:01:27', '2025-05-22 02:01:27'),
(17, 'Foreman A', '2025-05-22 02:01:32', '2025-05-22 02:01:32'),
(18, 'Senior Draftsman', '2025-05-22 02:03:05', '2025-05-22 02:03:05'),
(19, 'Utility Worker A ', '2025-05-22 02:03:11', '2025-05-22 02:03:11'),
(20, 'Senior IDO', '2025-05-22 02:03:16', '2025-05-22 02:03:16'),
(21, 'Driver Mechanic A', '2025-05-22 02:03:21', '2025-05-22 02:03:21'),
(22, 'SWRFT', '2025-05-22 02:03:26', '2025-05-22 02:03:26'),
(23, 'Driver Mechanic B', '2025-05-22 02:03:31', '2025-05-22 02:03:31'),
(24, 'WRFO', '2025-05-22 02:03:35', '2025-05-22 02:03:35'),
(25, 'IDO A', '2025-05-22 02:03:40', '2025-06-02 08:35:42'),
(26, 'Heavy Equipment Operator', '2025-05-22 02:03:47', '2025-05-22 02:03:47'),
(27, 'Information Officer C', '2025-05-22 02:03:52', '2025-05-22 02:03:52'),
(28, 'Admin. Services Officer B', '2025-05-22 02:03:58', '2025-05-22 02:03:58'),
(29, 'Procurement Analyst B', '2025-05-22 02:04:04', '2025-05-22 06:32:43'),
(30, 'Industrial Security Guard A', '2025-05-22 02:04:17', '2025-05-22 02:04:17'),
(31, 'Property Officer B', '2025-05-22 02:04:24', '2025-05-22 02:04:24'),
(32, 'Admin. Services Aide', '2025-05-22 02:04:29', '2025-05-22 02:04:29'),
(33, 'Welder A', '2025-05-22 02:04:33', '2025-05-22 02:04:33'),
(37, 'Data Encoder I', '2025-05-22 06:07:00', '2025-05-22 06:07:00'),
(39, 'Research Assistant B', '2025-06-02 08:21:49', '2025-06-02 08:21:49'),
(40, 'Research Assistant A', '2025-06-02 08:21:59', '2025-06-02 08:21:59'),
(41, 'Engineering Assistant B', '2025-06-02 08:24:29', '2025-06-02 08:24:29'),
(42, 'Utility Worker B', '2025-06-02 08:31:35', '2025-06-02 08:31:35'),
(43, 'Draftsman', '2025-06-02 08:43:39', '2025-06-02 08:43:39'),
(45, 'Super Engineer', '2025-10-18 03:04:13', '2025-10-18 03:04:13'),
(46, 'Corporate Budget Assistant', '2026-05-19 09:55:03', '2026-05-19 09:55:03');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `project_description` text DEFAULT NULL,
  `project_code` varchar(50) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','completed','on_hold','cancelled') DEFAULT 'active',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `color` varchar(7) DEFAULT '#007bff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`project_id`, `project_name`, `project_description`, `project_code`, `created_by`, `created_at`, `updated_at`, `status`, `start_date`, `end_date`, `color`) VALUES
(10, 'SOLAR PROJECT', 'SOLAR PROJECT LEGAZPI CITYY', 'SPIP', 32, '2025-11-03 08:51:42', '2025-11-03 08:51:42', 'active', '2025-11-01', '2026-11-03', '#007bff'),
(11, '1st Project', '1', '1st', 32, '2025-11-04 05:31:42', '2025-11-04 05:31:42', 'active', '2025-11-01', '2025-11-04', '#000000'),
(12, 'SQ DAM Project dummy', '', 'SQ2', 32, '2026-05-11 09:26:48', '2026-05-11 09:26:48', 'active', '2026-05-11', '2029-06-11', '#bbff00'),
(13, 'DaA', 'ASGA', 'AS', 26, '2026-06-02 00:08:35', '2026-06-02 00:08:35', 'active', '2026-06-10', '2026-06-15', '#007bff'),
(14, 'NASISI DAM Project dummy', '', 'NSSSPIP', 54, '2026-06-04 08:52:22', '2026-06-04 08:52:22', 'active', '2026-06-04', '2030-07-04', '#007bff'),
(15, '2026 ACIMO BL', '.21321', 'ABLDG', 26, '2026-06-04 09:30:32', '2026-06-04 09:30:32', 'active', '2026-06-30', '2027-06-30', '#007bff');

-- --------------------------------------------------------

--
-- Table structure for table `project_boards`
--

CREATE TABLE `project_boards` (
  `board_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `board_name` varchar(100) NOT NULL,
  `board_description` text DEFAULT NULL,
  `board_order` int(11) DEFAULT 0,
  `board_color` varchar(7) DEFAULT '#007bff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_boards`
--

INSERT INTO `project_boards` (`board_id`, `project_id`, `board_name`, `board_description`, `board_order`, `board_color`, `created_at`, `updated_at`) VALUES
(49, 10, 'Done', 'Completed tasks', 5, '#10B981', '2025-11-03 08:51:42', '2025-11-03 08:51:42'),
(50, 10, '1', '1', 6, '#007bff', '2025-11-04 02:32:23', '2025-11-04 02:32:23'),
(51, 11, 'Backlog', 'Tasks that are planned but not yet started', 1, '#6B7280', '2025-11-04 05:31:42', '2025-11-04 05:31:42'),
(52, 11, 'To Do', 'Tasks ready to be worked on', 2, '#3B82F6', '2025-11-04 05:31:42', '2025-11-04 05:31:42'),
(53, 11, 'In Progress', 'Tasks currently being worked on', 3, '#F59E0B', '2025-11-04 05:31:42', '2025-11-04 05:31:42'),
(54, 11, 'Review', 'Tasks awaiting review', 4, '#8B5CF6', '2025-11-04 05:31:42', '2025-11-04 05:31:42'),
(55, 11, 'Done', 'Completed tasks', 5, '#10b981', '2025-11-04 05:31:42', '2026-05-11 09:25:42'),
(57, 12, 'Backlog', 'Tasks that are planned but not yet started', 1, '#6B7280', '2026-05-11 09:26:48', '2026-05-11 09:26:48'),
(58, 12, 'To Do', 'Tasks ready to be worked on', 2, '#3B82F6', '2026-05-11 09:26:48', '2026-05-11 09:26:48'),
(59, 12, 'In Progress', 'Tasks currently being worked on', 3, '#F59E0B', '2026-05-11 09:26:48', '2026-05-11 09:26:48'),
(60, 12, 'Review', 'Tasks awaiting review', 4, '#8B5CF6', '2026-05-11 09:26:48', '2026-05-11 09:26:48'),
(61, 12, 'Doneklp,', 'Completed tasks', 5, '#10b981', '2026-05-11 09:26:48', '2026-05-18 08:42:39'),
(62, 12, 'billing', 'bhjhygjhgj', 6, '#000000', '2026-05-18 08:38:02', '2026-05-18 08:38:02'),
(64, 13, 'Backlog', 'Tasks that are planned but not yet started', 1, '#6B7280', '2026-06-02 00:08:35', '2026-06-02 00:08:35'),
(65, 13, 'To Do', 'Tasks ready to be worked on', 2, '#3B82F6', '2026-06-02 00:08:35', '2026-06-02 00:08:35'),
(66, 13, 'In Progress', 'Tasks currently being worked on', 3, '#F59E0B', '2026-06-02 00:08:35', '2026-06-02 00:08:35'),
(67, 13, 'Review', 'Tasks awaiting review', 4, '#8B5CF6', '2026-06-02 00:08:35', '2026-06-02 00:08:35'),
(68, 13, 'Done', 'Completed tasks', 5, '#10B981', '2026-06-02 00:08:35', '2026-06-02 00:08:35'),
(69, 14, 'Backlog', 'Tasks that are planned but not yet started', 1, '#6B7280', '2026-06-04 08:52:22', '2026-06-04 08:52:22'),
(70, 14, 'To Do', 'Tasks ready to be worked on', 2, '#3B82F6', '2026-06-04 08:52:22', '2026-06-04 08:52:22'),
(71, 14, 'In Progress', 'Tasks currently being worked on', 3, '#F59E0B', '2026-06-04 08:52:22', '2026-06-04 08:52:22'),
(72, 14, 'Review', 'Tasks awaiting review', 4, '#8B5CF6', '2026-06-04 08:52:22', '2026-06-04 08:52:22'),
(73, 14, 'Done', 'Completed tasks', 5, '#10B981', '2026-06-04 08:52:22', '2026-06-04 08:52:22'),
(74, 15, 'Backlog', 'Tasks that are planned but not yet started', 1, '#6B7280', '2026-06-04 09:30:32', '2026-06-04 09:30:32'),
(75, 15, 'To Do', 'Tasks ready to be worked on', 2, '#3B82F6', '2026-06-04 09:30:32', '2026-06-04 09:30:32'),
(76, 15, 'In Progress', 'Tasks currently being worked on', 3, '#F59E0B', '2026-06-04 09:30:32', '2026-06-04 09:30:32'),
(77, 15, 'Review', 'Tasks awaiting review', 4, '#8B5CF6', '2026-06-04 09:30:32', '2026-06-04 09:30:32'),
(78, 15, 'Done', 'Completed tasks', 5, '#10B981', '2026-06-04 09:30:32', '2026-06-04 09:30:32'),
(79, 15, 'ON-GOING', '', 3, '#007bff', '2026-06-04 09:37:10', '2026-06-04 09:37:10');

-- --------------------------------------------------------

--
-- Table structure for table `project_members`
--

CREATE TABLE `project_members` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `role` enum('owner','member','viewer') DEFAULT 'member',
  `added_by` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_members`
--

INSERT INTO `project_members` (`id`, `project_id`, `emp_id`, `role`, `added_by`, `added_at`) VALUES
(7, 10, 32, 'owner', 32, '2025-11-03 08:51:42'),
(8, 11, 32, 'owner', 32, '2025-11-04 05:31:42'),
(9, 12, 32, 'owner', 32, '2026-05-11 09:26:48'),
(10, 13, 26, 'owner', 26, '2026-06-02 00:08:35'),
(11, 14, 54, 'owner', 54, '2026-06-04 08:52:22'),
(12, 15, 26, 'owner', 26, '2026-06-04 09:30:32');

-- --------------------------------------------------------

--
-- Table structure for table `provinces`
--

CREATE TABLE `provinces` (
  `id` int(11) NOT NULL,
  `region_code` varchar(10) NOT NULL,
  `province_code` varchar(10) NOT NULL,
  `province_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `provinces`
--

INSERT INTO `provinces` (`id`, `region_code`, `province_code`, `province_name`) VALUES
(1, 'V', 'ALB', 'Albay'),
(2, 'V', 'CAN', 'Camarines Norte'),
(3, 'V', 'CAS', 'Camarines Sur'),
(4, 'V', 'CAT', 'Catanduanes'),
(5, 'V', 'MAS', 'Masbate'),
(6, 'V', 'SOR', 'Sorsogon');

-- --------------------------------------------------------

--
-- Table structure for table `queue_history`
--

CREATE TABLE `queue_history` (
  `id` int(11) NOT NULL,
  `queue_id` int(11) DEFAULT NULL,
  `queue_number` varchar(20) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `performed_at` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue_settings`
--

CREATE TABLE `queue_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `queue_settings`
--

INSERT INTO `queue_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'queue_prefix', 'V', NULL, '2025-12-08 07:17:02'),
(2, 'current_queue_number', '0', NULL, '2025-12-08 07:17:02'),
(3, 'counters', '4', NULL, '2025-12-08 07:17:02'),
(4, 'average_service_time', '5', 'Average service time in minutes', '2025-12-08 08:10:14'),
(5, 'office_hours_start', '08:00:00', 'Office opening time', '2025-12-08 08:10:14'),
(6, 'office_hours_end', '17:00:00', 'Office closing time', '2025-12-08 08:10:14'),
(7, 'average_service_time', '5', 'Average service time in minutes', '2025-12-08 08:10:14'),
(8, 'office_hours_start', '08:00:00', 'Office opening time', '2025-12-08 08:10:14'),
(9, 'office_hours_end', '17:00:00', 'Office closing time', '2025-12-08 08:10:14'),
(10, 'auto_call_interval', '30', 'Auto-call interval in seconds', '2025-12-08 08:10:14'),
(11, 'display_refresh_rate', '10', 'Display refresh rate in seconds', '2025-12-08 08:10:14');

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE `regions` (
  `id` int(11) NOT NULL,
  `region_code` varchar(10) NOT NULL,
  `region_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `regions`
--

INSERT INTO `regions` (`id`, `region_code`, `region_name`) VALUES
(1, 'V', 'Region V - Bicol Region');

-- --------------------------------------------------------

--
-- Table structure for table `ris_items`
--

CREATE TABLE `ris_items` (
  `id` int(11) NOT NULL,
  `ris_id` int(11) DEFAULT NULL,
  `item_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `movement_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ris_items`
--

INSERT INTO `ris_items` (`id`, `ris_id`, `item_id`, `description`, `unit`, `quantity`, `movement_id`) VALUES
(7, 5, 15, 'FLASH DRIVE', 'PCS', 2, 29),
(8, 5, 16, 'MOUSE', 'PCS', 2, 30),
(9, 6, 11, 'INK', 'BOTTLE', 10, 50);

-- --------------------------------------------------------

--
-- Table structure for table `ris_records`
--

CREATE TABLE `ris_records` (
  `id` int(11) NOT NULL,
  `ris_number` varchar(50) DEFAULT NULL,
  `iar_id` int(11) DEFAULT NULL,
  `requisition_office` varchar(255) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `requested_by` varchar(255) DEFAULT NULL,
  `requested_by_id` int(11) DEFAULT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ris_records`
--

INSERT INTO `ris_records` (`id`, `ris_number`, `iar_id`, `requisition_office`, `purpose`, `requested_by`, `requested_by_id`, `designation`, `created_by`, `created_at`) VALUES
(5, '2025-0001', 9, 'Engineering Section', 'OFFICE SUPPLIES', 'Gio Dominick M. MANLANGIT', 96, 'Engineering Assistant B', 10, '2025-09-18 05:43:50'),
(6, '2025-0002', 19, 'Administrative Section', 'OFFICE SUPPLIES', ' Jewel SIGUENZA', 102, 'Engineer A', 10, '2025-09-23 00:33:32');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 24),
(1, 25),
(1, 26),
(1, 27),
(1, 28),
(1, 29),
(1, 31),
(1, 32),
(1, 33),
(1, 34),
(1, 35),
(1, 36),
(1, 37),
(1, 38),
(1, 39),
(1, 41),
(2, 3),
(2, 5),
(2, 15),
(2, 17),
(2, 31),
(12, 3),
(12, 5),
(12, 15),
(12, 17),
(12, 31),
(13, 3),
(13, 15),
(13, 31),
(13, 32),
(13, 36),
(14, 3),
(14, 15),
(16, 3),
(16, 4),
(16, 15),
(16, 17),
(16, 29),
(16, 37);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(10) UNSIGNED NOT NULL,
  `room_name` varchar(120) NOT NULL,
  `capacity` smallint(5) UNSIGNED NOT NULL DEFAULT 10,
  `floor_location` varchar(80) DEFAULT NULL COMMENT 'e.g. 2nd Floor, Building A',
  `description` text DEFAULT NULL,
  `amenities` text DEFAULT NULL COMMENT 'Comma-separated: Projector, AC, Wi-Fi',
  `color` varchar(30) DEFAULT 'rc-icon-blue' COMMENT 'CSS class for card icon color',
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_name`, `capacity`, `floor_location`, `description`, `amenities`, `color`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Conference Room A', 20, '2nd Floor, Main Building', 'Large conference room with panoramic windows.', 'Projector, Whiteboard, AC, Wi-Fi, Video Conferencing', 'rc-icon-blue', 'active', '2026-05-28 11:48:26', NULL),
(2, 'Board Room', 12, '3rd Floor, Main Building', 'Exclusive boardroom for executive meetings.', 'LED Screen, Whiteboard, AC, Wi-Fi, Coffee Station', 'rc-icon-purple', 'active', '2026-05-28 11:48:26', NULL),
(3, 'Training Room', 40, '1st Floor, Annex Building', 'Spacious training room with individual workstations.', 'Projector, Whiteboard, AC, Wi-Fi, Podium, Microphone', 'rc-icon-green', 'active', '2026-05-28 11:48:26', NULL),
(4, 'Meeting Room B', 8, '2nd Floor, Main Building', 'Small meeting room for team huddles.', 'TV Monitor, Whiteboard, AC, Wi-Fi', 'rc-icon-cyan', 'active', '2026-05-28 11:48:26', NULL),
(5, 'Function Hall', 100, 'Ground Floor, Main Building', 'Large function hall for events and seminars.', 'Stage, Projector, PA System, AC, Wi-Fi, Microphone', 'rc-icon-yellow', 'active', '2026-05-28 11:48:26', NULL),
(6, 'Big Conference', 50, 'Ground Floor', '', 'AC,Aircon', 'rc-icon-green', 'active', '2026-05-29 08:58:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `room_reservations`
--

CREATE TABLE `room_reservations` (
  `reservation_id` int(10) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED NOT NULL,
  `emp_id` int(11) NOT NULL COMMENT 'FK → employee.emp_id',
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `attendees` smallint(5) UNSIGNED DEFAULT 0,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_reservations`
--

INSERT INTO `room_reservations` (`reservation_id`, `room_id`, `emp_id`, `reservation_date`, `start_time`, `end_time`, `purpose`, `description`, `attendees`, `status`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 4, 32, '2026-05-28', '13:52:00', '14:52:00', 'a', 'a', 5, 'approved', '', '2026-05-28 11:52:46', '2026-05-28 11:52:53'),
(2, 2, 32, '2026-05-28', '12:06:00', '13:06:00', 'dsfsdf', 'dfds', 3, 'cancelled', '', '2026-05-28 12:06:33', '2026-05-28 12:06:38'),
(3, 2, 32, '2026-05-29', '21:54:00', '22:54:00', 'a', 'a', 0, 'approved', '', '2026-05-29 08:54:42', '2026-05-29 08:54:58'),
(4, 2, 32, '2026-05-29', '09:55:00', '11:55:00', '3', '3', 0, 'approved', '', '2026-05-29 08:55:29', '2026-05-29 08:55:38');

-- --------------------------------------------------------

--
-- Table structure for table `section`
--

CREATE TABLE `section` (
  `section_id` int(11) NOT NULL,
  `office_id` int(11) DEFAULT NULL,
  `section_name` varchar(100) NOT NULL,
  `section_code` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `head_emp_id` int(11) DEFAULT NULL,
  `default_status_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section`
--

INSERT INTO `section` (`section_id`, `office_id`, `section_name`, `section_code`, `created_at`, `updated_at`, `head_emp_id`, `default_status_id`) VALUES
(1, 1, 'Administrative Section', 'ADM', '2025-05-20 03:03:25', '2025-08-05 03:01:15', 105, NULL),
(2, 1, 'Finance Section', 'FIN', '2025-05-20 03:03:30', '2025-11-05 03:42:09', 52, NULL),
(3, 1, 'Engineering Section', 'ENG', '2025-05-20 03:03:57', '2025-08-05 02:59:42', 54, NULL),
(4, 1, 'Operation and Maintenance Section', 'OMS', '2025-05-20 03:04:00', '2025-08-05 05:16:54', 105, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `section_secretaries`
--

CREATE TABLE `section_secretaries` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `request_id` int(11) NOT NULL,
  `request_no` varchar(20) NOT NULL,
  `requesting_emp_id` int(11) NOT NULL,
  `supervisor_emp_id` int(11) DEFAULT NULL,
  `date_requested` date NOT NULL,
  `date_of_travel` date NOT NULL,
  `date_of_travel_end` date DEFAULT NULL,
  `time_departure` time NOT NULL,
  `time_return` time NOT NULL,
  `destination` varchar(255) NOT NULL,
  `purpose` text NOT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `driver_emp_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed','cancelled') DEFAULT 'pending',
  `date_completed` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_requests`
--

INSERT INTO `service_requests` (`request_id`, `request_no`, `requesting_emp_id`, `supervisor_emp_id`, `date_requested`, `date_of_travel`, `date_of_travel_end`, `time_departure`, `time_return`, `destination`, `purpose`, `vehicle_id`, `driver_emp_id`, `status`, `date_completed`, `approved_by`, `approved_at`, `remarks`, `created_at`, `updated_at`) VALUES
(191, '001-2025', 26, 54, '2025-10-10', '2025-10-13', '2025-10-15', '08:00:00', '17:00:00', 'nAGA', 'HASFGGFK', 3, 24, 'completed', '2025-10-10 16:31:36', 32, '2025-10-10 16:29:20', NULL, '2025-10-10 08:28:52', '2025-10-10 08:31:36');

-- --------------------------------------------------------

--
-- Table structure for table `service_request_passengers`
--

CREATE TABLE `service_request_passengers` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `approved` tinyint(1) DEFAULT 0,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected` tinyint(1) DEFAULT 0,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_request_passengers`
--

INSERT INTO `service_request_passengers` (`id`, `request_id`, `emp_id`, `approved`, `approved_by`, `approved_at`, `rejected`, `rejected_by`, `rejected_at`) VALUES
(427, 191, 25, 1, 32, '2025-10-10 16:29:17', 0, NULL, NULL),
(428, 191, 27, 1, 32, '2025-10-10 16:29:17', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `movement_type` enum('in','out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `reference` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `item_id`, `movement_type`, `quantity`, `unit_cost`, `reference`, `notes`, `created_at`) VALUES
(24, 15, 'in', 10, 500.00, 'PO-2025-0001', NULL, '2025-09-18 05:42:14'),
(25, 9, 'in', 5, 25.00, 'PO-2025-0001', NULL, '2025-09-18 05:42:14'),
(26, 10, 'in', 5, 40.00, 'PO-2025-0001', NULL, '2025-09-18 05:42:14'),
(27, 21, 'in', 3, 300.00, 'PO-2025-0001', NULL, '2025-09-18 05:42:14'),
(28, 16, 'in', 12, 500.00, 'PO-2025-0001', NULL, '2025-09-18 05:42:14'),
(29, 15, 'out', 2, 0.00, '2025-0001', 'RIS: 2025-0001 - OFFICE SUPPLIES', '2025-09-18 05:43:50'),
(30, 16, 'out', 2, 0.00, '2025-0001', 'RIS: 2025-0001 - OFFICE SUPPLIES', '2025-09-18 05:43:50'),
(44, 9, 'in', 2, 11.00, 'PO-2025-20-0002', NULL, '2025-09-18 08:27:16'),
(45, 21, 'in', 5, 80.00, 'PO-2025-11-0022', NULL, '2025-09-18 08:28:28'),
(46, 11, 'in', 20, 150.00, 'PO-2025-11-0022', NULL, '2025-09-18 08:28:28'),
(47, 12, 'in', 20, 150.00, 'PO-2025-11-0022', NULL, '2025-09-18 08:28:28'),
(48, 13, 'in', 20, 150.00, 'PO-2025-11-0022', NULL, '2025-09-18 08:28:28'),
(49, 14, 'in', 20, 150.00, 'PO-2025-11-0022', NULL, '2025-09-18 08:28:28'),
(50, 11, 'out', 10, 0.00, '2025-0002', 'RIS: 2025-0002 - OFFICE SUPPLIES', '2025-09-23 00:33:32');

-- --------------------------------------------------------

--
-- Table structure for table `supply_requests`
--

CREATE TABLE `supply_requests` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `section` varchar(100) NOT NULL,
  `request_date` date NOT NULL,
  `status` enum('pending','approved','rejected','processed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supply_requests`
--

INSERT INTO `supply_requests` (`id`, `employee_id`, `employee_name`, `section`, `request_date`, `status`, `created_at`, `updated_at`) VALUES
(11, 32, 'Marc David  OROGO', 'Administrative Section', '2025-10-11', 'approved', '2025-10-11 02:20:55', '2025-10-20 07:21:27'),
(12, 32, 'Marc David  OROGO', 'Administrative Section', '2026-01-22', 'pending', '2026-01-22 05:47:00', '2026-01-22 05:47:00'),
(13, 26, 'Ella  RINGAD', 'Manager\'s Office', '2026-03-07', 'pending', '2026-03-07 07:07:09', '2026-03-07 07:07:09');

-- --------------------------------------------------------

--
-- Table structure for table `supply_request_items`
--

CREATE TABLE `supply_request_items` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `supply_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supply_request_items`
--

INSERT INTO `supply_request_items` (`id`, `request_id`, `supply_name`, `description`, `unit`, `quantity`, `status`) VALUES
(30, 11, 'MOUSE', 'USB connection type', 'PCS', 11, 'approved'),
(31, 12, 'FLASH DRIVE', '500GB', 'PCS', 12, 'rejected'),
(32, 12, 'CALCULATOR', 'COMPACT', 'PCS', 2, 'approved'),
(33, 12, 'MOUSE', 'USB connection type', 'PCS', 2, 'approved'),
(34, 13, 'MOUSE', 'USB connection type', 'PCS', 4, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `system_modules`
--

CREATE TABLE `system_modules` (
  `id` int(11) NOT NULL,
  `module_name` varchar(255) NOT NULL,
  `module_description` text DEFAULT NULL,
  `is_under_maintenance` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_modules`
--

INSERT INTO `system_modules` (`id`, `module_name`, `module_description`, `is_under_maintenance`, `created_at`, `updated_at`) VALUES
(1, 'Admin Dashboard', 'Main administrative dashboard and overview', 0, '2025-10-11 14:07:08', '2026-05-28 10:15:02'),
(2, 'Attachment Monitoring', 'Monitor and process document attachments', 0, '2025-10-11 14:07:08', '2026-03-09 11:48:23'),
(3, 'Calendar System', 'Company calendar and event management', 0, '2025-10-11 14:07:08', '2025-10-11 14:09:42'),
(4, 'Employee Management', 'Manage employee records and information', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(5, 'Employee Creation', 'Create new employee profiles', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(6, 'Employee Directory', 'View and manage employee list', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(7, 'Module Maintenance', 'System module maintenance control', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(8, 'Content Management', 'Manage website content and information', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(9, 'Appointment Settings', 'Configure appointment status and types', 0, '2025-10-11 14:07:08', '2026-05-11 11:59:12'),
(10, 'Position Management', 'Manage employee positions and roles', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(11, 'Section Management', 'Manage organizational sections', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(12, 'Office Management', 'Manage office locations and details', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(13, 'Employment Status', 'Configure employment status types', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(14, 'User Management', 'Manage system users and access', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(15, 'Role Management', 'Manage user roles and permissions', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(16, 'Permission Management', 'Configure system permissions', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(17, 'Service Dashboard', 'Vehicle service management dashboard', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(18, 'Service Calendar', 'Service scheduling and calendar', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(19, 'Service Information', 'Vehicle and service details', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(20, 'Operator/Driver Management', 'Manage drivers and operators', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(21, 'Transportation Request', 'Vehicle transportation requests', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(22, 'Inventory Dashboard', 'Inventory management overview', 0, '2025-10-11 14:07:08', '2025-10-12 16:10:09'),
(23, 'Inventory Management', 'View and manage inventory items', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(24, 'Supply Requests', 'Request supplies and materials', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(25, 'My Supply Requests', 'Personal supply request tracking', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(26, 'File Management', 'Document and file management system', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `board_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('backlog','todo','inprogress','review','done') DEFAULT 'backlog',
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `labels` varchar(255) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `position` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`task_id`, `project_id`, `board_id`, `title`, `description`, `status`, `priority`, `labels`, `due_date`, `assigned_to`, `created_by`, `created_at`, `updated_at`, `position`) VALUES
(8, 10, 49, 'ASADA', 'ADASD', '', 'low', 'urgent', '2025-11-08', 32, 32, '2025-11-03 08:52:09', '2026-05-07 08:37:57', 0),
(12, 11, 52, 'NEW TASK', 'COMPLETE T', '', 'high', '', NULL, 32, 32, '2026-05-11 06:31:48', '2026-05-11 09:24:51', 0),
(13, 11, 51, '2', '2', 'backlog', 'medium', '', NULL, NULL, 32, '2026-05-11 08:07:04', '2026-05-11 08:07:04', 0),
(14, 11, 51, '323', '', 'backlog', 'medium', '', NULL, NULL, 32, '2026-05-11 08:07:08', '2026-05-11 08:07:08', 0),
(15, 11, 51, '232', '', '', 'medium', '', NULL, NULL, 32, '2026-05-11 08:07:12', '2026-05-11 09:24:02', 0),
(16, 12, 59, 'survey', 'hjkjhk', '', 'high', '', NULL, 63, 32, '2026-05-18 08:35:42', '2026-06-02 00:10:51', 0),
(17, 12, 58, 'surbey', '+6\n3', '', 'medium', '', '2027-10-18', 65, 32, '2026-05-18 08:36:21', '2026-05-18 08:36:49', 0),
(18, 12, 57, '656+45', '', 'backlog', 'medium', '', '2026-05-01', 26, 32, '2026-05-18 08:38:52', '2026-05-18 08:38:52', 0),
(19, 12, 57, '62+26+', '', 'backlog', 'medium', '', '2026-05-19', 32, 32, '2026-05-18 08:41:51', '2026-05-18 08:41:51', 0),
(20, 13, 65, '321321', '3123', '', 'medium', '', NULL, 143, 26, '2026-06-04 08:54:09', '2026-06-04 09:08:42', 0),
(21, 15, 76, 'BILLING', '6150', '', 'medium', '', NULL, 98, 26, '2026-06-04 09:31:55', '2026-06-04 09:37:36', 0),
(22, 15, 75, 'POW', '645165', '', 'medium', '', NULL, 92, 26, '2026-06-04 09:32:37', '2026-06-04 09:35:47', 0),
(23, 15, 76, 'PLAN', '', '', 'medium', '', NULL, 82, 26, '2026-06-04 09:33:56', '2026-06-04 09:35:42', 0),
(24, 15, 76, 'DEIGN', '', '', 'medium', '', NULL, 84, 26, '2026-06-04 09:34:18', '2026-06-04 09:37:47', 0);

-- --------------------------------------------------------

--
-- Table structure for table `unit_section`
--

CREATE TABLE `unit_section` (
  `unit_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `unit_name` varchar(100) NOT NULL,
  `unit_code` varchar(10) NOT NULL,
  `head_emp_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unit_section`
--

INSERT INTO `unit_section` (`unit_id`, `section_id`, `unit_name`, `unit_code`, `head_emp_id`) VALUES
(46, 1, 'ADMIN UNIT', 'ADU', 28),
(47, 4, 'EQUIPMENT UNIT', 'EQU', 132),
(48, 4, 'INSTITUTIONAL DEVELOPMENT UNIT', 'IDU', 121),
(49, 3, 'CONSTRUCTION UNIT', 'CU', 87),
(50, 2, 'CASHIER UNIT', 'CAU', 52),
(51, 4, 'OPERATION UNIT', 'OU', 104),
(52, 3, 'PLANNING & DESIGN UNIT', 'PDU', 56),
(53, 1, 'PROCUREMENT UNIT', 'PRU', 42),
(55, 2, 'Accounting Unit', 'ACU', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user`, `password`, `employee_id`, `role_id`) VALUES
(10, 'Masteradmin', '$2y$10$VsDY/gKIadpJEmY8Zq9b6eidTovg.nmTV4sGnBjP8NoXlyCJsXB1K', 32, 1),
(12, 'mme', '$2y$10$w/ZLp6PD.K5IB7bm7W2xee5Ixk/FXjvHTJdzZFYNYBqpDI/UndQY6', 28, 14),
(13, '921488', '$2y$10$ZPNbF02R/Xkr6GXdPYnt1eh7/lXBGIPz4HCN3JH9FIvTW6TC5CuLq', 26, 22),
(14, 'mcgs', '$2y$10$N.xkqsb2fKa2c.bZAViS4emlWItrQuVrcVXjvEba68..LAAqU2VGG', 23, 2),
(15, 'jes', '$2y$10$bcyfvOud/Y8vxFG4UCdZautf5ecw7k3OZBCz9lHNk09FZdkAeW8VW', 27, 3),
(16, '677630', '$2y$10$CCpmBRWtEavAU04MCei.S.kc2JMAtjx1EhIDszl5algpZ1Ocj3BgG', 31, 16),
(19, 'coleen', '$2y$10$KD8cxgGAhNOJPyDNR.YUqOZu.GcAI3/Aht0Qll8Kd06ZVH00suKe2', 91, 13),
(21, '164959', '$2y$10$WagDKftu4Jwro8acWUx6cuyrpFdhnc5JzHqFOA4RaVQaBDQRLWkZ.', 54, 12),
(22, 'vp', '$2y$10$Prd/Xw.uDy3lH0QKI749xuzA/GsuISJdhI4MVGne8fYB4SqB6k5KC', 45, 12),
(25, 'july', '$2y$10$jLbt3B2mNtPss2ma45tmuenJwVPN.2lgP0oJCZxwp7uKw8GQms.hi', 55, 22),
(26, 'ifb', '$2y$10$UsO0RxHfq.mTq4QiFSkNiuazImygrsBfg9GN104LeMp3/i9qKIugS', 105, 12),
(27, 'jc', '$2y$10$XtE8z15IyUZJ5lN7EgrEa.WrfNoP26cbBAouqVsXPi35aTiZN4yUC', 56, 14),
(28, 'mhsn', '$2y$10$hQB.xPUxyPBeCn/XpnqpgOg6G2NaP/MfErKa1uP2TTt2zFQ5e4ZFW', 121, 14),
(29, 'rp', '$2y$10$840yIXq3HbkODpXHVlrVzek64B85yxWYfGq3rW9RLNWeRdbJqgYE.', 87, 14),
(30, 'apf', '$2y$10$HZhq89HVE0uOjPIxaz29He8gAD8tP8fRCKIvJWmuh4B6RPugrMtcO', 132, 14),
(31, 'mr', '$2y$10$BbdyemPO62T3IwHrhbSrNeXtX8u6QX122/ueKbL/AbqVt43qzbRiC', 127, 3),
(32, 'chad', '$2y$10$bqvAh/O3JxiycQeoHaNrUui40zBs6rZ3BK1dyz.rNU4ZpV8LvTbTm', 30, 3),
(33, 'joy', '$2y$10$xd9/W.ggi2JQtVAqLBM5/eDCI.TODG5MW7jEFd3vZIc4sT8EIM6pe', 57, 13),
(34, 'crisna', '$2y$10$HjBIW.hU1jcJyzIX1t0.7.O6Pz8pa49nnf/ilwyHXNj6Apj1TXhoC', 100, 3),
(35, 'wa', '$2y$10$FmVWlwdCMKJoqs2KEAqIUeAsWXGjvDjfMUkigGUTnZHpeO4oxmsU2', 59, 20),
(36, 'jev', '$2y$10$dj0sTc8SPXEYlHeJhXcMJem64uBIKhRiWtNHN31n3/AGV6Z9AjPHC', 85, 3),
(37, 'ela', '$2y$10$zxlaIp5RFeKHDRdOQvCJ4ORb0AmjGGJ7kUf2B6Hv3z909tlr4ySv6', 43, 17),
(38, 'charls', '$2y$10$qhSq/vMPOdb5z3VjMckRvu76pCsPr/eBfDo/PCdohp7xI53SqY3FW', 44, 18),
(39, 'pat', '$2y$10$49gwaRmmGXGHLXGpwi/kTO6.pbVZN6/BOMb.j4awMaJCtJHq8ibAi', 64, 1),
(40, 'lou', '$2y$10$u6ADxUVCMcZTaO0PZIu8L.4.ZcypiJo/KCNY7AQWGaG.tjzY.trX2', 42, 3),
(41, 'alvarez', '$2y$10$Cb.ayykjv/GbWnu0j0pId.i8c2ivyTBzUljPiF9bZedrSjQPg9g1W', 47, 3),
(42, 'abc', '$2y$10$Y.ae4UPCAcMNjIRywJROk.NH9sUt/TpdB.bZ3Dl4B5UV7UOfkSH6W', 29, 3);

-- --------------------------------------------------------

--
-- Table structure for table `user_online_status`
--

CREATE TABLE `user_online_status` (
  `id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `last_seen` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_online_status`
--

INSERT INTO `user_online_status` (`id`, `emp_id`, `is_online`, `last_seen`) VALUES
(1, 32, 1, '2026-06-09 09:05:46'),
(4984, 27, 0, '2026-05-29 07:08:45'),
(9161, 31, 0, '2026-02-09 05:51:33'),
(61299, 100, 0, '2026-05-20 05:21:53'),
(61608, 85, 0, '2025-10-13 00:38:22'),
(61969, 23, 0, '2026-05-29 07:08:45'),
(62442, 59, 0, '2026-03-09 06:48:57'),
(62977, 56, 0, '2025-10-20 07:18:14'),
(63127, 54, 0, '2026-06-04 08:52:31'),
(72181, 26, 1, '2026-06-09 09:19:58'),
(79043, 55, 0, '2025-10-28 06:26:31'),
(102042, 28, 0, '2026-03-18 03:41:45'),
(104488, 30, 0, '2026-05-06 09:26:15'),
(104504, 44, 0, '2026-03-09 01:58:52'),
(104858, 132, 0, '2026-03-09 02:19:17'),
(104875, 91, 0, '2026-05-13 03:12:22'),
(115986, 105, 0, '2026-05-07 10:18:31'),
(123205, 47, 0, '2026-05-20 01:02:19'),
(131945, 29, 0, '2026-06-04 00:34:49');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Administrator', 'Full system accesss', '2025-06-03 08:12:39'),
(2, 'Manager', 'Can manage employees and content', '2025-06-03 08:12:39'),
(3, 'Employee', 'Regular employee access', '2025-06-03 08:12:39'),
(12, 'Heads', 'For checking status', '2025-07-11 02:15:46'),
(13, 'Focal Person', 'Focal Person for each unit', '2025-07-11 06:18:47'),
(14, 'Unit Head', 'Reports', '2025-07-17 07:53:07'),
(16, 'Focal Person (DTR ATTACHMENTS)', 'In-charge of DTR ATTACHMENTS', '2025-10-08 06:20:18'),
(17, 'Focal Person (Procurement)', 'In charge of Procurement Procedure', '2025-10-09 05:39:19'),
(18, 'Focal Person (Service)', 'In-charge of Service available and Service Request', '2025-10-12 05:16:50'),
(20, 'Focal Person (ICT)', 'In-charge of ICT Equipments', '2025-10-17 06:44:50'),
(22, 'Focal Person (Document Monitoring)', 'In charge of the outgoing, incoming and internal communication documents', '2025-10-22 06:21:09');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `property_no` varchar(50) NOT NULL,
  `plate_no` varchar(20) NOT NULL,
  `vehicle_type` varchar(50) NOT NULL,
  `model` varchar(50) DEFAULT NULL,
  `year` int(4) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `status` enum('available','maintenance','unavailable') DEFAULT 'available',
  `office_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`vehicle_id`, `property_no`, `plate_no`, `vehicle_type`, `model`, `year`, `capacity`, `status`, `office_id`, `created_at`, `updated_at`) VALUES
(2, 'a', 'EAS-334', 'SERVICE CAR', 'FORD', 2020, 6, 'available', 1, '2025-08-19 02:13:34', '2025-08-19 02:13:34'),
(3, 'N/A', 'EAS-335', 'SERVICE CAR', 'STRADA', 2023, 5, 'available', 1, '2025-08-21 05:42:16', '2025-08-21 05:42:16'),
(5, 'N/A1', 'EAS-336', 'SERVICE CAR', 'FORD WHITE LEGEND', 2000, 10, 'available', 1, '2025-08-21 05:43:10', '2025-08-21 05:43:10'),
(6, 'asdas', '22', 'SERVICE CAR', 'ESTRADA', 2024, 6, 'maintenance', 1, '2025-10-20 07:54:01', '2025-10-20 07:54:01');

-- --------------------------------------------------------

--
-- Table structure for table `visitor_queue`
--

CREATE TABLE `visitor_queue` (
  `id` int(11) NOT NULL,
  `queue_number` varchar(20) NOT NULL,
  `priority_number` varchar(10) DEFAULT NULL,
  `visitor_name` varchar(100) NOT NULL,
  `company` varchar(100) DEFAULT NULL,
  `purpose` varchar(50) NOT NULL,
  `person_to_visit` int(11) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `time_in` datetime DEFAULT current_timestamp(),
  `time_called` datetime DEFAULT NULL,
  `time_completed` datetime DEFAULT NULL,
  `status` enum('waiting','called','serving','completed','cancelled') DEFAULT 'waiting',
  `counter_number` int(11) DEFAULT NULL,
  `qr_code_data` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `section_name` varchar(100) DEFAULT NULL,
  `unit_name` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `time_served` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `is_manager_office` tinyint(1) DEFAULT 0,
  `is_priority` tinyint(1) DEFAULT 0,
  `call_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_rooms_currently_occupied`
-- (See below for the actual view)
--
CREATE TABLE `v_rooms_currently_occupied` (
`room_id` int(10) unsigned
,`room_name` varchar(120)
,`purpose` varchar(255)
,`start_time` time
,`end_time` time
,`reserved_by` varchar(101)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_system_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_system_summary` (
`id` int(10) unsigned
,`system_code` varchar(30)
,`system_name` varchar(150)
,`system_type` varchar(80)
,`status` varchar(60)
,`status_color` varchar(20)
,`province` varchar(80)
,`potential_service_area_ha` decimal(10,2)
,`actual_service_area_ha` decimal(10,2)
,`utilization_pct` decimal(15,1)
,`total_assets` bigint(21)
,`open_work_orders` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_todays_room_schedule`
-- (See below for the actual view)
--
CREATE TABLE `v_todays_room_schedule` (
`room_name` varchar(120)
,`capacity` smallint(5) unsigned
,`reservation_date` date
,`start_time` time
,`end_time` time
,`purpose` varchar(255)
,`status` enum('pending','approved','rejected','cancelled')
,`reserved_by` varchar(101)
,`attendees` smallint(5) unsigned
);

-- --------------------------------------------------------

--
-- Structure for view `v_rooms_currently_occupied`
--
DROP TABLE IF EXISTS `v_rooms_currently_occupied`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_rooms_currently_occupied`  AS SELECT `r`.`room_id` AS `room_id`, `r`.`room_name` AS `room_name`, `rr`.`purpose` AS `purpose`, `rr`.`start_time` AS `start_time`, `rr`.`end_time` AS `end_time`, concat(`e`.`first_name`,' ',`e`.`last_name`) AS `reserved_by` FROM ((`room_reservations` `rr` join `rooms` `r` on(`r`.`room_id` = `rr`.`room_id`)) join `employee` `e` on(`e`.`emp_id` = `rr`.`emp_id`)) WHERE `rr`.`reservation_date` = curdate() AND `rr`.`status` = 'approved' AND curtime() between `rr`.`start_time` and `rr`.`end_time` ;

-- --------------------------------------------------------

--
-- Structure for view `v_system_summary`
--
DROP TABLE IF EXISTS `v_system_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_system_summary`  AS SELECT `s`.`id` AS `id`, `s`.`system_code` AS `system_code`, `s`.`system_name` AS `system_name`, `st`.`name` AS `system_type`, `ss`.`label` AS `status`, `ss`.`color` AS `status_color`, `s`.`province` AS `province`, `s`.`potential_service_area_ha` AS `potential_service_area_ha`, `s`.`actual_service_area_ha` AS `actual_service_area_ha`, round(`s`.`actual_service_area_ha` / nullif(`s`.`potential_service_area_ha`,0) * 100,1) AS `utilization_pct`, (select count(0) from `ism_infrastructure_assets` `a` where `a`.`system_id` = `s`.`id` and `a`.`deleted_at` is null) AS `total_assets`, (select count(0) from `ism_work_orders` `w` where `w`.`system_id` = `s`.`id` and !(`w`.`status_id` in (select `ism_work_order_statuses`.`id` from `ism_work_order_statuses` where `ism_work_order_statuses`.`code` in ('completed','cancelled')))) AS `open_work_orders` FROM ((`ism_irrigation_systems` `s` join `ism_system_types` `st` on(`st`.`id` = `s`.`system_type_id`)) join `ism_system_statuses` `ss` on(`ss`.`id` = `s`.`status_id`)) WHERE `s`.`deleted_at` is null ;

-- --------------------------------------------------------

--
-- Structure for view `v_todays_room_schedule`
--
DROP TABLE IF EXISTS `v_todays_room_schedule`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_todays_room_schedule`  AS SELECT `r`.`room_name` AS `room_name`, `r`.`capacity` AS `capacity`, `rr`.`reservation_date` AS `reservation_date`, `rr`.`start_time` AS `start_time`, `rr`.`end_time` AS `end_time`, `rr`.`purpose` AS `purpose`, `rr`.`status` AS `status`, concat(`e`.`first_name`,' ',`e`.`last_name`) AS `reserved_by`, `rr`.`attendees` AS `attendees` FROM ((`room_reservations` `rr` join `rooms` `r` on(`r`.`room_id` = `rr`.`room_id`)) join `employee` `e` on(`e`.`emp_id` = `rr`.`emp_id`)) WHERE `rr`.`reservation_date` = curdate() AND `rr`.`status` = 'approved' ORDER BY `rr`.`start_time` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_emp_id` (`admin_emp_id`);

--
-- Indexes for table `applicant`
--
ALTER TABLE `applicant`
  ADD PRIMARY KEY (`applicant_id`);

--
-- Indexes for table `appointment_status`
--
ALTER TABLE `appointment_status`
  ADD PRIMARY KEY (`appointment_id`);

--
-- Indexes for table `attachments_monitoring`
--
ALTER TABLE `attachments_monitoring`
  ADD PRIMARY KEY (`monitoring_id`),
  ADD UNIQUE KEY `unique_employee_period` (`emp_id`,`payroll_period`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `carousel_images`
--
ALTER TABLE `carousel_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_chat_messages_deleted` (`is_deleted`);

--
-- Indexes for table `chat_message_reactions`
--
ALTER TABLE `chat_message_reactions`
  ADD PRIMARY KEY (`reaction_id`),
  ADD UNIQUE KEY `unique_reaction` (`message_id`,`emp_id`,`emoji`),
  ADD KEY `fk_reaction_employee` (`emp_id`);

--
-- Indexes for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `chat_room_participants`
--
ALTER TABLE `chat_room_participants`
  ADD PRIMARY KEY (`participant_id`),
  ADD UNIQUE KEY `unique_participant` (`room_id`,`emp_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `unique_like` (`comment_id`,`emp_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `company_forms`
--
ALTER TABLE `company_forms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `company_info`
--
ALTER TABLE `company_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `congressional_districts`
--
ALTER TABLE `congressional_districts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `province_code` (`province_code`);

--
-- Indexes for table `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_id` (`delivery_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `document_archive`
--
ALTER TABLE `document_archive`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_archive_date` (`archive_date`),
  ADD KEY `idx_original_id` (`original_id`),
  ADD KEY `idx_kind` (`kind`);

--
-- Indexes for table `document_archive_log`
--
ALTER TABLE `document_archive_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `run_date` (`run_date`);

--
-- Indexes for table `document_delete_requests`
--
ALTER TABLE `document_delete_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ddr_doc` (`document_id`),
  ADD KEY `idx_ddr_req` (`requested_by`),
  ADD KEY `idx_ddr_status` (`status`);

--
-- Indexes for table `document_files`
--
ALTER TABLE `document_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_id` (`document_id`);

--
-- Indexes for table `document_forwards`
--
ALTER TABLE `document_forwards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `fk_fwd_received_by` (`received_by_emp_id`);

--
-- Indexes for table `document_notifications`
--
ALTER TABLE `document_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dn_recipient` (`recipient_emp_id`,`is_read`);

--
-- Indexes for table `document_records`
--
ALTER TABLE `document_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_doc_type` (`document_type_id`),
  ADD KEY `fk_from_section` (`from_section_id`),
  ADD KEY `fk_to_section` (`forwarded_to_section_id`),
  ADD KEY `fk_forwarded_by` (`forwarded_by_emp_id`),
  ADD KEY `fk_forwarded_to_emp` (`forwarded_to_emp_id`),
  ADD KEY `fk_doc_received_by` (`received_by_emp_id`);

--
-- Indexes for table `document_sections`
--
ALTER TABLE `document_sections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee`
--
ALTER TABLE `employee`
  ADD PRIMARY KEY (`emp_id`),
  ADD KEY `employment_status_id` (`employment_status_id`),
  ADD KEY `appointment_status_id` (`appointment_status_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `office_id` (`office_id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `employee_ibfk_6` (`unit_section_id`);

--
-- Indexes for table `employee_unit_sections`
--
ALTER TABLE `employee_unit_sections`
  ADD PRIMARY KEY (`emp_id`,`unit_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `employment_status`
--
ALTER TABLE `employment_status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `folder_id` (`folder_id`);

--
-- Indexes for table `file_activity_logs`
--
ALTER TABLE `file_activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`folder_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `parent_folder_id` (`parent_folder_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `folder_access`
--
ALTER TABLE `folder_access`
  ADD PRIMARY KEY (`access_id`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `folder_activity_logs`
--
ALTER TABLE `folder_activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `folder_shares`
--
ALTER TABLE `folder_shares`
  ADD PRIMARY KEY (`share_id`),
  ADD KEY `folder_id` (`folder_id`),
  ADD KEY `shared_by_emp_id` (`shared_by_emp_id`),
  ADD KEY `shared_with_emp_id` (`shared_with_emp_id`);

--
-- Indexes for table `iar_items`
--
ALTER TABLE `iar_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `iar_id` (`iar_id`),
  ADD KEY `delivery_item_id` (`delivery_item_id`);

--
-- Indexes for table `iar_records`
--
ALTER TABLE `iar_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `iar_number` (`iar_number`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `ia_officers`
--
ALTER TABLE `ia_officers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ia_profile_id` (`ia_profile_id`);

--
-- Indexes for table `ia_profiles`
--
ALTER TABLE `ia_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ia_code` (`ia_code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_ia_profiles_assigned_unit` (`assigned_employee_id`),
  ADD KEY `fk_ia_profiles_updated_by` (`updated_by`);

--
-- Indexes for table `ia_profile_history`
--
ALTER TABLE `ia_profile_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ia_profile_id` (`ia_profile_id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `action` (`action`),
  ADD KEY `performed_at` (`performed_at`);

--
-- Indexes for table `ict_equipment`
--
ALTER TABLE `ict_equipment`
  ADD PRIMARY KEY (`equipment_id`),
  ADD UNIQUE KEY `asset_tag` (`asset_tag`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `ict_equipment_categories`
--
ALTER TABLE `ict_equipment_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `ict_equipment_logs`
--
ALTER TABLE `ict_equipment_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `ict_maintenance`
--
ALTER TABLE `ict_maintenance`
  ADD PRIMARY KEY (`maintenance_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `reported_by` (`reported_by`),
  ADD KEY `assigned_technician` (`assigned_technician`);

--
-- Indexes for table `ict_maintenance_logs`
--
ALTER TABLE `ict_maintenance_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `ict_maintenance_notes`
--
ALTER TABLE `ict_maintenance_notes`
  ADD PRIMARY KEY (`note_id`),
  ADD KEY `maintenance_id` (`maintenance_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `intern`
--
ALTER TABLE `intern`
  ADD PRIMARY KEY (`intern_id`);

--
-- Indexes for table `ism_asset_types`
--
ALTER TABLE `ism_asset_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `ism_condition_ratings`
--
ALTER TABLE `ism_condition_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `ism_crop_types`
--
ALTER TABLE `ism_crop_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `ism_infrastructure_assets`
--
ALTER TABLE `ism_infrastructure_assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_code` (`asset_code`),
  ADD KEY `fk_asset_condition` (`current_condition_id`),
  ADD KEY `idx_asset_system` (`system_id`),
  ADD KEY `idx_asset_type` (`asset_type_id`);

--
-- Indexes for table `ism_inspections`
--
ALTER TABLE `ism_inspections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_insp_system` (`system_id`),
  ADD KEY `fk_insp_condition` (`condition_id`),
  ADD KEY `idx_insp_asset` (`asset_id`),
  ADD KEY `idx_insp_date` (`inspection_date`);

--
-- Indexes for table `ism_inspection_photos`
--
ALTER TABLE `ism_inspection_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_photo_insp` (`inspection_id`);

--
-- Indexes for table `ism_irrigation_systems`
--
ALTER TABLE `ism_irrigation_systems`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `system_code` (`system_code`),
  ADD KEY `fk_isms_type` (`system_type_id`),
  ADD KEY `fk_isms_status` (`status_id`);

--
-- Indexes for table `ism_production_entries`
--
ALTER TABLE `ism_production_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prod` (`system_id`,`season_id`,`crop_type_id`),
  ADD KEY `fk_prod_season` (`season_id`),
  ADD KEY `fk_prod_crop` (`crop_type_id`),
  ADD KEY `idx_prod_system_season` (`system_id`,`season_id`);

--
-- Indexes for table `ism_production_report_exports`
--
ALTER TABLE `ism_production_report_exports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rpt_season` (`season_id`);

--
-- Indexes for table `ism_production_targets`
--
ALTER TABLE `ism_production_targets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_target` (`season_id`,`system_id`),
  ADD KEY `fk_pt_system` (`system_id`);

--
-- Indexes for table `ism_seasons`
--
ALTER TABLE `ism_seasons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_season_year` (`year`);

--
-- Indexes for table `ism_submission_status`
--
ALTER TABLE `ism_submission_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sub_status` (`system_id`,`season_id`),
  ADD KEY `fk_ss_season` (`season_id`);

--
-- Indexes for table `ism_system_attachments`
--
ALTER TABLE `ism_system_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_att_system` (`system_id`);

--
-- Indexes for table `ism_system_audit_log`
--
ALTER TABLE `ism_system_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_system` (`system_id`),
  ADD KEY `idx_audit_date` (`changed_at`);

--
-- Indexes for table `ism_system_coverage`
--
ALTER TABLE `ism_system_coverage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cov_system` (`system_id`);

--
-- Indexes for table `ism_system_statuses`
--
ALTER TABLE `ism_system_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `ism_system_types`
--
ALTER TABLE `ism_system_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `ism_water_allocations`
--
ALTER TABLE `ism_water_allocations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_alloc` (`system_id`,`season_id`),
  ADD KEY `idx_alloc_system` (`system_id`),
  ADD KEY `idx_alloc_season` (`season_id`);

--
-- Indexes for table `ism_water_delivery_issues`
--
ALTER TABLE `ism_water_delivery_issues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wdi_log` (`delivery_log_id`);

--
-- Indexes for table `ism_water_delivery_logs`
--
ALTER TABLE `ism_water_delivery_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_wdl_season` (`season_id`),
  ADD KEY `idx_wdl_system_season` (`system_id`,`season_id`),
  ADD KEY `idx_wdl_date` (`delivery_date`);

--
-- Indexes for table `ism_work_orders`
--
ALTER TABLE `ism_work_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wo_number` (`wo_number`),
  ADD KEY `idx_wo_asset` (`asset_id`),
  ADD KEY `idx_wo_system` (`system_id`),
  ADD KEY `idx_wo_status` (`status_id`);

--
-- Indexes for table `ism_work_order_materials`
--
ALTER TABLE `ism_work_order_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wom_wo` (`work_order_id`);

--
-- Indexes for table `ism_work_order_statuses`
--
ALTER TABLE `ism_work_order_statuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `ism_work_order_status_history`
--
ALTER TABLE `ism_work_order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wosh_wo` (`work_order_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `leave_balance`
--
ALTER TABLE `leave_balance`
  ADD PRIMARY KEY (`balance_id`),
  ADD UNIQUE KEY `uq_emp_type_year` (`emp_id`,`leave_type_id`,`year`),
  ADD KEY `idx_lb_emp` (`emp_id`),
  ADD KEY `idx_lb_leave_type` (`leave_type_id`);

--
-- Indexes for table `leave_balance_log`
--
ALTER TABLE `leave_balance_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_log_emp` (`emp_id`),
  ADD KEY `fk_log_by` (`performed_by`),
  ADD KEY `fk_log_leave_type` (`leave_type_id`);

--
-- Indexes for table `leave_request`
--
ALTER TABLE `leave_request`
  ADD PRIMARY KEY (`leave_request_id`),
  ADD KEY `idx_emp_id` (`emp_id`),
  ADD KEY `idx_leave_type` (`leave_type_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date_from` (`date_from`),
  ADD KEY `idx_approved_by` (`approved_by`);

--
-- Indexes for table `leave_type`
--
ALTER TABLE `leave_type`
  ADD PRIMARY KEY (`leave_type_id`);

--
-- Indexes for table `managers_office_staff`
--
ALTER TABLE `managers_office_staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD PRIMARY KEY (`reaction_id`),
  ADD UNIQUE KEY `unique_reaction` (`message_id`,`emp_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_emp_id` (`emp_id`),
  ADD KEY `idx_notifications_is_read` (`is_read`),
  ADD KEY `idx_notifications_created_at` (`created_at`);

--
-- Indexes for table `office`
--
ALTER TABLE `office`
  ADD PRIMARY KEY (`office_id`),
  ADD KEY `fk_office_manager` (`manager_emp_id`),
  ADD KEY `fk_parent_office` (`parent_office_id`);

--
-- Indexes for table `others_leave_type`
--
ALTER TABLE `others_leave_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `idx_token` (`reset_token`),
  ADD KEY `idx_expiry` (`token_expiry`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `personal_locator_slips`
--
ALTER TABLE `personal_locator_slips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `position`
--
ALTER TABLE `position`
  ADD PRIMARY KEY (`position_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`project_id`),
  ADD UNIQUE KEY `project_code` (`project_code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `project_boards`
--
ALTER TABLE `project_boards`
  ADD PRIMARY KEY (`board_id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `project_members`
--
ALTER TABLE `project_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member` (`project_id`,`emp_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `provinces`
--
ALTER TABLE `provinces`
  ADD PRIMARY KEY (`id`),
  ADD KEY `region_code` (`region_code`);

--
-- Indexes for table `queue_history`
--
ALTER TABLE `queue_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_queue_id` (`queue_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_performed_at` (`performed_at`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `queue_settings`
--
ALTER TABLE `queue_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `regions`
--
ALTER TABLE `regions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ris_items`
--
ALTER TABLE `ris_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ris_id` (`ris_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `ris_records`
--
ALTER TABLE `ris_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ris_number` (`ris_number`),
  ADD KEY `iar_id` (`iar_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD KEY `idx_rooms_status` (`status`);

--
-- Indexes for table `room_reservations`
--
ALTER TABLE `room_reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `idx_rr_room_date` (`room_id`,`reservation_date`),
  ADD KEY `idx_rr_emp` (`emp_id`),
  ADD KEY `idx_rr_status` (`status`),
  ADD KEY `idx_rr_date_status` (`reservation_date`,`status`);

--
-- Indexes for table `section`
--
ALTER TABLE `section`
  ADD PRIMARY KEY (`section_id`),
  ADD KEY `head_emp_id` (`head_emp_id`),
  ADD KEY `fk_section_office` (`office_id`);

--
-- Indexes for table `section_secretaries`
--
ALTER TABLE `section_secretaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `section_id` (`section_id`,`emp_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD UNIQUE KEY `request_no` (`request_no`),
  ADD KEY `requesting_emp_id` (`requesting_emp_id`),
  ADD KEY `supervisor_emp_id` (`supervisor_emp_id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `driver_emp_id` (`driver_emp_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `service_request_passengers`
--
ALTER TABLE `service_request_passengers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_id` (`request_id`,`emp_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `fk_passenger_approved_by` (`approved_by`),
  ADD KEY `fk_passenger_rejected_by` (`rejected_by`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `supply_requests`
--
ALTER TABLE `supply_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `supply_request_items`
--
ALTER TABLE `supply_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `system_modules`
--
ALTER TABLE `system_modules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `board_id` (`board_id`);

--
-- Indexes for table `unit_section`
--
ALTER TABLE `unit_section`
  ADD PRIMARY KEY (`unit_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `head_emp_id` (`head_emp_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `fk_user_employee` (`employee_id`);

--
-- Indexes for table `user_online_status`
--
ALTER TABLE `user_online_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `emp_id` (`emp_id`),
  ADD KEY `is_online` (`is_online`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `property_no` (`property_no`),
  ADD UNIQUE KEY `plate_no` (`plate_no`),
  ADD KEY `office_id` (`office_id`);

--
-- Indexes for table `visitor_queue`
--
ALTER TABLE `visitor_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_visitor_queue_employee` (`person_to_visit`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=202;

--
-- AUTO_INCREMENT for table `applicant`
--
ALTER TABLE `applicant`
  MODIFY `applicant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointment_status`
--
ALTER TABLE `appointment_status`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `attachments_monitoring`
--
ALTER TABLE `attachments_monitoring`
  MODIFY `monitoring_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `carousel_images`
--
ALTER TABLE `carousel_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=180;

--
-- AUTO_INCREMENT for table `chat_message_reactions`
--
ALTER TABLE `chat_message_reactions`
  MODIFY `reaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `chat_room_participants`
--
ALTER TABLE `chat_room_participants`
  MODIFY `participant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `comment_likes`
--
ALTER TABLE `comment_likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `company_forms`
--
ALTER TABLE `company_forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `company_info`
--
ALTER TABLE `company_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `congressional_districts`
--
ALTER TABLE `congressional_districts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `delivery_items`
--
ALTER TABLE `delivery_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `document_archive`
--
ALTER TABLE `document_archive`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT for table `document_archive_log`
--
ALTER TABLE `document_archive_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `document_delete_requests`
--
ALTER TABLE `document_delete_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `document_files`
--
ALTER TABLE `document_files`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `document_forwards`
--
ALTER TABLE `document_forwards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `document_notifications`
--
ALTER TABLE `document_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=163;

--
-- AUTO_INCREMENT for table `document_records`
--
ALTER TABLE `document_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=330;

--
-- AUTO_INCREMENT for table `document_sections`
--
ALTER TABLE `document_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `employee`
--
ALTER TABLE `employee`
  MODIFY `emp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=306;

--
-- AUTO_INCREMENT for table `employment_status`
--
ALTER TABLE `employment_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `file_activity_logs`
--
ALTER TABLE `file_activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `folders`
--
ALTER TABLE `folders`
  MODIFY `folder_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `folder_access`
--
ALTER TABLE `folder_access`
  MODIFY `access_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `folder_activity_logs`
--
ALTER TABLE `folder_activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT for table `folder_shares`
--
ALTER TABLE `folder_shares`
  MODIFY `share_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `iar_items`
--
ALTER TABLE `iar_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `iar_records`
--
ALTER TABLE `iar_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `ia_officers`
--
ALTER TABLE `ia_officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ia_profiles`
--
ALTER TABLE `ia_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `ia_profile_history`
--
ALTER TABLE `ia_profile_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `ict_equipment`
--
ALTER TABLE `ict_equipment`
  MODIFY `equipment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `ict_equipment_categories`
--
ALTER TABLE `ict_equipment_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `ict_equipment_logs`
--
ALTER TABLE `ict_equipment_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `ict_maintenance`
--
ALTER TABLE `ict_maintenance`
  MODIFY `maintenance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ict_maintenance_logs`
--
ALTER TABLE `ict_maintenance_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ict_maintenance_notes`
--
ALTER TABLE `ict_maintenance_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `intern`
--
ALTER TABLE `intern`
  MODIFY `intern_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `ism_asset_types`
--
ALTER TABLE `ism_asset_types`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `ism_condition_ratings`
--
ALTER TABLE `ism_condition_ratings`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ism_crop_types`
--
ALTER TABLE `ism_crop_types`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `ism_infrastructure_assets`
--
ALTER TABLE `ism_infrastructure_assets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_inspections`
--
ALTER TABLE `ism_inspections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_inspection_photos`
--
ALTER TABLE `ism_inspection_photos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_irrigation_systems`
--
ALTER TABLE `ism_irrigation_systems`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_production_entries`
--
ALTER TABLE `ism_production_entries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_production_report_exports`
--
ALTER TABLE `ism_production_report_exports`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_production_targets`
--
ALTER TABLE `ism_production_targets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_seasons`
--
ALTER TABLE `ism_seasons`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_submission_status`
--
ALTER TABLE `ism_submission_status`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_system_attachments`
--
ALTER TABLE `ism_system_attachments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_system_audit_log`
--
ALTER TABLE `ism_system_audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_system_coverage`
--
ALTER TABLE `ism_system_coverage`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_system_statuses`
--
ALTER TABLE `ism_system_statuses`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ism_system_types`
--
ALTER TABLE `ism_system_types`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ism_water_allocations`
--
ALTER TABLE `ism_water_allocations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_water_delivery_issues`
--
ALTER TABLE `ism_water_delivery_issues`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_water_delivery_logs`
--
ALTER TABLE `ism_water_delivery_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_work_orders`
--
ALTER TABLE `ism_work_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_work_order_materials`
--
ALTER TABLE `ism_work_order_materials`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ism_work_order_statuses`
--
ALTER TABLE `ism_work_order_statuses`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ism_work_order_status_history`
--
ALTER TABLE `ism_work_order_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `leave_balance`
--
ALTER TABLE `leave_balance`
  MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5568;

--
-- AUTO_INCREMENT for table `leave_balance_log`
--
ALTER TABLE `leave_balance_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `leave_request`
--
ALTER TABLE `leave_request`
  MODIFY `leave_request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `leave_type`
--
ALTER TABLE `leave_type`
  MODIFY `leave_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `managers_office_staff`
--
ALTER TABLE `managers_office_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `reaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `office`
--
ALTER TABLE `office`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `others_leave_type`
--
ALTER TABLE `others_leave_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `personal_locator_slips`
--
ALTER TABLE `personal_locator_slips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `position`
--
ALTER TABLE `position`
  MODIFY `position_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `project_boards`
--
ALTER TABLE `project_boards`
  MODIFY `board_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `project_members`
--
ALTER TABLE `project_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `provinces`
--
ALTER TABLE `provinces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `queue_history`
--
ALTER TABLE `queue_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `queue_settings`
--
ALTER TABLE `queue_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `regions`
--
ALTER TABLE `regions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ris_items`
--
ALTER TABLE `ris_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `ris_records`
--
ALTER TABLE `ris_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `room_reservations`
--
ALTER TABLE `room_reservations`
  MODIFY `reservation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `section`
--
ALTER TABLE `section`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `section_secretaries`
--
ALTER TABLE `section_secretaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=192;

--
-- AUTO_INCREMENT for table `service_request_passengers`
--
ALTER TABLE `service_request_passengers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=429;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `supply_requests`
--
ALTER TABLE `supply_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `supply_request_items`
--
ALTER TABLE `supply_request_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `system_modules`
--
ALTER TABLE `system_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `unit_section`
--
ALTER TABLE `unit_section`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `user_online_status`
--
ALTER TABLE `user_online_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134073;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `visitor_queue`
--
ALTER TABLE `visitor_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD CONSTRAINT `admin_notifications_ibfk_1` FOREIGN KEY (`admin_emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `attachments_monitoring`
--
ALTER TABLE `attachments_monitoring`
  ADD CONSTRAINT `attachments_monitoring_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_message_reactions`
--
ALTER TABLE `chat_message_reactions`
  ADD CONSTRAINT `fk_reaction_employee` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reaction_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`message_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  ADD CONSTRAINT `chat_rooms_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `chat_room_participants`
--
ALTER TABLE `chat_room_participants`
  ADD CONSTRAINT `chat_room_participants_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_room_participants_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `document_comments` (`comment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comment_likes_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD CONSTRAINT `delivery_items_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `stock_movements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_files`
--
ALTER TABLE `document_files`
  ADD CONSTRAINT `fk_docfiles_doc` FOREIGN KEY (`document_id`) REFERENCES `document_records` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `document_forwards`
--
ALTER TABLE `document_forwards`
  ADD CONSTRAINT `fk_fwd_document` FOREIGN KEY (`document_id`) REFERENCES `document_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fwd_received_by` FOREIGN KEY (`received_by_emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `document_records`
--
ALTER TABLE `document_records`
  ADD CONSTRAINT `fk_doc_received_by` FOREIGN KEY (`received_by_emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_doc_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`),
  ADD CONSTRAINT `fk_forwarded_by_emp` FOREIGN KEY (`forwarded_by_emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_forwarded_to_emp` FOREIGN KEY (`forwarded_to_emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_from_section` FOREIGN KEY (`from_section_id`) REFERENCES `section` (`section_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_to_section` FOREIGN KEY (`forwarded_to_section_id`) REFERENCES `section` (`section_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `employee`
--
ALTER TABLE `employee`
  ADD CONSTRAINT `employee_ibfk_1` FOREIGN KEY (`employment_status_id`) REFERENCES `employment_status` (`status_id`),
  ADD CONSTRAINT `employee_ibfk_2` FOREIGN KEY (`appointment_status_id`) REFERENCES `appointment_status` (`appointment_id`),
  ADD CONSTRAINT `employee_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`),
  ADD CONSTRAINT `employee_ibfk_4` FOREIGN KEY (`office_id`) REFERENCES `office` (`office_id`),
  ADD CONSTRAINT `employee_ibfk_5` FOREIGN KEY (`position_id`) REFERENCES `position` (`position_id`),
  ADD CONSTRAINT `employee_ibfk_6` FOREIGN KEY (`unit_section_id`) REFERENCES `unit_section` (`unit_id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_unit_sections`
--
ALTER TABLE `employee_unit_sections`
  ADD CONSTRAINT `employee_unit_sections_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_unit_sections_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `unit_section` (`unit_id`) ON DELETE CASCADE;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`),
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `files_ibfk_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`folder_id`) ON DELETE SET NULL;

--
-- Constraints for table `file_activity_logs`
--
ALTER TABLE `file_activity_logs`
  ADD CONSTRAINT `file_activity_logs_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `file_activity_logs_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE SET NULL;

--
-- Constraints for table `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folders_ibfk_2` FOREIGN KEY (`parent_folder_id`) REFERENCES `folders` (`folder_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folders_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `folder_access`
--
ALTER TABLE `folder_access`
  ADD CONSTRAINT `folder_access_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`folder_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folder_access_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `folder_activity_logs`
--
ALTER TABLE `folder_activity_logs`
  ADD CONSTRAINT `folder_activity_logs_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`folder_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folder_activity_logs_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `iar_items`
--
ALTER TABLE `iar_items`
  ADD CONSTRAINT `iar_items_ibfk_1` FOREIGN KEY (`iar_id`) REFERENCES `iar_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `iar_items_ibfk_2` FOREIGN KEY (`delivery_item_id`) REFERENCES `delivery_items` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `iar_records`
--
ALTER TABLE `iar_records`
  ADD CONSTRAINT `iar_records_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ia_officers`
--
ALTER TABLE `ia_officers`
  ADD CONSTRAINT `ia_officers_ibfk_1` FOREIGN KEY (`ia_profile_id`) REFERENCES `ia_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ia_profiles`
--
ALTER TABLE `ia_profiles`
  ADD CONSTRAINT `fk_ia_profiles_assigned_employee` FOREIGN KEY (`assigned_employee_id`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `fk_ia_profiles_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `ia_profiles_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `ia_profile_history`
--
ALTER TABLE `ia_profile_history`
  ADD CONSTRAINT `ia_profile_history_ibfk_1` FOREIGN KEY (`ia_profile_id`) REFERENCES `ia_profiles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ia_profile_history_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `ict_equipment`
--
ALTER TABLE `ict_equipment`
  ADD CONSTRAINT `ict_equipment_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `ict_equipment_categories` (`category_id`),
  ADD CONSTRAINT `ict_equipment_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `ict_equipment_logs`
--
ALTER TABLE `ict_equipment_logs`
  ADD CONSTRAINT `ict_equipment_logs_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `ict_equipment` (`equipment_id`),
  ADD CONSTRAINT `ict_equipment_logs_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `ict_maintenance`
--
ALTER TABLE `ict_maintenance`
  ADD CONSTRAINT `ict_maintenance_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `ict_equipment` (`equipment_id`),
  ADD CONSTRAINT `ict_maintenance_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `ict_maintenance_ibfk_3` FOREIGN KEY (`assigned_technician`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `ict_maintenance_logs`
--
ALTER TABLE `ict_maintenance_logs`
  ADD CONSTRAINT `ict_maintenance_logs_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `ict_equipment` (`equipment_id`),
  ADD CONSTRAINT `ict_maintenance_logs_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `ism_infrastructure_assets`
--
ALTER TABLE `ism_infrastructure_assets`
  ADD CONSTRAINT `fk_asset_condition` FOREIGN KEY (`current_condition_id`) REFERENCES `ism_condition_ratings` (`id`),
  ADD CONSTRAINT `fk_asset_system` FOREIGN KEY (`system_id`) REFERENCES `ism_irrigation_systems` (`id`),
  ADD CONSTRAINT `fk_asset_type` FOREIGN KEY (`asset_type_id`) REFERENCES `ism_asset_types` (`id`);

--
-- Constraints for table `ism_inspections`
--
ALTER TABLE `ism_inspections`
  ADD CONSTRAINT `fk_insp_asset` FOREIGN KEY (`asset_id`) REFERENCES `ism_infrastructure_assets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_insp_condition` FOREIGN KEY (`condition_id`) REFERENCES `ism_condition_ratings` (`id`),
  ADD CONSTRAINT `fk_insp_system` FOREIGN KEY (`system_id`) REFERENCES `ism_irrigation_systems` (`id`);

--
-- Constraints for table `ism_inspection_photos`
--
ALTER TABLE `ism_inspection_photos`
  ADD CONSTRAINT `fk_photo_insp` FOREIGN KEY (`inspection_id`) REFERENCES `ism_inspections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ism_irrigation_systems`
--
ALTER TABLE `ism_irrigation_systems`
  ADD CONSTRAINT `fk_isms_status` FOREIGN KEY (`status_id`) REFERENCES `ism_system_statuses` (`id`),
  ADD CONSTRAINT `fk_isms_type` FOREIGN KEY (`system_type_id`) REFERENCES `ism_system_types` (`id`);

--
-- Constraints for table `ism_production_entries`
--
ALTER TABLE `ism_production_entries`
  ADD CONSTRAINT `fk_prod_crop` FOREIGN KEY (`crop_type_id`) REFERENCES `ism_crop_types` (`id`),
  ADD CONSTRAINT `fk_prod_season` FOREIGN KEY (`season_id`) REFERENCES `ism_seasons` (`id`),
  ADD CONSTRAINT `fk_prod_system` FOREIGN KEY (`system_id`) REFERENCES `ism_irrigation_systems` (`id`);

--
-- Constraints for table `ism_production_report_exports`
--
ALTER TABLE `ism_production_report_exports`
  ADD CONSTRAINT `fk_rpt_season` FOREIGN KEY (`season_id`) REFERENCES `ism_seasons` (`id`);

--
-- Constraints for table `ism_production_targets`
--
ALTER TABLE `ism_production_targets`
  ADD CONSTRAINT `fk_pt_season` FOREIGN KEY (`season_id`) REFERENCES `ism_seasons` (`id`),
  ADD CONSTRAINT `fk_pt_system` FOREIGN KEY (`system_id`) REFERENCES `ism_irrigation_systems` (`id`);

--
-- Constraints for table `ism_submission_status`
--
ALTER TABLE `ism_submission_status`
  ADD CONSTRAINT `fk_ss_season` FOREIGN KEY (`season_id`) REFERENCES `ism_seasons` (`id`),
  ADD CONSTRAINT `fk_ss_system` FOREIGN KEY (`system_id`) REFERENCES `ism_irrigation_systems` (`id`);

--
-- Constraints for table `ism_system_attachments`
--
ALTER TABLE `ism_system_attachments`
  ADD CONSTRAINT `fk_att_system` FOREIGN KEY (`system_id`) REFERENCES `ism_irrigation_systems` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ism_system_coverage`
--
ALTER TABLE `ism_system_coverage`
  ADD CONSTRAINT `fk_cov_system` FOREIGN KEY (`system_id`) REFERENCES `ism_irrigation_systems` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ism_water_allocations`
--
ALTER TABLE `ism_water_allocations`
  ADD CONSTRAINT `fk_alloc_season` FOREIGN KEY (`season_id`) REFERENCES `ism_seasons` (`id`),
  ADD CONSTRAINT `fk_alloc_system` FOREIGN KEY (`system_id`) REFERENCES `ism_irrigation_systems` (`id`);

--
-- Constraints for table `ism_water_delivery_issues`
--
ALTER TABLE `ism_water_delivery_issues`
  ADD CONSTRAINT `fk_wdi_log` FOREIGN KEY (`delivery_log_id`) REFERENCES `ism_water_delivery_logs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ism_water_delivery_logs`
--
ALTER TABLE `ism_water_delivery_logs`
  ADD CONSTRAINT `fk_wdl_season` FOREIGN KEY (`season_id`) REFERENCES `ism_seasons` (`id`),
  ADD CONSTRAINT `fk_wdl_system` FOREIGN KEY (`system_id`) REFERENCES `ism_irrigation_systems` (`id`);

--
-- Constraints for table `ism_work_orders`
--
ALTER TABLE `ism_work_orders`
  ADD CONSTRAINT `fk_wo_asset` FOREIGN KEY (`asset_id`) REFERENCES `ism_infrastructure_assets` (`id`),
  ADD CONSTRAINT `fk_wo_status` FOREIGN KEY (`status_id`) REFERENCES `ism_work_order_statuses` (`id`),
  ADD CONSTRAINT `fk_wo_system` FOREIGN KEY (`system_id`) REFERENCES `ism_irrigation_systems` (`id`);

--
-- Constraints for table `ism_work_order_materials`
--
ALTER TABLE `ism_work_order_materials`
  ADD CONSTRAINT `fk_wom_wo` FOREIGN KEY (`work_order_id`) REFERENCES `ism_work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ism_work_order_status_history`
--
ALTER TABLE `ism_work_order_status_history`
  ADD CONSTRAINT `fk_wosh_wo` FOREIGN KEY (`work_order_id`) REFERENCES `ism_work_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `leave_balance`
--
ALTER TABLE `leave_balance`
  ADD CONSTRAINT `fk_lb_employee` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lb_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_type` (`leave_type_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `leave_balance_log`
--
ALTER TABLE `leave_balance_log`
  ADD CONSTRAINT `fk_log_by` FOREIGN KEY (`performed_by`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_log_emp` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_log_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_type` (`leave_type_id`) ON UPDATE CASCADE;

--
-- Constraints for table `leave_request`
--
ALTER TABLE `leave_request`
  ADD CONSTRAINT `fk_lr_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `employee` (`emp_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lr_employee` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_lr_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_type` (`leave_type_id`) ON UPDATE CASCADE;

--
-- Constraints for table `managers_office_staff`
--
ALTER TABLE `managers_office_staff`
  ADD CONSTRAINT `managers_office_staff_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD CONSTRAINT `message_reactions_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`message_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_reactions_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `office`
--
ALTER TABLE `office`
  ADD CONSTRAINT `fk_office_manager` FOREIGN KEY (`manager_emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_parent_office` FOREIGN KEY (`parent_office_id`) REFERENCES `office` (`office_id`) ON DELETE SET NULL;

--
-- Constraints for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD CONSTRAINT `password_reset_requests_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `personal_locator_slips`
--
ALTER TABLE `personal_locator_slips`
  ADD CONSTRAINT `personal_locator_slips_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `personal_locator_slips_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `project_boards`
--
ALTER TABLE `project_boards`
  ADD CONSTRAINT `project_boards_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE;

--
-- Constraints for table `project_members`
--
ALTER TABLE `project_members`
  ADD CONSTRAINT `project_members_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_members_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_members_ibfk_3` FOREIGN KEY (`added_by`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `queue_history`
--
ALTER TABLE `queue_history`
  ADD CONSTRAINT `queue_history_ibfk_1` FOREIGN KEY (`queue_id`) REFERENCES `visitor_queue` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `queue_history_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `employee` (`emp_id`) ON DELETE SET NULL;

--
-- Constraints for table `ris_items`
--
ALTER TABLE `ris_items`
  ADD CONSTRAINT `ris_items_ibfk_1` FOREIGN KEY (`ris_id`) REFERENCES `ris_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ris_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `ris_records`
--
ALTER TABLE `ris_records`
  ADD CONSTRAINT `ris_records_ibfk_1` FOREIGN KEY (`iar_id`) REFERENCES `iar_records` (`id`),
  ADD CONSTRAINT `ris_records_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_reservations`
--
ALTER TABLE `room_reservations`
  ADD CONSTRAINT `fk_rr_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE;

--
-- Constraints for table `section`
--
ALTER TABLE `section`
  ADD CONSTRAINT `fk_section_office` FOREIGN KEY (`office_id`) REFERENCES `office` (`office_id`),
  ADD CONSTRAINT `section_ibfk_1` FOREIGN KEY (`head_emp_id`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `section_secretaries`
--
ALTER TABLE `section_secretaries`
  ADD CONSTRAINT `section_secretaries_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`),
  ADD CONSTRAINT `section_secretaries_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD CONSTRAINT `service_requests_ibfk_1` FOREIGN KEY (`requesting_emp_id`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `service_requests_ibfk_2` FOREIGN KEY (`supervisor_emp_id`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `service_requests_ibfk_3` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`),
  ADD CONSTRAINT `service_requests_ibfk_4` FOREIGN KEY (`driver_emp_id`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `service_requests_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `service_request_passengers`
--
ALTER TABLE `service_request_passengers`
  ADD CONSTRAINT `fk_passenger_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `fk_passenger_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `service_request_passengers_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`request_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_request_passengers_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`);

--
-- Constraints for table `supply_requests`
--
ALTER TABLE `supply_requests`
  ADD CONSTRAINT `supply_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `supply_request_items`
--
ALTER TABLE `supply_request_items`
  ADD CONSTRAINT `supply_request_items_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `supply_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`project_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `employee` (`emp_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_4` FOREIGN KEY (`board_id`) REFERENCES `project_boards` (`board_id`);

--
-- Constraints for table `unit_section`
--
ALTER TABLE `unit_section`
  ADD CONSTRAINT `unit_section_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`),
  ADD CONSTRAINT `unit_section_ibfk_2` FOREIGN KEY (`head_emp_id`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_employee` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`emp_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`);

--
-- Constraints for table `user_online_status`
--
ALTER TABLE `user_online_status`
  ADD CONSTRAINT `user_online_status_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `office` (`office_id`);

--
-- Constraints for table `visitor_queue`
--
ALTER TABLE `visitor_queue`
  ADD CONSTRAINT `fk_visitor_queue_employee` FOREIGN KEY (`person_to_visit`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `visitor_queue_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `employee` (`emp_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
