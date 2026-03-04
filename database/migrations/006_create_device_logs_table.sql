-- Migration: Create Device Logs Table
-- Created: 2026-02-25
-- Description: Stores device activity and system logs

CREATE TABLE IF NOT EXISTS device_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id INT UNSIGNED NOT NULL,
    log_type ENUM('info', 'warning', 'error', 'debug') DEFAULT 'info',
    event VARCHAR(100) NOT NULL,
    message TEXT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_device_id (device_id),
    INDEX idx_log_type (log_type),
    INDEX idx_event (event),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
