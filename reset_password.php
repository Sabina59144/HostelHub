<?php
require_once("includes/db.php");

$hash = password_hash('password', PASSWORD_BCRYPT);

$stmt = $db->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->execute([$hash]);

echo "Done. Admin password has been reset to: <strong>password</strong><br>";
echo "Hash used: " . $hash . "<br><br>";
echo "<strong>Delete this file now.</strong>";
?>
