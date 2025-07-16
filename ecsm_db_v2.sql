-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 16, 2025 at 07:12 AM
-- Server version: 10.6.22-MariaDB-0ubuntu0.22.04.1
-- PHP Version: 8.4.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ecsm_db_v2`
--

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `csm_responses`
--

CREATE TABLE `csm_responses` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `submission_date` datetime NOT NULL DEFAULT current_timestamp(),
  `affiliation` enum('Internal','External') NOT NULL,
  `client_type` enum('Citizen','Business','Government') NOT NULL,
  `age` int(3) DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `region_of_residence` varchar(100) DEFAULT NULL,
  `preferred_language` varchar(10) DEFAULT NULL,
  `ref_id` varchar(255) DEFAULT NULL,
  `cc1` tinyint(4) DEFAULT NULL,
  `cc2` tinyint(4) DEFAULT NULL,
  `cc3` tinyint(4) DEFAULT NULL,
  `sqd0` tinyint(4) DEFAULT NULL,
  `sqd1` tinyint(4) DEFAULT NULL,
  `sqd2` tinyint(4) DEFAULT NULL,
  `sqd3` tinyint(4) DEFAULT NULL,
  `sqd4` tinyint(4) DEFAULT NULL,
  `sqd5` tinyint(4) DEFAULT NULL,
  `sqd6` tinyint(4) DEFAULT NULL,
  `sqd7` tinyint(4) DEFAULT NULL,
  `sqd8` tinyint(4) DEFAULT NULL,
  `suggestions` text DEFAULT NULL,
  `email_address` varchar(255) DEFAULT NULL,
  `client_email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`) VALUES
(1, 'City Mayor’s Office (CMO)'),
(5, 'Sanguniang Panlungsod (SP)'),
(6, 'City Agriculturist’s Office (AGGIES)'),
(7, 'City Assessor’s Office (ASSESSOR)'),
(8, 'City Accountant’s Office (ACCTNG), City Budget Office (BUDGET) & City Treasurer’s Office (CTO)'),
(9, 'City Disaster Risk Reduction and Management Office (CDRRMO)'),
(10, 'City Economic Enterprise Department (CEED)'),
(11, 'City Engineer’s Office (CEO)'),
(12, 'City Health Office'),
(13, 'City Planning and Development Office (CPDO)'),
(14, 'City Social Welfare and Development Office'),
(15, 'City Veterinarian’s Office'),
(16, 'Local Civil Registrar’s Office (LCR)'),
(17, 'General Services Office (GSO)'),
(18, 'City Environment & Natural Resources Management Office (CENRMO)'),
(19, 'City Investment Promotions Office (CIPO)'),
(20, 'City Tourism Office (TOURISM)'),
(21, 'City Internal Audit Services Department (CIASD)');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `service_details_html` text DEFAULT NULL,
  `service_type` enum('Internal','External') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `department_id`, `service_name`, `service_details_html`, `service_type`, `is_active`) VALUES
(5, 1, 'Business Permit (New Registration/Renewal) Issuance', '', 'External', 1),
(6, 1, 'Business Permit to Tricycle/Motorela and Trisikad Operators  (New Registration/Renewal) Issuance', '', 'External', 1),
(7, 1, 'Mayor’s Clearance Issuance', '', 'External', 1),
(8, 1, 'Motorized Tricycle Operator’s Permit (MTOP)  (New Registration/Renewal) Issuance', '', 'External', 1),
(9, 1, 'Occupational Permit Issuance', NULL, 'External', 1),
(10, 1, 'Retirement of Business and Certification of Cessation', NULL, 'External', 1),
(11, 1, 'Rental of Tractor and Farm Equipment', NULL, 'External', 1),
(12, 1, 'Rental of Water Pump and Other Agri-Equipment', NULL, 'External', 1),
(13, 1, 'Issuance of Appointment – Regular', NULL, 'External', 1),
(14, 1, 'Issuance of Appointment – Casual', NULL, 'External', 1),
(15, 1, 'PESO Certification for Job Seekers', NULL, 'External', 1),
(16, 1, 'PESO Certification for Returning OFWs who did not finish their\r\n', NULL, 'External', 1),
(17, 1, 'Scholarship Contract Issuance', NULL, 'External', 1),
(18, 1, 'Technical Vocational Education and Training Certification', NULL, 'External', 1),
(19, 1, 'Provision of Administrative Case Investigation', NULL, 'External', 1),
(20, 1, 'Provision of Free Legal Consultation/Service', NULL, 'External', 1),
(21, 1, 'Provision of Document/s Available at the City Legal Office', NULL, 'External', 1),
(22, 1, 'Rendition of Legal Opinion', NULL, 'External', 1),
(23, 1, 'City Museum Tour', NULL, 'External', 1),
(24, 1, 'Borrowing or Photocopying of Library Books/Materials', NULL, 'External', 1),
(25, 1, 'Search Learning Materials via Internet at the Library', NULL, 'External', 1);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_name`, `setting_value`) VALUES
(1, 'agency_name', 'CITY GOVERNMENT OF GINGOOG'),
(2, 'province_name', 'Misamis Oriental'),
(3, 'region_name', 'Region X'),
(4, 'agency_logo', '2737c3964149c007f15a29b9ea079b06.png'),
(5, 'password_complexity', 'medium'),
(6, 'timezone', 'Asia/Manila');

-- --------------------------------------------------------

--
-- Table structure for table `submission_timestamps`
--

CREATE TABLE `submission_timestamps` (
  `id` int(11) NOT NULL,
  `client_email` varchar(255) NOT NULL,
  `submission_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `action` varchar(255) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--

INSERT INTO `system_logs` (`id`, `user_id`, `ip_address`, `user_agent`, `action`, `timestamp`) VALUES
(1, NULL, '49.146.7.228', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'CSM feedback submitted for service ID 24', '2025-07-16 06:50:31'),
(2, 1, '49.146.7.228', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'Updated system settings', '2025-07-16 06:52:14'),
(3, 1, '49.146.7.228', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'Added new service \'test\'', '2025-07-16 06:57:16'),
(4, 1, '49.146.7.228', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'Deleted service with ID 27', '2025-07-16 06:57:26'),
(5, 1, '49.146.7.228', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'Added new service \'test\'', '2025-07-16 07:09:28'),
(6, 1, '49.146.7.228', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'Updated service \'Business Permit (New Registration/Renewal) Issuance\'', '2025-07-16 07:09:55'),
(7, 1, '49.146.7.228', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'Deleted service with ID 28', '2025-07-16 07:10:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','dept') NOT NULL,
  `department_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `department_id`) VALUES
(1, 'admin', '$2y$10$L2nT6cSvIGoxSpUjFiW8m.Ujk9/vdRoPrOxtTpmOcXlm/1LJwm.xK', 'admin', NULL),
(3, 'user', '$2y$10$.EZkW3YZ/mQgfjuDGmCRJO3ZkCIuXFrX59Dk1cfYYu.OO.aAKXijS', 'dept', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `csm_responses`
--
ALTER TABLE `csm_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `csm_responses`
--
ALTER TABLE `csm_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `csm_responses`
--
ALTER TABLE `csm_responses`
  ADD CONSTRAINT `csm_responses_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
