-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Sep 25, 2026 at 03:57 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `spms`
--

-- --------------------------------------------------------

--
-- Table structure for table `panel_evaluation`
--

CREATE TABLE `panel_evaluation` (
  `eval_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `examiner_id` int(11) DEFAULT NULL,
  `presentation_score` int(11) DEFAULT NULL,
  `documentation_score` int(11) DEFAULT NULL,
  `references_score` int(11) DEFAULT NULL,
  `logic_score` int(11) DEFAULT NULL,
  `code_understanding_score` int(11) DEFAULT NULL,
  `security_score` int(11) DEFAULT NULL,
  `reports_score` int(11) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `total_panel_mark` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `panel_evaluation`
--

INSERT INTO `panel_evaluation` (`eval_id`, `project_id`, `examiner_id`, `presentation_score`, `documentation_score`, `references_score`, `logic_score`, `code_understanding_score`, `security_score`, `reports_score`, `comments`, `total_panel_mark`) VALUES
(11, 5, 1020000, 10, 10, 5, 17, 17, 17, 10, 'perfect', 86),
(12, 29, 1020000, 10, 9, 5, 9, 22, 19, 10, 'perfect', 84),
(13, 33, 1020000, 14, 17, 9, 13, 11, 10, 9, 'well and good', 83),
(14, 33, 100, 15, 20, 10, 15, 9, 9, 7, 'excellent sir', 85),
(15, 5, 100, 15, 10, 10, 10, 11, 10, 3, 'wow', 69),
(16, 29, 100, 15, 20, 10, 15, 11, 6, 9, 'nice work', 86),
(17, 36, 1020000, 14, 17, 5, 12, 13, 5, 10, 'system ideal for gated communities', 76),
(18, 36, 100, 15, 20, 10, 15, 20, 10, 10, 'perfect system', 100),
(19, 35, 1020000, 10, 10, 8, 10, 17, 10, 10, 'perfect', 75),
(20, 35, 100, 10, 15, 10, 15, 11, 10, 8, 'wow', 79),
(21, 37, 1020000, 13, 18, 8, 12, 15, 6, 10, 'excellent system', 82),
(22, 37, 100, 14, 13, 8, 13, 12, 8, 9, 'well done', 77),
(23, 35, 1030009, 15, 16, 10, 14, 15, 9, 10, 'perfect', 89),
(24, 33, 1030009, 10, 16, 10, 11, 10, 10, 9, 'perfect', 76),
(25, 37, 1030009, 9, 9, 9, 11, 13, 10, 10, 'wow i like it', 71);

-- --------------------------------------------------------

--
-- Table structure for table `project`
--

CREATE TABLE `project` (
  `project_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `title_1` varchar(255) DEFAULT NULL,
  `title_2` varchar(255) DEFAULT NULL,
  `title_3` varchar(255) DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `project_title` varchar(255) DEFAULT NULL,
  `m1_status` enum('Pending','Titles Submitted','Approved','Rejected') DEFAULT 'Pending',
  `supervisor_comment` text DEFAULT NULL,
  `m2_status` enum('Pending','Pending Review','Approved','Rejected') DEFAULT 'Pending',
  `m2_comment` text DEFAULT NULL,
  `m3_status` enum('Pending','Pending Review','Approved','Rejected') DEFAULT 'Pending',
  `m3_comment` text DEFAULT NULL,
  `m4_status` enum('Pending','Pending Review','Approved','Rejected') DEFAULT 'Pending',
  `m4_comment` text DEFAULT NULL,
  `m5_status` enum('Pending','Pending Review','Approved','Rejected') DEFAULT 'Pending',
  `m5_comment` text DEFAULT NULL,
  `m6_status` enum('Pending','Pending Review','Approved','Rejected') DEFAULT 'Pending',
  `m6_comment` text DEFAULT NULL,
  `m7_status` enum('Pending','Pending Review','Approved','Rejected','Scheduled','Completed') DEFAULT 'Pending',
  `m7_comment` text DEFAULT NULL,
  `final_presentation` enum('No','Yes') DEFAULT 'No',
  `final_grade` int(11) DEFAULT NULL,
  `supervisor_grade` int(11) DEFAULT 0,
  `clearance_status` enum('Not Cleared','Cleared') DEFAULT 'Not Cleared',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project`
--

INSERT INTO `project` (`project_id`, `student_id`, `title_1`, `title_2`, `title_3`, `supervisor_id`, `project_title`, `m1_status`, `supervisor_comment`, `m2_status`, `m2_comment`, `m3_status`, `m3_comment`, `m4_status`, `m4_comment`, `m5_status`, `m5_comment`, `m6_status`, `m6_comment`, `m7_status`, `m7_comment`, `final_presentation`, `final_grade`, `supervisor_grade`, `clearance_status`, `created_at`) VALUES
(5, 1030004, NULL, NULL, NULL, 1030006, 'hotel management system', 'Approved', 'submit titles', 'Approved', NULL, 'Approved', NULL, 'Approved', NULL, 'Approved', NULL, 'Approved', NULL, 'Scheduled', NULL, 'Yes', 78, 73, 'Cleared', '2026-05-18 10:18:38'),
(6, 1030005, NULL, NULL, NULL, 1030006, 'wholesale management system', 'Approved', 'looks good kindly submit subtitles ', 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-05-18 10:18:38'),
(7, 1030007, NULL, NULL, NULL, 1030006, 'porfolio management system', 'Approved', '', 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-06-11 12:58:35'),
(8, 1030008, NULL, NULL, NULL, 1030006, 'cctv management system', 'Approved', '', 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-06-11 14:34:21'),
(9, 1030010, 'housing approval  management system', 'fairmat  management system', 'pizza inn management system', 1030009, 'housing approval  management system', 'Approved', 'housing approval perfect', 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-07-06 12:41:44'),
(10, 1030011, NULL, NULL, NULL, 1030006, NULL, 'Approved', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-07-06 13:18:53'),
(21, 1040001, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-07-16 19:56:43'),
(22, 1040002, 'netflix management system', 'shif management system', 'sgr management system', 1030009, 'sgr management system', 'Approved', 'sgr perfect for me', 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-07-16 21:34:52'),
(24, 1040003, 'amazon management system', 'spotify management system', 'construction management system', 1030006, 'construction management system', 'Approved', 'go ahead with the construction management system', 'Approved', NULL, 'Rejected', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-07-16 22:15:34'),
(26, 1040000, NULL, NULL, NULL, 1030006, NULL, 'Approved', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-07-16 22:25:14'),
(27, 1040004, 'porfolio management system', 'tourism management system', 'car wash management system', 1030006, 'tourism management system', 'Approved', '', 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-07-20 20:52:03'),
(28, 1040005, 'porfolio management system', 'tourism management system', 'construction management system', 1030006, 'porfolio management system', 'Approved', '', 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-07-22 14:23:19'),
(29, 1040006, 'amazon management system', 'tourism management system', 'construction management system', 1030006, 'amazon management system', 'Approved', 'all good', 'Approved', NULL, 'Approved', NULL, 'Approved', NULL, 'Approved', NULL, 'Approved', NULL, 'Scheduled', NULL, 'Yes', 85, 60, 'Cleared', '2026-07-23 08:10:32'),
(31, 1040007, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-08-06 10:47:46'),
(33, 105471, 'CHURCH management system', 'FAIRMART management system', 'NAIVAS management system', 1030009, 'NAIVAS management system', 'Approved', 'go ahead with option 3', 'Approved', NULL, 'Approved', NULL, 'Approved', NULL, 'Approved', NULL, 'Approved', NULL, 'Scheduled', 'perfect', 'Yes', 81, 93, 'Cleared', '2026-08-06 10:52:07'),
(34, 105472, 'housing approval  management system', 'spotify management system', 'NAIVAS management system', 1030006, 'NAIVAS management system', 'Approved', 'option 3 all good', 'Approved', 'all is well', 'Pending Review', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-08-06 11:35:03'),
(35, 105473, 'git clone management system', 'tax clearance management system', 'animal feeds management system', 1030009, 'tax clearance management system', 'Approved', 'option 2 perfect', 'Approved', 'all good', 'Approved', 'perfect', 'Approved', 'perfect', 'Approved', 'clean work move to next', 'Approved', 'good work', 'Scheduled', 'all good', 'Yes', 81, 96, 'Cleared', '2026-08-06 11:38:18'),
(36, 105474, 'cement management system', 'apartement management system', 'market management system', 1030009, 'market management system', 'Approved', 'option 3 good', 'Approved', 'all good move on', 'Approved', 'perfect', 'Approved', 'nice work move to next one', 'Approved', 'all good move to next milestone', 'Approved', 'excellent work', 'Scheduled', 'Perfect, you are now ready to defend. Kindly prepare for the panel', 'Yes', 88, 90, 'Cleared', '2026-08-07 17:38:57'),
(37, 105475, 'traffic management system', 'dairy management system', 'sarit cinema management system', 1030009, 'sarit cinema management system', 'Approved', 'option 3 all good', 'Approved', 'well done', 'Approved', 'good work', 'Approved', 'move to the next milestone', 'Approved', 'good move to next', 'Approved', 'perfect', 'Scheduled', 'ready for panel', 'Yes', 77, 85, 'Cleared', '2026-08-25 19:13:36'),
(40, 105478, NULL, NULL, NULL, NULL, NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-09-02 11:39:08'),
(41, 1050110, NULL, NULL, NULL, NULL, NULL, '', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'Pending', NULL, 'No', NULL, 0, 'Not Cleared', '2026-09-10 17:45:17');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `student_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `reg_no` varchar(50) DEFAULT NULL,
  `transcript_path` varchar(255) DEFAULT NULL,
  `title_1` varchar(255) DEFAULT NULL,
  `title_2` varchar(255) DEFAULT NULL,
  `title_3` varchar(255) DEFAULT NULL,
  `approval_status` enum('Registered','Transcript Uploaded','Titles Submitted','Approved') DEFAULT 'Registered'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`student_id`, `full_name`, `reg_no`, `transcript_path`, `title_1`, `title_2`, `title_3`, `approval_status`) VALUES
