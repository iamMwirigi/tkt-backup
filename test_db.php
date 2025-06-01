echo "<pre>";
print_r([
    'DB_HOST' => getenv('DB_HOST'),
    'DB_PORT' => getenv('DB_PORT'),
    'DB_NAME' => getenv('DB_NAME'),
    'DB_USER' => getenv('DB_USER'),
]);
echo "</pre>";

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
