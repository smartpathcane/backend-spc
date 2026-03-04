-- Migration: Create Caretakers Table
-- Created: 2026-02-25
-- Description: Stores caretaker relationships with users

CREATE TABLE IF NOT EXISTS caretakers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL COMMENT 'The person being cared for',
    caretaker_user_id INT UNSIGNED NOT NULL COMMENT 'The caretaker user account',
    relationship VARCHAR(50) NULL COMMENT 'e.g., family, nurse, friend',
    permissions JSON NULL COMMENT 'Permissions as JSON array',
    can_view_location BOOLEAN DEFAULT TRUE,
    can_receive_alerts BOOLEAN DEFAULT TRUE,
    can_manage_device BOOLEAN DEFAULT FALSE,
    is_primary BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_caretaker (user_id, caretaker_user_id),
    INDEX idx_user_id (user_id),
    INDEX idx_caretaker_user_id (caretaker_user_id),
    INDEX idx_is_active (is_active),
    INDEX idx_is_primary (is_primary),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (caretaker_user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
