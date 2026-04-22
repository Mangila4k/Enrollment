-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 23, 2026 at 12:30 AM
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
(18, 6, 12, 39, 1, 1, 'RM 301', '2026-2027', 1, 'active', '2026-04-22 19:56:30', '2026-04-22 19:56:30'),
(20, 6, 12, 39, 2, 1, 'RM 301', '2026-2027', 1, 'active', '2026-04-22 22:11:55', '2026-04-22 22:11:55');

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
(22, 38, 2, 6, '2026-2027', 'Enrolled', NULL, 'uploads/enrollment_docs/38_form_138_1776888818.jpg', '2026-04-22 20:13:38', NULL, NULL, 'transferee', 'uploads/enrollment_docs/38_form_137_1776888818.jpg', 'uploads/enrollment_docs/38_psa_birth_cert_1776888818.jpg', 'uploads/enrollment_docs/38_good_moral_cert_1776888818.jpg', NULL, 'uploads/enrollment_docs/38_id_pictures_1776888818.jpg', NULL, NULL, NULL);

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
(7, 'Grade 7', 'transferee', 'Form 138 (Grade 6 Report Card)', 1, 0, 1),
(8, 'Grade 7', 'transferee', 'Certificate of Completion (Elementary)', 1, 0, 2),
(9, 'Grade 7', 'transferee', 'PSA Birth Certificate', 1, 0, 3),
(10, 'Grade 7', 'transferee', '2x2 ID Pictures', 1, 0, 4),
(11, 'Grade 7', 'transferee', 'Good Moral Certificate', 1, 0, 5),
(14, 'Grade 8', 'continuing', 'Form 138 (Previous Grade Report Card)', 1, 0, 1),
(15, 'Grade 9', 'continuing', 'Form 138 (Previous Grade Report Card)', 1, 0, 1),
(16, 'Grade 10', 'continuing', 'Form 138 (Previous Grade Report Card)', 1, 0, 1),
(17, 'Grade 8', 'transferee', 'Form 138 (Latest Report Card)', 1, 0, 1),
(18, 'Grade 8', 'transferee', 'Form 137 (Permanent Record - to follow)', 1, 1, 2),
(19, 'Grade 8', 'transferee', 'PSA Birth Certificate', 1, 0, 3),
(20, 'Grade 8', 'transferee', 'Good Moral Certificate', 1, 0, 4),
(21, 'Grade 8', 'transferee', '2x2 ID Pictures', 1, 0, 5),
(23, 'Grade 9', 'transferee', 'Form 138 (Latest Report Card)', 1, 0, 1),
(24, 'Grade 9', 'transferee', 'Form 137 (Permanent Record - to follow)', 1, 1, 2),
(25, 'Grade 9', 'transferee', 'PSA Birth Certificate', 1, 0, 3),
(26, 'Grade 9', 'transferee', 'Good Moral Certificate', 1, 0, 4),
(27, 'Grade 9', 'transferee', '2x2 ID Pictures', 1, 0, 5),
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
(119, 32, 'alert', '⚠️ Missing Requirement: Form 138 (Report Card)', 'The school administration has notified you about the missing requirement: Form 138 (Report Card). Please submit this requirement as soon as possible to complete your enrollment process.', 'requirements.php', 1, '2026-04-21 12:27:31'),
(120, 32, 'alert', '⚠️ Missing Requirement: Form 138 (Report Card)', 'The school administration has notified you about the missing requirement: Form 138 (Report Card). Please submit this requirement as soon as possible to complete your enrollment process.', 'requirements.php', 1, '2026-04-21 12:27:36'),
(121, 32, 'alert', '⚠️ Missing Requirement: Good Moral Certificate', 'The school administration has notified you about the missing requirement: Good Moral Certificate. Please submit this requirement as soon as possible to complete your enrollment process.', 'requirements.php', 1, '2026-04-21 12:27:41'),
(122, 32, 'alert', '⚠️ Missing Requirement: Good Moral Certificate', 'The school administration has notified you about the missing requirement: Good Moral Certificate. Please submit this requirement as soon as possible to complete your enrollment process.', 'requirements.php', 1, '2026-04-21 12:27:45'),
(123, 32, 'alert', '⚠️ Missing Requirement: Medical Certificate', 'The school administration has notified you about the missing requirement: Medical Certificate. Please submit this requirement as soon as possible to complete your enrollment process.', 'requirements.php', 1, '2026-04-21 12:27:50'),
(124, 32, 'alert', '⚠️ Missing Requirement: Medical Certificate', 'The school administration has notified you about the missing requirement: Medical Certificate. Please submit this requirement as soon as possible to complete your enrollment process.', 'requirements.php', 1, '2026-04-21 12:27:53'),
(158, 33, 'alert', '⚠️ Schedule Change', 'Your Math class schedule has been moved to Room 301.', 'schedule.php', 1, '2026-04-21 16:15:44'),
(159, 33, 'alert', '📄 Missing Document', 'Your Good Moral certificate is still pending. Please submit ASAP.', 'requirements.php', 1, '2026-04-21 16:15:44'),
(162, 33, 'alert', '⚠️ Missing Requirement: Entrance Exam / Interview Result', 'The school administration has notified you about the missing requirement: Entrance Exam / Interview Result. Please submit this requirement as soon as possible to complete your enrollment process.', 'requirements.php', 1, '2026-04-21 16:30:14'),
(163, 33, 'alert', '⚠️ Missing Requirement: Entrance Exam / Interview Result', 'The school administration has notified you about the missing requirement: Entrance Exam / Interview Result. Please submit this requirement as soon as possible to complete your enrollment process.', 'requirements.php', 1, '2026-04-21 16:30:18'),
(164, 33, 'alert', '❌ Enrollment Rejected - PLS NHS', 'Dear Justine S Mangila,\n\nWe regret to inform you that your enrollment application has been REJECTED.\n\nReason: Invalid Requirments\n\nIf you have any questions, please contact the registrar\'s office for assistance.\n\nThank you.', 'enrollments.php', 1, '2026-04-21 16:34:10'),
(200, 7, 'update', '🖼️ Profile Picture Updated', 'Student John Micole S. Mangila has updated their profile picture.', '../admin/students.php?view=21', 1, '2026-04-22 18:27:11'),
(201, 30, 'update', '🖼️ Profile Picture Updated', 'Student John Micole S. Mangila has updated their profile picture.', '../admin/students.php?view=21', 0, '2026-04-22 18:27:11'),
(202, 21, 'alert', '❌ Enrollment Rejected - PLS NHS', 'Dear John Micole S. Mangila,\n\nWe regret to inform you that your enrollment application has been REJECTED.\n\nReason: not valid file\n\nIf you have any questions, please contact the registrar\'s office for assistance.\n\nThank you.', 'enrollments.php', 0, '2026-04-22 18:32:13'),
(203, 30, 'alert', '🔐 Admin Password Changed', 'Admin AdminKo has changed their account password.', 'profile.php', 0, '2026-04-22 18:33:49'),
(204, 21, 'update', '✅ Enrollment Approved', 'Dear John Micole S. Mangila,\n\nCongratulations! Your enrollment for Grade 8 for School Year 2026-2027 has been APPROVED.\n\nWelcome to PLS NHS!', 'dashboard.php', 0, '2026-04-22 18:52:26'),
(205, 30, 'action', '👨‍🏫 New Teacher Added', 'A new teacher account has been created: Jaype Delsocoro (ID: PLSNHS-TCH-000001)', 'teachers.php', 0, '2026-04-22 18:57:59'),
(206, 30, 'alert', '🗑️ User Account Deleted', 'User Juana Mae S Mangila (Student) has been deleted from the system.', 'manage_accounts.php', 0, '2026-04-22 19:16:44'),
(207, 30, 'alert', '🗑️ User Account Deleted', 'User Justine S Mangila (Student) has been deleted from the system.', 'manage_accounts.php', 0, '2026-04-22 19:16:49'),
(208, 30, 'alert', '🗑️ User Account Deleted', 'User Khen B Dela Calzada (Student) has been deleted from the system.', 'manage_accounts.php', 0, '2026-04-22 19:16:51'),
(209, 30, 'alert', '🗑️ User Account Deleted', 'User John Micole S. Mangila (Student) has been deleted from the system.', 'manage_accounts.php', 0, '2026-04-22 19:16:58'),
(210, 30, 'update', '📝 Admin Profile Updated', 'Admin AdminKo has updated their profile name to Admin', 'profile.php', 0, '2026-04-22 19:17:20'),
(211, 36, 'action', '👤 New Registrar Account Created', 'A new Registrar account has been created: Angielica Sasuman (ID: PLSNHS-RGR-00001)', 'manage_accounts.php', 1, '2026-04-22 19:38:31'),
(212, 36, 'action', '👨‍🎓 New Student Added', 'A new student account has been created: Kian B. Victorillo (ID: PLSNHS-STD-2026-000001)', 'students.php', 1, '2026-04-22 19:50:02'),
(213, 36, 'action', '✅ User Account Approved', 'User Kian B Victorillo (Student) has been approved.', 'manage_accounts.php', 1, '2026-04-22 19:54:04'),
(214, 36, 'action', '👨‍🏫 New Teacher Added', 'A new teacher account has been created: Ashly Balbon (ID: PLSNHS-TCH-000001)', 'teachers.php', 1, '2026-04-22 19:55:59'),
(215, 38, 'update', '✅ Enrollment Approved', 'Dear Kian B Victorillo,\n\nCongratulations! Your enrollment for Grade 8 for School Year 2026-2027 has been APPROVED.\n\nWelcome to PLS NHS!', 'dashboard.php', 1, '2026-04-22 20:28:31'),
(216, 38, 'update', '🔄 Enrollment Status Updated to Pending', 'Dear Kian B Victorillo,\n\nYour enrollment application has been moved back to PENDING status. Please check your requirements and contact the registrar\'s office for more information.', 'enrollments.php', 1, '2026-04-22 20:46:48'),
(217, 38, 'update', '✅ Enrollment Approved - PLS NHS', 'Dear Kian B Victorillo,\n\nCongratulations! Your enrollment application has been APPROVED. Your Student ID is: PLSNHS-2026-0000001\n\nWelcome to PLS NHS!', 'dashboard.php', 1, '2026-04-22 20:46:51'),
(218, 7, 'update', '🖼️ Profile Picture Updated', 'Student Kian B Victorillo has updated their profile picture.', '../admin/students.php?view=38', 1, '2026-04-22 21:15:51'),
(219, 36, 'update', '🖼️ Profile Picture Updated', 'Student Kian B Victorillo has updated their profile picture.', '../admin/students.php?view=38', 1, '2026-04-22 21:15:51'),
(220, 7, 'update', '📝 Teacher Profile Updated', 'Teacher Ashly Balbon has updated their profile name to Ashly Esconde', '../admin/teachers.php?view=39', 1, '2026-04-22 21:52:00'),
(221, 36, 'update', '📝 Teacher Profile Updated', 'Teacher Ashly Balbon has updated their profile name to Ashly Esconde', '../admin/teachers.php?view=39', 0, '2026-04-22 21:52:00');

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
(6, 'Narra', 2, 39);

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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `session_type` enum('time_in','time_out') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subjects`
--

CREATE TABLE `teacher_subjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `section_id` int(11) DEFAULT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(10, '15:00:00', '16:00:00', '7th Period'),
(11, '16:00:00', '17:00:00', '8th Period');

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
  `phone` varchar(20) DEFAULT NULL,
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
  `profile_picture` varchar(255) DEFAULT NULL,
  `verification_code` varchar(10) DEFAULT NULL,
  `verification_expires` datetime DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `reset_code` varchar(10) DEFAULT NULL,
  `reset_code_expires` datetime DEFAULT NULL,
  `pending_email` varchar(255) DEFAULT NULL,
  `pending_email_code` varchar(10) DEFAULT NULL,
  `pending_email_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `id_number`, `firstname`, `middlename`, `lastname`, `birthdate`, `gender`, `fullname`, `email`, `phone`, `password`, `role`, `status`, `two_factor_enabled`, `two_factor_last_used`, `two_factor_verified`, `last_password_change`, `approved_by`, `approved_at`, `rejection_reason`, `created_at`, `profile_picture`, `verification_code`, `verification_expires`, `email_verified`, `reset_code`, `reset_code_expires`, `pending_email`, `pending_email_code`, `pending_email_expires`) VALUES
(7, NULL, NULL, NULL, NULL, NULL, NULL, 'Admin', 'johnmicoleko@gmail.com', '09752531214', '$2y$10$7R/zgvx07tXURyXfvJXVGOPjxmtQZn1ZAcWoxR7YjPDT0cyeUXJS2', 'Admin', 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, '2026-02-25 17:31:12', 'uploads/profile_pictures/admin_7_1776448674.jpg', NULL, NULL, 1, '388556', '2026-04-22 21:41:10', NULL, NULL, NULL),
(36, NULL, NULL, NULL, NULL, NULL, NULL, 'Angielica Sasuman', 'jhnmicole@gmail.com', NULL, '$2y$10$GMJDn3mMlz1vVh1.PLlNqumGTg1iyva/.KEdjG0fNSPv5xVwQ63UK', 'Registrar', 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, '2026-04-22 19:38:31', 'uploads/profile_pictures/registrar_36_1776894211.png', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL),
(38, 'PLSNHS-2026-0000001', 'Kian', 'B', 'Victorillo', '2000-03-09', 'Male', 'Kian B Victorillo', 'johnmicoleiii@gmail.com', NULL, '$2y$10$I9rDB4LxGOQOJASh456Pfubc0.N.8JY/QLLvv88vkhYENUeOfpTCm', 'Student', 'approved', 0, NULL, 0, NULL, 7, '2026-04-22 19:54:04', NULL, '2026-04-22 19:52:17', 'uploads/profile_pictures/student_38_1776892551.png', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(39, 'PLSNHS-TCH-000001', NULL, NULL, NULL, NULL, NULL, 'Ashly Esconde', 'okayjustbe8@gmail.com', '', '$2y$10$pThelB5Hm0mXZHDapT91/eXh9ZPsGAGXiG3M2nFf/5sr2RI.Lhk9e', 'Teacher', 'approved', 0, NULL, 0, NULL, NULL, NULL, NULL, '2026-04-22 19:55:59', 'uploads/profile_pictures/teacher_39_1776888065.png', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

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
-- Indexes for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `section_id` (`section_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `days_of_week`
--
ALTER TABLE `days_of_week`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `enrollment_requirements`
--
ALTER TABLE `enrollment_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `grade_levels`
--
ALTER TABLE `grade_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=222;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `time_slots`
--
ALTER TABLE `time_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- Constraints for dumped tables
--

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
