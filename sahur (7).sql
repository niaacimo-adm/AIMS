-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 30, 2025 at 10:02 AM
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
  `is_manager` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee`
--

INSERT INTO `employee` (`emp_id`, `picture`, `id_number`, `first_name`, `middle_name`, `last_name`, `ext_name`, `gender`, `address`, `bday`, `email`, `phone_number`, `employment_status_id`, `appointment_status_id`, `section_id`, `office_id`, `position_id`, `unit_section_id`, `is_manager`) VALUES
(23, '6860d112a681b_Sircloyd.png', '596165', 'Mark Cloyd ', 'G.', 'SO', '', 'Male', 'cam norte', '2000-06-26', 'email@example.com', '555-1002', 1, 10, NULL, 1, 3, NULL, 1),
(24, '683e6ef11e368_2020-nia-logo.png', '104282', 'Mark', 'L', 'SALEM', '', 'Male', 'Albay', '1991-12-03', 'email@example.com', '555-1001', 1, 5, 4, 1, 23, NULL, 0),
(25, '6860d355ae142_CREDO, P.png', '692846', 'Patricia Gillyn', 'L', 'CREDO', '', 'Female', 'Camsur', '2000-01-01', 'email@example.com', '09123456789', 1, 5, NULL, 1, 1, NULL, 0),
(26, '6860ccfe1fd0c_30. ELLA N. RINGAD.JPG', '921488', 'Ella ', 'N', 'RINGAD', '', 'Female', 'Ligao', '1998-09-17', 'email@example.com', '987897', 1, 5, NULL, 1, 1, NULL, 0),
(27, '6860d369e1eca_PEÑAFLOR, J.png', '785273', 'Jessica', 'V', 'PEÑAFLOR', '', 'Male', 'camsur', '2001-09-10', 'email@example.com', '09654800074', 1, 7, NULL, 1, 27, NULL, 0),
(28, '6860e76730f25_10. MYRA M. ETCOBANEZ (B).JPG', '406321', 'Myra', 'M', 'ETCOBANEZ', '', 'Male', 'Camalig', '2000-01-01', 'email@example.com', '987898', 1, 5, 1, 1, 28, 46, 0),
(29, '', '705491', 'Amy B.', '', 'CALPE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987899', 1, 5, 1, 1, 1, NULL, 0),
(30, '', '970465', 'Richard S.', '', 'NACARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987900', 1, 5, 1, 1, 1, NULL, 0),
(31, '6860d1d88d7e2_BONAPOS, R.png', '677630', 'Reese', 'P.', 'BONAPOS', '', 'Female', 'Ligao', '2000-01-01', 'email@example.com', '987901', 1, 5, 1, 1, 1, NULL, 0),
(32, '6860cd253ebac_OROGO, MARC DAVID.png', '616630', 'Marc David ', 'O', 'OROGO', '', 'Male', 'Guinobatan', '1996-08-27', 'email@example.com', '987902', 1, 7, 1, 1, 37, NULL, 0),
(33, '', '847101', 'Diana Rose P.', '', 'PAGAL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987903', 1, 5, 1, 1, 1, NULL, 0),
(34, '', '472890', 'Senen A.', '', 'BALONDO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987904', 1, 5, 1, 1, 1, NULL, 0),
(35, '', '578550', 'Jojo O.', '', 'PAJE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987905', 1, 5, 1, 1, 1, NULL, 0),
(36, '', '706109', 'Marcos B.', '', 'BALITA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987906', 1, 5, 1, 1, 1, NULL, 0),
(37, '', '413637', 'Dante A.', '', 'SAN BUENAVENTURA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987907', 1, 5, 1, 1, 30, NULL, 0),
(38, '', '196770', 'Isagani C.', '', 'CULLAT', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987908', 1, 7, 1, 1, 32, NULL, 0),
(39, '', '892078', 'Bryann Frederick R.', '', 'DINGLASAN', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987909', 1, 5, 1, 1, 1, NULL, 0),
(40, '', '853739', 'Christian Levy Jr. B.', '', 'LONTAC', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987910', 1, 5, 1, 1, 1, NULL, 0),
(41, '', '529551', 'Nando M.', '', 'NAYVE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987911', 1, 5, 1, 1, 1, NULL, 0),
(42, '6860e78f75c9a_27. LOUIE DEDASE.JPG', '411215', 'Luisito ', 'O', 'DEDASE', '', 'Male', 'Camsur', '2000-01-01', 'email@example.com', '987912', 1, 5, 1, 1, 31, NULL, 0),
(43, '', '990269', 'Ela Mae S.', '', 'ABILA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987913', 1, 5, 1, 1, 1, NULL, 0),
(44, '', '515714', 'Mark Charl\'s N.', '', 'AZUTEA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987914', 1, 5, 1, 1, 1, NULL, 0),
(45, '6860d0aa6019a_PITALLANO, V.png', '771459', 'Vida ', 'E', 'PITALLANO', '', 'Female', 'Naga City', '2000-01-01', 'email@example.com', '987915', 1, 5, 2, 1, 1, NULL, 0),
(46, '6860d15966f5d_21. ALEXANDRA JOY M. DELGADO.JPG', '489240', 'Alexandra Joy ', 'M', 'DELGADO', '', 'Female', 'legazpi', '2000-01-01', 'email@example.com', '987916', 1, 5, 1, 1, 1, NULL, 0),
(47, '', '199052', 'Ma. Cristina R.', '', 'ALVAREZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987917', 1, 7, 1, 1, 10, NULL, 0),
(48, '', '416828', 'Darlene Mae ', 'C', 'MAYOR', '', 'Male', 'cam sur', '2000-05-01', 'email@example.com', '987918', 1, 7, 2, 1, 9, NULL, 0),
(49, '', '862342', 'John Paul R.', '', 'PAPA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987919', 1, 5, 1, 1, 1, NULL, 0),
(50, '', '861143', 'Maria Beatrice', '', 'ROBAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987920', 1, 5, 1, 1, 1, NULL, 0),
(51, '', '282537', 'April Jane B.', '', 'RODA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987921', 1, 7, 2, 1, 13, NULL, 0),
(52, '', '929272', 'Vilma M.', '', 'MANLANGIT', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987922', 1, 5, 2, 1, 1, NULL, 0),
(53, '', '243192 ', 'Rejean L.', '', 'MARIÑAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987923', 1, 7, 2, 1, 11, NULL, 0),
(54, '6860d08955558_32. LECH FIDEL PANTE.JPG', '164959', 'Lech Fidel', 'C', 'PANTE', '', 'Male', 'Naga City', '2000-01-01', 'email@example.com', '987924', 1, 10, 3, 1, 1, NULL, 0),
(55, '6860d19789058_JUAREZ, JA.png', '936322', 'Julie Anne ', 'D', 'Juarez', '', 'Female', 'Ligao', '0001-12-01', 'email@example.com', '987925', 1, 5, 1, 1, 1, NULL, 0),
(56, '6860e7ddb8672_35. JESSICA B. COMPLETO.JPG', '488433', 'Jessica ', 'B', 'COMPLETO', '', 'Female', 'Tabaco', '2000-01-01', 'email@example.com', '987926', 1, 5, 3, 1, 1, NULL, 0),
(57, '', '568533', 'Jane Amy R. ', '', 'SARION', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987927', 1, 5, 1, 1, 1, NULL, 0),
(58, '', '505503', 'Roland O.', '', 'CLARIÑO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987928', 1, 5, 1, 1, 1, NULL, 0),
(59, '', '655711', 'Walter P.', '', 'EUSEBIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987929', 1, 5, 1, 1, 1, NULL, 0),
(60, '', '331046', 'Joel O.', '', 'OLAVIAGA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987930', 1, 5, 3, 1, 15, NULL, 0),
(61, '', '300179 ', 'Richard R.', '', 'RESENTES', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987931', 1, 5, 3, 1, 19, NULL, 0),
(62, '', '983758', 'Raymond Gil C.', '', 'AYCARDO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987932', 1, 5, 1, 1, 1, NULL, 0),
(63, '', '453727', 'Arnulfo Natividad B.', '', 'BANGA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987933', 1, 5, 1, 1, 1, NULL, 0),
(64, '', '864981', 'John Patrick B.', '', 'CABILES', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987934', 1, 5, 1, 1, 1, NULL, 0),
(65, '', '298598', 'Hendryx D.', '', 'CAPINO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987935', 1, 7, 3, 1, 2, NULL, 0),
(66, '', '778342', 'Don R.', '', 'CONCEPCION', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987936', 1, 5, 1, 1, 1, NULL, 0),
(67, '', '398357', 'Frederick V.', '', 'DAGUMBOY', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987937', 1, 7, 3, 1, 42, NULL, 0),
(68, '', '556790', 'Froilan S.', '', 'GESTIADA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987938', 1, 5, 1, 1, 1, NULL, 0),
(69, '', '466181', 'Ronald A.', '', 'LLEVA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987939', 1, 5, 1, 1, 1, NULL, 0),
(70, '', '746795', 'Joseph', '', 'MORAL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987940', 1, 5, 1, 1, 1, NULL, 0),
(71, '', '865161', 'Chlowell Ferby B.', '', 'NASOL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987941', 1, 5, 1, 1, 1, NULL, 0),
(72, '', '522191', 'Mark Renen Q.', '', 'NAVARRO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987942', 1, 5, 1, 1, 1, NULL, 0),
(73, '', '587562', 'Gregory Mark', '', 'OCAMPO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987943', 1, 5, 1, 1, 1, NULL, 0),
(74, '', '719165', 'Eduardo J.', '', 'PELIGAN', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987944', 1, 5, 1, 1, 1, NULL, 0),
(75, '', '177646', 'Sammy P.', '', 'PELIGAN', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987945', 1, 7, 1, 1, 42, NULL, 0),
(76, '', '382370', 'Raymond B.', '', 'PEPAÑO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987946', 1, 7, 3, 1, 15, NULL, 0),
(77, '', '570364', 'Haji P.', '', 'POLIDARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987947', 1, 5, 1, 1, 1, NULL, 0),
(78, '', '570984', 'Rizaldy P.', '', 'POLIDARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987948', 1, 5, 1, 1, 1, NULL, 0),
(79, '', '200580', 'Luisito P.', '', 'PROPOGO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987949', 1, 7, 3, 1, 19, NULL, 0),
(80, '686cb444b336a_SALIRE, MD.png', '359326', 'Mac Daryll ', 'c.', 'SALIRE', '', 'Male', 'OAS', '2000-05-22', 'email@example.com', '9305224889', 5, 7, NULL, 1, 15, NULL, 0),
(81, '', '705059', 'Donel P.', '', 'VIBAR', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987951', 1, 5, 1, 1, 1, NULL, 0),
(82, '6860e7fc7f05e_60. JORDAN P. RONCESVALLES.JPG', '740531', 'Jordan ', 'P', 'RONCESVALLES', '', 'Male', 'oas', '2000-01-01', 'email@example.com', '987952', 1, 5, 1, 1, 1, NULL, 0),
(83, '6878ab1175327_38. REGINE RENON.jpg', '429389', 'Regine ', 'Chavez', 'RENON', '', 'Female', 'Ligao', '1990-03-23', 'email@example.com', '987953', 1, 5, 3, 1, 18, NULL, 0),
(84, '6878a957c38ec_39. ALEXANDRA MAE L. DELA CRUZ.JPG', '905404', 'Alexandra Mae', 'Lozada', 'DELA CRUZ', '', 'Female', 'San Jose, Pili, Camarines Sur', '1997-07-05', 'alexandramaedelacruz97@gmail.com', '09121341758', 1, 5, 3, 1, 16, NULL, 0),
(85, '', '134245', 'Jevielyn A.', '', 'DOLZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987955', 1, 7, 3, 1, 2, NULL, 0),
(86, '', '318147', 'Harrish O.', '', 'MATOCINOS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987956', 1, 7, 3, 1, 43, NULL, 0),
(87, '6860e7bda4197_56. ROSEMARIE A. PARAISO.JPG', '938818', ' Rosemarie', 'A', 'PARAISO  ', '', 'Female', 'Naga CIty', '2000-01-01', 'email@example.com', '987957', 1, 5, 3, 1, 1, NULL, 0),
(88, '', '135180', 'Gilbert S.', '', 'ARABACA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987958', 1, 5, 3, 1, 14, NULL, 0),
(89, '', '250817', 'Rey B.', '', 'LANUZO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987959', 1, 5, 3, 1, 16, NULL, 0),
(90, '', '935951 ', 'Noel B.', '', 'NASH', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987960', 1, 5, 1, 1, 1, NULL, 0),
(91, '6860d38da2eea_57. COLEEN M. RASTRULLO -2.JPG', '648335', 'Coleen', 'M.', 'RASTRULLO', '', 'Female', 'poland', '2000-01-01', 'email@example.com', '987961', 1, 5, 1, 1, 1, NULL, 0),
(92, '', '956528', ' Don A.', '', 'REBADAJO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987962', 1, 5, 1, 1, 1, NULL, 0),
(93, '', '500213', 'Lyle Kenneth A.', '', 'CALABINES', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987963', 1, 5, 1, 1, 1, NULL, 0),
(94, '', '660100', 'Jennimel R.', '', 'DAYUPAY', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987964', 1, 5, 1, 1, 1, NULL, 0),
(95, '', '136841 ', 'Francisco Jr. B.', '', 'JUAREZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987965', 1, 7, 3, 1, 16, NULL, 0),
(96, '', '194697', 'Gio Dominick M.', '', 'MANLANGIT', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987966', 1, 7, 3, 1, 41, NULL, 0),
(97, '', '333794', 'Mark Christian R.', '', 'MARBELLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987967', 1, 7, 3, 1, 41, NULL, 0),
(98, '68467ce6d462d_sahur.png', '255154', ' Loewe Mae', 'B.', 'OLIVERA', '', 'Female', 'Guinobatan', '1997-05-29', 'email@example.com', '987968', 1, 7, 3, 1, 16, NULL, 0),
(99, '', '435914', 'Noel Jr. B.', '', 'ORAYE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987969', 1, 5, 1, 1, 1, NULL, 0),
(100, '', '587844', ' Crisna', '', 'PETILLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987970', 1, 5, 1, 1, 1, NULL, 0),
(101, '', '992299', 'Armando S.', '', 'PORTUGUEZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987971', 1, 5, 1, 1, 1, NULL, 0),
(102, '6870a1d6797fe_SIGUENZA, J.png', '710547', ' Jewel', 'A', 'SIGUENZA', '', 'Female', 'camsur', '2001-01-05', 'email@example.com', '987972', 1, 5, 1, 1, 1, NULL, 0),
(103, '', '608305 ', 'Bernardita P. ', '', 'BALINGASA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987973', 1, 5, 1, 1, 1, NULL, 0),
(104, '', '287556', 'Ma. Dolores S. ', '', 'BELGADO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987974', 1, 5, 4, 1, 3, NULL, 0),
(105, '6860d06a73b61_68. IAN FELICIANO III BERDIN.JPG', '587812', 'Ian Feliciano', 'P', 'BERDIN', 'III', 'Male', 'Camalig', '2000-01-01', 'email@example.com', '987975', 1, 10, 1, 1, 1, NULL, 0),
(106, '', '817959', 'John  Bernard S.', '', 'NACARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987976', 1, 5, 1, 1, 1, NULL, 0),
(107, '', '669920 ', 'Dale Derick L.', '', 'DETERA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987977', 1, 5, 1, 1, 1, NULL, 0),
(108, '', '620998', 'Ramon Jr. C.', '', 'AYDALLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987978', 1, 5, 1, 1, 1, NULL, 0),
(109, '', '659463', 'Marvin A.', '', 'MESTIOLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987979', 1, 5, 1, 1, 1, NULL, 0),
(110, '', '524286', 'Gerald M.', '', 'NAVARRO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987980', 1, 5, 1, 1, 1, NULL, 0),
(111, '', '136340', 'Sammy M.', '', 'OLI', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987981', 1, 7, 4, 1, 15, NULL, 0),
(112, '', '534860', 'Von Jayvee A.', '', 'PERALTA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987982', 1, 5, 1, 1, 1, NULL, 0),
(113, '', '259436', 'Francis B.', '', 'ARCILLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987983', 1, 7, 4, 1, 42, NULL, 0),
(114, '', '983424', 'Jay Ar P.', '', 'ATANANTE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987984', 1, 5, 1, 1, 1, NULL, 0),
(115, '', '837811', 'Jerwin P.', '', 'BOMBITA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987985', 1, 5, 1, 1, 1, NULL, 0),
(116, '', '595261', 'Ulysses ', '', 'GUADALUPE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987986', 1, 5, 1, 1, 1, NULL, 0),
(117, '', '323604', 'Ramon A.', '', 'RAMOS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987987', 1, 7, 4, 1, 42, NULL, 0),
(118, '', '636146', 'Raynald R.', '', 'RAÑOLA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987988', 1, 5, 1, 1, 1, NULL, 0),
(119, '', '793242', 'Cesar M.', '', 'REORA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987989', 1, 5, 1, 1, 1, NULL, 0),
(120, '', '740109', 'Conchita R.', '', 'REYES', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987990', 1, 5, 1, 1, 1, NULL, 0),
(121, '689162a174f31_96. MODESTO HARLEY S. NATE.jpg', '232531', 'Modesto Harley ', 'S', 'NATE', '', 'Male', 'ligao', '2000-01-01', 'email@example.com', '987991', 1, 5, 4, 1, 25, NULL, 0),
(122, '', '447264', 'Milany B.', '', 'DACILLO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987992', 1, 5, 1, 1, 1, NULL, 0),
(123, '', '478643', 'Elizabeth J.', '', 'JACOB', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987993', 1, 5, 1, 1, 1, NULL, 0),
(124, '', '429064', 'Jemar B.', '', 'PEÑAFLOR', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987994', 1, 5, 1, 1, 1, NULL, 0),
(125, '', '432411 ', 'Carlito C.', '', 'PONGPONG', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987995', 1, 5, 1, 1, 1, NULL, 0),
(126, '', '168325', 'Segfrido A.', '', 'PONTILLAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987996', 1, 5, 4, 1, 20, NULL, 0),
(127, '6860d2123f6dd_103. MARILOU P. ANGUSTIA.JPG', '187727', 'Marilou', 'A', 'ROBLEDO', '', 'Female', 'Ligao', '2000-01-01', 'email@example.com', '987997', 1, 5, 4, 1, 20, NULL, 0),
(128, '', '983454', 'Aliza May D.', '', 'BALINGASA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987998', 1, 5, 1, 1, 1, NULL, 0),
(129, '', '107996', 'Jhedson S.', '', 'CELLANO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '987999', 1, 7, 4, 1, 39, NULL, 0),
(130, '', '208506', 'Gail Nicole J.', '', 'JACOB', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988000', 1, 7, 4, 1, 39, NULL, 0),
(131, '', '569250', ' Jun Shane M.', '', 'PEÑAFIEL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988001', 1, 5, 1, 1, 1, NULL, 0),
(132, '689162cc35a98_85. ALDRIN P. FRANCIA.JPG', '751102', 'Aldrin', 'P', 'FRANCIA', '', 'Male', 'naga', '2000-01-01', 'email@example.com', '988002', 1, 5, 4, 1, 1, 47, 0),
(133, '', '722450', 'Nestor S.', '', 'REODIQUE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988003', 1, 5, 1, 1, 1, NULL, 0),
(134, '', '521215', 'Salvador Jr. H.', '', 'AGRIPA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988004', 1, 5, 1, 1, 1, NULL, 0),
(135, '', '348180', 'Alexander E.', '', 'RULL', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988005', 1, 5, 4, 1, 23, NULL, 0),
(136, '', '625407', 'Carl Louie B.', '', 'LONTAC', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988006', 1, 5, 1, 1, 1, NULL, 0),
(137, '', '254906', 'John Lloyd M.', '', 'AGRIPA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988007', 1, 7, 4, 1, 19, NULL, 0),
(138, '', '503396', 'Jeric A.', '', 'AMADOS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988008', 1, 5, 1, 1, 1, NULL, 0),
(139, '', '799103', 'Jose Domingo C.', '', 'BERZUELA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988009', 1, 5, 1, 1, 1, NULL, 0),
(140, '', '405192', 'John Kenneth P.', '', 'PAJE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988010', 1, 7, 3, 1, 42, NULL, 0),
(141, '', '775418', 'Patrick Jorge C.', '', 'PANTE.', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988011', 1, 5, 4, 1, 23, NULL, 0),
(142, '', '556604', 'Mark Angelo P.', '', 'POLIDARIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988012', 1, 5, 1, 1, 1, NULL, 0),
(143, '', '614317', ' Albert S.', '', 'RAPOSA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988013', 1, 5, 1, 1, 1, NULL, 0),
(144, '', '296935', ' Kenneth Christopher O.', '', 'REODIQUE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988014', 1, 7, 4, 1, 26, NULL, 0),
(145, '', '979595', 'Raymond R.', '', 'VIÑAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988015', 1, 5, 1, 1, 1, NULL, 0),
(146, '', '723219', 'Angelo V. ', '', 'MARQUEZ', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988016', 1, 5, 1, 1, 1, NULL, 0),
(147, '', '599880', 'Isagani Jr. P.', '', 'ADELANTE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988017', 1, 5, 1, 1, 1, NULL, 0),
(148, '', '910354', 'Elmerio B.', '', 'TENDENILLA,', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988018', 1, 5, 1, 1, 1, NULL, 0),
(149, '', '879431', 'John Learry A.', '', 'BRIOSO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988019', 1, 5, 1, 1, 1, NULL, 0),
(150, '', '578474', 'Jomel M.', '', 'TORIO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988020', 1, 5, 1, 1, 1, NULL, 0),
(151, '', '913614', 'Jomer L.', '', 'BEO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988021', 1, 5, 1, 1, 1, NULL, 0),
(152, '', '135884', 'Joshua A.', '', 'LUMBAO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988022', 1, 7, NULL, 1, 41, NULL, 0),
(153, '', '299888', 'Edilberto Jr. R.', '', 'BEO', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988023', 1, 7, NULL, 1, 39, NULL, 0),
(154, '', '653144', 'Dindo Z.', '', 'MANLANGIT', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988024', 1, 5, 1, 1, 1, NULL, 0),
(155, '', '877653', 'Frederick T.', '', 'MELGAR', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988025', 1, 5, 1, 1, 1, NULL, 0),
(156, '', '545194', 'Mark Anjo T.', '', 'ALNAS', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988026', 1, 5, 1, 1, 1, NULL, 0),
(157, '', '553783', 'Richiel A.', '', 'MASAGCA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988027', 1, 5, 1, 1, 1, NULL, 0),
(158, '', '298653', 'Kayceelyn M.', '', 'TAPIA', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988028', 1, 7, NULL, 1, 41, NULL, 0),
(159, '', '608312', 'Rodolfo Jr. G.', '', 'LLAVE', NULL, 'Male', '', '0000-00-00', 'email@example.com', '988029', 1, 5, 1, 1, 1, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `employee_unit_sections`
--

CREATE TABLE `employee_unit_sections` (
  `emp_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `is_head` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(7, 'asadsfd', 'meeting', '2025-06-06 11:36:00', '0000-00-00 00:00:00', 'aa'),
