-- Migration: Add role column to users table
-- Date: 2026-03-17
-- Description: Add role column to support user/admin roles

ALTER TABLE users 
ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user' AFTER avatar_url,
ADD INDEX idx_role (role);
