<?php
class AuthModel {
    public function getUserByEmail($email) {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT
                user_id AS id,
                user_name AS name,
                user_email AS email,
                user_password_hash AS password,
                user_role AS role,
                department_id
             FROM users
             WHERE user_email = ? AND user_is_active = 1
             LIMIT 1"
        );
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}