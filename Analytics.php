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

// Builds an analytics.php?... link, merging the current query string with overrides
function analytics_link($overrides) {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        }
    }
    $qs = http_build_query($params);
    return 'analytics.php' . ($qs !== '' ? '?' . $qs : '');
}

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

// ---- Pagination for the "quantity per item" summary table ----
$itemTablePerPage = 10;
$itemTablePage = isset($_GET['item_page']) ? max(1, (int)$_GET['item_page']) : 1;
$itemTableTotalPages = max(1, (int)ceil(count($byItemLabels) / $itemTablePerPage));
if ($itemTablePage > $itemTableTotalPages) {
    $itemTablePage = $itemTableTotalPages;
}
$itemTableOffset = ($itemTablePage - 1) * $itemTablePerPage;
$pagedItemLabels = array_slice($byItemLabels, $itemTableOffset, $itemTablePerPage);
$pagedItemCounts = array_slice($byItemCounts, $itemTableOffset, $itemTablePerPage);
$pagedItemPercents = array_slice($byItemPercents, $itemTableOffset, $itemTablePerPage);

// ---- Admin only: staff list + drill-down into one staff member's withdrawal history ----
$staffList = [];
$selectedStaffId = 0;
$selectedStaffUsername = '';
$staffDetailRows = [];
$staffDetailTotal = 0;
$staffDetailPerPage = 10;
$staffDetailPage = isset($_GET['staff_page']) ? max(1, (int)$_GET['staff_page']) : 1;

if ($isAdmin) {
    $staffSql = "SELECT u.id, u.username, COUNT(w.item_id) AS total
                 FROM users u
                 LEFT JOIN withdrawal_logs w ON w.user_id = u.id
                 GROUP BY u.id, u.username
                 ORDER BY u.username ASC";
    $staffResult = $conn->query($staffSql);
    if ($staffResult) {
        while ($row = $staffResult->fetch_assoc()) {
            $staffList[] = ['id' => (int)$row['id'], 'username' => $row['username'], 'total' => (int)$row['total']];
        }
    }

    $selectedStaffId = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;

    if ($selectedStaffId > 0) {
        $checkStmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $checkStmt->bind_param("i", $selectedStaffId);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result();

        if ($checkRes->num_rows === 1) {
            $selectedStaffUsername = $checkRes->fetch_assoc()['username'];

            // Group rows that share the same item + exact timestamp into one "withdrawal event" with a quantity,
            // since each unit withdrawn is logged as its own row.
            $countStmt = $conn->prepare(
                "SELECT COUNT(*) AS total FROM (
                    SELECT item_id, withdrawal_date FROM withdrawal_logs WHERE user_id = ? GROUP BY item_id, withdrawal_date
                 ) grouped"
            );
            $countStmt->bind_param("i", $selectedStaffId);
            $countStmt->execute();
            $staffDetailTotal = (int)$countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();

            $staffDetailTotalPages = max(1, (int)ceil($staffDetailTotal / $staffDetailPerPage));
            if ($staffDetailPage > $staffDetailTotalPages) {
                $staffDetailPage = $staffDetailTotalPages;
            }
            $staffDetailOffset = ($staffDetailPage - 1) * $staffDetailPerPage;

            $detailStmt = $conn->prepare(
                "SELECT i.name, COUNT(*) AS qty, w.withdrawal_date
                 FROM withdrawal_logs w
                 JOIN items i ON w.item_id = i.id
                 WHERE w.user_id = ?
                 GROUP BY w.item_id, w.withdrawal_date, i.name
                 ORDER BY w.withdrawal_date DESC
                 LIMIT ? OFFSET ?"
            );
            $detailStmt->bind_param("iii", $selectedStaffId, $staffDetailPerPage, $staffDetailOffset);
            $detailStmt->execute();
            $detailRes = $detailStmt->get_result();
            while ($row = $detailRes->fetch_assoc()) {
                $staffDetailRows[] = $row;
            }
            $detailStmt->close();
        } else {
            $selectedStaffId = 0;
        }
        $checkStmt->close();
    }
}

