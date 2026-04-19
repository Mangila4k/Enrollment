-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2026 at 11:23 PM
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
-- Database: `hs_enrollment`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `status` enum('Present','Absent','Late') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_knowledge`
--

CREATE TABLE `chatbot_knowledge` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `keywords` text NOT NULL,
  `response` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chatbot_knowledge`
--

INSERT INTO `chatbot_knowledge` (`id`, `category`, `keywords`, `response`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Enrollment', 'enroll,enrollment,register,sign up,admission', 'To enroll in courses, please visit the Enrollment page. You can submit your enrollment request online or visit the registrar\'s office during office hours.', 1, '2026-02-26 17:43:21', '2026-02-26 17:43:21'),
(2, 'Schedule', 'schedule,class schedule,timetable,classes timing', 'You can view your complete class schedule on the Schedule page. For personalized schedule, please log in to your student portal.', 1, '2026-02-26 17:43:21', '2026-02-26 17:43:21'),
(3, 'Attendance', 'attendance,absent,present,leave,absence', 'Your attendance is tracked by your teachers. You can check your attendance status on the Attendance page.', 1, '2026-02-26 17:43:21', '2026-02-26 17:43:21'),
(4, 'Office Hours', 'office hours,teacher hours,faculty hours,meeting time', 'Office hours vary by teacher. Check the specific teacher\'s schedule in the faculty directory.', 1, '2026-02-26 17:43:21', '2026-02-26 17:43:21'),
(5, 'Enrollment Requirements', 'requirements,documents,need to submit,required papers', '📋 For Grade 7 enrollment, you need: Form 138, Certificate of Completion, PSA Birth Certificate, 2x2 ID Pictures, and Good Moral Certificate. For transferees, additional documents like Form 137 may be required.', 1, '2026-04-17 19:50:03', '2026-04-17 19:50:03'),
(6, 'Schedule Viewing', 'view schedule,my classes,subjects', '📅 You can view your class schedule by clicking \"Class Schedule\" in the sidebar. Your current section is {section} and grade level is {grade}.', 1, '2026-04-17 19:50:03', '2026-04-17 19:50:03'),
(7, 'Grades Inquiry', 'my grades,grade average,performance', '📚 Your current average grade is {grade}. You can view detailed grades per subject in the \"My Grades\" section.', 1, '2026-04-17 19:50:03', '2026-04-17 19:50:03'),
(8, 'Profile Update', 'update profile,change password,edit info', '👤 Go to \"My Profile\" in the ACCOUNT section to update your personal information, change your profile picture, or update your password.', 1, '2026-04-17 19:50:03', '2026-04-17 19:50:03');

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_logs`
--

CREATE TABLE `chatbot_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` enum('student','teacher','registrar','admin') DEFAULT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_schedules`
--

CREATE TABLE `class_schedules` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `day_id` int(11) NOT NULL,
  `time_slot_id` int(11) NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `school_year` varchar(20) NOT NULL,
  `quarter` int(11) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_schedules`
--

INSERT INTO `class_schedules` (`id`, `section_id`, `subject_id`, `teacher_id`, `day_id`, `time_slot_id`, `room`, `school_year`, `quarter`, `status`, `created_at`, `updated_at`) VALUES
(5, 4, 4, 9, 1, 1, 'RM 301', '2026-2027', 1, 'active', '2026-04-17 18:24:57', '2026-04-17 18:24:57');

-- --------------------------------------------------------

--
-- Table structure for table `days_of_week`
--

CREATE TABLE `days_of_week` (
  `id` int(11) NOT NULL,
  `day_name` varchar(20) NOT NULL,
  `day_order` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `days_of_week`
--

INSERT INTO `days_of_week` (`id`, `day_name`, `day_order`) VALUES
(1, 'Monday', 1),
(2, 'Tuesday', 2),
(3, 'Wednesday', 3),
(4, 'Thursday', 4),
(5, 'Friday', 5),
(6, 'Saturday', 6);

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `grade_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `school_year` varchar(20) NOT NULL,
  `status` enum('Pending','Enrolled','Rejected') DEFAULT 'Pending',
  `strand` varchar(50) DEFAULT NULL,
  `form_138` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `good_moral` varchar(255) DEFAULT NULL,
  `psa_birth` varchar(255) DEFAULT NULL,
  `student_type` enum('new','continuing','transferee') DEFAULT 'new',
  `form_137` varchar(255) DEFAULT NULL,
  `psa_birth_cert` varchar(255) DEFAULT NULL,
  `good_moral_cert` varchar(255) DEFAULT NULL,
  `certificate_of_completion` varchar(255) DEFAULT NULL,
  `id_pictures` varchar(255) DEFAULT NULL,
  `medical_cert` varchar(255) DEFAULT NULL,
  `entrance_exam_result` varchar(255) DEFAULT NULL,
  `other_documents` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `grade_id`, `section_id`, `school_year`, `status`, `strand`, `form_138`, `created_at`, `good_moral`, `psa_birth`, `student_type`, `form_137`, `psa_birth_cert`, `good_moral_cert`, `certificate_of_completion`, `id_pictures`, `medical_cert`, `entrance_exam_result`, `other_documents`) VALUES
