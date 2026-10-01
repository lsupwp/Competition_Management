-- Dedicated password-reset tokens (separate from email verification)
ALTER TABLE users
    ADD COLUMN password_reset_token VARCHAR(255) NULL AFTER verification_token_expires_at,
    ADD COLUMN password_reset_token_expires_at TIMESTAMP NULL AFTER password_reset_token,
    ADD INDEX idx_password_reset_token (password_reset_token);
