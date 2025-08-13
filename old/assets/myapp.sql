-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 11, 2025 at 02:27 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `myapp`
--

-- --------------------------------------------------------

--
-- Table structure for table `caller_ids`
--

CREATE TABLE `caller_ids` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `phone_number` varchar(14) NOT NULL,
  `verification_code` varchar(4) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calls`
--

CREATE TABLE `calls` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_number` varchar(20) NOT NULL,
  `caller_id` varchar(20) NOT NULL,
  `duration` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `callback_method` enum('phone','softphone') DEFAULT 'phone',
  `institution_name` varchar(255) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `merchant_name` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `dtmf_input` varchar(10) DEFAULT NULL,
  `call_status` varchar(50) DEFAULT NULL,
  `tts_script` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`id`, `user_id`, `name`, `phone_number`, `created_at`) VALUES
(22, 7, 'Banger', '18005318722', '2025-08-08 19:05:15'),
(23, 2, 'Chase', '18005318722', '2025-08-09 03:09:39'),
(24, 2, 'Banger', '13058399485', '2025-08-09 03:09:47'),
(26, 7, 'usaa', '12105318722', '2025-08-10 14:27:49'),
(27, 7, 'MArk', '12105318722', '2025-08-11 12:09:50');

-- --------------------------------------------------------

--
-- Table structure for table `deleted_cdrs`
--

