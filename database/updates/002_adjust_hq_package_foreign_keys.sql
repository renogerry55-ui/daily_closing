-- phpMyAdmin SQL Dump
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2025 at 05:19 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = '+00:00';

DROP TABLE IF EXISTS `hq_batches`;
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

DROP TABLE IF EXISTS `hq_batch_files`;
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

DROP TABLE IF EXISTS `hq_batch_submissions`;
CREATE TABLE `hq_batch_submissions` (
  `hq_batch_id` int(11) NOT NULL,
  `submission_id` int(11) NOT NULL,
  PRIMARY KEY (`hq_batch_id`,`submission_id`),
  KEY `idx_hq_sub_submission` (`submission_id`),
  CONSTRAINT `fk_hq_sub_batch` FOREIGN KEY (`hq_batch_id`) REFERENCES `hq_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hq_sub_submit` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `outlets`;
CREATE TABLE `outlets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_outlets_name` (`name`)
  ) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `receipts`;
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

DROP TABLE IF EXISTS `submissions`;
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

DROP TABLE IF EXISTS `submission_items`;
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

DROP TABLE IF EXISTS `users`;
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

DROP TABLE IF EXISTS `user_outlets`;
CREATE TABLE `user_outlets` (
  `user_id` int(11) NOT NULL,
  `outlet_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`outlet_id`),
  KEY `fk_uo_outlet` (`outlet_id`),
  CONSTRAINT `fk_uo_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