// ---- Monthly breakdown (last 6 months, including the current one) ----
$monthsToShow = 6;
$monthKeys = [];   // e.g. "2026-04"
$monthLabels = []; // e.g. "Apr 2026"
for ($m = $monthsToShow - 1; $m >= 0; $m--) {
    $ts = strtotime("-$m months", strtotime(date('Y-m-01')));
    $monthKeys[] = date('Y-m', $ts);
    $monthLabels[] = date('M Y', $ts);
}
$earliestMonthStart = date('Y-m-01 00:00:00', strtotime('-' . ($monthsToShow - 1) . ' months', strtotime(date('Y-m-01'))));

$monthlyDatasets = []; // [{label, data:[...]}]
$maxStaffSeries = 8;   // cap series shown so the legend stays readable

if ($isAdmin) {
    $monthlyStaffData = []; // username => [ym => count]
    $monthlySql = "SELECT DATE_FORMAT(w.withdrawal_date, '%Y-%m') AS ym, u.username, COUNT(*) AS total
                   FROM withdrawal_logs w
                   JOIN users u ON w.user_id = u.id
                   WHERE w.withdrawal_date >= ?
                   GROUP BY ym, u.username";
    $stmt = $conn->prepare($monthlySql);
    if ($stmt) {
        $stmt->bind_param("s", $earliestMonthStart);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $monthlyStaffData[$row['username']][$row['ym']] = (int)$row['total'];
        }
        $stmt->close();
    }

    // Rank staff by total activity in this window, keep the busiest ones as separate bars
    $staffTotals = [];
    foreach ($monthlyStaffData as $uname => $months) {
        $staffTotals[$uname] = array_sum($months);
    }
    arsort($staffTotals);
    $topStaffUsernames = array_slice(array_keys($staffTotals), 0, $maxStaffSeries);

    foreach ($topStaffUsernames as $uname) {
        $data = [];
        foreach ($monthKeys as $mk) {
            $data[] = $monthlyStaffData[$uname][$mk] ?? 0;
        }
        $monthlyDatasets[] = ['label' => $uname, 'data' => $data];
    }
    $moreStaffCount = count($staffTotals) - count($topStaffUsernames);
} else {
    $monthlyOwnData = array_fill_keys($monthKeys, 0);
    $stmt = $conn->prepare("SELECT DATE_FORMAT(w.withdrawal_date, '%Y-%m') AS ym, COUNT(*) AS total
                             FROM withdrawal_logs w
                             WHERE w.user_id = ? AND w.withdrawal_date >= ?
                             GROUP BY ym");
    if ($stmt) {
        $stmt->bind_param("is", $userId, $earliestMonthStart);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            if (isset($monthlyOwnData[$row['ym']])) {
                $monthlyOwnData[$row['ym']] = (int)$row['total'];
            }
        }
        $stmt->close();
    }
    $monthlyDatasets[] = ['label' => 'My Withdrawals', 'data' => array_values($monthlyOwnData)];
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

            <?php if ($isAdmin): ?>
            <div class="page-title">
                <h2>Staff</h2>
                <p class="subtitle">Click a staff member to see everything they've withdrawn</p>
            </div>

            <div class="staff-list-grid">
                <?php foreach ($staffList as $staff): ?>
                    <a href="<?php echo htmlspecialchars(analytics_link(['staff_id' => $staff['id'], 'staff_page' => null])); ?>#staff-detail"
                       class="staff-list-card <?php echo $staff['id'] === $selectedStaffId ? 'staff-list-card-active' : ''; ?>">
                        <div class="staff-list-avatar"><i class="fas fa-user"></i></div>
                        <div class="staff-list-info">
                            <div class="staff-list-name"><?php echo htmlspecialchars($staff['username']); ?></div>
                            <div class="staff-list-count"><?php echo $staff['total']; ?> withdrawal<?php echo $staff['total'] === 1 ? '' : 's'; ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($selectedStaffId > 0): ?>
            <div id="staff-detail" class="page-title" style="margin-top:34px;">
                <h2><i class="fas fa-id-card" style="margin-right:8px;"></i><?php echo htmlspecialchars($selectedStaffUsername); ?>'s Withdrawals</h2>
                <p class="subtitle"><?php echo $staffDetailTotal; ?> withdrawal event<?php echo $staffDetailTotal === 1 ? '' : 's'; ?> total</p>
            </div>

            <?php if (empty($staffDetailRows)): ?>
                <div class="alert alert-info" style="text-align:center;">This staff member has no withdrawal history yet.</div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="view-items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Withdrawal Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffDetailRows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo (int)$row['qty']; ?></td>
                                    <td><?php echo htmlspecialchars(date("M j, Y g:i A", strtotime($row['withdrawal_date']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($staffDetailTotalPages > 1): ?>
                <div class="pagination">
                    <?php if ($staffDetailPage > 1): ?>
                        <a href="<?php echo htmlspecialchars(analytics_link(['staff_page' => $staffDetailPage - 1])); ?>#staff-detail" class="pagination-button">&larr; Previous Page</a>
                    <?php endif; ?>
                    <span class="alert alert-info" style="margin:0;">Page <?php echo $staffDetailPage; ?> of <?php echo $staffDetailTotalPages; ?></span>
                    <?php if ($staffDetailPage < $staffDetailTotalPages): ?>
                        <a href="<?php echo htmlspecialchars(analytics_link(['staff_page' => $staffDetailPage + 1])); ?>#staff-detail" class="pagination-button">Next Page &rarr;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php endif; ?>

            <div class="page-title" style="margin-top:40px;">
                <h2>Overview</h2>
                <p class="subtitle">System-wide charts and breakdown</p>
            </div>
            <?php endif; ?>

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
                <div class="analytics-chart-card analytics-chart-card-wide">
                    <h3><i class="fas fa-calendar-alt" style="margin-right:8px;"></i>
                        <?php echo $isAdmin ? 'Monthly Withdrawals by Staff' : 'My Monthly Withdrawals'; ?>
                        <span style="font-size:0.75rem; font-weight:400; color:var(--color-text-light); margin-left:8px;">(last <?php echo $monthsToShow; ?> months)</span>
                    </h3>
                    <div class="analytics-canvas-wrap"><canvas id="monthlyChart"></canvas></div>
                    <?php if ($isAdmin && !empty($moreStaffCount) && $moreStaffCount > 0): ?>
                        <p style="font-size:0.8rem; color:var(--color-text-light); margin-top:10px; text-align:center;">
                            Showing the <?php echo $maxStaffSeries; ?> most active staff. <?php echo $moreStaffCount; ?> more staff member<?php echo $moreStaffCount === 1 ? '' : 's'; ?> withdrew items in this period but <?php echo $moreStaffCount === 1 ? 'is' : 'are'; ?> not shown to keep the chart readable.
                        </p>
                    <?php endif; ?>
                </div>
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
                        <?php for ($i = 0; $i < count($pagedItemLabels); $i++): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pagedItemLabels[$i]); ?></td>
                                <td><?php echo $pagedItemCounts[$i]; ?></td>
                                <td><?php echo $pagedItemPercents[$i]; ?>%</td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($itemTableTotalPages > 1): ?>
            <div class="pagination">
                <?php if ($itemTablePage > 1): ?>
                    <a href="<?php echo htmlspecialchars(analytics_link(['item_page' => $itemTablePage - 1])); ?>" class="pagination-button">&larr; Previous Page</a>
                <?php endif; ?>
                <span class="alert alert-info" style="margin:0;">Page <?php echo $itemTablePage; ?> of <?php echo $itemTableTotalPages; ?></span>
                <?php if ($itemTablePage < $itemTableTotalPages): ?>
                    <a href="<?php echo htmlspecialchars(analytics_link(['item_page' => $itemTablePage + 1])); ?>" class="pagination-button">Next Page &rarr;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

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
        const monthLabels = <?php echo json_encode($monthLabels); ?>;
        const monthlyDatasets = <?php echo json_encode($monthlyDatasets); ?>;

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

        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: monthlyDatasets.map(function(ds, i) {
                    return {
                        label: ds.label,
                        data: ds.data,
                        backgroundColor: palette[i % palette.length],
                        borderRadius: 4
                    };
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: monthlyDatasets.length > 1, position: 'bottom' }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>