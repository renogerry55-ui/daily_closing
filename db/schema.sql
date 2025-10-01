-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: daily_closing
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
-- Dumping data for table `hq_batch_files`
--

LOCK TABLES `hq_batch_files` WRITE;
/*!40000 ALTER TABLE `hq_batch_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `hq_batch_files` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `hq_batch_submissions`
--

LOCK TABLES `hq_batch_submissions` WRITE;
/*!40000 ALTER TABLE `hq_batch_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `hq_batch_submissions` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `hq_batches`
--

LOCK TABLES `hq_batches` WRITE;
/*!40000 ALTER TABLE `hq_batches` DISABLE KEYS */;
INSERT INTO `hq_batches` VALUES (1,1,'2025-10-01','submitted',11000.00,1010.00,9990.00,'','2025-10-01 02:31:07','2025-10-01 02:31:07');
/*!40000 ALTER TABLE `hq_batches` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `hq_package_items`
--

LOCK TABLES `hq_package_items` WRITE;
/*!40000 ALTER TABLE `hq_package_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `hq_package_items` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `hq_packages`
--

LOCK TABLES `hq_packages` WRITE;
/*!40000 ALTER TABLE `hq_packages` DISABLE KEYS */;
/*!40000 ALTER TABLE `hq_packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hq_remittances`
--

DROP TABLE IF EXISTS `hq_remittances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hq_remittances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` int(11) NOT NULL,
  `submission_id` int(11) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `received_at` date NOT NULL,
  `status` enum('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `bank_ref` varchar(120) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hr_outlet` (`outlet_id`),
  KEY `idx_hr_status_date` (`status`,`received_at`),
  KEY `idx_hr_outlet_date` (`outlet_id`,`received_at`),
  KEY `fk_hr_submission` (`submission_id`),
  KEY `fk_hr_approved_by` (`approved_by`),
  CONSTRAINT `fk_hr_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_hr_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`),
  CONSTRAINT `fk_hr_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hq_remittances`
--

LOCK TABLES `hq_remittances` WRITE;
/*!40000 ALTER TABLE `hq_remittances` DISABLE KEYS */;
/*!40000 ALTER TABLE `hq_remittances` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `outlets`
--

LOCK TABLES `outlets` WRITE;
/*!40000 ALTER TABLE `outlets` DISABLE KEYS */;
INSERT INTO `outlets` VALUES (1,'Outlet Marina','active'),(2,'Outlet Tudan','active');
/*!40000 ALTER TABLE `outlets` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `receipts`
--

LOCK TABLES `receipts` WRITE;
/*!40000 ALTER TABLE `receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `receipts` ENABLE KEYS */;
UNLOCK TABLES;

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submission_items`
--

LOCK TABLES `submission_items` WRITE;
/*!40000 ALTER TABLE `submission_items` DISABLE KEYS */;
INSERT INTO `submission_items` VALUES (5,3,'income','MP',200.00,NULL,'2025-10-01 14:09:44'),(6,3,'income','Deposit',1000.00,NULL,'2025-10-01 14:09:44'),(7,3,'income','Berhad',30.00,NULL,'2025-10-01 14:09:44'),(8,3,'income','Market',40.00,NULL,'2025-10-01 14:09:44'),(9,3,'expense','Staff Salary',200.00,NULL,'2025-10-01 14:09:44');
/*!40000 ALTER TABLE `submission_items` ENABLE KEYS */;
UNLOCK TABLES;

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
  KEY `idx_submissions_mgr_outlet_date_status` (`manager_id`,`outlet_id`,`date`,`status`),
  CONSTRAINT `fk_sub_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_sub_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submissions`
--

LOCK TABLES `submissions` WRITE;
/*!40000 ALTER TABLE `submissions` DISABLE KEYS */;
INSERT INTO `submissions` VALUES (2,1,2,'2025-10-01','pending',1000.00,10.00,990.00,'testing',NULL,'2025-10-01 10:31:07','2025-10-01 01:00:02','2025-10-01 02:31:07'),(3,1,1,'2025-10-01','pending',1270.00,200.00,1070.00,'',NULL,NULL,'2025-10-01 14:09:44','2025-10-01 14:09:44');
/*!40000 ALTER TABLE `submissions` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `test_table_mig`
--

LOCK TABLES `test_table_mig` WRITE;
/*!40000 ALTER TABLE `test_table_mig` DISABLE KEYS */;
/*!40000 ALTER TABLE `test_table_mig` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `user_outlets`
--

LOCK TABLES `user_outlets` WRITE;
/*!40000 ALTER TABLE `user_outlets` DISABLE KEYS */;
INSERT INTO `user_outlets` VALUES (1,1),(1,2);
/*!40000 ALTER TABLE `user_outlets` ENABLE KEYS */;
UNLOCK TABLES;

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

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Test Manager','manager1','$2y$10$HewwLNjXPk.boO9wIuwble3gis.tB0xH3708nxN2P23.o2ZE7b4OW','manager',NULL),(2,'Test Account','account1','$2y$10$HewwLNjXPk.boO9wIuwble3gis.tB0xH3708nxN2P23.o2ZE7b4OW','account',NULL),(3,'Test Finance','finance1','$2y$10$HewwLNjXPk.boO9wIuwble3gis.tB0xH3708nxN2P23.o2ZE7b4OW','finance',NULL),(4,'Test CEO','ceo1','$2y$10$HewwLNjXPk.boO9wIuwble3gis.tB0xH3708nxN2P23.o2ZE7b4OW','ceo',NULL),(5,'Test Admin','admin1','$2y$10$HewwLNjXPk.boO9wIuwble3gis.tB0xH3708nxN2P23.o2ZE7b4OW','admin',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-02  0:07:58
