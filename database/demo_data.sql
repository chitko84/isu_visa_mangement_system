INSERT INTO `clearance_record` (`clearance_id`, `exit_id`, `submission_date`, `status`) VALUES
(1, 1, '2026-01-17', 'In Progress'),
(2, 2, '2026-01-26', 'Completed');

INSERT INTO `country` (`country_id`, `country_name`, `region`) VALUES
(1, 'Malaysia', 'Asia'),
(2, 'Indonesia', 'Asia'),
(3, 'China', 'Asia'),
(4, 'India', 'Asia'),
(5, 'Saudi Arabia', 'Middle East'),
(6, 'Nigeria', 'Africa'),
(7, 'Bangladesh', 'Asia'),
(8, 'Pakistan', 'Asia'),
(9, 'Yemen', 'Middle East'),
(10, 'Sudan', 'Africa');

INSERT INTO `exit_case` (`exit_id`, `student_id`, `exit_type`, `request_date`, `exit_status`) VALUES
(1, 768967, 'Completion', '2026-01-15', 'Approved'),
(2, 12354, 'Completion', '2026-01-26', 'Completed');

INSERT INTO `exit_visa_action` (`exit_visa_id`, `exit_id`, `action_type`, `action_date`, `remarks`) VALUES
(1, 1, 'Transfer', '2026-01-17', ';p;p'),
(2, 1, 'Lapse', '2026-01-06', NULL),
(3, 2, 'Lapse', '2026-01-06', 'yyyyy'),
(4, 1, 'Transfer', '2026-01-20', NULL);

INSERT INTO `insurance_claim` (`claim_id`, `policy_id`, `claim_date`, `claim_amount`, `claim_status`) VALUES
(1, 11, '2026-01-14', 2.00, 'Approved'),
(2, 11, '2026-01-14', 2.00, 'Approved'),
(3, 11, '2026-01-14', 4.00, 'Approved'),
(4, 11, '2026-01-14', 1222.00, 'Approved'),
(5, 11, '2026-01-15', 44.00, 'Approved'),
(6, 16, '2026-01-26', 123.00, 'Approved');

INSERT INTO `insurance_policy` (`policy_id`, `student_id`, `provider_id`, `policy_number`, `start_date`, `end_date`, `coverage_type`, `status`) VALUES
(1, 1, 1, 'AIU-2023-001', '2026-01-01', '2027-01-01', 'Comprehensive', 'Active'),
(2, 2, 1, 'AIU-2023-002', '2026-01-01', '2027-01-01', 'Comprehensive', 'Active'),
(3, 3, 2, 'ISI-2023-001', '2026-01-01', '2027-01-01', 'Basic', 'Active'),
(4, 1, 1, 'AIU-2026-001', '2026-01-01', '2027-01-01', 'Comprehensive', 'Active'),
(5, 2, 1, 'AIU-2026-002', '2026-01-01', '2027-01-01', 'Comprehensive', 'Active'),
(6, 3, 2, 'ISI-2026-001', '2026-01-01', '2027-01-01', 'Basic', 'Active'),
(7, 1023, 1, 'AIU-2026-003', '2026-01-01', '2027-01-01', 'Comprehensive', 'Active'),
(8, 3333, 1, 'AIU-2026-3333', '2026-01-13', '2027-01-13', 'Comprehensive', 'Active'),
(9, 6666, 1, 'AIU-2026-6666', '2026-01-13', '2027-01-13', 'Comprehensive', 'Active'),
(10, 436334, 1, 'AIU-2026-4363', '2026-01-13', '2027-01-13', 'Comprehensive', 'Active'),
(11, 768967, 1, 'AIU-2026-7689', '2026-01-13', '2030-01-13', 'Comprehensive', 'Active'),
(12, 848748, 1, 'AIU-2026-8487', '2026-01-13', '2027-01-13', 'Comprehensive', 'Active'),
(13, 4, 1, 'AIU-2026-0004', '2026-01-13', '2027-01-13', 'Comprehensive', 'Active'),
(14, 24102180, 1, 'AIU-2026-2410', '2026-01-13', '2027-01-13', 'Comprehensive', 'Active'),
(15, 5, 1, 'AIU-2026-0005', '2026-01-13', '2027-01-13', 'Comprehensive', 'Active'),
(16, 12354, 1, 'POL-12354', '2026-01-01', '2026-01-25', 'Medical', 'Expired'),
(1001, 12354, 1, 'TEST-POL-12354-1', '2026-01-01', '2026-03-01', 'Medical', 'Active');

