<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        $this->host = '127.0.0.1';
        $this->port = '3306';
        $this->db_name = 'tkt';
        $this->username = 'root';
        $this->password = '31278527';
    }

    public function getConnection() {
        $this->conn = null;
        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name}";

        try {
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'message' => 'Database connection failed',
                'details' => $e->getMessage()
            ]);
            exit();
        }

        return $this->conn;
    }
}
?>
