<?php

// config/database.php

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function connect(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host     = getenv('DB_HOST')     ?: ($_ENV['DB_HOST'] ?? 'localhost');
        $port     = getenv('DB_PORT')     ?: ($_ENV['DB_PORT'] ?? '3306');
        $dbname   = getenv('DB_NAME')     ?: ($_ENV['DB_NAME'] ?? 'ors_db');
        $username = getenv('DB_USER')     ?: ($_ENV['DB_USER'] ?? 'root');
        $password = getenv('DB_PASS')     ?: ($_ENV['DB_PASS'] ?? '');
        $charset  = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$instance = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // Never expose raw DB errors to the client
            error_log('[DB Connection Error] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
            exit;
        }

        return self::$instance;
    }
}
