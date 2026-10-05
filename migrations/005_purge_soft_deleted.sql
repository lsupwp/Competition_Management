-- Hard-delete soft-deleted rows every 5 minutes (MariaDB EVENT)
-- Requires: event_scheduler=ON (set in compose.yaml)

DROP EVENT IF EXISTS purge_soft_deleted_event;
DROP PROCEDURE IF EXISTS purge_soft_deleted;

DELIMITER //

CREATE PROCEDURE purge_soft_deleted()
BEGIN
    -- Children first, then parents
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
