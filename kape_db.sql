-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 04:55 AM
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
-- Database: `kape_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `logID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`logID`, `userID`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'employee_added', 'Admin added new employee: jane_doe', NULL, NULL, '2025-11-16 22:23:03'),
(2, 1, 'product_add', 'Product: Matcha Cheesecake - Product ID: 1, Category ID: 3, Unit: Ounce, Size: , Price: ₱0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-16 22:32:41'),
(3, 1, 'product_add', 'Product: Biscoff Latte - Product ID: 2, Category ID: 9, Unit: Ounce, Size: , Price: ₱0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-16 22:44:03'),
(4, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-16 22:46:17'),
(5, 3, 'login', 'Username: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-16 22:46:21'),
(6, 3, 'order_completed', 'Order ID: 1, Total: 99', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-16 22:48:33'),
(7, 3, 'logout', 'Logout for user: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-16 22:48:40'),
(8, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-16 22:48:44'),
(9, 1, 'product_add', 'Product: Ube Cheese Cake - Product ID: 3, Category ID: 3, Unit: Ounce, Size: , Price: ₱0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:25:20'),
(10, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:29:09'),
(11, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:29:16'),
(12, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:29:45'),
(13, 3, 'login', 'Username: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:29:50'),
(14, 3, 'order_completed', 'Order ID: 2, Total: 100', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:29:58'),
(15, 3, 'logout', 'Logout for user: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:30:25'),
(16, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:30:29'),
(17, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:33:29'),
(18, 3, 'login', 'Username: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:33:35'),
(19, 3, 'order_completed', 'Order ID: 3, Total: 95', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:33:43'),
(20, 3, 'logout', 'Logout for user: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:33:46'),
(21, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:33:51'),
(22, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:37:18'),
(23, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:37:24'),
(24, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:37:26'),
(25, 3, 'login', 'Username: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:37:32'),
(26, 3, 'order_completed', 'Order ID: 4, Total: 195', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:37:41'),
(27, 3, 'logout', 'Logout for user: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:37:53'),
(28, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:37:57'),
(29, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:39:43'),
(30, 3, 'login', 'Username: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:39:51'),
(31, 3, 'order_completed', 'Order ID: 5, Total: 100', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:40:04'),
(32, 3, 'logout', 'Logout for user: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:40:13'),
(33, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 05:40:17'),
(34, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 06:37:08'),
(35, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 06:37:11'),
(36, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 06:37:19'),
(38, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 06:37:37'),
(39, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 06:37:45'),
(40, 3, 'login', 'Username: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 06:37:48'),
(41, 3, 'logout', 'Logout for user: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 06:37:54'),
(42, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:01:51'),
(43, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:02:25'),
(44, 3, 'login', 'Username: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:02:28'),
(45, 3, 'order_completed', 'Order ID: 6, Total: 95', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:02:35'),
(46, 3, 'logout', 'Logout for user: jane_doe', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:02:43'),
(47, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:02:46'),
(48, 1, 'category_add', 'Category: test - Category ID: 29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:18:09'),
(49, 1, 'category_add', 'Category: test1 - Category ID: 30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:18:57'),
(50, 1, 'product_add', 'Product: test - Product ID: 4, Category ID: 29, Unit: Ounce, Size: , Price: ₱0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:20:13'),
(51, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:22:08'),
(52, 1, 'failed_login', 'Username: admin - Password mismatch', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:22:12'),
(53, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:22:15'),
(54, 1, 'logout', 'Logout for user: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:22:25'),
(55, 1, 'failed_login', 'Username: admin - Password mismatch', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:22:38'),
(56, 1, 'login', 'Username: admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:22:39'),
(57, 1, 'product_add', 'Product: test2 - Product ID: 5, Category ID: 30, Unit: Ounce, Size: 16, Price: ₱0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:28:36'),
(58, 1, 'product_add', 'Product: test3 - Product ID: 6, Category ID: 29, Unit: Ounce, Size: 16, Price: ₱0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:37:31'),
(59, 1, 'product_add', 'Product: test4 - Product ID: 7, Category ID: 30, Unit: Ounce, Size: 16, Price: ₱0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:39:37'),
(60, 1, 'product_add', 'Product: test5 - Product ID: 8, Category ID: 29, Unit: Ounce, Size: 16, Price: ₱0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-17 07:45:23');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `categoryID` int(11) NOT NULL,
  `categoryName` varchar(100) NOT NULL,
  `isActive` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`categoryID`, `categoryName`, `isActive`) VALUES
(1, 'Coffee Based', 1),
(2, 'Non-Coffee', 1),
(3, 'Cheese Cake Series', 1),
(4, 'Berry Series', 1),
(5, 'Ube Series', 1),
(9, 'Special Series', 1),
(29, 'test', 1),
(30, 'test1', 1);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventoryID` int(11) NOT NULL,
  `InventoryName` varchar(150) NOT NULL,
  `Size` varchar(50) NOT NULL,
  `Unit` varchar(50) NOT NULL,
  `Current_Stock` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `Cost_Price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `Total_Value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reorder_point` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `qty_per_order` varchar(50) DEFAULT NULL,
  `Status` enum('In_Stock','Low_Stock','Out_of_Stock','') NOT NULL DEFAULT 'In_Stock'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventoryID`, `InventoryName`, `Size`, `Unit`, `Current_Stock`, `Cost_Price`, `Total_Value`, `reorder_point`, `qty_per_order`, `Status`) VALUES
(1, 'Milk', '1000', 'mL', 5.0000, 80.00, 400.00, 2.0000, '150', 'In_Stock'),
(2, 'Matcha Powder', '1000', 'g', 6.0000, 500.00, 3000.00, 2.0000, '10', 'In_Stock'),
(3, 'Cream Cheese Base', '1000', 'g', 6.0000, 750.00, 4500.00, 2.0000, '20', 'In_Stock'),
(4, 'Condensed Milk', '1000', 'mL', 6.0000, 170.00, 1020.00, 2.0000, '50', 'In_Stock'),
(5, 'Coconut Cream', '1000', 'mL', 6.0000, 140.00, 840.00, 2.0000, '20', 'In_Stock'),
(6, 'Biscoff Spread', '1000', 'g', 4.0000, 800.00, 3200.00, 2.0000, '20', 'In_Stock'),
(7, 'Espresso Beans', '1000', 'g', 8.0000, 234.00, 1872.00, 3.0000, '20', 'In_Stock'),
(8, 'Ice', '1000', 'g', 6.0000, 100.00, 600.00, 2.0000, '20', 'In_Stock'),
(9, '16oz Cup', '50', 'pc', 84.0000, 112.00, 9408.00, 50.0000, '1', 'In_Stock'),
(10, '22oz Cup', '50', 'pc', 89.0000, 123.00, 10947.00, 50.0000, '1', 'In_Stock'),
(11, 'Ube Powder', '1000', 'g', 5.0000, 600.00, 3000.00, 2.0000, '5', 'In_Stock');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `orderID` int(11) NOT NULL,
  `userID` int(11) DEFAULT NULL,
  `paymentMethod` varchar(20) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `totalAmount` decimal(10,2) DEFAULT 0.00,
  `createdAt` datetime DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `orderSummary` text DEFAULT NULL,
  `referenceNumber` varchar(20) DEFAULT NULL,
  `ingredientCost` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`orderID`, `userID`, `paymentMethod`, `status`, `totalAmount`, `createdAt`, `completed_at`, `orderSummary`, `referenceNumber`, `ingredientCost`) VALUES
(4, 3, 'Cash', 'completed', 195.00, '2025-11-17 13:37:41', NULL, '[{\"productID\":1,\"sizeID\":1,\"quantity\":1,\"unitPrice\":95,\"totalPrice\":95,\"addons\":[]},{\"productID\":3,\"sizeID\":1,\"quantity\":1,\"unitPrice\":100,\"totalPrice\":100,\"addons\":[]}]', 'ORD000004', 0.00),
(5, 3, 'Cash', 'completed', 100.00, '2025-11-17 13:40:04', NULL, '[{\"productID\":3,\"sizeID\":1,\"quantity\":1,\"unitPrice\":100,\"totalPrice\":100,\"addons\":[]}]', 'ORD000005', 0.00),
(6, 3, 'Gcash', 'completed', 95.00, '2025-11-17 15:02:35', NULL, '[{\"productID\":1,\"sizeID\":1,\"quantity\":1,\"unitPrice\":95,\"totalPrice\":95,\"addons\":[]}]', 'ORD000006', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `production_cost_overrides`
--

CREATE TABLE `production_cost_overrides` (
  `overrideID` int(11) NOT NULL,
  `productID` int(11) NOT NULL,
  `inventoryID` int(11) NOT NULL,
  `needed_per_cup` decimal(10,4) DEFAULT NULL,
  `ingredient_cost` decimal(10,4) DEFAULT NULL,
  `is_removed` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_cost_overrides`
--

INSERT INTO `production_cost_overrides` (`overrideID`, `productID`, `inventoryID`, `needed_per_cup`, `ingredient_cost`, `is_removed`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 150.0000, 12.0000, 0, 1, '2025-11-16 22:33:00', '2025-11-17 07:02:17'),
(2, 1, 2, 10.0000, 5.0000, 0, 1, '2025-11-16 22:33:18', '2025-11-17 05:37:03'),
(3, 1, 3, 20.0000, 15.0000, 0, 1, '2025-11-16 22:33:27', '2025-11-17 05:37:07'),
(4, 1, 4, 50.0000, 8.5000, 0, 1, '2025-11-16 22:33:38', '2025-11-17 05:37:12'),
(5, 1, 8, 20.0000, 2.0000, 0, 1, '2025-11-16 22:35:59', '2025-11-16 22:35:59'),
(6, 2, 1, 150.0000, 12.0000, 0, 1, '2025-11-16 22:44:35', '2025-11-16 22:44:35'),
(7, 2, 5, 100.0000, 14.0000, 0, 1, '2025-11-16 22:45:01', '2025-11-16 22:45:01'),
(8, 2, 6, 20.0000, 16.0000, 0, 1, '2025-11-16 22:45:15', '2025-11-16 22:45:15'),
(9, 2, 7, 20.0000, 4.6800, 0, 1, '2025-11-16 22:45:29', '2025-11-16 22:45:29'),
(10, 2, 4, 20.0000, 3.4000, 0, 1, '2025-11-16 22:45:45', '2025-11-16 22:45:45'),
(11, 2, 8, 20.0000, 2.0000, 0, 1, '2025-11-16 22:45:52', '2025-11-16 22:45:52'),
(12, 3, 1, 150.0000, 12.0000, 0, 1, '2025-11-17 05:25:37', '2025-11-17 05:25:37'),
(13, 3, 4, 50.0000, 8.5000, 0, 1, '2025-11-17 05:25:46', '2025-11-17 05:25:46'),
(14, 3, 3, 20.0000, 15.0000, 0, 1, '2025-11-17 05:26:01', '2025-11-17 05:26:01'),
(15, 3, 11, 5.0000, 3.0000, 0, 1, '2025-11-17 05:27:23', '2025-11-17 05:27:23'),
(16, 3, 8, 20.0000, 2.0000, 0, 1, '2025-11-17 05:27:35', '2025-11-17 05:27:35');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `productID` int(11) NOT NULL,
  `productName` varchar(150) NOT NULL,
  `categoryID` int(11) NOT NULL,
  `isActive` tinyint(1) DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `unit_type` varchar(50) DEFAULT 'piece',
  `unit_value` decimal(10,2) DEFAULT 1.00,
  `base_price` decimal(10,2) DEFAULT 0.00,
  `cost_price` decimal(10,2) DEFAULT 0.00,
  `is_trackable` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`productID`, `productName`, `categoryID`, `isActive`, `createdAt`, `unit_type`, `unit_value`, `base_price`, `cost_price`, `is_trackable`) VALUES
(1, 'Matcha Cheesecake', 3, 1, '2025-11-16 22:32:41', 'Ounce', 1.00, 0.00, 0.00, 1),
(2, 'Biscoff Latte', 9, 1, '2025-11-16 22:44:03', 'Ounce', 1.00, 0.00, 0.00, 1),
(3, 'Ube Cheese Cake', 3, 1, '2025-11-17 05:25:20', 'Ounce', 1.00, 0.00, 0.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `product_prices`
--

CREATE TABLE `product_prices` (
  `productID` int(11) NOT NULL,
  `sizeID` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_prices`
--

INSERT INTO `product_prices` (`productID`, `sizeID`, `unit_id`, `price`) VALUES
(1, 1, 2, 95.00),
(1, 2, 2, 95.00),
(1, 4, 1, 0.00),
(2, 1, 2, 99.00),
(2, 4, 1, 0.00),
(3, 1, 2, 100.00),
(3, 4, 1, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `product_units`
--

CREATE TABLE `product_units` (
  `unit_id` int(11) NOT NULL,
  `unit_name` varchar(50) NOT NULL,
  `unit_symbol` varchar(10) NOT NULL,
  `conversion_factor` decimal(10,4) DEFAULT 1.0000,
  `is_base_unit` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_units`
--

INSERT INTO `product_units` (`unit_id`, `unit_name`, `unit_symbol`, `conversion_factor`, `is_base_unit`, `created_at`) VALUES
(1, 'Piece', 'pc', 1.0000, 1, '2025-09-27 12:59:48'),
(2, 'Ounce', 'oz', 1.0000, 1, '2025-09-27 12:59:48'),
(3, 'Pound', 'lb', 16.0000, 0, '2025-09-27 12:59:48'),
(4, 'Kilogram', 'kg', 35.2740, 0, '2025-09-27 12:59:48'),
(5, 'Gram', 'g', 0.0353, 0, '2025-09-27 12:59:48'),
(6, 'Liter', 'L', 33.8140, 0, '2025-09-27 12:59:48'),
(7, 'Milliliter', 'mL', 0.0338, 0, '2025-09-27 12:59:48'),
(8, 'Cup', 'cup', 8.0000, 0, '2025-09-27 12:59:48'),
(9, 'Tablespoon', 'tbsp', 0.5000, 0, '2025-09-27 12:59:48'),
(10, 'Teaspoon', 'tsp', 0.1667, 0, '2025-09-27 12:59:48');

-- --------------------------------------------------------

--
-- Table structure for table `recipes`
--

CREATE TABLE `recipes` (
  `recipeID` int(11) NOT NULL,
  `productID` int(11) NOT NULL,
  `inventoryID` int(11) NOT NULL,
  `amount` decimal(10,4) NOT NULL,
  `unit` varchar(10) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recipes`
--

INSERT INTO `recipes` (`recipeID`, `productID`, `inventoryID`, `amount`, `unit`, `display_order`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 150.0000, 'ml', 0, '2025-11-16 22:33:00', '2025-11-17 07:02:17'),
(2, 1, 2, 10.0000, 'g', 1, '2025-11-16 22:33:18', '2025-11-17 05:37:03'),
(3, 1, 3, 20.0000, 'g', 2, '2025-11-16 22:33:27', '2025-11-17 05:37:07'),
(4, 1, 4, 50.0000, 'ml', 3, '2025-11-16 22:33:38', '2025-11-17 05:37:12'),
(5, 1, 8, 20.0000, 'g', 4, '2025-11-16 22:35:59', '2025-11-16 22:35:59'),
(6, 2, 1, 150.0000, 'ml', 0, '2025-11-16 22:44:35', '2025-11-16 22:44:35'),
(7, 2, 5, 100.0000, 'ml', 1, '2025-11-16 22:45:01', '2025-11-16 22:45:01'),
(8, 2, 6, 20.0000, 'g', 2, '2025-11-16 22:45:15', '2025-11-16 22:45:15'),
(9, 2, 7, 20.0000, 'g', 3, '2025-11-16 22:45:29', '2025-11-16 22:45:29'),
(10, 2, 4, 20.0000, 'ml', 4, '2025-11-16 22:45:45', '2025-11-16 22:45:45'),
(11, 2, 8, 20.0000, 'g', 5, '2025-11-16 22:45:52', '2025-11-16 22:45:52'),
(12, 3, 1, 150.0000, 'ml', 0, '2025-11-17 05:25:37', '2025-11-17 05:25:37'),
(13, 3, 4, 50.0000, 'ml', 1, '2025-11-17 05:25:46', '2025-11-17 05:25:46'),
(14, 3, 3, 20.0000, 'g', 2, '2025-11-17 05:26:01', '2025-11-17 05:26:01'),
(15, 3, 11, 5.0000, 'g', 3, '2025-11-17 05:27:23', '2025-11-17 05:27:23'),
(16, 3, 8, 20.0000, 'g', 4, '2025-11-17 05:27:35', '2025-11-17 05:27:35'),
(17, 4, 4, 50.0000, 'ml', 0, '2025-11-17 07:20:39', '2025-11-17 07:20:39');

-- --------------------------------------------------------

--
-- Table structure for table `sizes`
--

CREATE TABLE `sizes` (
  `sizeID` int(11) NOT NULL,
  `sizeName` varchar(50) NOT NULL,
  `defaultPrice` decimal(10,2) NOT NULL DEFAULT 0.00,
  `isActive` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sizes`
--

INSERT INTO `sizes` (`sizeID`, `sizeName`, `defaultPrice`, `isActive`) VALUES
(1, '16', 0.00, 1),
(2, '22', 0.00, 1),
(4, '12', 0.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `size_multipliers`
--

CREATE TABLE `size_multipliers` (
  `sizeID` int(11) NOT NULL,
  `multiplier` decimal(5,2) NOT NULL,
  `size_name` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `size_multipliers`
--

INSERT INTO `size_multipliers` (`sizeID`, `multiplier`, `size_name`, `created_at`, `updated_at`) VALUES
(1, 1.00, '16oz', '2025-11-16 20:32:04', '2025-11-16 20:32:04'),
(2, 1.50, '22oz', '2025-11-16 20:32:04', '2025-11-16 20:32:04'),
(3, 0.75, '12oz', '2025-11-16 20:32:04', '2025-11-16 20:32:04');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_name`, `value`, `description`, `updated_at`) VALUES
(1, 'manager_override_pin', '$2y$10$h8H7EiaA1YI0ZYj8x62EGe1dQRJOlEkrKAyJKWQ/M.l1j.BnNdZHO', 'Manager override authorization PIN (default is hashed but you should change it)', '2025-09-27 12:59:47'),
(2, 'max_discount_without_override', '20', 'Automatic override is required for discounts above this percentage', '2025-09-27 12:59:47'),
(3, 'company_name', 'Kape Timplado', 'Business name', '2025-09-27 12:59:47'),
(4, 'system_version', '2.0', 'Running system version', '2025-09-27 12:59:47'),
(5, 'analytics_refresh_minutes', '5', 'Dashboard auto-refresh interval in minutes', '2025-09-27 12:59:47'),
(6, 'default_transaction_limit', '100', 'Default number of transactions to load in admin transactions view', '2025-09-27 12:59:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `passwordHash` varchar(255) NOT NULL,
  `role` enum('admin','cashier') DEFAULT 'admin',
  `isActive` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `employee_id` varchar(20) DEFAULT NULL,
  `shift_start` timestamp NULL DEFAULT NULL,
  `shift_end` timestamp NULL DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `username`, `passwordHash`, `role`, `isActive`, `created_at`, `last_login`, `is_active`, `employee_id`, `shift_start`, `shift_end`, `last_activity`, `first_name`, `last_name`, `email`, `phone`, `address`) VALUES
(1, 'admin', '$2y$10$kFK7AkmYA3BkcGstrunmLupuS.VTgq/mS84IxoKmTTzkTxyr8DEWG', 'admin', 1, '2025-09-27 12:59:47', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'cashier', '$2y$10$j0hbcs6M821qzLv61a2KkuPNor.otE4De./x7wunKF0p80oJOHio2', 'cashier', 1, '2025-09-27 12:59:47', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'jane_doe', '$2y$10$jhroxopprFftCy33F1JioevijZzKp5aAFQe8Z4MNaVnrlMXx.HHZC', 'cashier', 1, '2025-11-16 22:23:03', NULL, 1, 'EMP001', NULL, NULL, NULL, 'Jane', 'Doe', 'test@gmail.com', '12345678901', 'e');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`logID`),
  ADD KEY `idx_user` (`userID`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_date` (`created_at`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`categoryID`),
  ADD UNIQUE KEY `categoryName` (`categoryName`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventoryID`),
  ADD KEY `idx_size` (`Size`),
  ADD KEY `idx_status` (`Status`),
  ADD KEY `idx_stock` (`Current_Stock`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`orderID`),
  ADD KEY `userID` (`userID`);

--
-- Indexes for table `production_cost_overrides`
--
ALTER TABLE `production_cost_overrides`
  ADD PRIMARY KEY (`overrideID`),
  ADD UNIQUE KEY `unique_product_inventory_override` (`productID`,`inventoryID`),
  ADD KEY `idx_product` (`productID`),
  ADD KEY `idx_inventory` (`inventoryID`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`productID`),
  ADD KEY `categoryID` (`categoryID`);

--
-- Indexes for table `product_prices`
--
ALTER TABLE `product_prices`
  ADD PRIMARY KEY (`productID`,`sizeID`,`unit_id`),
  ADD KEY `sizeID` (`sizeID`),
  ADD KEY `fk_product_prices_unit_id` (`unit_id`);

--
-- Indexes for table `product_units`
--
ALTER TABLE `product_units`
  ADD PRIMARY KEY (`unit_id`);

--
-- Indexes for table `recipes`
--
ALTER TABLE `recipes`
  ADD PRIMARY KEY (`recipeID`),
  ADD KEY `productID` (`productID`),
  ADD KEY `inventoryID` (`inventoryID`);

--
-- Indexes for table `sizes`
--
ALTER TABLE `sizes`
  ADD PRIMARY KEY (`sizeID`),
  ADD UNIQUE KEY `sizeName` (`sizeName`);

--
-- Indexes for table `size_multipliers`
--
ALTER TABLE `size_multipliers`
  ADD PRIMARY KEY (`sizeID`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `logID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `categoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `orderID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `production_cost_overrides`
--
ALTER TABLE `production_cost_overrides`
  MODIFY `overrideID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `productID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `product_units`
--
ALTER TABLE `product_units`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `recipes`
--
ALTER TABLE `recipes`
  MODIFY `recipeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `sizes`
--
ALTER TABLE `sizes`
  MODIFY `sizeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_users` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`categoryID`) REFERENCES `categories` (`categoryID`) ON DELETE CASCADE;

--
-- Constraints for table `product_prices`
--
ALTER TABLE `product_prices`
  ADD CONSTRAINT `fk_product_prices_unit_id` FOREIGN KEY (`unit_id`) REFERENCES `product_units` (`unit_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_prices_ibfk_1` FOREIGN KEY (`productID`) REFERENCES `products` (`productID`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_prices_ibfk_2` FOREIGN KEY (`sizeID`) REFERENCES `sizes` (`sizeID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
