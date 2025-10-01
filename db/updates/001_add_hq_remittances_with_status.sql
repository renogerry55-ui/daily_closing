CREATE TABLE IF NOT EXISTS `hq_remittances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `outlet_id` INT UNSIGNED NOT NULL,
  `submission_id` BIGINT UNSIGNED NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `received_at` DATE NOT NULL,
  `status` ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
  `approved_by` INT UNSIGNED NULL,
  `approved_at` DATETIME NULL,
  `bank_ref` VARCHAR(120) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hr_outlet` (`outlet_id`),
  KEY `idx_hr_status_date` (`status`,`received_at`),
  CONSTRAINT `fk_hr_outlet` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
