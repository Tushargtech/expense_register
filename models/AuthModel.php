<?php

class AuthModel
{
    public function getUserByEmail(string $email): array|false
    {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT
                user_id AS id,
                user_name AS name,
                user_email AS email,
                user_password_hash AS password,
                user_role AS role,
                r.role_permissions,
                department_id,
                d.department_name AS department_name
             FROM users
             LEFT JOIN departments d ON d.id = users.department_id
             LEFT JOIN roles r ON r.role_slug = users.user_role
             WHERE user_email = ? AND user_is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$email]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getCredentialHints(int $limit = 8): array
    {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT
                user_name,
                user_email,
                user_role,
                user_password_hash
             FROM users
             WHERE user_is_active = 1
               ORDER BY user_role ASC, user_name ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $hints = [];
        foreach ($rows as $row) {
            $hints[] = [
                'name' => (string) ($row['user_name'] ?? ''),
                'email' => (string) ($row['user_email'] ?? ''),
                'role' => strtolower(trim((string) ($row['user_role'] ?? 'employee'))),
                'password_hint' => 'Use assigned password',
            ];
        }

        return $hints;
    }
}