INSERT INTO `insurance_provider` (`provider_id`, `provider_name`, `contact_info`) VALUES
(1, 'AIU Health Insurance', 'health@aiu.edu.my, 04-1234567'),
(2, 'International Student Insurance', 'info@studentinsure.com, 03-9876543'),
(3, 'Global Health Coverage', 'support@globalhealth.com, 03-5555555');

INSERT INTO `insurance_renewal_record` (`renewal_id`, `policy_id`, `renewal_date`, `new_end_date`, `remarks`, `status`) VALUES
(1, 11, '2026-01-14', '2028-01-13', 'ok', 'Pending'),
(2, 11, '2026-01-14', '2028-07-13', '.....', 'Pending'),
(4, 11, '2026-01-14', '2030-01-13', 'Reque +12 months (student submission)', 'Pending'),
(5, 16, '2026-01-26', '2027-06-26', '', 'Approved'),
(6, 16, '2026-01-26', '2028-10-26', '', 'Approved');

INSERT INTO `nationality` (`student_id`, `country_id`, `acquired_date`, `is_primary`) VALUES
(1, 6, '2023-09-01', 1),
(2, 5, '2023-09-01', 1),
(3, 3, '2023-09-01', 1),
(4, 9, '2023-09-01', 1),
(5, 1, '2023-09-01', 1),
(1023, 6, '2026-01-11', 1),
(3333, 5, '2026-01-11', 1),
(6666, 10, '2026-01-11', 1),
(12354, 1, '2026-01-25', 1),
(12354, 5, '2026-01-13', 0),
(33344, 2, '2026-01-16', 1),
(42265, 6, '2026-01-16', 1),
(436334, 5, '2026-01-11', 1),
(524535, 10, '2026-01-16', 1),
(525325, 9, '2026-01-16', 1),
(769634, 7, '2026-01-16', 1),
(848748, 10, '2026-01-11', 1),
(24102180, 8, '2026-01-11', 1);

