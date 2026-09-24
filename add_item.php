<?php
include('db_config.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$cacheBuster = 'v=' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Item</title>
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
                <h2>Add New Item</h2>
                <form class="add-item-form" method="post" action="add_item_process.php">
                    <label for="item_name"><i class="fas fa-tag" style="margin-right:6px; color:#8B0000;"></i>Item Name:</label>
                    <input type="text" id="item_name" name="name" required placeholder="Enter item name">
                    <label for="description"><i class="fas fa-file-alt" style="margin-right:6px; color:#8B0000;"></i>Description:</label>
                    <input type="text" id="description" name="description" required placeholder="Enter item description">
                    <label for="quantity"><i class="fas fa-hashtag" style="margin-right:6px; color:#8B0000;"></i>Quantity:</label>
                    <input type="number" id="quantity" name="quantity" required min="0" placeholder="0">
                    <label for="barcode"><i class="fas fa-barcode" style="margin-right:6px; color:#8B0000;"></i>Barcode:</label>
                    <input type="text" id="barcode" name="barcode" placeholder="Scan or enter barcode (optional)" autocomplete="off">
                    <button type="submit"><i class="fas fa-plus-circle" style="margin-right:8px;"></i>Add Item</button>
                </form>
            </div>
        </div>
    </div>
    <div class="footer">
        <p>&copy; Joseph Patron || <?php echo date("Y"); ?> Inventory System</p>
    </div>
    <script src="script.js?<?php echo $cacheBuster; ?>"></script>
</body>
</html>
