ALTER TABLE `submissions`
  ADD INDEX `idx_submissions_mgr_outlet_date_status` (`manager_id`, `outlet_id`, `date`, `status`);