(8, 16, 1, 4, '2026-2027', 'Enrolled', NULL, NULL, '2026-04-09 14:28:34', NULL, NULL, 'new', 'uploads/enrollment_docs/16_form_137_1775744914.jpg', 'uploads/enrollment_docs/16_psa_birth_cert_1775744914.jpg', 'uploads/enrollment_docs/16_good_moral_cert_1775744914.jpg', 'uploads/enrollment_docs/16_certificate_of_completion_1775744914.jpg', 'uploads/enrollment_docs/16_id_pictures_1775744914.jpg', NULL, NULL, NULL),
(9, 17, 2, NULL, '2026-2027', 'Enrolled', NULL, 'uploads/enrollment_docs/17_form_138_1776633662.jpg', '2026-04-19 21:21:02', NULL, NULL, 'continuing', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_requirements`
--

CREATE TABLE `enrollment_requirements` (
  `id` int(11) NOT NULL,
  `grade_level` varchar(20) NOT NULL,
  `student_type` enum('new','continuing','transferee') NOT NULL,
  `requirement_name` varchar(100) NOT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `can_be_followed` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollment_requirements`
--

INSERT INTO `enrollment_requirements` (`id`, `grade_level`, `student_type`, `requirement_name`, `is_required`, `can_be_followed`, `display_order`) VALUES
(1, 'Grade 7', 'new', 'Form 138 (Grade 6 Report Card)', 1, 0, 1),
(2, 'Grade 7', 'new', 'Certificate of Completion (Elementary)', 1, 0, 2),
(3, 'Grade 7', 'new', 'PSA Birth Certificate', 1, 0, 3),
(4, 'Grade 7', 'new', '2x2 ID Pictures', 1, 0, 4),
(5, 'Grade 7', 'new', 'Good Moral Certificate', 1, 0, 5),
(6, 'Grade 7', 'new', 'Medical/Dental Certificate', 0, 1, 6),
(7, 'Grade 7', 'transferee', 'Form 138 (Grade 6 Report Card)', 1, 0, 1),
(8, 'Grade 7', 'transferee', 'Certificate of Completion (Elementary)', 1, 0, 2),
(9, 'Grade 7', 'transferee', 'PSA Birth Certificate', 1, 0, 3),
(10, 'Grade 7', 'transferee', '2x2 ID Pictures', 1, 0, 4),
(11, 'Grade 7', 'transferee', 'Good Moral Certificate', 1, 0, 5),
(12, 'Grade 7', 'transferee', 'Medical/Dental Certificate', 0, 1, 6),
(13, 'Grade 7', 'transferee', 'Entrance Exam Result', 0, 1, 7),
(14, 'Grade 8', 'continuing', 'Form 138 (Previous Grade Report Card)', 1, 0, 1),
(15, 'Grade 9', 'continuing', 'Form 138 (Previous Grade Report Card)', 1, 0, 1),
(16, 'Grade 10', 'continuing', 'Form 138 (Previous Grade Report Card)', 1, 0, 1),
(17, 'Grade 8', 'transferee', 'Form 138 (Latest Report Card)', 1, 0, 1),
(18, 'Grade 8', 'transferee', 'Form 137 (Permanent Record - to follow)', 1, 1, 2),
(19, 'Grade 8', 'transferee', 'PSA Birth Certificate', 1, 0, 3),
(20, 'Grade 8', 'transferee', 'Good Moral Certificate', 1, 0, 4),
(21, 'Grade 8', 'transferee', '2x2 ID Pictures', 1, 0, 5),
(22, 'Grade 8', 'transferee', 'Entrance Exam / Interview Result', 0, 1, 6),
(23, 'Grade 9', 'transferee', 'Form 138 (Latest Report Card)', 1, 0, 1),
(24, 'Grade 9', 'transferee', 'Form 137 (Permanent Record - to follow)', 1, 1, 2),
(25, 'Grade 9', 'transferee', 'PSA Birth Certificate', 1, 0, 3),
(26, 'Grade 9', 'transferee', 'Good Moral Certificate', 1, 0, 4),
(27, 'Grade 9', 'transferee', '2x2 ID Pictures', 1, 0, 5),
(28, 'Grade 9', 'transferee', 'Entrance Exam / Interview Result', 0, 1, 6),
(29, 'Grade 10', 'transferee', 'Form 138 (Latest Report Card)', 1, 0, 1),
(30, 'Grade 10', 'transferee', 'Form 137 (Permanent Record - to follow)', 1, 1, 2),
(31, 'Grade 10', 'transferee', 'PSA Birth Certificate', 1, 0, 3),
(32, 'Grade 10', 'transferee', 'Good Moral Certificate', 1, 0, 4),
(33, 'Grade 10', 'transferee', '2x2 ID Pictures', 1, 0, 5),
(34, 'Grade 10', 'transferee', 'Entrance Exam / Interview Result', 0, 1, 6),
(35, 'Grade 11', 'new', 'Form 138 (Grade 10 Report Card)', 1, 0, 1),
(36, 'Grade 11', 'new', 'Certificate of Completion (Junior High)', 1, 0, 2),
(37, 'Grade 11', 'new', 'PSA Birth Certificate', 1, 0, 3),
(38, 'Grade 11', 'new', 'Good Moral Certificate', 1, 0, 4),
(39, 'Grade 11', 'new', 'SHS Enrollment Form (Track/Strand Selection)', 1, 0, 5),
(40, 'Grade 11', 'transferee', 'Form 138 (Grade 10 Report Card)', 1, 0, 1),
(41, 'Grade 11', 'transferee', 'Form 137 (Permanent Record - to follow)', 1, 1, 2),
(42, 'Grade 11', 'transferee', 'PSA Birth Certificate', 1, 0, 3),
(43, 'Grade 11', 'transferee', 'Good Moral Certificate', 1, 0, 4),
(44, 'Grade 11', 'transferee', 'SHS Enrollment Form (Track/Strand Selection)', 1, 0, 5),
(45, 'Grade 11', 'transferee', 'Entrance Exam / Screening Result', 0, 1, 6),
(46, 'Grade 12', 'continuing', 'Form 138 (Grade 11 Report Card)', 1, 0, 1),
(47, 'Grade 12', 'transferee', 'Form 138 (Grade 11 Report Card)', 1, 0, 1),
(48, 'Grade 12', 'transferee', 'Form 137 (Permanent Record)', 1, 0, 2),
(49, 'Grade 12', 'transferee', 'PSA Birth Certificate', 1, 0, 3),
(50, 'Grade 12', 'transferee', 'Good Moral Certificate', 1, 0, 4),
(51, 'Grade 12', 'transferee', '2x2 ID Pictures', 1, 0, 5);

-- --------------------------------------------------------

--
-- Table structure for table `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `quarter` int(11) NOT NULL,
  `grade` decimal(5,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `grade_levels`
--

CREATE TABLE `grade_levels` (
  `id` int(11) NOT NULL,
  `grade_name` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grade_levels`
--

INSERT INTO `grade_levels` (`id`, `grade_name`) VALUES
(1, 'Grade 7'),
(2, 'Grade 8'),
(3, 'Grade 9'),
(4, 'Grade 10'),
(5, 'Grade 11'),
(6, 'Grade 12');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('update','action','reminder','alert','message') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 7, 'update', '📢 New School Year 2026-2027', 'The enrollment period for the new school year is now open! Please review the updated requirements.', 'enrollments.php', 1, '2026-04-19 19:38:22'),
(2, 7, 'update', '📅 Class Schedules Posted', 'New class schedules for Grade 7-10 have been posted. Teachers can now view their assignments.', 'sections.php', 1, '2026-04-19 19:38:22'),
(3, 7, 'update', '🏫 System Maintenance Complete', 'The system has been updated with new features including real-time notifications and improved reporting.', NULL, 1, '2026-04-19 19:38:22'),
(4, 7, 'action', '✅ Enrollment Approved', 'Student John Michael Santos has been successfully enrolled in Grade 7 - Narra section.', 'students.php', 1, '2026-04-19 19:38:22'),
(5, 7, 'action', '📋 Teacher Assignment Updated', 'Ms. Maria Reyes has been assigned as adviser for Grade 10 - Mahogany section.', 'teachers.php', 1, '2026-04-19 19:38:22'),
(6, 7, 'reminder', '⏰ Enrollment Deadline Approaching', 'The enrollment deadline is in 5 days. Please process all pending applications.', 'enrollments.php', 1, '2026-04-19 19:38:22'),
(7, 7, 'reminder', '📝 Incomplete Requirements', '3 students have incomplete enrollment requirements. Please follow up.', 'enrollments.php', 1, '2026-04-19 19:38:22'),
(8, 7, 'reminder', '💰 Payment Reminder', 'First quarter tuition fees are due by end of this month.', NULL, 1, '2026-04-19 19:38:22'),
(9, 7, 'alert', '⚠️ Schedule Conflict Detected', 'Teacher Maria Reyes has a schedule conflict on Monday mornings. Please review the class schedule.', 'schedules.php', 1, '2026-04-19 19:38:22'),
(10, 7, 'alert', '📄 Missing Documents Alert', 'Student records missing: 5 students need to submit their PSA birth certificates.', 'students.php', 1, '2026-04-19 19:38:22'),
(11, 7, 'alert', '👥 Section Capacity Alert', 'Grade 7 - Narra section has reached maximum capacity (45/45 students).', 'sections.php', 1, '2026-04-19 19:38:22'),
(12, 7, 'message', '💬 New Message from Registrar', 'Please review the enrollment documents for new students submitted today.', 'enrollments.php', 1, '2026-04-19 19:38:22'),
(13, 7, 'message', '📨 Admin Response Received', 'Your request for additional teacher slots has been approved.', 'manage_accounts.php', 1, '2026-04-19 19:38:22'),
(14, 16, 'update', '📢 New School Year 2026-2027', 'The enrollment period is now open! Please complete your enrollment requirements.', 'enrollment.php', 1, '2026-04-19 20:03:03'),
(15, 16, 'update', '📅 Class Schedules Available', 'Your class schedules for this semester are now available to view.', 'schedule.php', 1, '2026-04-19 20:03:03'),
(16, 16, 'action', '✅ Enrollment Successful', 'Your enrollment for Grade 7 has been approved. Welcome to PLSNHS!', 'dashboard.php', 1, '2026-04-19 20:03:03'),
(17, 16, 'action', '📋 Profile Updated', 'Your profile information has been successfully updated.', 'profile.php', 1, '2026-04-19 20:03:03'),
(18, 16, 'reminder', '⏰ Enrollment Deadline', 'The enrollment deadline is approaching in 5 days. Complete your requirements.', 'enrollment.php', 1, '2026-04-19 20:03:03'),
(19, 16, 'reminder', '📝 Incomplete Requirements', 'Please submit your remaining requirements: PSA Birth Certificate.', 'requirements.php', 1, '2026-04-19 20:03:03'),
(20, 16, 'reminder', '💰 Payment Reminder', 'First quarter tuition fee is due by end of this month.', 'payments.php', 1, '2026-04-19 20:03:03'),
(21, 16, 'alert', '⚠️ Schedule Change', 'Your Math class schedule has been moved to Room 301.', 'schedule.php', 1, '2026-04-19 20:03:03'),
(22, 16, 'alert', '📄 Missing Document', 'Your Good Moral certificate is still pending. Please submit ASAP.', 'requirements.php', 1, '2026-04-19 20:03:03'),
(23, 16, 'message', '💬 Message from Registrar', 'Your enrollment documents have been received and are being processed.', 'enrollment.php', 1, '2026-04-19 20:03:03'),
(24, 16, 'message', '📨 Announcement from Principal', 'School will be closed on Friday for faculty development.', 'dashboard.php', 1, '2026-04-19 20:03:03'),
(25, 5, 'update', '📢 New Enrollment Period Open', 'The enrollment period for SY 2026-2027 is now open. Please review pending applications.', 'enrollments.php', 1, '2026-04-19 21:18:03'),
(26, 5, 'update', '📅 Deadline Extended', 'Enrollment deadline has been extended to June 15, 2026.', 'enrollments.php', 1, '2026-04-19 21:18:03'),
(27, 5, 'action', '✅ Enrollment Approved', 'Student John Santos has been successfully enrolled in Grade 7.', 'enrollments.php', 1, '2026-04-19 21:18:03'),
(28, 5, 'action', '📋 New Section Created', 'Section \"Mahogany\" for Grade 10 has been created.', 'sections.php', 1, '2026-04-19 21:18:03'),
(29, 5, 'reminder', '⏰ Pending Applications', 'You have 5 pending enrollment applications waiting for review.', 'enrollments.php?status=Pending', 1, '2026-04-19 21:18:03'),
(30, 5, 'reminder', '📝 Incomplete Records', '3 students have incomplete requirements. Please follow up.', 'students.php', 1, '2026-04-19 21:18:03'),
(31, 5, 'alert', '⚠️ Section Capacity Alert', 'Grade 7 - Narra section is almost full (42/45 students).', 'sections.php', 1, '2026-04-19 21:18:03'),
(32, 5, 'alert', '📄 Missing Documents', 'Several enrollment applications are missing PSA birth certificates.', 'enrollments.php', 1, '2026-04-19 21:18:03'),
(33, 5, 'message', '💬 Message from Admin', 'Please prepare the enrollment summary report for this week.', 'reports.php', 1, '2026-04-19 21:18:03'),
(34, 5, 'message', '📨 System Update', 'The enrollment system has been updated with new features.', 'dashboard.php', 1, '2026-04-19 21:18:03'),
(35, 17, 'update', '📢 New School Year 2026-2027', 'The enrollment period is now open! Please complete your enrollment requirements.', 'enrollment.php', 1, '2026-04-19 21:20:33'),
(36, 17, 'update', '📅 Class Schedules Available', 'Your class schedules for this semester are now available to view.', 'schedule.php', 1, '2026-04-19 21:20:33'),
(37, 17, 'action', '✅ Enrollment Successful', 'Your enrollment for Grade 7 has been approved. Welcome to PLSNHS!', 'dashboard.php', 1, '2026-04-19 21:20:33'),
(38, 17, 'action', '📋 Profile Updated', 'Your profile information has been successfully updated.', 'profile.php', 1, '2026-04-19 21:20:33'),
(39, 17, 'reminder', '⏰ Enrollment Deadline', 'The enrollment deadline is approaching in 5 days. Complete your requirements.', 'enrollment.php', 1, '2026-04-19 21:20:33'),
(40, 17, 'reminder', '📝 Incomplete Requirements', 'Please submit your remaining requirements: PSA Birth Certificate.', 'requirements.php', 1, '2026-04-19 21:20:33'),
(41, 17, 'reminder', '💰 Payment Reminder', 'First quarter tuition fee is due by end of this month.', 'payments.php', 1, '2026-04-19 21:20:33'),
(42, 17, 'alert', '⚠️ Schedule Change', 'Your Math class schedule has been moved to Room 301.', 'schedule.php', 1, '2026-04-19 21:20:33'),
(43, 17, 'alert', '📄 Missing Document', 'Your Good Moral certificate is still pending. Please submit ASAP.', 'requirements.php', 1, '2026-04-19 21:20:33'),
(44, 17, 'message', '💬 Message from Registrar', 'Your enrollment documents have been received and are being processed.', 'enrollment.php', 1, '2026-04-19 21:20:33'),
(45, 17, 'message', '📨 Announcement from Principal', 'School will be closed on Friday for faculty development.', 'dashboard.php', 1, '2026-04-19 21:20:33');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `section_name` varchar(50) DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `grade_id`, `adviser_id`) VALUES
(4, 'Narra', 1, 9);

