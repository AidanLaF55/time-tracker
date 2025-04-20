<?php
$host = 'mysql';
$db = 'time_tracking';
$user = 'root';
$pass = 'rootpassword';

date_default_timezone_set('America/New_York');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET SESSION time_zone = 'America/New_York'");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
