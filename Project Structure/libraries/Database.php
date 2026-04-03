<?php

class Database
{
	private static ?PDO $connection = null;

	public static function connection(array $dbConfig): PDO
	{
		if (self::$connection instanceof PDO) {
			return self::$connection;
		}

		$dsn = sprintf(
			'mysql:host=%s;port=%s;dbname=%s;charset=%s',
			$dbConfig['host'],
			$dbConfig['port'],
			$dbConfig['database'],
			$dbConfig['charset']
		);

		self::$connection = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);

		return self::$connection;
	}
}