(8, 'wwwaa', 'event', '2025-06-02 11:36:00', '0000-00-00 00:00:00', 'ww'),
(10, 'Edil Ad Ja', 'holiday', '2025-06-06 00:00:00', '2025-06-06 12:00:00', 'Holiday'),
(11, 'Independence Day', 'holiday', '2025-06-12 00:00:00', '2025-06-12 12:00:00', 'Regular Holiday'),
(12, 'TREE PLANTING', 'event', '2025-07-02 08:00:00', '2025-07-02 17:00:00', 'NIA TREE PLANTING'),
(13, 'NIA ANNIVERSARY CELEBRATION \"FIESTA SA NIA\"', 'event', '2025-07-03 08:00:00', '2025-07-04 17:00:00', 'CELEBRATING NIA ANNIVERSARY');

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
(4, 25, 'Manager Staff', 'Handle documents of Admin and Finance', '2025-08-07 01:55:42');

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
(37, 132, 'New Role Assignment', 'You have been assigned as head of unit EQUIPMENT UNIT', 'role_change', 0, '2025-08-15 08:02:14', NULL);

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
(1, 'NIA-Albay Office', 'Tuburan, Ligao City, Albay', '2025-05-20 03:00:21', '2025-07-24 07:42:01', 23, 1, NULL);

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
(36, 'download_document', 'Download Document', '2025-07-18 06:17:12');

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
(43, 'Draftsman', '2025-06-02 08:43:39', '2025-06-02 08:43:39');

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
(13, 36);

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
(2, 1, 'Finance Section', 'FIN', '2025-05-20 03:03:30', '2025-08-05 05:12:31', 45, NULL),
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
  `time_departure` time NOT NULL,
  `time_return` time NOT NULL,
  `destination` varchar(255) NOT NULL,
  `purpose` text NOT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `driver_emp_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_requests`