-- --------------------------------------------------------

--
-- Table structure for table `strands`
--

CREATE TABLE `strands` (
  `id` int(11) NOT NULL,
  `strand_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `grade_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_name`, `grade_id`) VALUES
(1, 'Mathematics', 1),
(2, 'Filipino', 1),
(3, 'English', 1),
(4, 'Araling Panlipunan', 1),
(5, 'Edukasyon sa Pagpapakatao', 1),
(6, 'MAPEH', 1),
(7, 'Science', 1),
(8, 'Mathematics', 2),
(9, 'Science', 2),
(10, 'English', 2),
(11, 'Filipino', 2),
(12, 'Araling Panlipunan', 2),
(13, 'MAPEH', 2),
(14, 'Edukasyon sa Pagpapakatao', 2),
(15, 'Technology and Livelihood Education', 2),
(16, 'Mathematics', 4),
(17, 'Science', 4),
(18, 'Filipino', 4);

-- --------------------------------------------------------

--
-- Table structure for table `teacher_attendance`
--

CREATE TABLE `teacher_attendance` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('Present','Absent','Late','Pending') NOT NULL DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `qr_token` varchar(100) DEFAULT NULL,
  `session_status` enum('active','used','expired','completed') DEFAULT 'active',
  `expires_at` datetime DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_attendance`
--

INSERT INTO `teacher_attendance` (`id`, `teacher_id`, `date`, `time_in`, `time_out`, `status`, `remarks`, `created_at`, `qr_token`, `session_status`, `expires_at`, `updated_at`) VALUES
(40, 9, '2026-04-05', '03:55:28', '03:55:32', 'Present', NULL, '2026-04-04 19:55:22', '64625f62d022e74742b6fcee89cca2d9', 'completed', '2026-04-05 04:55:30', '2026-04-04 19:55:32'),
(41, 8, '2026-04-05', '14:22:26', '14:22:34', 'Late', NULL, '2026-04-05 06:22:20', 'cc2ad111c6118be592a6f3ad0e1a6cf7', 'completed', '2026-04-05 15:22:28', '2026-04-05 06:22:34'),
(42, 9, '2026-04-06', '22:53:26', '22:53:32', 'Late', NULL, '2026-04-06 14:53:23', 'ecb8da68520e368a17f0d30e26815c1c', 'completed', '2026-04-06 23:53:28', '2026-04-06 14:53:32'),
(43, 8, '2026-04-06', '23:37:20', '23:39:07', 'Late', NULL, '2026-04-06 15:37:15', 'a6ad373fd57d958a4614b3a1e20d0d85', 'completed', '2026-04-07 00:37:22', '2026-04-06 15:39:07');

-- --------------------------------------------------------

--
-- Table structure for table `time_slots`
--

CREATE TABLE `time_slots` (
  `id` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_slots`
--

INSERT INTO `time_slots` (`id`, `start_time`, `end_time`, `slot_name`) VALUES
(1, '07:30:00', '08:30:00', '1st Period'),
(2, '08:30:00', '09:30:00', '2nd Period'),
(3, '09:30:00', '10:00:00', 'Morning Break'),
(4, '10:00:00', '11:00:00', '3rd Period'),
(5, '11:00:00', '12:00:00', '4th Period'),
(6, '12:00:00', '13:00:00', 'Lunch Break'),
(7, '13:00:00', '14:00:00', '5th Period'),
(8, '14:00:00', '15:00:00', '6th Period'),
(9, '15:00:00', '15:30:00', 'Afternoon Break'),
(10, '15:30:00', '16:30:00', '7th Period'),
(11, '16:30:00', '17:30:00', '8th Period');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) DEFAULT NULL,
  `firstname` varchar(100) DEFAULT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('Admin','Registrar','Teacher','Student') DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_last_used` timestamp NULL DEFAULT NULL,
  `two_factor_verified` tinyint(1) NOT NULL DEFAULT 0,
  `last_password_change` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `id_number`, `firstname`, `middlename`, `lastname`, `birthdate`, `gender`, `fullname`, `email`, `password`, `role`, `status`, `two_factor_enabled`, `two_factor_last_used`, `two_factor_verified`, `last_password_change`, `approved_by`, `approved_at`, `rejection_reason`, `created_at`, `profile_picture`) VALUES
(5, NULL, NULL, NULL, NULL, NULL, NULL, 'Registrar', 'registrar@gmail.com', '$2y$10$X08Ul9MuPNNcoeXDwMofyOzr0XUA0X3z/ZDIu7VJewO6xS7tU6SkG', 'Registrar', 'approved', 0, NULL, 0, NULL, 7, '2026-03-03 14:35:04', NULL, '2026-02-23 18:15:15', NULL),
(7, NULL, NULL, NULL, NULL, NULL, NULL, 'AdminKo', 'johnmicoleko@gmail.com', '$2y$10$rO3lV/AIgUoGfVLIndKfZeqGZOhHrtpok.5VbifnI/7Eo2NC./P/S', 'Admin', 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, '2026-02-25 17:31:12', 'uploads/profile_pictures/admin_7_1776448674.jpg'),
(8, NULL, NULL, NULL, NULL, NULL, NULL, 'Twengcle Deguma', 'deguma@gmail.com', '$2y$10$QNz6RxhiCB0d/BgNwi7uhOEbxZNBgo3VwLwmiZuDp0r3zGcjagqlq', 'Teacher', 'approved', 0, NULL, 0, NULL, 7, '2026-03-03 14:35:08', NULL, '2026-02-25 17:43:14', NULL),
(9, NULL, NULL, NULL, NULL, NULL, NULL, 'Ashly Balbon', 'ash@gmail.com', '$2y$10$mlaBuK5GfSISJMKwXnuqO.rjroCewFjw.KXTlJoR0afmdvHhaVt.i', 'Teacher', 'approved', 0, NULL, 0, NULL, 7, '2026-03-03 14:34:56', NULL, '2026-02-25 20:05:28', 'uploads/profile_pictures/teacher_9_1776448721.jpg'),
(16, 'PLSSHS-2026-7-0001', 'John Micole', 'S.', 'Mangila', '2002-09-30', 'Male', 'John Micole S. Mangila', 'johnmicoleiii@gmail.com', '$2y$10$.40o6tCSA53j3A6/zoPg1.mM44fQU4s0UIpH7EoLhuwehXvAKBoCK', 'Student', 'approved', 0, NULL, 0, NULL, 7, '2026-04-09 13:24:05', NULL, '2026-04-09 13:20:56', 'uploads/profile_pictures/student_16_1776448793.jpg'),
(17, 'PLSNHS-2026-0000001', 'Kian', 'B', 'Victorillo', '2002-04-17', 'Male', 'Kian B Victorillo', 'kian@gmail.com', '$2y$10$9Wy0teVo.siMVHbGNT068ONeu/5QnfxxwuASenSJ/X5zYvabGX.Ja', 'Student', 'approved', 0, NULL, 0, NULL, 7, '2026-04-19 21:20:23', NULL, '2026-04-19 21:19:26', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `chatbot_knowledge`
--
ALTER TABLE `chatbot_knowledge`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `day_id` (`day_id`),
  ADD KEY `time_slot_id` (`time_slot_id`);

--
-- Indexes for table `days_of_week`
--
ALTER TABLE `days_of_week`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `grade_id` (`grade_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `enrollment_requirements`
--
ALTER TABLE `enrollment_requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `grade_levels`
--
ALTER TABLE `grade_levels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grade_id` (`grade_id`),
  ADD KEY `adviser_id` (`adviser_id`);

--
-- Indexes for table `strands`
--
ALTER TABLE `strands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `grade_id` (`grade_id`);

--
-- Indexes for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_role_status` (`role`,`status`),
  ADD KEY `fk_approved_by` (`approved_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `chatbot_knowledge`
--
ALTER TABLE `chatbot_knowledge`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_schedules`
--
ALTER TABLE `class_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `days_of_week`
--
ALTER TABLE `days_of_week`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `enrollment_requirements`
--
ALTER TABLE `enrollment_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `grade_levels`
--
ALTER TABLE `grade_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `strands`
--
ALTER TABLE `strands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `time_slots`
--
ALTER TABLE `time_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD CONSTRAINT `class_schedules_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_schedules_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_schedules_ibfk_4` FOREIGN KEY (`day_id`) REFERENCES `days_of_week` (`id`),
  ADD CONSTRAINT `class_schedules_ibfk_5` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`);

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`grade_id`) REFERENCES `grade_levels` (`id`),
  ADD CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grades_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`grade_id`) REFERENCES `grade_levels` (`id`),
  ADD CONSTRAINT `sections_ibfk_2` FOREIGN KEY (`adviser_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`grade_id`) REFERENCES `grade_levels` (`id`);

--
-- Constraints for table `teacher_attendance`
--
ALTER TABLE `teacher_attendance`
  ADD CONSTRAINT `teacher_attendance_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