(105471, 'Zacharia Nyagwansa', '105471', '../uploads/transcripts/Transcript_105471_1786013589.pdf', 'CHURCH management system', 'FAIRMART management system', 'NAIVAS management system', 'Approved'),
(105472, 'ian rose', '105472', '../uploads/transcripts/Transcript_105472_1789195811.pdf', NULL, NULL, NULL, 'Approved'),
(105473, 'sam kimani', '105473', '../uploads/transcripts/Transcript_105473_1786369965.pdf', 'git clone management system', 'tax clearance management system', 'animal feeds management system', 'Approved'),
(105474, 'ronaldo kimani', '105474', '../uploads/transcripts/Transcript_105474_1786364990.pdf', 'cement management system', 'apartment management system', 'market management system', 'Approved'),
(105475, 'teresia kimani', '105475', '../uploads/transcripts/Transcript_105475_1787686051.pdf', 'traffic management system', 'dairy management system', 'sarit cinema management system', 'Approved'),
(105478, 'john were', '105478', NULL, NULL, NULL, NULL, 'Registered'),
(1010001, 'leeking', '1010001', '1777492209_sociopathic_traits_summary.pdf', NULL, NULL, NULL, 'Approved'),
(1030004, 'markmwai', '1030004', '1777999410_sociopathic_traits_summary.pdf', 'dairy management system', 'accouting management system', 'hotel management system', 'Approved'),
(1030005, 'shanice mwangi', '1030005', '1777995093_sociopathic_traits_summary.pdf', 'instargram management system', 'sha management system', 'wholesale managent system', 'Approved'),
(1030007, 'ryan karuiki', '1030007', '../uploads/transcripts/Transcript_1030007.pdf', 'car wash management system', 'porfolio management system', 'hp management system', 'Approved'),
(1030008, 'sarah kimani', '1030008', '../uploads/transcripts/Transcript_1030008.pdf', 'cctv management system', 'car wash management system', 'gardening management system', 'Approved'),
(1030010, 'lisa mwangi', '1030010', '../uploads/transcripts/Transcript_1030010.pdf', NULL, NULL, NULL, 'Approved'),
(1030011, 'grace mwangi', '1030011', '../uploads/transcripts/Transcript_1030011.pdf', NULL, NULL, NULL, 'Approved'),
(1040000, 'sonia mwangi', '1040000', '../uploads/transcripts/Transcript_1040000_1784240731.pdf', NULL, NULL, NULL, 'Approved'),
(1040001, 'king julian', '1040001', NULL, NULL, NULL, NULL, 'Registered'),
(1040002, 'shanice kimani', '1040002', '../uploads/transcripts/Transcript_1040002.pdf', 'hospital management system', 'car wash management system', 'cafe management system', 'Approved'),
(1040003, 'ruth kimani', '1040003', '../uploads/transcripts/Transcript_1040003.pdf', 'amazon management system', 'spotify management system', 'construction management system', 'Approved'),
(1040004, 'eugene kimani', '1040004', '../uploads/transcripts/Transcript_1040004_1784580774.pdf', 'porfolio management system', 'tourism management system', 'car wash management system', 'Approved'),
(1040005, 'gareth bale', '1040005', '../uploads/transcripts/Transcript_1040005_1784730272.pdf', 'porfolio management system', NULL, NULL, 'Approved'),
(1040006, 'ian kimani', '1040006', '../uploads/transcripts/Transcript_1040006_1784794279.pdf', NULL, NULL, NULL, 'Approved'),
(1040007, 'zacharia king', '1040007', NULL, NULL, NULL, NULL, 'Registered'),
(1050110, 'Nahum mwangi', '1050110', NULL, NULL, NULL, NULL, 'Registered');

