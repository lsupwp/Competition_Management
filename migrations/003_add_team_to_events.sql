-- Migration: Add team_id and required_members to events table
-- Date: 2026-09-30
-- Description: Events now tied to teams with member selection for visibility

ALTER TABLE events 
ADD COLUMN team_id INT NULL AFTER created_by,
ADD COLUMN required_members INT DEFAULT 3 AFTER location,
ADD INDEX idx_team_id (team_id),
ADD FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE SET NULL;
