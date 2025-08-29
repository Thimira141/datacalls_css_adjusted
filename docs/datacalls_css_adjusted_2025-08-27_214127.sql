-- MariaDB dump 10.19-11.3.2-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: datacalls_css_adjusted
-- ------------------------------------------------------
-- Server version	11.3.2-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `caller_ids`
--

DROP TABLE IF EXISTS `caller_ids`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `caller_ids` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `phone_number` varchar(14) NOT NULL,
  `verification_code` varchar(4) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `caller_ids`
--

/*!40000 ALTER TABLE `caller_ids` DISABLE KEYS */;
/*!40000 ALTER TABLE `caller_ids` ENABLE KEYS */;

--
-- Table structure for table `calls`
--

DROP TABLE IF EXISTS `calls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `tts_script` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calls`
--

/*!40000 ALTER TABLE `calls` DISABLE KEYS */;
/*!40000 ALTER TABLE `calls` ENABLE KEYS */;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
INSERT INTO `contacts` VALUES
(22,7,'Banger','18005318722','2025-08-08 19:05:15'),
(23,2,'Chase','18005318722','2025-08-09 03:09:39'),
(24,2,'Banger','13058399485','2025-08-09 03:09:47'),
(26,7,'usaa','12105318722','2025-08-10 14:27:49'),
(27,7,'MArk','12105318722','2025-08-11 12:09:50');
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;

--
-- Table structure for table `deleted_cdrs`
--

DROP TABLE IF EXISTS `deleted_cdrs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `deleted_cdrs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cdr_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cdr_user` (`cdr_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `deleted_cdrs`
--

/*!40000 ALTER TABLE `deleted_cdrs` DISABLE KEYS */;
/*!40000 ALTER TABLE `deleted_cdrs` ENABLE KEYS */;

--
-- Table structure for table `dtmf_inputs`
--

DROP TABLE IF EXISTS `dtmf_inputs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dtmf_inputs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `dtmf_keys` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `call_id` (`call_id`),
  CONSTRAINT `dtmf_inputs_ibfk_1` FOREIGN KEY (`call_id`) REFERENCES `calls` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dtmf_inputs`
--

/*!40000 ALTER TABLE `dtmf_inputs` DISABLE KEYS */;
/*!40000 ALTER TABLE `dtmf_inputs` ENABLE KEYS */;

--
-- Table structure for table `institutions`
--

DROP TABLE IF EXISTS `institutions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `institutions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `institutions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `institutions`
--

/*!40000 ALTER TABLE `institutions` DISABLE KEYS */;
INSERT INTO `institutions` VALUES
(11,2,'USAA','2025-08-09 03:09:56'),
(12,7,'USAA','2025-08-10 14:27:56'),
(14,8,'usaa','2025-08-21 11:42:59');
/*!40000 ALTER TABLE `institutions` ENABLE KEYS */;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `payment_id` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(50) NOT NULL,
  `created_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;

--
-- Table structure for table `ivr_calls`
--

DROP TABLE IF EXISTS `ivr_calls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ivr_calls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `institution_name` varchar(255) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_number` varchar(20) NOT NULL,
  `caller_id` varchar(20) NOT NULL,
  `callback_number` varchar(20) NOT NULL,
  `merchant_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('initiated','completed','failed') DEFAULT 'initiated',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ivr_calls_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ivr_calls`
--

/*!40000 ALTER TABLE `ivr_calls` DISABLE KEYS */;
/*!40000 ALTER TABLE `ivr_calls` ENABLE KEYS */;

--
-- Table structure for table `ivr_profiles`
--

DROP TABLE IF EXISTS `ivr_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ivr_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `profile_name` varchar(255) NOT NULL,
  `institution_name` varchar(255) NOT NULL,
  `caller_id` varchar(20) NOT NULL,
  `callback_number` varchar(20) NOT NULL,
  `merchant_name` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `magnus_ivr_id` int(11) DEFAULT NULL,
  `tts_audio_url` text DEFAULT NULL COMMENT 'tts audio file url',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ivr_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ivr_profiles`
--

