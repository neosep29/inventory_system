<?php
include('db_config.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

function sendJson($data) {
    echo json_encode($data);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendJson(['status' => 'error', 'message' => 'Invalid request method.']);
}

if (!isset($_SESSION['user_id'])) {
    sendJson(['status' => 'error', 'message' => 'User not logged in.']);
}

$userId = (int)$_SESSION['user_id'];
$action = isset($_POST['action']) ? $_POST['action'] : '';
$scannedBarcode = isset($_POST['scannedBarcode']) ? trim($_POST['scannedBarcode']) : '';

if (empty($scannedBarcode)) {
    sendJson(['status' => 'error', 'message' => 'Barcode cannot be empty.']);
}

$stmt = $conn->prepare("SELECT id, name, description, quantity, barcode FROM items WHERE barcode = ? LIMIT 1");
if (!$stmt) {
    sendJson(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}
$stmt->bind_param("s", $scannedBarcode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    sendJson(['status' => 'not_found', 'message' => 'Item not in the database.']);
}

$row = $result->fetch_assoc();
$stmt->close();

$itemId = (int)$row['id'];
$itemName = $row['name'];
$itemQuantity = (int)$row['quantity'];

if ($action === 'lookup') {
    sendJson([
        'status' => 'found',
        'item' => [
            'id' => $itemId,
            'name' => $itemName,
            'description' => $row['description'],
            'quantity' => $itemQuantity,
            'barcode' => $row['barcode']
        ]
    ]);
}

if ($action === 'withdraw') {
    $requestedQty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    if ($requestedQty <= 0) {
        sendJson(['status' => 'error', 'message' => 'Please enter a valid quantity greater than 0.']);
    }

    if ($requestedQty > $itemQuantity) {
        sendJson([
            'status' => 'error',
            'message' => 'Insufficient stock. Only ' . $itemQuantity . ' item(s) available.'
        ]);
    }

    $newQuantity = $itemQuantity - $requestedQty;

    $conn->begin_transaction();

    try {
        $updateStmt = $conn->prepare("UPDATE items SET quantity = ? WHERE id = ?");
        if (!$updateStmt) {
            throw new Exception('Update prepare failed: ' . $conn->error);
        }
        $updateStmt->bind_param("ii", $newQuantity, $itemId);
        if (!$updateStmt->execute()) {
            throw new Exception('Update execute failed: ' . $updateStmt->error);
        }
        $updateStmt->close();

        $logStmt = $conn->prepare("INSERT INTO withdrawal_logs (user_id, item_id) VALUES (?, ?)");
        if (!$logStmt) {
            throw new Exception('Log prepare failed: ' . $conn->error);
        }
        for ($i = 0; $i < $requestedQty; $i++) {
            $logStmt->bind_param("ii", $userId, $itemId);
            if (!$logStmt->execute()) {
                throw new Exception('Log insert failed: ' . $logStmt->error);
            }
        }
        $logStmt->close();

        $conn->commit();

        sendJson([
            'status' => 'success',
            'message' => 'Successfully withdrew ' . $requestedQty . ' unit(s) of ' . $itemName . '.',
            'remaining' => $newQuantity
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        sendJson(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

sendJson(['status' => 'error', 'message' => 'Invalid action.']);
