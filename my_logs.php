<?php
include('db_config.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$logsPerPage = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $logsPerPage;

$sql = "SELECT w.withdrawal_date, i.name
        FROM withdrawal_logs w
        JOIN items i ON w.item_id = i.id
        WHERE w.user_id = ?
        ORDER BY w.withdrawal_date DESC
        LIMIT $logsPerPage OFFSET $offset";

$stmt = $conn->prepare($sql);
$result = false;
$logError = null;

if ($stmt === false) {
    $logError = "Error preparing the query: " . $conn->error;
} else {
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        $logError = "Error executing the query: " . $stmt->error;
    } else {
        $result = $stmt->get_result();
    }
}

$cacheBuster = 'v=' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Logs</title>
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
                <h2>My Withdrawal Logs</h2>
                <p class="subtitle">History of items you have withdrawn</p>
            </div>
            <div class="view-container" style="padding:0;">
                <?php if ($logError): ?>
                    <div class="error"><i class="fas fa-exclamation-circle" style="margin-right:8px;"></i><?php echo htmlspecialchars($logError); ?></div>
                <?php else: ?>
                <div class="table-wrapper">
                    <table class="view-items-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Withdrawal Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows === 0) {
                                echo "<tr><td colspan='2' style='text-align:center; padding:30px; color:#6c757d;'>You have no withdrawal logs yet.</td></tr>";
                            } else {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row["name"]) . "</td>";
                                    $formattedDate = date("M j, Y g:i A", strtotime($row["withdrawal_date"]));
                                    echo "<td>" . htmlspecialchars($formattedDate) . "</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <div class="pagination">
                <?php
                $totalLogsSql = "SELECT COUNT(*) as total FROM withdrawal_logs WHERE user_id = $user_id";
                $totalLogsResult = $conn->query($totalLogsSql);
                $totalLogs = 0;
                if ($totalLogsResult) {
                    $totalLogs = (int)$totalLogsResult->fetch_assoc()['total'];
                }
                $totalPages = ceil($totalLogs / $logsPerPage);

                if ($page > 1) {
                    echo "<a href='my_logs.php?page=" . ($page - 1) . "' class='pagination-button'>&larr; Previous Page</a>";
                }
                if ($totalPages > 1) {
                    echo "<span class='alert alert-info' style='margin:0;'>Page $page of $totalPages</span>";
                }
                if ($page < $totalPages) {
                    echo "<a href='my_logs.php?page=" . ($page + 1) . "' class='pagination-button'>Next Page &rarr;</a>";
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