/*!40000 ALTER TABLE `ivr_profiles` DISABLE KEYS */;
INSERT INTO `ivr_profiles` VALUES
(4,8,'Chase','Chase','18005318722','13058399485','Target',350.00,NULL,NULL),
(7,8,'USAA','USAA','12105318722','18005318722','Walmart',350.87,NULL,NULL),
(8,8,'testing ivr profile','test institution','17759802006','17759802006','merchant name',1000.00,NULL,NULL);
/*!40000 ALTER TABLE `ivr_profiles` ENABLE KEYS */;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=139 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES
(1,'data','2025-08-03 22:25:55'),
(2,'fukme','2025-08-03 22:27:03'),
(3,'fukme','2025-08-03 22:30:48'),
(4,'fukme','2025-08-03 22:30:54'),
(5,'data','2025-08-03 22:31:16'),
(6,'data','2025-08-03 22:33:52'),
(7,'data','2025-08-03 22:43:23'),
(8,'data','2025-08-03 23:05:43'),
(9,'data','2025-08-03 23:29:16'),
(10,'data','2025-08-03 23:41:02'),
(11,'data','2025-08-03 23:43:37'),
(12,'labdata','2025-08-04 07:12:52'),
(13,'data','2025-08-04 07:12:59'),
(14,'data','2025-08-04 07:13:01'),
(15,'data','2025-08-04 07:13:06'),
(16,'testuser','2025-08-04 08:18:40'),
(17,'data','2025-08-04 08:18:46'),
(18,'data','2025-08-04 08:19:11'),
(19,'data','2025-08-04 08:19:17'),
(20,'data','2025-08-04 08:19:37'),
(21,'data','2025-08-04 08:24:34'),
(22,'data','2025-08-04 08:28:27'),
(23,'data','2025-08-04 08:28:32'),
(24,'data','2025-08-04 08:31:56'),
(25,'data','2025-08-04 08:33:26'),
(26,'data','2025-08-04 08:34:43'),
(27,'data','2025-08-04 08:34:49'),
(28,'labdata','2025-08-04 08:38:59'),
(29,'labdata','2025-08-04 08:39:52'),
(30,'labdata','2025-08-04 08:39:57'),
(31,'labdata','2025-08-04 08:41:07'),
(32,'labdata','2025-08-04 08:43:34'),
(33,'labdata','2025-08-04 08:43:39'),
(34,'data','2025-08-04 09:00:42'),
(35,'data','2025-08-04 13:34:18'),
(36,'data','2025-08-04 16:21:25'),
(37,'data','2025-08-04 16:21:33'),
(38,'data','2025-08-04 16:38:42'),
(39,'data','2025-08-05 09:11:54'),
(40,'data','2025-08-05 12:24:46'),
(41,'data','2025-08-05 20:15:02'),
(42,'data','2025-08-05 20:17:11'),
(43,'data','2025-08-05 20:18:03'),
(44,'labdata','2025-08-05 21:08:44'),
(45,'data','2025-08-05 21:12:45'),
(46,'data','2025-08-05 22:18:51'),
(47,'data','2025-08-05 22:33:53'),
(48,'data','2025-08-06 09:03:32'),
(49,'data','2025-08-06 09:09:50'),
(50,'data','2025-08-06 09:09:58'),
(51,'data','2025-08-07 19:40:08'),
(52,'data','2025-08-07 19:40:16'),
(53,'data','2025-08-07 19:40:17'),
(54,'data','2025-08-07 19:40:39'),
(55,'data','2025-08-07 19:40:47'),
(56,'data','2025-08-07 19:40:49'),
(57,'labdata','2025-08-07 19:41:48'),
(58,'labdata','2025-08-07 20:09:39'),
(59,'labdata','2025-08-07 20:10:06'),
(60,'labdata','2025-08-07 20:10:08'),
(61,'data','2025-08-07 20:10:21'),
(62,'data','2025-08-07 20:15:35'),
(63,'data','2025-08-07 20:20:12'),
(64,'data','2025-08-07 20:20:13'),
(65,'data','2025-08-07 20:20:28'),
(66,'labdata','2025-08-07 20:22:20'),
(67,'labdata','2025-08-07 20:22:23'),
(68,'labdata','2025-08-07 20:22:29'),
(69,'labdata','2025-08-07 20:22:33'),
(70,'data','2025-08-07 20:51:04'),
(71,'data','2025-08-07 20:53:29'),
(72,'data','2025-08-07 20:53:50'),
(73,'data','2025-08-07 21:16:46'),
(74,'data','2025-08-07 21:35:03'),
(75,'data','2025-08-07 22:15:44'),
(76,'data','2025-08-08 05:20:11'),
(77,'data','2025-08-08 06:10:26'),
(78,'data','2025-08-08 09:23:11'),
(79,'data','2025-08-08 09:24:00'),
(80,'data','2025-08-08 09:27:55'),
(81,'data','2025-08-08 09:28:15'),
(82,'data','2025-08-08 09:29:16'),
(83,'data','2025-08-08 09:29:33'),
(84,'sdfasde4','2025-08-08 09:37:04'),
(85,'da','2025-08-08 15:11:51'),
(86,'data','2025-08-08 15:11:56'),
(87,'data','2025-08-08 15:13:43'),
(88,'data','2025-08-08 15:17:30'),
(89,'data','2025-08-08 15:19:50'),
(90,'labdata','2025-08-08 15:20:17'),
(91,'labdata','2025-08-08 15:20:21'),
(92,'data','2025-08-08 15:20:28'),
(93,'data','2025-08-08 15:22:01'),
(94,'data','2025-08-08 15:26:41'),
(95,'data','2025-08-08 15:36:43'),
(96,'data','2025-08-08 15:41:35'),
(97,'data','2025-08-08 15:42:57'),
(98,'data','2025-08-08 15:46:08'),
(99,'data','2025-08-08 15:46:46'),
(100,'data','2025-08-08 15:55:01'),
(101,'data','2025-08-08 15:59:16'),
(102,'data','2025-08-08 16:02:53'),
(103,'data','2025-08-08 16:09:52'),
(104,'data','2025-08-08 16:30:11'),
(105,'data','2025-08-08 16:33:43'),
(106,'data','2025-08-08 16:34:08'),
(107,'ldata','2025-08-08 16:35:07'),
(108,'data','2025-08-08 16:39:50'),
(109,'ldata','2025-08-08 16:40:43'),
(110,'ldata','2025-08-08 16:42:14'),
(111,'ldata','2025-08-08 16:46:50'),
(112,'ldata','2025-08-08 16:46:58'),
(113,'data','2025-08-08 20:09:04'),
(114,'ldata','2025-08-09 04:21:48'),
(115,'ldata','2025-08-09 04:22:01'),
(116,'data','2025-08-09 05:01:53'),
(117,'ldata','2025-08-09 05:09:54'),
(118,'ldata','2025-08-09 05:46:12'),
(119,'ldata','2025-08-11 05:08:25'),
(120,'thimirada','2025-08-14 03:31:09'),
(121,'thimirada','2025-08-14 03:40:56'),
(122,'thimiradac','2025-08-14 04:29:24'),
(123,'thimiradac','2025-08-14 04:29:43'),
(124,'thimirada','2025-08-14 04:30:19'),
(125,'thimirada','2025-08-14 04:32:01'),
(126,'thimiradac','2025-08-14 04:32:21'),
(127,'thimirada','2025-08-14 04:55:34'),
(128,'thimirada','2025-08-14 04:56:14'),
(129,'thimirada','2025-08-14 22:59:27'),
(130,'thimirada','2025-08-15 04:07:54'),
(131,'thimirada','2025-08-21 14:14:09'),
(132,'thimirada','2025-08-21 21:49:14'),
(133,'thimirada','2025-08-23 18:52:44'),
(134,'thimirada','2025-08-25 10:49:38'),
(135,'ldata','2025-08-25 10:54:09'),
(136,'thimirada','2025-08-25 19:06:38'),
(137,'thimirada','2025-08-25 21:14:50'),
(138,'thimirada','2025-08-25 21:19:45');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;

