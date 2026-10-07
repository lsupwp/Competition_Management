-- Remove schema objects never used by application code.
-- PHP sessions are file/cookie based (SessionService), not the sessions table.
-- last_login_at was never read or written.

ALTER TABLE users DROP COLUMN IF EXISTS last_login_at;

DROP TABLE IF EXISTS sessions;