--

INSERT INTO `service_requests` (`request_id`, `request_no`, `requesting_emp_id`, `supervisor_emp_id`, `date_requested`, `date_of_travel`, `time_departure`, `time_return`, `destination`, `purpose`, `vehicle_id`, `driver_emp_id`, `status`, `approved_by`, `approved_at`, `remarks`, `created_at`, `updated_at`) VALUES
(162, '001-2025', 28, 23, '2025-08-27', '2025-09-03', '08:00:00', '17:00:00', 'LEGAZPI CITY', 'Training & Seminar ', 3, 24, 'approved', 32, '2025-08-27 13:43:38', NULL, '2025-08-27 03:53:06', '2025-08-27 05:43:38'),
(163, '002-2025', 32, 23, '2025-08-28', '2025-08-29', '08:00:00', '17:00:00', 'Naga City', 'SEMINAR', 2, 135, 'approved', 32, '2025-08-28 08:37:34', NULL, '2025-08-28 00:33:41', '2025-08-28 00:37:34'),
(164, '003-2025', 32, 23, '2025-08-28', '2025-08-29', '08:00:00', '17:00:00', 'LIGAO', 'AAAAAAAAAAAAAAA', 3, 24, 'pending', NULL, NULL, NULL, '2025-08-28 00:39:00', '2025-08-28 00:39:00'),
(165, '004-2025', 32, 23, '2025-08-30', '2025-08-31', '08:00:00', '17:00:00', 'LIGAOasds', 'dasdas', 5, 135, 'approved', 32, '2025-08-30 15:00:43', NULL, '2025-08-30 07:00:29', '2025-08-30 07:00:43');

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
(360, 162, 43, 1, 32, '2025-08-27 13:43:30', 0, NULL, NULL),
(361, 162, 36, 1, 32, '2025-08-27 13:43:33', 0, NULL, NULL),
(362, 162, 129, 1, 32, '2025-08-27 13:43:36', 0, NULL, NULL),
(365, 163, 147, 1, 32, '2025-08-28 08:37:32', 0, NULL, NULL),
(366, 164, 85, 0, NULL, NULL, 0, NULL, NULL),
(367, 164, 140, 0, NULL, NULL, 0, NULL, NULL),
(368, 165, 137, 1, 32, '2025-08-30 15:00:40', 0, NULL, NULL),
(369, 165, 47, 1, 32, '2025-08-30 15:00:34', 0, NULL, NULL),
(370, 165, 67, 1, 32, '2025-08-30 15:00:37', 0, NULL, NULL);

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
(47, 4, 'EQUIPMENT UNIT', 'EQU', 132);

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
(13, 'la', '$2y$10$0033UU0NpZ8qHO9kJtPxbeMa7pPB6jP2M67SMuT4U18Wxlb7iHF0C', 26, 13),
(14, 'mcgs', '$2y$10$N.xkqsb2fKa2c.bZAViS4emlWItrQuVrcVXjvEba68..LAAqU2VGG', 23, 2),
(15, 'jes', '$2y$10$bcyfvOud/Y8vxFG4UCdZautf5ecw7k3OZBCz9lHNk09FZdkAeW8VW', 27, 3),
(16, 'reese', '$2y$10$6P9Z2B6DNsSGoI2NEdBEm.LNIgTWq3G30hS04oKRBgDVkYd5.qk/y', 31, 13),
(19, 'coleen', '$2y$10$KD8cxgGAhNOJPyDNR.YUqOZu.GcAI3/Aht0Qll8Kd06ZVH00suKe2', 91, 13),
(21, 'lfp', '$2y$10$W27PHNGg/7ks5D0jWIN.M.WLmKJv.cicm/gvJXLjOXMkTHzn34lwy', 54, 12),
(22, 'vp', '$2y$10$Prd/Xw.uDy3lH0QKI749xuzA/GsuISJdhI4MVGne8fYB4SqB6k5KC', 45, 12),
(25, 'july', '$2y$10$jLbt3B2mNtPss2ma45tmuenJwVPN.2lgP0oJCZxwp7uKw8GQms.hi', 55, 3),
(26, 'ifb', '$2y$10$UsO0RxHfq.mTq4QiFSkNiuazImygrsBfg9GN104LeMp3/i9qKIugS', 105, 12),
(27, 'jc', '$2y$10$XtE8z15IyUZJ5lN7EgrEa.WrfNoP26cbBAouqVsXPi35aTiZN4yUC', 56, 14),
(28, 'mhsn', '$2y$10$hQB.xPUxyPBeCn/XpnqpgOg6G2NaP/MfErKa1uP2TTt2zFQ5e4ZFW', 121, 14),
(29, 'rp', '$2y$10$840yIXq3HbkODpXHVlrVzek64B85yxWYfGq3rW9RLNWeRdbJqgYE.', 87, 14),
(30, 'apf', '$2y$10$HZhq89HVE0uOjPIxaz29He8gAD8tP8fRCKIvJWmuh4B6RPugrMtcO', 132, 14),
(31, 'mr', '$2y$10$BbdyemPO62T3IwHrhbSrNeXtX8u6QX122/ueKbL/AbqVt43qzbRiC', 127, 3);

