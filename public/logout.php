<?php
session_start();

include 'db_config.php'; 

$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE users SET is_online = 0 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

session_unset();
session_destroy();

header("Location: login.php");
exit();