-- --------------------------------------------------------

--
-- Table structure for table `supervisor`
--

CREATE TABLE `supervisor` (
  `supervisor_id` int(11) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor`
--

INSERT INTO `supervisor` (`supervisor_id`, `full_name`, `department`) VALUES
(1030006, 'Dr.Lee Nyaga', 'Computer Science'),
(1030009, 'Dr.Alice Kimani', 'Computer Science'),
(1070000, 'Dr.Sonia Mwangi', 'Computer Science');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','supervisor','coordinator','hod') NOT NULL,
  `security_question` varchar(255) DEFAULT 'What is your high school name?',
  `security_answer` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `username`, `password`, `role`, `security_question`, `security_answer`) VALUES
(100, 'mark.mbugua', '$2y$10$SmqjeJHmnc7MUToU0MaQ4ulk4eBiXhOB/W3/n/41f8X4Kd6Aha.wK', 'hod', 'What is your department name?', 'Computer Science'),
(105471, '105471', '$2y$10$m3P52uHZYz8hLVDNsSwp6O3yMaEBVJ5R4.8daCGV0icULuI/5ebBC', 'student', 'What is your high school name?', 'CUEA'),
(105472, '105472', '$2y$10$84p0Ex35mD8XCq0bLlIsH.hwoUDIa1U414TTnIuCEbYXr/0yvGcGm', 'student', 'What is your high school name?', 'moringa'),
(105473, '105473', '$2y$10$FARw679dOJrqRO8YG8wzsueXZIOiylFjy5ozo/Rh1/ndDdE1pQ6oK', 'student', 'What is your high school name?', 'st elizabeth karen'),
(105474, '105474', '$2y$10$efdYtdBhGvUhkWD94StoT.BYU1DXNddF2Gmqm.NIg3vMk3nY.XLmm', 'student', 'What is your high school name?', 'st elizabeth karen'),
(105475, '105475', '$2y$10$M7LtxhXPpiUNJgfZEEnPB.VWT2pjrvxcqteuuRZJxauV1VDF0gKQG', 'student', 'What is your high school name?', 'moringa'),
(105478, '105478', '$2y$10$IbaHi0muo6ScD.ig.J3RQOJweruBre7Npo0cwKhoTJkDyzXOqTQIO', 'student', 'What is your high school name?', 'st elizabeth karen'),
(1010001, 'leeking', '$2y$10$CYGAGDySUC2gd6vtQzE7D.BpszB/jUrH50s1lB68.3qQxPe35PmGS', 'student', 'What is your high school name?', 'makini'),
(1020000, 'Dr.Eugene Kimani', '$2y$10$mp7GOdHmj.mW6m8iAsDZY.V3DlG5RXmAGFYFRKFglcONNAOPWOCR2', 'coordinator', 'What is your high school name?', 'makini'),
(1030004, '1030004', '$2y$10$H.NZuCOiF0WRXwwcKSpjk.pC3Cpj/9W2.Y.DwnQ9HYB0D5bXObcQa', 'student', 'What is your high school name?', 'makini'),
(1030005, '1030005', '$2y$10$wT5flWEqhfMMn2ii3FZ4kOvv82FTvXLA5QnYNK02B9U72dZUYYwUW', 'student', 'What is your high school name?', 'makini'),
(1030006, 'Dr.Lee Nyaga', '$2y$10$3bLFYFrWywrZQg7YBJpb1.gZ9CgjxRNE6eNwcw.9/myndvwMWhGky', 'supervisor', 'What is your high school name?', 'makini'),
(1030007, '1030007', '$2y$10$zlSL2SbvsSHobE7Uy7gwrOkRb1BDlU0ZHf5Ab9doHGMHTbOZ2PW4a', 'student', 'What is your high school name?', 'makini'),
(1030008, '1030008', '$2y$10$54LKeOE5qVb8D6XsO9co3.arF4/HlLSGLLhDqB97RIYEMBkA1BQEe', 'student', 'What is your high school name?', 'sarah123'),
(1030009, 'Dr.Alice Kimani', '$2y$10$I8EuaAwoPMCM3dkl3XUh/OAD8xkGzwsAvbyWXsJOZEY83o1cfiYRu', 'supervisor', 'What is your high school name?', 'makini'),
(1030010, '1030010', '$2y$10$jdFdeyp5IvsPLG2CX84JROnliyLk4LrBAhwMMmTIVB55.zAH46SUa', 'student', 'What is your high school name?', 'lisa123'),
(1030011, '1030011', '$2y$10$mOaWZ8duivQVVHF6qKkqROqccbx8k0G.bTzLoJvQ6jSy4NOX6YaCG', 'student', 'What is your high school name?', 'langata primary'),
(1030021, '1040001', '$2y$10$FN2sc1qMbRC5Qfy84DsQp.Vjgz3NbVxP7Rovmi84MweoRK9y9HOGm', 'student', 'What is your high school name?', 'moringa'),
(1040000, '1040000', '$2y$10$NrznPukH0lN558NtuN9m0.z8zJphqNdvIDAv.BJZx92enBqkbA98i', 'student', 'What is your high school name?', 'st elizabeth karen'),
(1040002, '1040002', '$2y$10$aN.Z3z4.Cj.7eD6Rbg5dWOLfxyDTCEwBYxQNjS2Kw0l/fGW32zLMm', 'student', 'What is your high school name?', 'makini'),
(1040003, '1040003', '$2y$10$Mdrj8bFMwKAy5KM5ovKeaezNzUL3PCGLJBZ92t5kQRA6NNHZcP3vq', 'student', 'What is your high school name?', 'moringa'),
(1040004, '1040004', '$2y$10$R48uwbfyntWXeZJMMJFhk.uuefNWn4LBcACnImrP2mtup2bPqGJG.', 'student', 'What is your high school name?', 'st elizabeth karen'),
(1040005, '1040005', '$2y$10$PEx4pgec4dUg7ilpWYoc3.tbSxPCxxnMJK.C/SZutIwIlQf/v4tQG', 'student', 'What is your high school name?', 'moringa'),
(1040006, '1040006', '$2y$10$N.tgeafNB/jKV4S8Sht6je8N7XZnXu6VfBOGMOKk0lQ3mJzX3q1kO', 'student', 'What is your high school name?', 'makini'),
(1040007, '1040007', '$2y$10$Djz2f5a0BtVByGxZ3TXfoOt4Vw0dpdjKvNZl/LJbnQLp6pC6Si7oa', 'student', 'What is your high school name?', 'st elizabeth karen'),
(1050110, '1050110', '$2y$10$AjO6talNHUPod1bKpWD6FOOc4/X9uJdJCkesWiVJKG39ig/twTO/e', 'student', 'In what town were you born?', 'kajiado'),
(1070000, '1070000', '$2y$10$Dc5jHREwYYhT0Og3DRuapu7CJeLe8Ch0ptJkILICK8vLw8Q5wVfRa', 'supervisor', 'What is your high school name?', 'St. Hannah\\\'s Girls school');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `panel_evaluation`
--
ALTER TABLE `panel_evaluation`
  ADD PRIMARY KEY (`eval_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `examiner_id` (`examiner_id`);

--
-- Indexes for table `project`
--
ALTER TABLE `project`
  ADD PRIMARY KEY (`project_id`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `project_ibfk_1` (`student_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `reg_no` (`reg_no`);

--
-- Indexes for table `supervisor`
--
ALTER TABLE `supervisor`
  ADD PRIMARY KEY (`supervisor_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `panel_evaluation`
--
ALTER TABLE `panel_evaluation`
  MODIFY `eval_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `project`
--
ALTER TABLE `project`
  MODIFY `project_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `panel_evaluation`
--
ALTER TABLE `panel_evaluation`
  ADD CONSTRAINT `panel_evaluation_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `project` (`project_id`),
  ADD CONSTRAINT `panel_evaluation_ibfk_2` FOREIGN KEY (`examiner_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `project`
--
ALTER TABLE `project`
  ADD CONSTRAINT `project_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `project_ibfk_2` FOREIGN KEY (`supervisor_id`) REFERENCES `supervisor` (`supervisor_id`);

--
-- Constraints for table `supervisor`
--
ALTER TABLE `supervisor`
  ADD CONSTRAINT `supervisor_ibfk_1` FOREIGN KEY (`supervisor_id`) REFERENCES `user` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
