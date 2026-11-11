-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 11, 2026 at 01:47 PM
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
-- Database: `product_catalog`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

DROP TABLE IF EXISTS `cart_items`;
CREATE TABLE `cart_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` VALUES(23, 56, 21, 1, '2026-11-09 01:10:47');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` VALUES(3, 'Bedding');
INSERT INTO `categories` VALUES(23, 'Blocks');
INSERT INTO `categories` VALUES(18, 'Fixtures');
INSERT INTO `categories` VALUES(8, 'Furniture');
INSERT INTO `categories` VALUES(10, 'Glass');
INSERT INTO `categories` VALUES(5, 'Lighting');
INSERT INTO `categories` VALUES(4, 'Paints');
INSERT INTO `categories` VALUES(11, 'Roof');
INSERT INTO `categories` VALUES(6, 'Tiles');
INSERT INTO `categories` VALUES(9, 'Windows');

-- --------------------------------------------------------

--
-- Table structure for table `custom_roles`
--

DROP TABLE IF EXISTS `custom_roles`;
CREATE TABLE `custom_roles` (
  `role_key` varchar(30) NOT NULL,
  `label` varchar(40) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `custom_roles`
--

INSERT INTO `custom_roles` VALUES('kapatid_ng_ceo', 'Kapatid ng CEO', '$2y$10$2aNKQIgLqtnMd64V0sMftOpByL.EUB/MDWAOs79FfdR4a9cF39F7m', '2026-11-09 05:20:25');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `email` varchar(254) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_logs`
--

DROP TABLE IF EXISTS `login_logs`;
CREATE TABLE `login_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(254) NOT NULL,
  `role` varchar(10) NOT NULL DEFAULT 'user',
  `staff_role` varchar(30) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `login_at` datetime NOT NULL,
  `last_seen` datetime NOT NULL,
  `logout_at` datetime DEFAULT NULL,
  `end_reason` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_logs`
--

