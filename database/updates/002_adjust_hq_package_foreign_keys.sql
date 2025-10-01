-- This migration normalizes the HQ package foreign key columns based on the
-- confirmed production schema as of the phpMyAdmin export dated Oct 01, 2025.
-- In that dump both `users` and `submissions` are `ENGINE=InnoDB` tables with
-- primary keys defined as `id INT(11) NOT NULL AUTO_INCREMENT` (signed).
--
-- To keep the references consistent we ensure the parent tables remain
-- InnoDB, align the referencing column definitions with the signed INT(11)
-- primary keys, and then recreate the foreign keys.

START TRANSACTION;

-- Ensure the parent tables are using InnoDB so FK enforcement is available.
ALTER TABLE `users` ENGINE=InnoDB;
ALTER TABLE `submissions` ENGINE=InnoDB;

-- Drop constraints so the columns can be modified safely.
ALTER TABLE `hq_packages`
  DROP FOREIGN KEY `fk_hq_packages_created_by_users`,
  DROP FOREIGN KEY `fk_hq_packages_approved_by_users`;

ALTER TABLE `hq_package_items`
  DROP FOREIGN KEY `fk_hq_package_items_submission`;

-- Align the foreign key columns with the referenced INT(11) definition.
ALTER TABLE `hq_packages`
  MODIFY `created_by` INT NOT NULL,
  MODIFY `approved_by` INT DEFAULT NULL;

ALTER TABLE `hq_package_items`
  MODIFY `submission_id` INT NOT NULL;

-- Recreate foreign keys with the original cascading behaviour.
ALTER TABLE `hq_packages`
  ADD CONSTRAINT `fk_hq_packages_created_by_users`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_hq_packages_approved_by_users`
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`)
    ON UPDATE CASCADE;

ALTER TABLE `hq_package_items`
  ADD CONSTRAINT `fk_hq_package_items_submission`
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`)
    ON UPDATE CASCADE;

COMMIT;
