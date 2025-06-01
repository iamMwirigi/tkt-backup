<?php
require_once __DIR__ . '/config/db.php';

$db = new Database();
$conn = $db->getConnection();

if ($conn) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => '✅ Successfully connected to the live database!'
    ]);
} else {
    // The db.php file already handles the error + JSON output
}
