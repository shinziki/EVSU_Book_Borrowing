-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 10, 2025 at 03:53 PM
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
-- Database: `coffee_prince_library`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `created_at`) VALUES
(1, 1, 'delete', 'Deleted transaction ID: 33', '2025-06-09 20:07:03'),
(2, 1, 'delete', 'Deleted transaction ID: 32', '2025-06-09 20:08:41'),
(3, 1, 'delete', 'Deleted transaction ID: 31', '2025-06-09 20:08:48'),
(4, 1, 'delete', 'Deleted transaction ID: 4 (Book: The Subtle Art of Not Giving a Fuck, Status: Borrowed)', '2025-06-09 20:20:57'),
(5, 1, 'delete', 'Deleted transaction ID: 27 (Book: Everything is Fucked, Status: Borrowed)', '2025-06-09 20:21:37');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `fullname`, `email`, `role`, `profile_image`) VALUES
(1, 'admin', '$2y$10$luB0oZz0l5COixfnBSxKXOGM8b3fcTWSJ4cn.7k.rZ9jak.p.ms1e', 'Administrator', 'migsbacho04@gmail.com', 'admin', 'uploads/profile_images/admin_1_1749138198.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(100) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `barcode` varchar(50) NOT NULL,
  `status` enum('Available','Borrowed','Overdue','Damaged','Lost') DEFAULT 'Available',
  `stock` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cover_image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `description`, `barcode`, `status`, `stock`, `created_at`, `cover_image_path`) VALUES
(4, 'The Subtle Art of Not Giving a Fuck', 'Mark Manson', '9780062641540', 'Self Improvement', 'A counterintuitive approach to living a good life.', '9780062641540', 'Lost', 7, '2025-06-05 02:06:11', 'uploads/books/6841b3ddcb493.jpg'),
(5, 'Everything is Fucked', 'Mark Manson', '9780062888433', 'Self Improvement', 'This Book gave these people Hope.', '9780062888433', 'Damaged', 8, '2025-06-05 08:36:44', 'uploads/books/6841b36e80bfa.png'),
(6, 'Malice', 'Pintip Dunn', '9781640634121', 'Fiction', 'Taking one life could save millions.', '9781640634121', 'Available', 9, '2025-06-05 15:15:11', 'uploads/books/6841b53e73ab7.jpg'),
(7, '48 Laws of Power', 'Robert Greene', 'LIB0001', 'Self Improvement', 'If Power is your ultimate goal, this is the book you need The Times.', 'LIB0001', 'Available', 10, '2025-06-05 15:21:36', 'uploads/books/6841b600070f4.png'),
(8, 'Our Tribe', 'Terry Pluto', '9780684845050', 'History', 'Perhaps the best American writer of sports books.', '9780684845050', 'Available', 10, '2025-06-05 15:23:46', 'uploads/books/6841b682d86ee.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `barcode` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `photo_path` varchar(255) DEFAULT NULL,
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `membership_type` enum('Regular','Premium') NOT NULL DEFAULT 'Regular'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `fullname`, `email`, `phone`, `address`, `barcode`, `created_at`, `photo_path`, `notifications_enabled`, `membership_type`) VALUES
(5, 'Miguelito Bacho', 'miguelito.bacho@evsu.edu.ph', '09454875526', 'Tambulilid', 'MEM2005', '2025-06-05 02:20:47', 'uploads/members/6840feff183d1.jpg', 1, 'Premium'),
(7, 'Nicole V. Cretecio', 'nicole.cretecio@evsu.edu.ph', '09690357589', 'Simangan', 'MEM2006', '2025-06-05 15:01:03', 'uploads/members/6841b12fe17e7.jpg', 1, 'Premium'),
(8, 'Raziel Insigne', 'raziel.insigne@evsu.edu.ph', '09465320790', 'Carigara', 'MEM2007', '2025-06-05 15:02:02', 'uploads/members/6841b16acbc66.jpg', 1, 'Premium'),
(9, 'Ariel Cupta', 'ariel.cupta@evsu.edu.ph', '09123456789', 'Don Felipe', 'MEM2008', '2025-06-05 15:03:03', 'uploads/members/6841b1a75c484.jpg', 1, 'Regular'),
(10, 'Regine Pales', 'regine.pales@evsu.edu.ph', '09987654321', 'Kagbuhangin', 'MEM2009', '2025-06-05 15:04:09', 'uploads/members/6841b1e9d978c.jpg', 1, 'Regular'),
(11, 'Gabriel Valmera', 'gabriel.valmera@evsu.edu.ph', '09123456789', 'Albuera', 'MEM2010', '2025-06-05 15:04:54', 'uploads/members/6841b21618745.jpg', 1, 'Regular'),
(12, 'Jhonel Andamon', 'jhonel.andamon@evsu.edu.ph', '09456123789', 'Alta Vista', 'MEM2011', '2025-06-05 15:05:47', 'uploads/members/6841b24b11d7c.jpg', 1, 'Regular');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `type` enum('Due Soon','Overdue','Return Confirmation','Payment','System','Book Damaged Penalty','Book Lost Penalty') NOT NULL,
  `is_sent` tinyint(1) DEFAULT 0,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `member_id`, `transaction_id`, `message`, `type`, `is_sent`, `is_read`, `sent_date`, `created_at`) VALUES
