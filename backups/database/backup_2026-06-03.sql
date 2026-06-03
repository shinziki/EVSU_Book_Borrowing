-- EVSU Book Borrowing System — Database Backup
-- Database: coffee_prince_library
-- Generated: 2026-06-03 07:41:23 PST

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `profile_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admins` (`id`, `username`, `password`, `fullname`, `email`, `role`, `status`, `profile_image`) VALUES ('1', 'admin', '$2y$10$luB0oZz0l5COixfnBSxKXOGM8b3fcTWSJ4cn.7k.rZ9jak.p.ms1e', 'Administrator', 'migsbacho04@gmail.com', 'admin', 'active', 'uploads/profile_images/admin_1_1749138198.jpg');
INSERT INTO `admins` (`id`, `username`, `password`, `fullname`, `email`, `role`, `status`, `profile_image`) VALUES ('2', 'staff1', '$2y$10$ECRoCR/w7FGtWbBVGYQbKOsF49i4BJwSRq0tiNoEVQZ9rfAPjcjc.', 'Sherwin Dave Osma', 'sherwinosma1@gmail.com', 'staff', 'active', 'uploads/profile_images/admin_2_1779198546.jpg');

DROP TABLE IF EXISTS `books`;
CREATE TABLE `books` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `author` varchar(100) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `barcode` varchar(50) NOT NULL,
  `status` enum('Available','Borrowed','Overdue','Damaged','Lost','Needs Replacement') DEFAULT 'Available',
  `stock` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cover_image_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `description`, `barcode`, `status`, `stock`, `created_at`, `cover_image_path`) VALUES ('4', 'The Subtle Art of Not Giving a Fuck', 'Mark Manson', '9780062641540', 'Self Improvement', 'A counterintuitive approach to living a good life.', '9780062641540', 'Needs Replacement', '7', '2025-06-05 10:06:11', 'uploads/books/6841b3ddcb493.jpg');
INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `description`, `barcode`, `status`, `stock`, `created_at`, `cover_image_path`) VALUES ('5', 'Everything is Fucked', 'Mark Manson', '9780062888433', 'Self Improvement', 'This Book gave these people Hope.', '9780062888433', 'Overdue', '0', '2025-06-05 16:36:44', 'uploads/books/6841b36e80bfa.png');
INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `description`, `barcode`, `status`, `stock`, `created_at`, `cover_image_path`) VALUES ('6', 'Malice', 'Pintip Dunn', '9781640634121', 'Fiction', 'Taking one life could save millions.', '9781640634121', 'Available', '8', '2025-06-05 23:15:11', 'uploads/books/6841b53e73ab7.jpg');
INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `description`, `barcode`, `status`, `stock`, `created_at`, `cover_image_path`) VALUES ('7', '48 Laws of Power', 'Robert Greene', 'LIB0001', 'Self Improvement', 'If Power is your ultimate goal, this is the book you need The Times.', 'LIB0001', 'Available', '7', '2025-06-05 23:21:36', 'uploads/books/6841b600070f4.png');
INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `description`, `barcode`, `status`, `stock`, `created_at`, `cover_image_path`) VALUES ('8', 'Our Tribe', 'Terry Pluto', '9780684845050', 'History', 'Perhaps the best American writer of sports books.', '9780684845050', 'Available', '10', '2025-06-05 23:23:46', 'uploads/books/6841b682d86ee.jpg');

DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `inactive_since` datetime DEFAULT NULL,
  `barcode` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `photo_path` varchar(255) DEFAULT NULL,
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `membership_type` enum('Regular','Premium') NOT NULL DEFAULT 'Regular',
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `members` (`id`, `fullname`, `email`, `phone`, `address`, `course`, `student_id`, `status`, `inactive_since`, `barcode`, `created_at`, `photo_path`, `notifications_enabled`, `membership_type`) VALUES ('2', 'Sherwin Dave Osma', 'sherwindave.osma@evsu.edu.ph', '09123456789', 'Brgy. Linao', 'BSIT', '2022-31958', 'active', NULL, 'MEM0001', '2026-05-28 19:27:24', 'uploads/members/6a18269cbd7b5.jpg', '1', 'Regular');
INSERT INTO `members` (`id`, `fullname`, `email`, `phone`, `address`, `course`, `student_id`, `status`, `inactive_since`, `barcode`, `created_at`, `photo_path`, `notifications_enabled`, `membership_type`) VALUES ('3', 'Miguelito Bacho', 'miguelito.bacho@evsu.edu.ph', '09123456789', 'Brgy. Tambulilid', 'BSIT', '2023-34167', 'active', NULL, 'MEM0002', '2026-05-28 20:30:30', 'uploads/members/6a183566da4ff.jpg', '1', 'Regular');
INSERT INTO `members` (`id`, `fullname`, `email`, `phone`, `address`, `course`, `student_id`, `status`, `inactive_since`, `barcode`, `created_at`, `photo_path`, `notifications_enabled`, `membership_type`) VALUES ('4', 'Orwell Anthony Barida', 'orwellanthony.barida@evsu.edu.ph', '09123456789', '', 'BSIT', '2023-93721', 'inactive', '2026-06-02 11:26:06', 'MEM0003', '2026-06-02 11:25:33', 'uploads/members/6a1e4d2dea3ff.png', '1', 'Regular');

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `type` varchar(64) NOT NULL DEFAULT 'System',
  `is_sent` tinyint(1) DEFAULT 0,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`),
  KEY `transaction_id` (`transaction_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notifications` (`id`, `member_id`, `transaction_id`, `message`, `type`, `is_sent`, `is_read`, `sent_date`, `created_at`) VALUES ('3', '2', '2', 'Dear Sherwin Dave Osma,\n\nYou have borrowed the book \'Everything is Fucked\' from EVSU Book Borrowing System.\nDue date: May 29, 2026, 7:49 PM\n\nThank you for using EVSU Book Borrowing System!\n', '', '1', '0', NULL, '2026-05-28 19:50:03');
INSERT INTO `notifications` (`id`, `member_id`, `transaction_id`, `message`, `type`, `is_sent`, `is_read`, `sent_date`, `created_at`) VALUES ('4', '3', '3', 'Dear Miguelito Bacho,\n\nYou have borrowed the book \'Everything is Fucked\' from EVSU Book Borrowing System.\nDue date: May 29, 2026, 8:30 PM\n\nThank you for using EVSU Book Borrowing System!\n', '', '1', '0', NULL, '2026-05-28 20:31:01');
INSERT INTO `notifications` (`id`, `member_id`, `transaction_id`, `message`, `type`, `is_sent`, `is_read`, `sent_date`, `created_at`) VALUES ('5', '2', '4', 'Dear Sherwin Dave Osma,\n\nYou have borrowed the book \'Everything is Fucked\' from EVSU Book Borrowing System.\nDue date: May 30, 2026, 4:52 PM\n\nThank you for using EVSU Book Borrowing System!\n', '', '1', '0', NULL, '2026-05-29 16:53:06');
INSERT INTO `notifications` (`id`, `member_id`, `transaction_id`, `message`, `type`, `is_sent`, `is_read`, `sent_date`, `created_at`) VALUES ('6', '2', '5', 'Sherwin Dave Osma borrowed \"Everything is Fucked\".', 'Borrowed', '1', '1', NULL, '2026-05-29 17:03:21');
INSERT INTO `notifications` (`id`, `member_id`, `transaction_id`, `message`, `type`, `is_sent`, `is_read`, `sent_date`, `created_at`) VALUES ('7', '2', '5', 'Sherwin Dave Osma returned \"Everything is Fucked\".', 'Returned', '1', '1', NULL, '2026-05-29 17:03:43');
INSERT INTO `notifications` (`id`, `member_id`, `transaction_id`, `message`, `type`, `is_sent`, `is_read`, `sent_date`, `created_at`) VALUES ('8', '2', '4', 'Sherwin Dave Osma returned \"Everything is Fucked\" (overdue — ₱75.00 penalty).', 'Returned', '1', '1', NULL, '2026-06-02 11:26:14');
INSERT INTO `notifications` (`id`, `member_id`, `transaction_id`, `message`, `type`, `is_sent`, `is_read`, `sent_date`, `created_at`) VALUES ('9', '3', '6', 'Miguelito Bacho borrowed \"Everything is Fucked\".', 'Borrowed', '1', '0', NULL, '2026-06-02 11:27:53');
INSERT INTO `notifications` (`id`, `member_id`, `transaction_id`, `message`, `type`, `is_sent`, `is_read`, `sent_date`, `created_at`) VALUES ('10', '3', '7', 'Miguelito Bacho borrowed \"Everything is Fucked\".', 'Borrowed', '1', '0', NULL, '2026-06-02 11:28:20');
INSERT INTO `notifications` (`id`, `member_id`, `transaction_id`, `message`, `type`, `is_sent`, `is_read`, `sent_date`, `created_at`) VALUES ('11', '3', '8', 'Miguelito Bacho borrowed \"Malice\".', 'Borrowed', '1', '0', NULL, '2026-06-02 11:29:57');

DROP TABLE IF EXISTS `staff_permissions`;
CREATE TABLE `staff_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `permission_key` varchar(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_permission` (`admin_id`,`permission_key`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('79', '2', 'books.view');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('83', '2', 'borrow.process');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('78', '2', 'dashboard.view');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('81', '2', 'members.add');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('82', '2', 'members.edit');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('80', '2', 'members.view');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('94', '2', 'notifications.send');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('93', '2', 'notifications.view');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('92', '2', 'overdue.view');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('91', '2', 'penalties.mark_paid');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('90', '2', 'penalties.record');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('89', '2', 'penalties.view');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('84', '2', 'return.process');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('95', '2', 'settings.profile');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('87', '2', 'transactions.delete');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('88', '2', 'transactions.delete_all');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('85', '2', 'transactions.view');
INSERT INTO `staff_permissions` (`id`, `admin_id`, `permission_key`) VALUES ('86', '2', 'transactions.view_details');

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `borrow_date` datetime NOT NULL,
  `due_date` datetime NOT NULL,
  `return_date` datetime DEFAULT NULL,
  `status` enum('Borrowed','Returned','Overdue','Damaged','Lost','Needs Replacement') DEFAULT 'Borrowed',
  `payment_amount` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('Pending','Paid','Overdue Fee Pending','Overdue Fee Paid','Penalty Fee Pending','Penalty Fee Paid') DEFAULT 'Pending',
  `penalty_amount` decimal(10,2) DEFAULT 0.00,
  `penalty_type` enum('late','damaged','lost') DEFAULT NULL,
  `last_reminder` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `book_id` (`book_id`),
  KEY `member_id` (`member_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `transactions` (`id`, `book_id`, `member_id`, `borrow_date`, `due_date`, `return_date`, `status`, `payment_amount`, `payment_status`, `penalty_amount`, `penalty_type`, `last_reminder`) VALUES ('2', '5', '2', '2026-05-28 19:49:55', '2026-05-29 19:49:55', NULL, 'Overdue', '0.00', 'Paid', '0.00', NULL, NULL);
INSERT INTO `transactions` (`id`, `book_id`, `member_id`, `borrow_date`, `due_date`, `return_date`, `status`, `payment_amount`, `payment_status`, `penalty_amount`, `penalty_type`, `last_reminder`) VALUES ('3', '5', '3', '2026-05-28 20:30:52', '2026-05-29 20:30:52', '2026-05-28 21:55:24', 'Returned', '0.00', 'Paid', '0.00', NULL, NULL);
INSERT INTO `transactions` (`id`, `book_id`, `member_id`, `borrow_date`, `due_date`, `return_date`, `status`, `payment_amount`, `payment_status`, `penalty_amount`, `penalty_type`, `last_reminder`) VALUES ('4', '5', '2', '2026-05-29 16:52:59', '2026-05-30 16:52:59', '2026-06-02 11:26:14', 'Returned', '0.00', '', '75.00', NULL, NULL);
INSERT INTO `transactions` (`id`, `book_id`, `member_id`, `borrow_date`, `due_date`, `return_date`, `status`, `payment_amount`, `payment_status`, `penalty_amount`, `penalty_type`, `last_reminder`) VALUES ('5', '5', '2', '2026-05-29 17:03:21', '2026-05-30 17:03:21', '2026-05-29 17:03:43', 'Returned', '0.00', 'Paid', '0.00', NULL, NULL);
INSERT INTO `transactions` (`id`, `book_id`, `member_id`, `borrow_date`, `due_date`, `return_date`, `status`, `payment_amount`, `payment_status`, `penalty_amount`, `penalty_type`, `last_reminder`) VALUES ('6', '5', '3', '2026-06-02 11:27:53', '2026-06-03 11:27:53', NULL, 'Borrowed', '0.00', 'Paid', '0.00', NULL, NULL);
INSERT INTO `transactions` (`id`, `book_id`, `member_id`, `borrow_date`, `due_date`, `return_date`, `status`, `payment_amount`, `payment_status`, `penalty_amount`, `penalty_type`, `last_reminder`) VALUES ('7', '5', '3', '2026-06-02 11:28:20', '2026-06-03 11:28:20', NULL, 'Borrowed', '0.00', 'Paid', '0.00', NULL, NULL);
INSERT INTO `transactions` (`id`, `book_id`, `member_id`, `borrow_date`, `due_date`, `return_date`, `status`, `payment_amount`, `payment_status`, `penalty_amount`, `penalty_type`, `last_reminder`) VALUES ('8', '6', '3', '2026-06-02 11:29:57', '2026-06-03 11:29:57', NULL, 'Borrowed', '0.00', 'Paid', '0.00', NULL, NULL);

SET FOREIGN_KEY_CHECKS=1;
