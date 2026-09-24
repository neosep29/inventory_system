<?php
include('db_config.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['user_id']) && isset($_POST['new_role'])) {
        $user_id = (int)$_POST['user_id'];
        $new_role = ($_POST['new_role'] === 'admin') ? 'admin' : 'user';
        $sql = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("si", $new_role, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$sql = "SELECT id, username, role FROM users";
$result = $conn->query($sql);

$cacheBuster = 'v=' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Users List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="css/style.css?<?php echo $cacheBuster; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include('nav.php'); ?>

    <div class="page-wrapper">
        <h1 class="inventory-header">Inventory System</h1>
        <div class="container">
            <div class="back-button" style="margin-bottom:16px;">
                <a href="index.php">&larr; Back to Menu</a>
            </div>
            <div class="page-title">
                <h2>Admin Dashboard - Users List</h2>
                <p class="subtitle">Manage user accounts and assign roles</p>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><i class="fas fa-user" style="margin-right:6px;"></i>User</th>
                            <th><i class="fas fa-user-tag" style="margin-right:6px;"></i>Role</th>
                            <th><i class="fas fa-edit" style="margin-right:6px;"></i>Change Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!$result || $result->num_rows === 0) {
                            echo "<tr><td colspan='3' style='text-align:center; padding:30px; color:#6c757d;'>No users found.</td></tr>";
                        } else {
                            while ($row = $result->fetch_assoc()) {
                                $roleBadgeClass = ($row["role"] === 'admin') ? 'style="color:#8B0000; font-weight:700;"' : 'style="color:#28a745; font-weight:600;"';
                                $selectedUser = ($row["role"] === 'user') ? 'selected' : '';
                                $selectedAdmin = ($row["role"] === 'admin') ? 'selected' : '';
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                                echo "<td $roleBadgeClass>" . ucfirst(htmlspecialchars($row["role"])) . "</td>";
                                echo '<td>
                                        <form method="POST" style="display:flex; gap:8px; align-items:center; margin:0;">
                                            <input type="hidden" name="user_id" value="'.(int)$row["id"].'">
                                            <select name="new_role" style="margin:0; padding:7px 10px; font-size:0.88rem;">
                                                <option value="user" '.$selectedUser.'>User</option>
                                                <option value="admin" '.$selectedAdmin.'>Admin</option>
                                            </select>
                                            <button type="submit" style="padding:7px 15px; font-size:0.88rem;">Update</button>
                                        </form>
                                    </td>';
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="footer">
        <p>&copy; Joseph Patron || <?php echo date("Y"); ?> Inventory System</p>
    </div>
    <script src="script.js?<?php echo $cacheBuster; ?>"></script>
</body>
</html>
