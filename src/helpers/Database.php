<?php
/**
 * Database Configuration
 * Health Tracker Application
 */

class Database {
    private $host = 'localhost';
    private $dbname = 'health_tracker';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $pdo = null;

    public function connect() {
        if ($this->pdo === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }

        return $this->pdo;
    }

    public function disconnect() {
        $this->pdo = null;
    }
}
?>