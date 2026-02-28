-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Feb 23, 2026 at 09:07 AM
-- Wersja serwera: 11.4.9-MariaDB
-- Wersja PHP: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Baza danych: `edux`
--

DELIMITER $$
--
-- Procedury
--
DROP PROCEDURE IF EXISTS `sp_cleanup_expired_sessions`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cleanup_expired_sessions` ()   BEGIN
  DELETE FROM `user_sessions`
  WHERE `expires_at` < NOW()
    OR `revoked_at` IS NOT NULL;
END$$

DROP PROCEDURE IF EXISTS `sp_cleanup_expired_tokens`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cleanup_expired_tokens` ()   BEGIN
  DELETE FROM `password_reset_tokens`
  WHERE `expires_at` < NOW()
    OR `used_at` IS NOT NULL;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `admins`
--

DROP TABLE IF EXISTS `admins`;
CREATE TABLE IF NOT EXISTS `admins` (
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Wyzwalacze `admins`
--
DROP TRIGGER IF EXISTS `trg_check_admin_role`;
DELIMITER $$
CREATE TRIGGER `trg_check_admin_role` BEFORE INSERT ON `admins` FOR EACH ROW BEGIN
  DECLARE v_role VARCHAR(100);

  SELECT r.role_name INTO v_role
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.user_id = NEW.user_id;

  IF v_role != 'admin' AND v_role != 'administrator' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Użytkownik nie ma roli administratora';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `announcements`
--

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE IF NOT EXISTS `announcements` (
  `announcement_id` int(11) NOT NULL AUTO_INCREMENT,
  `author_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` date DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`announcement_id`),
  KEY `fk_ann_author` (`author_id`),
  KEY `idx_ann_expires` (`expires_at`),
  KEY `idx_ann_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `announcement_reads`
--

DROP TABLE IF EXISTS `announcement_reads`;
CREATE TABLE IF NOT EXISTS `announcement_reads` (
  `announcement_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`announcement_id`,`user_id`),
  KEY `fk_ar_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `announcement_targets`
--

DROP TABLE IF EXISTS `announcement_targets`;
CREATE TABLE IF NOT EXISTS `announcement_targets` (
  `target_id` int(11) NOT NULL AUTO_INCREMENT,
  `announcement_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`target_id`),
  KEY `fk_at_announcement` (`announcement_id`),
  KEY `fk_at_class` (`class_id`),
  KEY `fk_at_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `attendance`
--

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `timetable_id` int(11) NOT NULL,
  `lesson_date` date NOT NULL,
  `status_id` int(11) NOT NULL,
  `noted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `excused_by` int(11) DEFAULT NULL,
  `excused_at` timestamp NULL DEFAULT NULL,
  `excuse_note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `uq_attendance` (`student_id`,`timetable_id`,`lesson_date`),
  KEY `fk_att_timetable` (`timetable_id`),
  KEY `fk_att_status` (`status_id`),
  KEY `fk_att_teacher` (`noted_by`),
  KEY `fk_att_excused_by` (`excused_by`),
  KEY `idx_attendance_date` (`lesson_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Wyzwalacze `attendance`
