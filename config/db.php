<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        // Use Render's environment variables with local defaults
        $this->host = getenv('MYSQL_HOST') ?: '127.0.0.1';
        $this->port = getenv('MYSQL_PORT') ?: '3306';
        $this->db_name = getenv('MYSQLDATABASE') ?: 'tkt';
        $this->username = getenv('MYSQLUSER') ?: 'root';
        $this->password = getenv('MYSQLPASSWORD') ?: '31278527';
        
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