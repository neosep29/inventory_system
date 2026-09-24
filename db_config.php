<?php
$db_host = 'localhost';
$db_port = 3306;
$db_user = "root";
$db_password = "";
$db_name = 'inventory_db';

$conn = new mysqli($db_host, $db_user, $db_password, $db_name, $db_port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . " (Check if MySQL is running and credentials are correct)");
}
?>