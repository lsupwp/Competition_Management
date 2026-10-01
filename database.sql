-- Team Competition Management System Database Schema
-- Single source of truth (fresh installs via compose initdb / re-import this file).
-- Soft delete: ใช้ deleted_at (NULL = active, timestamp = deleted)
-- Timestamps: created_at, updated_at ทุก table

-- =====================================================
-- USERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255),
    avatar_url VARCHAR(500),
    role ENUM('user', 'admin') DEFAULT 'user',
    email_verified_at TIMESTAMP NULL,
    verification_token VARCHAR(255) NULL,
    verification_token_expires_at TIMESTAMP NULL,
    password_reset_token VARCHAR(255) NULL,
    password_reset_token_expires_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_verification_token (verification_token),
    INDEX idx_password_reset_token (password_reset_token),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TEAMS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    logo_url VARCHAR(500),
    max_members INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_owner_id (owner_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TEAM MEMBERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_team_user (team_id, user_id, deleted_at),
    INDEX idx_team_id (team_id),
    INDEX idx_user_id (user_id),
    INDEX idx_role (role),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TEAM INVITATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS team_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    invited_by INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) COMMENT 'Optional: invite specific email',
    role ENUM('admin', 'member') DEFAULT 'member',
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_team_id (team_id),
    INDEX idx_email (email),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used_at (used_at),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVENTS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_by INT NOT NULL,
    team_id INT NULL COMMENT 'Team creating event',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(500),
    required_members INT DEFAULT 3 COMMENT 'Members needed per team',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    INDEX idx_created_by (created_by),
    INDEX idx_team_id (team_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVENT DATES TABLE
-- วันที่ของ event (เพิ่มได้หลายวัน เช่น วันแข่ง, วันสิ้นสุดลงทะเบียน, วันประชุม)
-- รองรับช่วงเวลา: start_datetime - end_datetime
-- =====================================================
CREATE TABLE IF NOT EXISTS event_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    date_type VARCHAR(100) NOT NULL COMMENT 'ประเภทวัน เช่น competition, registration_deadline, meeting, etc.',
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    description VARCHAR(255) COMMENT 'คำอธิบายเพิ่มเติม',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id),
    INDEX idx_date_type (date_type),
    INDEX idx_start_datetime (start_datetime),
    INDEX idx_end_datetime (end_datetime),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVENT TAGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS event_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'เช่น สนใจ, ลงแล้ว, รอ, ฯลฯ',
    color VARCHAR(7) DEFAULT '#3b82f6' COMMENT 'Hex color',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id),
    INDEX idx_name (name),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVENT REGISTRATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    team_id INT NULL COMMENT 'NULL = ลงคนเดียว, มีค่า = ลงเป็นทีม',
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL,
    UNIQUE KEY unique_event_user (event_id, user_id, deleted_at),
    INDEX idx_event_id (event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_team_id (team_id),
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVENT VISIBILITY TABLE
-- กำหนดว่าใครเห็นงานบ้าง (สำหรับงาน private)
-- =====================================================
CREATE TABLE IF NOT EXISTS event_visibility (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL COMMENT 'User ที่เห็นงานนี้',
    granted_by INT NOT NULL COMMENT 'User ที่ให้สิทธิ์',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_user (event_id, user_id, deleted_at),
    INDEX idx_event_id (event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ACTIVITY LOGS TABLE
-- =====================================================
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

-- =====================================================
-- SESSIONS TABLE (optional - สำหรับ session management)
-- =====================================================
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PURGE SOFT-DELETED ROWS (every 5 minutes)
-- Requires event_scheduler=ON (compose.yaml mariadb command)
-- =====================================================
DROP EVENT IF EXISTS purge_soft_deleted_event;
DROP PROCEDURE IF EXISTS purge_soft_deleted;

DELIMITER //

CREATE PROCEDURE purge_soft_deleted()
BEGIN
    DELETE FROM event_visibility WHERE deleted_at IS NOT NULL;
    DELETE FROM event_dates WHERE deleted_at IS NOT NULL;
    DELETE FROM event_tags WHERE deleted_at IS NOT NULL;
    DELETE FROM event_registrations WHERE deleted_at IS NOT NULL;
    DELETE FROM events WHERE deleted_at IS NOT NULL;
    DELETE FROM team_invitations WHERE deleted_at IS NOT NULL;
    DELETE FROM team_members WHERE deleted_at IS NOT NULL;
    DELETE FROM teams WHERE deleted_at IS NOT NULL;
    DELETE FROM users WHERE deleted_at IS NOT NULL;
END //

DELIMITER ;

CREATE EVENT purge_soft_deleted_event
ON SCHEDULE EVERY 5 MINUTE
STARTS CURRENT_TIMESTAMP
ON COMPLETION PRESERVE
ENABLE
DO CALL purge_soft_deleted();
