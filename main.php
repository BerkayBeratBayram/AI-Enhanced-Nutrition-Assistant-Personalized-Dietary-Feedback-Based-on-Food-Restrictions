<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $disease = $_POST['disease'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE users SET disease = ? WHERE user_id = ?");
    $stmt->bind_param("si", $disease, $user_id);
    $stmt->execute();

    $_SESSION['disease'] = $disease;
    $success = "Disease information successfully updated!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            margin-bottom: 20px;
        }
        input[type="text"], button {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
        }
        button:hover {
            background-color: #45a049;
        }
        .success {
            color: green;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome, <?= $_SESSION['name'] ?></h2>
        <p>Current Disease Information: <?= $_SESSION['disease'] ?? "Not Entered Yet" ?></p>

        <form method="POST">
            <label for="disease">Update Your Disease Information</label>
            <input type="text" name="disease" id="disease" placeholder="Enter Your Disease Information" required>
            <button type="submit">Update</button>
        </form>

        <?php if (isset($success)): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
