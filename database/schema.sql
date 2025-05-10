-- Smart Parking System Database Schema
-- Improved database design with proper relationships and constraints

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS smart_parking;
USE smart_parking;

-- Drop existing tables if they exist to avoid conflicts
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS parking_spots;
DROP TABLE IF EXISTS auth_logs;
DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Create auth_logs table for tracking login attempts
CREATE TABLE auth_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create parking_spots table
CREATE TABLE parking_spots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spot_number VARCHAR(10) NOT NULL UNIQUE,
    floor_number INT NOT NULL,
    type ENUM('standard', 'handicap', 'electric', 'Car', 'Bike', 'VIP') NOT NULL DEFAULT 'standard',
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 5.00,
    is_occupied BOOLEAN DEFAULT FALSE,
    vehicle_number VARCHAR(20) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create bookings table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    spot_id INT NOT NULL,
    vehicle_number VARCHAR(20) NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    payment_status ENUM('pending', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (spot_id) REFERENCES parking_spots(id) ON DELETE CASCADE
);

-- Create payments table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATETIME NOT NULL,
    payment_method ENUM('cash', 'card', 'paypal', 'bank_transfer') NOT NULL,
    transaction_id VARCHAR(100) NOT NULL,
    payment_status ENUM('pending', 'paid') DEFAULT 'paid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- Add indexes for better performance
ALTER TABLE bookings ADD INDEX idx_user_status (user_id, status);
ALTER TABLE bookings ADD INDEX idx_spot_status (spot_id, status);
ALTER TABLE bookings ADD INDEX idx_booking_dates (start_time, end_time);
ALTER TABLE payments ADD INDEX idx_booking_payment (booking_id, payment_status);

-- Create function to update expired bookings
DELIMITER //
CREATE FUNCTION IF NOT EXISTS update_booking_status() RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE updated_count INT;

    -- Update completed bookings
    UPDATE bookings
    SET status = 'completed'
    WHERE status = 'active' AND end_time < NOW();

    -- Get the number of updated rows
    SET updated_count = ROW_COUNT();

    RETURN updated_count;
END //
DELIMITER ;

-- Create event to automatically update booking status
CREATE EVENT IF NOT EXISTS update_booking_status_event
ON SCHEDULE EVERY 1 HOUR
DO
    SELECT update_booking_status();

-- Insert sample parking spots
INSERT INTO parking_spots (spot_number, floor_number, type, hourly_rate, is_occupied) VALUES
('A1', 0, 'Car', 50.00, 0),
('A2', 0, 'Car', 50.00, 0),
('B1', 0, 'Bike', 20.00, 0),
('B2', 0, 'Bike', 20.00, 0),
('V1', 0, 'VIP', 100.00, 0),
('A3', 1, 'Car', 60.00, 0),
('A4', 1, 'Car', 60.00, 0),
('B3', 1, 'Bike', 25.00, 0),
('B4', 1, 'Bike', 25.00, 0),
('V2', 1, 'VIP', 120.00, 0),
('A5', 2, 'Car', 70.00, 0),
('A6', 2, 'Car', 70.00, 0),
('B5', 2, 'Bike', 30.00, 0),
('B6', 2, 'Bike', 30.00, 0),
('V3', 2, 'VIP', 150.00, 0);

-- Add Default users with admin and normal user
INSERT INTO users (name, email, password, is_admin) VALUES
('Admin User', 'admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
('Public User', 'user@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0);

-- Admin and User
-- Password: password
