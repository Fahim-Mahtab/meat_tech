-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 07, 2025 at 09:00 AM
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
-- Database: `meettrack`
--

-- --------------------------------------------------------

--
-- Table structure for table `condition_monitoring`
--

CREATE TABLE `condition_monitoring` (
  `id` int(11) NOT NULL,
  `storage_location_id` int(11) NOT NULL,
  `temperature` decimal(5,2) NOT NULL,
  `humidity` decimal(5,2) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dismissed_alerts`
--

CREATE TABLE `dismissed_alerts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `alert_identifier` varchar(50) NOT NULL,
  `dismissed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dismissed_alerts`
--

INSERT INTO `dismissed_alerts` (`id`, `user_id`, `alert_identifier`, `dismissed_at`) VALUES
(1, 1, 'inv_5', '2025-05-06 21:40:56'),
(2, 1, 'inv_6', '2025-05-06 21:40:59'),
(3, 1, 'inv_3', '2025-05-06 21:41:00'),
(4, 1, 'inv_1', '2025-05-06 21:41:01'),
(5, 1, 'inv_2', '2025-05-06 21:41:01'),
(7, 1, 'cond_3', '2025-05-06 21:41:03'),
(8, 1, 'cond_2', '2025-05-06 21:41:04'),
(9, 1, 'inv_8', '2025-05-06 21:41:06'),
(10, 1, 'inv_9', '2025-05-07 06:14:10'),
(11, 1, 'cond_9', '2025-05-07 06:30:12'),
(12, 1, 'cond_4', '2025-05-07 06:30:14');

-- --------------------------------------------------------

--
-- Table structure for table `distribution`
--

CREATE TABLE `distribution` (
  `id` int(11) NOT NULL,
  `delivery_id` varchar(50) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `scheduled_datetime` datetime NOT NULL,
  `vehicle` varchar(50) DEFAULT NULL,
  `driver` varchar(100) DEFAULT NULL,
  `status` enum('preparing','in_transit','delivered','cancelled') DEFAULT 'preparing',
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `distribution`
--

INSERT INTO `distribution` (`id`, `delivery_id`, `destination`, `scheduled_datetime`, `vehicle`, `driver`, `status`, `completed_at`, `created_at`) VALUES
(7, 'DL-2025-05-07-473', 'Dhaka', '2025-05-07 08:51:00', 'Truck', 'Sadaf', 'delivered', '2025-05-07 12:51:49', '2025-05-07 06:51:26'),
(8, 'DL-2025-05-07-110', 'Dhaka', '2025-05-07 08:51:00', 'Truck', 'Sadaf', 'cancelled', NULL, '2025-05-07 06:52:02'),
(9, 'DL-2025-05-07-698', 'Dhaka', '2025-05-07 08:53:00', 'Truck', 'Sadaf', 'preparing', NULL, '2025-05-07 06:53:16');

-- --------------------------------------------------------

--
-- Table structure for table `distribution_items`
--

CREATE TABLE `distribution_items` (
  `id` int(11) NOT NULL,
  `distribution_id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `distribution_items`
--

INSERT INTO `distribution_items` (`id`, `distribution_id`, `inventory_id`, `quantity`) VALUES
(9, 7, 19, 5.00),
(10, 8, 19, 5.00),
(11, 9, 19, 5.00);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `meat_type_id` int(11) NOT NULL,
  `cut_type` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `processing_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `storage_location_id` int(11) NOT NULL,
  `quality_notes` text DEFAULT NULL,
  `status` enum('good','near_expiry','spoiled') DEFAULT 'good',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `batch_number`, `meat_type_id`, `cut_type`, `quantity`, `processing_date`, `expiry_date`, `storage_location_id`, `quality_notes`, `status`, `created_at`) VALUES
(19, 'MT-2025-05-07-055', 1, 'Loin', 5.00, '2025-05-07', '2025-05-05', 2, '', 'good', '2025-05-07 06:51:14');

-- --------------------------------------------------------

--
-- Table structure for table `loss_records`
--

CREATE TABLE `loss_records` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `meat_type_id` int(11) NOT NULL,
  `stage` enum('slaughter','processing','storage','handling','transport','retail') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `action_taken` text DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meat_types`
--

CREATE TABLE `meat_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `default_expiry_days` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meat_types`
--

INSERT INTO `meat_types` (`id`, `name`, `default_expiry_days`) VALUES
(1, 'Beef', 1),
(2, 'Chicken', 2),
(4, 'Lamb', 1),
(5, 'Turkey', 5);

-- --------------------------------------------------------

--
-- Table structure for table `spoilage`
--

CREATE TABLE `spoilage` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `batch_number` varchar(50) NOT NULL,
  `meat_type_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `processing_date` date NOT NULL,
  `storage_location_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `disposal_method` varchar(100) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `storage_locations`
--

CREATE TABLE `storage_locations` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `temperature_range` varchar(50) NOT NULL,
  `capacity_kg` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `storage_locations`
--

INSERT INTO `storage_locations` (`id`, `name`, `temperature_range`, `capacity_kg`) VALUES
(1, 'Cold Room 1', '0-4°C', 1000.00),
(2, 'Cold Room 2', '0-4°C', 1000.00),
(3, 'Freezer 1', '-18°C', 800.00),
(4, 'Freezer 2', '-18°C', 800.00);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `expiry_alerts` tinyint(1) DEFAULT 1,
  `expiry_days_before` int(11) DEFAULT 3,
  `max_temp` decimal(3,1) DEFAULT 4.0,
  `min_temp` decimal(3,1) DEFAULT 0.0,
  `max_humidity` int(11) DEFAULT 80,
  `min_humidity` int(11) DEFAULT 65,
  `monitoring_alerts` tinyint(1) DEFAULT 1,
  `email_alerts` tinyint(1) DEFAULT 1,
  `email_recipients` text DEFAULT NULL,
  `sms_alerts` tinyint(1) DEFAULT 0,
  `sms_recipients` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager','supervisor','operator','viewer') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `last_login`, `created_at`) VALUES
(1, 'Admin User', 'admin@meettrack.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', NULL, '2025-04-23 16:31:47'),
(2, 'asdasdagds', 'fahimmahtab40@gmail.com', '$2y$10$p3SdGga/SocgHIvTMEI6V.4h1kAjxq/It4BvIjX7HPakE7igUkHRO', 'manager', 'active', NULL, '2025-04-23 16:43:55'),
(3, 'Abdullah', 'abdullah55@gmail.com', '$2y$10$4froNn.qYksKwvdMk3S6xOuY6Cll749UfHbnppNgdYhv3KqCQp0E2', 'manager', 'active', NULL, '2025-04-23 17:00:13'),
(4, 'sadaf', 'sadaf55@gmail.com', '$2y$10$4JRJywg/EME41hP6K2BH9Orjb7u2B4ll6sPbDn/nIFDlrTLbSs6sq', 'viewer', 'active', NULL, '2025-04-23 17:02:16'),
(5, 'Abdullah', 'abdullah58@gmail.com', '$2y$10$gBxwvobBBNk9u3ISYN/KL.I0TyH8oDMn.1K3gj.Qu4mPcdpjW6gbu', 'manager', 'active', NULL, '2025-05-07 06:19:01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `condition_monitoring`
--
ALTER TABLE `condition_monitoring`
  ADD PRIMARY KEY (`id`),
  ADD KEY `storage_location_id` (`storage_location_id`);

--
-- Indexes for table `dismissed_alerts`
--
ALTER TABLE `dismissed_alerts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`alert_identifier`);

--
-- Indexes for table `distribution`
--
ALTER TABLE `distribution`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `delivery_id` (`delivery_id`);

--
-- Indexes for table `distribution_items`
--
ALTER TABLE `distribution_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `distribution_id` (`distribution_id`),
  ADD KEY `distribution_items_ibfk_2` (`inventory_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_number` (`batch_number`),
  ADD KEY `storage_location_id` (`storage_location_id`),
  ADD KEY `inventory_ibfk_1` (`meat_type_id`);

--
-- Indexes for table `loss_records`
--
ALTER TABLE `loss_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meat_type_id` (`meat_type_id`),
  ADD KEY `loss_records_ibfk_1` (`inventory_id`);

--
-- Indexes for table `meat_types`
--
ALTER TABLE `meat_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `spoilage`
--
ALTER TABLE `spoilage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `meat_type_id` (`meat_type_id`),
  ADD KEY `storage_location_id` (`storage_location_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Indexes for table `storage_locations`
--
ALTER TABLE `storage_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `condition_monitoring`
--
ALTER TABLE `condition_monitoring`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `dismissed_alerts`
--
ALTER TABLE `dismissed_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `distribution`
--
ALTER TABLE `distribution`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `distribution_items`
--
ALTER TABLE `distribution_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `loss_records`
--
ALTER TABLE `loss_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `meat_types`
--
ALTER TABLE `meat_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `spoilage`
--
ALTER TABLE `spoilage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `storage_locations`
--
ALTER TABLE `storage_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `condition_monitoring`
--
ALTER TABLE `condition_monitoring`
  ADD CONSTRAINT `condition_monitoring_ibfk_1` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`);

--
-- Constraints for table `dismissed_alerts`
--
ALTER TABLE `dismissed_alerts`
  ADD CONSTRAINT `dismissed_alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `distribution_items`
--
ALTER TABLE `distribution_items`
  ADD CONSTRAINT `distribution_items_ibfk_1` FOREIGN KEY (`distribution_id`) REFERENCES `distribution` (`id`),
  ADD CONSTRAINT `distribution_items_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`meat_type_id`) REFERENCES `meat_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`);

--
-- Constraints for table `loss_records`
--
ALTER TABLE `loss_records`
  ADD CONSTRAINT `loss_records_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loss_records_ibfk_2` FOREIGN KEY (`meat_type_id`) REFERENCES `meat_types` (`id`);

--
-- Constraints for table `spoilage`
--
ALTER TABLE `spoilage`
  ADD CONSTRAINT `spoilage_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `spoilage_ibfk_2` FOREIGN KEY (`meat_type_id`) REFERENCES `meat_types` (`id`),
  ADD CONSTRAINT `spoilage_ibfk_3` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`),
  ADD CONSTRAINT `spoilage_ibfk_4` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
