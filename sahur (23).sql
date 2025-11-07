-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 07, 2025 at 06:30 AM
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
(16, 147, 'Oct 1 - Oct 15', '2025-10-01', '2025-10-15', 'COMPLETE AND LATE', 'NOT FORWARDED', '2025-10-20', '', '2025-10-08 02:58:28', '2025-10-08 03:02:57'),
(17, 43, 'Oct 1 - Oct 15', '2025-10-01', '2025-10-15', 'COMPLETE', 'NOT FORWARDED', '2025-10-17', '', '2025-10-08 03:02:17', '2025-10-08 03:02:17'),
(18, 88, 'Oct 1 - Oct 15', '2025-10-01', '2025-10-15', 'COMPLETE AND LATE', 'NOT FORWARDED', '2025-10-22', '', '2025-10-08 03:03:54', '2025-10-08 03:03:54'),
(19, 43, 'Oct 16 - Oct 31', '2025-10-16', '2025-10-31', 'COMPLETE', 'FORWARDED', '2025-11-29', 'TO ABC\r\n', '2025-10-08 03:04:22', '2025-10-08 06:54:26');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`message_id`, `room_id`, `sender_id`, `message`, `message_type`, `file_path`, `is_read`, `created_at`) VALUES
(45, 30, 59, 'awaw', 'text', NULL, 1, '2025-10-13 00:39:05'),
(46, 30, 59, 'asd', 'text', NULL, 1, '2025-10-13 00:39:14'),
(47, 30, 32, 'sadsa', 'text', NULL, 0, '2025-10-13 00:39:23'),
(48, 30, 32, 'dfsgf', 'text', NULL, 0, '2025-10-13 00:39:37'),
(49, 31, 32, 'yow', 'text', NULL, 1, '2025-10-20 07:18:42'),
(50, 31, 31, 'ngi', 'text', NULL, 1, '2025-10-20 07:19:07'),
(51, 31, 32, 'asdasda', 'text', NULL, 0, '2025-10-20 07:19:13');

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
(27, 'Private Chat', 'private', 32, '2025-10-12 05:04:32'),
(28, 'Private Chat', 'private', 27, '2025-10-12 05:11:00'),
(29, 'Private Chat', 'private', 32, '2025-10-12 05:13:25'),
(30, 'Private Chat', 'private', 59, '2025-10-13 00:39:01'),
(31, 'Private Chat', 'private', 32, '2025-10-20 07:18:24');

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
(53, 27, 32, '2025-10-12 05:04:32'),
(54, 27, 27, '2025-10-12 05:04:32'),
(55, 28, 27, '2025-10-12 05:11:00'),
(56, 28, 50, '2025-10-12 05:11:00'),
(57, 29, 32, '2025-10-12 05:13:25'),
(58, 29, 139, '2025-10-12 05:13:25'),
(59, 30, 59, '2025-10-13 00:39:01'),
(60, 30, 32, '2025-10-13 00:39:01'),
(61, 31, 32, '2025-10-20 07:18:24'),
(62, 31, 31, '2025-10-20 07:18:24');

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
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `doc_id` int(11) NOT NULL,
  `doc_number` varchar(50) NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `title` varchar(255) NOT NULL,
  `type_id` int(11) NOT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `owner_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `size` int(11) DEFAULT NULL COMMENT 'File size in bytes',
  `mime_type` varchar(100) DEFAULT NULL,
  `qr_code` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`doc_id`, `doc_number`, `version`, `title`, `type_id`, `is_archived`, `owner_id`, `file_path`, `size`, `mime_type`, `qr_code`, `created_at`, `updated_at`, `remarks`) VALUES
(59, 'ACIMO-ADM(ADU)-080825-1', 1, 'asd', 3, 0, 32, '../uploads/documents/1754623115_6901640bc7694e17.pdf', NULL, NULL, '../uploads/qrcodes/qr_5a9eaeda6c8049f4.png', '2025-08-08 03:18:35', '2025-08-08 03:18:35', 'dasd');

-- --------------------------------------------------------

--
-- Table structure for table `document_actions`
--

CREATE TABLE `document_actions` (
  `action_id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `remarks` text DEFAULT NULL,
  `action_by` int(11) NOT NULL,
  `action_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_comments`
--

