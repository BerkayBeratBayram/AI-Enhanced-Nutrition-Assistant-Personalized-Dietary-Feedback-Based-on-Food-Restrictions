<?php
include 'db_config.php';
$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$term = $_GET['term'] ?? '';
$term = strtolower(trim($term));
$matches = [];

$ingredientSql = "SELECT recipe_ingredients FROM recipes";
$result = $conn->query($ingredientSql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        preg_match_all("/'([^']+)'/", $row['recipe_ingredients'], $ingredientMatches);
        foreach ($ingredientMatches[1] as $ingredient) {
            $ingredientLower = strtolower($ingredient);
            similar_text($ingredientLower, $term, $percent);
            if (stripos($ingredientLower, $term) !== false || $percent > 70) {
                $matches[] = ucwords($ingredientLower);
            }
        }
    }
}

$nameSql = "SELECT recipe_name FROM recipes";
$result2 = $conn->query($nameSql);
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        $name = strtolower($row['recipe_name']);
        similar_text($name, $term, $percent);
        if (stripos($name, $term) !== false || $percent > 70) {
            $matches[] = ucwords($row['recipe_name']);
        }
    }
}

$matches = array_unique($matches);
$matches = array_slice($matches, 0, 10);

header('Content-Type: application/json');
echo json_encode(array_values($matches));
?>
