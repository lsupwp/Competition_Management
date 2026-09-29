<?php

namespace App\Controllers;

use App\Services\Database;
use App\Services\EmailService;
use App\Services\ActivityLogService;

class TeamController
{
    private $db;
    private $emailService;
    private $activityLog;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->emailService = new EmailService();
        $this->activityLog = new ActivityLogService();
    }

    /**
     * Generate invite token for team
     */
    public function generateInviteToken(int $teamId, int $userId, ?string $email = null): array
    {
        // Check if user is owner or admin of team
        if (!$this->canInvite($teamId, $userId)) {
            return ['success' => false, 'error' => 'Permission denied'];
        }

        // Generate unique token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days')); // 7 days expiry

        // Insert invitation
        $stmt = $this->db->prepare("
            INSERT INTO team_invitations (team_id, invited_by, token, email, role, expires_at)
            VALUES (?, ?, ?, ?, 'member', ?)
        ");
        $role = 'member'; // Tokens always invite as member
        $stmt->bind_param('iisss', $teamId, $userId, $token, $email, $expiresAt);
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Failed to create invitation'];
        }

        $invitationId = $this->db->insert_id;
        $stmt->close();

        // If email provided, send invitation email
        if ($email) {
            $teamName = $this->getTeamName($teamId);
            $this->emailService->sendInvitationEmail($email, $teamName, $token);
        }

        return [
            'success' => true,
            'token' => $token,
            'invitation_id' => $invitationId,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Accept invitation by token
     */
    public function acceptInvitation(string $token, int $userId): array
    {
        // Find valid invitation
        $stmt = $this->db->prepare("
            SELECT id, team_id, email, role, expires_at, used_at
            FROM team_invitations
            WHERE token = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $invitation = $result->fetch_assoc();
        $stmt->close();

        if (!$invitation) {
            return ['success' => false, 'error' => 'Invalid invitation token'];
        }

        // Check if expired
        if (strtotime($invitation['expires_at']) < time()) {
            return ['success' => false, 'error' => 'Invitation has expired'];
        }

        // Email invites are single-use
        $isEmailInvite = !empty($invitation['email']);
        
        if ($isEmailInvite && $invitation['used_at'] !== null) {
            return ['success' => false, 'error' => 'This invitation has already been used'];
        }

        // If email specified, check if current user matches
        if ($isEmailInvite && $invitation['email'] !== $this->getUserEmail($userId)) {
            return ['success' => false, 'error' => 'This invitation is for a different email address'];
        }

        // Check if already member
        if ($this->isTeamMember($invitation['team_id'], $userId)) {
            return ['success' => false, 'error' => 'You are already a member of this team'];
        }

        // Check team member limit
        $memberCount = $this->getTeamMemberCount($invitation['team_id']);
        $maxMembers = $this->getTeamMaxMembers($invitation['team_id']);
        
        if ($memberCount >= $maxMembers) {
            return ['success' => false, 'error' => 'Team has reached maximum member limit'];
        }

        // Add user to team
        $stmt = $this->db->prepare("
            INSERT INTO team_members (team_id, user_id, role)
            VALUES (?, ?, ?)
        ");
        $role = $invitation['role'];
        $stmt->bind_param('iis', $invitation['team_id'], $userId, $role);
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Failed to join team'];
        }
        $stmt->close();

        // Mark email invites as used (single-use)
        // Token invites remain reusable (no used_at set)
        if ($isEmailInvite) {
            $stmt = $this->db->prepare("
                UPDATE team_invitations 
                SET used_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param('i', $invitation['id']);
            $stmt->execute();
            $stmt->close();
        }

        // Get team name for logging
        $teamName = $this->getTeamName($invitation['team_id']);

        // Log activity
        $this->activityLog->log(
            'team.join',
            "Joined team '$teamName'",
            $userId,
            'team',
            $invitation['team_id'],
            ['role' => $role, 'via_invitation' => $isEmailInvite ? 'email' : 'token']
        );

        return [
            'success' => true,
            'team_id' => $invitation['team_id'],
            'role' => $role
        ];
    }

    /**
     * Revoke invitation token
     */
    public function revokeToken(int $teamId, int $userId, int $invitationId): array
    {
        // Get invitation details
        $stmt = $this->db->prepare("
            SELECT invited_by FROM team_invitations
            WHERE id = ? AND team_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $invitationId, $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        $invitation = $result->fetch_assoc();
        $stmt->close();

        if (!$invitation) {
            return ['success' => false, 'error' => 'Token not found'];
        }

        // Check permissions
        $userRole = $this->getUserRoleInTeam($teamId, $userId);
        
        if ($userRole === 'owner') {
            // Owner can revoke any token
            $canRevoke = true;
        } elseif ($userRole === 'admin') {
            // Admin can only revoke own tokens
            $canRevoke = ($invitation['invited_by'] === $userId);
        } else {
            $canRevoke = false;
        }

        if (!$canRevoke) {
            return ['success' => false, 'error' => 'Permission denied'];
        }

        // Soft delete the invitation
        $stmt = $this->db->prepare("
            UPDATE team_invitations 
            SET deleted_at = NOW() 
            WHERE id = ? AND team_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $invitationId, $teamId);
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Failed to revoke token'];
        }
        $stmt->close();

        return ['success' => true, 'message' => 'Token revoked successfully'];
    }

    /**
     * Check if user can invite to team
     */
    private function canInvite(int $teamId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT role FROM team_members
            WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $teamId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $member = $result->fetch_assoc();
        $stmt->close();

        return $member && in_array($member['role'], ['owner', 'admin']);
    }

    /**
     * Check if user is team member
     */
    private function isTeamMember(int $teamId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id FROM team_members
            WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $teamId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    /**
     * Get team member count
     */
    private function getTeamMemberCount(int $teamId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM team_members
            WHERE team_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return (int)$row['count'];
    }

    /**
     * Get team max members
     */
    private function getTeamMaxMembers(int $teamId): int
    {
        $stmt = $this->db->prepare("
            SELECT max_members FROM teams
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return (int)$row['max_members'];
    }

    /**
     * Get team name
     */
    private function getTeamName(int $teamId): string
    {
        $stmt = $this->db->prepare("
            SELECT name FROM teams
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['name'] ?? 'Unknown Team';
    }

    /**
     * Get user email
     */
    private function getUserEmail(int $userId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT email FROM users
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['email'] ?? null;
    }

    /**
     * Get user name
     */
    private function getUserName(int $userId): string
    {
        $stmt = $this->db->prepare("
            SELECT name FROM users
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['name'] ?? 'Unknown User';
    }

    /**
     * Create a new team
     */
    public function createTeam(array $data, array $files, int $userId): array
    {
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $maxMembers = (int)($data['max_members'] ?? 10);

        // Validate
        if (empty($name)) {
            return ['success' => false, 'error' => 'Team name is required'];
        }
        if (strlen($name) > 255) {
            return ['success' => false, 'error' => 'Team name must not exceed 255 characters'];
        }
        if ($maxMembers < 2 || $maxMembers > 100) {
            return ['success' => false, 'error' => 'Max members must be between 2 and 100'];
        }

        // Handle logo upload
        $logoUrl = null;
        if (isset($files['logo']) && $files['logo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 2 * 1024 * 1024;
            
            $fileType = $files['logo']['type'];
            $fileSize = $files['logo']['size'];
            
            if (!in_array($fileType, $allowedTypes)) {
                return ['success' => false, 'error' => 'Invalid logo type. Only JPG, PNG, GIF, and WebP are allowed.'];
            }
            if ($fileSize > $maxSize) {
                return ['success' => false, 'error' => 'Logo must be less than 2MB.'];
            }
            
            $extension = pathinfo($files['logo']['name'], PATHINFO_EXTENSION);
            $filename = 'team_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $uploadPath = __DIR__ . '/../../uploads/teams/' . $filename;
            
            if (!move_uploaded_file($files['logo']['tmp_name'], $uploadPath)) {
                return ['success' => false, 'error' => 'Failed to upload logo.'];
            }
            
            $logoUrl = '/uploads/teams/' . $filename;
        }

        // Insert team
        $stmt = $this->db->prepare("
            INSERT INTO teams (owner_id, name, description, logo_url, max_members)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('isssi', $userId, $name, $description, $logoUrl, $maxMembers);
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Failed to create team'];
        }
        
        $teamId = $this->db->insert_id;
        $stmt->close();

        // Add creator as owner
        $stmt = $this->db->prepare("
            INSERT INTO team_members (team_id, user_id, role)
            VALUES (?, ?, 'owner')
        ");
        $stmt->bind_param('ii', $teamId, $userId);
        
        if (!$stmt->execute()) {
            // Rollback team creation
            $this->db->query("DELETE FROM teams WHERE id = $teamId");
            return ['success' => false, 'error' => 'Failed to add owner to team'];
        }
        $stmt->close();

        // Log activity
        $this->activityLog->log(
            'team.create',
            "Created team '$name'",
            $userId,
            'team',
            $teamId,
            ['team_name' => $name, 'max_members' => $maxMembers]
        );

        return [
            'success' => true,
            'team_id' => $teamId
        ];
    }

    /**
     * Get all teams for a user
     */
    public function getTeamsForUser(int $userId, string $search = '', int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $searchParam = "%$search%";

        // Get total count for pagination
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM teams t
            JOIN team_members tm ON tm.team_id = t.id AND tm.user_id = ? AND tm.deleted_at IS NULL
            WHERE t.deleted_at IS NULL AND (t.name LIKE ? OR t.description LIKE ?)
        ");
        $countStmt->bind_param('iss', $userId, $searchParam, $searchParam);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        // Get paginated results
        $stmt = $this->db->prepare("
            SELECT t.id, t.name, t.description, t.logo_url, t.max_members,
                   tm.role as user_role,
                   (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND deleted_at IS NULL) as member_count
            FROM teams t
            JOIN team_members tm ON tm.team_id = t.id AND tm.user_id = ? AND tm.deleted_at IS NULL
            WHERE t.deleted_at IS NULL AND (t.name LIKE ? OR t.description LIKE ?)
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('issii', $userId, $searchParam, $searchParam, $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $teams = [];
        
        while ($row = $result->fetch_assoc()) {
            $teams[] = $row;
        }
        $stmt->close();
        
        return [
            'teams' => $teams,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get team by ID with user's role
     */
    public function getTeamById(int $teamId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.id, t.name, t.description, t.logo_url, t.max_members, t.owner_id,
                   tm.role as user_role
            FROM teams t
            JOIN team_members tm ON tm.team_id = t.id AND tm.user_id = ? AND tm.deleted_at IS NULL
            WHERE t.id = ? AND t.deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $userId, $teamId);
        $stmt->execute();
        $result = $stmt->get_result();
        $team = $result->fetch_assoc();
        $stmt->close();
        
        return $team;
    }

    /**
     * Get team members
     */
    public function getTeamMembers(int $teamId, string $search = ''): array
    {
        $searchParam = "%$search%";
        
        $stmt = $this->db->prepare("
            SELECT u.id, u.name, u.email, u.avatar_url, tm.role, tm.joined_at
            FROM team_members tm
            JOIN users u ON u.id = tm.user_id
            WHERE tm.team_id = ? AND tm.deleted_at IS NULL AND u.deleted_at IS NULL
            AND (u.name LIKE ? OR u.email LIKE ?)
            ORDER BY 
                CASE tm.role 
                    WHEN 'owner' THEN 1 
                    WHEN 'admin' THEN 2 
                    ELSE 3 
                END,
                tm.joined_at ASC
        ");
        $stmt->bind_param('iss', $teamId, $searchParam, $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
        $members = [];
        
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
        $stmt->close();
        
        return $members;
    }

    /**
     * Update team settings (owner only)
     */
    public function updateTeamSettings(int $teamId, int $userId, array $data, array $files): array
    {
        // Check if user is owner
        if (!$this->isTeamOwner($teamId, $userId)) {
            return ['success' => false, 'error' => 'Only team owner can update settings'];
        }

        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $maxMembers = (int)($data['max_members'] ?? 10);

        // Validate
        if (empty($name)) {
            return ['success' => false, 'error' => 'Team name is required'];
        }
        if (strlen($name) > 255) {
            return ['success' => false, 'error' => 'Team name must not exceed 255 characters'];
        }
        if ($maxMembers < 2 || $maxMembers > 100) {
            return ['success' => false, 'error' => 'Max members must be between 2 and 100'];
        }

        // Check if max_members is less than current member count
        $currentCount = $this->getTeamMemberCount($teamId);
        if ($maxMembers < $currentCount) {
            return ['success' => false, 'error' => "Max members cannot be less than current member count ($currentCount)"];
        }

        // Handle logo upload
        $logoUrl = null;
        if (isset($files['logo']) && $files['logo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 2 * 1024 * 1024;
            
            $fileType = $files['logo']['type'];
            $fileSize = $files['logo']['size'];
            
            if (!in_array($fileType, $allowedTypes)) {
                return ['success' => false, 'error' => 'Invalid logo type. Only JPG, PNG, GIF, and WebP are allowed.'];
            }
            if ($fileSize > $maxSize) {
                return ['success' => false, 'error' => 'Logo must be less than 2MB.'];
            }
            
            $extension = pathinfo($files['logo']['name'], PATHINFO_EXTENSION);
            $filename = 'team_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $uploadPath = __DIR__ . '/../../uploads/teams/' . $filename;
            
            if (!move_uploaded_file($files['logo']['tmp_name'], $uploadPath)) {
                return ['success' => false, 'error' => 'Failed to upload logo.'];
            }
            
            $logoUrl = '/uploads/teams/' . $filename;
        }

        // Update team
        if ($logoUrl) {
            $stmt = $this->db->prepare("
                UPDATE teams 
                SET name = ?, description = ?, logo_url = ?, max_members = ?
                WHERE id = ?
            ");
            $stmt->bind_param('ssssi', $name, $description, $logoUrl, $maxMembers, $teamId);
        } else {
            $stmt = $this->db->prepare("
                UPDATE teams 
                SET name = ?, description = ?, max_members = ?
                WHERE id = ?
            ");
            $stmt->bind_param('ssi', $name, $description, $maxMembers, $teamId);
        }
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Failed to update team'];
        }
        $stmt->close();

        return ['success' => true, 'message' => 'Team settings updated'];
    }

    /**
     * Kick member from team (owner or admin)
     */
    public function kickMember(int $teamId, int $userId, int $targetUserId): array
    {
        // Check permissions
        $userRole = $this->getUserRoleInTeam($teamId, $userId);
        
        if (!$userRole) {
            return ['success' => false, 'error' => 'You are not a member of this team'];
        }

        $targetRole = $this->getUserRoleInTeam($teamId, $targetUserId);
        
        if (!$targetRole) {
            return ['success' => false, 'error' => 'Target user is not a member of this team'];
        }

        // Owner can kick anyone except themselves
        // Admin can kick members only
        if ($userRole === 'owner') {
            if ($targetUserId === $userId) {
                return ['success' => false, 'error' => 'Owner cannot kick themselves'];
            }
        } elseif ($userRole === 'admin') {
            if ($targetRole !== 'member') {
                return ['success' => false, 'error' => 'Admins can only kick members'];
            }
        } else {
            return ['success' => false, 'error' => 'Permission denied'];
        }

        // Soft delete member
        $stmt = $this->db->prepare("
            UPDATE team_members 
            SET deleted_at = NOW() 
            WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $teamId, $targetUserId);
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Failed to kick member'];
        }
        $stmt->close();

        // Get names for logging
        $teamName = $this->getTeamName($teamId);
        $targetUserName = $this->getUserName($targetUserId);

        // Log activity
        $this->activityLog->log(
            'team.member.kick',
            "Kicked $targetUserName from team '$teamName'",
            $userId,
            'team',
            $teamId,
            ['kicked_user_id' => $targetUserId, 'kicked_user_name' => $targetUserName]
        );

        return ['success' => true, 'message' => 'Member kicked successfully'];
    }

    /**
     * Leave team (member or admin, not owner)
     */
    public function leaveTeam(int $teamId, int $userId): array
    {
        $userRole = $this->getUserRoleInTeam($teamId, $userId);
        
        if (!$userRole) {
            return ['success' => false, 'error' => 'You are not a member of this team'];
        }

        if ($userRole === 'owner') {
            return ['success' => false, 'error' => 'Owner cannot leave the team. Transfer ownership first or delete the team.'];
        }

        // Soft delete member
        $stmt = $this->db->prepare("
            UPDATE team_members 
            SET deleted_at = NOW() 
            WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $teamId, $userId);
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Failed to leave team'];
        }
        $stmt->close();

        // Get team name for logging
        $teamName = $this->getTeamName($teamId);

        // Log activity
        $this->activityLog->log(
            'team.leave',
            "Left team '$teamName'",
            $userId,
            'team',
            $teamId
        );

        return ['success' => true, 'message' => 'You have left the team'];
    }

    /**
     * Change member role (owner only)
     */
    public function changeMemberRole(int $teamId, int $userId, int $targetUserId, string $newRole): array
    {
        // Check if user is owner
        if (!$this->isTeamOwner($teamId, $userId)) {
            return ['success' => false, 'error' => 'Only team owner can change roles'];
        }

        // Validate role
        if (!in_array($newRole, ['owner', 'admin', 'member'])) {
            return ['success' => false, 'error' => 'Invalid role'];
        }

        // Check if target is member
        $targetRole = $this->getUserRoleInTeam($teamId, $targetUserId);
        
        if (!$targetRole) {
            return ['success' => false, 'error' => 'Target user is not a member of this team'];
        }

        // Cannot change own role
        if ($targetUserId === $userId) {
            return ['success' => false, 'error' => 'Cannot change your own role'];
        }

        // Update role
        $stmt = $this->db->prepare("
            UPDATE team_members 
            SET role = ? 
            WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('sii', $newRole, $teamId, $targetUserId);
        
        if (!$stmt->execute()) {
            return ['success' => false, 'error' => 'Failed to change role'];
        }
        $stmt->close();

        // If changing to owner, demote current owner to admin
        if ($newRole === 'owner') {
            $stmt = $this->db->prepare("
                UPDATE team_members 
                SET role = 'admin' 
                WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
            ");
            $stmt->bind_param('ii', $teamId, $userId);
            $stmt->execute();
            $stmt->close();

            // Update team owner_id
            $stmt = $this->db->prepare("
                UPDATE teams 
                SET owner_id = ? 
                WHERE id = ?
            ");
            $stmt->bind_param('ii', $targetUserId, $teamId);
            $stmt->execute();
            $stmt->close();
        }

        return ['success' => true, 'message' => 'Role changed successfully'];
    }

    /**
     * Transfer ownership to another member (owner only)
     */
    public function transferOwnership(int $teamId, int $currentOwnerId, int $newOwnerId): array
    {
        // Check if current user is owner
        if (!$this->isTeamOwner($teamId, $currentOwnerId)) {
            return ['success' => false, 'error' => 'Only team owner can transfer ownership'];
        }

        // Cannot transfer to self
        if ($currentOwnerId === $newOwnerId) {
            return ['success' => false, 'error' => 'Cannot transfer ownership to yourself'];
        }

        // Check if new owner is a member
        $newOwnerRole = $this->getUserRoleInTeam($teamId, $newOwnerId);
        if (!$newOwnerRole) {
            return ['success' => false, 'error' => 'Target user is not a member of this team'];
        }

        // Start transaction
        $this->db->begin_transaction();

        try {
            // Update new owner's role
            $stmt = $this->db->prepare("
                UPDATE team_members 
                SET role = 'owner' 
                WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
            ");
            $stmt->bind_param('ii', $teamId, $newOwnerId);
            $stmt->execute();
            $stmt->close();

            // Demote current owner to admin
            $stmt = $this->db->prepare("
                UPDATE team_members 
                SET role = 'admin' 
                WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
            ");
            $stmt->bind_param('ii', $teamId, $currentOwnerId);
            $stmt->execute();
            $stmt->close();

            // Update team owner_id
            $stmt = $this->db->prepare("
                UPDATE teams 
                SET owner_id = ? 
                WHERE id = ?
            ");
            $stmt->bind_param('ii', $newOwnerId, $teamId);
            $stmt->execute();
            $stmt->close();

            $this->db->commit();

            // Get names for logging
            $teamName = $this->getTeamName($teamId);
            $newOwnerName = $this->getUserName($newOwnerId);

            // Log activity
            $this->activityLog->log(
                'team.ownership.transfer',
                "Transferred ownership of team '$teamName' to $newOwnerName",
                $currentOwnerId,
                'team',
                $teamId,
                ['new_owner_id' => $newOwnerId, 'new_owner_name' => $newOwnerName]
            );

            return ['success' => true, 'message' => 'Ownership transferred successfully'];
        } catch (\Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'error' => 'Failed to transfer ownership'];
        }
    }

    /**
     * Check if user is team owner
     */
    private function isTeamOwner(int $teamId, int $userId): bool
    {
        return $this->getUserRoleInTeam($teamId, $userId) === 'owner';
    }

    /**
     * Get user's role in team
     */
    private function getUserRoleInTeam(int $teamId, int $userId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT role FROM team_members
            WHERE team_id = ? AND user_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $teamId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['role'] ?? null;
    }
}