--
DROP TRIGGER IF EXISTS `trg_check_attendance_date`;
DELIMITER $$
CREATE TRIGGER `trg_check_attendance_date` BEFORE INSERT ON `attendance` FOR EACH ROW BEGIN
  DECLARE v_valid_from DATE;
  DECLARE v_valid_to DATE;

  SELECT valid_from, valid_to
    INTO v_valid_from, v_valid_to
    FROM timetables
    WHERE timetable_id = NEW.timetable_id;

  IF NEW.lesson_date < v_valid_from THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Data lekcji jest wcześniejsza niż początek obowiązywania planu';
  END IF;

  IF v_valid_to IS NOT NULL AND NEW.lesson_date > v_valid_to THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Data lekcji jest późniejsza niż koniec obowiązywania planu';
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_attendance_date_update`;
DELIMITER $$
CREATE TRIGGER `trg_check_attendance_date_update` BEFORE UPDATE ON `attendance` FOR EACH ROW BEGIN
  DECLARE v_valid_from DATE;
  DECLARE v_valid_to DATE;

  -- Sprawdzamy tylko jeśli zmieniono datę lub timetable_id
  IF NEW.lesson_date != OLD.lesson_date OR NEW.timetable_id != OLD.timetable_id THEN
    SELECT valid_from, valid_to
      INTO v_valid_from, v_valid_to
      FROM timetables
      WHERE timetable_id = NEW.timetable_id;

    IF NEW.lesson_date < v_valid_from THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Data lekcji jest wcześniejsza niż początek obowiązywania planu';
    END IF;

    IF v_valid_to IS NOT NULL AND NEW.lesson_date > v_valid_to THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Data lekcji jest późniejsza niż koniec obowiązywania planu';
    END IF;
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_attendance_weekday`;
DELIMITER $$
CREATE TRIGGER `trg_check_attendance_weekday` BEFORE INSERT ON `attendance` FOR EACH ROW BEGIN
  DECLARE v_day_id INT;
  DECLARE v_actual_weekday INT;

  SELECT day_of_week_id INTO v_day_id
    FROM timetables
    WHERE timetable_id = NEW.timetable_id;

  -- DAYOFWEEK() zwraca 1=Niedziela, 2=Poniedziałek, ..., 7=Sobota
  -- Zakładamy, że days_of_week.day_id: 1=Poniedziałek, ..., 7=Niedziela
  SET v_actual_weekday = WEEKDAY(NEW.lesson_date) + 1;

  IF v_day_id != v_actual_weekday THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Data lekcji nie odpowiada dniu tygodnia z planu lekcji';
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_attendance_weekday_update`;
DELIMITER $$
CREATE TRIGGER `trg_check_attendance_weekday_update` BEFORE UPDATE ON `attendance` FOR EACH ROW BEGIN
  DECLARE v_day_id INT;
  DECLARE v_actual_weekday INT;

  IF NEW.lesson_date != OLD.lesson_date OR NEW.timetable_id != OLD.timetable_id THEN
    SELECT day_of_week_id INTO v_day_id
      FROM timetables
      WHERE timetable_id = NEW.timetable_id;

    SET v_actual_weekday = WEEKDAY(NEW.lesson_date) + 1;

    IF v_day_id != v_actual_weekday THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Data lekcji nie odpowiada dniu tygodnia z planu lekcji';
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `attendance_statuses`
--

DROP TABLE IF EXISTS `attendance_statuses`;
CREATE TABLE IF NOT EXISTS `attendance_statuses` (
  `status_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE IF NOT EXISTS `audit_log` (
  `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'Kto dokonał zmiany (NULL = system)',
  `table_name` varchar(64) NOT NULL COMMENT 'Nazwa zmienianej tabeli',
  `record_id` int(11) NOT NULL COMMENT 'PK zmienianego rekordu',
  `action` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Poprzednie wartości (przy UPDATE/DELETE)' CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Nowe wartości (przy INSERT/UPDATE)' CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_audit_table` (`table_name`,`record_id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci COMMENT='Centralny dziennik zmian – audyt';

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `behaviour_grades`
--

DROP TABLE IF EXISTS `behaviour_grades`;
CREATE TABLE IF NOT EXISTS `behaviour_grades` (
  `bg_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `school_year_id` int(11) NOT NULL,
  `semester` tinyint(4) NOT NULL,
  `grade` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`bg_id`),
  UNIQUE KEY `uq_behaviour_grade` (`student_id`,`school_year_id`,`semester`),
  KEY `fk_bg_teacher` (`teacher_id`),
  KEY `fk_bg_school_year` (`school_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `behaviour_notes`
--

DROP TABLE IF EXISTS `behaviour_notes`;
CREATE TABLE IF NOT EXISTS `behaviour_notes` (
  `note_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `type_id` int(11) NOT NULL,
  `content` varchar(500) NOT NULL,
  `noted_at` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`note_id`),
  KEY `fk_bn_student` (`student_id`),
  KEY `fk_bn_teacher` (`teacher_id`),
  KEY `fk_bn_type` (`type_id`),
  KEY `idx_bn_student_date` (`student_id`,`noted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `behaviour_note_types`
--

DROP TABLE IF EXISTS `behaviour_note_types`;
CREATE TABLE IF NOT EXISTS `behaviour_note_types` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `positive` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `classes`
--

DROP TABLE IF EXISTS `classes`;
CREATE TABLE IF NOT EXISTS `classes` (
  `class_id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL,
  `letter` char(1) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `school_year_id` int(11) NOT NULL,
  PRIMARY KEY (`class_id`),
  UNIQUE KEY `uq_class_per_year` (`school_year_id`,`year`,`letter`),
  UNIQUE KEY `uq_teacher_per_year` (`teacher_id`,`school_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `classrooms`
--

DROP TABLE IF EXISTS `classrooms`;
CREATE TABLE IF NOT EXISTS `classrooms` (
  `classroom_id` int(11) NOT NULL AUTO_INCREMENT,
  `destination_id` int(11) DEFAULT NULL,
  `name` varchar(45) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  PRIMARY KEY (`classroom_id`),
  KEY `fk_classrooms_destinations` (`destination_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `classrooms_destinations`
--

DROP TABLE IF EXISTS `classrooms_destinations`;
CREATE TABLE IF NOT EXISTS `classrooms_destinations` (
  `destination_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`destination_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `countries`
--

DROP TABLE IF EXISTS `countries`;
CREATE TABLE IF NOT EXISTS `countries` (
  `country_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `abbreviation` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `days_of_week`
--

DROP TABLE IF EXISTS `days_of_week`;
CREATE TABLE IF NOT EXISTS `days_of_week` (
  `day_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `abbreviation` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`day_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `degrees`
--

DROP TABLE IF EXISTS `degrees`;
CREATE TABLE IF NOT EXISTS `degrees` (
  `degree_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `abbreviation` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`degree_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `exams`
--

DROP TABLE IF EXISTS `exams`;
CREATE TABLE IF NOT EXISTS `exams` (
  `exam_id` int(11) NOT NULL AUTO_INCREMENT,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `type_id` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`exam_id`),
  KEY `fk_exam_class` (`class_id`),
  KEY `fk_exam_subject` (`subject_id`),
  KEY `fk_exam_teacher` (`teacher_id`),
  KEY `fk_exam_type` (`type_id`),
  KEY `fk_exams_teachers_subjects_classes` (`teacher_id`,`subject_id`,`class_id`),
  KEY `idx_exam_class_date` (`class_id`,`exam_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Wyzwalacze `exams`
--
DROP TRIGGER IF EXISTS `trg_check_exam_date`;
DELIMITER $$
CREATE TRIGGER `trg_check_exam_date` BEFORE INSERT ON `exams` FOR EACH ROW BEGIN
  DECLARE v_start DATE;
  DECLARE v_end DATE;

  SELECT sy.start_date, sy.end_date
    INTO v_start, v_end
    FROM classes c
    JOIN school_years sy ON sy.school_year_id = c.school_year_id
    WHERE c.class_id = NEW.class_id;

  IF NEW.exam_date < v_start OR NEW.exam_date > v_end THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Data egzaminu jest poza zakresem roku szkolnego';
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_exam_date_update`;
DELIMITER $$
CREATE TRIGGER `trg_check_exam_date_update` BEFORE UPDATE ON `exams` FOR EACH ROW BEGIN
  DECLARE v_start DATE;
  DECLARE v_end DATE;

  IF NEW.exam_date != OLD.exam_date OR NEW.class_id != OLD.class_id THEN
    SELECT sy.start_date, sy.end_date
      INTO v_start, v_end
      FROM classes c
      JOIN school_years sy ON sy.school_year_id = c.school_year_id
      WHERE c.class_id = NEW.class_id;

    IF NEW.exam_date < v_start OR NEW.exam_date > v_end THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Data egzaminu jest poza zakresem roku szkolnego';
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `exam_types`
--

DROP TABLE IF EXISTS `exam_types`;
CREATE TABLE IF NOT EXISTS `exam_types` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `counts_towards_limit` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `final_grades`
--

DROP TABLE IF EXISTS `final_grades`;
CREATE TABLE IF NOT EXISTS `final_grades` (
  `final_grade_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `subject_id` int(11) NOT NULL,
  `school_year_id` int(11) NOT NULL,
  `semester` tinyint(4) NOT NULL,
  `grade` tinyint(4) NOT NULL,
  `is_final_year` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `_final_year_guard` tinyint(4) GENERATED ALWAYS AS (case when `is_final_year` = 1 then 1 else NULL end) VIRTUAL COMMENT 'Kolumna pomocnicza – UNIQUE ignoruje NULLe, więc ograniczenie działa tylko dla is_final_year=1',
  PRIMARY KEY (`final_grade_id`),
  UNIQUE KEY `uq_final_grade_semester` (`student_id`,`subject_id`,`school_year_id`,`semester`),
  UNIQUE KEY `uq_final_grade_year_v2` (`student_id`,`subject_id`,`school_year_id`,`_final_year_guard`),
  KEY `fk_fg_teacher` (`teacher_id`),
  KEY `fk_fg_subject` (`subject_id`),
  KEY `fk_fg_school_year` (`school_year_id`),
  KEY `idx_fg_student_year` (`student_id`,`school_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `grades`
--

DROP TABLE IF EXISTS `grades`;
CREATE TABLE IF NOT EXISTS `grades` (
  `grade_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `subject_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `grade` decimal(3,1) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `graded_at` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `color` char(7) DEFAULT '#000000',
  PRIMARY KEY (`grade_id`),
  KEY `fk_grades_student` (`student_id`),
  KEY `fk_grades_teacher` (`teacher_id`),
  KEY `fk_grades_subject` (`subject_id`),
  KEY `fk_grades_category` (`category_id`),
  KEY `idx_grades_student_subject` (`student_id`,`subject_id`),
  KEY `idx_grades_graded_at` (`graded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `grade_categories`
--

DROP TABLE IF EXISTS `grade_categories`;
CREATE TABLE IF NOT EXISTS `grade_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `weight` decimal(3,2) NOT NULL DEFAULT 1.00,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `lessons_hours`
--

DROP TABLE IF EXISTS `lessons_hours`;
CREATE TABLE IF NOT EXISTS `lessons_hours` (
  `lesson_id` int(11) NOT NULL AUTO_INCREMENT,
  `number` int(11) NOT NULL,
  `start_hour` time DEFAULT NULL,
  `end_hour` time DEFAULT NULL,
  PRIMARY KEY (`lesson_id`),
  UNIQUE KEY `number_UNIQUE` (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `lesson_records`
--

DROP TABLE IF EXISTS `lesson_records`;
CREATE TABLE IF NOT EXISTS `lesson_records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `timetable_id` int(11) NOT NULL,
  `lesson_date` date NOT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `homework` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `uq_lesson_record` (`timetable_id`,`lesson_date`),
  KEY `fk_lr_teacher` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Wyzwalacze `lesson_records`
--
DROP TRIGGER IF EXISTS `trg_check_lesson_record_date`;
DELIMITER $$
CREATE TRIGGER `trg_check_lesson_record_date` BEFORE INSERT ON `lesson_records` FOR EACH ROW BEGIN
  DECLARE v_valid_from DATE;
  DECLARE v_valid_to DATE;

  SELECT valid_from, valid_to
    INTO v_valid_from, v_valid_to
    FROM timetables
    WHERE timetable_id = NEW.timetable_id;

  IF NEW.lesson_date < v_valid_from THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Data lekcji jest wcześniejsza niż początek obowiązywania planu';
  END IF;

  IF v_valid_to IS NOT NULL AND NEW.lesson_date > v_valid_to THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Data lekcji jest późniejsza niż koniec obowiązywania planu';
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_lesson_record_date_update`;
DELIMITER $$
CREATE TRIGGER `trg_check_lesson_record_date_update` BEFORE UPDATE ON `lesson_records` FOR EACH ROW BEGIN
  DECLARE v_valid_from DATE;
  DECLARE v_valid_to DATE;

  IF NEW.lesson_date != OLD.lesson_date OR NEW.timetable_id != OLD.timetable_id THEN
    SELECT valid_from, valid_to
      INTO v_valid_from, v_valid_to
      FROM timetables
      WHERE timetable_id = NEW.timetable_id;

    IF NEW.lesson_date < v_valid_from THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Data lekcji jest wcześniejsza niż początek obowiązywania planu';
    END IF;

    IF v_valid_to IS NOT NULL AND NEW.lesson_date > v_valid_to THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Data lekcji jest późniejsza niż koniec obowiązywania planu';
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `messages`
--

DROP TABLE IF EXISTS `messages`;
CREATE TABLE IF NOT EXISTS `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `thread_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `fk_msg_thread` (`thread_id`),
  KEY `fk_msg_sender` (`sender_id`),
  KEY `idx_msg_thread_created` (`thread_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `message_threads`
--

DROP TABLE IF EXISTS `message_threads`;
CREATE TABLE IF NOT EXISTS `message_threads` (
  `thread_id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `message_thread_participants`
--

DROP TABLE IF EXISTS `message_thread_participants`;
CREATE TABLE IF NOT EXISTS `message_thread_participants` (
  `thread_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`thread_id`,`user_id`),
  KEY `fk_mtp_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` varchar(500) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'Typ referencji: grade, attendance, announcement, message, exam itp.',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_notif_user` (`user_id`,`read_at`),
  KEY `fk_notif_type` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `notification_types`
--

DROP TABLE IF EXISTS `notification_types`;
CREATE TABLE IF NOT EXISTS `notification_types` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `parents`
--

DROP TABLE IF EXISTS `parents`;
CREATE TABLE IF NOT EXISTS `parents` (
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Wyzwalacze `parents`
--
DROP TRIGGER IF EXISTS `trg_check_parent_role`;
DELIMITER $$
CREATE TRIGGER `trg_check_parent_role` BEFORE INSERT ON `parents` FOR EACH ROW BEGIN
  DECLARE v_role VARCHAR(100);

  SELECT r.role_name INTO v_role
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.user_id = NEW.user_id;

  IF v_role != 'rodzic' AND v_role != 'parent' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Użytkownik nie ma roli rodzica';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `token_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `fk_prt_user` (`user_id`),
  KEY `idx_prt_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `school_years`
--

DROP TABLE IF EXISTS `school_years`;
CREATE TABLE IF NOT EXISTS `school_years` (
  `school_year_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  PRIMARY KEY (`school_year_id`),
  UNIQUE KEY `uq_school_year_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Wyzwalacze `school_years`
--
DROP TRIGGER IF EXISTS `trg_check_school_year_dates`;
DELIMITER $$
CREATE TRIGGER `trg_check_school_year_dates` BEFORE INSERT ON `school_years` FOR EACH ROW BEGIN
  IF NEW.end_date <= NEW.start_date THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Data końca roku szkolnego musi być późniejsza niż data początku';
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_school_year_dates_update`;
DELIMITER $$
CREATE TRIGGER `trg_check_school_year_dates_update` BEFORE UPDATE ON `school_years` FOR EACH ROW BEGIN
  IF NEW.end_date <= NEW.start_date THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Data końca roku szkolnego musi być późniejsza niż data początku';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `statuses`
--

DROP TABLE IF EXISTS `statuses`;
CREATE TABLE IF NOT EXISTS `statuses` (
  `status_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(45) NOT NULL,
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `students`
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `user_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `PESEL` char(11) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `PESEL_UNIQUE` (`PESEL`),
  KEY `fk_students_class` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Wyzwalacze `students`
--
DROP TRIGGER IF EXISTS `trg_check_capacity_on_student_insert`;
DELIMITER $$
CREATE TRIGGER `trg_check_capacity_on_student_insert` BEFORE INSERT ON `students` FOR EACH ROW BEGIN
    DECLARE v_min_capacity INT;
    DECLARE v_current_class_size INT;

    IF NEW.class_id IS NOT NULL THEN
        -- Sprawdzamy obecną liczbę uczniów w klasie
        SELECT COUNT(*) INTO v_current_class_size
        FROM `students`
        WHERE `class_id` = NEW.class_id;

        -- Szukamy najmniejszej sali przypisanej do tej klasy w planie lekcji
        SELECT MIN(c.capacity) INTO v_min_capacity
        FROM `timetables` t
        JOIN `classrooms` c ON t.classroom_id = c.classroom_id
        WHERE t.class_id = NEW.class_id;

        -- Zgłaszamy błąd, jeśli nowy uczeń nie zmieści się w najmniejszej sali
        IF v_min_capacity IS NOT NULL AND (v_current_class_size + 1) > v_min_capacity THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Dodanie ucznia przekroczy pojemność jednej z sal przypisanych do tej klasy w planie lekcji';
        END IF;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_capacity_on_student_update`;
DELIMITER $$
CREATE TRIGGER `trg_check_capacity_on_student_update` BEFORE UPDATE ON `students` FOR EACH ROW BEGIN
  DECLARE v_min_capacity INT;
  DECLARE v_current_class_size INT;

  -- Sprawdzamy tylko jeśli zmieniono class_id
  IF NEW.class_id IS NOT NULL AND (OLD.class_id IS NULL OR NEW.class_id != OLD.class_id) THEN
    SELECT COUNT(*) INTO v_current_class_size
      FROM `students`
      WHERE `class_id` = NEW.class_id
        AND `user_id` != NEW.user_id;

    SELECT MIN(c.capacity) INTO v_min_capacity
      FROM `timetables` t
      JOIN `classrooms` c ON t.classroom_id = c.classroom_id
      WHERE t.class_id = NEW.class_id;

    IF v_min_capacity IS NOT NULL AND (v_current_class_size + 1) > v_min_capacity THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Przeniesienie ucznia przekroczy pojemność jednej z sal przypisanych do klasy';
    END IF;
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_student_role`;
DELIMITER $$
CREATE TRIGGER `trg_check_student_role` BEFORE INSERT ON `students` FOR EACH ROW BEGIN
  DECLARE v_role VARCHAR(100);

  SELECT r.role_name INTO v_role
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.user_id = NEW.user_id;

  IF v_role != 'uczeń' AND v_role != 'uczen' AND v_role != 'student' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Użytkownik nie ma roli ucznia';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `students_parents`
--

DROP TABLE IF EXISTS `students_parents`;
CREATE TABLE IF NOT EXISTS `students_parents` (
  `student_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  PRIMARY KEY (`student_id`,`parent_id`),
  KEY `fk_sp_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `subjects`
--

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `abbreviation` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `substitutions`
--

DROP TABLE IF EXISTS `substitutions`;
CREATE TABLE IF NOT EXISTS `substitutions` (
  `substitution_id` int(11) NOT NULL AUTO_INCREMENT,
  `timetable_id` int(11) NOT NULL,
  `substitution_date` date NOT NULL,
  `substitute_teacher_id` int(11) DEFAULT NULL,
  `classroom_id` int(11) DEFAULT NULL,
  `cancelled` tinyint(1) NOT NULL DEFAULT 0,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`substitution_id`),
  UNIQUE KEY `uq_substitution` (`timetable_id`,`substitution_date`),
  KEY `fk_sub_teacher` (`substitute_teacher_id`),
  KEY `fk_sub_classroom` (`classroom_id`),
  KEY `idx_sub_date` (`substitution_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `teachers`
--

DROP TABLE IF EXISTS `teachers`;
CREATE TABLE IF NOT EXISTS `teachers` (
  `user_id` int(11) NOT NULL,
  `degree_id` int(11) DEFAULT NULL,
  `PESEL` char(11) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `PESEL_UNIQUE` (`PESEL`),
  KEY `fk_teachers_degree` (`degree_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Wyzwalacze `teachers`
--
DROP TRIGGER IF EXISTS `trg_check_teacher_role`;
DELIMITER $$
CREATE TRIGGER `trg_check_teacher_role` BEFORE INSERT ON `teachers` FOR EACH ROW BEGIN
  DECLARE v_role VARCHAR(100);

  SELECT r.role_name INTO v_role
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    WHERE u.user_id = NEW.user_id;

  IF v_role != 'nauczyciel' AND v_role != 'teacher' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Użytkownik nie ma roli nauczyciela';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `teachers_subjects_classes`
--

DROP TABLE IF EXISTS `teachers_subjects_classes`;
CREATE TABLE IF NOT EXISTS `teachers_subjects_classes` (
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  PRIMARY KEY (`teacher_id`,`subject_id`,`class_id`),
  KEY `fk_tsc_subject` (`subject_id`),
  KEY `fk_tsc_class` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `timetables`
--

DROP TABLE IF EXISTS `timetables`;
CREATE TABLE IF NOT EXISTS `timetables` (
  `timetable_id` int(11) NOT NULL AUTO_INCREMENT,
  `day_of_week_id` int(11) NOT NULL,
  `lesson_hour_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `classroom_id` int(11) DEFAULT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  PRIMARY KEY (`timetable_id`),
  KEY `fk_tt_day` (`day_of_week_id`),
  KEY `fk_tt_hour` (`lesson_hour_id`),
  KEY `fk_tt_class` (`class_id`),
  KEY `fk_tt_subject` (`subject_id`),
  KEY `fk_tt_teacher` (`teacher_id`),
  KEY `fk_tt_classroom` (`classroom_id`),
  KEY `idx_tt_class_day` (`class_id`,`day_of_week_id`,`lesson_hour_id`),
  KEY `idx_tt_teacher_day` (`teacher_id`,`day_of_week_id`,`lesson_hour_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

--
-- Wyzwalacze `timetables`
--
DROP TRIGGER IF EXISTS `trg_check_classroom_capacity`;
DELIMITER $$
CREATE TRIGGER `trg_check_classroom_capacity` BEFORE INSERT ON `timetables` FOR EACH ROW BEGIN
  DECLARE v_capacity INT;
  DECLARE v_class_size INT;

  IF NEW.classroom_id IS NOT NULL THEN
    SELECT capacity INTO v_capacity
      FROM classrooms WHERE classroom_id = NEW.classroom_id;

    SELECT COUNT(*) INTO v_class_size
      FROM students WHERE class_id = NEW.class_id;

    IF v_class_size > v_capacity THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Liczba uczniów przekracza pojemność sali';
    END IF;
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_classroom_capacity_update`;
DELIMITER $$
CREATE TRIGGER `trg_check_classroom_capacity_update` BEFORE UPDATE ON `timetables` FOR EACH ROW BEGIN
  DECLARE v_capacity INT;
  DECLARE v_class_size INT;

  IF NEW.classroom_id IS NOT NULL THEN
    SELECT capacity INTO v_capacity
      FROM classrooms
      WHERE classroom_id = NEW.classroom_id;

    SELECT COUNT(*) INTO v_class_size
      FROM students
      WHERE class_id = NEW.class_id;

    IF v_class_size > v_capacity THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Liczba uczniów przekracza pojemność sali';
    END IF;
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_timetable_conflicts`;
DELIMITER $$
CREATE TRIGGER `trg_check_timetable_conflicts` BEFORE INSERT ON `timetables` FOR EACH ROW BEGIN
  DECLARE v_teacher_conflict INT DEFAULT 0;
  DECLARE v_classroom_conflict INT DEFAULT 0;

  -- Sprawdzanie konfliktu nauczyciela
  SELECT COUNT(*) INTO v_teacher_conflict
    FROM `timetables`
    WHERE `teacher_id` = NEW.teacher_id
      AND `day_of_week_id` = NEW.day_of_week_id
      AND `lesson_hour_id` = NEW.lesson_hour_id
      AND `valid_from` <= IFNULL(NEW.valid_to, '9999-12-31')
      AND IFNULL(`valid_to`, '9999-12-31') >= NEW.valid_from;

  IF v_teacher_conflict > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Konflikt: nauczyciel ma już zajęcia w tym czasie';
  END IF;

  -- Sprawdzanie konfliktu sali
  IF NEW.classroom_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_classroom_conflict
      FROM `timetables`
      WHERE `classroom_id` = NEW.classroom_id
        AND `day_of_week_id` = NEW.day_of_week_id
        AND `lesson_hour_id` = NEW.lesson_hour_id
        AND `valid_from` <= IFNULL(NEW.valid_to, '9999-12-31')
        AND IFNULL(`valid_to`, '9999-12-31') >= NEW.valid_from;

    IF v_classroom_conflict > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Konflikt: sala jest już zajęta w tym czasie';
    END IF;
  END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_timetable_conflicts_update`;
DELIMITER $$
CREATE TRIGGER `trg_check_timetable_conflicts_update` BEFORE UPDATE ON `timetables` FOR EACH ROW BEGIN
  DECLARE v_teacher_conflict INT DEFAULT 0;
  DECLARE v_classroom_conflict INT DEFAULT 0;

  -- Sprawdzanie konfliktu nauczyciela (wykluczamy bieżący rekord)
  SELECT COUNT(*) INTO v_teacher_conflict
    FROM `timetables`
    WHERE `timetable_id` != NEW.timetable_id
      AND `teacher_id` = NEW.teacher_id
      AND `day_of_week_id` = NEW.day_of_week_id
      AND `lesson_hour_id` = NEW.lesson_hour_id
      AND `valid_from` <= IFNULL(NEW.valid_to, '9999-12-31')
      AND IFNULL(`valid_to`, '9999-12-31') >= NEW.valid_from;

  IF v_teacher_conflict > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Konflikt: nauczyciel ma już zajęcia w tym czasie';
  END IF;

  -- Sprawdzanie konfliktu sali
  IF NEW.classroom_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_classroom_conflict
      FROM `timetables`
      WHERE `timetable_id` != NEW.timetable_id
        AND `classroom_id` = NEW.classroom_id
        AND `day_of_week_id` = NEW.day_of_week_id
        AND `lesson_hour_id` = NEW.lesson_hour_id
        AND `valid_from` <= IFNULL(NEW.valid_to, '9999-12-31')
        AND IFNULL(`valid_to`, '9999-12-31') >= NEW.valid_from;

    IF v_classroom_conflict > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Konflikt: sala jest już zajęta w tym czasie';
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `second_name` varchar(100) DEFAULT NULL,
  `surname` varchar(100) DEFAULT NULL,
  `email_address` varchar(255) NOT NULL,
  `login` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `street` varchar(100) DEFAULT NULL,
  `building_number` varchar(10) DEFAULT NULL,
  `apartment_number` varchar(10) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `last_password_change` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `login_UNIQUE` (`login`),
  UNIQUE KEY `uq_email` (`email_address`),
  KEY `fk_users_role` (`role_id`),
  KEY `fk_users_status` (`status_id`),
  KEY `fk_users_country` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  UNIQUE KEY `uq_session_token` (`token`),
  KEY `fk_sess_user` (`user_id`),
  KEY `idx_sess_expires` (`expires_at`),
  KEY `idx_sess_revoked` (`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- --------------------------------------------------------

--
-- Zastąpiona struktura widoku `v_active_announcements`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_active_announcements`;
CREATE TABLE IF NOT EXISTS `v_active_announcements` (
`announcement_id` int(11)
,`author_id` int(11)
,`title` varchar(255)
,`content` text
,`created_at` timestamp
,`expires_at` date
);

-- --------------------------------------------------------

--
-- Zastąpiona struktura widoku `v_active_sessions`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_active_sessions`;
CREATE TABLE IF NOT EXISTS `v_active_sessions` (
`session_id` int(11)
,`user_id` int(11)
,`token` varchar(255)
,`ip_address` varchar(45)
,`user_agent` varchar(255)
,`created_at` timestamp
,`expires_at` timestamp
);

-- --------------------------------------------------------

--
-- Zastąpiona struktura widoku `v_current_timetable`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_current_timetable`;
CREATE TABLE IF NOT EXISTS `v_current_timetable` (
`timetable_id` int(11)
,`day_of_week_id` int(11)
,`day_name` varchar(100)
,`lesson_hour_id` int(11)
,`lesson_number` int(11)
,`start_hour` time
,`end_hour` time
,`class_id` int(11)
,`class_name` varchar(12)
,`subject_id` int(11)
,`subject_name` varchar(255)
,`teacher_id` int(11)
,`teacher_name` varchar(201)
,`classroom_id` int(11)
,`classroom_name` varchar(45)
,`valid_from` date
,`valid_to` date
);

-- --------------------------------------------------------

--
-- Zastąpiona struktura widoku `v_student_attendance_stats`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_student_attendance_stats`;
CREATE TABLE IF NOT EXISTS `v_student_attendance_stats` (
`student_id` int(11)
,`total_entries` bigint(21)
,`present_count` decimal(22,0)
,`absent_count` decimal(22,0)
,`late_count` decimal(22,0)
,`excused_count` decimal(22,0)
,`attendance_pct` decimal(27,1)
);

-- --------------------------------------------------------

--
-- Zastąpiona struktura widoku `v_student_grade_averages`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `v_student_grade_averages`;
CREATE TABLE IF NOT EXISTS `v_student_grade_averages` (
`student_id` int(11)
,`subject_id` int(11)
,`subject_name` varchar(255)
,`weighted_average` decimal(30,2)
,`grade_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Struktura widoku `v_active_announcements`
--
DROP TABLE IF EXISTS `v_active_announcements`;

DROP VIEW IF EXISTS `v_active_announcements`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_active_announcements`  AS SELECT `a`.`announcement_id` AS `announcement_id`, `a`.`author_id` AS `author_id`, `a`.`title` AS `title`, `a`.`content` AS `content`, `a`.`created_at` AS `created_at`, `a`.`expires_at` AS `expires_at` FROM `announcements` AS `a` WHERE `a`.`deleted_at` is null AND (`a`.`expires_at` is null OR `a`.`expires_at` >= curdate()) ;

-- --------------------------------------------------------

--
-- Struktura widoku `v_active_sessions`
--
DROP TABLE IF EXISTS `v_active_sessions`;

DROP VIEW IF EXISTS `v_active_sessions`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_active_sessions`  AS SELECT `s`.`session_id` AS `session_id`, `s`.`user_id` AS `user_id`, `s`.`token` AS `token`, `s`.`ip_address` AS `ip_address`, `s`.`user_agent` AS `user_agent`, `s`.`created_at` AS `created_at`, `s`.`expires_at` AS `expires_at` FROM `user_sessions` AS `s` WHERE `s`.`revoked_at` is null AND `s`.`expires_at` > current_timestamp() ;

-- --------------------------------------------------------

--
-- Struktura widoku `v_current_timetable`
--
DROP TABLE IF EXISTS `v_current_timetable`;

DROP VIEW IF EXISTS `v_current_timetable`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_current_timetable`  AS SELECT `t`.`timetable_id` AS `timetable_id`, `t`.`day_of_week_id` AS `day_of_week_id`, `d`.`name` AS `day_name`, `t`.`lesson_hour_id` AS `lesson_hour_id`, `lh`.`number` AS `lesson_number`, `lh`.`start_hour` AS `start_hour`, `lh`.`end_hour` AS `end_hour`, `t`.`class_id` AS `class_id`, concat(`cl`.`year`,`cl`.`letter`) AS `class_name`, `t`.`subject_id` AS `subject_id`, `s`.`name` AS `subject_name`, `t`.`teacher_id` AS `teacher_id`, concat(`u`.`first_name`,' ',`u`.`surname`) AS `teacher_name`, `t`.`classroom_id` AS `classroom_id`, `cr`.`name` AS `classroom_name`, `t`.`valid_from` AS `valid_from`, `t`.`valid_to` AS `valid_to` FROM (((((((`timetables` `t` join `days_of_week` `d` on(`d`.`day_id` = `t`.`day_of_week_id`)) join `lessons_hours` `lh` on(`lh`.`lesson_id` = `t`.`lesson_hour_id`)) join `classes` `cl` on(`cl`.`class_id` = `t`.`class_id`)) left join `subjects` `s` on(`s`.`subject_id` = `t`.`subject_id`)) join `teachers` `tc` on(`tc`.`user_id` = `t`.`teacher_id`)) join `users` `u` on(`u`.`user_id` = `tc`.`user_id`)) left join `classrooms` `cr` on(`cr`.`classroom_id` = `t`.`classroom_id`)) WHERE `t`.`valid_from` <= curdate() AND (`t`.`valid_to` is null OR `t`.`valid_to` >= curdate()) ;

-- --------------------------------------------------------

--
-- Struktura widoku `v_student_attendance_stats`
--
DROP TABLE IF EXISTS `v_student_attendance_stats`;

DROP VIEW IF EXISTS `v_student_attendance_stats`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_student_attendance_stats`  AS SELECT `a`.`student_id` AS `student_id`, count(0) AS `total_entries`, sum(case when `ast`.`name` = 'obecny' then 1 else 0 end) AS `present_count`, sum(case when `ast`.`name` = 'nieobecny' then 1 else 0 end) AS `absent_count`, sum(case when `ast`.`name` = 'spóźniony' then 1 else 0 end) AS `late_count`, sum(case when `ast`.`name` = 'usprawiedliwiony' then 1 else 0 end) AS `excused_count`, round(sum(case when `ast`.`name` = 'obecny' then 1 else 0 end) * 100.0 / count(0),1) AS `attendance_pct` FROM (`attendance` `a` join `attendance_statuses` `ast` on(`ast`.`status_id` = `a`.`status_id`)) GROUP BY `a`.`student_id` ;

-- --------------------------------------------------------

--
-- Struktura widoku `v_student_grade_averages`
--
DROP TABLE IF EXISTS `v_student_grade_averages`;

DROP VIEW IF EXISTS `v_student_grade_averages`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_student_grade_averages`  AS SELECT `g`.`student_id` AS `student_id`, `g`.`subject_id` AS `subject_id`, `s`.`name` AS `subject_name`, round(sum(`g`.`grade` * ifnull(`gc`.`weight`,1)) / sum(ifnull(`gc`.`weight`,1)),2) AS `weighted_average`, count(`g`.`grade_id`) AS `grade_count` FROM ((`grades` `g` join `subjects` `s` on(`s`.`subject_id` = `g`.`subject_id`)) left join `grade_categories` `gc` on(`gc`.`category_id` = `g`.`category_id`)) GROUP BY `g`.`student_id`, `g`.`subject_id`, `s`.`name` ;

--
-- Ograniczenia dla zrzutów tabel
--

--
-- Ograniczenia dla tabeli `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `fk_admins_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_ann_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Ograniczenia dla tabeli `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD CONSTRAINT `fk_ar_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ar_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_att_excused_by` FOREIGN KEY (`excused_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_att_status` FOREIGN KEY (`status_id`) REFERENCES `attendance_statuses` (`status_id`),
  ADD CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_att_teacher` FOREIGN KEY (`noted_by`) REFERENCES `teachers` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_att_timetable` FOREIGN KEY (`timetable_id`) REFERENCES `timetables` (`timetable_id`);

--
-- Ograniczenia dla tabeli `behaviour_notes`
--
ALTER TABLE `behaviour_notes`
  ADD CONSTRAINT `fk_bn_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bn_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bn_type` FOREIGN KEY (`type_id`) REFERENCES `behaviour_note_types` (`type_id`);

--
-- Ograniczenia dla tabeli `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `fk_exam_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`),
  ADD CONSTRAINT `fk_exam_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `fk_exam_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_exam_type` FOREIGN KEY (`type_id`) REFERENCES `exam_types` (`type_id`);

--
-- Ograniczenia dla tabeli `lesson_records`
--
ALTER TABLE `lesson_records`
  ADD CONSTRAINT `fk_lr_teacher` FOREIGN KEY (`created_by`) REFERENCES `teachers` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lr_timetable` FOREIGN KEY (`timetable_id`) REFERENCES `timetables` (`timetable_id`);

--
-- Ograniczenia dla tabeli `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_msg_thread` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`thread_id`);

--
-- Ograniczenia dla tabeli `message_thread_participants`
--
ALTER TABLE `message_thread_participants`
  ADD CONSTRAINT `fk_mtp_thread` FOREIGN KEY (`thread_id`) REFERENCES `message_threads` (`thread_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mtp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_type` FOREIGN KEY (`type_id`) REFERENCES `notification_types` (`type_id`),
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `fk_parents_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `students_parents`
--
ALTER TABLE `students_parents`
  ADD CONSTRAINT `fk_sp_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sp_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`user_id`) ON DELETE CASCADE;

--
-- Ograniczenia dla tabeli `substitutions`
--
ALTER TABLE `substitutions`
  ADD CONSTRAINT `fk_sub_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`classroom_id`),
  ADD CONSTRAINT `fk_sub_teacher` FOREIGN KEY (`substitute_teacher_id`) REFERENCES `teachers` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sub_timetable` FOREIGN KEY (`timetable_id`) REFERENCES `timetables` (`timetable_id`);

--
-- Ograniczenia dla tabeli `teachers_subjects_classes`
--
ALTER TABLE `teachers_subjects_classes`
  ADD CONSTRAINT `fk_tsc_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`),
  ADD CONSTRAINT `fk_tsc_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `fk_tsc_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`user_id`);

--
-- Ograniczenia dla tabeli `timetables`
--
ALTER TABLE `timetables`
  ADD CONSTRAINT `fk_tt_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`),
  ADD CONSTRAINT `fk_tt_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classrooms` (`classroom_id`),
  ADD CONSTRAINT `fk_tt_day` FOREIGN KEY (`day_of_week_id`) REFERENCES `days_of_week` (`day_id`),
  ADD CONSTRAINT `fk_tt_hour` FOREIGN KEY (`lesson_hour_id`) REFERENCES `lessons_hours` (`lesson_id`),
  ADD CONSTRAINT `fk_tt_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `fk_tt_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`user_id`);

--
-- Ograniczenia dla tabeli `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_sess_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

DELIMITER $$
--
-- Zdarzenia
--
DROP EVENT IF EXISTS `evt_cleanup_sessions`$$
CREATE DEFINER=`root`@`localhost` EVENT `evt_cleanup_sessions` ON SCHEDULE EVERY 1 HOUR STARTS '2026-02-22 22:12:58' ON COMPLETION NOT PRESERVE ENABLE DO CALL `sp_cleanup_expired_sessions`()$$

DROP EVENT IF EXISTS `evt_cleanup_tokens`$$
CREATE DEFINER=`root`@`localhost` EVENT `evt_cleanup_tokens` ON SCHEDULE EVERY 1 DAY STARTS '2026-02-22 22:12:58' ON COMPLETION NOT PRESERVE ENABLE DO CALL `sp_cleanup_expired_tokens`()$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
