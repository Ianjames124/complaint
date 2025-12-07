<?php

class Database
{
    private $conn;

    public function getConnection()
    {
        if ($this->conn) {
            return $this->conn;
        }

        $config = require __DIR__ . '/env.php';

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['DB_HOST'], $config['DB_NAME']);

        try {
            $this->conn = new PDO($dsn, $config['DB_USER'], $config['DB_PASS'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Don't output here - let the calling script handle CORS first
            // Just throw so the calling script can handle it properly
            throw new PDOException('Database connection failed: ' . $e->getMessage());
        }

        return $this->conn;
    }
}


