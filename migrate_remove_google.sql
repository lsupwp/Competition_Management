-- Migration: Remove Google OAuth support
-- Run this on existing databases to remove google_id column

-- Drop google_id column from users table
ALTER TABLE users DROP COLUMN IF EXISTS google_id;

-- Drop google_id index (if exists)
ALTER TABLE users DROP INDEX IF EXISTS idx_google_id;