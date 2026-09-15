<?php
// Route: /auth/google/callback
require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Controllers\AuthController;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$originalUserId = $_SESSION['user']['id'] ?? null;

if (!isset($_GET['code'])) {
    header('Location: /auth/login');
    exit;
}

$authController = new AuthController();
$result = $authController->handleGoogleCallback($_GET['code']);

if ($result['success']) {
    $source = $_SESSION['google_auth_source'] ?? 'login';
    
    if ($source === 'settings_link') {
        // Check if Google account is already linked to current user
        $linkedUserId = $result['user_id'] ?? null;
        
        if ($linkedUserId === $originalUserId) {
            // Already linked to current user
            $_SESSION['settings_flash'] = ['error' => 'This Google account is already linked to your account.'];
        } else {
            // Already linked to another user
            $_SESSION['settings_flash'] = ['error' => 'This Google account is already linked to another user.'];
        }
        session_write_close();
        header('Location: /settings');
        exit;
    } elseif ($source === 'settings_email') {
        $pendingData = $_SESSION['pending_email_change'] ?? null;
        if ($pendingData && isset($pendingData['expected_google_id'])) {
            $db = \App\Services\Database::getInstance();
            $stmt = $db->prepare("SELECT google_id FROM users WHERE id = ? AND deleted_at IS NULL");
            $stmt->bind_param('i', $_SESSION['user']['id']);
            $stmt->execute();
            $userResult = $stmt->get_result();
            $currentUser = $userResult->fetch_assoc();
            $stmt->close();
            
            if ($currentUser['google_id'] === $pendingData['expected_google_id']) {
                session_write_close();
                header('Location: /settings?email_changed=1');
            } else {
                unset($_SESSION['pending_email_change']);
                $_SESSION['flash_error'] = 'Invalid Google account. Please use your linked Google account.';
                session_write_close();
                header('Location: /settings');
            }
        } else {
            unset($_SESSION['pending_email_change']);
            $_SESSION['flash_error'] = 'Invalid request.';
            session_write_close();
            header('Location: /settings');
        }
    } else {
        session_write_close();
        header('Location: ' . $result['redirect']);
    }
} elseif (isset($result['user_not_found']) && $result['user_not_found']) {
    $source = $_SESSION['google_auth_source'] ?? 'login';
    unset($_SESSION['google_auth_source']);
    
    if ($source === 'settings_link') {
        // Link Google account to current user (Google account not linked to anyone)
        $userInfo = $result['user_info'] ?? null;
        if ($userInfo && $originalUserId) {
            $db = \App\Services\Database::getInstance();
            $stmt = $db->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $stmt->bind_param('si', $userInfo['google_id'], $originalUserId);
            $stmt->execute();
            $stmt->close();
            
            $_SESSION['settings_flash'] = ['success' => 'Google account linked successfully'];
            session_write_close();
            header('Location: /settings');
            exit;
        }
    } elseif ($source === 'settings_email') {
        unset($_SESSION['pending_email_change']);
        $_SESSION['flash_error'] = 'Invalid Google account. Please use your linked Google account.';
        session_write_close();
        header('Location: /settings');
    } elseif ($source === 'register') {
        $createResult = $authController->createAccountFromGoogle();
        session_write_close();
        if ($createResult['success']) {
            header('Location: ' . $createResult['redirect']);
        } else {
            $_SESSION['flash_error'] = $createResult['error'] ?? 'Failed to create account';
            header('Location: /auth/register');
        }
    } else {
        session_write_close();
        header('Location: /auth/login?google_signup=1');
    }
} elseif (isset($result['requires_linking']) && $result['requires_linking']) {
    $source = $_SESSION['google_auth_source'] ?? 'login';
    
    if ($source === 'settings_link') {
        // Auto-link Google account to current user
        $pendingData = $_SESSION['pending_google_link'] ?? null;
        if ($pendingData && $pendingData['existing_user_id'] === $originalUserId) {
            $db = \App\Services\Database::getInstance();
            $stmt = $db->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $stmt->bind_param('si', $pendingData['google_id'], $originalUserId);
            $stmt->execute();
            $stmt->close();
            
            unset($_SESSION['pending_google_link']);
            $_SESSION['settings_flash'] = ['success' => 'Google account linked successfully'];
            session_write_close();
            header('Location: /settings');
            exit;
        } else {
            // Google account email belongs to another user
            unset($_SESSION['pending_google_link']);
            $_SESSION['settings_flash'] = ['error' => 'This Google account email is already associated with another user.'];
            session_write_close();
            header('Location: /settings');
            exit;
        }
    }
    
    if ($source === 'settings_email') {
        unset($_SESSION['pending_email_change']);
        $_SESSION['flash_error'] = 'Invalid Google account. Please use your linked Google account.';
        session_write_close();
        header('Location: /settings');
    } else {
        session_write_close();
        header('Location: /auth/link-account');
    }
} else {
    $source = $_SESSION['google_auth_source'] ?? 'login';
    $_SESSION['flash_error'] = $result['error'];
    session_write_close();
    
    if ($source === 'settings_email') {
        unset($_SESSION['pending_email_change']);
        header('Location: /settings');
    } else {
        header('Location: /auth/login');
    }
}
exit;
