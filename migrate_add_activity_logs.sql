-- Migration: Add activity_logs table
-- Run this on existing databases to add activity logging

-- Create activity_logs table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL for system actions',
    action VARCHAR(100) NOT NULL COMMENT 'e.g., team.create, team.member.kick, auth.login',
    entity_type VARCHAR(50) NULL COMMENT 'e.g., team, user, event',
    entity_id INT NULL COMMENT 'ID of the affected entity',
    description TEXT NOT NULL,
    metadata JSON NULL COMMENT 'Additional context data',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
