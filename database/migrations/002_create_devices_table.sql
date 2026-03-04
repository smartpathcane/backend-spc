-- Migration: Create Devices Table
-- Created: 2026-02-25
-- Description: Stores SmartPath Cane device information

CREATE TABLE IF NOT EXISTS devices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_serial VARCHAR(100) NOT NULL UNIQUE COMMENT 'Unique device serial number',
    device_name VARCHAR(100) NULL,
    device_model VARCHAR(50) DEFAULT 'SPC-001',
    user_id INT UNSIGNED NULL,
    status ENUM('active', 'inactive', 'maintenance', 'lost') DEFAULT 'inactive',
    firmware_version VARCHAR(20) NULL,
    battery_level INT UNSIGNED NULL COMMENT 'Battery percentage 0-100',
    last_location_lat DECIMAL(10, 8) NULL,
    last_location_lng DECIMAL(11, 8) NULL,
    last_location_at TIMESTAMP NULL,
    last_connected_at TIMESTAMP NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_device_serial (device_serial),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_last_location (last_location_lat, last_location_lng),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
