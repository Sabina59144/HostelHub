<?php
$password_entered = 'password';
$hash_in_db = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uhewG/igi';

if (password_verify($password_entered, $hash_in_db)) {
    echo "✓ Password matches!";
} else {
    echo "✗ Password does NOT match";
}
?>