<?php
require_once(__DIR__ . '/../config/auth.php');
authLogoutUser();
header('Location: ../../html/login.php');
exit;

