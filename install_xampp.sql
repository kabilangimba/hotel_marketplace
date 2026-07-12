-- ============================================================
-- ONE-CLICK INSTALLER for XAMPP (Windows) — import this ONE file
-- in phpMyAdmin (Import tab) or: mysql -u root < install_xampp.sql
-- It builds the database, loads the real Moshi/Arusha hotels,
-- and adds reviews/wishlist/amenities/trip add-ons.
-- ============================================================

-- ===== PART 1: schema + base demo data =====
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

-- ===== PART 2: real hotels (Moshi & Arusha) + rooms =====
-- =====================================================
-- REAL HOTEL DATA SEED — Moshi & Arusha, Tanzania
-- Populates the marketplace with real, existing hotels
-- (verified names, locations, official websites, ratings)
-- Images are license-free Unsplash stock photos used as
-- representative imagery (hotels' own photos are copyrighted).
-- Run:  mysql -u hoteluser -p hotel_marketplace < seed_real_hotels.sql
-- =====================================================
USE hotel_marketplace;

-- ---- 1. Extend the hotels table with real-world fields ----
ALTER TABLE hotels
    ADD COLUMN IF NOT EXISTS website VARCHAR(255) NULL AFTER image,
    ADD COLUMN IF NOT EXISTS phone   VARCHAR(40)  NULL AFTER website,
    ADD COLUMN IF NOT EXISTS rating  DECIMAL(2,1) NULL AFTER phone;

-- ---- 2. Wipe demo hotels (cascades to rooms & bookings) ----
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM bookings;
DELETE FROM rooms;
DELETE FROM hotels;
ALTER TABLE hotels   AUTO_INCREMENT = 1;
ALTER TABLE rooms    AUTO_INCREMENT = 1;
ALTER TABLE bookings AUTO_INCREMENT = 1;
SET FOREIGN_KEY_CHECKS = 1;

-- ---- 3. Insert real hotels (managers 2 & 3 from seed data) ----
-- image column now holds a full https URL.
INSERT INTO hotels (manager_id, name, location, description, image, website, phone, rating) VALUES
-- ===================== MOSHI =====================
(2, 'Kilimanjaro Wonders Hotel', 'Moshi, Tanzania',
 'One of the first 4-star boutique hotels in Moshi, with spacious rooms, hot showers, free Wi-Fi and a rooftop restaurant and bar offering stunning views of Mount Kilimanjaro.',
 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?auto=format&fit=crop&w=800&q=80',
 'https://kiliwonders.com/', '+255 754 000 100', 4.5),

(2, 'Sal Salinero Hotel', 'Moshi, Tanzania',
 'A boutique hotel established in 2004 in the heart of Moshi, set in tropical gardens with a swimming pool, restaurant and bar — an elegant base before a Kilimanjaro climb.',
 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=80',
 'https://www.salsalinerohotel.com/', '+255 766 000 200', 4.5),

(2, 'Ameg Lodge Kilimanjaro', 'Moshi, Tanzania',
 'A relaxed mid-range lodge with garden cottages, a year-round outdoor pool, fitness centre and free Wi-Fi, with views toward Mount Kilimanjaro.',
 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=800&q=80',
 'https://ameglodge.com/', '+255 784 000 300', 4.0),

(3, 'Parkview Inn', 'Moshi, Tanzania',
 'A centrally located, secure hotel offering comfortable, budget-friendly rooms and a restaurant with vegetarian options, within easy reach of Moshi town centre.',
 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?auto=format&fit=crop&w=800&q=80',
 'https://parkviewinn.co.tz/', '+255 759 000 400', 4.0),

-- ===================== ARUSHA =====================
(3, 'Gran Melia Arusha', 'Arusha, Tanzania',
 'A five-star luxury hotel set between the Serengeti and Kilimanjaro, with personalised butler service, an infinity pool, signature restaurant, spa and wellness centre.',
 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=800&q=80',
 'https://www.melia.com/en/hotels/tanzania/arusha/gran-melia-arusha', '+255 272 970 000', 5.0),

(3, 'Mount Meru Hotel', 'Arusha, Tanzania',
 'A landmark 4-star hotel with 178 rooms and suites, two restaurants, two bars, an outdoor pool and a gym, overlooking landscaped gardens and Mount Meru.',
 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=800&q=80',
 'https://www.mountmeruhotel.co.tz/', '+255 272 970 100', 4.0),

(2, 'Arusha Serena Hotel', 'Arusha, Tanzania',
 'A gracious colonial-style retreat on the forested shores of Lake Duluti, with cottage rooms, landscaped gardens and a pool — a serene gateway to the northern safari circuit.',
 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=800&q=80',
 'https://www.serenahotels.com/arusha', '+255 272 504 155', 4.5),

(2, 'Kibo Palace Hotel', 'Arusha, Tanzania',
 'A contemporary city-centre hotel with modern rooms, a swimming pool, spa and conference facilities, popular with business travellers and safari guests.',
 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=800&q=80',
 'https://www.kibopalacehotel.com/', '+255 272 543 500', 4.0);

-- ---- 4. Real-style rooms & services per hotel (TZS / night) ----
-- Kilimanjaro Wonders Hotel (id 1)
INSERT INTO rooms (hotel_id, room_type, price_per_night, capacity, description) VALUES
(1, 'Standard Double', 165000.00, 2, 'King or twin beds, en-suite hot shower, AC, free Wi-Fi, breakfast included.'),
(1, 'Deluxe Kilimanjaro View', 230000.00, 2, 'Spacious room with private balcony and direct views of Mount Kilimanjaro.'),
(1, 'Family Suite', 360000.00, 4, 'Two-room suite with sitting area, ideal for families before or after a climb.');

-- Sal Salinero Hotel (id 2)
INSERT INTO rooms (hotel_id, room_type, price_per_night, capacity, description) VALUES
(2, 'Garden Standard', 150000.00, 2, 'Comfortable double opening onto the tropical garden, Wi-Fi and breakfast.'),
(2, 'Superior Poolside', 210000.00, 2, 'Upgraded room steps from the swimming pool, with seating area and minibar.'),
(2, 'Junior Suite', 320000.00, 3, 'Large suite with lounge, perfect for a relaxed pre-safari stay.');

-- Ameg Lodge Kilimanjaro (id 3)
INSERT INTO rooms (hotel_id, room_type, price_per_night, capacity, description) VALUES
(3, 'Cottage Single', 95000.00, 1, 'Cozy garden cottage with private bathroom and Wi-Fi.'),
(3, 'Cottage Double', 140000.00, 2, 'Double garden cottage near the pool and fitness centre.'),
(3, 'Family Cottage', 220000.00, 4, 'Two-bedroom cottage with mountain views, great for families.');

-- Parkview Inn (id 4)
INSERT INTO rooms (hotel_id, room_type, price_per_night, capacity, description) VALUES
(4, 'Budget Single', 55000.00, 1, 'Clean, affordable single room with Wi-Fi in central Moshi.'),
(4, 'Standard Double', 85000.00, 2, 'Comfortable double with en-suite bathroom and breakfast.');

-- Gran Melia Arusha (id 5)
INSERT INTO rooms (hotel_id, room_type, price_per_night, capacity, description) VALUES
(5, 'Deluxe Room', 520000.00, 2, 'Five-star deluxe room with premium bedding, minibar and city or garden view.'),
(5, 'The Level Suite', 850000.00, 2, 'Exclusive suite with personalised butler service and infinity-pool access.'),
(5, 'Presidential Suite', 1800000.00, 4, 'Top-tier suite with expansive living space, terrace and panoramic views.');

-- Mount Meru Hotel (id 6)
INSERT INTO rooms (hotel_id, room_type, price_per_night, capacity, description) VALUES
(6, 'Superior Room', 260000.00, 2, 'Well-appointed room overlooking the gardens, with AC and 24-hour room service.'),
(6, 'Executive Room', 380000.00, 2, 'Upgraded room with lounge access and views toward Mount Meru.'),
(6, 'Diplomatic Suite', 720000.00, 3, 'Spacious suite with separate living area and premium amenities.');

-- Arusha Serena Hotel (id 7)
INSERT INTO rooms (hotel_id, room_type, price_per_night, capacity, description) VALUES
(7, 'Cottage Double', 340000.00, 2, 'Colonial-style cottage room set in gardens by Lake Duluti.'),
(7, 'Lake View Cottage', 450000.00, 2, 'Cottage with views over the forested lake and surrounding hills.'),
(7, 'Family Cottage', 620000.00, 4, 'Two-room cottage for families exploring the northern safari circuit.');

-- Kibo Palace Hotel (id 8)
INSERT INTO rooms (hotel_id, room_type, price_per_night, capacity, description) VALUES
(8, 'Standard Double', 175000.00, 2, 'Modern city-centre room with AC, Wi-Fi and breakfast included.'),
(8, 'Executive Room', 250000.00, 2, 'Larger room with work desk, lounge area and pool access.'),
(8, 'Suite', 420000.00, 3, 'Generous suite with separate sitting room and spa access.');

-- ---- 5. One sample booking so the double-booking demo still works ----
INSERT INTO bookings (user_id, room_id, check_in, check_out, total_price, status) VALUES
(5, 2, '2026-06-01', '2026-06-03', 460000.00, 'confirmed');

-- ===== PART 3: reviews, wishlist, amenities, gallery, add-ons =====
-- =====================================================
-- GUEST-EXPERIENCE UPGRADE — reviews, wishlist, amenities,
-- photo gallery, and Safari/Kilimanjaro booking add-ons.
-- Run:  mysql -u hoteluser -p hotel_marketplace < features_upgrade.sql
-- =====================================================
USE hotel_marketplace;

-- ---- Guest reviews (one per user per hotel) ----
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hotel_id INT NOT NULL,
    user_id  INT NOT NULL,
    rating   TINYINT NOT NULL,           -- 1..5 stars
    comment  TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    UNIQUE KEY uniq_user_hotel (user_id, hotel_id),
    CHECK (rating BETWEEN 1 AND 5)
);

