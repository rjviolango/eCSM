-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 04, 2025 at 08:19 AM
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
-- Database: `ecsm_db`
--

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
  `email_address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `csm_responses`
--

INSERT INTO `csm_responses` (`id`, `service_id`, `submission_date`, `affiliation`, `client_type`, `age`, `sex`, `region_of_residence`, `preferred_language`, `ref_id`, `cc1`, `cc2`, `cc3`, `sqd0`, `sqd1`, `sqd2`, `sqd3`, `sqd4`, `sqd5`, `sqd6`, `sqd7`, `sqd8`, `suggestions`, `email_address`) VALUES
(1, 3, '2025-07-03 08:06:17', 'External', 'Government', NULL, 'Male', 'Northern Mindanao (Region X)', NULL, NULL, 4, NULL, NULL, NULL, 5, 5, 5, 5, 5, 5, 5, 5, 'bad service', ''),
(2, 3, '2025-07-03 08:08:43', 'External', 'Citizen', 20, 'Male', 'Northern Mindanao (Region X)', NULL, NULL, 4, NULL, NULL, 4, 4, 2, 3, 3, 2, 3, 2, 3, 'Sakit vaccine', ''),
(3, 3, '2025-07-03 08:21:34', 'External', 'Government', NULL, 'Male', 'Northern Mindanao (Region X)', NULL, NULL, 3, 2, 1, 1, 4, 5, 3, 5, 3, 5, 3, 5, 'ahaha', ''),
(4, 1, '2025-07-04 03:12:52', 'External', 'Government', 25, 'Male', 'Northern Mindanao (Region X)', NULL, NULL, 4, NULL, NULL, 5, 5, 5, 5, 5, 5, 5, 5, 5, 'Great service', ''),
(5, 4, '2025-07-04 05:47:57', 'Internal', 'Government', NULL, 'Male', 'Northern Mindanao (Region X)', NULL, NULL, 4, NULL, NULL, 5, 5, 5, 5, 5, 5, 5, 5, 5, '', '');

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
(1, 'City Mayor\'s Office'),
(2, 'Human Resource Management Office'),
(3, 'City Health Office'),
(4, 'IT Office');

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
(1, 1, 'Processing of Business Permit', '<p>Standard processing of new business permits and renewals.</p>', 'External', 1),
(2, 2, 'Leave Application Processing', '<p>For all city government employees.</p>', 'Internal', 1),
(3, 3, 'Vaccination Drive', '<p>Public vaccination services.</p>', 'External', 1),
(4, 4, 'IT Support Request', 'Provides technical assistance for hardware and software issues for employees.', 'Internal', 1);

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
(4, 'agency_logo', '68662fd59fb21_LGU-GINGOOG LOGO SMALL.png'),
(5, 'password_complexity', 'medium'),
(6, 'timezone', 'Asia/Manila');

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
(2, 'user', '$2y$10$7WvRRLr4UtrahujJBybu5O0zHkylptfFqATLycsjNhwhQgcKAvoiC', 'dept', 4);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