CREATE TABLE `deleted_cdrs` (
  `id` int(11) NOT NULL,
  `cdr_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dtmf_inputs`
--

CREATE TABLE `dtmf_inputs` (
  `id` int(11) NOT NULL,
  `call_id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `dtmf_keys` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `institutions`
--

CREATE TABLE `institutions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `institutions`
--

INSERT INTO `institutions` (`id`, `user_id`, `name`, `created_at`) VALUES
(11, 2, 'USAA', '2025-08-09 03:09:56'),
(12, 7, 'USAA', '2025-08-10 14:27:56');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payment_id` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(50) NOT NULL,
  `created_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ivr_calls`
--

CREATE TABLE `ivr_calls` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `institution_name` varchar(255) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_number` varchar(20) NOT NULL,
  `caller_id` varchar(20) NOT NULL,
  `callback_number` varchar(20) NOT NULL,
  `merchant_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('initiated','completed','failed') DEFAULT 'initiated',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ivr_profiles`
--

CREATE TABLE `ivr_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `profile_name` varchar(255) NOT NULL,
  `institution_name` varchar(255) NOT NULL,
  `caller_id` varchar(20) NOT NULL,
  `callback_number` varchar(20) NOT NULL,
  `merchant_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `magnus_ivr_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ivr_profiles`
--

INSERT INTO `ivr_profiles` (`id`, `user_id`, `profile_name`, `institution_name`, `caller_id`, `callback_number`, `merchant_name`, `amount`, `magnus_ivr_id`) VALUES
(4, 2, 'Chase', 'Chase', '18005318722', '13058399485', 'Target', 350.00, NULL),
(7, 7, 'USAA', 'USAA', '12105318722', '18005318722', 'Walmart', 350.87, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `created_at`) VALUES
(1, 'data', '2025-08-03 22:25:55'),
(2, 'fukme', '2025-08-03 22:27:03'),
(3, 'fukme', '2025-08-03 22:30:48'),
(4, 'fukme', '2025-08-03 22:30:54'),
(5, 'data', '2025-08-03 22:31:16'),
(6, 'data', '2025-08-03 22:33:52'),
(7, 'data', '2025-08-03 22:43:23'),
(8, 'data', '2025-08-03 23:05:43'),
(9, 'data', '2025-08-03 23:29:16'),
(10, 'data', '2025-08-03 23:41:02'),
(11, 'data', '2025-08-03 23:43:37'),
(12, 'labdata', '2025-08-04 07:12:52'),
(13, 'data', '2025-08-04 07:12:59'),
(14, 'data', '2025-08-04 07:13:01'),
(15, 'data', '2025-08-04 07:13:06'),
(16, 'testuser', '2025-08-04 08:18:40'),
(17, 'data', '2025-08-04 08:18:46'),
(18, 'data', '2025-08-04 08:19:11'),
(19, 'data', '2025-08-04 08:19:17'),
(20, 'data', '2025-08-04 08:19:37'),
(21, 'data', '2025-08-04 08:24:34'),
(22, 'data', '2025-08-04 08:28:27'),
(23, 'data', '2025-08-04 08:28:32'),
(24, 'data', '2025-08-04 08:31:56'),
(25, 'data', '2025-08-04 08:33:26'),
(26, 'data', '2025-08-04 08:34:43'),
(27, 'data', '2025-08-04 08:34:49'),
(28, 'labdata', '2025-08-04 08:38:59'),
(29, 'labdata', '2025-08-04 08:39:52'),
(30, 'labdata', '2025-08-04 08:39:57'),
(31, 'labdata', '2025-08-04 08:41:07'),
(32, 'labdata', '2025-08-04 08:43:34'),
(33, 'labdata', '2025-08-04 08:43:39'),
(34, 'data', '2025-08-04 09:00:42'),
(35, 'data', '2025-08-04 13:34:18'),
(36, 'data', '2025-08-04 16:21:25'),
(37, 'data', '2025-08-04 16:21:33'),
(38, 'data', '2025-08-04 16:38:42'),
(39, 'data', '2025-08-05 09:11:54'),
(40, 'data', '2025-08-05 12:24:46'),
(41, 'data', '2025-08-05 20:15:02'),
(42, 'data', '2025-08-05 20:17:11'),
(43, 'data', '2025-08-05 20:18:03'),
(44, 'labdata', '2025-08-05 21:08:44'),
(45, 'data', '2025-08-05 21:12:45'),
(46, 'data', '2025-08-05 22:18:51'),
(47, 'data', '2025-08-05 22:33:53'),
(48, 'data', '2025-08-06 09:03:32'),
(49, 'data', '2025-08-06 09:09:50'),
(50, 'data', '2025-08-06 09:09:58'),
(51, 'data', '2025-08-07 19:40:08'),
(52, 'data', '2025-08-07 19:40:16'),
(53, 'data', '2025-08-07 19:40:17'),
(54, 'data', '2025-08-07 19:40:39'),
(55, 'data', '2025-08-07 19:40:47'),
(56, 'data', '2025-08-07 19:40:49'),
(57, 'labdata', '2025-08-07 19:41:48'),
(58, 'labdata', '2025-08-07 20:09:39'),
(59, 'labdata', '2025-08-07 20:10:06'),
(60, 'labdata', '2025-08-07 20:10:08'),
(61, 'data', '2025-08-07 20:10:21'),
(62, 'data', '2025-08-07 20:15:35'),
(63, 'data', '2025-08-07 20:20:12'),
(64, 'data', '2025-08-07 20:20:13'),
(65, 'data', '2025-08-07 20:20:28'),
(66, 'labdata', '2025-08-07 20:22:20'),
(67, 'labdata', '2025-08-07 20:22:23'),
(68, 'labdata', '2025-08-07 20:22:29'),
(69, 'labdata', '2025-08-07 20:22:33'),
(70, 'data', '2025-08-07 20:51:04'),
(71, 'data', '2025-08-07 20:53:29'),
(72, 'data', '2025-08-07 20:53:50'),
(73, 'data', '2025-08-07 21:16:46'),
(74, 'data', '2025-08-07 21:35:03'),
(75, 'data', '2025-08-07 22:15:44'),
(76, 'data', '2025-08-08 05:20:11'),
(77, 'data', '2025-08-08 06:10:26'),
(78, 'data', '2025-08-08 09:23:11'),
(79, 'data', '2025-08-08 09:24:00'),
(80, 'data', '2025-08-08 09:27:55'),
(81, 'data', '2025-08-08 09:28:15'),
(82, 'data', '2025-08-08 09:29:16'),
(83, 'data', '2025-08-08 09:29:33'),
(84, 'sdfasde4', '2025-08-08 09:37:04'),
(85, 'da', '2025-08-08 15:11:51'),
(86, 'data', '2025-08-08 15:11:56'),
(87, 'data', '2025-08-08 15:13:43'),
(88, 'data', '2025-08-08 15:17:30'),
(89, 'data', '2025-08-08 15:19:50'),
(90, 'labdata', '2025-08-08 15:20:17'),
(91, 'labdata', '2025-08-08 15:20:21'),
(92, 'data', '2025-08-08 15:20:28'),
(93, 'data', '2025-08-08 15:22:01'),
(94, 'data', '2025-08-08 15:26:41'),
(95, 'data', '2025-08-08 15:36:43'),
(96, 'data', '2025-08-08 15:41:35'),
(97, 'data', '2025-08-08 15:42:57'),
(98, 'data', '2025-08-08 15:46:08'),
(99, 'data', '2025-08-08 15:46:46'),
(100, 'data', '2025-08-08 15:55:01'),
(101, 'data', '2025-08-08 15:59:16'),
(102, 'data', '2025-08-08 16:02:53'),
(103, 'data', '2025-08-08 16:09:52'),
(104, 'data', '2025-08-08 16:30:11'),
(105, 'data', '2025-08-08 16:33:43'),
(106, 'data', '2025-08-08 16:34:08'),
(107, 'ldata', '2025-08-08 16:35:07'),
(108, 'data', '2025-08-08 16:39:50'),
(109, 'ldata', '2025-08-08 16:40:43'),
(110, 'ldata', '2025-08-08 16:42:14'),
(111, 'ldata', '2025-08-08 16:46:50'),
(112, 'ldata', '2025-08-08 16:46:58'),
(113, 'data', '2025-08-08 20:09:04'),
(114, 'ldata', '2025-08-09 04:21:48'),
(115, 'ldata', '2025-08-09 04:22:01'),
(116, 'data', '2025-08-09 05:01:53'),
(117, 'ldata', '2025-08-09 05:09:54'),
(118, 'ldata', '2025-08-09 05:46:12'),
(119, 'ldata', '2025-08-11 05:08:25');

-- --------------------------------------------------------

--
-- Table structure for table `merchants`
--

CREATE TABLE `merchants` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `merchants`
--

INSERT INTO `merchants` (`id`, `user_id`, `name`, `created_at`) VALUES
(12, 7, 'Walmart', '2025-08-08 17:28:12'),
(13, 2, 'Target', '2025-08-09 03:10:03');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(1, 'data10@yopmail.com', '330c2bf4a8da06a78beed144d125b0663bcfbf28d46bc8581a1ecf20a756c243', '2025-08-04 08:43:33', '2025-08-03 22:43:33'),
(2, 'data10@yopmail.com', 'faa3e52b102b34f00d6c5f4213b8adabb3d06db01c8ec5ee591ca0f714c056ed', '2025-08-04 08:45:26', '2025-08-03 22:45:26');

-- --------------------------------------------------------

--
-- Table structure for table `registration_attempts`
--

CREATE TABLE `registration_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_attempts`
--

INSERT INTO `registration_attempts` (`id`, `email`, `created_at`) VALUES
(7, 'data15@yopmail.com', '2025-08-07 21:23:00'),
(8, 'data15@yopmail.com', '2025-08-07 21:23:06'),
(9, 'labcoder3@mfence.com', '2025-08-07 21:28:27'),
(10, 'email@gmail.com', '2025-08-07 21:35:33'),
(11, 'email@gmail.com', '2025-08-07 21:40:15'),
(12, 'ojikjkh@jhvhvh.com', '2025-08-07 21:43:28'),
(13, 'sjgd@gmaod.com', '2025-08-07 21:48:41'),
(14, 'email@gmail.com', '2025-08-07 21:56:51'),
(15, 'dsajhg@dkjfg.com', '2025-08-07 22:00:36'),
(16, 'hjasvv@kjhds.com', '2025-08-08 06:11:31'),
(17, 'akjdg@jbss.com', '2025-08-08 09:40:30');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `magnus_user_id` int(11) DEFAULT NULL,
  `sip_id` int(11) DEFAULT NULL,
  `magnus_username` varchar(20) DEFAULT NULL,
  `magnus_password` varchar(255) NOT NULL,
  `sip_domain` varchar(255) NOT NULL DEFAULT '72.60.25.185',
  `caller_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`, `magnus_user_id`, `sip_id`, `magnus_username`, `magnus_password`, `sip_domain`, `caller_id`) VALUES
(2, 'data', 'john_doe@example.com', '$2y$10$1c6dzIfxSv2pwSOHSEMWL.s0rRBQRawps.C1TQ/wEjvd5zKNMmwH6', '2025-08-06 04:12:20', NULL, NULL, NULL, '', '72.60.25.185', NULL),
(5, 'sdfasde4', 'dsajhg@dkjfg.com', '$2y$10$lL9JVvCIx1m2xTo05V5MTOhVRQJmBjFARt5x/N5vRJrjJm/gcFqey', '2025-08-08 05:00:36', NULL, NULL, 'sdfasde4', '', '72.60.25.185', NULL),
(6, 'nbsdbfjh', 'hjasvv@kjhds.com', '$2y$10$6aeJGQwe4M3CbT.bsRIewO3WqQElG0NlMFo74leW7WJo6E1yA5HpK', '2025-08-08 13:11:31', 20, 19, 'nbsdbfjh', '', '72.60.25.185', NULL),
(7, 'ldata', 'akjdg@jbss.com', '$2y$10$PdKPM87/f2.l5CwNLqAPi.N.j/671YEH6sshGjidVsk2Qd3U4tDUq', '2025-08-08 16:40:30', 21, 20, 'ldata', 'Data1017$$', '72.60.25.185', '12105318722');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `caller_ids`
--
ALTER TABLE `caller_ids`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `calls`
--
ALTER TABLE `calls`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `deleted_cdrs`
--
ALTER TABLE `deleted_cdrs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cdr_user` (`cdr_id`,`user_id`);

--
-- Indexes for table `dtmf_inputs`
--
ALTER TABLE `dtmf_inputs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `call_id` (`call_id`);

--
-- Indexes for table `institutions`
--
ALTER TABLE `institutions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `ivr_calls`
--
ALTER TABLE `ivr_calls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `ivr_profiles`
--
ALTER TABLE `ivr_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `merchants`
--
ALTER TABLE `merchants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`);

