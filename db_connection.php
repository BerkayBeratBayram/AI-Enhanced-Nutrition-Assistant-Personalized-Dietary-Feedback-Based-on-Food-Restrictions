<?php
$host = 'localhost';
$username = 'berkayber';
$password = '2634';
$dbname = 'dietapp';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
