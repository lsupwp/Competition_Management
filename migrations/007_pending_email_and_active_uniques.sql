-- Pending email change: keep current email verified until new address confirms
-- Also enforce one active membership/registration (NULL deleted_at uniqueness)

ALTER TABLE users
    ADD COLUMN pending_email VARCHAR(255) NULL AFTER email,
    ADD INDEX idx_pending_email (pending_email);

-- One active team membership per user
ALTER TABLE team_members
    DROP INDEX unique_team_user,
    ADD COLUMN active_slot TINYINT
        GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) VIRTUAL,
    ADD UNIQUE KEY unique_active_team_user (team_id, user_id, active_slot);

-- One active event registration per user
ALTER TABLE event_registrations
    DROP INDEX unique_event_user,
    ADD COLUMN active_slot TINYINT
        GENERATED ALWAYS AS (IF(deleted_at IS NULL, 1, NULL)) VIRTUAL,
    ADD UNIQUE KEY unique_active_event_user (event_id, user_id, active_slot);