--
-- Indexes for table `registration_attempts`
--
ALTER TABLE `registration_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `caller_ids`
--
ALTER TABLE `caller_ids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calls`
--
ALTER TABLE `calls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `deleted_cdrs`
--
ALTER TABLE `deleted_cdrs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dtmf_inputs`
--
ALTER TABLE `dtmf_inputs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `institutions`
--
ALTER TABLE `institutions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ivr_calls`
--
ALTER TABLE `ivr_calls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ivr_profiles`
--
ALTER TABLE `ivr_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT for table `merchants`
--
ALTER TABLE `merchants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `registration_attempts`
--
ALTER TABLE `registration_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contacts`
--
ALTER TABLE `contacts`
  ADD CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `dtmf_inputs`
--
ALTER TABLE `dtmf_inputs`
  ADD CONSTRAINT `dtmf_inputs_ibfk_1` FOREIGN KEY (`call_id`) REFERENCES `calls` (`id`);

--
-- Constraints for table `institutions`
--
ALTER TABLE `institutions`
  ADD CONSTRAINT `institutions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ivr_calls`
--
ALTER TABLE `ivr_calls`
  ADD CONSTRAINT `ivr_calls_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ivr_profiles`
--
ALTER TABLE `ivr_profiles`
  ADD CONSTRAINT `ivr_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `merchants`
--
ALTER TABLE `merchants`
  ADD CONSTRAINT `merchants_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `password_resets`
--
-- ALTER TABLE `password_resets`
--   ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`email`) REFERENCES `users` (`email`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
