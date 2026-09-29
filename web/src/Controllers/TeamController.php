<?php

namespace App\Controllers;

use App\Services\Database;
use App\Services\EmailService;

class TeamController
{
    private $db;
    private $emailService;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->emailService = new EmailService();
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

        // Check if already used
        if ($invitation['used_at'] !== null) {
            return ['success' => false, 'error' => 'Invitation already used'];
        }

        // Check if expired
        if (strtotime($invitation['expires_at']) < time()) {
            return ['success' => false, 'error' => 'Invitation has expired'];
        }

        // If email specified, check if current user matches
        if ($invitation['email'] && $invitation['email'] !== $this->getUserEmail($userId)) {
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

        // Mark invitation as used
        $stmt = $this->db->prepare("
            UPDATE team_invitations 
            SET used_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $invitation['id']);
        $stmt->execute();
        $stmt->close();

        return [
            'success' => true,
            'team_id' => $invitation['team_id'],
            'role' => $role
        ];
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
}
