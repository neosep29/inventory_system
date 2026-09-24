<?php
header('Content-Type: application/json; charset=utf-8');
include('db_config.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$RL_RATE_DIR = __DIR__ . DIRECTORY_SEPARATOR . '.tmp_rate';
if (!is_dir($RL_RATE_DIR)) {
    @mkdir($RL_RATE_DIR, 0775, true);
}
function rfid_rate_check_login($ip) {
    global $RL_RATE_DIR;
    $window = 60;
    $max = 30;
    $safeIp = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$ip);
    if ($safeIp === '') $safeIp = 'unknown';
    $file = $RL_RATE_DIR . DIRECTORY_SEPARATOR . 'rl_login_' . $safeIp . '.json';
    $now = time();
    $data = null;
    if ($fp = @fopen($file, 'c+')) {
        if (flock($fp, LOCK_EX)) {
            $raw = stream_get_contents($fp);
            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['ts']) || !is_array($data['ts'])) {
                $data = ['ts' => []];
            }
            $data['ts'] = array_values(array_filter($data['ts'], function($t) use ($now, $window) {
                return ($now - (int)$t) < $window;
            }));
            $ok = count($data['ts']) < $max;
            if ($ok) {
                $data['ts'][] = $now;
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data, JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return $ok;
        }
        fclose($fp);
    }
    return true;
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
if (!rfid_rate_check_login($ip)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait a moment and try again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$raw = isset($_POST['rfid']) ? $_POST['rfid'] : '';
if (!is_string($raw) || trim($raw) === '') {
    echo json_encode(['status' => 'error', 'message' => 'Empty RFID value']);
    exit;
}

$norm = rfid_normalize($raw);
if (!rfid_plausible($norm)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid RFID format']);
    exit;
}

$sql = "SELECT id, username, role FROM users WHERE rfid_number = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database query preparation failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param('s', $norm);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $_SESSION['user_id']   = (int)$row['id'];
    $_SESSION['user_role'] = (string)$row['role'];
    session_write_close();
    echo json_encode([
        'status'   => 'ok',
        'redirect' => 'index.php',
        'user'     => [
            'id'       => (int)$row['id'],
            'username' => (string)$row['username'],
            'role'     => (string)$row['role'],
        ],
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'status'   => 'not_found',
        'message'  => 'RFID card not registered to any account.',
        'debug_rfid_normalized' => $norm,
    ], JSON_UNESCAPED_UNICODE);
}
$stmt->close();
$conn->close();
