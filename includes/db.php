<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "hostelhub";

try {
    $db = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
<?php
$host     = 'localhost';
$dbname   = 'hostelhub';
$username = 'root';
$password = '';          // XAMPP default is blank

try {
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $username,
        $password
    );
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("<b>Database connection failed:</b> " . $e->getMessage());
}