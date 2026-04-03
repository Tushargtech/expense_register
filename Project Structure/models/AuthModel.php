<?php

final class AuthModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Returns active user by email using schema.sql columns.
     */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->db->prepare(
            'SELECT
                user_id,
                user_name,
                user_email,
                user_password_hash,
                user_role,
                department_id,
                user_is_active
             FROM users
             WHERE user_email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            return null;
        }

        if ((int) ($user['user_is_active'] ?? 0) !== 1) {
            return null;
        }

        return $user;
    }
}
