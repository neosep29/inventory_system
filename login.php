<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('db_config.php');

if (isset($_SESSION['user_id'])) {
    $userRole = $_SESSION['user_role'];
    if ($userRole === 'admin') {
        // If the user is an admin, redirect to the main page with a button for barcode logs
        header("Location: index.php");
    } else {
        // If the user is not an admin, redirect to the main page without the barcode logs button
        header("Location: index.php");
    }
}



if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Use prepared statements to prevent SQL injection
    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $hashed_password = $row["password"];
        $userRole = $row["role"];

        if (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $row["id"];
            $_SESSION['user_role'] = $userRole;
            header("Location: index.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Username not found.";
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login UPLB Graduate School IS </title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body class="b1">
    <div class="page-wrapper" style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding-top:0;">
        <h1 class="inventory-header" style="margin-top:10px;">Inventory System</h1>
        <div class="login-container">
            <img src="css/GS-logo.png" alt="Your Logo" class="login-logo">
            <h2>UPLB Graduate School</h2>
            <p class="login-text">Sign in to your account</p>
            <form method="post" action="login.php">
                <label for="username">Username:</label>
                <input type="text" name="username" required autocomplete="username">
                <label for="password">Password:</label>
                <input type="password" name="password" required autocomplete="current-password">
                <button type="submit">Sign In</button>
            </form>
            <?php
            if (isset($error)) {
                echo "<p class='error'>$error</p>";
            }
            ?>
            <p class="create-account-link">Don't have an account? <a href="registration.php">Create an account</a></p>
        </div>
    </div>
    <div class="footer">
        <p>&copy; Joseph Patron || <?php echo date("Y"); ?> Inventory System</p>
    </div>
</body>
</html>