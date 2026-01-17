-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2025 at 09:01 PM
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
-- Database: `macj_pest_control`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` time NOT NULL,
  `kind_of_place` varchar(50) NOT NULL,
  `location_address` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `technician_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'assigned',
  `pest_problems` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `client_id`, `client_name`, `email`, `contact_number`, `preferred_date`, `preferred_time`, `kind_of_place`, `location_address`, `notes`, `created_at`, `technician_id`, `status`, `pest_problems`) VALUES
(110, 26, 'Rean Nartea', 'narteareanfredrick@gmail.com', '09202544398', '2025-05-14', '07:00:00', 'House', '29, Bahawan Street, Masambong, San Francisco del Monte, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1115, Philippines', 'as', '2025-05-13 16:56:26', 1, 'completed', 'pALA');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_technicians`
--

CREATE TABLE `appointment_technicians` (
  `appointment_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_technicians`
--

INSERT INTO `appointment_technicians` (`appointment_id`, `technician_id`, `is_primary`) VALUES
(110, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `assessment_report`
--

CREATE TABLE `assessment_report` (
  `report_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `end_time` time NOT NULL,
  `area` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `attachments` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `pest_types` varchar(255) DEFAULT NULL,
  `problem_area` varchar(255) DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `chemical_recommendations` text DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` time DEFAULT NULL,
  `frequency` enum('one-time','weekly','monthly','quarterly') DEFAULT 'one-time',
  `type_of_work` varchar(255) DEFAULT NULL,
  `report_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assessment_report`
--

INSERT INTO `assessment_report` (`report_id`, `appointment_id`, `end_time`, `area`, `notes`, `attachments`, `created_at`, `pest_types`, `problem_area`, `recommendation`, `chemical_recommendations`, `preferred_date`, `preferred_time`, `frequency`, `type_of_work`, `report_date`) VALUES
(86, 110, '19:00:54', 1000.00, 'ss', NULL, '2025-05-13 17:00:54', 'Ants', 'Kicthen', 'ss', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', '2025-05-14', '13:05:00', 'monthly', 'General Pest Control', '2025-05-13 17:00:54');

-- --------------------------------------------------------

--
-- Table structure for table `chemical_assignments`
--

CREATE TABLE `chemical_assignments` (
  `assignment_id` int(11) NOT NULL,
  `chemical_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `job_order_id` int(11) DEFAULT NULL,
  `report_id` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('assigned','used','released') NOT NULL DEFAULT 'assigned'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chemical_inventory`
--

CREATE TABLE `chemical_inventory` (
  `id` int(11) NOT NULL,
  `chemical_name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` enum('Liters','Kilograms','Grams','Pieces') NOT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `safety_info` text DEFAULT NULL,
  `expiration_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) GENERATED ALWAYS AS (case when `quantity` <= 0 then 'Out of Stock' when `quantity` < 10 then 'Low Stock' else 'In Stock' end) STORED,
  `target_pest` varchar(255) DEFAULT NULL,
  `dosage_modifier` decimal(5,2) NOT NULL DEFAULT 1.00 COMMENT 'Modifier for dosage calculation (default: 1.00, meaning 100% of standard dosage)',
  `dilution_rate` decimal(10,2) DEFAULT NULL COMMENT 'Dilution rate in ml per liter',
  `area_coverage` decimal(10,2) DEFAULT 100.00 COMMENT 'Area coverage in square meters per liter of diluted solution'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chemical_inventory`
--

INSERT INTO `chemical_inventory` (`id`, `chemical_name`, `type`, `quantity`, `unit`, `manufacturer`, `supplier`, `description`, `safety_info`, `expiration_date`, `created_at`, `target_pest`, `dosage_modifier`, `dilution_rate`, `area_coverage`) VALUES
(18, 'Fipronil', 'Insecticide', 14.03, 'Liters', 'BASF', 'Pest Control Supplies Inc.', 'Effective against termites and other wood-destroying insects.', 'Use in well-ventilated areas. Avoid contact with skin, eyes, and clothing. Keep away from food and water sources.', '2026-04-27', '2025-04-27 10:41:32', 'Termites', 1.00, NULL, 100.00),
(24, 'Cypermethrin', 'Insecticide', 19.57, 'Liters', 'Cypermethrin', 'Cypermethrin', 'Cypermethrin', 'Cypermethrin', '2025-05-28', '2025-05-09 16:51:05', 'Crawling & Flying Pest', 1.00, NULL, 100.00),
(25, 'Cypermethrin', 'Insecticide', 9.74, 'Liters', 'Cypermethrin', 'Cypermethrin', 'Cypermethrin', 'Cypermethrin', '2025-05-14', '2025-05-10 02:49:29', 'Crawling & Flying Pest', 1.00, NULL, 100.00),
(26, 'Cypermethrin', 'Insecticide', 9.40, 'Liters', '', '', 'Cypermethrin', 'Cypermethrin', '2025-05-13', '2025-05-10 02:50:05', 'Crawling & Flying Pest', 1.00, NULL, 100.00),
(28, 'Cypermethrin', 'Insecticide', 19.52, 'Liters', 'Cypermethrin', 'Cypermethrin', 'Cypermethrin', 'Cypermethrin', '2025-06-10', '2025-05-10 13:47:51', 'Crawling & Flying Pest', 1.00, NULL, 100.00),
(29, 'Cypermethrin', 'Insecticide', 10.00, 'Liters', 'Cypermethrin', 'Cypermethrin', 'Cypermetrin', 'Cypermetrin', '2025-06-10', '2025-05-11 03:00:52', 'Crawling & Flying Pest', 1.00, NULL, 100.00),
(31, 'Imidaclopred', 'Insecticide', 9.09, 'Liters', 'Ginebra', 'Ginebra San Miguel', '12', '12', '2025-06-13', '2025-05-12 19:41:14', 'Crawling & Flying Pest', 1.00, 20.00, 100.00),
(32, 'Imidaclopred', 'Insecticide', 12.00, 'Liters', 'Ginebra', 'Ginebra San Miguel', 'ww', 'ww', '2025-06-13', '2025-05-12 19:42:52', 'Crawling & Flying Pest', 1.00, 20.00, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `chemical_usage_log`
--

CREATE TABLE `chemical_usage_log` (
  `id` int(11) NOT NULL,
  `chemical_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `job_order_id` int(11) NOT NULL,
  `quantity_used` decimal(10,2) NOT NULL,
  `usage_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chemical_usage_log`
--

INSERT INTO `chemical_usage_log` (`id`, `chemical_id`, `technician_id`, `job_order_id`, `quantity_used`, `usage_date`, `notes`, `created_at`) VALUES
(1, 31, 1, 835, 0.05, '2025-05-13', 'Used for job order #835', '2025-05-13 14:53:58'),
(2, 24, 1, 836, 0.05, '2025-05-13', 'Used for job order #836 (Replacement for Imidaclopred)', '2025-05-13 14:55:49'),
(3, 31, 1, 882, 0.04, '2025-05-13', 'Used for job order #882', '2025-05-13 15:13:36'),
(4, 31, 1, 935, 0.20, '2025-05-13', 'Used for job order #935', '2025-05-13 18:52:11');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `location_address` varchar(255) DEFAULT NULL,
  `type_of_place` varchar(50) DEFAULT NULL,
  `location_lat` varchar(20) DEFAULT NULL,
  `location_lng` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`client_id`, `first_name`, `last_name`, `email`, `contact_number`, `password`, `registered_at`, `location_address`, `type_of_place`, `location_lat`, `location_lng`) VALUES
