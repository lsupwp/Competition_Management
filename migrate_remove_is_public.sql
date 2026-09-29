-- Migration: Remove is_public column from teams table
-- Run this on existing databases to remove public/private visibility

-- Drop is_public column from teams table
ALTER TABLE teams DROP COLUMN IF EXISTS is_public;

-- Drop is_public index (if exists)
ALTER TABLE teams DROP INDEX IF EXISTS idx_is_public;