--
-- Table structure for table `merchants`
--

DROP TABLE IF EXISTS `merchants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `merchants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `merchants_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchants`
--

/*!40000 ALTER TABLE `merchants` DISABLE KEYS */;
INSERT INTO `merchants` VALUES
(12,7,'Walmart','2025-08-08 17:28:12'),
(13,2,'Target','2025-08-09 03:10:03'),
(14,8,'target','2025-08-21 11:43:06');
/*!40000 ALTER TABLE `merchants` ENABLE KEYS */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES
(1,'data10@yopmail.com','330c2bf4a8da06a78beed144d125b0663bcfbf28d46bc8581a1ecf20a756c243','2025-08-04 08:43:33','2025-08-03 22:43:33'),
(2,'data10@yopmail.com','faa3e52b102b34f00d6c5f4213b8adabb3d06db01c8ec5ee591ca0f714c056ed','2025-08-04 08:45:26','2025-08-03 22:45:26');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;

--
-- Table structure for table `registration_attempts`
--

DROP TABLE IF EXISTS `registration_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `registration_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registration_attempts`
--

/*!40000 ALTER TABLE `registration_attempts` DISABLE KEYS */;
INSERT INTO `registration_attempts` VALUES
(7,'data15@yopmail.com','2025-08-07 21:23:00'),
(8,'data15@yopmail.com','2025-08-07 21:23:06'),
(9,'labcoder3@mfence.com','2025-08-07 21:28:27'),
(10,'email@gmail.com','2025-08-07 21:35:33'),
(11,'email@gmail.com','2025-08-07 21:40:15'),
(12,'ojikjkh@jhvhvh.com','2025-08-07 21:43:28'),
(13,'sjgd@gmaod.com','2025-08-07 21:48:41'),
(14,'email@gmail.com','2025-08-07 21:56:51'),
(15,'dsajhg@dkjfg.com','2025-08-07 22:00:36'),
(16,'hjasvv@kjhds.com','2025-08-08 06:11:31'),
(17,'akjdg@jbss.com','2025-08-08 09:40:30'),
(18,'thimirad865@gmail.com','2025-08-11 22:55:38');
/*!40000 ALTER TABLE `registration_attempts` ENABLE KEYS */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `magnus_user_id` int(11) DEFAULT NULL,
  `sip_id` int(11) DEFAULT NULL,
  `magnus_username` varchar(20) DEFAULT NULL,
  `magnus_password` varchar(255) NOT NULL,
  `sip_domain` varchar(255) NOT NULL DEFAULT '72.60.25.185',
  `caller_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(2,'data','john_doe@example.com','$2y$10$1c6dzIfxSv2pwSOHSEMWL.s0rRBQRawps.C1TQ/wEjvd5zKNMmwH6','2025-08-06 04:12:20',NULL,NULL,NULL,'','72.60.25.185',NULL),
