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
