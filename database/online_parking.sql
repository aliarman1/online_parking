-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 23, 2025 at 02:20 PM
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
-- Database: `online_parking`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `vehicle_number` varchar(20) NOT NULL,
  `booking_date` date NOT NULL,
  `booking_time` time NOT NULL,
  `duration` int(11) NOT NULL,
  `end_time` time NOT NULL,
  `cost_per_hour` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `bkash_number` varchar(15) NOT NULL,
  `bkash_pin` varchar(6) NOT NULL,
  `status` varchar(20) DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `slot_id`, `vehicle_number`, `booking_date`, `booking_time`, `duration`, `end_time`, `cost_per_hour`, `total_cost`, `bkash_number`, `bkash_pin`, `status`) VALUES
(11, 14, 26, 'D9', '2025-03-23', '19:55:00', 1, '20:55:00', 50.00, 50.00, '01704442185', '11111', 'Pending'),
(12, 14, 107, 'D10', '2025-03-23', '14:17:00', 1, '15:17:00', 60.00, 60.00, '01721207767', '11111', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `parking_slots`
--

CREATE TABLE `parking_slots` (
  `id` int(11) NOT NULL,
  `slot_number` varchar(10) NOT NULL,
  `location` varchar(100) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `vehicle_type` enum('bike','car') NOT NULL,
  `cost_per_hour` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_slots`
--

INSERT INTO `parking_slots` (`id`, `slot_number`, `location`, `is_available`, `vehicle_type`, `cost_per_hour`) VALUES
(1, 'B1', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(2, 'B2', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(3, 'B3', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(4, 'B4', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(5, 'B5', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(6, 'B6', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(7, 'B7', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(8, 'B8', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(9, 'B9', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(10, 'B10', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(11, 'B11', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(12, 'B12', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(13, 'B13', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(14, 'B14', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(15, 'B15', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(16, 'B16', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(17, 'B17', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(18, 'B18', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(19, 'B19', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(20, 'B20', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(21, 'B21', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(22, 'B22', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(23, 'B23', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(24, 'B24', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(25, 'B25', 'Bashundhara Shopping Mall', 1, 'bike', 20.00),
(26, 'C1', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(27, 'C2', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(28, 'C3', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(29, 'C4', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(30, 'C5', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(31, 'C6', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(32, 'C7', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(33, 'C8', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(34, 'C9', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(35, 'C10', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(36, 'C11', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(37, 'C12', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(38, 'C13', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(39, 'C14', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(40, 'C15', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(41, 'C16', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(42, 'C17', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(43, 'C18', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(44, 'C19', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(45, 'C20', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(46, 'C21', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(47, 'C22', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(48, 'C23', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(49, 'C24', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(50, 'C25', 'Bashundhara Shopping Mall', 1, 'car', 50.00),
(51, 'J1', 'Jamuna Future Park', 1, 'bike', 25.00),
(52, 'J2', 'Jamuna Future Park', 1, 'bike', 25.00),
(53, 'J3', 'Jamuna Future Park', 1, 'bike', 25.00),
(54, 'J4', 'Jamuna Future Park', 1, 'bike', 25.00),
(55, 'J5', 'Jamuna Future Park', 1, 'bike', 25.00),
(56, 'J6', 'Jamuna Future Park', 1, 'bike', 25.00),
(57, 'J7', 'Jamuna Future Park', 1, 'bike', 25.00),
(58, 'J8', 'Jamuna Future Park', 1, 'bike', 25.00),
(59, 'J9', 'Jamuna Future Park', 1, 'bike', 25.00),
(60, 'J10', 'Jamuna Future Park', 1, 'bike', 25.00),
(61, 'J11', 'Jamuna Future Park', 1, 'bike', 25.00),
(62, 'J12', 'Jamuna Future Park', 1, 'bike', 25.00),
(63, 'J13', 'Jamuna Future Park', 1, 'bike', 25.00),
(64, 'J14', 'Jamuna Future Park', 1, 'bike', 25.00),
(65, 'J15', 'Jamuna Future Park', 1, 'bike', 25.00),
(66, 'J16', 'Jamuna Future Park', 1, 'bike', 25.00),
(67, 'J17', 'Jamuna Future Park', 1, 'bike', 25.00),
(68, 'J18', 'Jamuna Future Park', 1, 'bike', 25.00),
(69, 'J19', 'Jamuna Future Park', 1, 'bike', 25.00),
(70, 'J20', 'Jamuna Future Park', 1, 'bike', 25.00),
(71, 'J21', 'Jamuna Future Park', 1, 'bike', 25.00),
(72, 'J22', 'Jamuna Future Park', 1, 'bike', 25.00),
(73, 'J23', 'Jamuna Future Park', 1, 'bike', 25.00),
(74, 'J24', 'Jamuna Future Park', 1, 'bike', 25.00),
(75, 'J25', 'Jamuna Future Park', 1, 'bike', 25.00),
(101, 'K1', 'Jamuna Future Park', 1, 'car', 60.00),
(102, 'K2', 'Jamuna Future Park', 1, 'car', 60.00),
(103, 'K3', 'Jamuna Future Park', 1, 'car', 60.00),
(104, 'K4', 'Jamuna Future Park', 1, 'car', 60.00),
(105, 'K5', 'Jamuna Future Park', 1, 'car', 60.00),
(106, 'K6', 'Jamuna Future Park', 1, 'car', 60.00),
(107, 'K7', 'Jamuna Future Park', 1, 'car', 60.00),
(108, 'K8', 'Jamuna Future Park', 1, 'car', 60.00),
(109, 'K9', 'Jamuna Future Park', 1, 'car', 60.00),
(110, 'K10', 'Jamuna Future Park', 1, 'car', 60.00),
(111, 'K11', 'Jamuna Future Park', 1, 'car', 60.00),
(112, 'K12', 'Jamuna Future Park', 1, 'car', 60.00),
(113, 'K13', 'Jamuna Future Park', 1, 'car', 60.00),
(114, 'K14', 'Jamuna Future Park', 1, 'car', 60.00),
(115, 'K15', 'Jamuna Future Park', 1, 'car', 60.00),
(116, 'K16', 'Jamuna Future Park', 1, 'car', 60.00),
(117, 'K17', 'Jamuna Future Park', 1, 'car', 60.00),
(118, 'K18', 'Jamuna Future Park', 1, 'car', 60.00),
(119, 'K19', 'Jamuna Future Park', 1, 'car', 60.00),
(120, 'K20', 'Jamuna Future Park', 1, 'car', 60.00),
(121, 'K21', 'Jamuna Future Park', 1, 'car', 60.00),
(122, 'K22', 'Jamuna Future Park', 1, 'car', 60.00),
(123, 'K23', 'Jamuna Future Park', 1, 'car', 60.00),
(124, 'K24', 'Jamuna Future Park', 1, 'car', 60.00),
(125, 'K25', 'Jamuna Future Park', 1, 'car', 60.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$10$6VrGiDX6voZH4uCZJVmUL.3fWPJymMHfkn8tnDrTF40GdHbOEQXDC');

-- Username: admin
-- Password: admin
--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `slot_id` (`slot_id`);

--
-- Indexes for table `parking_slots`
--
ALTER TABLE `parking_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slot_number` (`slot_number`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `parking_slots`
--
ALTER TABLE `parking_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`slot_id`) REFERENCES `parking_slots` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
