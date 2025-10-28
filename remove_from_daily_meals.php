<?php
include 'auth.php'; 
include 'db_config.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $recipe_id = $_POST['recipe_id'] ?? null;

    if (!$recipe_id) {
        die("Invalid recipe ID.");
    }

    try {
        $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);
        if ($conn->connect_error) {
            throw new Exception("Database connection failed: " . $conn->connect_error);
        }

        $query = "DELETE FROM daily_meals WHERE user_id = ? AND recipe_id = ? AND meal_date = CURRENT_DATE()";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $user_id, $recipe_id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $_SESSION['message'] = "Meal successfully removed.";
        } else {
            $_SESSION['message'] = "Meal not found or already removed.";
        }

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }

    header("Location: daily_food.php");
    exit();
}
?>