INSERT INTO `login_logs` VALUES(1, 56, 'Jane Araya', 'jane@gmail.com', 'user', NULL, '172.20.10.3', '2026-11-09 12:48:34', '2026-11-09 12:49:18', '2026-11-09 12:49:18', 'signed_out');
INSERT INTO `login_logs` VALUES(2, 55, 'Sebastian Araya', 'seb@gmail.com', 'admin', NULL, '172.20.10.3', '2026-11-09 12:49:38', '2026-11-09 12:51:29', '2026-11-09 12:51:29', 'signed_out');
INSERT INTO `login_logs` VALUES(3, 56, 'Jane Araya', 'jane@gmail.com', 'user', NULL, '172.20.10.3', '2026-11-09 12:51:48', '2026-11-09 12:57:29', '2026-11-09 12:57:29', 'signed_out');
INSERT INTO `login_logs` VALUES(4, 57, 'Felipe', 'felipe@gmail.com', 'staff', 'manager', '172.20.10.3', '2026-11-09 12:57:50', '2026-11-09 12:59:19', '2026-11-09 12:59:19', 'signed_out');
INSERT INTO `login_logs` VALUES(5, 57, 'Felipe', 'felipe@gmail.com', 'staff', 'manager', '172.20.10.3', '2026-11-09 13:00:06', '2026-11-09 13:00:15', '2026-11-09 13:00:15', 'signed_out');
INSERT INTO `login_logs` VALUES(6, 56, 'Jane Araya', 'jane@gmail.com', 'user', NULL, '172.20.10.3', '2026-11-09 13:00:25', '2026-11-09 13:04:18', '2026-11-09 13:04:18', 'signed_out');
INSERT INTO `login_logs` VALUES(7, 57, 'Felipe', 'felipe@gmail.com', 'staff', 'manager', '172.20.10.3', '2026-11-09 13:04:41', '2026-11-09 13:08:59', '2026-11-09 13:08:59', 'signed_out');
INSERT INTO `login_logs` VALUES(8, 55, 'Sebastian Araya', 'seb@gmail.com', 'admin', NULL, '172.20.10.3', '2026-11-09 13:09:23', '2026-11-09 13:24:02', '2026-11-09 13:24:02', 'signed_out');
INSERT INTO `login_logs` VALUES(9, 59, 'Maymay', 'maymay@gmail.com', 'staff', 'kapatid_ng_ceo', '172.20.10.3', '2026-11-09 13:31:43', '2026-11-09 13:32:20', '2026-11-09 13:32:20', 'signed_out');
INSERT INTO `login_logs` VALUES(10, 57, 'Felipe', 'felipe@gmail.com', 'staff', 'manager', '172.20.10.3', '2026-11-09 13:32:46', '2026-11-09 13:32:46', NULL, NULL);
INSERT INTO `login_logs` VALUES(11, 56, 'Jane Araya', 'jane@gmail.com', 'user', NULL, '172.20.10.3', '2026-11-10 11:22:31', '2026-11-10 11:27:14', NULL, NULL);
INSERT INTO `login_logs` VALUES(12, 56, 'Jane Araya', 'jane@gmail.com', 'user', NULL, '172.20.10.3', '2026-11-11 20:29:53', '2026-11-11 20:30:57', '2026-11-11 20:30:57', 'signed_out');
INSERT INTO `login_logs` VALUES(13, 55, 'Sebastian Araya', 'seb@gmail.com', 'admin', NULL, '172.20.10.3', '2026-11-11 20:32:25', '2026-11-11 20:35:38', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(40) NOT NULL,
  `message` varchar(500) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` VALUES(21, 55, 'order_new', 'New order BQ-261107-0004 from Jane Araya (₱86,334.60). Please accept or decline it.', 'index.php?page=orders&status=pending', 0, '2026-11-07 13:57:34');
INSERT INTO `notifications` VALUES(22, 56, 'order_placed', 'Your order BQ-261107-0004 has been placed. Wait for the admin\'s response.', 'index.php?page=my_orders', 1, '2026-11-07 13:57:34');
INSERT INTO `notifications` VALUES(23, 55, 'feedback_new', 'Jane Araya sent feedback about \"jaswdchuecbhbjavef\".', 'index.php?page=inquiries', 0, '2026-11-07 14:04:22');
INSERT INTO `notifications` VALUES(24, 56, 'order_accepted', 'The admin is preparing your order. (Order BQ-261107-0004)', 'index.php?page=my_orders', 1, '2026-11-07 14:08:36');
INSERT INTO `notifications` VALUES(25, 56, 'order_shipping', 'Your order is on shipping. (Order BQ-261107-0004)', 'index.php?page=my_orders', 1, '2026-11-07 14:08:42');
INSERT INTO `notifications` VALUES(26, 56, 'feedback_reply', 'The admin replied to your feedback about \"jaswdchuecbhbjavef\".', 'index.php?page=my_feedback', 1, '2026-11-07 14:08:55');
INSERT INTO `notifications` VALUES(27, 55, 'order_new', 'New order BQ-261107-0005 from Jane Araya (₱2,369.00). Please accept or decline it.', 'index.php?page=orders&status=pending', 0, '2026-11-07 14:24:15');
INSERT INTO `notifications` VALUES(28, 57, 'order_new', 'New order BQ-261107-0005 from Jane Araya (₱2,369.00). Please accept or decline it.', 'index.php?page=orders&status=pending', 1, '2026-11-07 14:24:15');
INSERT INTO `notifications` VALUES(29, 56, 'order_placed', 'Your order BQ-261107-0005 has been placed. Wait for the admin\'s response.', 'index.php?page=my_orders', 1, '2026-11-07 14:24:15');
INSERT INTO `notifications` VALUES(30, 55, 'order_new', 'New order BQ-261107-0006 from Jane Araya (₱92,563,801.04). Please accept or decline it.', 'index.php?page=orders&status=pending', 0, '2026-11-07 14:26:10');
INSERT INTO `notifications` VALUES(31, 57, 'order_new', 'New order BQ-261107-0006 from Jane Araya (₱92,563,801.04). Please accept or decline it.', 'index.php?page=orders&status=pending', 1, '2026-11-07 14:26:10');
INSERT INTO `notifications` VALUES(32, 56, 'order_placed', 'Your order BQ-261107-0006 has been placed. Wait for the admin\'s response.', 'index.php?page=my_orders', 1, '2026-11-07 14:26:10');
INSERT INTO `notifications` VALUES(33, 55, 'order_new', 'New order BQ-261107-0007 from Jane Araya (₱6,386.00). Please accept or decline it.', 'index.php?page=orders&status=pending', 0, '2026-11-07 14:33:34');
INSERT INTO `notifications` VALUES(34, 57, 'order_new', 'New order BQ-261107-0007 from Jane Araya (₱6,386.00). Please accept or decline it.', 'index.php?page=orders&status=pending', 1, '2026-11-07 14:33:34');
INSERT INTO `notifications` VALUES(35, 56, 'order_placed', 'Your order BQ-261107-0007 has been placed. Wait for the admin\'s response.', 'index.php?page=my_orders', 1, '2026-11-07 14:33:34');
INSERT INTO `notifications` VALUES(36, 56, 'order_accepted', 'The admin is preparing your order. (Order BQ-261107-0007)', 'index.php?page=my_orders', 1, '2026-11-07 14:35:22');
INSERT INTO `notifications` VALUES(37, 55, 'order_new', 'New order BQ-261109-0008 from Jane Araya (₱2,457.35, GCash). Please accept or decline it.', 'index.php?page=orders&status=pending', 1, '2026-11-09 01:11:59');
INSERT INTO `notifications` VALUES(38, 57, 'order_new', 'New order BQ-261109-0008 from Jane Araya (₱2,457.35, GCash). Please accept or decline it.', 'index.php?page=orders&status=pending', 1, '2026-11-09 01:11:59');
INSERT INTO `notifications` VALUES(39, 56, 'order_placed', 'Your order BQ-261109-0008 has been placed. Wait for the admin\'s response.', 'index.php?page=my_orders', 1, '2026-11-09 01:11:59');
INSERT INTO `notifications` VALUES(40, 56, 'order_accepted', 'The admin is preparing your order. (Order BQ-261109-0008)', 'index.php?page=my_orders', 1, '2026-11-09 01:14:11');
INSERT INTO `notifications` VALUES(41, 56, 'order_declined', 'Your order has been declined due to some reasons. Please try again. (Order BQ-261107-0006)', 'index.php?page=my_orders', 1, '2026-11-09 01:23:17');
INSERT INTO `notifications` VALUES(42, 56, 'order_declined', 'Your order has been declined due to some reasons. Please try again. (Order BQ-261107-0005)', 'index.php?page=my_orders', 1, '2026-11-09 01:24:22');
INSERT INTO `notifications` VALUES(43, 56, 'order_shipping', 'Your order is on shipping. (Order BQ-261109-0008)', 'index.php?page=my_orders', 1, '2026-11-09 01:24:35');
INSERT INTO `notifications` VALUES(44, 56, 'order_completed', 'Your order has been completed. Thank you for shopping with BuildQuin! (Order BQ-261109-0008)', 'index.php?page=my_orders', 1, '2026-11-09 04:50:50');
INSERT INTO `notifications` VALUES(45, 56, 'order_shipping', 'Your order is on shipping. (Order BQ-261107-0007)', 'index.php?page=my_orders', 1, '2026-11-09 04:51:00');
INSERT INTO `notifications` VALUES(46, 56, 'order_completed', 'Your order has been completed. Thank you for shopping with BuildQuin! (Order BQ-261107-0007)', 'index.php?page=my_orders', 1, '2026-11-09 04:51:04');
INSERT INTO `notifications` VALUES(47, 56, 'order_completed', 'Your order has been completed. Thank you for shopping with BuildQuin! (Order BQ-261107-0004)', 'index.php?page=my_orders', 1, '2026-11-09 04:51:12');
INSERT INTO `notifications` VALUES(48, 55, 'feedback_new', 'Jane Araya rated order BQ-261109-0008 5/5.', 'index.php?page=inquiries', 1, '2026-11-09 04:54:33');
INSERT INTO `notifications` VALUES(49, 57, 'feedback_new', 'Jane Araya rated order BQ-261109-0008 5/5.', 'index.php?page=inquiries', 1, '2026-11-09 04:54:33');
INSERT INTO `notifications` VALUES(50, 56, 'feedback_reply', 'The admin replied to your review of order BQ-261109-0008.', 'index.php?page=my_feedback', 1, '2026-11-09 04:58:16');
INSERT INTO `notifications` VALUES(51, 55, 'feedback_new', 'Jane Araya rated order BQ-261107-0004 5/5.', 'index.php?page=inquiries', 0, '2026-11-11 12:30:33');
INSERT INTO `notifications` VALUES(52, 57, 'feedback_new', 'Jane Araya rated order BQ-261107-0004 5/5.', 'index.php?page=inquiries', 0, '2026-11-11 12:30:33');
INSERT INTO `notifications` VALUES(53, 59, 'feedback_new', 'Jane Araya rated order BQ-261107-0004 5/5.', 'index.php?page=inquiries', 0, '2026-11-11 12:30:33');
INSERT INTO `notifications` VALUES(54, 56, 'feedback_reply', 'The admin replied to your review of order BQ-261107-0004.', 'index.php?page=my_feedback', 0, '2026-11-11 12:32:55');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `contact_number` varchar(30) NOT NULL,
  `shipping_address` varchar(600) NOT NULL,
  `payment_method` varchar(40) NOT NULL DEFAULT 'Cash on Delivery',
  `notes` varchar(500) DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  `vat_rate` decimal(5,2) NOT NULL DEFAULT 3.00,
  `vat_amount` decimal(12,2) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `status` enum('pending','accepted','shipping','completed','declined','cancelled') NOT NULL DEFAULT 'pending',
  `decline_reason` varchar(500) DEFAULT NULL,
  `handled_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` VALUES(1, 'BQ-261106-0001', NULL, 'Lovely Jane Araya', '09667628651', 'Purok 4, Brgy. Cabs, Manito, Albay 4514', 'Cash on Delivery', 'kyutieee', 89881966.00, 3.00, 2696458.98, 92578424.98, 'completed', NULL, NULL, '2026-11-06 03:56:51', '2026-11-06 04:01:33');
INSERT INTO `orders` VALUES(2, 'BQ-261106-0002', NULL, 'Lovely Jane Araya', '09667628651', 'Purok 4, Brgy. Cabs, Manito, Albay 4514', 'Cash on Delivery', NULL, 89870067.00, 3.00, 2696102.01, 92566169.01, 'declined', NULL, NULL, '2026-11-06 05:05:39', '2026-11-06 05:06:53');
INSERT INTO `orders` VALUES(3, 'BQ-261107-0003', NULL, 'Adrian Arcabas', '09667628651', 'hcfhfhfh, Brgy. vshqbhg, ghrwn, rthrth rth', 'Cash on Delivery', NULL, 269638399.00, 3.00, 8089151.97, 277727550.97, 'completed', NULL, NULL, '2026-11-07 06:26:13', '2026-11-07 06:44:27');
INSERT INTO `orders` VALUES(4, 'BQ-261107-0004', 56, 'Jane Araya', '09667628651', 'Purok 4, Brgy. Cabacongan, Manito, Albay 4514', 'Cash on Delivery', 'pwede po paki deliver ng maayos para iwas damage. thank u', 83820.00, 3.00, 2514.60, 86334.60, 'completed', NULL, 55, '2026-11-07 13:57:34', '2026-11-09 04:51:12');
INSERT INTO `orders` VALUES(5, 'BQ-261107-0005', 56, 'Jane Araya', '09667628651', 'Purok 4, Brgy. Cabacongan, Manito, Albay 4514', 'Cash on Delivery', NULL, 2300.00, 3.00, 69.00, 2369.00, 'declined', 'i am sorry but metal roof is out of stock. thank u for understanding', 55, '2026-11-07 14:24:15', '2026-11-09 01:24:22');
INSERT INTO `orders` VALUES(6, 'BQ-261107-0006', 56, 'Jane Araya', '09667628651', 'Purok 4, Brgy. Cabacongan, Manito, Albay 4514', 'Cash on Delivery', NULL, 89867768.00, 3.00, 2696033.04, 92563801.04, 'declined', NULL, 55, '2026-11-07 14:26:10', '2026-11-09 01:23:17');
INSERT INTO `orders` VALUES(7, 'BQ-261107-0007', 56, 'Jane Araya', '09667628651', 'Purok 4, Brgy. Cabacongan, Manito, Albay 4514', 'Cash on Delivery', NULL, 6200.00, 3.00, 186.00, 6386.00, 'completed', NULL, 55, '2026-11-07 14:33:34', '2026-11-09 04:51:04');
INSERT INTO `orders` VALUES(8, 'BQ-261109-0008', 56, 'Jane Araya', '09667628651', 'Purok 4, Brgy. Cabacongan, Manito, Albay 4514', 'GCash', NULL, 2450.00, 0.30, 7.35, 2457.35, 'completed', NULL, 55, '2026-11-09 01:11:59', '2026-11-09 04:50:50');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `line_total` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` VALUES(1, 1, NULL, 'PT-SH-01', 'jaswdchuecbhbjavef', 89865466.00, 1, 89865466.00);
INSERT INTO `order_items` VALUES(2, 1, NULL, 'BQ-FIX-02', 'Matte Black Thermostatic Shower System', 16500.00, 1, 16500.00);
INSERT INTO `order_items` VALUES(3, 2, NULL, 'BQ-ROOF-02', 'Metal Roof', 2300.00, 2, 4600.00);
INSERT INTO `order_items` VALUES(4, 2, NULL, 'PT-SH-01', 'jaswdchuecbhbjavef', 89865466.00, 1, 89865466.00);
INSERT INTO `order_items` VALUES(5, 2, NULL, 'BQ-FIX-03', 'Freestanding Oval Acrylic Soaking Tub', 1.00, 1, 1.00);
INSERT INTO `order_items` VALUES(6, 3, NULL, 'PT-SH-01', 'jaswdchuecbhbjavef', 89865466.00, 3, 269596398.00);
INSERT INTO `order_items` VALUES(7, 3, NULL, 'BQ-BDG-07', 'Boho Tufted Quilted Bedspread', 6700.00, 1, 6700.00);
INSERT INTO `order_items` VALUES(8, 3, NULL, 'BQ-FIX-02', 'Matte Black Thermostatic Shower System', 16500.00, 2, 33000.00);
INSERT INTO `order_items` VALUES(9, 3, NULL, 'BQ-FIX-03', 'Freestanding Oval Acrylic Soaking Tub', 1.00, 1, 1.00);
INSERT INTO `order_items` VALUES(10, 3, NULL, 'BQ-ROOF-02', 'Metal Roof', 2300.00, 1, 2300.00);
INSERT INTO `order_items` VALUES(11, 4, NULL, 'BQ-ROOF-02', 'Metal Roof', 2300.00, 1, 2300.00);
INSERT INTO `order_items` VALUES(12, 4, NULL, 'BQ-GLS-07', 'Curved Architectural Glass Section', 24000.00, 2, 48000.00);
INSERT INTO `order_items` VALUES(13, 4, NULL, 'BQ-WND-05', 'Awning Ventilation Window Unit', 8500.00, 1, 8500.00);
INSERT INTO `order_items` VALUES(14, 4, 22, 'BQ-FRN-01', 'Bouclé Curved Accent Armchair', 24500.00, 1, 24500.00);
INSERT INTO `order_items` VALUES(15, 4, NULL, 'BQ-TLS-06', 'Terrazzo Multi-Flake Slab Tile', 520.00, 1, 520.00);
INSERT INTO `order_items` VALUES(16, 5, NULL, 'BQ-ROOF-02', 'Metal Roof', 2300.00, 1, 2300.00);
INSERT INTO `order_items` VALUES(17, 6, NULL, 'BQ-ROOF-02', 'Metal Roof', 2300.00, 1, 2300.00);
INSERT INTO `order_items` VALUES(18, 6, NULL, 'PT-SH-01', 'jaswdchuecbhbjavef', 89865466.00, 1, 89865466.00);
INSERT INTO `order_items` VALUES(19, 6, NULL, 'BQ-FIX-03', 'Freestanding Oval Acrylic Soaking Tub', 1.00, 2, 2.00);
INSERT INTO `order_items` VALUES(20, 7, 36, 'BQ-GLS-01', 'Fluted Reeded Decorative Glass Sheet', 6200.00, 1, 6200.00);
INSERT INTO `order_items` VALUES(21, 8, 1, 'BQ-PNT-01', 'Velvet Matte Interior Paint', 2450.00, 1, 2450.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_reviews`
--

DROP TABLE IF EXISTS `order_reviews`;
CREATE TABLE `order_reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `message` text DEFAULT NULL,
  `photos` varchar(1000) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `responded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_reviews`
--

INSERT INTO `order_reviews` VALUES(1, 8, 56, 5, 'okay naman. highly recommended', 'photos/review_b977fb5329b3b049.jpg|photos/review_8cbc280aea47d803.jpg', 'wow, sana all', 57, '2026-11-09 04:54:33', '2026-11-09 04:58:16');
INSERT INTO `order_reviews` VALUES(2, 4, 56, 5, 'all goods insan', 'photos/review_1af6fdc755d25784.jpg', 'sanaol naka bakugo', 55, '2026-11-11 12:30:33', '2026-11-11 12:32:55');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 10,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?q=80&w=600',
  `img_interior` varchar(500) DEFAULT NULL,
  `img_exterior` varchar(500) DEFAULT NULL,
  `img_accent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` VALUES(1, 'BQ-PNT-01', 'Velvet Matte Interior Paint', 4, 2450.00, 2, 'Premium ultra-flat finish for accent walls and bedrooms.', 'https://images.unsplash.com/photo-1562259949-e8e7689d7828?q=80&w=600', '', '', '', '2026-09-03 11:31:51');
INSERT INTO `products` VALUES(21, 'BQ-TLS-07', 'Hexagon Matte Black Mosaic', 6, 180.00, 300, 'Sheet-mounted mini hexagonal wall and backsplash tile.', 'https://images.unsplash.com/photo-1581858726788-75bc0f6a952d?q=80&w=600', NULL, NULL, NULL, '2026-09-03 11:31:51');
INSERT INTO `products` VALUES(22, 'BQ-FRN-01', 'Bouclé Curved Accent Armchair', 8, 24500.00, 3, 'Textured cream bouclé fabric chair with oak legs.', 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?q=80&w=600', NULL, NULL, NULL, '2026-09-03 11:31:51');
INSERT INTO `products` VALUES(35, 'BQ-WND-07', 'Motorized Skylight Roof Window', 9, 29000.00, 6, 'Remote control rain-sensing roof glass window.', 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?q=80&w=600', NULL, NULL, NULL, '2026-09-03 11:31:51');
INSERT INTO `products` VALUES(36, 'BQ-GLS-01', 'Fluted Reeded Decorative Glass Sheet', 10, 6200.00, 10, 'Textured privacy glass panel for doors and partitions.', 'https://images.unsplash.com/photo-1509644851169-2acc08aa25b5?q=80&w=600', '', '', '', '2026-09-03 11:31:51');
INSERT INTO `products` VALUES(50, 'BQ-FIX-01', 'Brushed Gold Waterfall Basin Faucet', 18, 6800.00, 5, 'Deck-mounted brass bathroom tap with ceramic valve.', 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?q=80&w=600', '', '', '', '2026-09-03 11:31:51');

-- --------------------------------------------------------

--
-- Table structure for table `product_feedback`
--

DROP TABLE IF EXISTS `product_feedback`;
CREATE TABLE `product_feedback` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `response` text DEFAULT NULL,
  `responded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(254) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `staff_role` varchar(30) DEFAULT NULL,
  `security_password_hash` varchar(255) DEFAULT NULL,
  `account_status` varchar(12) NOT NULL DEFAULT 'active',
  `suspended_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` VALUES(55, 'Sebastian Araya', 'seb@gmail.com', '$2y$10$WQ.38OqJRQwuVYtiAvlLveaKF1C2IYbmPirXvwGYVyhWbsZA09oki', 'admin', '2026-11-07 13:23:38', '2026-11-11 12:32:25', 1, NULL, '$2y$10$eNALIoJQGeObkYlV/f87HuUqX02wQp4GtT1GZ.KRfPgyspDKYxJ72', 'active', NULL);
INSERT INTO `users` VALUES(56, 'Jane Araya', 'jane@gmail.com', '$2y$10$dkp4E4qTTX43NFRCPfbUdOgV6lqF18uZ7IJQ.b/YSAmGCiZgKflPG', 'user', '2026-11-07 13:54:59', '2026-11-11 12:29:53', 1, NULL, NULL, 'active', NULL);
INSERT INTO `users` VALUES(57, 'Felipe', 'felipe@gmail.com', '$2y$10$924m46nv6On0dS3PoooAzeiT1qka0H7EoAhonF1Qte5KU4BnvEQ8S', 'staff', '2026-11-07 14:05:19', '2026-11-09 05:32:46', 1, 'manager', '$2y$10$vkLmzlopXGDuZatvnMFM0.yew5PyS5ClZIp6ZYcdN4gplLgldY0BS', 'active', NULL);
INSERT INTO `users` VALUES(59, 'Maymay', 'maymay@gmail.com', '$2y$10$SvQW1Egw3MCrTQDdjr62b.lvHsXcN4TVmLGqERfApREdbNv4usvza', 'staff', '2026-11-09 05:31:27', '2026-11-09 05:31:43', 1, 'kapatid_ng_ceo', '$2y$10$KbFlsRkmrSNIBTTkJoXbO.SxJZEC0ZEdrkEW3jzb84GxX6PwMWvUS', 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

DROP TABLE IF EXISTS `user_addresses`;
CREATE TABLE `user_addresses` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `label` varchar(50) NOT NULL DEFAULT 'Home',
  `recipient_name` varchar(100) NOT NULL,
  `contact_number` varchar(30) NOT NULL,
  `street_address` varchar(255) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(12) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` VALUES(3, 56, 'Home', 'Jane Araya', '09667628651', 'Purok 4', 'Cabacongan', 'Manito', 'Albay', '4514', 1, '2026-11-07 13:56:59');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cart_user_product` (`user_id`,`product_id`),
  ADD KEY `fk_cart_product` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categories_category_name` (`category_name`);

--
-- Indexes for table `custom_roles`
--
ALTER TABLE `custom_roles`
  ADD PRIMARY KEY (`role_key`),
  ADD UNIQUE KEY `uq_custom_roles_label` (`label`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`email`,`ip_address`);

--
-- Indexes for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_logs_user` (`user_id`),
  ADD KEY `idx_login_logs_time` (`login_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_orders_number` (`order_number`),
  ADD KEY `idx_orders_user` (`user_id`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `fk_orders_handler` (`handled_by`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order` (`order_id`),
  ADD KEY `fk_order_items_product` (`product_id`);

--
-- Indexes for table `order_reviews`
--
ALTER TABLE `order_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review_order` (`order_id`),
  ADD KEY `idx_review_user` (`user_id`),
  ADD KEY `fk_review_responder` (`responded_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_product_code` (`product_code`),
  ADD KEY `idx_products_category_id` (`category_id`);

--
-- Indexes for table `product_feedback`
--
ALTER TABLE `product_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_feedback_product` (`product_id`),
  ADD KEY `idx_feedback_user` (`user_id`),
  ADD KEY `fk_feedback_responder` (`responded_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_addresses_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `login_logs`
--
ALTER TABLE `login_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `order_reviews`
--
ALTER TABLE `order_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `product_feedback`
--
ALTER TABLE `product_feedback`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cart_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_logs`
--
ALTER TABLE `login_logs`
  ADD CONSTRAINT `fk_login_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_reviews`
--
ALTER TABLE `order_reviews`
  ADD CONSTRAINT `fk_review_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_review_responder` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `product_feedback`
--
ALTER TABLE `product_feedback`
  ADD CONSTRAINT `fk_feedback_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_feedback_responder` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
