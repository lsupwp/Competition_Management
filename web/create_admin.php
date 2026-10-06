#!/usr/bin/env php
<?php
/**
 * Create Super Admin Account
 * 
 * Usage: php create_admin.php
 * 
 * This script creates a super admin account with the following credentials:
 * - Email: admin@teamcomp.local
 * - Password: Admin@123456
 * - Name: Super Admin
 * 
 * IMPORTANT: Change these credentials after first use!
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap_env.php';

use App\Services\Database;

// Admin credentials
$adminEmail = 'admin@teamcomp.local';
$adminPassword = 'Admin@123456';
$adminName = 'Super Admin';

echo "Creating super admin account...\n";
echo "Email: $adminEmail\n";
echo "Password: $adminPassword\n";
echo "Name: $adminName\n\n";

try {
    $db = Database::getInstance();
    
    // Check if admin already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $adminEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Admin account already exists!\n";
        echo "Updating existing account to admin role...\n";
        
        $stmt = $db->prepare("UPDATE users SET role = 'admin', email_verified_at = NOW() WHERE email = ?");
        $stmt->bind_param('s', $adminEmail);
        $stmt->execute();
        
        echo "Done! Admin role updated.\n";
    } else {
        // Create new admin account
        $passwordHash = password_hash($adminPassword, PASSWORD_ARGON2ID);
        
        $stmt = $db->prepare("
            INSERT INTO users (email, name, password_hash, role, email_verified_at) 
            VALUES (?, ?, ?, 'admin', NOW())
        ");
        $stmt->bind_param('sss', $adminEmail, $adminName, $passwordHash);
        
        if ($stmt->execute()) {
            echo "Super admin account created successfully!\n";
            echo "User ID: " . $stmt->insert_id . "\n";
        } else {
            echo "Error creating admin account: " . $stmt->error . "\n";
            exit(1);
        }
    }
    
    $stmt->close();
    
    echo "\nIMPORTANT: Change these credentials after first login!\n";
    echo "You can now login at http://localhost:8000/auth/login\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
