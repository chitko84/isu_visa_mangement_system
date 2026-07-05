-- Local-only test accounts for XAMPP/demo use.
-- These accounts are intentionally documented in README.md.
-- Do not use these passwords in production.

INSERT INTO `school` (`school_id`, `school_name`) VALUES
(9001, 'Demo School of Business')
ON DUPLICATE KEY UPDATE
  `school_name` = VALUES(`school_name`);

INSERT INTO `program` (`program_id`, `school_id`, `program_name`, `level`, `faculty`, `duration_years`) VALUES
(9001, 9001, 'Demo Bachelor of Business Administration', 'Degree', 'Business', 3)
ON DUPLICATE KEY UPDATE
  `school_id` = VALUES(`school_id`),
  `program_name` = VALUES(`program_name`),
  `level` = VALUES(`level`),
  `faculty` = VALUES(`faculty`),
  `duration_years` = VALUES(`duration_years`);

INSERT INTO `country` (`country_id`, `country_name`, `region`) VALUES
(9001, 'Demo Country', 'Asia')
ON DUPLICATE KEY UPDATE
  `country_name` = VALUES(`country_name`),
  `region` = VALUES(`region`);

INSERT INTO `staff` (`staff_id`, `first_name`, `last_name`, `email`, `password`, `role`, `department`, `phone`, `status`, `profile_photo`) VALUES
(9001, 'Demo', 'Admin', 'admin.demo@isu.local', '$2y$10$A69SF0NXrS.6pcVTbPfSM.OckHVBfhbQWHwETX1qKrrHekTMkhj9m', 'admin', 'International Student Services Unit', '0100009001', 'Active', NULL),
(9002, 'Demo', 'Staff', 'staff.demo@isu.local', '$2y$10$Jk.b46i6DmpDy6/XM96FtO8a.qDd4a02Pmzy3A2hD1OUbsR46ZYYK', 'staff', 'International Student Services Unit', '0100009002', 'Active', NULL)
ON DUPLICATE KEY UPDATE
  `first_name` = VALUES(`first_name`),
  `last_name` = VALUES(`last_name`),
  `password` = VALUES(`password`),
  `role` = VALUES(`role`),
  `department` = VALUES(`department`),
  `phone` = VALUES(`phone`),
  `status` = VALUES(`status`),
  `profile_photo` = VALUES(`profile_photo`);

INSERT INTO `student` (`student_id`, `program_id`, `first_name`, `last_name`, `phone`, `email`, `status`, `student_type`, `password`, `profile_photo`) VALUES
(900001, 9001, 'Demo', 'Student', '0100009003', 'student.demo@student.aiu.edu.my', 'Active', 'UG', '$2y$10$F6lxzbqCiGB3tPKI7Tzm0O644kakJ48ZRmzrTuYWs6GYptNEl6AjS', NULL)
ON DUPLICATE KEY UPDATE
  `program_id` = VALUES(`program_id`),
  `first_name` = VALUES(`first_name`),
  `last_name` = VALUES(`last_name`),
  `phone` = VALUES(`phone`),
  `status` = VALUES(`status`),
  `student_type` = VALUES(`student_type`),
  `password` = VALUES(`password`),
  `profile_photo` = VALUES(`profile_photo`);

INSERT INTO `undergraduate` (`student_id`, `high_school_name`, `admission_score`, `scholarship_flag`) VALUES
(900001, 'Demo International School', 88.00, 0)
ON DUPLICATE KEY UPDATE
  `high_school_name` = VALUES(`high_school_name`),
  `admission_score` = VALUES(`admission_score`),
  `scholarship_flag` = VALUES(`scholarship_flag`);

INSERT INTO `nationality` (`student_id`, `country_id`, `acquired_date`, `is_primary`) VALUES
(900001, 9001, '2026-01-01', 1)
ON DUPLICATE KEY UPDATE
  `acquired_date` = VALUES(`acquired_date`),
  `is_primary` = VALUES(`is_primary`);

INSERT INTO `student_visa` (`visa_id`, `student_id`, `visa_type`, `issue_date`, `expiry_date`, `status`, `passport_no`) VALUES
(90000101, 900001, 'Student Pass', '2026-01-01', '2027-01-01', 'Active', 'DEMO900001')
ON DUPLICATE KEY UPDATE
  `student_id` = VALUES(`student_id`),
  `visa_type` = VALUES(`visa_type`),
  `issue_date` = VALUES(`issue_date`),
  `expiry_date` = VALUES(`expiry_date`),
  `status` = VALUES(`status`),
  `passport_no` = VALUES(`passport_no`);
