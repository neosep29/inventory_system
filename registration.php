<?php
$rfid_error = null;
?>
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
        <div class="login-container" style="max-width:440px;">
            <img src="css/GS-logo.png" alt="Your Logo" class="login-logo">
            <h2>Create an Account</h2>
            <p class="login-text">UPLB Graduate School - Sign Up</p>

            <?php
            if (isset($_GET['error'])) {
                $errMsg = $_GET['error'];
                echo '<div class="error" style="margin-top:0; margin-bottom:18px;"><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i>' . htmlspecialchars($errMsg) . '</div>';
            }
            ?>

            <form method="post" action="registration_process.php">
                <label for="username"><i class="fas fa-user" style="margin-right:6px; color:#8B0000;"></i>Username:</label>
                <input type="text" name="username" id="username" required placeholder="Choose a username" autocomplete="username">

                <label for="password"><i class="fas fa-lock" style="margin-right:6px; color:#8B0000;"></i>Password:</label>
                <input type="password" name="password" id="password" required placeholder="Create a password" autocomplete="new-password">

                <label for="rfid_number" style="margin-top:6px;">
                    <i class="fas fa-id-card" style="margin-right:6px; color:#8B0000;"></i>
                    RFID Card Number
                    <span style="font-weight:400; color:#6c757d; font-size:0.82rem; margin-left:6px;">(optional)</span>
                </label>
                <input type="text" name="rfid_number" id="rfid_number"
                       placeholder="Click here then scan your RFID card, or leave blank"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                <p style="font-size:0.78rem; color:#6c757d; margin:-10px 0 16px; line-height:1.4;">
                    <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                    Click the field above to focus it, then wave your RFID card near the reader. You can also type or paste the number manually, or skip this and link the card later in Account Settings.
                </p>

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
