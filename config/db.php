<?php
class Database {
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        // Load environment variables
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            if (class_exists('Dotenv\Dotenv')) {
                try {
                    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
                    $dotenv->load();
                } catch (Exception $e) {
                    error_log("Dotenv Error in Database constructor: " . $e->getMessage());
                }
            }
        }

        // Get database credentials from environment variables
        $this->host = getenv('DB_HOST');
        $this->port = getenv('DB_PORT');
        $this->dbname = getenv('DB_NAME');
        $this->username = getenv('DB_USER');
        $this->password = getenv('DB_PASSWORD');

        // Validate required environment variables
        $required_vars = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
        $missing_vars = [];
        foreach ($required_vars as $var) {
            if (!getenv($var)) {
                $missing_vars[] = $var;
            }
        }

        if (!empty($missing_vars)) {
            throw new Exception('Missing required environment variables: ' . implode(', ', $missing_vars));
        }
    }

    public function getConnection() {
        if (!$this->conn) {
            try {
                $this->conn = new PDO(
                    "mysql:host={$this->host};port={$this->port};dbname={$this->dbname}",
                    $this->username,
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                    ]
                );
            } catch (PDOException $e) {
                error_log("Database Connection Error: " . $e->getMessage());
                throw new Exception("Database connection failed");
            }
        }
        return $this->conn;
    }
}
?>
