<?php
include('db_config.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$success_message = null;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_SESSION['user_id'];
    $old_password = isset($_POST['old_password']) ? $_POST['old_password'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

    $sql = "SELECT password FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        $error_message = "Error preparing the query: " . $conn->error;
    } else {
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $hashed_password = $row['password'];

                if (password_verify($old_password, $hashed_password)) {
                    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);

                    if ($update_stmt === false) {
                        $error_message = "Error preparing the update query: " . $conn->error;
                    } else {
                        $update_stmt->bind_param("si", $new_hashed_password, $user_id);

                        if ($update_stmt->execute()) {
                            $success_message = "Password updated successfully.";
                        } else {
                            $error_message = "Error updating the password: " . $update_stmt->error;
                        }
                        $update_stmt->close();
                    }
                } else {
                    $error_message = "Incorrect old password.";
                }
            }
        } else {
            $error_message = "Error executing the query: " . $stmt->error;
        }
        $stmt->close();
    }
}

$cacheBuster = 'v=' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="css/style.css?<?php echo $cacheBuster; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include('nav.php'); ?>

    <div class="page-wrapper">
        <h1 class="inventory-header">Inventory System</h1>
        <div class="container-sm">
            <div class="back-button" style="margin-bottom:16px;">
                <a href="index.php">&larr; Back to Menu</a>
            </div>
            <div class="add-item-container">
                <h2>Change Password</h2>
                <form class="add-item-form" method="post" action="change_password.php">
                    <label for="old_password"><i class="fas fa-lock" style="margin-right:6px; color:#8B0000;"></i>Old Password:</label>
                    <input type="password" id="old_password" name="old_password" required placeholder="Enter current password" autocomplete="current-password">
                    <label for="new_password"><i class="fas fa-key" style="margin-right:6px; color:#8B0000;"></i>New Password:</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter new password" autocomplete="new-password">
                    <button type="submit"><i class="fas fa-save" style="margin-right:8px;"></i>Update Password</button>
                </form>
                <?php
                if (isset($success_message)) {
                    echo '<div class="success"><i class="fas fa-check-circle" style="margin-right:8px;"></i>' . htmlspecialchars($success_message) . '</div>';
                }
                if (isset($error_message)) {
                    echo '<div class="error"><i class="fas fa-exclamation-circle" style="margin-right:8px;"></i>' . htmlspecialchars($error_message) . '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    <div class="footer">
        <p>&copy; Joseph Patron || <?php echo date("Y"); ?> Inventory System</p>
    </div>
    <script src="script.js?<?php echo $cacheBuster; ?>"></script>
</body>
</html>
