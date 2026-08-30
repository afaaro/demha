-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 15, 2026 at 05:42 PM
-- Server version: 8.4.7
-- PHP Version: 8.4.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `demha`
--

-- --------------------------------------------------------

--
-- Table structure for table `people_member`
--

DROP TABLE IF EXISTS `people_member`;
CREATE TABLE IF NOT EXISTS `people_member` (
  `id` int NOT NULL AUTO_INCREMENT,
  `mother_id` int NOT NULL DEFAULT '-1',
  `father_id` int NOT NULL DEFAULT '-1',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fullname` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` enum('male','female') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'male',
  `is_alive` tinyint(1) NOT NULL DEFAULT '1',
  `dob` date DEFAULT '0000-00-00',
  `dod` date DEFAULT '0000-00-00',
  `living` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `photo` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `marital_status` enum('single','married','divorced','widowed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `fullname` (`fullname`),
  KEY `gender` (`gender`)
) ENGINE=InnoDB AUTO_INCREMENT=149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `people_member`
--

INSERT INTO `people_member` (`id`, `mother_id`, `father_id`, `name`, `fullname`, `gender`, `is_alive`, `dob`, `dod`, `living`, `photo`, `notes`, `visible`, `marital_status`, `created`) VALUES
(1, -1, -1, 'Abaali', 'Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, '', '2019-09-11 19:28:35'),
(2, -1, 1, 'Maxamed', 'Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-11 19:29:15'),
(3, -1, 1, 'Abuubakar', 'Abuubakar Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-11 19:30:49'),
(4, -1, 1, 'Yuusuf', 'Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-11 19:30:57'),
(5, -1, 2, 'Faqay caalim', 'Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-11 19:50:48'),
(6, -1, 5, 'Amiin Sadiiq', 'Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-11 19:51:13'),
(7, -1, 6, 'Axmed', 'Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:56:30'),
(8, -1, 7, 'Shariifoow', 'Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:56:41'),
(9, -1, 8, 'Aamin', 'Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:56:51'),
(10, -1, 9, 'Axmed', 'Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:57:01'),
(11, -1, 10, 'Sheekh Maxamed', 'Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:57:13'),
(12, -1, 11, 'Shariif', 'Shariif Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:57:23'),
(13, -1, 12, 'Awees', 'Awees Shariif Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:57:33'),
(14, -1, 13, 'Cali', 'Cali Awees Shariif Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:57:43'),
(15, -1, 14, 'Suufi', 'Suufi Cali Awees Shariif Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:57:52'),
(16, -1, 15, 'Maxamed', 'Maxamed Suufi Cali Awees Shariif Sheekh Maxamed Axmed Aamin Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:58:08'),
(17, -1, 8, 'Cabdi', 'Cabdi Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:58:52'),
(18, -1, 8, 'Xaaji', 'Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:59:04'),
(19, -1, 18, 'Cusmaan', 'Cusmaan Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:59:24'),
(20, -1, 19, 'Shiikheey', 'Shiikheey Cusmaan Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:59:43'),
(21, -1, 18, 'Nuur', 'Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 17:59:58'),
(22, -1, 21, 'Iikar', 'Iikar Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:00:09'),
(23, -1, 21, 'Mooye', 'Mooye Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:00:18'),
(24, -1, 22, 'Muxyidiin', 'Muxyidiin Iikar Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:00:31'),
(25, -1, 22, 'Mooye', 'Mooye Iikar Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:00:37'),
(26, -1, 22, 'Cusmaan', 'Cusmaan Iikar Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:00:45'),
(27, -1, 22, 'Maclin', 'Maclin Iikar Nuur Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:00:52'),
(28, -1, 18, 'Ikraam', 'Ikraam Xaaji Shariifoow Axmed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:01:22'),
(29, -1, 6, 'Cusmaan', 'Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:11:46'),
(30, -1, 6, 'Maxamed', 'Maxamed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:12:05'),
(31, -1, 30, 'Axmed', 'Axmed Maxamed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:12:23'),
(32, -1, 31, 'Abraar', 'Abraar Axmed Maxamed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:12:31'),
(33, -1, 31, 'Cali', 'Cali Abraar Axmed Maxamed Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:12:40'),
(34, -1, 29, 'Abubakar', 'Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:15:30'),
(35, -1, 34, 'Cusmaan', 'Cusmaan Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:15:41'),
(36, -1, 34, 'Yaxya', 'Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:15:50'),
(37, -1, 35, 'Maxamed', 'Maxamed Cusmaan Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:16:02'),
(38, -1, 37, 'Maclin', 'Maclin Maxamed Cusmaan Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:16:12'),
(39, -1, 38, 'Reer Yaawali', 'Reer Yaawali Maclin Maxamed Cusmaan Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:16:21'),
(40, -1, 38, 'Reer Shariif', 'Reer Shariif Maclin Maxamed Cusmaan Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:16:28'),
(41, -1, 36, 'Macow', 'Macow Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:16:49'),
(42, -1, 36, 'Cumar', 'Cumar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:16:57'),
(43, -1, 36, 'Nuur', 'Nuur Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:17:04'),
(44, -1, 36, 'Abubakar', 'Abubakar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:17:11'),
(45, -1, 36, 'Amiin', 'Amiin Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:17:20'),
(46, -1, 43, 'Reer Amoo Axmed', 'Reer Amoo Axmed Nuur Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:17:34'),
(47, -1, 44, 'Nuurow', 'Nuurow Abubakar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:17:49'),
(48, -1, 47, 'Cusmaan', 'Cusmaan Nuurow Abubakar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:17:57'),
(49, -1, 48, 'Cabduraxman', 'Cabduraxman Cusmaan Nuurow Abubakar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:18:05'),
(50, -1, 49, 'Xaaji Shariif', 'Xaaji Shariif Cabduraxman Cusmaan Nuurow Abubakar Yaxya Abubakar Cusmaan Amiin Sadiiq Faqay caalim Maxamed Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-13 18:18:14'),
(51, -1, 4, 'Xarameen', 'Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:45:50'),
(52, -1, 51, 'Cumar', 'Cumar Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:46:09'),
(53, -1, 51, 'Cali', 'Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:46:18'),
(54, -1, 51, 'Cusmaan', 'Cusmaan Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:46:32'),
(55, -1, 53, 'Abashiikh', 'Abashiikh Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:47:14'),
(56, -1, 53, 'Amiin (warmoog)', 'Amiin (warmoog) Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:47:24'),
(57, -1, 53, 'Abubakar', 'Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:47:33'),
(58, -1, 57, 'Amiin', 'Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:47:59'),
(59, -1, 57, 'Cabdalle', 'Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:48:08'),
(60, -1, 57, 'Maxamuud', 'Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:48:20'),
(61, -1, 57, 'Nuur', 'Nuur Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:48:27'),
(62, -1, 61, 'Maxamed', 'Maxamed Nuur Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:48:53'),
(63, -1, 61, 'Axmed', 'Axmed Nuur Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:49:00'),
(64, -1, 61, 'Maxamed (Xaaji Maye)', 'Maxamed (Xaaji Maye) Nuur Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:49:08'),
(65, -1, 61, 'Abukar (Xaaji Maye)', 'Abukar (Xaaji Maye) Nuur Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:49:21'),
(66, -1, 60, 'Imaankow', 'Imaankow Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:50:05'),
(67, -1, 66, 'Waliyoo', 'Waliyoo Imaankow Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:50:17'),
(68, -1, 66, 'Suufi', 'Suufi Imaankow Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:50:23'),
(69, -1, 66, 'Shiikheey', 'Shiikheey Imaankow Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:50:29'),
(70, -1, 66, 'Awees', 'Awees Imaankow Maxamuud Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:50:35'),
(71, -1, 58, 'Axmed', 'Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:51:20'),
(72, -1, 71, 'Maxamuud (Dabiye)', 'Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:51:49'),
(73, -1, 72, 'Axmed', 'Axmed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:52:04'),
(74, -1, 73, 'Maxamed (Sheekh Abba)', 'Maxamed (Sheekh Abba) Axmed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:52:17'),
(75, -1, 72, 'Maxamed', 'Maxamed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:52:50'),
(76, -1, 75, 'Axmed', 'Axmed Maxamed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:52:58'),
(77, -1, 75, 'Cabdalle', 'Cabdalle Maxamed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:53:05'),
(78, -1, 75, 'Jeylaani', 'Jeylaani Maxamed Maxamuud (Dabiye) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:53:12'),
(79, -1, 71, 'Nuur (Boodoo)', 'Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:54:09'),
(80, -1, 71, 'Maxamed', 'Maxamed Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:54:20'),
(81, -1, 80, 'Reer Shiikheey Maxamed Shiikh Awees (Garbood)', 'Reer Shiikheey Maxamed Shiikh Awees (Garbood) Maxamed Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:54:55'),
(82, -1, 79, 'Axmed', 'Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:55:16'),
(83, -1, 82, 'Cadoow', 'Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:55:32'),
(84, -1, 83, 'Cusmaan', 'Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:55:43'),
(85, -1, 84, 'Awees', 'Awees Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:55:51'),
(86, -1, 84, 'Xaaji', 'Xaaji Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:55:57'),
(87, -1, 84, 'Imaankeey', 'Imaankeey Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:56:03'),
(88, -1, 84, 'Sayyid', 'Sayyid Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:56:08'),
(89, -1, 85, 'Maxamed', 'Maxamed Awees Cusmaan Cadoow Axmed Nuur (Boodoo) Axmed Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:56:17'),
(90, -1, 58, 'Maye', 'Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:57:18'),
(91, -1, 90, 'Cabdalle', 'Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:57:33'),
(92, -1, 91, 'Nuur', 'Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:57:45'),
(93, -1, 92, 'Maxamed', 'Maxamed Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:57:59'),
(94, -1, 92, 'Cabdalle', 'Cabdalle Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:58:05'),
(95, -1, 93, 'Axmed', 'Axmed Maxamed Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:58:14'),
(96, -1, 95, 'Maxamed', 'Maxamed Axmed Maxamed Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:58:22'),
(97, -1, 95, 'Banuuri', 'Banuuri Axmed Maxamed Nuur Cabdalle Maye Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:58:28'),
(98, -1, 58, 'Cusmaan', 'Cusmaan Amiin Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 01:59:40'),
(99, -1, 59, 'Maxamed', 'Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:02:15'),
(100, -1, 59, 'Cabduraxman', 'Cabduraxman Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:02:26'),
(101, -1, 59, 'Axmed', 'Axmed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:02:31'),
(102, -1, 99, 'Cabdalle', 'Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:02:46'),
(103, -1, 102, 'Maxamed (Macow Shiikh)', 'Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:03:07'),
(104, -1, 102, 'Cumar', 'Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:03:14'),
(105, -1, 102, 'Cabduraxman (Sheekh Suufi)', 'Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:03:25'),
(106, -1, 102, 'Xuseen', 'Xuseen Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:03:34'),
(107, -1, 102, 'Calawi', 'Calawi Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:03:47'),
(108, -1, 103, 'Ibraahim', 'Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:04:12'),
(109, -1, 108, 'Maclin', 'Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:04:20'),
(110, -1, 109, 'Xaaji', 'Xaaji Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:04:30'),
(111, -1, 109, 'Jeylaani', 'Jeylaani Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:04:37'),
(112, -1, 109, 'Maana', 'Maana Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', '', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:04:47'),
(113, -1, 110, 'Ibraahim (Gaduudow)', 'Ibraahim (Gaduudow) Xaaji Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:05:09'),
(114, -1, 110, 'Qaasim (Gaduudow)', 'Qaasim (Gaduudow) Xaaji Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:05:17'),
(115, -1, 110, 'Maxamed (Gaduudow)', 'Maxamed (Gaduudow) Xaaji Maclin Ibraahim Maxamed (Macow Shiikh) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:05:25'),
(116, -1, 104, 'Cabdi', 'Cabdi Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:05:44'),
(117, -1, 104, 'Xaaji', 'Xaaji Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:05:49'),
(118, -1, 104, 'Maxamed', 'Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:05:55'),
(119, -1, 116, 'Maxamed', 'Maxamed Cabdi Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:06:10'),
(120, -1, 119, 'Cusmaan', 'Cusmaan Maxamed Cabdi Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:06:22'),
(121, -1, 120, 'Axmed', 'Axmed Cusmaan Maxamed Cabdi Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:06:29'),
(122, -1, 117, 'Abuukar', 'Abuukar Xaaji Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:06:47'),
(123, -1, 122, 'Maxamed (Somali Fruit)', 'Maxamed (Somali Fruit) Abuukar Xaaji Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:06:57'),
(124, -1, 118, 'Axmed', 'Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:07:24'),
(125, -1, 124, 'Cumar', 'Cumar Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:07:33'),
(126, -1, 124, 'Shariif', 'Shariif Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:07:41'),
(127, -1, 125, 'Suufi', 'Suufi Cumar Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:07:50'),
(128, -1, 127, 'Maxamed (Jeeri)', 'Maxamed (Jeeri) Suufi Cumar Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:08:03'),
(129, -1, 127, 'Cumar', 'Cumar Suufi Cumar Axmed Maxamed Cumar Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:08:09'),
(130, -1, 105, 'Maxamed', 'Maxamed Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:11:43'),
(131, -1, 105, 'Cusmaan', 'Cusmaan Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:11:50'),
(132, -1, 105, 'Abubakar', 'Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:11:56'),
(133, -1, 105, 'Shaami', 'Shaami Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:12:05'),
(134, -1, 132, 'Dheeroow', 'Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:12:50'),
(135, -1, 132, 'Nuureyni', 'Nuureyni Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:13:01'),
(136, -1, 134, 'Dahir', 'Dahir Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:13:49'),
(137, -1, 134, 'Mardaadi', 'Mardaadi Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:13:57'),
(138, -1, 134, 'Khaliif Axmed', 'Khaliif Axmed Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:14:08'),
(139, -1, 134, 'Baaba Aamac', 'Baaba Aamac Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:14:17'),
(140, -1, 134, 'Xuseen', 'Xuseen Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:14:23'),
(141, -1, 134, 'Abba Cali', 'Abba Cali Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:14:30'),
(142, -1, 134, 'Jeylaani', 'Jeylaani Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:14:39'),
(143, -1, 134, 'Cumar (Cumushow)', 'Cumar (Cumushow) Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:14:47'),
(144, -1, 134, 'Cabdallah', 'Cabdallah Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:14:54'),
(145, -1, 134, 'Iikar', 'Iikar Dheeroow Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:15:00'),
(146, -1, 135, 'Axmed', 'Axmed Nuureyni Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:15:29'),
(147, -1, 135, 'Bakar', 'Bakar Nuureyni Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:15:35'),
(148, -1, 135, 'Muxyiddin', 'Muxyiddin Nuureyni Abubakar Cabduraxman (Sheekh Suufi) Cabdalle Maxamed Cabdalle Abubakar Cali Xarameen Yuusuf Abaali ', 'male', 1, '0000-00-00', '0000-00-00', '', '', '', 1, 'single', '2019-09-14 02:15:41');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
