<?php

/**
 * Simple PSR-4 autoloader for PHPMailer
 * Used when Composer is not available
 */

class PHPMailerAutoloader
{
    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    public static function autoload(string $class): void
    {
        // Only load PHPMailer classes
        if (strpos($class, 'PHPMailer\\') !== 0) {
            return;
        }

        // Convert namespace to file path
        $candidateDirs = [
            __DIR__ . '/../PHPMailer-master/src/',
            __DIR__ . '/../PHPMailer-master 2/src/',
        ];
        $baseDir = null;
        foreach ($candidateDirs as $candidate) {
            if (is_dir($candidate)) {
                $baseDir = $candidate;
                break;
            }
        }

        if ($baseDir === null) {
            return;
        }

        $relativePath = str_replace('PHPMailer\\', '', $class);
        $filePath = $baseDir . str_replace('\\', '/', $relativePath) . '.php';

        if (file_exists($filePath)) {
            require_once $filePath;
        }
    }
}

// Register the autoloader
PHPMailerAutoloader::register();