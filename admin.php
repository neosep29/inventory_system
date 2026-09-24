<?php
include('db_config.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userRole = $_SESSION['user_role'];
if ($userRole !== 'admin') {
    header("Location: index.php");
    exit;
}

$logsPerPage = 15;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $logsPerPage;

$cacheBuster = 'v=' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Page - Withdrawal Logs</title>
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
                <h2>Withdrawal Logs</h2>
                <p class="subtitle">Complete history of all item withdrawals</p>
            </div>
            <div class="table-wrapper">
                <table class="admin-logs-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Item</th>
                            <th>Withdrawal Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT w.withdrawal_date, u.username, i.name 
                        FROM withdrawal_logs w 
                        JOIN users u ON w.user_id = u.id 
                        JOIN items i ON w.item_id = i.id 
                        ORDER BY w.withdrawal_date DESC
                        LIMIT $offset, $logsPerPage";

                        $stmt = $conn->prepare($sql);

                        if ($stmt === false) {
                            echo "<tr><td colspan='3' class='alert alert-warning'>Error preparing the query: " . htmlspecialchars($conn->error) . "</td></tr>";
                        } else {
                            if ($stmt->execute()) {
                                $result = $stmt->get_result();
                                if ($result->num_rows === 0) {
                                    echo "<tr><td colspan='3' style='text-align:center; padding:30px; color:#6c757d;'>No withdrawal logs found.</td></tr>";
                                } else {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                                        echo "<td>" . htmlspecialchars($row["name"]) . "</td>";
                                        $formattedDate = date("M j, Y g:i A", strtotime($row["withdrawal_date"]));
                                        echo "<td>" . htmlspecialchars($formattedDate) . "</td>";
                                        echo "</tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td colspan='3' class='alert alert-warning'>Error executing the query: " . htmlspecialchars($stmt->error) . "</td></tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <?php
                $totalLogs = $conn->query("SELECT COUNT(*) FROM withdrawal_logs")->fetch_row()[0];
                $totalPages = ceil($totalLogs / $logsPerPage);
                if ($page > 1) {
                    echo "<a href='admin.php?page=" . ($page - 1) . "'>&larr; Previous Page</a>";
                }
                if ($totalPages > 1) {
                    echo "<span class='alert alert-info' style='margin:0;'>Page $page of $totalPages</span>";
                }
                if ($page < $totalPages) {
                    echo "<a href='admin.php?page=" . ($page + 1) . "'>Next Page &rarr;</a>";
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
