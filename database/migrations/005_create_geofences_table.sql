-- Migration: Create Geofences Table
-- Created: 2026-02-25
-- Description: Stores safe zones and geofence areas

CREATE TABLE IF NOT EXISTS geofences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    device_id INT UNSIGNED NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    center_lat DECIMAL(10, 8) NOT NULL,
    center_lng DECIMAL(11, 8) NOT NULL,
    radius_meters INT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Radius in meters',
    type ENUM('safe_zone', 'restricted_zone') DEFAULT 'safe_zone',
    is_active BOOLEAN DEFAULT TRUE,
    notify_on_enter BOOLEAN DEFAULT FALSE,
    notify_on_exit BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_id (user_id),
    INDEX idx_device_id (device_id),
    INDEX idx_is_active (is_active),
    INDEX idx_type (type),
    INDEX idx_coordinates (center_lat, center_lng),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
