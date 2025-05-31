<?php
$plainPassword = 'password'; // The password you want to use
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
echo "Plain password: " . htmlspecialchars($plainPassword) . "<br>";
echo "Hashed password: " . htmlspecialchars($hashedPassword);
?>
