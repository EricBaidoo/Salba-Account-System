-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2025 at 12:48 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `salba_acc`
--

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `Level` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`id`, `name`, `Level`, `created_at`) VALUES
(1, 'Creche', 'Pre-School', '2025-09-30 03:28:48'),
(2, 'Nursery 1', 'Pre-School', '2025-09-30 03:28:48'),
(3, 'Nursery 2', 'Pre-School', '2025-09-30 03:28:48'),
(4, 'KG 1', 'Pre-School', '2025-09-30 03:28:48'),
(5, 'KG 2', 'Pre-School', '2025-09-30 03:28:48'),
(6, 'Basic 1', 'Lower Basic', '2025-09-30 03:28:48'),
(7, 'Basic 2', 'Lower Basic', '2025-09-30 03:28:48'),
(8, 'Basic 3', 'Lower Basic', '2025-09-30 03:28:48'),
(9, 'Basic 4', 'Upper Basic', '2025-09-30 03:28:48'),
(10, 'Basic 5', 'Upper Basic', '2025-09-30 03:28:48'),
(11, 'Basic 6', 'Upper Basic', '2025-09-30 03:28:48'),
(12, 'Basic 7', 'Junior High', '2025-09-30 03:28:48');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `name`) VALUES
(7, 'ABACUS'),
(5, 'FOOD'),
(1, 'Maintenance'),
(2, 'SSNIT'),
(4, 'STATIONERY'),
(6, 'UNIFORMS'),
(3, 'UTILITIES');

-- --------------------------------------------------------

--
-- Table structure for table `fees`
--

CREATE TABLE `fees` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `fee_type` enum('fixed','class_based','category') DEFAULT 'fixed',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fees`
--

INSERT INTO `fees` (`id`, `name`, `amount`, `fee_type`, `description`, `created_at`) VALUES
(1, 'Examination Fee', 30.00, 'fixed', '', '2025-09-29 00:51:56'),
(2, 'Vacation Party', 30.00, 'fixed', '', '2025-09-29 00:53:23'),
(4, 'Books Fee', 0.00, 'class_based', '', '2025-09-29 00:59:19'),
(5, 'ABACUS', 0.00, 'class_based', '', '2025-09-29 01:00:18'),
(6, 'Toileteries', 70.00, 'fixed', '', '2025-09-29 01:00:51'),
(7, 'Feeding Fee', 0.00, 'class_based', '', '2025-09-29 18:31:44'),
(8, 'ADMISSION  FEE', 2000.00, 'fixed', '', '2025-09-30 04:04:16'),
(9, 'Uniform Fee', NULL, 'class_based', '', '2025-09-30 04:08:34'),
(10, 'Tuition Fee', NULL, 'category', '', '2025-09-30 04:32:28');

-- --------------------------------------------------------

--
-- Table structure for table `fee_amounts`
--

CREATE TABLE `fee_amounts` (
  `id` int(11) NOT NULL,
  `fee_id` int(11) NOT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fee_amounts`
--