CREATE TABLE `document_comments` (
  `comment_id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_history`
--

CREATE TABLE `document_history` (
  `history_id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_history`
--

INSERT INTO `document_history` (`history_id`, `doc_id`, `action`, `emp_id`, `details`, `created_at`) VALUES
(225, 59, 'created', 32, 'Document created with remarks', '2025-08-08 03:18:35');

-- --------------------------------------------------------

--
-- Table structure for table `document_monitoring`
--

CREATE TABLE `document_monitoring` (
  `document_id` int(11) NOT NULL,
  `tracking_no` varchar(50) NOT NULL,
  `document_type` enum('incoming','outgoing','internal') NOT NULL,
  `type_of_document` varchar(100) NOT NULL,
  `from_section` varchar(255) NOT NULL,
  `from_emp_id` int(11) DEFAULT NULL,
  `document_name` varchar(255) NOT NULL,
  `to_section_id` int(11) NOT NULL,
  `for_signature` varchar(50) NOT NULL,
  `received_by` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled','forwarded','needs_clarification') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_status`
--

CREATE TABLE `document_status` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `color` varchar(20) DEFAULT '#007bff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_status`
--

INSERT INTO `document_status` (`status_id`, `status_name`, `color`, `created_at`) VALUES
(1, 'Draft', '#6c757d', '2025-07-15 04:36:34'),
(2, 'Pending', '#ffc107', '2025-07-15 04:36:34'),
(3, 'Approved', '#28a745', '2025-07-15 04:36:34'),
(4, 'Rejected', '#dc3545', '2025-07-15 04:36:34'),
(5, 'Archived', '#17a2b8', '2025-07-15 04:36:34');

-- --------------------------------------------------------

--
-- Table structure for table `document_transfers`
--

CREATE TABLE `document_transfers` (
  `transfer_id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `from_emp_id` int(11) NOT NULL,
  `to_section_id` int(11) NOT NULL,
  `to_unit_id` int(11) DEFAULT NULL,
  `status` enum('pending','accepted','revised','returned') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_types`
--

CREATE TABLE `document_types` (
  `type_id` int(11) NOT NULL,
  `type_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_types`
--

INSERT INTO `document_types` (`type_id`, `type_name`, `description`, `created_at`) VALUES
(1, 'Policy', 'Company policies and guidelines', '2025-07-15 04:36:34'),
(2, 'Procedure', 'Standard operating procedures', '2025-07-15 04:36:34'),
(3, 'Form', 'Official forms and templates', '2025-07-15 04:36:34'),
(4, 'Report', 'Various reports and documents', '2025-07-15 04:36:34');

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
  `reset_token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`emp_id`, `picture`, `id_number`, `first_name`, `middle_name`, `last_name`, `ext_name`, `gender`, `address`, `bday`, `email`, `phone_number`, `employment_status_id`, `appointment_status_id`, `section_id`, `office_id`, `position_id`, `unit_section_id`, `is_manager`, `reset_token`, `reset_token_expiry`) VALUES
(23, '6860d112a681b_Sircloyd.png', '596165', 'Mark Cloyd ', 'G.', 'SO', '', 'Male', 'cam norte', '2000-06-26', 'email@example.com', '555-1002', 1, 10, NULL, 1, 3, NULL, 1, NULL, NULL),
(24, '683e6ef11e368_2020-nia-logo.png', '104282', 'Mark', 'L', 'SALEM', '', 'Male', 'Albay', '1991-12-03', 'email@example.com', '555-1001', 1, 5, 4, 1, 23, NULL, 0, NULL, NULL),
(25, '6860d355ae142_CREDO, P.png', '692846', 'Patricia Gillyn', 'L', 'CREDO', '', 'Female', 'Camsur', '2000-01-01', 'email@example.com', '09123456789', 1, 7, NULL, 1, 1, NULL, 0, NULL, NULL),
(26, '6860ccfe1fd0c_30. ELLA N. RINGAD.JPG', '921488', 'Ella ', 'N', 'RINGAD', '', 'Female', 'Ligao', '1998-09-17', 'email@example.com', '987897', 1, 5, NULL, 1, 1, NULL, 0, '6ddddc8c24bbf94a7632f21574d46b55710cf6e32d757f9cc0711e19d1f865b0', '2025-10-20 09:37:53'),
(27, '6860d369e1eca_PEÑAFLOR, J.png', '785273', 'Jessica', 'V', 'PEÑAFLOR', '', 'Male', 'camsur', '2001-09-10', 'email@example.com', '09654800074', 1, 7, NULL, 1, 27, NULL, 0, '800876e752e564bfa2a1ec89993bd124d4d71e9d4b6830fda2c2c6f86eb15dce', '2025-10-11 09:58:12'),
(28, '6860e76730f25_10. MYRA M. ETCOBANEZ (B).JPG', '406321', 'Myra', 'M', 'ETCOBANEZ', '', 'Male', 'Camalig', '2000-01-01', 'email@example.com', '987898', 1, 5, 1, 1, 28, 46, 0, NULL, NULL),
(29, '', '705491', 'Amy B.', '', 'CALPE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987899', 1, 5, 1, 1, 7, NULL, 0, NULL, NULL),
(30, '', '970465', 'Richard S.', '', 'NACARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987900', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(31, '6860d1d88d7e2_BONAPOS, R.png', '677630', 'Reese', 'P.', 'BONAPOS', '', 'Female', 'Ligao', '1999-11-08', 'email@example.com', '987901', 1, 5, 1, 1, 1, NULL, 0, '73cd50383b5d0b712d9c790f277461656e492f2d4ef354ca322f4065c02b3de2', '2025-10-20 09:44:36'),
(32, '6860cd253ebac_OROGO, MARC DAVID.png', '616630', 'Marc David ', 'O', 'OROGO', '', 'Male', 'Guinobatan', '1996-08-27', 'email@example.com', '987902', 1, 7, 1, 1, 37, 46, 0, '3b61e42caa8521acad8c7312348cb28b0f269dc4fe106885b074db17dff642d6', '2025-10-20 09:04:48'),
(33, '', '847101', 'Diana Rose P.', '', 'PAGAL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987903', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(34, '', '472890', 'Senen A.', '', 'BALONDO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987904', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(35, '', '578550', 'Jojo O.', '', 'PAJE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987905', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(36, '', '706109', 'Marcos B.', '', 'BALITA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987906', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(37, '', '413637', 'Dante A.', '', 'SAN BUENAVENTURA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987907', 1, 5, 1, 1, 30, NULL, 0, NULL, NULL),
(38, '', '196770', 'Isagani C.', '', 'CULLAT', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987908', 1, 7, 1, 1, 32, NULL, 0, NULL, NULL),
(39, '', '892078', 'Bryann Frederick R.', '', 'DINGLASAN', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987909', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(40, '', '853739', 'Christian Levy Jr. B.', '', 'LONTAC', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987910', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(41, '', '529551', 'Nando M.', '', 'NAYVE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987911', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(42, '6860e78f75c9a_27. LOUIE DEDASE.JPG', '411215', 'Luisito ', 'O', 'DEDASE', '', 'Male', 'Camsur', '2000-01-01', 'email@example.com', '987912', 1, 5, 1, 1, 31, NULL, 0, NULL, NULL),
(43, '68eb373195e29_26. ELA MAE S. ABILA.jpg', '990269', 'Ela Mae ', 'S.', 'ABILA', '', 'Female', 'qq', '2000-02-02', 'email@example.com', '987913', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(44, '', '515714', 'Mark Charl\'s N.', '', 'AZUTEA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987914', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(45, '6860d0aa6019a_PITALLANO, V.png', '771459', 'Vida ', 'E', 'PITALLANO', '', 'Female', 'Naga City', '2000-01-01', 'email@example.com', '987915', 1, 5, 2, 1, 1, NULL, 0, NULL, NULL),
(46, '6860d15966f5d_21. ALEXANDRA JOY M. DELGADO.JPG', '489240', 'Alexandra Joy ', 'M', 'DELGADO', '', 'Female', 'legazpi', '2000-01-01', 'email@example.com', '987916', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(47, '', '199052', 'Ma. Cristina R.', '', 'ALVAREZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987917', 1, 7, 1, 1, 10, NULL, 0, NULL, NULL),
(48, '', '416828', 'Darlene Mae ', 'C', 'MAYOR', '', 'Male', 'cam sur', '2000-05-01', 'email@example.com', '987918', 1, 7, 2, 1, 9, NULL, 0, NULL, NULL),
(49, '', '862342', 'John Paul R.', '', 'PAPA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987919', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(50, '', '861143', 'Maria Beatrice', '', 'ROBAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987920', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(51, '', '282537', 'April Jane B.', '', 'RODA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987921', 1, 7, 2, 1, 13, NULL, 0, NULL, NULL),
(52, '68e4bfdb564b1_4 VILMA M. MANLANGIT.JPG', '929272', 'Vilma ', 'M.', 'MANLANGIT', '', 'Female', 'Catanduanes', '2000-01-01', 'email@example.com', '987922', 1, 5, 2, 1, 1, 50, 0, NULL, NULL),
(53, '', '243192 ', 'Rejean L.', '', 'MARIÑAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987923', 1, 7, 2, 1, 11, NULL, 0, NULL, NULL),
(54, '6860d08955558_32. LECH FIDEL PANTE.JPG', '164959', 'Lech Fidel', 'C', 'PANTE', '', 'Male', 'Naga City', '2000-01-01', 'email@example.com', '987924', 1, 10, 3, 1, 1, NULL, 0, '0af1ad755dc92e4a58696473b7469136903502e902102f9934364be8ae0b676a', '2025-10-20 09:16:15'),
(55, '6860d19789058_JUAREZ, JA.png', '936322', 'Julie Anne ', 'D', 'Juarez', '', 'Female', 'Ligao', '0001-12-01', 'email@example.com', '987925', 1, 5, 3, 1, 1, NULL, 0, NULL, NULL),
(56, '6860e7ddb8672_35. JESSICA B. COMPLETO.JPG', '488433', 'Jessica ', 'B', 'COMPLETO', '', 'Female', 'Tabaco', '2000-01-01', 'email@example.com', '987926', 1, 5, 3, 1, 1, 52, 0, 'baa85edcee1ac30228435d2a56c44f84dd0bf11f4efec7d2b0f91a7a00f3304b', '2025-10-15 09:18:59'),
(57, '', '568533', 'Jane Amy R. ', '', 'SARION', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987927', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(58, '', '505503', 'Roland O.', '', 'CLARIÑO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987928', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(59, '68c0dac31ff8d_EUSEBIO, W.png', '655711', 'Walter', 'P', 'EUSEBIO', '', 'Male', 'tuburan', '2002-01-10', 'email@example.com', '987929', 1, 5, 1, 1, 1, NULL, 0, '536a7b58e80359b4dde44276d451f23be7c2c06033f07aa543c0922d17fbec77', '2025-10-20 09:40:24'),
(60, '', '331046', 'Joel O.', '', 'OLAVIAGA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987930', 1, 5, 3, 1, 15, NULL, 0, NULL, NULL),
(61, '', '300179 ', 'Richard R.', '', 'RESENTES', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987931', 1, 5, 3, 1, 19, NULL, 0, NULL, NULL),
(62, '', '983758', 'Raymond Gil C.', '', 'AYCARDO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987932', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(63, '', '453727', 'Arnulfo Natividad B.', '', 'BANGA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987933', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(64, '68f303772fd2f_436382184_829285465286365_6559261396441469346_n.jpg', '864981', 'John Patrick', 'B.', 'CABILES', '', 'Male', 'asdas', '2000-01-02', 'email@example.com', '987934', 1, 5, 3, 1, 45, NULL, 0, NULL, NULL),
(65, '', '298598', 'Hendryx D.', '', 'CAPINO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987935', 1, 7, 3, 1, 2, NULL, 0, NULL, NULL),
(66, '', '778342', 'Don R.', '', 'CONCEPCION', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987936', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(67, '', '398357', 'Frederick V.', '', 'DAGUMBOY', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987937', 1, 7, 3, 1, 42, NULL, 0, NULL, NULL),
(68, '', '556790', 'Froilan S.', '', 'GESTIADA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987938', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(69, '', '466181', 'Ronald A.', '', 'LLEVA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987939', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(70, '', '746795', 'Joseph', '', 'MORAL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987940', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(71, '', '865161', 'Chlowell Ferby B.', '', 'NASOL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987941', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(72, '', '522191', 'Mark Renen Q.', '', 'NAVARRO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987942', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(73, '', '587562', 'Gregory Mark', '', 'OCAMPO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987943', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(74, '', '719165', 'Eduardo J.', '', 'PELIGAN', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987944', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(75, '', '177646', 'Sammy P.', '', 'PELIGAN', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987945', 1, 7, 1, 1, 42, NULL, 0, NULL, NULL),
(76, '', '382370', 'Raymond B.', '', 'PEPAÑO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987946', 1, 7, 3, 1, 15, NULL, 0, NULL, NULL),
(77, '', '570364', 'Haji P.', '', 'POLIDARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987947', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(78, '', '570984', 'Rizaldy P.', '', 'POLIDARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987948', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(79, '', '200580', 'Luisito P.', '', 'PROPOGO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987949', 1, 7, 3, 1, 19, NULL, 0, NULL, NULL),
(80, '686cb444b336a_SALIRE, MD.png', '359326', 'Mac Daryll ', 'c.', 'SALIRE', '', 'Male', 'OAS', '2000-05-22', 'email@example.com', '9305224889', 5, 7, NULL, 1, 15, NULL, 0, NULL, NULL),
(81, '', '705059', 'Donel P.', '', 'VIBAR', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987951', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(82, '6860e7fc7f05e_60. JORDAN P. RONCESVALLES.JPG', '740531', 'Jordan ', 'P', 'RONCESVALLES', '', 'Male', 'oas', '2000-01-01', 'email@example.com', '987952', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(83, '6878ab1175327_38. REGINE RENON.jpg', '429389', 'Regine ', 'Chavez', 'RENON', '', 'Female', 'Ligao', '1990-03-23', 'email@example.com', '987953', 1, 5, 3, 1, 18, NULL, 0, NULL, NULL),
(84, '6878a957c38ec_39. ALEXANDRA MAE L. DELA CRUZ.JPG', '905404', 'Alexandra Mae', 'Lozada', 'DELA CRUZ', '', 'Female', 'San Jose, Pili, Camarines Sur', '1997-07-05', 'alexandramaedelacruz97@gmail.com', '09121341758', 1, 5, 3, 1, 16, NULL, 0, NULL, NULL),
(85, '68c232aec4003_DOLZ, JEV.png', '134245', 'Jevielyn', 'A', 'DOLZ', '', 'Female', 'oas', '2000-07-01', 'email@example.com', '987955', 1, 7, 3, 1, 2, NULL, 0, 'f1ac547358fe58b25bbcbbd12be8a37381e49c8fd79c44cc85a2c41b25453567', '2025-09-11 05:25:16'),
(86, '', '318147', 'Harrish O.', '', 'MATOCINOS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987956', 1, 7, 3, 1, 43, NULL, 0, NULL, NULL),
(87, '6860e7bda4197_56. ROSEMARIE A. PARAISO.JPG', '938818', ' Rosemarie', 'A', 'PARAISO  ', '', 'Female', 'Naga CIty', '2000-01-01', 'email@example.com', '987957', 1, 5, 3, 1, 1, 49, 0, NULL, NULL),
(88, '', '135180', 'Gilbert S.', '', 'ARABACA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987958', 1, 5, 3, 1, 14, NULL, 0, NULL, NULL),
(89, '', '250817', 'Rey B.', '', 'LANUZO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987959', 1, 5, 3, 1, 16, NULL, 0, NULL, NULL),
(90, '', '935951 ', 'Noel B.', '', 'NASH', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987960', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(91, '6860d38da2eea_57. COLEEN M. RASTRULLO -2.JPG', '648335', 'Coleen', 'M.', 'RASTRULLO', '', 'Female', 'albay', '2000-10-01', 'email@example.com', '987961', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(92, '', '956528', ' Don A.', '', 'REBADAJO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987962', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(93, '', '500213', 'Lyle Kenneth A.', '', 'CALABINES', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987963', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(94, '', '660100', 'Jennimel R.', '', 'DAYUPAY', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987964', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(95, '', '136841 ', 'Francisco Jr. B.', '', 'JUAREZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987965', 1, 7, 3, 1, 16, NULL, 0, NULL, NULL),
(96, '', '194697', 'Gio Dominick M.', '', 'MANLANGIT', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987966', 1, 7, 3, 1, 41, NULL, 0, NULL, NULL),
(97, '', '333794', 'Mark Christian R.', '', 'MARBELLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987967', 1, 7, 3, 1, 41, NULL, 0, NULL, NULL),
(98, '68467ce6d462d_sahur.png', '255154', ' Loewe Mae', 'B.', 'OLIVERA', '', 'Female', 'Guinobatan', '1997-05-29', 'email@example.com', '987968', 1, 7, 3, 1, 16, 49, 0, NULL, NULL),
(99, '', '435914', 'Noel Jr. B.', '', 'ORAYE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987969', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(100, '68bfa30e8feae_PETILLA, CRISNA.png', '587844', ' Crisna', ' ', 'PETILLA', '', 'Female', 'P-3, TULA- TULA(GRANDE), LIGAO CITY', '2000-02-02', 'crisna.petilla1@gmail.com', '0916-501-3844', 1, 5, 3, 1, 16, NULL, 0, NULL, NULL),
(101, '', '992299', 'Armando S.', '', 'PORTUGUEZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987971', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(102, '6870a1d6797fe_SIGUENZA, J.png', '710547', ' Jewel', 'A', 'SIGUENZA', '', 'Female', 'camsur', '2001-01-05', 'email@example.com', '987972', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(103, '', '608305 ', 'Bernardita P. ', '', 'BALINGASA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987973', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(104, '68e9b1d167a47_70. MA. DOLORES S. BELGADO.JPG', '287556', 'Ma. Dolores ', 'S.', 'BELGADO', '', 'Female', 'camsur', '2000-01-01', 'email@example.com', '987974', 1, 5, 4, 1, 3, 51, 0, NULL, NULL),
(105, '6860d06a73b61_68. IAN FELICIANO III BERDIN.JPG', '587812', 'Ian Feliciano', 'P', 'BERDIN', 'III', 'Male', 'Camalig', '2000-01-01', 'email@example.com', '987975', 1, 10, 1, 1, 22, NULL, 0, NULL, NULL),
(106, '', '817959', 'John  Bernard S.', '', 'NACARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987976', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(107, '', '669920 ', 'Dale Derick L.', '', 'DETERA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987977', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(108, '', '620998', 'Ramon Jr. C.', '', 'AYDALLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987978', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(109, '', '659463', 'Marvin A.', '', 'MESTIOLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987979', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(110, '', '524286', 'Gerald M.', '', 'NAVARRO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987980', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(111, '', '136340', 'Sammy M.', '', 'OLI', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987981', 4, 7, 4, 1, 15, NULL, 0, NULL, NULL),
(112, '', '534860', 'Von Jayvee A.', '', 'PERALTA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987982', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(113, '', '259436', 'Francis B.', '', 'ARCILLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987983', 1, 7, 4, 1, 42, NULL, 0, NULL, NULL),
(114, '', '983424', 'Jay Ar P.', '', 'ATANANTE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987984', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(115, '', '837811', 'Jerwin P.', '', 'BOMBITA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987985', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(116, '', '595261', 'Ulysses ', '', 'GUADALUPE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987986', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(117, '', '323604', 'Ramon A.', '', 'RAMOS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987987', 1, 7, 4, 1, 42, NULL, 0, NULL, NULL),
(118, '', '636146', 'Raynald R.', '', 'RAÑOLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987988', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(119, '', '793242', 'Cesar M.', '', 'REORA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987989', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(120, '', '740109', 'Conchita R.', '', 'REYES', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987990', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(121, '689162a174f31_96. MODESTO HARLEY S. NATE.jpg', '232531', 'Modesto Harley ', 'S', 'NATE', '', 'Male', 'ligao', '2000-01-01', 'email@example.com', '987991', 1, 5, 4, 1, 25, 48, 0, '0d2be1312f1cd481c55b3d7f27baf04f6d227bb2a46fef978cd4f0f604c92296', '2025-09-09 10:28:57'),
(122, '', '447264', 'Milany B.', '', 'DACILLO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987992', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(123, '', '478643', 'Elizabeth J.', '', 'JACOB', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987993', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(124, '', '429064', 'Jemar B.', '', 'PEÑAFLOR', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987994', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(125, '', '432411 ', 'Carlito C.', '', 'PONGPONG', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987995', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(126, '', '168325', 'Segfrido A.', '', 'PONTILLAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987996', 1, 5, 4, 1, 20, NULL, 0, NULL, NULL),
(127, '6860d2123f6dd_103. MARILOU P. ANGUSTIA.JPG', '187727', 'Marilou', 'A', 'ROBLEDO', '', 'Female', 'Ligao', '2000-01-01', 'email@example.com', '987997', 1, 5, 4, 1, 20, NULL, 0, NULL, NULL),
(128, '', '983454', 'Aliza May D.', '', 'BALINGASA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987998', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(129, '', '107996', 'Jhedson S.', '', 'CELLANO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987999', 1, 7, 4, 1, 39, NULL, 0, NULL, NULL),
(130, '', '208506', 'Gail Nicole J.', '', 'JACOB', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988000', 1, 7, 4, 1, 39, NULL, 0, NULL, NULL),
(131, '', '569250', ' Jun Shane M.', '', 'PEÑAFIEL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988001', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(132, '689162cc35a98_85. ALDRIN P. FRANCIA.JPG', '751102', 'Aldrin', 'P', 'FRANCIA', '', 'Male', 'naga', '2000-01-01', 'email@example.com', '988002', 1, 5, 4, 1, 1, 47, 0, '39d9963cd99164c5886415efeff07c963ec7c71935ab6473a4cd8bf4558a07bd', '2025-09-09 11:47:59'),
(133, '', '722450', 'Nestor S.', '', 'REODIQUE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988003', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(134, '', '521215', 'Salvador Jr. H.', '', 'AGRIPA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988004', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(135, '', '348180', 'Alexander E.', '', 'RULL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988005', 1, 5, 4, 1, 23, NULL, 0, NULL, NULL),
(136, '', '625407', 'Carl Louie B.', '', 'LONTAC', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988006', 1, 5, 4, 1, 23, NULL, 0, NULL, NULL),
(137, '', '254906', 'John Lloyd M.', '', 'AGRIPA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988007', 1, 7, 4, 1, 19, NULL, 0, NULL, NULL),
(138, '', '503396', 'Jeric A.', '', 'AMADOS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988008', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(139, '', '799103', 'Jose Domingo C.', '', 'BERZUELA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988009', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(140, '', '405192', 'John Kenneth P.', '', 'PAJE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988010', 1, 7, 3, 1, 42, NULL, 0, NULL, NULL),
(141, '', '775418', 'Patrick Jorge C.', '', 'PANTE.', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988011', 1, 5, 4, 1, 23, NULL, 0, NULL, NULL),
(142, '', '556604', 'Mark Angelo P.', '', 'POLIDARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988012', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(143, '', '614317', ' Albert S.', '', 'RAPOSA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988013', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(144, '', '296935', ' Kenneth Christopher O.', '', 'REODIQUE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988014', 4, 7, 4, 1, 26, NULL, 0, NULL, NULL),
(145, '', '979595', 'Raymond R.', '', 'VIÑAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988015', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(146, '', '723219', 'Angelo V. ', '', 'MARQUEZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988016', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(147, '', '599880', 'Isagani Jr. P.', '', 'ADELANTE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988017', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(148, '', '910354', 'Elmerio B.', '', 'TENDENILLA,', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988018', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(149, '', '879431', 'John Learry A.', '', 'BRIOSO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988019', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(150, '', '578474', 'Jomel M.', '', 'TORIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988020', 1, 7, 1, 10, 28, NULL, 0, NULL, NULL),
(151, '', '913614', 'Jomer L.', '', 'BEO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988021', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(152, '', '135884', 'Joshua A.', '', 'LUMBAO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988022', 4, 7, NULL, 1, 41, NULL, 0, NULL, NULL),
(153, '', '299888', 'Edilberto Jr. R.', '', 'BEO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988023', 1, 7, NULL, 1, 39, NULL, 0, NULL, NULL),
(154, '', '653144', 'Dindo Z.', '', 'MANLANGIT', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988024', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(155, '', '877653', 'Frederick T.', '', 'MELGAR', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988025', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(156, '', '545194', 'Mark Anjo T.', '', 'ALNAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988026', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(157, '', '553783', 'Richiel A.', '', 'MASAGCA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988027', 1, 5, NULL, 10, 1, NULL, 0, NULL, NULL),
(158, '', '298653', 'Kayceelyn M.', '', 'TAPIA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988028', 1, 7, 3, 10, 2, NULL, 0, NULL, NULL),
(159, '', '608312', 'Rodolfo Jr. G.', '', 'LLAVE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988029', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL),
(160, '68f82bd0dcb97_2020-nia-logo.png', '607452', 'Francis', 'B.', 'Olaguer', '', 'Male', 'Guinobatan', '2000-10-10', 'email@email.com', '12321312', 1, 5, 1, 1, 1, NULL, 0, NULL, NULL);

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
(64, 52, 0),
(85, 52, 0),
(100, 49, 0),
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

--
-- Dumping data for table `folders`
--

INSERT INTO `folders` (`folder_id`, `folder_name`, `description`, `section_id`, `parent_folder_id`, `password`, `created_by`, `created_at`, `updated_at`, `is_locked`) VALUES
(60, 'ASDAS', 'ADA', 1, NULL, NULL, 32, '2025-10-20 15:55:12', '2025-10-20 15:55:12', 0);

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

--
-- Dumping data for table `folder_activity_logs`
--

INSERT INTO `folder_activity_logs` (`log_id`, `folder_id`, `emp_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(79, 60, 32, 'created', 'Folder \'ASDAS\' created', '::1', NULL, '2025-10-20 15:55:12');

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

--
-- Dumping data for table `folder_shares`
--

INSERT INTO `folder_shares` (`share_id`, `folder_id`, `shared_by_emp_id`, `shared_with_emp_id`, `permission_level`, `expires_at`, `is_active`, `created_at`) VALUES
(1, 31, 31, 32, 'view', NULL, 1, '2025-10-06 01:28:12'),
(2, 38, 32, 31, 'view', NULL, 1, '2025-10-06 01:52:56'),
(3, 42, 31, 32, 'view', NULL, 0, '2025-10-06 02:07:31'),
(4, 30, 32, 31, 'edit', NULL, 1, '2025-10-06 03:36:00'),
(5, 43, 32, 31, 'view', NULL, 1, '2025-10-06 07:11:51'),
(6, 41, 32, 31, 'view', NULL, 0, '2025-10-06 07:43:33');

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

--
-- Dumping data for table `ia_officers`
--

INSERT INTO `ia_officers` (`id`, `ia_profile_id`, `officer_name`, `position`, `contact_number`, `email`, `is_active`, `created_at`) VALUES
(1, 10, '1', '1', '12121', '', 1, '2025-11-02 18:26:26');

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

--
-- Dumping data for table `ia_profiles`
--

INSERT INTO `ia_profiles` (`id`, `ia_name`, `ia_code`, `mailing_address`, `president_name`, `contact_number`, `date_organized`, `sec_registration_date`, `sec_registration_number`, `ia_tin`, `service_area_ha`, `fusa_ha`, `farmer_beneficiaries`, `actual_ia_members`, `tsags_count`, `existing_contract`, `contract_effectivity_date`, `canal_length_km`, `male_members`, `female_members`, `congressional_district`, `region`, `province`, `imo`, `nis`, `status`, `assigned_employee_id`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(10, 'LIGAO-OAS CONSOLIDATED IRRIGATORS\' ASSOCIATION, (LOCIA) INC.', 'LOCIA', 'LIGAO', 'RAMON S. REGILME', '09507214286', NULL, NULL, 'CN200251352', '1', 1.0000, 1.0000, 1, 1, 1, 'Under IMT', NULL, 1.000, 1, 1, 'Albay 3rd District', 'Region V - Bicol Region', 'Albay', '', NULL, 'operational', 121, 32, NULL, '2025-11-02 17:45:05', '2025-11-03 03:56:39');

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
(22, 9, 'DATA FILE BOX', 'N/A', 'PCS', 0, 0, '2025-09-18 05:40:34', '2025-09-18 05:40:34');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `balance_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` year(4) NOT NULL,
  `total_credits` decimal(5,2) NOT NULL DEFAULT 0.00,
  `used_credits` decimal(5,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_balances`
--

INSERT INTO `leave_balances` (`balance_id`, `emp_id`, `leave_type_id`, `year`, `total_credits`, `used_credits`, `balance`, `created_at`, `updated_at`) VALUES
(1, 56, 1, '2025', 20.00, 2.00, 20.00, '2025-10-16 02:06:00', '2025-10-16 04:37:16'),
(2, 59, 1, '2025', 15.00, 1.00, 14.00, '2025-10-16 03:46:37', '2025-10-16 03:46:37'),
(3, 59, 2, '2025', 0.00, 0.00, 0.50, '2025-10-20 00:52:40', '2025-10-20 00:52:40');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balance_logs`
--

CREATE TABLE `leave_balance_logs` (
  `log_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `new_balance` decimal(5,1) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `adjusted_by` int(11) DEFAULT NULL,
  `adjusted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_balance_logs`
--

INSERT INTO `leave_balance_logs` (`log_id`, `emp_id`, `leave_type_id`, `year`, `new_balance`, `remarks`, `adjusted_by`, `adjusted_at`) VALUES
(1, 56, 1, 2025, 20.0, '0', 32, '2025-10-16 03:52:41'),
(2, 56, 1, 2025, 20.0, '0', 32, '2025-10-16 04:37:16'),
(3, 59, 2, 2025, 0.5, '0', 31, '2025-10-20 00:52:40');

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `leave_id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` int(11) NOT NULL,
  `particulars` text NOT NULL,
  `remarks` text DEFAULT NULL,
  `applied_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_date` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `medical_certificate` varchar(255) DEFAULT NULL,
  `section_head_approved` tinyint(1) DEFAULT 0,
  `section_head_id` int(11) DEFAULT NULL,
  `section_head_date` datetime DEFAULT NULL,
  `section_head_remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_approved` tinyint(1) DEFAULT NULL,
  `admin_remarks` text DEFAULT NULL,
  `admin_date` datetime DEFAULT NULL,
  `manager_approved` tinyint(1) DEFAULT NULL,
  `manager_remarks` text DEFAULT NULL,
  `manager_date` datetime DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`leave_id`, `emp_id`, `leave_type_id`, `start_date`, `end_date`, `total_days`, `particulars`, `remarks`, `applied_date`, `status`, `approved_by`, `approved_date`, `rejection_reason`, `medical_certificate`, `section_head_approved`, `section_head_id`, `section_head_date`, `section_head_remarks`, `created_at`, `updated_at`, `admin_approved`, `admin_remarks`, `admin_date`, `manager_approved`, `manager_remarks`, `manager_date`, `manager_id`) VALUES
(13, 56, 1, '2025-10-17', '2025-10-17', 1, 'asd', 'asd', '2025-10-16 02:05:53', 'approved', 54, NULL, NULL, NULL, 0, 54, '2025-10-16 10:06:00', '', '2025-10-16 02:05:53', '2025-10-16 02:06:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 56, 1, '2025-10-20', '2025-10-20', 1, 'fgjhg', 'ghjgh', '2025-10-16 02:10:55', 'approved', 32, NULL, NULL, NULL, 0, 54, NULL, NULL, '2025-10-16 02:10:55', '2025-10-16 02:11:02', NULL, '', NULL, NULL, NULL, NULL, NULL),
(15, 59, 1, '2025-10-31', '2025-10-31', 1, 'asd', 'sad', '2025-10-16 03:46:16', 'approved', 32, NULL, NULL, NULL, 0, 105, NULL, NULL, '2025-10-16 03:46:16', '2025-10-16 03:46:37', NULL, 'ada', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `leave_type_id` int(11) NOT NULL,
  `leave_name` varchar(100) NOT NULL,
  `leave_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `max_days_per_year` int(11) DEFAULT NULL,
  `requires_medical_certificate` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`leave_type_id`, `leave_name`, `leave_code`, `description`, `max_days_per_year`, `requires_medical_certificate`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Vacation Leave', 'VL', 'Annual vacation leave', 15, 0, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(2, 'Mandatory/Forced Leave', 'MFL', 'Mandatory leave for employees', 5, 0, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(3, 'Sick Leave', 'SL', 'Leave for health reasons', 15, 1, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(4, 'Maternity Leave', 'MATL', 'Leave for childbirth', 105, 1, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(5, 'Paternity Leave', 'PATL', 'Leave for fathers', 7, 1, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(6, 'Special Privilege Leave', 'SPL', 'Special privilege leave', 3, 0, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(7, 'Solo Parent Leave', 'SOLO', 'Leave for solo parents', 7, 0, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(8, 'Study Leave', 'STUL', 'Leave for educational purposes', 10, 0, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(9, 'VAWC Leave', 'VAWC', 'Leave for violence against women cases', 10, 0, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(10, 'Rehabilitation Leave', 'REHAB', 'Leave for rehabilitation', 30, 1, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(11, 'Special Leave Benefits for Women', 'WOMEN', 'Special leave for women', 2, 0, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(12, 'Special Emergency (Calamity) Leave', 'CALAMITY', 'Leave during calamities', 5, 0, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(13, 'Terminal Leave', 'TERMINAL', 'Leave prior to retirement', 0, 0, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00'),
(14, 'Adoption Leave', 'ADOPT', 'Leave for adoption', 30, 0, 1, '2025-11-14 23:00:00', '2025-11-14 23:00:00');

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
(3, 91, 'Manager Staff', 'Handle documents for Engineering Section', '2025-08-07 01:54:45'),
(4, 25, 'Manager Staff', 'Handle documents of Admin and Finance', '2025-08-07 01:55:42'),
(5, 27, 'Manager Staff', 'Public Relations Information', '2025-09-18 00:46:32');

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
(1, 54, 'New Role Assignment', 'You have been assigned as head of section Engineering Section', 'role_change', 0, '2025-08-05 02:59:42', NULL),
(2, 105, 'New Role Assignment', 'You have been assigned as head of section Administrative Section', 'role_change', 0, '2025-08-05 03:01:15', NULL),
(3, 105, 'New Role Assignment', 'You have been assigned as head of section Operation and Maintenance Section', 'role_change', 0, '2025-08-05 03:05:50', NULL),
(4, 45, 'New Role Assignment', 'You have been assigned as head of section Finance Section', 'role_change', 0, '2025-08-05 05:12:31', NULL),
(5, 105, 'New Role Assignment', 'You have been assigned as head of section Operation and Maintenance Section', 'role_change', 0, '2025-08-05 05:16:54', NULL),
(6, 31, 'New Role Assignment', 'You have been assigned as focal person of section Administrative Section', 'role_change', 0, '2025-08-05 06:52:49', NULL),
(7, 31, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2025-08-05 06:54:06', NULL),
(8, 28, 'New Role Assignment', 'You have been assigned as head of unit ADMIN UNIT', 'role_change', 0, '2025-08-05 06:54:06', NULL),
(9, 32, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2025-08-05 07:40:03', NULL),
(10, 31, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2025-08-05 07:40:03', NULL),
(11, 26, 'New Role Assignment', 'You have been assigned as Manager\'s Office Staff', 'role_change', 0, '2025-08-05 07:53:39', NULL),
(12, 26, 'New Role Assignment', 'You have been assigned as Manager\'s Office Staff', 'role_change', 0, '2025-08-05 07:54:32', NULL),
(13, 146, 'Role Change', 'You have been removed from the Manager\'s Office', 'role_change', 0, '2025-08-05 07:59:27', NULL),
(14, 33, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2025-08-06 03:45:51', NULL),
(15, 32, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2025-08-06 03:45:51', NULL),
(16, 31, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2025-08-06 03:45:51', NULL),
(17, 91, 'New Role Assignment', 'You have been assigned as Manager\'s Office Staff', 'role_change', 0, '2025-08-07 01:54:45', NULL),
(18, 25, 'New Role Assignment', 'You have been assigned as Manager\'s Office Staff', 'role_change', 0, '2025-08-07 01:55:42', NULL),
(19, 28, 'New Role Assignment', 'You have been assigned as head of unit ADMIN UNIT', 'role_change', 0, '2025-08-07 03:05:01', NULL),
(20, 42, 'New Role Assignment', 'You have been assigned as head of unit Procurement Unit', 'role_change', 0, '2025-08-07 03:05:46', NULL),
(21, 28, 'New Role Assignment', 'You have been assigned as head of unit ISG UNIT', 'role_change', 0, '2025-08-07 03:06:15', NULL),
(22, 87, 'New Role Assignment', 'You have been assigned as head of unit Construction Unit', 'role_change', 0, '2025-08-07 03:07:10', NULL),
(23, 56, 'New Role Assignment', 'You have been assigned as head of unit PLANNING & DESIGN UNIT', 'role_change', 0, '2025-08-07 03:07:42', NULL),
(24, 52, 'New Role Assignment', 'You have been assigned as head of unit CASHIER UNIT', 'role_change', 0, '2025-08-07 03:08:28', NULL),
(25, 52, 'New Role Assignment', 'You have been assigned as head of unit CASHIER UNIT', 'role_change', 0, '2025-08-07 03:08:58', NULL),
(26, 104, 'New Role Assignment', 'You have been assigned as head of unit OPERATION UNIT', 'role_change', 0, '2025-08-07 03:09:22', NULL),
(27, 132, 'New Role Assignment', 'You have been assigned as head of unit EQUIPMENT UNIT', 'role_change', 0, '2025-08-07 03:09:41', NULL),
(28, 121, 'New Role Assignment', 'You have been assigned as head of unit INSTITUTIONAL DEVELOPMENT UNIT', 'role_change', 0, '2025-08-07 03:10:04', NULL),
(29, 32, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2025-08-07 03:34:14', NULL),
(30, 42, 'New Role Assignment', 'You have been assigned as head of unit Budget Unit', 'role_change', 0, '2025-08-08 04:03:05', NULL),
(31, 42, 'New Role Assignment', 'You have been assigned as head of unit Procurement Unit', 'role_change', 0, '2025-08-08 04:12:38', NULL),
(33, 28, 'New Role Assignment', 'You have been assigned as head of unit ADMIN UNIT', 'role_change', 0, '2025-08-08 06:25:49', NULL),
(34, 92, 'New Role Assignment', 'You have been assigned as head of unit a', 'role_change', 0, '2025-08-08 06:34:24', NULL),
(35, 28, 'New Role Assignment', 'You have been assigned as head of unit ADMIN UNIT', 'role_change', 0, '2025-08-08 06:35:23', NULL),
(36, 28, 'New Role Assignment', 'You have been assigned as head of unit ADMIN UNIT', 'role_change', 0, '2025-08-08 06:54:29', NULL),
(37, 132, 'New Role Assignment', 'You have been assigned as head of unit EQUIPMENT UNIT', 'role_change', 0, '2025-08-15 08:02:14', NULL),
(38, 27, 'New Role Assignment', 'You have been assigned as Manager\'s Office Staff', 'role_change', 0, '2025-09-18 00:46:32', NULL),
(39, 121, 'New Role Assignment', 'You have been assigned as head of unit INSTITUTIONAL DEVELOPMENT UNIT', 'role_change', 0, '2025-10-03 05:10:00', NULL),
(40, 32, 'New Assignment', 'You have been assigned to unit ADMIN UNIT', '', 0, '2025-10-03 05:10:29', NULL),
(41, 87, 'New Role Assignment', 'You have been assigned as head of unit CONSTRUCTION UNIT', 'role_change', 0, '2025-10-03 05:39:04', NULL),
(42, 52, 'New Role Assignment', 'You have been assigned as head of unit CASHIER UNIT', 'role_change', 0, '2025-10-03 05:39:23', NULL),
(43, 104, 'New Role Assignment', 'You have been assigned as head of unit OPERATION UNIT', 'role_change', 0, '2025-10-03 06:33:06', NULL),
(44, 56, 'New Role Assignment', 'You have been assigned as head of unit PLANNING & DESIGN UNIT', 'role_change', 0, '2025-10-11 01:23:12', NULL),
(45, 98, 'New Assignment', 'You have been assigned to unit CONSTRUCTION UNIT', '', 0, '2025-10-12 05:29:49', NULL),
(46, 52, 'New Role Assignment', 'You have been assigned as head of section Finance Section', 'role_change', 0, '2025-11-05 03:42:09', NULL);

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
(24, 31, '73cd50383b5d0b712d9c790f277461656e492f2d4ef354ca322f4065c02b3de2', '2025-10-20 09:44:36', 'approved', '2025-10-20 14:44:36', 32, '2025-10-20 14:44:43', NULL);

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
(40, 'check_service_request', 'Check service request per employee', '2025-10-12 05:15:58'),
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
(24, 100, '2025-10-21', '15:47:00', 'personal', 'asdasda', '16:47:00', 0, 'rejected', 32, '2025-10-27 11:47:48', '2025-10-21 07:48:02', '2025-10-27 03:47:48'),
(29, 160, '2025-10-22', '09:00:00', 'official', 'Go to notary for notarization of docs', '12:00:00', 0, 'approved', 32, '2025-10-22 08:58:21', '2025-10-22 00:58:09', '2025-10-22 00:58:21'),
(30, 160, '2025-10-22', '15:00:00', 'official', 'Go to Post Office ', '17:00:00', 0, 'approved', 32, '2025-10-22 15:19:29', '2025-10-22 07:19:25', '2025-10-22 07:19:29'),
(31, 32, '2025-11-03', '14:00:00', 'personal', 'Go to Landback', '15:00:00', 0, 'approved', 32, '2025-11-03 13:47:58', '2025-11-03 05:47:51', '2025-11-03 05:47:58'),
(32, 40, '2025-11-04', '08:18:00', 'official', 'Go to aleco', '17:18:00', 0, 'approved', 32, '2025-11-04 08:18:25', '2025-11-04 00:18:22', '2025-11-04 00:18:25'),
(33, 53, '2025-11-05', '14:00:00', 'official', 'Go to Landbank to deposit cash', '15:00:00', 0, 'approved', 32, '2025-11-05 11:41:18', '2025-11-05 03:41:12', '2025-11-05 03:41:18'),
(34, 160, '2025-11-07', '13:00:00', 'official', 'to pay utility bills (ALECO, PNB, Water District)', '17:00:00', 0, 'approved', 31, '2025-11-07 12:09:35', '2025-11-07 04:09:25', '2025-11-07 04:09:35');

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
(45, 'Super Engineer', '2025-10-18 03:04:13', '2025-10-18 03:04:13');

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
(11, '1st Project', '1', '1st', 32, '2025-11-04 05:31:42', '2025-11-04 05:31:42', 'active', '2025-11-01', '2025-11-04', '#000000');

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
(45, 10, 'Backlog', 'Tasks that are planned but not yet started', 1, '#6B7280', '2025-11-03 08:51:42', '2025-11-03 08:51:42'),
(46, 10, 'To Do', 'Tasks ready to be worked on', 2, '#3B82F6', '2025-11-03 08:51:42', '2025-11-03 08:51:42'),
(47, 10, 'In Progress', 'Tasks currently being worked on', 3, '#F59E0B', '2025-11-03 08:51:42', '2025-11-03 08:51:42'),
(48, 10, 'Review', 'Tasks awaiting review', 4, '#8B5CF6', '2025-11-03 08:51:42', '2025-11-03 08:51:42'),
(49, 10, 'Done', 'Completed tasks', 5, '#10B981', '2025-11-03 08:51:42', '2025-11-03 08:51:42'),
(50, 10, '1', '1', 6, '#007bff', '2025-11-04 02:32:23', '2025-11-04 02:32:23'),
(51, 11, 'Backlog', 'Tasks that are planned but not yet started', 1, '#6B7280', '2025-11-04 05:31:42', '2025-11-04 05:31:42'),
(52, 11, 'To Do', 'Tasks ready to be worked on', 2, '#3B82F6', '2025-11-04 05:31:42', '2025-11-04 05:31:42'),
(53, 11, 'In Progress', 'Tasks currently being worked on', 3, '#F59E0B', '2025-11-04 05:31:42', '2025-11-04 05:31:42'),
(54, 11, 'Review', 'Tasks awaiting review', 4, '#8B5CF6', '2025-11-04 05:31:42', '2025-11-04 05:31:42'),
(55, 11, 'Done', 'Completed tasks', 5, '#10B981', '2025-11-04 05:31:42', '2025-11-04 05:31:42');

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
(8, 11, 32, 'owner', 32, '2025-11-04 05:31:42');

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
(1, 40),
(1, 41),
(2, 3),
(2, 5),
(2, 15),
(2, 17),
(2, 31),
(3, 3),
(3, 15),
(3, 17),
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
(11, 32, 'Marc David  OROGO', 'Administrative Section', '2025-10-11', 'approved', '2025-10-11 02:20:55', '2025-10-20 07:21:27');

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
(30, 11, 'MOUSE', 'USB connection type', 'PCS', 11, 'approved');

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
(1, 'Admin Dashboard', 'Main administrative dashboard and overview', 0, '2025-10-11 14:07:08', '2025-11-07 11:11:48'),
(2, 'Attachment Monitoring', 'Monitor and process document attachments', 0, '2025-10-11 14:07:08', '2025-10-18 12:40:56'),
(3, 'Calendar System', 'Company calendar and event management', 0, '2025-10-11 14:07:08', '2025-10-11 14:09:42'),
(4, 'Employee Management', 'Manage employee records and information', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(5, 'Employee Creation', 'Create new employee profiles', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(6, 'Employee Directory', 'View and manage employee list', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(7, 'Module Maintenance', 'System module maintenance control', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(8, 'Content Management', 'Manage website content and information', 0, '2025-10-11 14:07:08', '2025-10-11 14:07:08'),
(9, 'Appointment Settings', 'Configure appointment status and types', 0, '2025-10-11 14:07:08', '2025-11-07 11:11:57'),
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
(8, 10, 47, 'ASADA', 'ADASD', '', 'low', 'urgent', '2025-11-08', 32, 32, '2025-11-03 08:52:09', '2025-11-04 02:47:13', 0),
(9, 10, 50, '1212', '212', '', 'medium', 'review', '2025-11-04', 32, 32, '2025-11-04 01:15:17', '2025-11-04 02:32:29', 0),
(11, 11, 53, '1', '1', '', 'high', 'development', '2025-11-20', 32, 32, '2025-11-04 05:32:15', '2025-11-04 05:32:29', 0);

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
(52, 3, 'PLANNING & DESIGN UNIT', 'PDU', 56);

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
(21, 'lfp', '$2y$10$W27PHNGg/7ks5D0jWIN.M.WLmKJv.cicm/gvJXLjOXMkTHzn34lwy', 54, 12),
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
(39, 'pat', '$2y$10$49gwaRmmGXGHLXGpwi/kTO6.pbVZN6/BOMb.j4awMaJCtJHq8ibAi', 64, 1);

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
(1, 32, 1, '2025-11-07 05:30:36'),
(4984, 27, 1, '2025-11-04 06:11:37'),
(9161, 31, 1, '2025-11-07 05:30:45'),
(61299, 100, 0, '2025-10-28 06:26:31'),
(61608, 85, 0, '2025-10-13 00:38:22'),
(61969, 23, 0, '2025-10-12 07:05:15'),
(62442, 59, 0, '2025-10-18 03:53:46'),
(62977, 56, 0, '2025-10-20 07:18:14'),
(63127, 54, 0, '2025-10-20 07:18:14'),
(72181, 26, 1, '2025-10-30 08:34:26'),
(79043, 55, 0, '2025-10-28 06:26:31');

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
  ADD KEY `created_at` (`created_at`);

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
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`doc_id`),
  ADD UNIQUE KEY `doc_number` (`doc_number`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `document_actions`
--
ALTER TABLE `document_actions`
  ADD PRIMARY KEY (`action_id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `document_comments`
--
ALTER TABLE `document_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `fk_parent_comment` (`parent_id`);

--
-- Indexes for table `document_history`
--
ALTER TABLE `document_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `document_monitoring`
--
ALTER TABLE `document_monitoring`
  ADD PRIMARY KEY (`document_id`),
  ADD UNIQUE KEY `tracking_no` (`tracking_no`),
  ADD KEY `to_section_id` (`to_section_id`),
  ADD KEY `received_by` (`received_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `from_emp_id` (`from_emp_id`);

--
-- Indexes for table `document_status`
--
ALTER TABLE `document_status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `document_transfers`
--
ALTER TABLE `document_transfers`
  ADD PRIMARY KEY (`transfer_id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `from_emp_id` (`from_emp_id`),
  ADD KEY `to_section_id` (`to_section_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `document_types`
--
ALTER TABLE `document_types`
  ADD PRIMARY KEY (`type_id`);

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
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`balance_id`),
  ADD UNIQUE KEY `emp_leave_year` (`emp_id`,`leave_type_id`,`year`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_balance_logs`
--
ALTER TABLE `leave_balance_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `adjusted_by` (`adjusted_by`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `emp_id` (`emp_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `section_head_id` (`section_head_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`leave_type_id`),
  ADD UNIQUE KEY `leave_code` (`leave_code`);

--
-- Indexes for table `managers_office_staff`
--
ALTER TABLE `managers_office_staff`
  ADD PRIMARY KEY (`id`),
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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=197;

--
-- AUTO_INCREMENT for table `appointment_status`
--
ALTER TABLE `appointment_status`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `attachments_monitoring`
--
ALTER TABLE `attachments_monitoring`
  MODIFY `monitoring_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `chat_room_participants`
--
ALTER TABLE `chat_room_participants`
  MODIFY `participant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `comment_likes`
--
ALTER TABLE `comment_likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `company_forms`
--
ALTER TABLE `company_forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `document_actions`
--
ALTER TABLE `document_actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `document_comments`
--
ALTER TABLE `document_comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `document_history`
--
ALTER TABLE `document_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=226;

--
-- AUTO_INCREMENT for table `document_monitoring`
--
ALTER TABLE `document_monitoring`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `document_status`
--
ALTER TABLE `document_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `document_transfers`
--
ALTER TABLE `document_transfers`
  MODIFY `transfer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `document_types`
--
ALTER TABLE `document_types`
  MODIFY `type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `file_activity_logs`
--
ALTER TABLE `file_activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `folders`
--
ALTER TABLE `folders`
  MODIFY `folder_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `folder_access`
--
ALTER TABLE `folder_access`
  MODIFY `access_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `folder_activity_logs`
--
ALTER TABLE `folder_activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `folder_shares`
--
ALTER TABLE `folder_shares`
  MODIFY `share_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ia_profiles`
--
ALTER TABLE `ia_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `ia_profile_history`
--
ALTER TABLE `ia_profile_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `leave_balance_logs`
--
ALTER TABLE `leave_balance_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `leave_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `managers_office_staff`
--
ALTER TABLE `managers_office_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `office`
--
ALTER TABLE `office`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `personal_locator_slips`
--
ALTER TABLE `personal_locator_slips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `position`
--
ALTER TABLE `position`
  MODIFY `position_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `project_boards`
--
ALTER TABLE `project_boards`
  MODIFY `board_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `project_members`
--
ALTER TABLE `project_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `provinces`
--
ALTER TABLE `provinces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
-- AUTO_INCREMENT for table `section`
--
ALTER TABLE `section`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `section_secretaries`
--
ALTER TABLE `section_secretaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `supply_request_items`
--
ALTER TABLE `supply_request_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `system_modules`
--
ALTER TABLE `system_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `unit_section`
--
ALTER TABLE `unit_section`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `user_online_status`
--
ALTER TABLE `user_online_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87682;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `document_types` (`type_id`),
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`owner_id`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `document_actions`
--
ALTER TABLE `document_actions`
  ADD CONSTRAINT `document_actions_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `document_monitoring` (`document_id`),
  ADD CONSTRAINT `document_actions_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `document_comments`
--
ALTER TABLE `document_comments`
  ADD CONSTRAINT `document_comments_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`doc_id`),
  ADD CONSTRAINT `document_comments_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `fk_parent_comment` FOREIGN KEY (`parent_id`) REFERENCES `document_comments` (`comment_id`) ON DELETE CASCADE;

--
-- Constraints for table `document_history`
--
ALTER TABLE `document_history`
  ADD CONSTRAINT `document_history_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`doc_id`),
  ADD CONSTRAINT `document_history_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `document_monitoring`
--
ALTER TABLE `document_monitoring`
  ADD CONSTRAINT `document_monitoring_ibfk_1` FOREIGN KEY (`from_emp_id`) REFERENCES `employee` (`emp_id`);

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
-- Constraints for table `items`
--
ALTER TABLE `items`
  ADD CONSTRAINT `items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD CONSTRAINT `leave_balances_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `leave_balances_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`);

--
-- Constraints for table `leave_balance_logs`
--
ALTER TABLE `leave_balance_logs`
  ADD CONSTRAINT `leave_balance_logs_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `leave_balance_logs_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`),
  ADD CONSTRAINT `leave_balance_logs_ibfk_3` FOREIGN KEY (`adjusted_by`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`),
  ADD CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `leave_requests_ibfk_4` FOREIGN KEY (`section_head_id`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `leave_requests_ibfk_5` FOREIGN KEY (`manager_id`) REFERENCES `employee` (`emp_id`),
  ADD CONSTRAINT `leave_requests_ibfk_6` FOREIGN KEY (`approved_by`) REFERENCES `employee` (`emp_id`);

--
-- Constraints for table `managers_office_staff`
--
ALTER TABLE `managers_office_staff`
  ADD CONSTRAINT `managers_office_staff_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
