<?php
include 'db_connection.php';

$recipe_id = isset($_GET['recipe_id']) ? (int) $_GET['recipe_id'] : null;

if (!$recipe_id) {
    http_response_code(400);
    echo json_encode(['error' => 'recipe_id parameter is required.']);
    exit();
}

$sql = "SELECT r.stars, r.comment, r.created_at, u.name AS user_name
        FROM recipe_ratings r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.recipe_id = ?
        ORDER BY r.created_at DESC
        LIMIT 3"; 

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'SQL preparation error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("i", $recipe_id);
$stmt->execute();
$result = $stmt->get_result();

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $reviews[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['reviews' => $reviews]);