INSERT INTO `fee_amounts` (`id`, `fee_id`, `class_name`, `category`, `amount`) VALUES
(62, 4, 'Creche', NULL, 250.00),
(63, 4, 'Nursery 1', NULL, 450.00),
(64, 4, 'Nursery 2', NULL, 450.00),
(65, 4, 'KG 1', NULL, 450.00),
(66, 4, 'KG 2', NULL, 450.00),
(67, 4, 'Basic 1', NULL, 800.00),
(68, 4, 'Basic 2', NULL, 800.00),
(69, 4, 'Basic 3', NULL, 800.00),
(70, 4, 'Basic 4', NULL, 800.00),
(71, 4, 'Basic 5', NULL, 800.00),
(72, 4, 'Basic 6', NULL, 800.00),
(73, 4, 'Basic 7', NULL, 850.00),
(82, 7, 'Creche', NULL, 1040.00),
(83, 7, 'Nursery 1', NULL, 1040.00),
(84, 7, 'Nursery 2', NULL, 1040.00),
(85, 7, 'KG 1', NULL, 1200.00),
(86, 7, 'KG 2', NULL, 1200.00),
(87, 7, 'Basic 1', NULL, 1200.00),
(88, 7, 'Basic 2', NULL, 1200.00),
(89, 7, 'Basic 3', NULL, 1200.00),
(90, 7, 'Basic 4', NULL, 1200.00),
(91, 7, 'Basic 5', NULL, 1200.00),
(92, 7, 'Basic 6', NULL, 1200.00),
(93, 7, 'Basic 7', NULL, 1200.00),
(94, 9, 'Basic 1', NULL, 150.00),
(99, 10, NULL, 'Pre-School', 700.00),
(100, 10, NULL, 'Lower Basic', 800.00),
(101, 10, NULL, 'Upper Basic', 800.00),
(102, 10, NULL, 'Junior High', 800.00),
(103, 5, 'KG 2', NULL, 250.00),
(104, 5, 'Basic 1', NULL, 100.00),
(105, 5, 'Basic 2', NULL, 100.00),
(106, 5, 'Basic 3', NULL, 100.00),
(107, 5, 'Basic 4', NULL, 100.00),
(108, 5, 'Basic 5', NULL, 100.00),
(109, 5, 'Basic 6', NULL, 100.00),
(110, 5, 'Basic 7', NULL, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `fee_id` int(11) DEFAULT NULL,
  `payment_type` enum('student','general') NOT NULL DEFAULT 'student',
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_allocations`
--

CREATE TABLE `payment_allocations` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `student_fee_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `class` varchar(50) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `parent_contact` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `first_name`, `last_name`, `class`, `date_of_birth`, `parent_contact`, `created_at`, `status`) VALUES
(1, 'FIRDAUS', 'ABDALLAH', 'Basic 4', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(2, 'TIPAYA', 'ABDALLAH MOHAMMED', 'Basic 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(3, 'MARIAM', 'ABDALLA', 'Basic 6', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(4, 'RAHMAN', 'ABDUL', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(5, 'JANELLE', 'ACHEAMPONGMAA ASIEDU', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(6, 'BRIAN', 'ADAMS', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(7, 'JESHURUN', 'ADAMS', 'NURSERY 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(8, 'OSAE', 'ADIEPENA ANUMWAA', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(9, 'DAVID', 'ADOBOE EYIRAM', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(10, 'RANSFORD', 'ADOBOE WOMA', 'Basic 5', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(11, 'CHEALSEA', 'AGYAPONG', 'Basic 3', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(12, 'GLENDER', 'AGYAPONG', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(13, 'ASAFO', 'AGYEI DONALD', 'Basic 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(14, 'ASAFO', 'AGYEI GERALD', 'Basic 3', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(15, 'CHRISTIAN', 'AKITAH K', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(16, 'JEREMY', 'ALOAYE', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(17, 'TESTIMONY', 'ALOAYE', 'Basic 4', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(18, 'CANDACE', 'AMANKWAAH YAA MENSAH', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(19, 'MICALY', 'AMANKWAH OWUSU GYAMFUAH', 'NURSERY 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(20, 'ADEPA', 'AMEYAW ESI', 'CRECHE', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(21, 'AMA', 'AMEYAW NANA', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(22, 'NUTIFAFA', 'AMEYAW', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(23, 'AFIA', 'AMOAKOA', 'Basic 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(24, 'PRINCE', 'ANGEL ANIM', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(25, 'RAYMOND', 'ANKRAH', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(26, 'LIZABETH', 'APHRIPHA', 'Creche', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(27, 'JAHAZIEL', 'APPIAH', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(28, 'JOCHEBED', 'APPIAH', 'Basic 4', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(29, 'MORIAH', 'APPIAH', 'NURSERY 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(32, 'OTHNIELTA', 'APPIAH', 'Basic 7', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(33, 'MAJOLA', 'ARKHURST KLENAM SAWYERR', 'NURSERY 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(34, 'MAJOLA', 'ARKHURST KLENAM SAWYERR', 'NURSERY 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(35, 'FOH', 'ARKHURST KWEKU', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(36, 'AMOBIA', 'ARKHURST NAA', 'Basic 4', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(37, 'ABA', 'ARKOH ADELAIDE MAMAE', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(39, 'MIRABEL', 'ARMAH', 'Basic 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(40, 'AMELEY', 'ARMAH THEODOSIAH NAA MIISHEE', 'Basic 6', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(41, 'ARYEE', 'ARYEEQUAYE ABDUL SALEEM NII', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(42, 'DEDE', 'ARMAH', 'CRECHE', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(43, 'ARMAH', 'ARYEEQUAYE DAWUD', 'NURSERY 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(44, 'JULIOUS', 'ARYEETEY', 'Basic 5', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(46, 'REINA', 'ASAMOAH YEBOAH MAXWELLA', 'NURSERY 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(47, 'DEANGIL', 'ASIRIFI', 'Basic 6', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(48, 'JULIET', 'AWUAH', 'Basic 4', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(49, 'ARKOH', 'BAAH MANUEL AFRIYIE', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(50, 'EDGARDO', 'BAIDOO', 'NURSERY 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(51, 'ENYAMA', 'BANNIE GODSGIFT NANA', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(52, 'SAMAABA', 'BANNIE GODSWILL NANA', 'Basic 5', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(53, 'MARVIN', 'BEDZRAH', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(54, 'SIAW', 'BEMPOE EMMANUEL', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(55, 'ARIEL', 'BENTIL', 'NURSERY 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(56, 'FOSUAA', 'BENTIL CHARISMA', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(57, 'ABAYAA', 'BERMUDEZ', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(58, 'RAFAEL', 'BERMUDEZ', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(59, 'GEORGINA', 'BLACKWELL OBENG', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(60, 'ALVIN', 'BOATENG', 'Basic 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(61, 'NANA', 'BOATENG JETHRO', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(62, 'AFRIRYIE', 'BOATENG PRINCE', 'Basic 6', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(63, 'BLESSING', 'DANSO', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(64, 'KYEKYEKU', 'DAPAAH ZION OPPONG', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(65, 'JESSE', 'DOE', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(66, 'JOEL', 'DOTSE', 'CRECHE', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(67, 'NANA', 'FILSON EKOW', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(68, 'EMERALDIA', 'FILSON', 'NURSERY 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(69, 'OSEI', 'FRIMPONG JADEN', 'NURSERY 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(70, 'ELIANA', 'GAVI AMENUVEVE', 'Nursery 1', NULL, NULL, '2025-09-29 01:46:08', 'inactive'),
(71, 'JEFFREY', 'HINI', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(72, 'AGYEMANG', 'KODUAH YOUNGMONEY', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(73, 'MAWUENA', 'KPORMEGBEY RYAN KOJO', 'Creche', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(74, 'KWAME', 'KWAANSAH NANA', 'Basic 7', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(75, 'ADELA', 'KWOFIE BRIANNA NANA', 'NURSERY 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(76, 'BROOKLYN', 'KYEI LOLA AKOSUA', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'inactive'),
(77, 'TAWFIQ', 'MAHMUD DREAM RYAN', 'Basic 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(79, 'JAYSON', 'MANTE-SARFO', 'Basic 6', NULL, NULL, '2025-09-29 01:46:08', 'inactive'),
(80, 'JOYCE', 'MENSAH', 'NURSERY 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(81, 'JULIOUS', 'MENSAH', 'Basic 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(82, 'OPARE', 'MINTAH CHLOE', 'Basic 4', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(83, 'OPARE', 'MINTAH ETHEN', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(84, 'OPARE', 'MINTAH SABASTIAN', 'Basic 6', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(85, 'ANAT', 'MOHAMMAD', 'NURSERY 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(86, 'RAHEEMA', 'MOHAMMED', 'Basic 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(87, 'GABRIELLA', 'NIZER', 'Basic 6', NULL, NULL, '2025-09-29 01:46:08', 'inactive'),
(88, 'JIBRIL', 'NIZER JANAT', 'NURSERY 2', NULL, NULL, '2025-09-29 01:46:08', 'inactive'),
(89, 'DANIELLA', 'NYARKO', 'Basic 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(90, 'SAMUEL', 'NYARKO', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(91, 'LAMPTEY', 'ODARTEI HARRISSON NII', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(92, 'MONDAY', 'ODUM', 'Basic 4', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(93, 'PRECIOUS', 'ODUM', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(94, 'NHYIRA', 'OFORI DONATELLA', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(95, 'SAMUEL', 'OFOSU', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(96, 'ELIANA', 'OPPONG BOADUWAA', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(97, 'JAYDEN', 'OSEI QUAYE', 'Basic 4', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(98, 'NYAMEKYE', 'OTOO JAIDAH', 'NURSERY 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(99, 'OKAINSIE', 'OTUMFUO MARY', 'KG 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(100, 'ADOM', 'TETTEH GODWIN', 'Basic 5', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(101, 'MAXWELL', 'TETTEH', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(102, 'RAYMOND', 'TETTEH', 'NURSERY 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(103, 'TRUDILOVE', 'TETTEH', 'Basic 4', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(104, 'BLESSING', 'THOMAS-SAM', 'Basic 5', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(105, 'MERCY', 'THOMAS-SAM', 'Basic 5', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(106, 'VAVA', 'TSIDI VALERIE', 'Basic 6', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(107, 'DANSO', 'YAMOAH QUEENSTER ABENA', 'NURSERY 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(108, 'MALTITI', 'AISHA', 'NURSERY 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(109, 'DAVID', 'ABANGAH', 'NURSERY 1', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(110, 'CHRISTODIA', 'OTOO MERYGOLD', 'CRECHE', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(111, 'OKATAKYIE', 'OSAE', 'CRECHE', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(112, 'TEIKO', 'TAGOE JYLAN NII', 'CRECHE', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(113, 'APPIAH', 'KELVIN', 'Basic 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(114, 'BOATENG', 'YEBOAH KESTER', 'KG 2', NULL, NULL, '2025-09-29 01:46:08', 'active'),
(115, 'RAHMAN', 'ABDUL', 'Basic 1', NULL, NULL, '2025-09-30 04:50:03', 'active'),
(116, 'ABLORDE GERHARD KEKELI WOLARNYO YAW', 'ABLORDE', 'Basic 4', NULL, NULL, '2025-09-30 14:57:46', 'active'),
(117, 'GERHARDINE KLENAM ESI', 'ABLORDE', 'Basic 5', NULL, NULL, '2025-09-30 14:58:19', 'active'),
(118, 'RYAN', 'AWUDE', 'KG 2', NULL, NULL, '2025-09-30 14:59:09', 'active'),
(119, 'ELSIE', 'ADOBOE', 'Creche', NULL, NULL, '2025-09-30 14:59:37', 'active'),
(120, 'AUSTIN', 'NANA WIAFE BROWN', 'Creche', NULL, NULL, '2025-09-30 15:00:12', 'active'),
(121, 'AASIYA NAANA', 'JAFAR', 'Creche', NULL, NULL, '2025-09-30 15:00:58', 'active'),
(122, 'MATILDA', 'ABOGLE', 'Basic 7', NULL, NULL, '2025-09-30 15:01:43', 'active'),
(123, 'YVONNE NUNOO', 'ADDAI', 'Nursery 1', NULL, NULL, '2025-09-30 16:09:38', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `student_fees`
--

CREATE TABLE `student_fees` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `fee_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `term` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `assigned_date` datetime DEFAULT current_timestamp(),
  `due_date` date DEFAULT NULL,
  `status` enum('pending','due','paid','overdue','cancelled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Admin', '$2y$10$p7/eGEM5SzFNqRIo2kYF8uc4CZVzSh9e7iaF.PDbmN7FFiAxlYjYG', 'admin', '2025-09-29 00:24:39');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_fee_assignments`
-- (See below for the actual view)
--
CREATE TABLE `v_fee_assignments` (
`assignment_id` int(11)
,`student_name` varchar(201)
,`student_class` varchar(50)
,`fee_name` varchar(100)
,`fee_type` enum('fixed','class_based','category')
,`amount` decimal(10,2)
,`due_date` date
,`term` varchar(50)
,`assigned_date` datetime
,`status` enum('pending','due','paid','overdue','cancelled')
,`notes` text
,`days_to_due` int(7)
,`payment_status` varchar(8)
);

-- --------------------------------------------------------

--
-- Structure for view `v_fee_assignments`
--
DROP TABLE IF EXISTS `v_fee_assignments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_fee_assignments`  AS SELECT `sf`.`id` AS `assignment_id`, concat(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`, `s`.`class` AS `student_class`, `f`.`name` AS `fee_name`, `f`.`fee_type` AS `fee_type`, `sf`.`amount` AS `amount`, `sf`.`due_date` AS `due_date`, `sf`.`term` AS `term`, `sf`.`assigned_date` AS `assigned_date`, `sf`.`status` AS `status`, `sf`.`notes` AS `notes`, to_days(`sf`.`due_date`) - to_days(curdate()) AS `days_to_due`, CASE WHEN `sf`.`status` = 'paid' THEN 'Paid' WHEN `sf`.`due_date` < curdate() AND `sf`.`status` = 'pending' THEN 'Overdue' WHEN to_days(`sf`.`due_date`) - to_days(curdate()) <= 7 AND `sf`.`status` = 'pending' THEN 'Due Soon' ELSE 'Pending' END AS `payment_status` FROM ((`student_fees` `sf` join `students` `s` on(`sf`.`student_id` = `s`.`id`)) join `fees` `f` on(`sf`.`fee_id` = `f`.`id`)) WHERE `sf`.`status` <> 'cancelled' ORDER BY `sf`.`due_date` DESC, `s`.`class` ASC, `s`.`first_name` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `fees`
--
ALTER TABLE `fees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fee_amounts`
--
ALTER TABLE `fee_amounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fee_class` (`fee_id`,`class_name`),
  ADD KEY `idx_fee_category` (`fee_id`,`category`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fk_payments_fee_id` (`fee_id`);

--
-- Indexes for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `student_fee_id` (`student_fee_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_fees`
--
ALTER TABLE `student_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fee_id` (`fee_id`),
  ADD KEY `idx_student_fees_status` (`status`),
  ADD KEY `idx_student_fees_due_date` (`due_date`),
  ADD KEY `idx_student_fees_assigned_date` (`assigned_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `fees`
--
ALTER TABLE `fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `fee_amounts`
--
ALTER TABLE `fee_amounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=124;

--
-- AUTO_INCREMENT for table `student_fees`
--
ALTER TABLE `student_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=702;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `fee_amounts`
--
ALTER TABLE `fee_amounts`
  ADD CONSTRAINT `fee_amounts_ibfk_1` FOREIGN KEY (`fee_id`) REFERENCES `fees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_fee_id` FOREIGN KEY (`fee_id`) REFERENCES `fees` (`id`),
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Constraints for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  ADD CONSTRAINT `payment_allocations_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  ADD CONSTRAINT `payment_allocations_ibfk_2` FOREIGN KEY (`student_fee_id`) REFERENCES `student_fees` (`id`);

--
-- Constraints for table `student_fees`
--
ALTER TABLE `student_fees`
  ADD CONSTRAINT `student_fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `student_fees_ibfk_2` FOREIGN KEY (`fee_id`) REFERENCES `fees` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
