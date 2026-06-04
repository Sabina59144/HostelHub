<?php
$password = 'password';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
echo "Password: password<br>";
echo "Hash: " . $hash . "<br>";
echo "Test: " . (password_verify($password, $hash) ? '✓ MATCH' : '✗ NO MATCH');
?>
