-- Create Super Admin Account
-- Email: admin@teamcomp.local
-- Password: Admin@123456

INSERT INTO users (email, name, password_hash, role, email_verified_at) 
VALUES (
    'admin@teamcomp.local', 
    'Super Admin', 
    '$argon2id$v=19$m=65536,t=4,p=1$ZVdQd0hKZ0xNVEhKWjJGTw$qK8m5hJ8vL9xW2yP3nR4tU6vI7cB8dE9fG0hJ1kL2mN', 
    'admin', 
    NOW()
);
