<?php
session_start();
$hata='';
if (!isset($_SESSION['user_id'])) {
    echo $hata='userId Alınamadı';
    exit();
}

$user_id = $_SESSION['user_id'];
$recipe_id = $_POST['recipe_id'] ?? null;

if (!$recipe_id) {
    die("Invalid recipe ID.");
}

include 'db_connection.php';

$check_query = "SELECT * FROM user_feedback WHERE user_id = ? AND recipe_id = ?";
$stmt_check = $conn->prepare($check_query);
$stmt_check->bind_param("ii", $user_id, $recipe_id);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows > 0) {
    $_SESSION['message'] = "Recipe is already in your favorites.";
    $stmt_check->close();
    $conn->close();
    
    header("Location: recommendations.php");
    exit();
}

$insert_query = "INSERT INTO favorites (user_id, recipe_id) VALUES (?, ?)";
$stmt_insert = $conn->prepare($insert_query);
$stmt_insert->bind_param("ii", $user_id, $recipe_id);
$stmt_insert->execute();

if ($stmt_insert->affected_rows > 0) {
    $_SESSION['message'] = "Recipe added to favorites successfully.";
} else {
    $_SESSION['message'] = "Failed to add recipe to favorites.";
}

$stmt_insert->close();
$conn->close();

header("Location: recommendations.php");
exit();
?>
