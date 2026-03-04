

CREATE TABLE IF NOT EXISTS locations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy DECIMAL(8, 2) NULL COMMENT 'GPS accuracy in meters',
    altitude DECIMAL(10, 2) NULL COMMENT 'Altitude in meters',
    speed DECIMAL(8, 2) NULL COMMENT 'Speed in km/h',
    heading DECIMAL(6, 2) NULL COMMENT 'Direction in degrees (0-360)',
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_device_id (device_id),
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_coordinates (latitude, longitude),
    INDEX idx_device_recorded (device_id, recorded_at),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