-- --------------------------------------------------------

--
-- Table structure for table `user_role`
--

CREATE TABLE `user_role` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_role`
--

INSERT INTO `user_role` (`user_id`, `role_id`) VALUES
(10, 1);

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
(14, 'Unit Head', 'Reports', '2025-07-17 07:53:07');

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
(5, 'N/A1', 'EAS-336', 'SERVICE CAR', 'FORD WHITE LEGEND', 2000, 10, 'available', 1, '2025-08-21 05:43:10', '2025-08-21 05:43:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointment_status`
--
ALTER TABLE `appointment_status`
  ADD PRIMARY KEY (`appointment_id`);

--
-- Indexes for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD PRIMARY KEY (`like_id`),
  ADD UNIQUE KEY `unique_like` (`comment_id`,`emp_id`),
  ADD KEY `emp_id` (`emp_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`doc_id`),
  ADD UNIQUE KEY `doc_number` (`doc_number`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `owner_id` (`owner_id`);

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
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `position`
--
ALTER TABLE `position`
  ADD PRIMARY KEY (`position_id`);

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
-- Indexes for table `user_role`
--
ALTER TABLE `user_role`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

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
-- AUTO_INCREMENT for table `appointment_status`
--
ALTER TABLE `appointment_status`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `comment_likes`
--
ALTER TABLE `comment_likes`
  MODIFY `like_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

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
  MODIFY `emp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `employment_status`
--
ALTER TABLE `employment_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `managers_office_staff`
--
ALTER TABLE `managers_office_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `office`
--
ALTER TABLE `office`
  MODIFY `office_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `position`
--
ALTER TABLE `position`
  MODIFY `position_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

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
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=166;

--
-- AUTO_INCREMENT for table `service_request_passengers`
--
ALTER TABLE `service_request_passengers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=371;

--
-- AUTO_INCREMENT for table `unit_section`
--
ALTER TABLE `unit_section`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `document_comments` (`comment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comment_likes_ibfk_2` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`emp_id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `document_types` (`type_id`),
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`owner_id`) REFERENCES `employee` (`emp_id`);

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
-- Constraints for table `user_role`
--
ALTER TABLE `user_role`
  ADD CONSTRAINT `user_role_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_role_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`office_id`) REFERENCES `office` (`office_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
