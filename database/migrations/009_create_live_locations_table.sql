-- Migration: Create Live Locations Table
-- Created: 2026-02-27
-- Description: Stores only the LATEST location for each device (live tracking)
-- One row per device - updated continuously

CREATE TABLE IF NOT EXISTS live_locations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy DECIMAL(8, 2) NULL COMMENT 'GPS accuracy in meters',
    altitude DECIMAL(10, 2) NULL COMMENT 'Altitude in meters',
    speed DECIMAL(8, 2) NULL COMMENT 'Speed in km/h',
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_device (device_id),
    INDEX idx_coordinates (latitude, longitude),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
