<?php

/**
 * Database connection manager (singleton).
 *
 * Why this class exists:
 * - Keeps one PDO connection for the whole request lifecycle.
 * - Centralizes MySQL connection settings from configs/db.php.
 * - Avoids repeating connection code in controllers/models.
 */
final class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    /**
     * Private constructor enforces singleton usage through getInstance().
     */
    private function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int) $config['port'],
            $config['database'],
            $config['charset']
        );

        $this->connection = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Returns shared Database instance.
     */
    public static function getInstance(array $config): Database
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Exposes active PDO connection for queries.
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException('Cannot unserialize singleton Database instance.');
    }
}
