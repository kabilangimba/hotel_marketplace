-- =====================================================
-- WEB-BASED MULTI-HOTEL BOOKING MARKETPLACE
-- Database: hotel_marketplace
-- Author: Judith Antoni Obedi (BCS-01-0019-2023)
-- =====================================================

-- Drop and recreate the database (safe for development)
DROP DATABASE IF EXISTS hotel_marketplace;
CREATE DATABASE hotel_marketplace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotel_marketplace;

-- =====================================================
-- TABLE: users
-- Stores customers, hotel managers, and admins
-- =====================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,        -- stores bcrypt hash, NEVER plain text
    phone VARCHAR(20),
    role ENUM('customer', 'manager', 'admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- TABLE: hotels
-- Each hotel is owned by one manager (user with role='manager')
-- =====================================================
CREATE TABLE hotels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manager_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    location VARCHAR(150) NOT NULL,         -- e.g. "Arusha, Tanzania"
    description TEXT,
    image VARCHAR(255),                     -- cover photo: filename in images/ OR a full https URL
    website VARCHAR(255),                   -- official hotel website (optional)
    phone VARCHAR(40),                      -- contact phone (optional)
    rating DECIMAL(2,1),                    -- star rating 0.0–5.0 (optional)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- TABLE: rooms
-- Each room belongs to one hotel
-- =====================================================
CREATE TABLE rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    room_type VARCHAR(50) NOT NULL,         -- "Single", "Double", "Suite"
    price_per_night DECIMAL(10,2) NOT NULL, -- e.g. 75000.00 TZS
    capacity INT NOT NULL DEFAULT 1,        -- number of guests
    description TEXT,
    image VARCHAR(255),
    is_active TINYINT(1) NOT NULL DEFAULT 1, -- manager can disable a room
    FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
);

-- =====================================================
-- TABLE: bookings
-- Tracks every reservation. The key business rule lives here.
-- =====================================================
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,                   -- who booked it
    room_id INT NOT NULL,                   -- which room
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'confirmed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    -- Sanity check at the database level
    CHECK (check_out > check_in)
);

-- Index for fast availability lookups (the most frequent query)
CREATE INDEX idx_bookings_room_dates ON bookings(room_id, check_in, check_out, status);

-- =====================================================
-- SAMPLE DATA FOR DEMO ON MONDAY
-- =====================================================

-- Passwords below are bcrypt hashes of 'password123'
-- Generate yours in PHP with: password_hash('password123', PASSWORD_DEFAULT)
-- (This hash is verified to match 'password123'.)

INSERT INTO users (name, email, password, phone, role) VALUES
('System Admin',   'admin@hotel.com',   '$2y$12$b9jpyMVeOG4H1BSnwVCAMuauxvKIyePMk41E8JnWp8BLLTDmhhIsG', '0712000001', 'admin'),
('Mount Meru Mgr', 'mgr1@hotel.com',    '$2y$12$b9jpyMVeOG4H1BSnwVCAMuauxvKIyePMk41E8JnWp8BLLTDmhhIsG', '0712000002', 'manager'),
('Arusha Inn Mgr', 'mgr2@hotel.com',    '$2y$12$b9jpyMVeOG4H1BSnwVCAMuauxvKIyePMk41E8JnWp8BLLTDmhhIsG', '0712000003', 'manager'),
('Judith Test',    'judith@gmail.com',  '$2y$12$b9jpyMVeOG4H1BSnwVCAMuauxvKIyePMk41E8JnWp8BLLTDmhhIsG', '0712000004', 'customer'),
('John Tourist',   'john@gmail.com',    '$2y$12$b9jpyMVeOG4H1BSnwVCAMuauxvKIyePMk41E8JnWp8BLLTDmhhIsG', '0712000005', 'customer');

-- Sample hotels
INSERT INTO hotels (manager_id, name, location, description, image) VALUES
(2, 'Mount Meru Hotel',  'Arusha, Tanzania', 'Luxury hotel near Mount Meru with safari views', 'hotel1.jpg'),
(2, 'Kilimanjaro Lodge', 'Moshi, Tanzania',  'Cozy lodge close to Mount Kilimanjaro',         'hotel2.jpg'),
(3, 'Arusha City Inn',   'Arusha, Tanzania', 'Budget-friendly inn in the city center',        'hotel3.jpg');

-- Sample rooms
INSERT INTO rooms (hotel_id, room_type, price_per_night, capacity, description) VALUES
(1, 'Single Standard', 75000.00,  1, 'Single bed, AC, WiFi, breakfast included'),
(1, 'Double Deluxe',   120000.00, 2, 'Queen bed, balcony, mountain view'),
(1, 'Family Suite',    250000.00, 4, '2 bedrooms, living room, kitchenette'),
(2, 'Single Room',     60000.00,  1, 'Basic single room with WiFi'),
(2, 'Double Room',     95000.00,  2, 'Comfortable double with hot shower'),
(3, 'Budget Single',   35000.00,  1, 'Affordable, clean, central location'),
(3, 'Budget Double',   55000.00,  2, 'Two singles, shared bathroom');

-- One sample existing booking so the conflict-prevention demo has something to clash with
INSERT INTO bookings (user_id, room_id, check_in, check_out, total_price, status) VALUES
(5, 2, '2026-06-01', '2026-06-03', 240000.00, 'confirmed');
