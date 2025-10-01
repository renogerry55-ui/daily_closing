-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: daily_closing
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Table structure for table `hq_batch_files`
--

DROP TABLE IF EXISTS `hq_batch_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hq_batch_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hq_batch_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `size_bytes` bigint(20) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hq_files_batch` (`hq_batch_id`),
  CONSTRAINT `fk_hq_files_batch` FOREIGN KEY (`hq_batch_id`) REFERENCES `hq_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hq_batch_submissions`
--

DROP TABLE IF EXISTS `hq_batch_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hq_batch_submissions` (
  `hq_batch_id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  PRIMARY KEY (`hq_batch_id`,`submission_id`),
  KEY `idx_hq_sub_submission` (`submission_id`),
  CONSTRAINT `fk_hq_sub_batch` FOREIGN KEY (`hq_batch_id`) REFERENCES `hq_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hq_sub_submit` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hq_batches`
--

DROP TABLE IF EXISTS `hq_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hq_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manager_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `status` enum('submitted','approved','rejected','recorded') NOT NULL DEFAULT 'submitted',
  `overall_total_income` decimal(14,2) NOT NULL DEFAULT 0.00,
  `overall_total_expenses` decimal(14,2) NOT NULL DEFAULT 0.00,
  `overall_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hq_batches_mgr_date` (`manager_id`,`report_date`),
  CONSTRAINT `fk_hq_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hq_package_items`
--

DROP TABLE IF EXISTS `hq_package_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hq_package_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint(20) unsigned NOT NULL,
  `submission_id` int(11) NOT NULL,
  `pass_to_hq` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hq_package_items_package_submission` (`package_id`,`submission_id`),
  KEY `idx_hq_package_items_package_id` (`package_id`),
  KEY `idx_hq_package_items_submission_id` (`submission_id`),
  CONSTRAINT `fk_hq_package_items_package` FOREIGN KEY (`package_id`) REFERENCES `hq_packages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hq_package_items_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hq_packages`
--

DROP TABLE IF EXISTS `hq_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hq_packages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_date` date NOT NULL,
  `status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `total_income` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hq_packages_package_date` (`package_date`),
  KEY `idx_hq_packages_status` (`status`),
  KEY `idx_hq_packages_created_by` (`created_by`),
  KEY `idx_hq_packages_approved_by` (`approved_by`),
  CONSTRAINT `fk_hq_packages_approved_by_users` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_hq_packages_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `outlets`
--

DROP TABLE IF EXISTS `outlets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `outlets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_outlets_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `receipts`
--

DROP TABLE IF EXISTS `receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `size_bytes` bigint(20) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_receipts_submission` (`submission_id`),
  CONSTRAINT `fk_receipts_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submission_items`
--

DROP TABLE IF EXISTS `submission_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submission_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `submission_id` int(11) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(64) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_items_submission` (`submission_id`,`type`),
  KEY `idx_items_type_cat` (`type`,`category`),
  CONSTRAINT `fk_items_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `submissions`
--

DROP TABLE IF EXISTS `submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manager_id` int(11) NOT NULL,
  `outlet_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('pending','approved','rejected','recorded') NOT NULL DEFAULT 'pending',
  `total_income` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_expenses` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `account_comment` text DEFAULT NULL,
  `submitted_to_hq_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_submissions_outlet_date` (`outlet_id`,`date`),
  KEY `idx_submissions_status_date` (`status`,`date`),
  KEY `idx_submissions_manager_date` (`manager_id`,`date`),
  CONSTRAINT `fk_sub_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_sub_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `test_table_mig`
--

DROP TABLE IF EXISTS `test_table_mig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `test_table_mig` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_outlets`
--

DROP TABLE IF EXISTS `user_outlets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_outlets` (
  `user_id` int(11) NOT NULL,
  `outlet_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`outlet_id`),
  KEY `fk_uo_outlet` (`outlet_id`),
  CONSTRAINT `fk_uo_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('manager','account','finance','ceo','admin') NOT NULL,
  `outlet_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `fk_users_outlet_id` (`outlet_id`),
  CONSTRAINT `fk_users_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-01 15:28:07