-- ---- Wishlist / saved hotels ----
CREATE TABLE IF NOT EXISTS favorites (
    user_id  INT NOT NULL,
    hotel_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, hotel_id),
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
);

-- ---- Richer property pages: amenities + photo gallery (CSV columns) ----
ALTER TABLE hotels
    ADD COLUMN IF NOT EXISTS amenities TEXT NULL AFTER rating,   -- e.g. "WiFi,Pool,Restaurant"
    ADD COLUMN IF NOT EXISTS gallery   TEXT NULL AFTER amenities; -- CSV of image URLs

-- ---- Trip add-ons captured on a booking ----
ALTER TABLE bookings
    ADD COLUMN IF NOT EXISTS addons       TEXT NULL AFTER total_price,             -- CSV of add-on keys
    ADD COLUMN IF NOT EXISTS addons_total DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER addons;

-- ---- Seed amenities + extra gallery photos for the 8 real hotels ----
UPDATE hotels SET amenities = 'WiFi,Restaurant,Bar,Mountain View,Breakfast,Room Service,Airport Shuttle',
    gallery = 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?auto=format&fit=crop&w=800&q=80,https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=800&q=80'
    WHERE name = 'Kilimanjaro Wonders Hotel';
UPDATE hotels SET amenities = 'WiFi,Pool,Restaurant,Bar,Garden,Breakfast,AC',
    gallery = 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?auto=format&fit=crop&w=800&q=80,https://images.unsplash.com/photo-1582719508461-905c673771fd?auto=format&fit=crop&w=800&q=80'
    WHERE name = 'Sal Salinero Hotel';