INSERT INTO `notifications` (`notification_id`, `student_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'Welcome to ISU Portal', 'Your account has been successfully created!', 0, '2026-01-15 10:03:58'),
(2, 1, 'Visa Renewal Reminder', 'Your student visa expires in 30 days. Please submit renewal application.', 0, '2026-01-15 10:05:57'),
(3, 2, 'Insurance Update', 'Your insurance policy has been renewed successfully.', 0, '2026-01-15 10:05:57'),
(4, 1, 'Academic Calendar', 'Mid-term exams schedule has been released. Check your dashboard.', 0, '2026-01-15 10:05:57'),
(5, 3, 'Document Verification', 'Your passport copy needs to be updated. Please upload a new copy.', 0, '2026-01-15 10:05:57'),
(6, 1, 'Fee Payment', 'Tuition fee payment for next semester is due next week.', 0, '2026-01-15 10:05:57'),
(7, 768967, 'Test Notification', 'This is a test notification for the student header badge.', 1, '2026-01-15 10:17:52'),
(8, 768967, 'Exit Process Update', 'Your exit request has been received and is currently under review by ISSU. Please monitor the clearance section for updates.', 1, '2026-01-15 10:40:37'),
(9, 768967, 'rrrrf', 'ddfadsfdsa', 0, '2026-01-15 20:43:21'),
(10, 1, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(11, 1023, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(12, 3333, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(13, 33344, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(14, 524535, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(15, 769634, 'jj', 'ad', 1, '2026-01-16 17:05:04'),
(16, 2, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(17, 6666, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(18, 436334, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(19, 768967, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(20, 848748, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(21, 3, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(22, 42265, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(23, 525325, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(24, 4, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(25, 24102180, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(26, 5, 'jj', 'ad', 0, '2026-01-16 17:05:04'),
(27, 12354, 'Visa Expiry Reminder', 'Your visa will expire on 2026-04-26. Please submit your visa renewal form and upload the required documents.', 1, '2026-01-25 19:59:55'),
(28, 1023, 'Visa Expiry Reminder', 'Your visa will expire on 15 April 2026. Please submit your visa renewal form as soon as possible.', 0, '2026-01-25 20:02:33'),
(29, 12354, 'Visa Expiry Reminder', 'Your visa will expire on 26 April 2026. Please submit your visa renewal form as soon as possible.', 1, '2026-01-25 20:02:33'),
(30, 12354, 'Visa Expiry Reminder', 'Your visa will expire on 10 March 2026. Please submit your visa renewal form as soon as possible.', 1, '2026-01-25 20:02:33'),
(31, 768967, 'Visa Expiry Reminder', 'Your visa will expire on 20 February 2026. Please submit your visa renewal form as soon as possible.', 0, '2026-01-25 20:02:33');

INSERT INTO `post_graduate` (`student_id`, `previous_degree`, `supervisor_name`, `thesis_required`) VALUES
(5, 'Bachelor of Business Administration', 'Dr. Smith', 1);

INSERT INTO `pre_college` (`student_id`, `guardian_name`, `guardian_contact`, `placement_test_score`) VALUES
(4, 'Mohamed Ibrahim', '01123456789', 78),
(3333, '59349322', '59349322', NULL),
(6666, '433434', '433434', NULL),
(525325, '64542235235', '64542235235', NULL),
(768967, '97988780', '97988780', 22),
(769634, 'MISS', '6897809780', NULL),
(848748, '433434', '433434', NULL),
(24102180, '255254', '255254', NULL);

INSERT INTO `program` (`program_id`, `school_id`, `program_name`, `level`, `faculty`, `duration_years`) VALUES
(1, 1, 'Bachelor of Business Administration', 'Degree', 'Business', 3),
(2, 2, 'Bachelor of Mechanical Engineering', 'Degree', 'Engineering', 4),
(3, 3, 'Bachelor of Computer Science', 'Degree', 'Computing', 3),
(4, 4, 'Foundation in Science', 'Foundation', 'Science', 1),
(5, 1, 'Master of Business Administration', 'Master', 'Business', 2);

INSERT INTO `reminder_queue` (`reminder_id`, `student_id`, `reminder_type`, `reference_id`, `due_date`, `email_to`, `subject`, `body`, `created_at`, `sent_at`, `status`) VALUES
(1, 12354, 'VISA_EXPIRY_3M', 12354001, '2026-01-26', 'dwaynejohnson@student.aiu.edu.my', 'Visa expiry reminder (12354)', 'Dear Thomas,\n\nYour visa will expire on 2026-04-26. Please submit your visa renewal form and upload the required documents.\n\nISSU', '2026-01-26 03:59:55', NULL, 'Pending'),
(2, 1023, 'VISA_EXPIRY_SOON', 10230001, '2026-04-15', 'npo@aiu.edu.my', 'Visa Expiry Reminder', 'Dear Chit Ko Ko,\n\nYour visa will expire on 15 April 2026. Please submit your visa renewal form as soon as possible.\n\nISSU', '2026-01-26 04:02:33', NULL, 'Pending'),
(3, 12354, 'VISA_EXPIRY_SOON', 12354001, '2026-04-26', 'dwaynejohnson@student.aiu.edu.my', 'Visa Expiry Reminder', 'Dear Thomas,\n\nYour visa will expire on 26 April 2026. Please submit your visa renewal form as soon as possible.\n\nISSU', '2026-01-26 04:02:33', NULL, 'Pending'),
(4, 12354, 'VISA_EXPIRY_SOON', 12354002, '2026-03-10', 'dwaynejohnson@student.aiu.edu.my', 'Visa Expiry Reminder', 'Dear Thomas,\n\nYour visa will expire on 10 March 2026. Please submit your visa renewal form as soon as possible.\n\nISSU', '2026-01-26 04:02:33', NULL, 'Pending'),
(5, 768967, 'VISA_EXPIRY_SOON', 76896702, '2026-02-20', 'ggg@aiu.edu.my', 'Visa Expiry Reminder', 'Dear ggg,\n\nYour visa will expire on 20 February 2026. Please submit your visa renewal form as soon as possible.\n\nISSU', '2026-01-26 04:02:33', NULL, 'Pending');

INSERT INTO `school` (`school_id`, `school_name`) VALUES
(5, 'School of Arts and Social Sciences'),
(1, 'School of Business'),
(3, 'School of Computer Science'),
(2, 'School of Engineering'),
(4, 'School of Medicine');

INSERT INTO `student_visa` (`visa_id`, `student_id`, `visa_type`, `issue_date`, `expiry_date`, `status`, `passport_no`) VALUES
(10230001, 1023, 'Student Pass', '2025-02-01', '2026-04-15', 'Active', 'MM12345'),
(12354001, 12354, 'Student Pass', '2026-01-25', '2026-04-26', 'Active', 'MG3729'),
(12354002, 12354, 'Student Pass', '2025-01-25', '2026-03-10', 'Active', 'MG3729X'),
(76896702, 768967, 'Student Pass', '2025-05-01', '2026-02-20', 'Active', 'PP99887');

INSERT INTO `undergraduate` (`student_id`, `high_school_name`, `admission_score`, `scholarship_flag`) VALUES
(1, 'Nigeria High School', 85.50, 1),
(2, 'Riyadh Secondary School', 88.75, 1),
(3, 'Beijing High School', 92.30, 0),
(1023, '01324309342', NULL, 0),
(12354, '3466345', 123.00, 1),
(33344, '4532553225354343', NULL, 0),
(42265, '9370', NULL, 0),
(436334, '+35235442', NULL, 0),
(524535, 'kjlklj', NULL, 0);

INSERT INTO `unit_clearance` (`unit_clearance_id`, `clearance_id`, `unit_name`, `clearance_date`) VALUES
(1, 1, 'jjj', '2026-01-07'),
(2, 2, 'rrrr', '2026-01-23'),
(3, 1, 'Finance', '2026-01-14');

INSERT INTO `visa_renewal_application` (`application_id`, `student_id`, `submission_date`, `requested_months`, `status`) VALUES
(1, 768967, '2026-01-13', 12, 'Passport collected'),
(2, 12354, '2026-01-25', 12, 'Passport collected'),
(1001, 1023, '2026-01-20', 12, 'Pending');

INSERT INTO `visa_renewal_status` (`status_id`, `application_id`, `stage_name`, `updated_date`, `remarks`) VALUES
(1, 1, 'Pending', '2026-01-13', 'Auto-created on submission'),
(2, 1, 'Submitted passport to ISSU', '2026-01-17', ''),
(3, 1, 'Passport collected', '2026-01-17', ''),
(4, 1, 'Passport collected', '2026-01-17', 'kk'),
(5, 1, 'Submitted passport to ISSU', '2026-01-17', 'jjjjjjj'),
(6, 1, 'Approved', '2026-01-17', 'Approved & visa updated by staff'),
(7, 1, 'Passport collected', '2026-01-17', 'Approved & visa updated by staff'),
(8, 1, 'Submitted passport to ISSU', '2026-01-17', 'ccc'),
(9, 1, 'Passport collected', '2026-01-17', 'dfff'),
(10, 1, 'Pending', '2026-01-17', ''),
(11, 1, 'approved', '2026-01-17', ''),
(12, 1, 'rejected', '2026-01-17', 'ddd'),
(13, 1, 'Submitted passport to ISSU', '2026-01-17', ''),
(14, 1, 'Approved', '2026-01-17', 'Approved & visa updated by staff'),
(15, 1, 'Passport collected', '2026-01-17', 'Approved & visa updated by staff'),
(16, 2, 'Pending', '2026-01-25', 'Auto-created on submission'),
(17, 2, 'Passport collected', '2026-01-26', 'yyyy'),
(18, 2, 'Approved', '2026-01-26', 'none'),
(19, 2, 'Approved', '2026-01-26', 'Approved & visa updated by staff'),
(20, 2, 'Passport collected', '2026-01-26', 'Approved & visa updated by staff'),
(21, 2, 'Approved', '2026-01-26', ''),
(22, 2, 'Approved', '2026-01-26', 'Approved & visa updated by staff'),
(23, 2, 'Passport collected', '2026-01-26', 'Approved & visa updated by staff'),
(24, 2, 'Approved', '2026-01-26', ''),
(25, 2, 'Approved', '2026-01-26', 'Approved & visa updated by staff'),
(26, 2, 'Passport collected', '2026-01-26', 'Approved & visa updated by staff'),
(2001, 1001, 'Pending', '2026-01-20', 'Submitted'),
(2002, 1001, 'Approved', '2026-01-26', 'Approved - passport ready');

