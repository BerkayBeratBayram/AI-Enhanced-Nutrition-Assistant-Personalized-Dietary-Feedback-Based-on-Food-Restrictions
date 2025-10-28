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

if (!isset($_POST['review_id']) || empty($_POST['review_id'])) {
    die("Hata: Geçersiz değerlendirme ID'si.");
}
$review_id = intval($_POST['review_id']); 

$sql = "DELETE FROM recipe_ratings WHERE recipe_id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL hazırlama hatası: " . $conn->error);
}
$stmt->bind_param("ii", $review_id, $_SESSION['user_id']);

if ($stmt->execute()) {
    echo "The review was deleted successfully!";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

header("Location: reviews.php");
exit();
?>
