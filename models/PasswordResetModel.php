<?php

class PasswordResetModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    /**
     * Create a password reset token
     */
    public function createResetToken(int $userId, int $expiryMinutes = 60): ?string
    {
        try {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"));

            $stmt = $this->db->prepare('
                INSERT INTO password_reset_tokens (user_id, token_hash, token_expires_at, is_used)
                VALUES (?, ?, ?, 0)
            ');
            $stmt->execute([$userId, $tokenHash, $expiresAt]);

            return $token;
        } catch (Exception $e) {
            error_log('Error creating reset token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate and get user from reset token
     */
    public function getUserByResetToken(string $token): ?array
    {
        try {
            $tokenHash = hash('sha256', $token);

            $stmt = $this->db->prepare('
                SELECT u.*, prt.token_id, prt.token_expires_at, prt.is_used
                FROM password_reset_tokens prt
                INNER JOIN users u ON prt.user_id = u.user_id
                WHERE prt.token_hash = ? 
                  AND prt.is_used = 0
                  AND prt.token_expires_at > NOW()
                LIMIT 1
            ');
            $stmt->execute([$tokenHash]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (Exception $e) {
            error_log('Error validating reset token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Mark token as used
     */
    public function markTokenAsUsed(string $token): bool
    {
        try {
            $tokenHash = hash('sha256', $token);

            $stmt = $this->db->prepare('
                UPDATE password_reset_tokens 
                SET is_used = 1, used_at = NOW()
                WHERE token_hash = ?
            ');
            return $stmt->execute([$tokenHash]);
        } catch (Exception $e) {
            error_log('Error marking token as used: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Invalidate all active tokens for a user
     */
    public function invalidateUserTokens(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare('
                UPDATE password_reset_tokens 
                SET is_used = 1
                WHERE user_id = ? AND is_used = 0
            ');
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            error_log('Error invalidating user tokens: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Request password reset for user by email
     */
    public function createResetTokenByEmail(string $email): ?array
    {
        try {
            $stmt = $this->db->prepare('
                SELECT user_id, user_name, user_email 
                FROM users 
                WHERE user_email = ? AND user_is_active = 1
                LIMIT 1
            ');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return null;
            }

            $token = $this->createResetToken((int) ($user['user_id'] ?? 0));
            if (!$token) {
                return null;
            }

            return [
                'user_id' => (int) ($user['user_id'] ?? 0),
                'user_name' => (string) ($user['user_name'] ?? ''),
                'user_email' => (string) ($user['user_email'] ?? ''),
                'token' => $token,
            ];
        } catch (Exception $e) {
            error_log('Error creating reset token by email: ' . $e->getMessage());
            return null;
        }
    }
}