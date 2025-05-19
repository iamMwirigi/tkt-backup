<?php
header('Content-Type: application/json');

try {
    $conn = new PDO(
        "mysql:host=127.0.0.1;port=3306;dbname=tkt",
        "root",
        "31278527",
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'tables' => $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN)
    ]);
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 