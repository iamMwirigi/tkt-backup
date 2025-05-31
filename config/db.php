<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        // Use environment variables with local defaults
       $this->host = '142.44.187.111';
$this->port = '3306';
$this->db_name = 'tkt';
$this->username = 'dev_ops1';
$this->password = 'a26N8Iv22TC4kJdb';

        
        
        // For services like PlanetScale:
        // $this->host = getenv('MYSQL_HOST') ?: getenv('DB_HOST');
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db_name}",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::MYSQL_ATTR_SSL_CA => getenv('MYSQL_SSL_CA_PATH'), // For SSL connections
                    PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false // For services like PlanetScale
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            header('Content-Type: application/json');
            exit(json_encode([
                'error' => true,
                'message' => 'Database connection failed',
                'details' => $e->getMessage()
            ]));
        }

        return $this->conn;
    }
}
?>