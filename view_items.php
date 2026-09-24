<?php
include('db_config.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$itemsPerPage = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) { $page = 1; }
$offset = ($page - 1) * $itemsPerPage;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search !== '') {
    $likeTerm = '%' . $search . '%';

    $stmt = $conn->prepare("SELECT * FROM items WHERE name LIKE ? OR description LIKE ? ORDER BY name ASC LIMIT ? OFFSET ?");
    $stmt->bind_param("ssii", $likeTerm, $likeTerm, $itemsPerPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM items WHERE name LIKE ? OR description LIKE ?");
    $totalStmt->bind_param("ss", $likeTerm, $likeTerm);
    $totalStmt->execute();
    $totalItems = (int)$totalStmt->get_result()->fetch_assoc()['total'];
    $totalStmt->close();
} else {
    $sql = "SELECT * FROM items ORDER BY name ASC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $itemsPerPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $totalItems = 0;
    $totalItemsResult = $conn->query("SELECT COUNT(*) as total FROM items");
    if ($totalItemsResult) {
        $totalItems = (int)$totalItemsResult->fetch_assoc()['total'];
    }
}

// Used to keep the search term in pagination links
$searchQueryParam = $search !== '' ? '&search=' . urlencode($search) : '';

$cacheBuster = 'v=' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Items</title>
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
                <h2>View Items</h2>
                <p class="subtitle">Current inventory items and stock levels</p>
            </div>

            <div class="item-search-bar">
                <form method="get" action="view_items.php" class="item-search-form">
                    <div class="item-search-input-wrap">
                        <i class="fas fa-search item-search-icon"></i>
                        <input type="text" name="search" id="itemSearchInput" placeholder="Search items by name or description..."
                               value="<?php echo htmlspecialchars($search); ?>" autocomplete="off">
                    </div>
                    <button type="submit"><i class="fas fa-search" style="margin-right:6px;"></i>Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="view_items.php" class="item-search-clear"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
                <?php if ($search !== ''): ?>
                    <p class="item-search-results-info">
                        <?php echo $totalItems; ?> result<?php echo $totalItems === 1 ? '' : 's'; ?> for "<?php echo htmlspecialchars($search); ?>"
                    </p>
                <?php endif; ?>
            </div>

            <div class="view-container">
                <div class="table-wrapper">
                    <table class="view-items-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!$result || $result->num_rows === 0) {
                                if ($search !== '') {
                                    echo "<tr><td colspan='3' style='text-align:center; padding:30px; color:#6c757d;'>No items match \"" . htmlspecialchars($search) . "\".</td></tr>";
                                } else {
                                    echo "<tr><td colspan='3' style='text-align:center; padding:30px; color:#6c757d;'>No items found in inventory.</td></tr>";
                                }
                            } else {
                                while ($row = $result->fetch_assoc()) {
                                    $qty = intval($row["quantity"]);
                                    $qtyClass = '';
                                    if ($qty <= 5) {
                                        $qtyClass = 'quantity-low';
                                    } elseif ($qty <= 15) {
                                        $qtyClass = 'quantity-medium';
                                    } else {
                                        $qtyClass = 'quantity-high';
                                    }
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row["name"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
                                    echo "<td class='$qtyClass'>" . htmlspecialchars($row["quantity"]) . "</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pagination">
                <?php
                $totalPages = ceil($totalItems / $itemsPerPage);
                if ($page > 1) {
                    echo "<a href='view_items.php?page=" . ($page - 1) . $searchQueryParam . "' class='pagination-button'>&larr; Previous Page</a>";
                }
                if ($totalPages > 1) {
                    echo "<span class='alert alert-info' style='margin:0;'>Page $page of $totalPages</span>";
                }
                if ($page < $totalPages) {
                    echo "<a href='view_items.php?page=" . ($page + 1) . $searchQueryParam . "' class='pagination-button'>Next Page &rarr;</a>";
                }
                ?>
            </div>
        </div>
    </div>
    <div class="footer">
        <p>&copy; Joseph Patron || <?php echo date("Y"); ?> Inventory System</p>
    </div>
    <script src="script.js?<?php echo $cacheBuster; ?>"></script>
    <script>
    (function() {
        const searchInput = document.getElementById('itemSearchInput');
        const searchForm = searchInput ? searchInput.closest('form') : null;
        if (!searchInput || !searchForm) return;

        let debounceTimer = null;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                searchForm.submit();
            }, 500);
        });
    })();
    </script>
</body>
</html>