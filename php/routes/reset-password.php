<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = trim($_POST['token']);
    $new_password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($new_password !== $confirm_password) {
        echo "Passwords do not match.";
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?");
        $stmt->bind_param("si", $new_password, $user['user_id']);
        $stmt->execute();

        echo "Your password has been reset successfully.";
        header("Location: login.php");
        exit();
    } else {
        echo "Invalid or expired token.";
    }
} elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
} else {
    echo "No token provided.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password | DietApp</title>

  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <link rel="stylesheet" href="../../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../../plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="../../dist/css/adminlte.min.css">
</head>
<body class="login-page" style="min-height: 496.781px;">
<div class="login-box">
  <div class="login-logo">
    <a href="#"><b>Diet</b>App</a>
  </div>
  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">Enter your email to reset your password</p>

      <form action="forgot-password.php" method="post">
        <div class="input-group mb-3">
          <input type="email" name="email" class="form-control" placeholder="Email" required>
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-envelope"></span>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
          </div>
        </div>
      </form>

      <p class="mt-3 mb-1">
        <a href="login.php">Back to Login</a>
      </p>
    </div>
  </div>
</div>

<script src="../../plugins/jquery/jquery.min.js"></script>
<script src="../../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../../dist/js/adminlte.min.js"></script>
</body>
</html>
