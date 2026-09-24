<?php
include('db_config.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
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

$user_id = (int)$_SESSION['user_id'];
$success_message = null;
$error_message = null;
$rfid_success = null;
$rfid_error = null;

$currentRfid = null;
$rfidFetch = "SELECT rfid_number FROM users WHERE id = ? LIMIT 1";
$rfidStmt = $conn->prepare($rfidFetch);
if ($rfidStmt) {
    $rfidStmt->bind_param('i', $user_id);
    $rfidStmt->execute();
    $rfidRes = $rfidStmt->get_result();
    if ($rfidRes && $rfidRes->num_rows === 1) {
        $row = $rfidRes->fetch_assoc();
        $currentRfid = $row['rfid_number'];
    }
    $rfidStmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'password';

    if ($action === 'password') {
        $old_password = isset($_POST['old_password']) ? $_POST['old_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $error_message = "Error preparing the query: " . $conn->error;
        } else {
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows == 1) {
                    $row = $result->fetch_assoc();
                    $hashed_password = $row['password'];

                    if (password_verify($old_password, $hashed_password)) {
                        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);

                        if ($update_stmt === false) {
                            $error_message = "Error preparing the update query: " . $conn->error;
                        } else {
                            $update_stmt->bind_param("si", $new_hashed_password, $user_id);

                            if ($update_stmt->execute()) {
                                $success_message = "Password updated successfully.";
                            } else {
                                $error_message = "Error updating the password: " . $update_stmt->error;
                            }
                            $update_stmt->close();
                        }
                    } else {
                        $error_message = "Incorrect old password.";
                    }
                }
            } else {
                $error_message = "Error executing the query: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    elseif ($action === 'assign_rfid') {
        $rawRfid = isset($_POST['rfid_number']) ? $_POST['rfid_number'] : '';
        $norm = rfid_normalize($rawRfid);

        if ($norm === '') {
            $rfid_error = "RFID number cannot be empty. Scan a card or enter it manually.";
        } elseif (!rfid_plausible($norm)) {
            $rfid_error = "Invalid RFID format. Must be 4-32 alphanumeric characters.";
        } else {
            $checkSql = "SELECT id, username FROM users WHERE rfid_number = ? AND id != ? LIMIT 1";
            $checkStmt = $conn->prepare($checkSql);
            if ($checkStmt) {
                $checkStmt->bind_param('si', $norm, $user_id);
                $checkStmt->execute();
                $checkRes = $checkStmt->get_result();
                if ($checkRes && $checkRes->num_rows > 0) {
                    $other = $checkRes->fetch_assoc();
                    $rfid_error = "RFID <strong>" . htmlspecialchars($norm) . "</strong> is already assigned to another account (<strong>" . htmlspecialchars($other['username']) . "</strong>). Please use a different card.";
                } else {
                    $upd = "UPDATE users SET rfid_number = ? WHERE id = ? LIMIT 1";
                    $updStmt = $conn->prepare($upd);
                    if ($updStmt) {
                        $updStmt->bind_param('si', $norm, $user_id);
                        if ($updStmt->execute()) {
                            $currentRfid = $norm;
                            $rfid_success = "RFID card linked successfully! Value saved: <strong>" . htmlspecialchars($norm) . "</strong>";
                        } else {
                            $rfid_error = "Failed to link RFID: " . $updStmt->error;
                        }
                        $updStmt->close();
                    }
                }
                $checkStmt->close();
            }
        }
    }
    elseif ($action === 'clear_rfid') {
        $upd = "UPDATE users SET rfid_number = NULL WHERE id = ? LIMIT 1";
        $updStmt = $conn->prepare($upd);
        if ($updStmt) {
            $updStmt->bind_param('i', $user_id);
            if ($updStmt->execute()) {
                $currentRfid = null;
                $rfid_success = "RFID card unlinked successfully. You can still sign in with your username and password.";
            } else {
                $rfid_error = "Failed to unlink RFID: " . $updStmt->error;
            }
            $updStmt->close();
        }
    }
}

