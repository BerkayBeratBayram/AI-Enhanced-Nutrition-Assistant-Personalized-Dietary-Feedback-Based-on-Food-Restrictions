<?php
session_start();
$hata = '';
if (!isset($_SESSION['user_id'])) {
    echo $hata = 'userId" Could not be retrieved';
    exit();
}

$user_id = $_SESSION['user_id'];
$recipe_id = $_POST['recipe_id'] ?? null;

if (!$recipe_id) {
    die("Invalid recipe ID.");
}

include 'db_connection.php';


$check_query = "SELECT * FROM daily_meals WHERE user_id = ? AND recipe_id = ? AND meal_date = CURRENT_DATE()";
$stmt_check = $conn->prepare($check_query);
$stmt_check->bind_param("ii", $user_id, $recipe_id);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows > 0) {
    $_SESSION['message'] = "Recipe is already added to today's meals.";
    $stmt_check->close();
    $conn->close();
    
    header("Location: daily_food.php");
    exit();
}

$insert_query = "INSERT INTO daily_meals (user_id, recipe_id, meal_date) VALUES (?, ?, CURRENT_DATE())";
$stmt_insert = $conn->prepare($insert_query);
$stmt_insert->bind_param("ii", $user_id, $recipe_id);
$stmt_insert->execute();

if ($stmt_insert->affected_rows > 0) {
    $_SESSION['message'] = "Recipe added to today's meals successfully.";
} else {
    $_SESSION['message'] = "Failed to add recipe to today's meals.";
}

$stmt_insert->close();
$conn->close();

header("Location: daily_food.php");
exit();
?>
