<?php
include('db_config.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Maroon/gold palette reused across all charts on this page
$chartPalette = ['#8B0000', '#C9A962', '#a52a2a', '#5a0000', '#e0c98a', '#28a745', '#17a2b8', '#a88d4f', '#dc3545', '#6c757d'];

$byItemLabels = [];
$byItemCounts = [];
$byItemPercents = [];
$grandTotal = 0;

$byUserLabels = [];
$byUserCounts = [];

if ($isAdmin) {
    // ---- Admin: withdrawals by item, across ALL staff ----
    $sql = "SELECT i.name, COUNT(*) AS total
            FROM withdrawal_logs w
            JOIN items i ON w.item_id = i.id
            GROUP BY i.name
            ORDER BY total DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $byItemLabels[] = $row['name'];
            $byItemCounts[] = (int)$row['total'];
        }
    }

    $grandTotalResult = $conn->query("SELECT COUNT(*) AS total FROM withdrawal_logs");
    $grandTotal = $grandTotalResult ? (int)$grandTotalResult->fetch_assoc()['total'] : 0;

    // ---- Admin: top staff by number of withdrawals ----
    $userSql = "SELECT u.username, COUNT(*) AS total
                FROM withdrawal_logs w
                JOIN users u ON w.user_id = u.id
                GROUP BY u.username
                ORDER BY total DESC
                LIMIT 10";
    $userResult = $conn->query($userSql);
    if ($userResult) {
        while ($row = $userResult->fetch_assoc()) {
            $byUserLabels[] = $row['username'];
            $byUserCounts[] = (int)$row['total'];
        }
    }
} else {
    // ---- Staff: their own withdrawals only ----
    $stmt = $conn->prepare("SELECT i.name, COUNT(*) AS total
                             FROM withdrawal_logs w
                             JOIN items i ON w.item_id = i.id
                             WHERE w.user_id = ?
                             GROUP BY i.name
                             ORDER BY total DESC");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $byItemLabels[] = $row['name'];
            $byItemCounts[] = (int)$row['total'];
        }
        $stmt->close();
    }

    $totalStmt = $conn->prepare("SELECT COUNT(*) AS total FROM withdrawal_logs WHERE user_id = ?");
    if ($totalStmt) {
        $totalStmt->bind_param("i", $userId);
        $totalStmt->execute();
        $grandTotal = (int)$totalStmt->get_result()->fetch_assoc()['total'];
        $totalStmt->close();
    }
}

foreach ($byItemCounts as $c) {
    $byItemPercents[] = $grandTotal > 0 ? round(($c / $grandTotal) * 100, 1) : 0;
}

$cacheBuster = 'v=' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="css/style.css?<?php echo $cacheBuster; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
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
                <h2><?php echo $isAdmin ? 'Staff Withdrawal Analytics' : 'My Withdrawal Analytics'; ?></h2>
                <p class="subtitle">
                    <?php echo $isAdmin
                        ? 'Item and staff withdrawal breakdown across the whole system'
                        : 'A breakdown of the items you have withdrawn'; ?>
                </p>
            </div>

            <?php if ($grandTotal === 0): ?>
                <div class="alert alert-info" style="text-align:center;">
                    No withdrawal history yet<?php echo $isAdmin ? ' for any staff member.' : ' for your account.'; ?>
                </div>
            <?php else: ?>

            <div class="analytics-summary" style="display:flex; gap:16px; flex-wrap:wrap; justify-content:center; margin-bottom:28px;">
                <div class="analytics-stat-card">
                    <div class="analytics-stat-value"><?php echo $grandTotal; ?></div>
                    <div class="analytics-stat-label"><?php echo $isAdmin ? 'Total Withdrawals (All Staff)' : 'Total Items Withdrawn'; ?></div>
                </div>
                <div class="analytics-stat-card">
                    <div class="analytics-stat-value"><?php echo count($byItemLabels); ?></div>
                    <div class="analytics-stat-label">Distinct Items <?php echo $isAdmin ? 'Withdrawn' : 'You Withdrew'; ?></div>
                </div>
                <?php if ($isAdmin): ?>
                <div class="analytics-stat-card">
                    <div class="analytics-stat-value"><?php echo count($byUserLabels); ?></div>
                    <div class="analytics-stat-label">Staff With Withdrawal Activity</div>
                </div>
                <?php endif; ?>
            </div>

            <div class="analytics-chart-grid">
                <div class="analytics-chart-card">
                    <h3><i class="fas fa-chart-bar" style="margin-right:8px;"></i>Quantity Withdrawn by Item</h3>
                    <div class="analytics-canvas-wrap"><canvas id="itemQuantityChart"></canvas></div>
                </div>
                <div class="analytics-chart-card">
                    <h3><i class="fas fa-chart-pie" style="margin-right:8px;"></i>Share of Withdrawals by Item</h3>
                    <div class="analytics-canvas-wrap"><canvas id="itemPercentChart"></canvas></div>
                </div>
                <?php if ($isAdmin): ?>
                <div class="analytics-chart-card analytics-chart-card-wide">
                    <h3><i class="fas fa-users" style="margin-right:8px;"></i>Top Staff by Withdrawals</h3>
                    <div class="analytics-canvas-wrap"><canvas id="userQuantityChart"></canvas></div>
                </div>
                <?php endif; ?>
            </div>

            <div class="table-wrapper" style="margin-top:30px;">
                <table class="view-items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity Withdrawn</th>
                            <th>% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < count($byItemLabels); $i++): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($byItemLabels[$i]); ?></td>
                                <td><?php echo $byItemCounts[$i]; ?></td>
                                <td><?php echo $byItemPercents[$i]; ?>%</td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <?php endif; ?>
        </div>
    </div>
    <div class="footer">
        <p>&copy; Joseph Patron || <?php echo date("Y"); ?> Inventory System</p>
    </div>
    <script src="script.js?<?php echo $cacheBuster; ?>"></script>

    <?php if ($grandTotal > 0): ?>
    <script>
    (function() {
        const palette = <?php echo json_encode($chartPalette); ?>;
        const itemLabels = <?php echo json_encode($byItemLabels); ?>;
        const itemCounts = <?php echo json_encode($byItemCounts); ?>;
        const itemPercents = <?php echo json_encode($byItemPercents); ?>;
        <?php if ($isAdmin): ?>
        const userLabels = <?php echo json_encode($byUserLabels); ?>;
        const userCounts = <?php echo json_encode($byUserCounts); ?>;
        <?php endif; ?>

        new Chart(document.getElementById('itemQuantityChart'), {
            type: 'bar',
            data: {
                labels: itemLabels,
                datasets: [{
                    label: 'Quantity Withdrawn',
                    data: itemCounts,
                    backgroundColor: '#8B0000',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });

        new Chart(document.getElementById('itemPercentChart'), {
            type: 'doughnut',
            data: {
                labels: itemLabels,
                datasets: [{
                    data: itemPercents,
                    backgroundColor: palette
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) { return ctx.label + ': ' + ctx.raw + '%'; }
                        }
                    }
                }
            }
        });

        <?php if ($isAdmin): ?>
        new Chart(document.getElementById('userQuantityChart'), {
            type: 'bar',
            data: {
                labels: userLabels,
                datasets: [{
                    label: 'Withdrawals',
                    data: userCounts,
                    backgroundColor: '#C9A962',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
        <?php endif; ?>
    })();
    </script>
    <?php endif; ?>
</body>
</html>