UPDATE hotels SET amenities = 'WiFi,Pool,Gym,Restaurant,Parking,Mountain View',
    gallery = 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?auto=format&fit=crop&w=800&q=80'
    WHERE name = 'Ameg Lodge Kilimanjaro';
UPDATE hotels SET amenities = 'WiFi,Restaurant,Parking,Breakfast,Family Rooms',
    gallery = 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=800&q=80'
    WHERE name = 'Parkview Inn';
UPDATE hotels SET amenities = 'WiFi,Pool,Spa,Gym,Restaurant,Bar,Room Service,Airport Shuttle,AC,Breakfast',
    gallery = 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=80,https://images.unsplash.com/photo-1578683010236-d716f9a3f461?auto=format&fit=crop&w=800&q=80'
    WHERE name = 'Gran Melia Arusha';
UPDATE hotels SET amenities = 'WiFi,Pool,Gym,Restaurant,Bar,Parking,Room Service,AC',
    gallery = 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?auto=format&fit=crop&w=800&q=80'
    WHERE name = 'Mount Meru Hotel';
UPDATE hotels SET amenities = 'WiFi,Pool,Restaurant,Bar,Garden,Breakfast',
    gallery = 'https://images.unsplash.com/photo-1602002418816-5c0aeef426aa?auto=format&fit=crop&w=800&q=80'
    WHERE name = 'Arusha Serena Hotel';
UPDATE hotels SET amenities = 'WiFi,Pool,Spa,Restaurant,Parking,Room Service,AC,Breakfast',
    gallery = 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=800&q=80'
    WHERE name = 'Kibo Palace Hotel';