(5,'sdfasde4','dsajhg@dkjfg.com','$2y$10$lL9JVvCIx1m2xTo05V5MTOhVRQJmBjFARt5x/N5vRJrjJm/gcFqey','2025-08-08 05:00:36',NULL,NULL,'sdfasde4','','72.60.25.185',NULL),
(6,'nbsdbfjh','hjasvv@kjhds.com','$2y$10$6aeJGQwe4M3CbT.bsRIewO3WqQElG0NlMFo74leW7WJo6E1yA5HpK','2025-08-08 13:11:31',20,19,'nbsdbfjh','','72.60.25.185',NULL),
(7,'ldata','akjdg@jbss.com','$2y$10$PdKPM87/f2.l5CwNLqAPi.N.j/671YEH6sshGjidVsk2Qd3U4tDUq','2025-08-08 16:40:30',21,20,'ldata','Data1017$$','72.60.25.185','12105318722'),
(8,'thimirada','thimirad865@gmail.com','$2y$10$Gp5q3ArMHs1zzeKwSHQaEOvn1hp8i1UNdaHdT9n9DScs/CX7zWMpS','2025-08-11 17:25:39',22,21,'thimirada','a5M3wFH!PuY!FKk','72.60.25.185',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;

--
-- Dumping routines for database 'datacalls_css_adjusted'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-27 21:41:30
