<?php
require_once __DIR__ . '/config/db.php';

$db = new Database();
$conn = $db->getConnection();

if ($conn) {
    echo "✅ Successfully connected to the live database!";
} else {
    echo "❌ Connection failed.";
}
?>
