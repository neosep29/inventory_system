<?php
include('db_config.php');

function rfid_normalize($s) {
    $s = is_string($s) ? trim($s) : '';
    $s = preg_replace('/[^A-Za-z0-9]/', '', $s);
    if (preg_match('/^[a-f0-9]+$/i', $s)) {
        $s = strtoupper($s);
    }
    return $s;
}

function rfid_plausible($s) {
    $len = strlen($s);
    if ($len < 4 || $len > 32) return false;
    return (bool)preg_match('/^[A-Za-z0-9]+$/', $s);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $rawRfid = isset($_POST['rfid_number']) ? $_POST['rfid_number'] : '';

    if ($username === '' || $password === '') {
        header("Location: registration.php?error=" . urlencode("Username and password are required."));
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    $role = "user";

    $normRfid = rfid_normalize($rawRfid);
    $rfidValue = null;
    if ($normRfid !== '') {
        if (!rfid_plausible($normRfid)) {
            header("Location: registration.php?error=" . urlencode("Invalid RFID format. Must be 4-32 alphanumeric characters, or leave blank."));
            exit();
        }
        $checkSql = "SELECT id FROM users WHERE rfid_number = ? LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);
        if ($checkStmt) {
            $checkStmt->bind_param('s', $normRfid);
            $checkStmt->execute();
            $checkRes = $checkStmt->get_result();
            if ($checkRes && $checkRes->num_rows > 0) {
                $checkStmt->close();
                header("Location: registration.php?error=" . urlencode("This RFID card is already linked to another account. Please use a different card, or leave the field blank and link it later."));
                exit();
            }
            $checkStmt->close();
        }
        $rfidValue = $normRfid;
    }

    $userCheck = "SELECT id FROM users WHERE username = ? LIMIT 1";
    $userStmt = $conn->prepare($userCheck);
    if ($userStmt) {
        $userStmt->bind_param('s', $username);
        $userStmt->execute();
        $userRes = $userStmt->get_result();
        if ($userRes && $userRes->num_rows > 0) {
            $userStmt->close();
            header("Location: registration.php?error=" . urlencode("Username already taken. Please choose a different username."));
            exit();
        }
        $userStmt->close();
    }

    if ($rfidValue !== null) {
        $sql = "INSERT INTO users (username, password, role, rfid_number) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssss", $username, $hashed_password, $role, $rfidValue);
        } else {
            $stmt = false;
        }
    } else {
        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sss", $username, $hashed_password, $role);
        } else {
            $stmt = false;
        }
    }

    if ($stmt && $stmt->execute()) {
        header("Location: login.php");
        exit();
    } else {
        $err = $stmt ? $stmt->error : $conn->error;
        header("Location: registration.php?error=" . urlencode("Registration failed. Please try again. (" . $err . ")"));
        exit();
    }
    if ($stmt) $stmt->close();
}

$conn->close();
