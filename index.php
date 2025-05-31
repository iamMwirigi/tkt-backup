<?php
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TKT Application</title>
</head>
<body>
    <h1>Welcome to the TKT Application API</h1>
    <p>This is the main landing page. API endpoints are available at their designated paths.</p>
</body>
</html>
