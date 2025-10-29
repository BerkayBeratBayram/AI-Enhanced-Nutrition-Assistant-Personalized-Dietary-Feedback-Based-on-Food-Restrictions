<?php
include 'auth.php'; 
include 'db_config.php'; 

try {
    $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $recipe_id = $_POST['recipe_id'];

    $query = "DELETE FROM favorites WHERE user_id = ? AND recipe_id = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die("Query preparation error: " . $conn->error);
    }

    $stmt->bind_param('ii', $user_id, $recipe_id);

    if ($stmt->execute()) {
        header("Location: favorites.php?success=removed");
    } else {
        header("Location: favorites.php?error=remove_failed");
    }
    $stmt->close();
    $conn->close();
    exit;
} else {
    header("Location: favorites.php");
    exit;
}
