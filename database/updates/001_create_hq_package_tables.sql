-- Assumptions & Notes:
-- * Target database is MySQL 8.0+.
-- * Confirmed via `SHOW CREATE TABLE users;` that the table uses `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
--   with a primary key column defined as ``id` int(10) NOT NULL AUTO_INCREMENT`.
-- * Confirmed via `SHOW CREATE TABLE submissions;` that the table uses `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
--   with a primary key column defined as ``id` int(10) NOT NULL AUTO_INCREMENT`.
-- * Status lifecycle for HQ packages is `draft`, `submitted`, `approved`, or `rejected`; default is `draft`.
-- * Monetary totals are stored with DECIMAL(12,2) precision, reflecting PHP's rounding before persistence.
-- * `pass_to_hq` is treated as a boolean flag (0 = no, 1 = yes).

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `hq_packages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_date` DATE NOT NULL,
  `status` ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `total_income` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_expenses` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_by` INT NOT NULL,
  `approved_by` INT DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hq_packages_package_date` (`package_date`),
  KEY `idx_hq_packages_status` (`status`),
  KEY `idx_hq_packages_created_by` (`created_by`),
  KEY `idx_hq_packages_approved_by` (`approved_by`),
  CONSTRAINT `fk_hq_packages_created_by_users`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON UPDATE CASCADE,
  CONSTRAINT `fk_hq_packages_approved_by_users`
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hq_package_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id` BIGINT UNSIGNED NOT NULL,
  `submission_id` INT NOT NULL,
  `pass_to_hq` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hq_package_items_package_submission` (`package_id`, `submission_id`),
  KEY `idx_hq_package_items_submission_id` (`submission_id`),
  CONSTRAINT `fk_hq_package_items_package`
    FOREIGN KEY (`package_id`) REFERENCES `hq_packages`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_hq_package_items_submission`
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