$cacheBuster = 'v=' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Settings</title>
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
                <h2><i class="fas fa-lock" style="color:#8B0000; margin-right:8px;"></i>Change Password</h2>
                <form class="add-item-form" method="post" action="change_password.php">
                    <input type="hidden" name="action" value="password">
                    <label for="old_password"><i class="fas fa-lock" style="margin-right:6px; color:#8B0000;"></i>Old Password:</label>
                    <input type="password" id="old_password" name="old_password" required placeholder="Enter current password" autocomplete="current-password">
                    <label for="new_password"><i class="fas fa-key" style="margin-right:6px; color:#8B0000;"></i>New Password:</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter new password" autocomplete="new-password">
                    <button type="submit"><i class="fas fa-save" style="margin-right:8px;"></i>Update Password</button>
                </form>
                <?php
                if (isset($success_message)) {
                    echo '<div class="success"><i class="fas fa-check-circle" style="margin-right:8px;"></i>' . htmlspecialchars($success_message) . '</div>';
                }
                if (isset($error_message)) {
                    echo '<div class="error"><i class="fas fa-exclamation-circle" style="margin-right:8px;"></i>' . htmlspecialchars($error_message) . '</div>';
                }
                ?>
            </div>

            <div style="height:24px;"></div>

            <div class="add-item-container" id="rfidPanel" style="border-top:4px solid #C9A962;">
                <h2><i class="fas fa-id-card" style="color:#C9A962; margin-right:8px;"></i>Link / Update My RFID Card</h2>
                <p style="color:#6c757d; margin-bottom:18px; font-size:0.92rem;">
                    Link an RFID card to your account for quick sign-in. Once linked, simply wave your card on the login page — no username or password needed.
                </p>

                <div style="padding:14px 16px; border-radius:8px; margin-bottom:18px; background:<?php echo ($currentRfid !== null && trim((string)$currentRfid) !== '') ? 'linear-gradient(135deg,#e6f7ee 0%,#d4edda 100%)' : 'linear-gradient(135deg,#fff3cd 0%,#ffeeba 100%)'; ?>; border:1.5px solid <?php echo ($currentRfid !== null) ? '#c3e6cb' : '#ffeeba'; ?>;">
                    <div style="display:flex; align-items:center; gap:10px; font-weight:600; color:<?php echo ($currentRfid !== null) ? '#1e7e34' : '#856404'; ?>;">
                        <?php if ($currentRfid !== null && trim((string)$currentRfid) !== ''):
                            $len = strlen((string)$currentRfid);
                            $masked = ($len <= 4) ? str_repeat('*', $len) : (str_repeat('*', $len - 4) . substr((string)$currentRfid, -4));
                        ?>
                            <i class="fas fa-id-card"></i>
                            <span>RFID card currently linked: <code style="background:#fff; padding:2px 8px; border-radius:4px; font-family:Consolas, monospace;" title="Full value: <?php echo htmlspecialchars((string)$currentRfid); ?>"><?php echo htmlspecialchars($masked); ?></code></span>
                            <span style="margin-left:auto; font-size:0.78rem; background:#fff; padding:3px 10px; border-radius:999px; border:1px solid #28a745; color:#1e7e34;">ACTIVE</span>
                        <?php else: ?>
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>No RFID card linked yet</span>
                            <span style="margin-left:auto; font-size:0.78rem; background:#fff; padding:3px 10px; border-radius:999px; border:1px solid #ffc107; color:#856404;">NOT LINKED</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="scanner-flash" id="cpRfidFlash" style="position:static; transform:none; margin:0 auto 14px; display:inline-flex;">
                    <i class="fas fa-check-circle"></i>
                    <span>RFID captured — click Save below</span>
                </div>

                <div class="scanner-ready-card" id="cpRfidReady" style="margin-top:0; padding:22px 20px; margin-bottom:18px;">
                    <div class="scanner-ready-icon scanner-ready-icon-ready" id="cpRfidIcon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="scanner-ready-text">
                        <h3 id="cpRfidTitle">Ready — Scan Your RFID Card</h3>
                        <p id="cpRfidHint">Click anywhere on this panel first, then wave your card near the reader. It will auto-fill the field below.</p>
                    </div>
                </div>

                <input type="text" id="cpRfidGhost" class="ghost-scanner-input" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">

                <form method="post" action="change_password.php#rfidPanel" style="margin:0;" id="cpRfidAssignForm">
                    <input type="hidden" name="action" value="assign_rfid">
                    <label for="cpRfidInput" style="font-weight:600;">
                        <i class="fas fa-hashtag" style="margin-right:6px; color:#8B0000;"></i>Captured RFID Number:
                    </label>
                    <input type="text" id="cpRfidInput" name="rfid_number"
                           placeholder="Scan card above, or paste/type here..."
                           autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                           style="margin-bottom:8px;">
                    <p style="font-size:0.78rem; color:#6c757d; margin:-4px 0 16px;">
                        4-32 letters/numbers. Whitespace and dashes are stripped automatically.
                    </p>

                    <?php if ($rfid_success): ?>
                        <div class="success" style="margin-bottom:14px;"><?php echo $rfid_success; ?></div>
                    <?php endif; ?>
                    <?php if ($rfid_error): ?>
                        <div class="error" style="margin-bottom:14px;"><?php echo $rfid_error; ?></div>
                    <?php endif; ?>

                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="submit" id="cpRfidSaveBtn" style="background:#28a745; flex:1 1 180px;">
                            <i class="fas fa-save" style="margin-right:6px;"></i>Save This RFID
                        </button>
                        <?php if ($currentRfid !== null && trim((string)$currentRfid) !== ''): ?>
                            <button type="submit" formaction="change_password.php#rfidPanel" formmethod="post"
                                    name="action" value="clear_rfid"
                                    style="background:#6c757d; flex:1 1 180px;"
                                    onclick="return confirm('Unlink your RFID card? You will still be able to sign in with your username and password.');">
                                <i class="fas fa-unlink" style="margin-right:6px;"></i>Unlink Current Card
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="footer">
        <p>&copy; Joseph Patron || <?php echo date("Y"); ?> Inventory System</p>
    </div>
    <script src="script.js?<?php echo $cacheBuster; ?>"></script>
    <script>
    (function() {
        "use strict";

        var iconEl  = document.getElementById('cpRfidIcon');
        var titleEl = document.getElementById('cpRfidTitle');
        var hintEl  = document.getElementById('cpRfidHint');
        var flashEl = document.getElementById('cpRfidFlash');
        var ghostEl = document.getElementById('cpRfidGhost');
        var inputEl = document.getElementById('cpRfidInput');
        var panel   = document.getElementById('rfidPanel');

        var scanBuffer = '';
        var scanBufferTimer = null;
        var autoSubmitTimer = null;
        var RFID_MIN_LEN = 4;
        var RFID_MAX_LEN = 32;

        function escapeHtml(t) {
            return String(t).replace(/[&<>"']/g, function(c){
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        }

        function normalizeRfid(s) {
            s = String(s || '').trim();
            s = s.replace(/[^A-Za-z0-9]/g, '');
            if (/^[a-f0-9]+$/i.test(s)) s = s.toUpperCase();
            return s;
        }

        function clearTimers() {
            if (scanBufferTimer) { clearTimeout(scanBufferTimer); scanBufferTimer = null; }
            if (autoSubmitTimer) { clearTimeout(autoSubmitTimer); autoSubmitTimer = null; }
        }

        function setIcon(state, extra) {
            if (!iconEl) return;
            if (state === 'processing') {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-processing';
                iconEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                titleEl.textContent = 'Reading card...';
                hintEl.textContent = 'RFID characters detected';
            } else if (state === 'captured') {
                iconEl.className = 'scanner-ready-icon';
                iconEl.style.background = 'linear-gradient(135deg,#28a745 0%,#20c997 100%)';
                iconEl.style.boxShadow = '0 8px 22px rgba(40,167,69,0.42)';
                iconEl.innerHTML = '<i class="fas fa-check"></i>';
                titleEl.textContent = 'RFID captured';
                hintEl.textContent = extra || 'Review the number below and click Save to link this card.';
            } else if (state === 'error') {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-error';
                iconEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                titleEl.textContent = extra || 'Read error';
                hintEl.textContent = 'Try scanning again';
            } else {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-ready';
                iconEl.style.background = '';
                iconEl.style.boxShadow = '';
                iconEl.innerHTML = '<i class="fas fa-id-card"></i>';
                titleEl.textContent = 'Ready — Scan Your RFID Card';
                hintEl.textContent = 'Click anywhere on this panel first, then wave your card near the reader. It will auto-fill the field below.';
            }
        }

        function flash(msg) {
            if (!flashEl) return;
            if (msg) {
                var s = flashEl.querySelector('span');
                if (s) s.textContent = msg;
            }
            flashEl.style.opacity = '1';
            flashEl.style.transform = 'translateY(0) scale(1)';
            setTimeout(function() {
                flashEl.style.opacity = '0';
                flashEl.style.transform = 'translateY(-12px) scale(0.96)';
            }, 1200);
        }

        function pokeCapture() {
            if (autoSubmitTimer) clearTimeout(autoSubmitTimer);
            autoSubmitTimer = setTimeout(function() {
                autoSubmitTimer = null;
                var candidate = scanBuffer && scanBuffer.trim() ? scanBuffer : (ghostEl ? ghostEl.value : '');
                candidate = normalizeRfid(candidate);
                if (candidate.length >= RFID_MIN_LEN && candidate.length <= RFID_MAX_LEN) {
                    scanBuffer = '';
                    if (ghostEl) ghostEl.value = '';
                    inputEl.value = candidate;
                    setIcon('captured', 'Length: ' + candidate.length);
                    flash('RFID captured');
                }
            }, 260);
        }

        var focusTries = 0;
        function panelFocus() {
            if (document.activeElement === inputEl) return;
            if (!ghostEl) return;
            try {
                ghostEl.focus({ preventScroll: true });
                if (focusTries < 2) {
                    focusTries++;
                }
            } catch(e) {}
        }
        if (panel) {
            panel.addEventListener('click', function(e) {
                if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || e.target.closest('button') || e.target.closest('a'))) return;
                setTimeout(panelFocus, 40);
            });
        }
        setTimeout(panelFocus, 200);
        setTimeout(panelFocus, 600);
        setInterval(panelFocus, 3200);

        if (ghostEl) {
            ghostEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    var v = normalizeRfid(ghostEl.value || '');
                    if (v.length >= RFID_MIN_LEN) {
                        ghostEl.value = '';
                        clearTimers();
                        scanBuffer = '';
                        inputEl.value = v;
                        setIcon('captured', 'Length: ' + v.length);
                        flash('RFID captured');
                    }
                    return false;
                }
            }, true);
            ghostEl.addEventListener('input', function() {
                if (ghostEl.value.length > 0) {
                    scanBuffer = ghostEl.value;
                    pokeCapture();
                }
            });
        }

        function isTypingField(el) {
            if (!el) return false;
            var tag = (el.tagName || '').toLowerCase();
            var type = (el.type || '').toLowerCase();
            if (tag === 'textarea') return true;
            if (tag === 'select') return true;
            if (tag === 'input' && type !== 'submit' && type !== 'button' && type !== 'radio' && type !== 'checkbox') {
                if (el.id === 'cpRfidGhost') return false;
                if (el.id === 'cpRfidInput') return true;
                if (el.id === 'old_password' || el.id === 'new_password') return true;
                return true;
            }
            if (el.isContentEditable) return true;
            return false;
        }

        document.addEventListener('keydown', function(e) {
            if (isTypingField(e.target)) return;

            if (e.key === 'Enter' || e.keyCode === 13 || e.key === 'Tab') {
                if (scanBuffer.length > 0) {
                    e.preventDefault();
                    var code = normalizeRfid(scanBuffer);
                    clearTimers();
                    scanBuffer = '';
                    if (ghostEl) ghostEl.value = '';
                    if (code.length >= RFID_MIN_LEN && code.length <= RFID_MAX_LEN) {
                        inputEl.value = code;
                        setIcon('captured', 'Length: ' + code.length);
                        flash('RFID captured');
                    }
                    return false;
                }
                return;
            }

            var char = '';
            if (typeof e.key === 'string' && e.key.length === 1) {
                char = e.key;
            } else if (typeof e.which === 'number' && e.which >= 32 && e.which !== 127) {
                char = String.fromCharCode(e.which);
            }
            if (!char) return;

            scanBuffer += char;
            if (scanBuffer.length > 120) scanBuffer = scanBuffer.slice(-120);
            setIcon('processing');
            clearTimers();
            pokeCapture();
            scanBufferTimer = setTimeout(function() {
                if (scanBuffer.length > 0) {
                    var snap = normalizeRfid(scanBuffer);
                    if (snap.length < RFID_MIN_LEN || snap.length > RFID_MAX_LEN) {
                        setIcon('error', 'Bad length (' + snap.length + ')');
                        setTimeout(function(){ setIcon('ready'); }, 1600);
                    }
                }
                scanBuffer = '';
                scanBufferTimer = null;
            }, 1200);
        }, true);
    })();
    </script>
</body>
</html>