(3, 5, NULL, 'Welcome to Coffee Prince Library!\n\nYour member information:\nName: Miguelito Bacho\nBarcode: MEM2005\n\nThank you for joining Coffee Prince Library!', 'System', 1, 0, '2025-06-05 10:20:49', '2025-06-05 02:20:49'),
(5, 5, 5, 'Dear Miguelito Bacho,\n\nYou have borrowed the book \'The Subtle Art of Not Giving a Fuck\' from Coffee Prince Library.\n\nBorrow date: June 5, 2025\nDue date: June 19, 2025\n\nPlease return the book on or before the due date to avoid penalties.\nLate returns incur a penalty of 3x the original borrowing fee.\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-05 05:24:36'),
(6, 5, 5, 'Dear Miguelito Bacho,\n\nYou have returned the book \'The Subtle Art of Not Giving a Fuck\' to Coffee Prince Library.\nReturn date: June 5, 2025\n\nThank you for using Coffee Prince Library!\n', 'Return Confirmation', 1, 0, NULL, '2025-06-05 05:25:43'),
(7, 5, 6, 'Dear Miguelito Bacho,\n\nYou have borrowed the book \'The Subtle Art of Not Giving a Fuck\' from Coffee Prince Library.\n\nBorrow date: June 5, 2025\nDue date: June 8, 2025\n\nPlease return the book on or before the due date to avoid penalties.\nLate returns incur a penalty of 3x the original borrowing fee.\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-05 05:51:01'),
(8, 5, 6, 'Dear Miguelito Bacho,\n\nYou have borrowed the book \'The Subtle Art of Not Giving a Fuck\' from Coffee Prince Library.\nDue date: June 8, 2025\nPayment amount: 30.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 0, 0, NULL, '2025-06-05 05:51:01'),
(9, 5, 9, 'Dear Miguelito Bacho,\n\nYou have borrowed the book \'The Subtle Art of Not Giving a Fuck\' from Coffee Prince Library.\n\nBorrow date: June 5, 2025\nDue date: June 10, 2025\n\nPlease return the book on or before the due date to avoid penalties.\nLate returns incur a penalty of 3x the original borrowing fee.\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-05 06:53:05'),
(10, 5, 9, 'Dear Miguelito Bacho,\n\nYou have borrowed the book \'The Subtle Art of Not Giving a Fuck\' from Coffee Prince Library.\nDue date: June 10, 2025\nPayment amount: 30.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-05 06:53:05'),
(28, 5, 20, 'Dear Miguelito Bacho,\n\nYou have borrowed the book \'Everything is Fucked!\' from Coffee Prince Library.\nDue date: June 12, 2025\nPayment amount: 50.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-05 09:08:14'),
(29, 5, 20, 'Dear Miguelito Bacho,\n\nYou have returned the book \'Everything is Fucked!\' to Coffee Prince Library.\nReturn date: June 5, 2025\n\nThank you for using Coffee Prince Library!\n', 'Return Confirmation', 1, 0, NULL, '2025-06-05 09:09:35'),
(42, 9, 28, 'Dear Ariel Cupta,\n\nYou have borrowed the book \'The Subtle Art of Not Giving a Fuck\' from Coffee Prince Library.\nDue date: June 19, 2025\nPayment amount: 50.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-05 15:31:58'),
(43, 11, 29, 'Dear Gabriel Valmera,\n\nYou have borrowed the book \'The Subtle Art of Not Giving a Fuck\' from Coffee Prince Library.\nDue date: June 19, 2025\nPayment amount: 50.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-05 15:32:46'),
(44, 12, 30, 'Dear Jhonel Andamon,\n\nYou have borrowed the book \'The Subtle Art of Not Giving a Fuck\' from Coffee Prince Library.\nDue date: June 8, 2025\nPayment amount: 30.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-05 15:33:19'),
(48, 8, 34, 'Dear Raziel Insigne,\n\nYou have borrowed the book \'The Subtle Art of Not Giving a Fuck\' from Coffee Prince Library.\nDue date: June 10, 2025\nPayment amount: 30.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-05 15:35:19'),
(49, 10, 35, 'Dear Regine Pales,\n\nYou have borrowed the book \'The Subtle Art of Not Giving a Fuck\' from Coffee Prince Library.\nDue date: June 19, 2025\nPayment amount: 50.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-05 15:35:45'),
(50, 7, 36, 'Dear Nicole V. Cretecio,\n\nYou have borrowed the book \'Everything is Fucked\' from Coffee Prince Library.\nDue date: June 13, 2025\nPayment amount: 50.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-06 06:43:28'),
(51, 7, 36, 'Dear Nicole V. Cretecio,\n\nYou have returned the book \'Everything is Fucked\'.\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-06 06:46:10'),
(52, 12, 37, 'Dear Jhonel Andamon,\n\nYou have borrowed the book \'Everything is Fucked\' from Coffee Prince Library.\nDue date: June 20, 2025\nPayment amount: 50.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-06 07:23:05'),
(53, 12, 37, 'Dear Jhonel Andamon,\n\nYou have returned the book \'Everything is Fucked\' to Coffee Prince Library.\nReturn date: June 9, 2025\n\nThank you for using Coffee Prince Library!\n', 'Return Confirmation', 1, 0, '2025-06-10 02:49:18', '2025-06-09 18:49:18'),
(54, 10, 35, 'Dear Regine Pales,\n\nYou have returned the book \'The Subtle Art of Not Giving a Fuck\' to Coffee Prince Library.\nReturn date: June 9, 2025\n\nThank you for using Coffee Prince Library!\n', 'Return Confirmation', 1, 0, '2025-06-10 02:49:26', '2025-06-09 18:49:26'),
(55, 5, 6, 'Dear Miguelito Bacho,\n\nThe book \'The Subtle Art of Not Giving a Fuck\' is OVERDUE.\nDue date was: June 8, 2025\nPenalty amount: 90.00 pesos\n\nPlease return the book as soon as possible and settle the penalty.\n\nThank you for your cooperation.\n', 'Overdue', 1, 0, NULL, '2025-06-09 18:58:07'),
(56, 12, 30, 'Dear Jhonel Andamon,\n\nThe book \'The Subtle Art of Not Giving a Fuck\' is OVERDUE.\nDue date was: June 8, 2025\nPenalty amount: 90.00 pesos\n\nPlease return the book as soon as possible and settle the penalty.\n\nThank you for your cooperation.\n', 'Overdue', 1, 0, NULL, '2025-06-09 18:58:09'),
(57, 5, 6, 'Dear Miguelito Bacho,\n\nThis is a reminder that the book \'The Subtle Art of Not Giving a Fuck\' is overdue by 2 days.\n\nBook details:\n- Title: The Subtle Art of Not Giving a Fuck\n- Author: Mark Manson\n- Due date: June 8, 2025\n- Days overdue: 2\n- Late fee: 90.00 pesos\n\nPlease return the book as soon as possible to avoid additional fees.\n\nThank you,\nCoffee Prince Library', 'Overdue', 1, 0, NULL, '2025-06-09 19:01:14'),
(58, 8, 34, 'Dear Raziel Insigne,\n\nYou have returned the book \'The Subtle Art of Not Giving a Fuck\'.\nThank you for using Coffee Prince Library!\n', '', 1, 1, NULL, '2025-06-09 19:03:18'),
(59, 7, 42, 'Dear Nicole V. Cretecio,\n\nYou have borrowed the book \'Everything is Fucked\' from Coffee Prince Library.\nDue date: June 23, 2025\nPayment amount: 50.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-09 21:48:49'),
(60, 5, 43, 'Dear Miguelito Bacho,\n\nYou have borrowed the book \'Our Tribe\' from Coffee Prince Library.\nDue date: June 23, 2025\nPayment amount: 50.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 1, NULL, '2025-06-09 21:50:28'),
(61, 5, 43, 'Dear Miguelito Bacho,\n\nYou have returned the book \'Our Tribe\'.\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-09 21:56:32'),
(62, 7, 42, 'Dear Nicole V. Cretecio,\n\nWe regret to inform you that a penalty has been applied for the book \'Everything is Fucked\'.\n\nThe book was returned in damaged condition.\nPenalty fee: 1,500.00 pesos\n\nPlease pay this amount to the librarian at your earliest convenience.\n\nThank you for your understanding.\nCoffee Prince Library', 'Book Damaged Penalty', 1, 1, NULL, '2025-06-09 21:57:43'),
(63, 9, 44, 'Dear Ariel Cupta,\n\nYou have borrowed the book \'The Subtle Art of Not Giving a Fuck\' from Coffee Prince Library.\nDue date: June 13, 2025\nPayment amount: 30.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-10 13:30:05'),
(64, 9, 45, 'Dear Ariel Cupta,\n\nYou have borrowed the book \'Malice\' from Coffee Prince Library.\nDue date: June 24, 2025\nPayment amount: 50.00 pesos\n\nThank you for using Coffee Prince Library!\n', '', 1, 0, NULL, '2025-06-10 13:34:48'),
(65, 9, 44, 'Dear Ariel Cupta,\n\nWe regret to inform you that a penalty has been applied for the book \'The Subtle Art of Not Giving a Fuck\'.\n\nThe book was reported as lost.\nPenalty fee: 2,000.00 pesos\n\nPlease pay this amount to the librarian at your earliest convenience.\n\nThank you for your understanding.\nCoffee Prince Library', 'Book Lost Penalty', 1, 0, NULL, '2025-06-10 13:47:23');

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `otp_code` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `borrow_date` datetime NOT NULL,
  `due_date` datetime NOT NULL,
  `return_date` datetime DEFAULT NULL,
  `status` enum('Borrowed','Returned','Overdue','Damaged','Lost') DEFAULT 'Borrowed',
  `payment_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('Pending','Paid','Overdue Fee Pending','Overdue Fee Paid','Penalty Fee Pending','Penalty Fee Paid') DEFAULT 'Pending',
  `penalty_amount` decimal(10,2) DEFAULT 0.00,
  `penalty_type` enum('late','damaged','lost') DEFAULT NULL,
  `last_reminder` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `book_id`, `member_id`, `borrow_date`, `due_date`, `return_date`, `status`, `payment_amount`, `payment_status`, `penalty_amount`, `penalty_type`, `last_reminder`) VALUES
(5, 4, 5, '2025-06-05 13:24:36', '2025-06-19 00:00:00', '2025-06-05 13:25:43', 'Returned', 50.00, 'Paid', 0.00, NULL, NULL),
(6, 4, 5, '2025-06-05 13:51:01', '2025-06-08 00:00:00', NULL, 'Overdue', 30.00, 'Pending', 0.00, NULL, NULL),
(9, 4, 5, '2025-06-05 14:53:05', '2025-06-10 00:00:00', NULL, 'Borrowed', 30.00, 'Pending', 0.00, NULL, NULL),
(20, 5, 5, '2025-06-05 17:08:07', '2025-06-12 00:00:00', '2025-06-05 17:09:33', 'Returned', 50.00, 'Paid', 0.00, NULL, NULL),
(28, 4, 9, '2025-06-05 23:31:51', '2025-06-19 00:00:00', NULL, 'Borrowed', 50.00, 'Paid', 0.00, NULL, NULL),
(29, 4, 11, '2025-06-05 23:32:40', '2025-06-19 00:00:00', NULL, 'Borrowed', 50.00, 'Paid', 0.00, NULL, NULL),
(30, 4, 12, '2025-06-05 23:33:13', '2025-06-08 00:00:00', NULL, 'Overdue', 30.00, 'Paid', 0.00, NULL, NULL),
(34, 4, 8, '2025-06-05 23:35:14', '2025-06-10 00:00:00', '2025-06-10 03:03:16', 'Returned', 30.00, 'Paid', 0.00, NULL, NULL),
(35, 4, 10, '2025-06-05 23:35:39', '2025-06-19 00:00:00', '2025-06-10 02:49:26', 'Returned', 50.00, 'Paid', 0.00, NULL, NULL),
(36, 5, 7, '2025-06-06 14:43:17', '2025-06-13 00:00:00', '2025-06-06 14:46:08', 'Returned', 50.00, 'Paid', 0.00, NULL, NULL),
(37, 5, 12, '2025-06-06 15:22:58', '2025-06-20 00:00:00', '2025-06-10 02:49:18', 'Returned', 50.00, 'Paid', 0.00, NULL, NULL),
(38, 5, 7, '2025-06-10 05:23:33', '2025-06-23 00:00:00', '2025-06-10 05:27:19', 'Returned', 50.00, 'Paid', 0.00, NULL, NULL),
(39, 4, 7, '2025-06-10 05:23:39', '2025-06-23 00:00:00', NULL, 'Borrowed', 50.00, 'Paid', 0.00, NULL, NULL),
(40, 4, 12, '2025-06-10 05:40:27', '2025-06-23 00:00:00', NULL, 'Borrowed', 50.00, 'Paid', 0.00, NULL, NULL),
(41, 5, 7, '2025-06-10 05:43:38', '2025-06-10 00:00:00', NULL, 'Borrowed', 10.00, 'Paid', 0.00, NULL, NULL),
(42, 5, 7, '2025-06-10 05:48:37', '2025-06-23 00:00:00', '2025-06-10 05:57:43', 'Returned', 50.00, 'Penalty Fee Pending', 1500.00, 'damaged', NULL),
(43, 8, 5, '2025-06-10 05:50:14', '2025-06-23 00:00:00', '2025-06-10 05:56:29', 'Returned', 50.00, 'Paid', 0.00, NULL, NULL),
(44, 4, 9, '2025-06-10 21:29:57', '2025-06-13 00:00:00', '2025-06-10 21:47:23', 'Returned', 30.00, 'Penalty Fee Pending', 2000.00, 'lost', NULL),
(45, 6, 9, '2025-06-10 21:34:39', '2025-06-24 00:00:00', NULL, 'Borrowed', 50.00, 'Paid', 0.00, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `member_id` (`member_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
