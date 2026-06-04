<?php
require_once(__DIR__ . '/../config/auth.php');
authLogoutUser();
header('Location: ../../login.php');
exit;

