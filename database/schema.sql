-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 25, 2026 at 09:21 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `issu`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_delete_clearance_record` (IN `p_clearance_id` INT)   BEGIN
    START TRANSACTION;
    
    -- Delete unit clearances first
    DELETE FROM unit_clearance WHERE clearance_id = p_clearance_id;
    
    -- Delete clearance record
    DELETE FROM clearance_record WHERE clearance_id = p_clearance_id;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_delete_exit_case` (IN `p_exit_id` INT)   BEGIN
    START TRANSACTION;
    
    -- Delete related unit clearances (via clearance_record)
    DELETE uc FROM unit_clearance uc
    JOIN clearance_record cr ON uc.clearance_id = cr.clearance_id
    WHERE cr.exit_id = p_exit_id;
    
    -- Delete clearance record
    DELETE FROM clearance_record WHERE exit_id = p_exit_id;
    
    -- Delete exit visa actions
    DELETE FROM exit_visa_action WHERE exit_id = p_exit_id;
    
    -- Delete exit case
    DELETE FROM exit_case WHERE exit_id = p_exit_id;
    
    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_visa_expiry_reminders` ()   BEGIN
  -- 1ï¸âƒ£ Insert into reminder_queue (email queue)
  INSERT INTO reminder_queue(
    student_id, reminder_type, reference_id, due_date,
    email_to, subject, body, created_at, status
  )
  SELECT
    s.student_id,
    'VISA_EXPIRY_SOON',
    v.visa_id,
    v.expiry_date,
    s.email,
    'Visa Expiry Reminder',
    CONCAT(
      'Dear ', s.first_name, ',\n\n',
      'Your visa will expire on ',
      DATE_FORMAT(v.expiry_date, '%d %M %Y'),
      '. Please submit your visa renewal form as soon as possible.\n\nISSU'
    ),
    NOW(),
    'Pending'
  FROM student_visa v
  JOIN student s ON s.student_id = v.student_id
  LEFT JOIN visa_notification_optout o ON o.student_id = s.student_id
  WHERE v.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
    AND s.status <> 'Graduated'
    AND o.student_id IS NULL
    AND NOT EXISTS (
      SELECT 1
      FROM reminder_queue rq
      WHERE rq.reminder_type = 'VISA_EXPIRY_SOON'
        AND rq.reference_id = v.visa_id
    );

  -- 2ï¸âƒ£ Insert into notifications (student dashboard)
  INSERT INTO notifications(student_id, title, message, is_read, created_at)
  SELECT
    s.student_id,
    'Visa Expiry Reminder',
    CONCAT(
      'Your visa will expire on ',
      DATE_FORMAT(v.expiry_date, '%d %M %Y'),
      '. Please submit your visa renewal form as soon as possible.'
    ),
    0,
    NOW()
  FROM student_visa v
  JOIN student s ON s.student_id = v.student_id
  LEFT JOIN visa_notification_optout o ON o.student_id = s.student_id
  WHERE v.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
    AND s.status <> 'Graduated'
    AND o.student_id IS NULL
    AND NOT EXISTS (
      SELECT 1
      FROM notifications n
      WHERE n.student_id = s.student_id
        AND n.title = 'Visa Expiry Reminder'
        AND n.message LIKE CONCAT('%', DATE_FORMAT(v.expiry_date, '%d %M %Y'), '%')
    );

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_student_profile` (IN `p_student_id` INT)   BEGIN
  /* Result set #1: core student + program + school */
  SELECT
    s.student_id, s.first_name, s.last_name, s.phone, s.email, s.status, s.student_type,
    p.program_id, p.program_name, p.level, p.faculty, p.duration_years,
    sc.school_id, sc.school_name
  FROM student s
  JOIN program p ON p.program_id = s.program_id
  JOIN school sc ON sc.school_id = p.school_id
  WHERE s.student_id = p_student_id;

  /* Result set #2: subtype info (one row max; depending on type) */
  SELECT 'pre_college' AS subtype,
         pc.guardian_name AS col1,
         pc.guardian_contact AS col2,
         pc.placement_test_score AS col3
  FROM pre_college pc
  WHERE pc.student_id = p_student_id

  UNION ALL

  SELECT 'undergraduate',
         u.high_school_name,
         NULL,
         u.admission_score
  FROM undergraduate u
  WHERE u.student_id = p_student_id

  UNION ALL

  SELECT 'post_graduate',
         pg.previous_degree,
         pg.supervisor_name,
         pg.thesis_required
  FROM post_graduate pg
  WHERE pg.student_id = p_student_id;

  /* Result set #3: repeating nationalities */
  SELECT
    n.country_id, c.country_name, c.region,
    n.acquired_date, n.is_primary
  FROM nationality n
  JOIN country c ON c.country_id = n.country_id
  WHERE n.student_id = p_student_id
  ORDER BY n.is_primary DESC, n.acquired_date DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_overview_all_students` ()   BEGIN
  SELECT
    s.student_id, s.first_name, s.last_name, s.email, s.phone, s.status AS student_status,

    (SELECT v.expiry_date FROM student_visa v WHERE v.student_id = s.student_id ORDER BY v.expiry_date DESC LIMIT 1) AS visa_expiry_date,
    (SELECT v.status FROM student_visa v WHERE v.student_id = s.student_id ORDER BY v.expiry_date DESC LIMIT 1) AS visa_status,

    (SELECT a.status FROM visa_renewal_application a WHERE a.student_id = s.student_id ORDER BY a.submission_date DESC LIMIT 1) AS visa_renewal_app_status,

    (SELECT ip.end_date FROM insurance_policy ip WHERE ip.student_id = s.student_id ORDER BY ip.end_date DESC LIMIT 1) AS insurance_end_date,
    (SELECT ip.status FROM insurance_policy ip WHERE ip.student_id = s.student_id ORDER BY ip.end_date DESC LIMIT 1) AS insurance_status,

    (SELECT e.exit_status FROM exit_case e WHERE e.student_id = s.student_id ORDER BY e.request_date DESC LIMIT 1) AS exit_status
  FROM student s;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_report_insurance_expiring_next_3_months` ()   BEGIN
  SELECT
    s.student_id, s.first_name, s.last_name, s.email, s.phone,
    ip.policy_id, ip.policy_number, ip.end_date, ip.status,
    pr.provider_name
  FROM insurance_policy ip
  JOIN student s ON s.student_id = ip.student_id
  JOIN insurance_provider pr ON pr.provider_id = ip.provider_id
  WHERE ip.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
    AND s.status <> 'Graduated'
  ORDER BY ip.end_date ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_report_passport_ready_to_collect` ()   BEGIN
  SELECT
    s.student_id, s.first_name, s.last_name, s.email, s.phone,
    a.application_id, a.submission_date, a.status AS application_status,
    rs.stage_name AS latest_stage, rs.updated_date AS stage_updated_date
  FROM visa_renewal_application a
  JOIN student s ON s.student_id = a.student_id
  JOIN visa_renewal_status rs ON rs.status_id = (
    SELECT rs2.status_id
    FROM visa_renewal_status rs2
    WHERE rs2.application_id = a.application_id
    ORDER BY rs2.updated_date DESC, rs2.status_id DESC
    LIMIT 1
  )
  WHERE rs.stage_name = 'Approved'
    AND a.status <> 'Passport collected'
  ORDER BY rs.updated_date DESC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_report_pending_exit_cases` ()   BEGIN
  SELECT
    e.exit_id, e.student_id, s.first_name, s.last_name, s.email,
    e.exit_type, e.request_date, e.exit_status
  FROM exit_case e
  JOIN student s ON s.student_id = e.student_id
  WHERE e.exit_status IN ('Pending','In Progress')
  ORDER BY e.request_date ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_report_visas_expiring_next_3_months` ()   BEGIN
  SELECT
    s.student_id, s.first_name, s.last_name, s.email, s.phone,
    v.visa_id, v.visa_type, v.passport_no, v.expiry_date,
    v.status AS visa_status
  FROM student_visa v
  JOIN student s ON s.student_id = v.student_id
  LEFT JOIN visa_notification_optout o ON o.student_id = s.student_id
  WHERE v.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
    AND s.status <> 'Graduated'
    AND o.student_id IS NULL
  ORDER BY v.expiry_date ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_add_exit_visa_action` (IN `p_exit_id` INT, IN `p_action_type` VARCHAR(30), IN `p_action_date` DATE, IN `p_remarks` VARCHAR(255), OUT `o_exit_visa_id` INT)   BEGIN
  DECLARE v_next_id INT;

  START TRANSACTION;

  SELECT IFNULL(MAX(exit_visa_id), 0) + 1 INTO v_next_id
  FROM exit_visa_action
  FOR UPDATE;

  INSERT INTO exit_visa_action(exit_visa_id, exit_id, action_type, action_date, remarks)
  VALUES (v_next_id, p_exit_id, p_action_type, p_action_date, p_remarks);

  COMMIT;

  SET o_exit_visa_id = v_next_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_add_visa_renewal_status` (IN `p_application_id` INT, IN `p_stage_name` VARCHAR(100), IN `p_remarks` VARCHAR(255), OUT `o_status_id` INT)   BEGIN
  DECLARE v_next_id INT;

  START TRANSACTION;

  SELECT IFNULL(MAX(status_id), 0) + 1 INTO v_next_id
  FROM visa_renewal_status
  FOR UPDATE;

  INSERT INTO visa_renewal_status(status_id, application_id, stage_name, updated_date, remarks)
  VALUES (v_next_id, p_application_id, p_stage_name, CURDATE(), p_remarks);

  COMMIT;

  SET o_status_id = v_next_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_create_clearance_record` (IN `p_exit_id` INT, IN `p_status` VARCHAR(30), OUT `o_clearance_id` INT)   BEGIN
  DECLARE v_next_id INT;

  -- enforce one-to-one (exit_id is UNIQUE in clearance_record)
  IF EXISTS (SELECT 1 FROM clearance_record WHERE exit_id = p_exit_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Clearance record already exists for this exit case.';
  END IF;

  START TRANSACTION;

  SELECT IFNULL(MAX(clearance_id), 0) + 1 INTO v_next_id
  FROM clearance_record
  FOR UPDATE;

  INSERT INTO clearance_record(clearance_id, exit_id, submission_date, status)
  VALUES (v_next_id, p_exit_id, CURDATE(), p_status);

  COMMIT;

  SET o_clearance_id = v_next_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_delete_exit_visa_action` (IN `p_exit_visa_id` INT)   BEGIN
    DELETE FROM exit_visa_action
    WHERE exit_visa_id = p_exit_visa_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_delete_unit_clearance` (IN `p_unit_clearance_id` INT)   BEGIN
    DELETE FROM unit_clearance
    WHERE unit_clearance_id = p_unit_clearance_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_update_claim_status` (IN `p_claim_id` INT, IN `p_new_status` VARCHAR(20))   BEGIN
  UPDATE insurance_claim
  SET claim_status = p_new_status
  WHERE claim_id = p_claim_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_update_exit_status` (IN `p_exit_id` INT, IN `p_new_status` VARCHAR(30))   BEGIN
  UPDATE exit_case
  SET exit_status = p_new_status
  WHERE exit_id = p_exit_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_update_exit_visa_action` (IN `p_exit_visa_id` INT, IN `p_action_type` VARCHAR(30), IN `p_action_date` DATE, IN `p_remarks` VARCHAR(255))   BEGIN
    UPDATE exit_visa_action
    SET action_type = p_action_type,
        action_date = p_action_date,
        remarks     = p_remarks
    WHERE exit_visa_id = p_exit_visa_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_update_renewal_status` (IN `p_renewal_id` INT, IN `p_new_status` VARCHAR(20))   BEGIN
    DECLARE v_policy_id INT DEFAULT NULL;
    DECLARE v_new_end_date DATE DEFAULT NULL;

    -- Basic validation
    IF p_renewal_id IS NULL OR p_renewal_id <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid renewal id.';
    END IF;

    IF p_new_status NOT IN ('Pending','Approved','Rejected') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid renewal status.';
    END IF;

    START TRANSACTION;

    -- Get policy_id and new_end_date for this renewal record
    SELECT policy_id, new_end_date
      INTO v_policy_id, v_new_end_date
    FROM insurance_renewal_record
    WHERE renewal_id = p_renewal_id
    FOR UPDATE;

    IF v_policy_id IS NULL THEN
        ROLLBACK;
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Renewal record not found.';
    END IF;

    -- Update the renewal status
    UPDATE insurance_renewal_record
    SET status = p_new_status
    WHERE renewal_id = p_renewal_id;

    -- If approved, extend the policy end date
    IF p_new_status = 'Approved' THEN
        IF v_new_end_date IS NULL THEN
            ROLLBACK;
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'New end date is missing for this renewal.';
        END IF;

        UPDATE insurance_policy
        SET end_date = v_new_end_date,
            status = 'Active'
        WHERE policy_id = v_policy_id;
    END IF;

    -- If rejected, no policy change (only renewal record status changes)

    COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_update_unit_clearance` (IN `p_unit_clearance_id` INT, IN `p_unit_name` VARCHAR(100), IN `p_clearance_date` DATE)   BEGIN
    UPDATE unit_clearance
    SET unit_name = p_unit_name,
        clearance_date = p_clearance_date
    WHERE unit_clearance_id = p_unit_clearance_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_update_visa_application_status` (IN `p_application_id` INT, IN `p_new_status` VARCHAR(40), IN `p_remarks` VARCHAR(255))   BEGIN
  UPDATE visa_renewal_application
  SET status = p_new_status
  WHERE application_id = p_application_id;

  -- Also append to history (auditable lifecycle)
  CALL sp_staff_add_visa_renewal_status(p_application_id, p_new_status, p_remarks, @tmp_status_id);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_upsert_student` (IN `p_student_id` INT, IN `p_program_id` INT, IN `p_first_name` VARCHAR(50), IN `p_last_name` VARCHAR(50), IN `p_phone` VARCHAR(30), IN `p_email` VARCHAR(100), IN `p_status` VARCHAR(30), IN `p_student_type` VARCHAR(5))   BEGIN
  IF p_student_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'student_id is required (no AUTO_INCREMENT in your schema).';
  END IF;

  IF EXISTS (SELECT 1 FROM student WHERE student_id = p_student_id) THEN
    UPDATE student
      SET program_id  = p_program_id,
          first_name  = p_first_name,
          last_name   = p_last_name,
          phone       = p_phone,
          email       = p_email,
          status      = p_status,
          student_type= p_student_type
    WHERE student_id = p_student_id;
  ELSE
    INSERT INTO student(student_id, program_id, first_name, last_name, phone, email, status, student_type)
    VALUES (p_student_id, p_program_id, p_first_name, p_last_name, p_phone, p_email, p_status, p_student_type);
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_upsert_student_visa` (IN `p_visa_id` INT, IN `p_student_id` INT, IN `p_visa_type` VARCHAR(50), IN `p_issue_date` DATE, IN `p_expiry_date` DATE, IN `p_passport_no` VARCHAR(30))   BEGIN
  DECLARE v_status VARCHAR(20);

  IF p_expiry_date < CURDATE() THEN
    SET v_status = 'Expired';
  ELSE
    SET v_status = 'Active';
  END IF;

  IF EXISTS (SELECT 1 FROM student_visa WHERE visa_id = p_visa_id) THEN
    UPDATE student_visa
      SET student_id  = p_student_id,
          visa_type   = p_visa_type,
          issue_date  = p_issue_date,
          expiry_date = p_expiry_date,
          status      = v_status,
          passport_no = p_passport_no
    WHERE visa_id = p_visa_id;
  ELSE
    INSERT INTO student_visa(visa_id, student_id, visa_type, issue_date, expiry_date, status, passport_no)
    VALUES (p_visa_id, p_student_id, p_visa_type, p_issue_date, p_expiry_date, v_status, p_passport_no);
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_staff_upsert_unit_clearance` (IN `p_clearance_id` INT, IN `p_unit_name` VARCHAR(50), IN `p_clearance_date` DATE, OUT `o_unit_clearance_id` INT)   BEGIN
  DECLARE v_existing_id INT;
  DECLARE v_next_id INT;

  SELECT unit_clearance_id INTO v_existing_id
  FROM unit_clearance
  WHERE clearance_id = p_clearance_id AND unit_name = p_unit_name
  LIMIT 1;

  IF v_existing_id IS NOT NULL THEN
    UPDATE unit_clearance
    SET clearance_date = p_clearance_date
    WHERE unit_clearance_id = v_existing_id;
    SET o_unit_clearance_id = v_existing_id;
  ELSE
    START TRANSACTION;

    SELECT IFNULL(MAX(unit_clearance_id), 0) + 1 INTO v_next_id
    FROM unit_clearance
    FOR UPDATE;

    INSERT INTO unit_clearance(unit_clearance_id, clearance_id, unit_name, clearance_date)
    VALUES (v_next_id, p_clearance_id, p_unit_name, p_clearance_date);

    COMMIT;

    SET o_unit_clearance_id = v_next_id;
  END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_student_add_visa_document` (IN `p_student_id` INT, IN `p_application_id` INT, IN `p_document_type` VARCHAR(100), IN `p_document_path` VARCHAR(255), OUT `o_document_id` INT)   BEGIN
  DECLARE v_next_id INT;

  IF NOT EXISTS (
    SELECT 1 FROM visa_renewal_application a
    WHERE a.application_id = p_application_id AND a.student_id = p_student_id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Application does not belong to student.';
  END IF;

  START TRANSACTION;

  SELECT IFNULL(MAX(document_id), 0) + 1 INTO v_next_id
  FROM visa_document
  FOR UPDATE;

  INSERT INTO visa_document(document_id, application_id, document_type, document_path, upload_date)
  VALUES (v_next_id, p_application_id, p_document_type, p_document_path, CURDATE());

  COMMIT;

  SET o_document_id = v_next_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_student_delete_visa_document` (IN `p_student_id` INT, IN `p_document_id` INT)   BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM visa_document d
    JOIN visa_renewal_application a ON a.application_id = d.application_id
    WHERE d.document_id = p_document_id AND a.student_id = p_student_id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Document does not belong to student.';
  END IF;

  DELETE FROM visa_document
  WHERE document_id = p_document_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_student_overview` (IN `p_student_id` INT)   BEGIN
  SELECT
    s.student_id, s.first_name, s.last_name, s.email, s.phone, s.status AS student_status,
    -- latest visa
    (SELECT v.visa_type FROM student_visa v WHERE v.student_id = s.student_id ORDER BY v.expiry_date DESC LIMIT 1) AS visa_type,
    (SELECT v.passport_no FROM student_visa v WHERE v.student_id = s.student_id ORDER BY v.expiry_date DESC LIMIT 1) AS passport_no,
    (SELECT v.expiry_date FROM student_visa v WHERE v.student_id = s.student_id ORDER BY v.expiry_date DESC LIMIT 1) AS visa_expiry_date,
    (SELECT v.status FROM student_visa v WHERE v.student_id = s.student_id ORDER BY v.expiry_date DESC LIMIT 1) AS visa_status,

    -- latest visa renewal application
    (SELECT a.application_id FROM visa_renewal_application a WHERE a.student_id = s.student_id ORDER BY a.submission_date DESC LIMIT 1) AS latest_application_id,
    (SELECT a.status FROM visa_renewal_application a WHERE a.student_id = s.student_id ORDER BY a.submission_date DESC LIMIT 1) AS visa_renewal_app_status,
    (SELECT rs.stage_name
       FROM visa_renewal_status rs
       JOIN visa_renewal_application a2 ON a2.application_id = rs.application_id
      WHERE a2.student_id = s.student_id
      ORDER BY rs.updated_date DESC, rs.status_id DESC
      LIMIT 1
    ) AS visa_renewal_latest_stage,

    -- Insurance: latest policy by end_date
    (SELECT ip.policy_id FROM insurance_policy ip WHERE ip.student_id = s.student_id ORDER BY ip.end_date DESC LIMIT 1) AS policy_id,
    (SELECT ip.end_date FROM insurance_policy ip WHERE ip.student_id = s.student_id ORDER BY ip.end_date DESC LIMIT 1) AS insurance_end_date,
    (SELECT ip.status FROM insurance_policy ip WHERE ip.student_id = s.student_id ORDER BY ip.end_date DESC LIMIT 1) AS insurance_status,

    -- Exit: latest exit case by request_date
    (SELECT e.exit_id FROM exit_case e WHERE e.student_id = s.student_id ORDER BY e.request_date DESC LIMIT 1) AS latest_exit_id,
    (SELECT e.exit_status FROM exit_case e WHERE e.student_id = s.student_id ORDER BY e.request_date DESC LIMIT 1) AS exit_status
  FROM student s
  WHERE s.student_id = p_student_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_student_submit_exit_request` (IN `p_student_id` INT, IN `p_exit_type` VARCHAR(30), OUT `o_exit_id` INT)   BEGIN
  DECLARE v_next_id INT;

  START TRANSACTION;

  SELECT IFNULL(MAX(exit_id), 0) + 1 INTO v_next_id
  FROM exit_case
  FOR UPDATE;

  INSERT INTO exit_case(exit_id, student_id, exit_type, request_date, exit_status)
  VALUES (v_next_id, p_student_id, p_exit_type, CURDATE(), 'Pending');

  COMMIT;

  SET o_exit_id = v_next_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_student_submit_insurance_claim` (IN `p_student_id` INT, IN `p_policy_id` INT, IN `p_claim_amount` DECIMAL(10,2), OUT `o_claim_id` INT)   BEGIN
  DECLARE v_next_id INT;

  IF p_claim_amount <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'claim_amount must be > 0.';
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM insurance_policy p
    WHERE p.policy_id = p_policy_id AND p.student_id = p_student_id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Policy does not belong to student.';
  END IF;

  START TRANSACTION;
  SELECT IFNULL(MAX(claim_id), 0) + 1 INTO v_next_id
  FROM insurance_claim
  FOR UPDATE;

  INSERT INTO insurance_claim(claim_id, policy_id, claim_date, claim_amount, claim_status)
  VALUES (v_next_id, p_policy_id, CURDATE(), p_claim_amount, 'Pending');

  COMMIT;

  SET o_claim_id = v_next_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_student_submit_insurance_renewal_form` (IN `p_student_id` INT, IN `p_policy_id` INT, IN `p_new_end_date` DATE, IN `p_remarks` VARCHAR(255), OUT `o_renewal_id` INT)   BEGIN
  DECLARE v_next_id INT;

  IF NOT EXISTS (
    SELECT 1 FROM insurance_policy p
    WHERE p.policy_id = p_policy_id AND p.student_id = p_student_id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Policy does not belong to student.';
  END IF;

  START TRANSACTION;

  SELECT IFNULL(MAX(renewal_id), 0) + 1 INTO v_next_id
  FROM insurance_renewal_record
  FOR UPDATE;

  INSERT INTO insurance_renewal_record(renewal_id, policy_id, renewal_date, new_end_date, remarks)
  VALUES (v_next_id, p_policy_id, CURDATE(), p_new_end_date, p_remarks);

  COMMIT;

  SET o_renewal_id = v_next_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_student_submit_visa_renewal_form` (IN `p_student_id` INT, IN `p_requested_months` INT, OUT `o_application_id` INT)   BEGIN
  DECLARE v_next_id INT;

  IF p_requested_months <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'requested_months must be > 0.';
  END IF;

  START TRANSACTION;

  SELECT IFNULL(MAX(application_id), 0) + 1 INTO v_next_id
  FROM visa_renewal_application
  FOR UPDATE;

  INSERT INTO visa_renewal_application(application_id, student_id, submission_date, requested_months, status)
  VALUES (v_next_id, p_student_id, CURDATE(), p_requested_months, 'Pending');

  CALL sp_staff_add_visa_renewal_status(v_next_id, 'Pending', 'Auto-created on submission', @tmp_status_id);

  COMMIT;

  SET o_application_id = v_next_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_student_update_visa_document` (IN `p_student_id` INT, IN `p_document_id` INT, IN `p_document_type` VARCHAR(100), IN `p_document_path` VARCHAR(255))   BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM visa_document d
    JOIN visa_renewal_application a ON a.application_id = d.application_id
    WHERE d.document_id = p_document_id AND a.student_id = p_student_id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Document does not belong to student.';
  END IF;

  UPDATE visa_document
  SET document_type = p_document_type,
      document_path = p_document_path,
      upload_date   = CURDATE()
  WHERE document_id = p_document_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_clearance_record` (IN `p_clearance_id` INT, IN `p_submission_date` DATE, IN `p_status` VARCHAR(20))   BEGIN
    UPDATE clearance_record 
    SET submission_date = p_submission_date,
        status = p_status
    WHERE clearance_id = p_clearance_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_exit_case` (IN `p_exit_id` INT, IN `p_exit_type` VARCHAR(20), IN `p_request_date` DATE, IN `p_exit_status` VARCHAR(20))   BEGIN
    UPDATE exit_case 
    SET exit_type = p_exit_type,
        request_date = p_request_date,
        exit_status = p_exit_status
    WHERE exit_id = p_exit_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_visa` (IN `p_visa_id` INT, IN `p_visa_type` VARCHAR(50), IN `p_passport_no` VARCHAR(50), IN `p_issue_date` DATE, IN `p_expiry_date` DATE, IN `p_status` VARCHAR(20))   BEGIN
    UPDATE student_visa 
    SET visa_type = p_visa_type,
        passport_no = p_passport_no,
        issue_date = p_issue_date,
        expiry_date = p_expiry_date,
        status = p_status
    WHERE visa_id = p_visa_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `academic_dates`
--

CREATE TABLE `academic_dates` (
  `id` int(11) NOT NULL,
  `event_name` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clearance_record`
--

CREATE TABLE `clearance_record` (
  `clearance_id` int(11) NOT NULL,
  `exit_id` int(11) NOT NULL,
  `submission_date` date NOT NULL,
  `status` varchar(20) NOT NULL CHECK (`status` in ('In Progress','Completed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clearance_record`
--


-- --------------------------------------------------------

--
-- Table structure for table `country`
--

CREATE TABLE `country` (
  `country_id` int(11) NOT NULL,
  `country_name` varchar(100) NOT NULL,
  `region` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `country`
--


-- --------------------------------------------------------

--
-- Table structure for table `exit_case`
--

CREATE TABLE `exit_case` (
  `exit_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exit_type` varchar(30) NOT NULL CHECK (`exit_type` in ('Completion','Withdrawal','Termination')),
  `request_date` date NOT NULL,
  `exit_status` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exit_case`
--


--
-- Triggers `exit_case`
--
DELIMITER $$
CREATE TRIGGER `trg_exit_case_approval_check` BEFORE UPDATE ON `exit_case` FOR EACH ROW BEGIN
  IF NEW.exit_status IN ('Approved','Completed') AND OLD.exit_status <> NEW.exit_status THEN
    IF EXISTS (
      SELECT 1
      FROM insurance_claim ic
      JOIN insurance_policy ip ON ip.policy_id = ic.policy_id
      WHERE ip.student_id = NEW.student_id
        AND ic.claim_status = 'Pending'
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot approve exit: student has unresolved insurance claims.';
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `exit_visa_action`
--

CREATE TABLE `exit_visa_action` (
  `exit_visa_id` int(11) NOT NULL,
  `exit_id` int(11) NOT NULL,
  `action_type` varchar(30) NOT NULL CHECK (`action_type` in ('Cancellation','Lapse','Transfer')),
  `action_date` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exit_visa_action`
--


-- --------------------------------------------------------

--
-- Table structure for table `insurance_claim`
--

CREATE TABLE `insurance_claim` (
  `claim_id` int(11) NOT NULL,
  `policy_id` int(11) NOT NULL,
  `claim_date` date NOT NULL,
  `claim_amount` decimal(10,2) NOT NULL CHECK (`claim_amount` > 0),
  `claim_status` varchar(20) NOT NULL CHECK (`claim_status` in ('Pending','Approved','Rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `insurance_claim`
--


-- --------------------------------------------------------

--
-- Table structure for table `insurance_policy`
--

CREATE TABLE `insurance_policy` (
  `policy_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `policy_number` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `coverage_type` varchar(50) DEFAULT NULL,
  `status` varchar(20) NOT NULL CHECK (`status` in ('Active','Expired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `insurance_policy`
--


--
-- Triggers `insurance_policy`
--
DELIMITER $$
CREATE TRIGGER `trg_insurance_policy_validate` BEFORE INSERT ON `insurance_policy` FOR EACH ROW BEGIN
  IF NEW.end_date < NEW.start_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'insurance_policy.end_date must be >= start_date.';
  END IF;

  IF NEW.end_date < CURDATE() AND NEW.status <> 'Expired' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot set insurance status Active when end_date is in the past.';
  END IF;

  IF NEW.end_date >= CURDATE() AND NEW.status <> 'Active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot set insurance status Expired when end_date is in the future.';
  END IF;

  -- one active policy for same provider at a time (business rule)
  IF NEW.status = 'Active' AND EXISTS (
    SELECT 1 FROM insurance_policy p
    WHERE p.student_id = NEW.student_id
      AND p.provider_id = NEW.provider_id
      AND p.status = 'Active'
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student already has an active policy with this provider.';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_insurance_policy_validate_u` BEFORE UPDATE ON `insurance_policy` FOR EACH ROW BEGIN
  IF NEW.end_date < NEW.start_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'insurance_policy.end_date must be >= start_date.';
  END IF;

  IF NEW.end_date < CURDATE() AND NEW.status <> 'Expired' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot set insurance status Active when end_date is in the past.';
  END IF;

  IF NEW.end_date >= CURDATE() AND NEW.status <> 'Active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot set insurance status Expired when end_date is in the future.';
  END IF;

  IF NEW.status = 'Active' AND EXISTS (
    SELECT 1 FROM insurance_policy p
    WHERE p.student_id = NEW.student_id
      AND p.provider_id = NEW.provider_id
      AND p.status = 'Active'
      AND p.policy_id <> OLD.policy_id
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student already has another active policy with this provider.';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `insurance_provider`
--

CREATE TABLE `insurance_provider` (
  `provider_id` int(11) NOT NULL,
  `provider_name` varchar(100) NOT NULL,
  `contact_info` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `insurance_provider`
--


-- --------------------------------------------------------

--
-- Table structure for table `insurance_renewal_record`
--

CREATE TABLE `insurance_renewal_record` (
  `renewal_id` int(11) NOT NULL,
  `policy_id` int(11) NOT NULL,
  `renewal_date` date NOT NULL,
  `new_end_date` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `insurance_renewal_record`
--


--
-- Triggers `insurance_renewal_record`
--
DELIMITER $$
CREATE TRIGGER `trg_insurance_renewal_apply` AFTER INSERT ON `insurance_renewal_record` FOR EACH ROW BEGIN
  UPDATE insurance_policy
  SET end_date = NEW.new_end_date,
      status   = CASE WHEN NEW.new_end_date < CURDATE() THEN 'Expired' ELSE 'Active' END
  WHERE policy_id = NEW.policy_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_insurance_renewal_validate` BEFORE INSERT ON `insurance_renewal_record` FOR EACH ROW BEGIN
  DECLARE v_current_end DATE;

  SELECT end_date INTO v_current_end
  FROM insurance_policy
  WHERE policy_id = NEW.policy_id;

  IF v_current_end IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid policy_id for renewal.';
  END IF;

  IF NEW.new_end_date <= v_current_end THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'new_end_date must be later than current end_date.';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `nationality`
--

CREATE TABLE `nationality` (
  `student_id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `acquired_date` date DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nationality`
--


-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--


-- --------------------------------------------------------

--
-- Table structure for table `post_graduate`
--

CREATE TABLE `post_graduate` (
  `student_id` int(11) NOT NULL,
  `previous_degree` varchar(100) NOT NULL,
  `supervisor_name` varchar(100) DEFAULT NULL,
  `thesis_required` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_graduate`
--


-- --------------------------------------------------------

--
-- Table structure for table `pre_college`
--

CREATE TABLE `pre_college` (
  `student_id` int(11) NOT NULL,
  `guardian_name` varchar(100) NOT NULL,
  `guardian_contact` varchar(20) NOT NULL,
  `placement_test_score` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pre_college`
--


-- --------------------------------------------------------

--
-- Table structure for table `program`
--

CREATE TABLE `program` (
  `program_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `level` varchar(30) NOT NULL CHECK (`level` in ('Foundation','Degree','Master')),
  `faculty` varchar(100) DEFAULT NULL,
  `duration_years` int(11) NOT NULL CHECK (`duration_years` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program`
--


-- --------------------------------------------------------

--
-- Table structure for table `reminder_queue`
--

CREATE TABLE `reminder_queue` (
  `reminder_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `reminder_type` varchar(30) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `due_date` date NOT NULL,
  `email_to` varchar(255) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reminder_queue`
--


-- --------------------------------------------------------

--
-- Table structure for table `school`
--

CREATE TABLE `school` (
  `school_id` int(11) NOT NULL,
  `school_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school`
--


-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `department` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(30) DEFAULT 'Active',
  `profile_photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--


-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `student_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `status` varchar(30) NOT NULL,
  `student_type` char(2) NOT NULL CHECK (`student_type` in ('PC','UG','PG')),
  `password` varchar(255) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--


-- --------------------------------------------------------

--
-- Table structure for table `student_visa`
--

CREATE TABLE `student_visa` (
  `visa_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `visa_type` varchar(50) NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `status` varchar(20) NOT NULL CHECK (`status` in ('Active','Expired')),
  `passport_no` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_visa`
--


--
-- Triggers `student_visa`
--
DELIMITER $$
CREATE TRIGGER `trg_student_visa_validate` BEFORE INSERT ON `student_visa` FOR EACH ROW BEGIN
  IF NEW.expiry_date < NEW.issue_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'expiry_date must be >= issue_date.';
  END IF;

  IF NEW.expiry_date < CURDATE() AND NEW.status <> 'Expired' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot set status Active when expiry_date is in the past.';
  END IF;

  IF NEW.expiry_date >= CURDATE() AND NEW.status <> 'Active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot set status Expired when expiry_date is in the future.';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_student_visa_validate_u` BEFORE UPDATE ON `student_visa` FOR EACH ROW BEGIN
  IF NEW.expiry_date < NEW.issue_date THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'expiry_date must be >= issue_date.';
  END IF;

  IF NEW.expiry_date < CURDATE() AND NEW.status <> 'Expired' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot set status Active when expiry_date is in the past.';
  END IF;

  IF NEW.expiry_date >= CURDATE() AND NEW.status <> 'Active' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot set status Expired when expiry_date is in the future.';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `undergraduate`
--

CREATE TABLE `undergraduate` (
  `student_id` int(11) NOT NULL,
  `high_school_name` varchar(100) DEFAULT NULL,
  `admission_score` decimal(5,2) DEFAULT NULL,
  `scholarship_flag` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `undergraduate`
--


-- --------------------------------------------------------

--
-- Table structure for table `unit_clearance`
--

CREATE TABLE `unit_clearance` (
  `unit_clearance_id` int(11) NOT NULL,
  `clearance_id` int(11) NOT NULL,
  `unit_name` varchar(50) NOT NULL,
  `clearance_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unit_clearance`
--


-- --------------------------------------------------------

--
-- Table structure for table `visa_document`
--

CREATE TABLE `visa_document` (
  `document_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `upload_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visa_document`
--


-- --------------------------------------------------------

--
-- Table structure for table `visa_notification_optout`
--

CREATE TABLE `visa_notification_optout` (
  `student_id` int(11) NOT NULL,
  `optout_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visa_renewal_application`
--

CREATE TABLE `visa_renewal_application` (
  `application_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_date` date NOT NULL,
  `requested_months` int(11) NOT NULL CHECK (`requested_months` > 0),
  `status` varchar(40) NOT NULL CHECK (`status` in ('Pending','Submitted passport to ISSU','Passport collected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visa_renewal_application`
--


--
-- Triggers `visa_renewal_application`
--
DELIMITER $$
CREATE TRIGGER `trg_vra_one_active_per_student` BEFORE INSERT ON `visa_renewal_application` FOR EACH ROW BEGIN
  IF EXISTS (
    SELECT 1
    FROM visa_renewal_application a
    WHERE a.student_id = NEW.student_id
      AND a.status <> 'Passport collected'
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Student already has an active visa renewal application.';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `visa_renewal_status`
--

CREATE TABLE `visa_renewal_status` (
  `status_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `stage_name` varchar(100) NOT NULL,
  `updated_date` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visa_renewal_status`
--


--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_dates`
--
ALTER TABLE `academic_dates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `clearance_record`
--
ALTER TABLE `clearance_record`
  ADD PRIMARY KEY (`clearance_id`),
  ADD UNIQUE KEY `exit_id` (`exit_id`);

--
-- Indexes for table `country`
--
ALTER TABLE `country`
  ADD PRIMARY KEY (`country_id`),
  ADD UNIQUE KEY `country_name` (`country_name`);

--
-- Indexes for table `exit_case`
--
ALTER TABLE `exit_case`
  ADD PRIMARY KEY (`exit_id`),
  ADD KEY `fk_exit_student` (`student_id`);

--
-- Indexes for table `exit_visa_action`
--
ALTER TABLE `exit_visa_action`
  ADD PRIMARY KEY (`exit_visa_id`),
  ADD KEY `fk_exitvisa_exit` (`exit_id`);

--
-- Indexes for table `insurance_claim`
--
ALTER TABLE `insurance_claim`
  ADD PRIMARY KEY (`claim_id`),
  ADD KEY `fk_claim_policy` (`policy_id`);

--
-- Indexes for table `insurance_policy`
--
ALTER TABLE `insurance_policy`
  ADD PRIMARY KEY (`policy_id`),
  ADD KEY `fk_policy_student` (`student_id`),
  ADD KEY `fk_policy_provider` (`provider_id`);

--
-- Indexes for table `insurance_provider`
--
ALTER TABLE `insurance_provider`
  ADD PRIMARY KEY (`provider_id`),
  ADD UNIQUE KEY `provider_name` (`provider_name`);

--
-- Indexes for table `insurance_renewal_record`
--
ALTER TABLE `insurance_renewal_record`
  ADD PRIMARY KEY (`renewal_id`),
  ADD KEY `fk_renewal_policy` (`policy_id`);

--
-- Indexes for table `nationality`
--
ALTER TABLE `nationality`
  ADD PRIMARY KEY (`student_id`,`country_id`),
  ADD KEY `fk_nat_country` (`country_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `post_graduate`
--
ALTER TABLE `post_graduate`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `pre_college`
--
ALTER TABLE `pre_college`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `program`
--
ALTER TABLE `program`
  ADD PRIMARY KEY (`program_id`),
  ADD KEY `fk_program_school` (`school_id`);

--
-- Indexes for table `reminder_queue`
--
ALTER TABLE `reminder_queue`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `fk_rq_student` (`student_id`);

--
-- Indexes for table `school`
--
ALTER TABLE `school`
  ADD PRIMARY KEY (`school_id`),
  ADD UNIQUE KEY `school_name` (`school_name`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_student_program` (`program_id`);

--
-- Indexes for table `student_visa`
--
ALTER TABLE `student_visa`
  ADD PRIMARY KEY (`visa_id`),
  ADD KEY `fk_visa_student` (`student_id`);

--
-- Indexes for table `undergraduate`
--
ALTER TABLE `undergraduate`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `unit_clearance`
--
ALTER TABLE `unit_clearance`
  ADD PRIMARY KEY (`unit_clearance_id`),
  ADD KEY `fk_unit_clearance` (`clearance_id`);

--
-- Indexes for table `visa_document`
--
ALTER TABLE `visa_document`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `fk_doc_application` (`application_id`);

--
-- Indexes for table `visa_notification_optout`
--
ALTER TABLE `visa_notification_optout`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `visa_renewal_application`
--
ALTER TABLE `visa_renewal_application`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `fk_vra_student` (`student_id`);

--
-- Indexes for table `visa_renewal_status`
--
ALTER TABLE `visa_renewal_status`
  ADD PRIMARY KEY (`status_id`),
  ADD KEY `fk_vrs_application` (`application_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_dates`
--
ALTER TABLE `academic_dates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clearance_record`
--
ALTER TABLE `clearance_record`
  MODIFY `clearance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `country`
--
ALTER TABLE `country`
  MODIFY `country_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `exit_case`
--
ALTER TABLE `exit_case`
  MODIFY `exit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `exit_visa_action`
--
ALTER TABLE `exit_visa_action`
  MODIFY `exit_visa_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `insurance_claim`
--
ALTER TABLE `insurance_claim`
  MODIFY `claim_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `insurance_policy`
--
ALTER TABLE `insurance_policy`
  MODIFY `policy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1002;

--
-- AUTO_INCREMENT for table `insurance_provider`
--
ALTER TABLE `insurance_provider`
  MODIFY `provider_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `insurance_renewal_record`
--
ALTER TABLE `insurance_renewal_record`
  MODIFY `renewal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `program`
--
ALTER TABLE `program`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `reminder_queue`
--
ALTER TABLE `reminder_queue`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `school`
--
ALTER TABLE `school`
  MODIFY `school_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24102181;

--
-- AUTO_INCREMENT for table `student_visa`
--
ALTER TABLE `student_visa`
  MODIFY `visa_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76896703;

--
-- AUTO_INCREMENT for table `unit_clearance`
--
ALTER TABLE `unit_clearance`
  MODIFY `unit_clearance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `visa_document`
--
ALTER TABLE `visa_document`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `visa_renewal_application`
--
ALTER TABLE `visa_renewal_application`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1002;

--
-- AUTO_INCREMENT for table `visa_renewal_status`
--
ALTER TABLE `visa_renewal_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2003;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_dates`
--
ALTER TABLE `academic_dates`
  ADD CONSTRAINT `academic_dates_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `program` (`program_id`) ON DELETE SET NULL;

--
-- Constraints for table `clearance_record`
--
ALTER TABLE `clearance_record`
  ADD CONSTRAINT `fk_clearance_exit` FOREIGN KEY (`exit_id`) REFERENCES `exit_case` (`exit_id`);

--
-- Constraints for table `exit_visa_action`
--
ALTER TABLE `exit_visa_action`
  ADD CONSTRAINT `fk_exitvisa_exit` FOREIGN KEY (`exit_id`) REFERENCES `exit_case` (`exit_id`);

--
-- Constraints for table `insurance_claim`
--
ALTER TABLE `insurance_claim`
  ADD CONSTRAINT `fk_claim_policy` FOREIGN KEY (`policy_id`) REFERENCES `insurance_policy` (`policy_id`);

--
-- Constraints for table `insurance_policy`
--
ALTER TABLE `insurance_policy`
  ADD CONSTRAINT `fk_policy_provider` FOREIGN KEY (`provider_id`) REFERENCES `insurance_provider` (`provider_id`),
  ADD CONSTRAINT `fk_policy_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`);

--
-- Constraints for table `insurance_renewal_record`
--
ALTER TABLE `insurance_renewal_record`
  ADD CONSTRAINT `fk_renewal_policy` FOREIGN KEY (`policy_id`) REFERENCES `insurance_policy` (`policy_id`);

--
-- Constraints for table `nationality`
--
ALTER TABLE `nationality`
  ADD CONSTRAINT `fk_nat_country` FOREIGN KEY (`country_id`) REFERENCES `country` (`country_id`),
  ADD CONSTRAINT `fk_nat_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `post_graduate`
--
ALTER TABLE `post_graduate`
  ADD CONSTRAINT `fk_pg_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`);

--
-- Constraints for table `pre_college`
--
ALTER TABLE `pre_college`
  ADD CONSTRAINT `fk_pc_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`);

--
-- Constraints for table `program`
--
ALTER TABLE `program`
  ADD CONSTRAINT `fk_program_school` FOREIGN KEY (`school_id`) REFERENCES `school` (`school_id`);

--
-- Constraints for table `reminder_queue`
--
ALTER TABLE `reminder_queue`
  ADD CONSTRAINT `fk_rq_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`);

--
-- Constraints for table `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `fk_student_program` FOREIGN KEY (`program_id`) REFERENCES `program` (`program_id`);

--
-- Constraints for table `student_visa`
--
ALTER TABLE `student_visa`
  ADD CONSTRAINT `fk_visa_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`);

--
-- Constraints for table `undergraduate`
--
ALTER TABLE `undergraduate`
  ADD CONSTRAINT `fk_ug_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`);

--
-- Constraints for table `unit_clearance`
--
ALTER TABLE `unit_clearance`
  ADD CONSTRAINT `fk_unit_clearance` FOREIGN KEY (`clearance_id`) REFERENCES `clearance_record` (`clearance_id`);

--
-- Constraints for table `visa_document`
--
ALTER TABLE `visa_document`
  ADD CONSTRAINT `fk_doc_application` FOREIGN KEY (`application_id`) REFERENCES `visa_renewal_application` (`application_id`);

--
-- Constraints for table `visa_notification_optout`
--
ALTER TABLE `visa_notification_optout`
  ADD CONSTRAINT `fk_vno_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`);

--
-- Constraints for table `visa_renewal_application`
--
ALTER TABLE `visa_renewal_application`
  ADD CONSTRAINT `fk_vra_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`);

--
-- Constraints for table `visa_renewal_status`
--
ALTER TABLE `visa_renewal_status`
  ADD CONSTRAINT `fk_vrs_application` FOREIGN KEY (`application_id`) REFERENCES `visa_renewal_application` (`application_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Security, password reset, audit, and notification updates

CREATE TABLE IF NOT EXISTS password_resets (
  reset_id INT AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('student','staff') NOT NULL,
  user_id INT NOT NULL,
  email VARCHAR(150) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_password_resets_token (token_hash),
  INDEX idx_password_resets_user (user_type, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  actor_id INT DEFAULT NULL,
  actor_role VARCHAR(50) DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(100) DEFAULT NULL,
  entity_id INT DEFAULT NULL,
  details TEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_actor (actor_id, actor_role),
  INDEX idx_audit_action (action),
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT DEFAULT NULL,
  staff_id INT DEFAULT NULL,
  notification_type VARCHAR(50) DEFAULT 'general',
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_student (student_id, is_read),
  INDEX idx_notifications_staff (staff_id, is_read),
  INDEX idx_notifications_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS staff_id INT DEFAULT NULL AFTER student_id,
  ADD COLUMN IF NOT EXISTS notification_type VARCHAR(50) DEFAULT 'general' AFTER staff_id;
