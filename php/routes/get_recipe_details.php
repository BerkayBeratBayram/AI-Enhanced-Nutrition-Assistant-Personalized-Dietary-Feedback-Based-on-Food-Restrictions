<?php
include 'db_connection.php';

if (!isset($_GET['recipe_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "recipe_id missing"]);
    exit;
}

$recipe_id = (int) $_GET['recipe_id'];

$sql = "SELECT recipe_id, recipe_name, recipe_calories, recipe_fat_content, recipe_carbohydrate_content, recipe_sugar_content, recipe_protein_content, recipe_recipe_instructions, recipe_ingredients FROM recipes_new WHERE recipe_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $recipe_id);
$stmt->execute();

$result = $stmt->get_result();
$recipe = $result->fetch_assoc();

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($recipe);
