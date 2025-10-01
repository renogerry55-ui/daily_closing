-- Create hq_packages + indexes (safe if already exists)
CREATE TABLE IF NOT EXISTS `hq_packages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_date` DATE NOT NULL,
  `status` ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `total_income` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_expenses` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_by` INT UNSIGNED NOT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hq_packages_package_date` (`package_date`),
  KEY `idx_hq_packages_status` (`status`),
  KEY `idx_hq_packages_created_by` (`created_by`),
  KEY `idx_hq_packages_approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: add FKs if users(id) exists and is INT UNSIGNED
-- ALTER TABLE `hq_packages`
--   ADD CONSTRAINT `fk_hq_packages_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
--   ADD CONSTRAINT `fk_hq_packages_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;
