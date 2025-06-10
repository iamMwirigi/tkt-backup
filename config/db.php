<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $options;
    public $conn;

    public function __construct() {
        // Load from environment variables
        $this->host = getenv('DB_HOST') ?: '127.0.0.1';
        $this->port = getenv('DB_PORT') ?: '3306';
        $this->db_name = getenv('DB_NAME') ?: 'tkt';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';

        $this->options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 30,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
    }

    public function getConnection() {
        try {
            if (!$this->conn || !$this->isConnectionAlive()) {
                $this->conn = new PDO(
                    "mysql:host={$this->host};port={$this->port};dbname={$this->db_name}",
                    $this->username,
                    $this->password,
                    $this->options
                );
            }
            return $this->conn;
        } catch (PDOException $e) {
            // Attempt one reconnect on failure
            try {
                error_log("Primary connection failed, attempting reconnect: " . $e->getMessage());
                $this->conn = new PDO(
                    "mysql:host={$this->host};port={$this->port};dbname={$this->db_name}",
                    $this->username,
                    $this->password,
                    $this->options
                );
                return $this->conn;
            } catch (PDOException $e) {
                $this->handleConnectionError($e);
            }
        }
    }

    private function isConnectionAlive() {
        try {
            $this->conn->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function handleConnectionError($e) {
        error_log("Database connection failed: " . $e->getMessage());

        if (strpos($e->getMessage(), 'MySQL server has gone away') !== false) {
            error_log("Attempting to re-establish database connection...");
            sleep(1);
            $this->getConnection();
            return;
        }

        header('Content-Type: application/json');
        http_response_code(503); 
        exit(json_encode([
            'error' => true,
            'message' => 'Database connection failed',
            'details' => $e->getMessage(),
            'advice' => 'The application could not connect to the database. Please try again later.'
        ]));
    }
}
?>
