<?php
// Route: /team/invite
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /team/manage');
    exit;
}

// Validate CSRF token
if (!\App\Services\CsrfService::validateToken($_POST['csrf_token'] ?? null)) {
    $_SESSION['flash_error'] = 'Invalid security token. Please try again.';
    header('Location: /team/manage');
    exit;
}

$action = $_POST['action'] ?? '';
$teamId = \App\Services\IdEncoder::decode($_POST['team_id'] ?? '');

if (!$teamId) {
    $_SESSION['flash_error'] = 'Invalid team ID';
    header('Location: /team/manage');
    exit;
}

$teamController = new \App\Controllers\TeamController();

if ($action === 'invite_email') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash_error'] = 'Invalid email address';
        header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
        exit;
    }
    
    $result = $teamController->generateInviteToken($teamId, $_SESSION['user']['id'], $email);
    
    if ($result['success']) {
        $_SESSION['flash_success'] = "Invitation sent to $email";
    } else {
        $_SESSION['flash_error'] = $result['error'];
    }
    
} elseif ($action === 'generate_token') {
    // Check if active token already exists
    $db = \App\Services\Database::getInstance();
    $stmt = $db->prepare("
        SELECT id FROM team_invitations
        WHERE team_id = ? AND deleted_at IS NULL AND used_at IS NULL AND expires_at > NOW() AND email IS NULL
        LIMIT 1
    ");
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['flash_error'] = 'An active invite token already exists. Revoke it first to generate a new one.';
        header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
        exit;
    }
    $stmt->close();
    
    $result = $teamController->generateInviteToken($teamId, $_SESSION['user']['id']);
    
    if ($result['success']) {
        $_SESSION['invite_token'] = $result['token'];
        $_SESSION['invite_token_expires'] = $result['expires_at'];
        $_SESSION['flash_success'] = 'Invite token generated successfully';
        header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId) . '&show_token=1');
        exit;
    } else {
        $_SESSION['flash_error'] = $result['error'];
    }
}

header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($teamId));
exit;
