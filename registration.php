<!DOCTYPE html>
<html>
<head>
    <title>Register | Inventory System</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="b1">
    <div class="page-wrapper" style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding-top:0;">
        <h1 class="inventory-header" style="margin-top:10px;">Inventory System</h1>
        <div class="login-container">
            <img src="css/GS-logo.png" alt="Your Logo" class="login-logo">
            <h2>Create an Account</h2>
            <p class="login-text">UPLB Graduate School - Sign Up</p>
            <form method="post" action="registration_process.php">
                <label for="username"><i class="fas fa-user" style="margin-right:6px; color:#8B0000;"></i>Username:</label>
                <input type="text" name="username" required placeholder="Choose a username" autocomplete="username">
                <label for="password"><i class="fas fa-lock" style="margin-right:6px; color:#8B0000;"></i>Password:</label>
                <input type="password" name="password" required placeholder="Create a password" autocomplete="new-password">
                <button type="submit"><i class="fas fa-user-plus" style="margin-right:8px;"></i>Register</button>
            </form>
            <p class="create-account-link">Already have an account? <a href="login.php">Sign In</a></p>
        </div>
    </div>
    <div class="footer">
        <p>&copy; Joseph Patron || <?php echo date("Y"); ?> Inventory System</p>
    </div>
</body>
</html>
