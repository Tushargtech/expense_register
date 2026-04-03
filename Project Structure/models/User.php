<?php

class User
{
	private array $dbConfig;

	public function __construct(array $dbConfig)
	{
		$this->dbConfig = $dbConfig;
	}

	public function findByEmail(string $email): ?array
	{
		$pdo = Database::connection($this->dbConfig);
		$stmt = $pdo->prepare('SELECT id, name, email, password FROM users WHERE email = :email LIMIT 1');
		$stmt->execute(['email' => $email]);
		$user = $stmt->fetch();

		if (!$user) {
			return null;
		}

		return $user;
	}

	public function verifyCredentials(string $email, string $password): ?array
	{
		$user = $this->findByEmail($email);
		if (!$user) {
			return null;
		}

		$hash = (string) ($user['password'] ?? '');
		if (password_verify($password, $hash) || hash_equals($hash, $password)) {
			return $user;
		}

		return null;
	}
}