(8, 'Francis', 'Gernan', 'gernan123@gmail.com', '09202544398', '$2y$10$bAZv1VaklaOYBFUcDJhndOwFyv8jpGhoDG338zG3BUv27CVYPRciS', '2025-03-30 11:54:04', NULL, NULL, NULL, NULL),
(9, 'Paul Klarence A.', 'De Guzman', 'deguzman0369@gmail.com', '09690381171', '$2y$10$i0jXOIJ6g9Hh1Y41VEKT0eko798.dsn1qhNCBw7tjkD5o4ydEafci', '2025-04-12 02:44:43', 'Halcon 2 Street, Santa Teresita, Santa Mesa Heights, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1114, Philippines [14.619218994370428,120.99877277997442]', 'Office', '14.619218994370428', '120.99877277997442'),
(10, 'Gorge', 'Cooper', 'Cooper@gmail.com', '123', '$2y$10$gRFu2athyehRtpYMREbUmOwjdYVgmyvlOmCyj7Kq89RezhAd46nl6', '2025-04-14 05:36:32', 'Fishrmall', 'House', NULL, NULL),
(11, 'Klarence', 'De Guzman', 'klarence@yahoo.com', '0957463811', '$2y$10$PpwZMTaGNNEQZgk2/IajN.QXOwFshxrNTviLZapym8rqMpVxG/O1u', '2025-04-16 09:08:58', 'Halcon 2 Street, Santa Teresita, Santa Mesa Heights, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1114, Philippines [14.619897,120.998094]', 'House', '14.619897', '120.998094'),
(12, 'Francis', 'Gernan', 'gernan1234@gmail.com', '09202544398', '$2y$10$LJbK8RUfNOrOWNyxvdSVK.JXBhdfbfx421j7J7.S6JfXQk5QZ9IrW', '2025-04-20 16:17:42', '55, Bahawan Street, Masambong, San Francisco del Monte, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1105, Philippines [14.644037,121.008090]', 'House', '14.644037', '121.008090'),
(13, 'Zak', 'Agbalo', 'agbalo@gmail.com', '123', '$2y$10$ag9fTlOPHilmG8PNr802kel2T8OkUqUF8Eu8aS5P3yNLaAATXohiW', '2025-04-21 08:19:11', 'Nexus Enterprises & Electrical Supply, Congressional Avenue, Ramon Magsaysay, Bago Bantay, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1105, Philippines [14.658428,121.019301]', 'Restaurant', '14.658428', '121.019301'),
(14, 'John', 'Jake', 'deguzman0361@gmail.com', '09202544398', '$2y$10$66vK96sWs6pjSoSjzIpojO3eMJotrJVrjNox6g2S5HHUys5tjb.ca', '2025-04-30 02:38:39', 'Halcon 2 Street, Santa Teresita, Santa Mesa Heights, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1114, Philippines [14.619939,120.998061]', 'House', '14.619939', '120.998061'),
(26, 'Rean', 'Nartea', 'narteareanfredrick@gmail.com', '09202544398', '$2y$10$a3Uc8jgesxvj1dKPMi3DVuC1WYSrYVfnKXd8heshDdtTlaD5T1jzS', '2025-05-01 15:49:24', '29, Bahawan Street, Masambong, San Francisco del Monte, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1115, Philippines', 'House', NULL, NULL),
(27, 'ass', 'as', 'm91704308@gmail.com', '09111111111', '$2y$10$P7vvx.ho9O1UIsHmBp2Lf.aw0Xxo7lHJ5KzPx1QnaaUcpTXZRjKO2', '2025-05-13 18:59:16', 'University Parkway, Fort Bonifacio, Taguig District 2, Taguig, Southern Manila District, Metro Manila, 1634, Philippines', 'House', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `joborder_feedback`
--

CREATE TABLE `joborder_feedback` (
  `feedback_id` int(11) NOT NULL,
  `job_order_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `technician_arrived` tinyint(1) NOT NULL DEFAULT 0,
  `job_completed` tinyint(1) NOT NULL DEFAULT 0,
  `verification_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `joborder_feedback`
--

INSERT INTO `joborder_feedback` (`feedback_id`, `job_order_id`, `client_id`, `technician_id`, `rating`, `comments`, `created_at`, `technician_arrived`, `job_completed`, `verification_notes`) VALUES
(5, 935, 26, 1, 5, 'Very Good Job', '2025-05-13 18:54:01', 1, 1, 'sasa');

-- --------------------------------------------------------

--
-- Table structure for table `job_order`
--

CREATE TABLE `job_order` (
  `job_order_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `type_of_work` varchar(50) NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `frequency` enum('one-time','weekly','monthly','quarterly') NOT NULL DEFAULT 'one-time',
  `client_approval_status` enum('pending','approved','declined','one-time') NOT NULL DEFAULT 'pending',
  `client_approval_date` datetime DEFAULT NULL,
  `chemical_recommendations` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `payment_amount` decimal(10,2) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_order`
--

INSERT INTO `job_order` (`job_order_id`, `report_id`, `type_of_work`, `preferred_date`, `preferred_time`, `created_at`, `frequency`, `client_approval_status`, `client_approval_date`, `chemical_recommendations`, `cost`, `payment_amount`, `payment_proof`, `payment_date`, `status`) VALUES
(935, 86, 'General Pest Control', '2025-05-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, 0.00, '', '2025-05-13 19:44:38', 'completed'),
(936, 86, 'General Pest Control', '2025-06-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled'),
(937, 86, 'General Pest Control', '2025-07-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled'),
(938, 86, 'General Pest Control', '2025-08-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled'),
(939, 86, 'General Pest Control', '2025-09-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled'),
(940, 86, 'General Pest Control', '2025-10-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled'),
(941, 86, 'General Pest Control', '2025-11-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled'),
(942, 86, 'General Pest Control', '2025-12-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled'),
(943, 86, 'General Pest Control', '2026-01-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled'),
(944, 86, 'General Pest Control', '2026-02-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled'),
(945, 86, 'General Pest Control', '2026-03-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled'),
(946, 86, 'General Pest Control', '2026-04-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled'),
(947, 86, 'General Pest Control', '2026-05-14', '13:05:00', '2025-05-13 17:13:14', 'monthly', 'approved', '2025-05-13 19:44:38', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"dosage\":20,\"dosage_unit\":\"ml\",\"target_pest\":\"Crawling & Flying Pest\"}]', 240000.00, NULL, NULL, NULL, 'scheduled');

-- --------------------------------------------------------

--
-- Table structure for table `job_order_checklists`
--

CREATE TABLE `job_order_checklists` (
  `id` int(11) NOT NULL,
  `job_order_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `type_of_work` varchar(100) NOT NULL,
  `checked_items` text NOT NULL,
  `checked_tools` text DEFAULT '[]',
  `total_items` int(11) NOT NULL DEFAULT 0,
  `checked_count` int(11) NOT NULL DEFAULT 0,
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_order_checklists`
--

INSERT INTO `job_order_checklists` (`id`, `job_order_id`, `technician_id`, `type_of_work`, `checked_items`, `checked_tools`, `total_items`, `checked_count`, `is_completed`, `created_at`, `updated_at`) VALUES
(1, 553, 1, 'General Pest Control, Termite Control', '[]', '[{\"id\":\"24\",\"name\":\"1\",\"type\":\"tool\"},{\"id\":\"1\",\"name\":\"Backpack Sprayer\",\"type\":\"tool\"},{\"id\":\"6\",\"name\":\"Bait Gun\",\"type\":\"tool\"},{\"id\":\"7\",\"name\":\"Dust Applicator\",\"type\":\"tool\"},{\"id\":\"5\",\"name\":\"Flashlight\",\"type\":\"tool\"},{\"id\":\"3\",\"name\":\"Fogger Machine\",\"type\":\"tool\"},{\"id\":\"8\",\"name\":\"Glue Traps\",\"type\":\"tool\"},{\"id\":\"2\",\"name\":\"Hand Sprayer\",\"type\":\"tool\"},{\"id\":\"4\",\"name\":\"Inspection Mirror\",\"type\":\"tool\"},{\"id\":\"11\",\"name\":\"Drill\",\"type\":\"tool\"},{\"id\":\"12\",\"name\":\"Injection Rod\",\"type\":\"tool\"},{\"id\":\"10\",\"name\":\"Moisture Meter\",\"type\":\"tool\"},{\"id\":\"9\",\"name\":\"Termite Bait Station\",\"type\":\"tool\"},{\"id\":\"13\",\"name\":\"Termite Inspection Tool Kit\",\"type\":\"tool\"},{\"id\":\"14\",\"name\":\"Foam Applicator\",\"type\":\"tool\"},{\"id\":\"16\",\"name\":\"Soil Injector\",\"type\":\"tool\"},{\"id\":\"15\",\"name\":\"Trenching Shovel\",\"type\":\"tool\"}]', 24, 17, 1, '2025-05-11 22:00:22', '2025-05-11 22:00:22'),
(2, 554, 1, 'General Pest Control', '[]', '[{\"id\":\"24\",\"name\":\"1\",\"type\":\"tool\"},{\"id\":\"1\",\"name\":\"Backpack Sprayer\",\"type\":\"tool\"},{\"id\":\"6\",\"name\":\"Bait Gun\",\"type\":\"tool\"},{\"id\":\"7\",\"name\":\"Dust Applicator\",\"type\":\"tool\"},{\"id\":\"5\",\"name\":\"Flashlight\",\"type\":\"tool\"},{\"id\":\"3\",\"name\":\"Fogger Machine\",\"type\":\"tool\"},{\"id\":\"8\",\"name\":\"Glue Traps\",\"type\":\"tool\"},{\"id\":\"2\",\"name\":\"Hand Sprayer\",\"type\":\"tool\"},{\"id\":\"4\",\"name\":\"Inspection Mirror\",\"type\":\"tool\"}]', 24, 9, 1, '2025-05-11 22:10:13', '2025-05-11 22:10:13'),
(3, 488, 16, 'General Pest Control', '[]', '[{\"id\":\"24\",\"name\":\"1\",\"type\":\"tool\"},{\"id\":\"1\",\"name\":\"Backpack Sprayer\",\"type\":\"tool\"},{\"id\":\"6\",\"name\":\"Bait Gun\",\"type\":\"tool\"},{\"id\":\"7\",\"name\":\"Dust Applicator\",\"type\":\"tool\"},{\"id\":\"5\",\"name\":\"Flashlight\",\"type\":\"tool\"},{\"id\":\"3\",\"name\":\"Fogger Machine\",\"type\":\"tool\"},{\"id\":\"8\",\"name\":\"Glue Traps\",\"type\":\"tool\"},{\"id\":\"2\",\"name\":\"Hand Sprayer\",\"type\":\"tool\"},{\"id\":\"4\",\"name\":\"Inspection Mirror\",\"type\":\"tool\"}]', 24, 9, 1, '2025-05-11 22:21:49', '2025-05-11 22:21:49'),
(4, 646, 10, '', '[0]', '[{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"}]', 3, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(5, 699, 16, '', '[23]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"}]', 7, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(6, 703, 18, '', '[1,2,3,4,5]', '[{\"id\":1,\"name\":\"Backpack Sprayer\"},{\"id\":2,\"name\":\"Hand Sprayer\"},{\"id\":3,\"name\":\"Fogger Machine\"},{\"id\":4,\"name\":\"Inspection Mirror\"},{\"id\":5,\"name\":\"Flashlight\"}]', 5, 5, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(7, 439, 16, '', '[25]', '[{\"id\":\"25\",\"name\":\"Extention wire\\n                                            mahaba\"}]', 16, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(8, 427, 16, '', '[23]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"}]', 14, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(9, 423, 10, '', '[24,1,6,7,5,8]', '[{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"1\",\"name\":\"Backpack Sprayer\\n                                            Professional-grade backpack sprayer with adjustable nozzle for applying liquid pesticides\"},{\"id\":\"6\",\"name\":\"Bait Gun\\n                                            Precision applicator for gel baits and pastes\"},{\"id\":\"7\",\"name\":\"Dust Applicator\\n                                            Tool for applying insecticidal dust in cracks and crevices\"},{\"id\":\"5\",\"name\":\"Flashlight\\n                                            High-powered LED flashlight for inspections in dark areas\"},{\"id\":\"8\",\"name\":\"Glue Traps\\n                                            Non-toxic monitoring traps for insects and rodents\"}]', 22, 6, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(10, 492, 16, '', '[24,1,6,7,5,8]', '[{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"1\",\"name\":\"Backpack Sprayer\\n                                            Professional-grade backpack sprayer with adjustable nozzle for applying liquid pesticides\"},{\"id\":\"6\",\"name\":\"Bait Gun\\n                                            Precision applicator for gel baits and pastes\"},{\"id\":\"7\",\"name\":\"Dust Applicator\\n                                            Tool for applying insecticidal dust in cracks and crevices\"},{\"id\":\"5\",\"name\":\"Flashlight\\n                                            High-powered LED flashlight for inspections in dark areas\"},{\"id\":\"8\",\"name\":\"Glue Traps\\n                                            Non-toxic monitoring traps for insects and rodents\"}]', 22, 6, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(11, 502, 16, '', '[23,22,20,21]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"},{\"id\":\"22\",\"name\":\"Bed Bug Vacuum\\n                                            Specialized vacuum with HEPA filter for bed bug removal\"},{\"id\":\"20\",\"name\":\"Heat Treatment Unit\\n                                            Portable heater for thermal bed bug elimination\"},{\"id\":\"21\",\"name\":\"Mattress Encasement\\n                                            Protective covers to prevent bed bug infestations in mattresses\"}]', 22, 4, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(12, 498, 16, '', '[24,1,6,7,5,8]', '[{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"1\",\"name\":\"Backpack Sprayer\\n                                            Professional-grade backpack sprayer with adjustable nozzle for applying liquid pesticides\"},{\"id\":\"6\",\"name\":\"Bait Gun\\n                                            Precision applicator for gel baits and pastes\"},{\"id\":\"7\",\"name\":\"Dust Applicator\\n                                            Tool for applying insecticidal dust in cracks and crevices\"},{\"id\":\"5\",\"name\":\"Flashlight\\n                                            High-powered LED flashlight for inspections in dark areas\"},{\"id\":\"8\",\"name\":\"Glue Traps\\n                                            Non-toxic monitoring traps for insects and rodents\"}]', 22, 6, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(13, 493, 10, '', '[25]', '[{\"id\":\"25\",\"name\":\"Extention wire\\n                                            mahaba\"}]', 16, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(14, 496, 10, '', '[23]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"}]', 15, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(15, 501, 10, '', '[21]', '[{\"id\":\"21\",\"name\":\"Mattress Encasement\\n                                            Protective covers to prevent bed bug infestations in mattresses\"}]', 12, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(16, 489, 10, '', '[9]', '[{\"id\":\"9\",\"name\":\"Termite Bait Station\\n                                            In-ground monitoring and baiting system for termite control\"}]', 8, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(17, 500, 10, '', '[14]', '[{\"id\":\"14\",\"name\":\"Foam Applicator\\n                                            Device for applying termiticide foam in wall voids and galleries\"}]', 6, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(18, 505, 10, '', '[25,11,10]', '[{\"id\":\"25\",\"name\":\"Extention wire\\n                                            mahaba\"},{\"id\":\"11\",\"name\":\"Drill\\n                                            Cordless drill for creating treatment holes in concrete and wood\"},{\"id\":\"10\",\"name\":\"Moisture Meter\\n                                            Digital device for measuring moisture content in wood and building materials\"}]', 16, 3, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(19, 494, 10, '', '[]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"},{\"id\":\"21\",\"name\":\"Mattress Encasement\\n                                            Protective covers to prevent bed bug infestations in mattresses\"}]', 15, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(20, 704, 10, '', '[23,21,24]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"},{\"id\":\"21\",\"name\":\"Mattress Encasement\\n                                            Protective covers to prevent bed bug infestations in mattresses\"},{\"id\":\"24\",\"name\":\"1\"}]', 15, 3, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(21, 495, 10, '', '[]', '[{\"id\":\"8\",\"name\":\"Glue Traps\\n                                            Non-toxic monitoring traps for insects and rodents\"},{\"id\":\"25\",\"name\":\"Extention wire\\n                                            mahaba\"}]', 12, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(22, 440, 10, '', '[]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"},{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"8\",\"name\":\"Glue Traps\\n                                            Non-toxic monitoring traps for insects and rodents\"}]', 14, 3, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(23, 444, 10, '', '[]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"},{\"id\":\"25\",\"name\":\"Extention wire\\n                                            mahaba\"}]', 12, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(24, 443, 10, '', '[]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"},{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"8\",\"name\":\"Glue Traps\\n                                            Non-toxic monitoring traps for insects and rodents\"},{\"id\":\"25\",\"name\":\"Extention wire\\n                                            mahaba\"},{\"id\":\"12\",\"name\":\"Injection Rod\\n                                            Specialized tool for injecting termiticide into soil\"}]', 14, 5, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(25, 709, 1, '', '[]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"},{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"25\",\"name\":\"Extention wire\\n                                            mahaba\"},{\"id\":\"12\",\"name\":\"Injection Rod\\n                                            Specialized tool for injecting termiticide into soil\"},{\"id\":\"10\",\"name\":\"Moisture Meter\\n                                            Digital device for measuring moisture content in wood and building materials\"},{\"id\":\"9\",\"name\":\"Termite Bait Station\\n                                            In-ground monitoring and baiting system for termite control\"},{\"id\":\"13\",\"name\":\"Termite Inspection Tool Kit\\n                                            Complete kit with probes, scrapers, and inspection tools\"}]', 14, 7, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(26, 722, 1, '', '[23,24]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"},{\"id\":\"24\",\"name\":\"1\"}]', 11, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(27, 728, 1, '', '[]', '[{\"id\":\"17\",\"name\":\"Backpack Herbicide Sprayer\\n                                            Heavy-duty sprayer specifically for herbicide application\"},{\"id\":\"19\",\"name\":\"Spreader\\n                                            Broadcast spreader for granular herbicide application\"},{\"id\":\"18\",\"name\":\"Weed Torch\\n                                            Propane torch for thermal weed control\"}]', 9, 3, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(28, 741, 1, '', '[]', '[{\"id\":\"8\",\"name\":\"Glue Traps\\n                                            Non-toxic monitoring traps for insects and rodents\"},{\"id\":\"25\",\"name\":\"Extention wire\\n                                            mahaba\"}]', 6, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(29, 746, 16, '', '[]', '[{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"8\",\"name\":\"Glue Traps\\n                                            Non-toxic monitoring traps for insects and rodents\"}]', 14, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(30, 760, 1, '', '[]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"}]', 11, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(31, 761, 1, '', '[]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"}]', 11, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(32, 762, 1, '', '[]', '[{\"id\":\"17\",\"name\":\"Backpack Herbicide Sprayer\\n                                            Heavy-duty sprayer specifically for herbicide application\"},{\"id\":\"19\",\"name\":\"Spreader\\n                                            Broadcast spreader for granular herbicide application\"},{\"id\":\"18\",\"name\":\"Weed Torch\\n                                            Propane torch for thermal weed control\"}]', 10, 3, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(33, 763, 1, '', '[]', '[{\"id\":\"14\",\"name\":\"Foam Applicator\\n                                            Device for applying termiticide foam in wall voids and galleries\"},{\"id\":\"16\",\"name\":\"Soil Injector\\n                                            Tool for injecting termiticide into soil at precise depths\"},{\"id\":\"15\",\"name\":\"Trenching Shovel\\n                                            Specialized shovel for creating treatment trenches around foundations\"}]', 6, 3, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(34, 766, 1, '', '[]', '[{\"id\":\"17\",\"name\":\"Backpack Herbicide Sprayer\\n                                            Heavy-duty sprayer specifically for herbicide application\"},{\"id\":\"19\",\"name\":\"Spreader\\n                                            Broadcast spreader for granular herbicide application\"},{\"id\":\"18\",\"name\":\"Weed Torch\\n                                            Propane torch for thermal weed control\"}]', 3, 3, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(35, 765, 18, '', '[]', '[{\"id\":\"14\",\"name\":\"Foam Applicator\\n                                            Device for applying termiticide foam in wall voids and galleries\"},{\"id\":\"16\",\"name\":\"Soil Injector\\n                                            Tool for injecting termiticide into soil at precise depths\"},{\"id\":\"15\",\"name\":\"Trenching Shovel\\n                                            Specialized shovel for creating treatment trenches around foundations\"}]', 14, 3, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(36, 773, 18, '', '[]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"}]', 11, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(37, 774, 18, '', '[23]', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\\n                                            Passive monitoring device for detecting bed bug presence\"}]', 11, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(38, 775, 10, '', '[]', '[{\"id\":\"25\",\"name\":\"Extention wire\\n                                            mahaba\"}]', 10, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(39, 776, 1, '', '[24,8]', '[{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"8\",\"name\":\"Glue Traps\\n                                            Non-toxic monitoring traps for insects and rodents\"}]', 6, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(40, 777, 1, '', '[]', '[{\"id\":\"17\",\"name\":\"Backpack Herbicide Sprayer\\n                                            Heavy-duty sprayer specifically for herbicide application\"},{\"id\":\"19\",\"name\":\"Spreader\\n                                            Broadcast spreader for granular herbicide application\"},{\"id\":\"18\",\"name\":\"Weed Torch\\n                                            Propane torch for thermal weed control\"}]', 4, 3, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(41, 778, 1, '', '[0]', '[{\"id\":\"default-3\",\"name\":\"Inspection Tools\\n                        Includes flashlight, mirror, and measuring tools\"}]', 3, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(42, 779, 1, '', '[0,0]', '[{\"id\":\"default-1\",\"name\":\"Basic Pest Control Kit\\n                        Includes sprayer, gloves, and basic tools\"},{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"}]', 3, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(43, 830, 1, '', '[]', '[{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"},{\"id\":\"default-3\",\"name\":\"Inspection Tools\\n                        Includes flashlight, mirror, and measuring tools\"}]', 3, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(44, 828, 1, '', '[]', '[{\"id\":\"default-1\",\"name\":\"Basic Pest Control Kit\\n                        Includes sprayer, gloves, and basic tools\"},{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"}]', 3, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(45, 831, 1, '', '[]', '[{\"id\":\"default-1\",\"name\":\"Basic Pest Control Kit\\n                        Includes sprayer, gloves, and basic tools\"},{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"}]', 3, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(46, 829, 1, '', '[0,0]', '[{\"id\":\"default-1\",\"name\":\"Basic Pest Control Kit\\n                        Includes sprayer, gloves, and basic tools\"},{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"}]', 3, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(47, 832, 1, '', '[0,0]', '[{\"id\":\"default-1\",\"name\":\"Basic Pest Control Kit\\n                        Includes sprayer, gloves, and basic tools\"},{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"}]', 3, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(48, 833, 1, '', '[0,0]', '[{\"id\":\"default-1\",\"name\":\"Basic Pest Control Kit\\n                        Includes sprayer, gloves, and basic tools\"},{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"}]', 3, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(49, 834, 1, '', '[0,0]', '[{\"id\":\"default-1\",\"name\":\"Basic Pest Control Kit\\n                        Includes sprayer, gloves, and basic tools\"},{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"}]', 3, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(50, 780, 1, '', '[0,0]', '[{\"id\":\"default-1\",\"name\":\"Basic Pest Control Kit\\n                        Includes sprayer, gloves, and basic tools\"},{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"}]', 3, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(51, 835, 1, '', '[0,0]', '[{\"id\":\"default-1\",\"name\":\"Basic Pest Control Kit\\n                        Includes sprayer, gloves, and basic tools\"},{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"}]', 3, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(52, 836, 1, '', '[0,0]', '[{\"id\":\"default-1\",\"name\":\"Basic Pest Control Kit\\n                        Includes sprayer, gloves, and basic tools\"},{\"id\":\"default-2\",\"name\":\"Safety Equipment\\n                        Includes mask, goggles, and protective clothing\"}]', 3, 2, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(53, 882, 1, '', '[]', '[{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"8\",\"name\":\"Glue Traps\\n                                            Non-toxic monitoring traps for insects and rodents\"},{\"id\":\"25\",\"name\":\"Extention wire\\n                                            mahaba\"},{\"id\":\"14\",\"name\":\"Foam Applicator\\n                                            Device for applying termiticide foam in wall voids and galleries\"},{\"id\":\"16\",\"name\":\"Soil Injector\\n                                            Tool for injecting termiticide into soil at precise depths\"},{\"id\":\"15\",\"name\":\"Trenching Shovel\\n                                            Specialized shovel for creating treatment trenches around foundations\"}]', 14, 6, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(54, 935, 1, '', '[]', '[{\"id\":\"25\",\"name\":\"Extention wire\\n                                            mahaba\"}]', 14, 1, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `job_order_report`
--

CREATE TABLE `job_order_report` (
  `report_id` int(11) NOT NULL,
  `job_order_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `observation_notes` text NOT NULL,
  `attachments` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `chemical_usage` text DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `payment_proof` text DEFAULT NULL,
  `id_attachments` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_order_report`
--

INSERT INTO `job_order_report` (`report_id`, `job_order_id`, `technician_id`, `observation_notes`, `attachments`, `created_at`, `chemical_usage`, `recommendation`, `payment_proof`, `id_attachments`) VALUES
(148, 935, 1, 'nice', '682394dbbea67_Screenshot 2025-05-06 095436.png', '2025-05-13 18:52:11', '[{\"id\":31,\"name\":\"Imidaclopred\",\"type\":\"Insecticide\",\"target_pest\":\"Crawling & Flying Pest\",\"dosage\":200,\"recommended_dosage\":200,\"dosage_unit\":\"ml\",\"inventory_unit\":\"ml\"}]', 'nice', '20000', 'id_682394dbbf45f_Screenshot 2025-05-14 021036.png,id_682394dbbf9ae_Screenshot 2025-05-14 021721.png');

-- --------------------------------------------------------

--
-- Table structure for table `job_order_technicians`
--

CREATE TABLE `job_order_technicians` (
  `job_order_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_order_technicians`
--

INSERT INTO `job_order_technicians` (`job_order_id`, `technician_id`, `is_primary`) VALUES
(935, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('client','technician','admin') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `user_type`, `title`, `message`, `related_id`, `related_type`, `is_read`, `created_at`) VALUES
(1437, 1, 'admin', 'New Appointment Request', 'A new appointment has been requested by Rean Nartea.', 110, 'appointment', 0, '2025-05-13 16:56:26'),
(1438, 2, 'admin', 'New Appointment Request', 'A new appointment has been requested by Rean Nartea.', 110, 'appointment', 0, '2025-05-13 16:56:26'),
(1439, 3, 'admin', 'New Appointment Request', 'A new appointment has been requested by Rean Nartea.', 110, 'appointment', 0, '2025-05-13 16:56:26'),
(1440, 1, 'technician', 'New Appointment Assigned', 'You have been assigned to a new appointment for Rean Nartea on 2025-05-14 at 7:00 AM at 29, Bahawan Street, Masambong, San Francisco del Monte, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1115, Philippines.', 110, 'appointment', 0, '2025-05-13 16:56:51'),
(1441, 26, 'client', 'Appointment Accepted', 'Your appointment on 2025-05-14 at 07:00:00 has been accepted. Technician tech one has been assigned to your appointment.', 110, 'appointment', 0, '2025-05-13 16:56:51'),
(1442, 26, 'client', 'New Quotation Available', 'A new quotation has been sent for your assessment. Type of work: General Pest Control, Frequency: Monthly. Please check your <a href=\'../Client Side/contract.php\'>contracts</a> to approve or decline.', 935, 'quotation', 0, '2025-05-13 17:13:14'),
(1443, 26, 'client', 'Service Completed', 'tech_one has completed your General Pest Control service at 29, Bahawan Street, Masambong, San Francisco del Monte, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1115, Philippines. Please check your job order reports and provide feedback on your experience.', 935, 'job_completed', 0, '2025-05-13 18:52:11'),
(1444, 1, 'admin', 'Job Order Completed', 'tech_one has completed a General Pest Control job order for Rean Nartea at 29, Bahawan Street, Masambong, San Francisco del Monte, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1115, Philippines. Please check the job order report.', 935, 'job_completed', 0, '2025-05-13 18:52:11'),
(1445, 2, 'admin', 'Job Order Completed', 'tech_one has completed a General Pest Control job order for Rean Nartea at 29, Bahawan Street, Masambong, San Francisco del Monte, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1115, Philippines. Please check the job order report.', 935, 'job_completed', 0, '2025-05-13 18:52:11'),
(1446, 3, 'admin', 'Job Order Completed', 'tech_one has completed a General Pest Control job order for Rean Nartea at 29, Bahawan Street, Masambong, San Francisco del Monte, 1st District, Quezon City, Eastern Manila District, Metro Manila, 1115, Philippines. Please check the job order report.', 935, 'job_completed', 0, '2025-05-13 18:52:11'),
(1447, 1, 'admin', 'Job Order Feedback Received', 'Client Rean Nartea has verified technician tech one\'s work for the General Pest Control job order with a rating of 5/5.', 935, 'job_order_feedback', 0, '2025-05-13 18:54:01'),
(1448, 2, 'admin', 'Job Order Feedback Received', 'Client Rean Nartea has verified technician tech one\'s work for the General Pest Control job order with a rating of 5/5.', 935, 'job_order_feedback', 0, '2025-05-13 18:54:01'),
(1449, 3, 'admin', 'Job Order Feedback Received', 'Client Rean Nartea has verified technician tech one\'s work for the General Pest Control job order with a rating of 5/5.', 935, 'job_order_feedback', 0, '2025-05-13 18:54:01');

-- --------------------------------------------------------

--
-- Table structure for table `notification_queue`
--

CREATE TABLE `notification_queue` (
  `id` int(11) NOT NULL,
  `job_order_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `office_staff`
--

CREATE TABLE `office_staff` (
  `staff_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `office_staff`
--

INSERT INTO `office_staff` (`staff_id`, `username`, `password`, `full_name`, `email`, `contact_number`, `profile_picture`) VALUES
(1, 'admin_mike', '4a169480fb6c63f85a2bdb42192bb7c6', '', '', '', '680fa652e374b_3a2115b888673fecde00c4317f42eb5d.jpg'),
(2, 'staff_jane', 'de9bf5643eabf80f4a56fda3bbb84483', NULL, NULL, NULL, NULL),
(3, 'staff_john', 'e10adc3949ba59abbe56e057f20f883e', '', '', '', '680f94e41b1bb_Playbutton2.png');

-- --------------------------------------------------------

--
-- Table structure for table `pest_checkboxes`
--

CREATE TABLE `pest_checkboxes` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pest_checkboxes`
--

INSERT INTO `pest_checkboxes` (`id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Flies', 'active', '2025-05-13 07:33:10', '2025-05-13 07:33:10'),
(2, 'Ants', 'active', '2025-05-13 07:33:10', '2025-05-13 07:33:10'),
(3, 'Cockroaches', 'active', '2025-05-13 07:33:10', '2025-05-13 07:33:10'),
(4, 'Bed Bugs', 'active', '2025-05-13 07:33:10', '2025-05-13 07:33:10'),
(5, 'Mice/Rats', 'active', '2025-05-13 07:33:10', '2025-05-13 07:33:10'),
(6, 'Termites (White Ants)', 'active', '2025-05-13 07:33:10', '2025-05-13 07:33:10'),
(7, 'Mosquitoes', 'active', '2025-05-13 07:33:10', '2025-05-13 07:33:10'),
(8, 'Grass Problems', 'active', '2025-05-13 07:33:10', '2025-05-13 07:33:10'),
(9, 'Disinfect Area', 'active', '2025-05-13 07:33:10', '2025-05-13 07:33:10'),
(10, 'Other (please specify below on notes)', 'active', '2025-05-13 07:33:10', '2025-05-13 07:33:10'),
(11, 'pALA', 'active', '2025-05-13 07:33:21', '2025-05-13 07:33:21'),
(12, 'zdfsdsdv', 'active', '2025-05-13 07:44:11', '2025-05-13 07:44:11'),
(13, 'awawaw', 'active', '2025-05-13 15:24:54', '2025-05-13 15:24:54');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-spray-can',
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `name`, `description`, `icon`, `image`, `status`, `created_at`, `updated_at`) VALUES
(1, 'General Pest Control', 'Comprehensive pest control services targeting common household pests', 'fa-spray-can', 'GenPest.jpg', 'active', '2025-05-10 08:43:46', '2025-05-10 08:43:46'),
(2, 'Termite Control', 'Specialized treatment for termite infestations', 'fa-bug', 'termite.jpg', 'active', '2025-05-10 08:43:46', '2025-05-10 08:43:46'),
(3, 'Rodent Control', 'Targeted solutions for rodent problems', 'fa-mouse', 'rodent.jpg', 'active', '2025-05-10 08:43:46', '2025-05-10 08:43:46'),
(4, 'Disinfection', 'Thorough disinfection services for homes and businesses', 'fa-pump-medical', 'disinfect.jpg', 'active', '2025-05-10 08:43:46', '2025-05-10 08:43:46'),
(5, 'Weed Control', 'Effective weed management solutions', 'fa-seedling', 'weed.jpg', 'active', '2025-05-10 08:43:46', '2025-05-10 08:43:46'),
(6, 'PALA', 'AAAAA', 'fa-house-damage', 'service_681f11ef29bea.jpg', 'active', '2025-05-10 08:44:31', '2025-05-10 08:44:31'),
(7, 'Shovel', 'Pala in ennglish', 'fa-seedling', 'service_682196876586b.jpg', 'active', '2025-05-12 06:34:47', '2025-05-12 06:34:47');

-- --------------------------------------------------------

--
-- Table structure for table `technicians`
--

CREATE TABLE `technicians` (
  `technician_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `tech_contact_number` varchar(20) NOT NULL,
  `tech_fname` varchar(50) NOT NULL,
  `tech_lname` varchar(50) NOT NULL,
  `technician_picture` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technicians`
--

INSERT INTO `technicians` (`technician_id`, `username`, `password`, `tech_contact_number`, `tech_fname`, `tech_lname`, `technician_picture`, `status`) VALUES
(1, 'tech_one', '$2y$10$rKrbkn.ESzByp6epci06VuJazs7jXxrLfB7b20kI8y4q.3DD2/Q32', '09202544398', 'tech', 'one', 'uploads/technicians/680de8765421e_42643.jpg', 'active'),
(10, 'tech_two', '$2y$10$b8mdPiIIu9rHM96AJFEDk.XQCOq9h.hA/364FDEOndwTLWi87/wGG', '09690381171', 'John', 'Paul', 'uploads/technicians/67fe2d6221640_Screenshot 2025-04-12 141855.png', 'active'),
(16, 'tech_three', '$2y$10$k4H1tq2BH917Ky8.yQz/2.tzYS7akR11qIpULjIHx.UcwD9u3CQlS', '09202544398', 'John', 'Jake', 'uploads/technicians/68105e2cec793_baby-elephant-3526681_1280.png', 'active'),
(17, 'tech_four', '$2y$10$N6pAkmact.7kpxwJX9bnn.AzKgWNVSDGQ2Pzr6h8U/htb40.So78a', '09202544398', 'Four', 'Chan', 'uploads/technicians/6810c888d0288_cat-7563332_1280.png', 'active'),
(18, 'tech_five', '$2y$10$kYI6gDSuvxz/du/QLZsS9uqc/zAAgOsLXhdLyUNWQymhnwZsKJ9wG', '0965385692', 'Five', 'Six', 'uploads/technicians/6810ce41c1249_Skunk.png', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `technician_availability`
--

CREATE TABLE `technician_availability` (
  `id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `day_of_week` int(1) DEFAULT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `specific_date` date DEFAULT NULL COMMENT 'For date-specific availability, NULL for weekly pattern',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=available, 0=unavailable',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technician_availability`
--

INSERT INTO `technician_availability` (`id`, `technician_id`, `day_of_week`, `specific_date`, `start_time`, `end_time`, `is_available`, `created_at`, `updated_at`) VALUES
(9, 1, 2, NULL, '13:00:00', '19:00:00', 1, '2025-05-13 05:25:00', '2025-05-13 05:25:00'),
(10, 1, NULL, '2025-05-13', '13:00:00', '16:00:00', 1, '2025-05-13 05:41:49', '2025-05-13 05:41:49'),
(11, 16, 2, NULL, '08:00:00', '17:00:00', 1, '2025-05-13 05:48:20', '2025-05-13 05:48:20'),
(12, 10, 2, NULL, '13:00:00', '22:50:00', 1, '2025-05-13 05:50:28', '2025-05-13 11:54:14'),
(14, 10, 6, NULL, '06:00:00', '23:00:00', 1, '2025-05-13 09:49:16', '2025-05-13 09:49:16'),
(15, 1, 2, NULL, '18:00:00', '22:00:00', 1, '2025-05-13 09:50:08', '2025-05-13 11:53:24'),
(16, 18, 2, NULL, '15:00:00', '20:00:00', 1, '2025-05-13 09:59:03', '2025-05-13 09:59:03'),
(17, 1, 3, NULL, '07:00:00', '19:00:00', 1, '2025-05-13 16:52:19', '2025-05-13 16:52:19'),
(18, 1, 4, NULL, '07:00:00', '19:00:00', 1, '2025-05-13 16:52:45', '2025-05-13 16:52:45'),
(19, 1, 5, NULL, '07:00:00', '19:00:00', 1, '2025-05-13 16:53:15', '2025-05-13 16:53:15');

-- --------------------------------------------------------

--
-- Table structure for table `technician_checklist_logs`
--

CREATE TABLE `technician_checklist_logs` (
  `log_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `checklist_date` date NOT NULL,
  `checked_items` text DEFAULT NULL,
  `total_items` int(11) NOT NULL,
  `checked_count` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technician_checklist_logs`
--

INSERT INTO `technician_checklist_logs` (`log_id`, `technician_id`, `checklist_date`, `checked_items`, `total_items`, `checked_count`, `created_at`) VALUES
(6, 1, '2025-04-21', '[]', 0, 0, '2025-04-21 07:58:06'),
(7, 1, '2025-04-27', '[]', 0, 0, '2025-04-27 07:47:24'),
(8, 10, '2025-04-27', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\"},{\"id\":\"22\",\"name\":\"Bed Bug Vacuum\"},{\"id\":\"20\",\"name\":\"Heat Treatment Unit\"},{\"id\":\"21\",\"name\":\"Mattress Encasement\"},{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"1\",\"name\":\"Backpack Sprayer\"},{\"id\":\"6\",\"name\":\"Bait Gun\"},{\"id\":\"7\",\"name\":\"Dust Applicator\"},{\"id\":\"5\",\"name\":\"Flashlight\"},{\"id\":\"3\",\"name\":\"Fogger Machine\"},{\"id\":\"8\",\"name\":\"Glue Traps\"},{\"id\":\"2\",\"name\":\"Hand Sprayer\"},{\"id\":\"4\",\"name\":\"Inspection Mirror\"},{\"id\":\"11\",\"name\":\"Drill\"},{\"id\":\"12\",\"name\":\"Injection Rod\"},{\"id\":\"10\",\"name\":\"Moisture Meter\"},{\"id\":\"15\",\"name\":\"Trenching Shovel\"}]', 24, 17, '2025-04-27 09:54:42'),
(9, 1, '2025-04-28', '[]', 0, 0, '2025-04-28 02:26:03'),
(10, 10, '2025-04-28', '[]', 0, 0, '2025-04-28 02:26:52'),
(11, 10, '2025-04-29', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\"},{\"id\":\"22\",\"name\":\"Bed Bug Vacuum\"},{\"id\":\"20\",\"name\":\"Heat Treatment Unit\"},{\"id\":\"21\",\"name\":\"Mattress Encasement\"}]', 24, 4, '2025-04-29 01:56:42'),
(12, 1, '2025-04-29', '[]', 0, 0, '2025-04-29 03:22:18'),
(13, 16, '2025-04-29', '[]', 0, 0, '2025-04-29 05:08:37'),
(14, 17, '2025-04-29', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\"},{\"id\":\"22\",\"name\":\"Bed Bug Vacuum\"},{\"id\":\"20\",\"name\":\"Heat Treatment Unit\"},{\"id\":\"24\",\"name\":\"1\"},{\"id\":\"1\",\"name\":\"Backpack Sprayer\"},{\"id\":\"6\",\"name\":\"Bait Gun\"},{\"id\":\"7\",\"name\":\"Dust Applicator\"},{\"id\":\"11\",\"name\":\"Drill\"},{\"id\":\"12\",\"name\":\"Injection Rod\"},{\"id\":\"10\",\"name\":\"Moisture Meter\"},{\"id\":\"14\",\"name\":\"Foam Applicator\"},{\"id\":\"16\",\"name\":\"Soil Injector\"},{\"id\":\"17\",\"name\":\"Backpack Herbicide Sprayer\"},{\"id\":\"19\",\"name\":\"Spreader\"}]', 24, 14, '2025-04-29 12:52:03'),
(15, 18, '2025-04-29', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\"},{\"id\":\"22\",\"name\":\"Bed Bug Vacuum\"},{\"id\":\"20\",\"name\":\"Heat Treatment Unit\"},{\"id\":\"21\",\"name\":\"Mattress Encasement\"},{\"id\":\"11\",\"name\":\"Drill\"},{\"id\":\"12\",\"name\":\"Injection Rod\"},{\"id\":\"10\",\"name\":\"Moisture Meter\"},{\"id\":\"9\",\"name\":\"Termite Bait Station\"},{\"id\":\"13\",\"name\":\"Termite Inspection Tool Kit\"}]', 24, 9, '2025-04-29 13:04:27'),
(16, 18, '2025-04-30', '[{\"id\":\"23\",\"name\":\"Bed Bug Monitor\"},{\"id\":\"22\",\"name\":\"Bed Bug Vacuum\"},{\"id\":\"20\",\"name\":\"Heat Treatment Unit\"},{\"id\":\"21\",\"name\":\"Mattress Encasement\"},{\"id\":\"11\",\"name\":\"Drill\"},{\"id\":\"12\",\"name\":\"Injection Rod\"},{\"id\":\"10\",\"name\":\"Moisture Meter\"},{\"id\":\"9\",\"name\":\"Termite Bait Station\"},{\"id\":\"13\",\"name\":\"Termite Inspection Tool Kit\"}]', 24, 9, '2025-04-30 02:59:35'),
(17, 1, '2025-04-30', '[23,22,20,21,7,3,11,12,10,9,13]', 24, 11, '2025-04-30 09:13:25'),
(18, 1, '2025-05-01', '[]', 0, 0, '2025-05-01 08:20:10'),
(19, 1, '2025-05-02', '[]', 0, 0, '2025-05-02 13:08:11'),
(20, 10, '2025-05-02', '[]', 0, 0, '2025-05-02 13:09:51'),
(21, 1, '2025-05-06', '[]', 0, 0, '2025-05-06 14:05:00'),
(22, 10, '2025-05-06', '[23,22,20,21]', 24, 4, '2025-05-06 14:11:55'),
(23, 1, '2025-05-09', '[23,22,20,21]', 24, 4, '2025-05-09 12:20:24'),
(24, 10, '2025-05-09', '[]', 0, 0, '2025-05-09 18:02:48'),
(25, 1, '2025-05-10', '[23,22,20,21]', 24, 4, '2025-05-10 02:25:41'),
(26, 16, '2025-05-10', '[23,22,20,21]', 24, 4, '2025-05-10 02:25:51'),
(27, 17, '2025-05-10', '[23,22,20,21]', 24, 4, '2025-05-10 03:26:20'),
(28, 1, '2025-05-11', '[23,22,20,21]', 24, 4, '2025-05-11 02:27:28'),
(29, 16, '2025-05-11', '[23,22,20,21]', 24, 4, '2025-05-11 12:20:46'),
(30, 10, '2025-05-11', '[]', 0, 0, '2025-05-11 17:22:59'),
(31, 10, '2025-05-12', '[23,24,8,25,12]', 14, 5, '2025-05-12 03:30:16'),
(32, 16, '2025-05-12', '[24,1,6,7,5,8]', 22, 6, '2025-05-12 16:17:30'),
(33, 1, '2025-05-13', '[25]', 14, 1, '2025-05-13 04:44:29'),
(34, 16, '2025-05-13', '[24,8]', 14, 2, '2025-05-13 06:06:07'),
(35, 18, '2025-05-13', '[23]', 11, 1, '2025-05-13 10:12:41'),
(36, 10, '2025-05-13', '[25]', 10, 1, '2025-05-13 11:54:42');

-- --------------------------------------------------------

--
-- Table structure for table `technician_feedback`
--

CREATE TABLE `technician_feedback` (
  `feedback_id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `rating` int(1) NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `technician_arrived` tinyint(1) NOT NULL DEFAULT 0,
  `job_completed` tinyint(1) NOT NULL DEFAULT 0,
  `verification_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tools_equipment`
--

CREATE TABLE `tools_equipment` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('in stock','in use') NOT NULL DEFAULT 'in stock'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tools_equipment`
--

INSERT INTO `tools_equipment` (`id`, `name`, `category`, `quantity`, `description`, `created_at`, `updated_at`, `status`) VALUES
(8, 'Glue Traps', 'General Pest Control', 150, 'Non-toxic monitoring traps for insects and rodents', '2025-04-22 13:04:06', '2025-05-13 15:14:04', 'in stock'),
(9, 'Termite Bait Station', 'Termite', 50, 'In-ground monitoring and baiting system for termite control', '2025-04-22 13:04:06', '2025-05-13 15:12:39', 'in stock'),
(10, 'Moisture Meter', 'Termite', 6, 'Digital device for measuring moisture content in wood and building materials', '2025-04-22 13:04:06', '2025-05-13 15:12:39', 'in stock'),
(12, 'Injection Rod', 'Termite', 10, 'Specialized tool for injecting termiticide into soil', '2025-04-22 13:04:06', '2025-05-13 15:12:39', 'in stock'),
(13, 'Termite Inspection Tool Kit', 'Termite', 5, 'Complete kit with probes, scrapers, and inspection tools', '2025-04-22 13:04:06', '2025-05-13 15:12:39', 'in stock'),
(14, 'Foam Applicator', 'Termite Treatment', 7, 'Device for applying termiticide foam in wall voids and galleries', '2025-04-22 13:04:06', '2025-05-13 15:14:04', 'in stock'),
(15, 'Trenching Shovel', 'Termite Treatment', 12, 'Specialized shovel for creating treatment trenches around foundations', '2025-04-22 13:04:06', '2025-05-13 15:14:04', 'in stock'),
(16, 'Soil Injector', 'Termite Treatment', 8, 'Tool for injecting termiticide into soil at precise depths', '2025-04-22 13:04:06', '2025-05-13 15:14:04', 'in stock'),
(17, 'Backpack Herbicide Sprayer', 'Weed Control', 6, 'Heavy-duty sprayer specifically for herbicide application', '2025-04-22 13:04:06', '2025-05-13 15:12:39', 'in stock'),
(18, 'Weed Torch', 'Weed Control', 4, 'Propane torch for thermal weed control', '2025-04-22 13:04:06', '2025-05-13 15:12:39', 'in stock'),
(19, 'Spreader', 'Weed Control', 5, 'Broadcast spreader for granular herbicide application', '2025-04-22 13:04:06', '2025-05-13 15:12:39', 'in stock'),
(23, 'Bed Bug Monitor', 'Bed Bugs', 40, 'Passive monitoring device for detecting bed bug presence', '2025-04-22 13:04:06', '2025-05-13 15:12:39', 'in stock'),
(24, '1', 'General Pest Control', 0, '', '2025-04-26 16:10:55', '2025-05-13 15:14:04', 'in stock'),
(25, 'Extention wire', 'General Pest Control, Termite Treatment', 0, 'mahaba', '2025-05-12 06:37:50', '2025-05-13 18:52:17', 'in stock');

-- --------------------------------------------------------

--
-- Table structure for table `work_types`
--

CREATE TABLE `work_types` (
  `id` int(11) NOT NULL,
  `type_name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `appointment_technicians`
--
ALTER TABLE `appointment_technicians`
  ADD PRIMARY KEY (`appointment_id`,`technician_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `assessment_report`
--
ALTER TABLE `assessment_report`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `chemical_assignments`
--
ALTER TABLE `chemical_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `chemical_id` (`chemical_id`),
  ADD KEY `technician_id` (`technician_id`),
  ADD KEY `job_order_id` (`job_order_id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `chemical_inventory`
--
ALTER TABLE `chemical_inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chemical_usage_log`
--
ALTER TABLE `chemical_usage_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chemical_id` (`chemical_id`),
  ADD KEY `job_order_id` (`job_order_id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`client_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `joborder_feedback`
--
ALTER TABLE `joborder_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `job_order_id` (`job_order_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `job_order`
--
ALTER TABLE `job_order`
  ADD PRIMARY KEY (`job_order_id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `job_order_checklists`
--
ALTER TABLE `job_order_checklists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_order_id` (`job_order_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `job_order_report`
--
ALTER TABLE `job_order_report`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `job_order_id` (`job_order_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `job_order_technicians`
--
ALTER TABLE `job_order_technicians`
  ADD PRIMARY KEY (`job_order_id`,`technician_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `user_type` (`user_type`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `job_order_id` (`job_order_id`);

--
-- Indexes for table `office_staff`
--
ALTER TABLE `office_staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `pest_checkboxes`
--
ALTER TABLE `pest_checkboxes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `technicians`
--
ALTER TABLE `technicians`
  ADD PRIMARY KEY (`technician_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `technician_availability`
--
ALTER TABLE `technician_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `technician_id` (`technician_id`),
  ADD KEY `day_of_week` (`day_of_week`),
  ADD KEY `specific_date` (`specific_date`);

--
-- Indexes for table `technician_checklist_logs`
--
ALTER TABLE `technician_checklist_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD UNIQUE KEY `technician_date` (`technician_id`,`checklist_date`),
  ADD KEY `technician_id` (`technician_id`),
  ADD KEY `checklist_date` (`checklist_date`);

--
-- Indexes for table `technician_feedback`
--
ALTER TABLE `technician_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD UNIQUE KEY `report_id` (`report_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `technician_id` (`technician_id`);

--
-- Indexes for table `tools_equipment`
--
ALTER TABLE `tools_equipment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `work_types`
--
ALTER TABLE `work_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_name` (`type_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `assessment_report`
--
ALTER TABLE `assessment_report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `chemical_assignments`
--
ALTER TABLE `chemical_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chemical_inventory`
--
ALTER TABLE `chemical_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `chemical_usage_log`
--
ALTER TABLE `chemical_usage_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `joborder_feedback`
--
ALTER TABLE `joborder_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `job_order`
--
ALTER TABLE `job_order`
  MODIFY `job_order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=948;

--
-- AUTO_INCREMENT for table `job_order_checklists`
--
ALTER TABLE `job_order_checklists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `job_order_report`
--
ALTER TABLE `job_order_report`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1450;

--
-- AUTO_INCREMENT for table `notification_queue`
--
ALTER TABLE `notification_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `office_staff`
--
ALTER TABLE `office_staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pest_checkboxes`
--
ALTER TABLE `pest_checkboxes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `technicians`
--
ALTER TABLE `technicians`
  MODIFY `technician_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `technician_availability`
--
ALTER TABLE `technician_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `technician_checklist_logs`
--
ALTER TABLE `technician_checklist_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `technician_feedback`
--
ALTER TABLE `technician_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `tools_equipment`
--
ALTER TABLE `tools_equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `work_types`
--
ALTER TABLE `work_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`);

--
-- Constraints for table `appointment_technicians`
--
ALTER TABLE `appointment_technicians`
  ADD CONSTRAINT `appointment_technicians_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  ADD CONSTRAINT `appointment_technicians_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);

--
-- Constraints for table `assessment_report`
--
ALTER TABLE `assessment_report`
  ADD CONSTRAINT `assessment_report_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`);

--
-- Constraints for table `chemical_assignments`
--
ALTER TABLE `chemical_assignments`
  ADD CONSTRAINT `chemical_assignments_ibfk_1` FOREIGN KEY (`chemical_id`) REFERENCES `chemical_inventory` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chemical_assignments_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chemical_assignments_ibfk_3` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chemical_assignments_ibfk_4` FOREIGN KEY (`report_id`) REFERENCES `assessment_report` (`report_id`) ON DELETE SET NULL;

--
-- Constraints for table `joborder_feedback`
--
ALTER TABLE `joborder_feedback`
  ADD CONSTRAINT `joborder_feedback_ibfk_1` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`),
  ADD CONSTRAINT `joborder_feedback_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`),
  ADD CONSTRAINT `joborder_feedback_ibfk_3` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);

--
-- Constraints for table `job_order`
--
ALTER TABLE `job_order`
  ADD CONSTRAINT `job_order_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `assessment_report` (`report_id`);

--
-- Constraints for table `job_order_report`
--
ALTER TABLE `job_order_report`
  ADD CONSTRAINT `job_order_report_ibfk_1` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`),
  ADD CONSTRAINT `job_order_report_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);

--
-- Constraints for table `job_order_technicians`
--
ALTER TABLE `job_order_technicians`
  ADD CONSTRAINT `job_order_technicians_ibfk_1` FOREIGN KEY (`job_order_id`) REFERENCES `job_order` (`job_order_id`),
  ADD CONSTRAINT `job_order_technicians_ibfk_2` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);

--
-- Constraints for table `technician_availability`
--
ALTER TABLE `technician_availability`
  ADD CONSTRAINT `technician_availability_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`) ON DELETE CASCADE;

--
-- Constraints for table `technician_checklist_logs`
--
ALTER TABLE `technician_checklist_logs`
  ADD CONSTRAINT `technician_checklist_logs_ibfk_1` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);

--
-- Constraints for table `technician_feedback`
--
ALTER TABLE `technician_feedback`
  ADD CONSTRAINT `technician_feedback_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `assessment_report` (`report_id`),
  ADD CONSTRAINT `technician_feedback_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`),
  ADD CONSTRAINT `technician_feedback_ibfk_3` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`technician_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
