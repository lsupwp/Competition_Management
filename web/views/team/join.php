<?php
// Route: /team/join
require_once __DIR__ . '/../../vendor/autoload.php';

\App\Services\SessionService::start();

if (!isset($_SESSION['user'])) {
    $_SESSION['redirect_after_login'] = '/team/join' . (isset($_GET['token']) ? '?token=' . urlencode($_GET['token']) : '');
    header('Location: /auth/login');
    exit;
}

$title = 'Join Team - Team Competition';

$token = $_GET['token'] ?? null;
$error = '';
$success = '';
$teamInfo = null;

// If token provided, validate and show team info
if ($token) {
    $db = \App\Services\Database::getInstance();
    
    // Get invitation details
    $stmt = $db->prepare("
        SELECT ti.id, ti.team_id, ti.email, ti.role, ti.expires_at, ti.used_at, 
               t.name as team_name, t.logo_url
        FROM team_invitations ti
        JOIN teams t ON t.id = ti.team_id
        WHERE ti.token = ? AND ti.deleted_at IS NULL AND t.deleted_at IS NULL
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $invitation = $result->fetch_assoc();
    $stmt->close();
    
    if (!$invitation) {
        $error = 'Invalid invitation token';
    } elseif ($invitation['used_at'] !== null) {
        $error = 'This invitation has already been used';
    } elseif (strtotime($invitation['expires_at']) < time()) {
        $error = 'This invitation has expired';
    } elseif ($invitation['email'] && $invitation['email'] !== $_SESSION['user']['email']) {
        $error = 'This invitation is for a different email address';
    } else {
        // Check if already member
        $stmt = $db->prepare("
            SELECT id FROM team_members
            WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $invitation['team_id'], $_SESSION['user']['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'You are already a member of this team';
        } else {
            $teamInfo = [
                'name' => $invitation['team_name'],
                'logo_url' => \App\Services\UploadUrl::existing($invitation['logo_url'] ?? null),
                'role' => $invitation['role']
            ];
        }
        $stmt->close();
    }
}

// Handle POST - accept invitation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token) {
    // Validate CSRF token
    if (!\App\Services\CsrfService::validateToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $teamController = new \App\Controllers\TeamController();
        $result = $teamController->acceptInvitation($token, $_SESSION['user']['id']);
        
        if ($result['success']) {
            $_SESSION['flash_success'] = 'You have successfully joined the team!';
            header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($result['team_id']));
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

// Handle POST - join with token (manual entry)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token']) && !$token) {
    // Validate CSRF token
    if (!\App\Services\CsrfService::validateToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $teamController = new \App\Controllers\TeamController();
        $result = $teamController->acceptInvitation($_POST['token'], $_SESSION['user']['id']);
        
        if ($result['success']) {
            $_SESSION['flash_success'] = 'You have successfully joined the team!';
            header('Location: /team/manage?id=' . \App\Services\IdEncoder::encode($result['team_id']));
            exit;
        } else {
            $error = $result['error'];
            $token = $_POST['token']; // Keep the token in the field
        }
    }
}

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-3xl font-bold mb-8">Join Team</h1>

    <?php if ($error): ?>
        <div class="alert alert-error mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($teamInfo): ?>
        <!-- Show team info and accept button -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-2xl mb-4">You're Invited!</h2>
                
                <div class="flex items-center gap-4 mb-6">
                    <div class="avatar">
                        <div class="w-16 rounded-full bg-primary text-primary-content flex items-center justify-center text-2xl font-bold">
                            <?php if (!empty($teamInfo['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($teamInfo['logo_url']) ?>" alt="<?= htmlspecialchars($teamInfo['name']) ?>" class="w-full h-full object-cover" />
                            <?php else: ?>
                                <?= strtoupper(substr($teamInfo['name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold"><?= htmlspecialchars($teamInfo['name']) ?></h3>
                        <p class="text-sm text-base-content/70">Role: <span class="badge badge-primary"><?= ucfirst($teamInfo['role']) ?></span></p>
                    </div>
                </div>

                <form method="POST">
                    <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                    <button type="submit" class="btn btn-primary w-full">Accept Invitation</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Manual token entry -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title text-xl mb-4">Enter Invitation Token</h2>
                <p class="text-base-content/70 mb-6">Enter the invitation token or click the link from your email to join a team.</p>
                
                <form method="POST" class="space-y-4">
                    <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text">Invitation Token</span>
                        </label>
                        <input type="text" name="token" value="<?= htmlspecialchars($token ?? '') ?>" 
                               class="input input-bordered w-full" placeholder="Paste your invitation token here" required />
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-full">Join Team</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
