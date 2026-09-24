<?php
include('db_config.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['user_role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$admin_message = null;
$admin_error = null;

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
    if (isset($_POST['user_id']) && isset($_POST['new_role'])) {
        $user_id = (int)$_POST['user_id'];
        $new_role = ($_POST['new_role'] === 'admin') ? 'admin' : 'user';
        $sql = "UPDATE users SET role = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("si", $new_role, $user_id);
            if ($stmt->execute()) {
                $admin_message = "Role updated successfully.";
            } else {
                $admin_error = "Failed to update role: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'assign_rfid' && isset($_POST['user_id']) && isset($_POST['rfid_number'])) {
        $user_id = (int)$_POST['user_id'];
        $rawRfid = $_POST['rfid_number'];
        $norm = rfid_normalize($rawRfid);

        if ($norm === '') {
            $admin_error = "RFID number cannot be empty.";
        } elseif (!rfid_plausible($norm)) {
            $admin_error = "Invalid RFID format. Must be 4-32 alphanumeric characters.";
        } else {
            $checkSql = "SELECT id, username FROM users WHERE rfid_number = ? AND id != ? LIMIT 1";
            $checkStmt = $conn->prepare($checkSql);
            if ($checkStmt) {
                $checkStmt->bind_param('si', $norm, $user_id);
                $checkStmt->execute();
                $checkRes = $checkStmt->get_result();
                if ($checkRes && $checkRes->num_rows > 0) {
                    $other = $checkRes->fetch_assoc();
                    $admin_error = "RFID <strong>" . htmlspecialchars($norm) . "</strong> is already assigned to user <strong>" . htmlspecialchars($other['username']) . "</strong>. Please use a different card.";
                } else {
                    $upd = "UPDATE users SET rfid_number = ? WHERE id = ? LIMIT 1";
                    $updStmt = $conn->prepare($upd);
                    if ($updStmt) {
                        $updStmt->bind_param('si', $norm, $user_id);
                        if ($updStmt->execute()) {
                            $admin_message = "RFID assigned successfully. Normalized value: " . htmlspecialchars($norm);
                        } else {
                            $admin_error = "Failed to assign RFID: " . $updStmt->error;
                        }
                        $updStmt->close();
                    }
                }
                $checkStmt->close();
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'clear_rfid' && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $nullVal = null;
        $upd = "UPDATE users SET rfid_number = NULL WHERE id = ? LIMIT 1";
        $updStmt = $conn->prepare($upd);
        if ($updStmt) {
            $updStmt->bind_param('i', $user_id);
            if ($updStmt->execute()) {
                $admin_message = "RFID cleared for user #" . $user_id . ".";
            } else {
                $admin_error = "Failed to clear RFID: " . $updStmt->error;
            }
            $updStmt->close();
        }
    }
}

$sql = "SELECT id, username, role, rfid_number FROM users ORDER BY id ASC";
$result = $conn->query($sql);

$cacheBuster = 'v=' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Users List</title>
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
                <h2>Admin Dashboard - Users List</h2>
                <p class="subtitle">Manage user accounts, roles, and RFID card assignments</p>
            </div>

            <?php if ($admin_message): ?>
                <div class="success"><i class="fas fa-check-circle" style="margin-right:8px;"></i><?php echo htmlspecialchars($admin_message); ?></div>
            <?php endif; ?>
            <?php if ($admin_error): ?>
                <div class="error"><i class="fas fa-exclamation-circle" style="margin-right:8px;"></i><?php echo $admin_error; ?></div>
            <?php endif; ?>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width:48px;">#</th>
                            <th><i class="fas fa-user" style="margin-right:6px;"></i>User</th>
                            <th style="width:100px;"><i class="fas fa-user-tag" style="margin-right:6px;"></i>Role</th>
                            <th><i class="fas fa-id-card" style="margin-right:6px;"></i>RFID Card</th>
                            <th style="width:200px;"><i class="fas fa-edit" style="margin-right:6px;"></i>Change Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!$result || $result->num_rows === 0) {
                            echo "<tr><td colspan='5' style='text-align:center; padding:30px; color:#6c757d;'>No users found.</td></tr>";
                        } else {
                            while ($row = $result->fetch_assoc()) {
                                $uid = (int)$row["id"];
                                $roleBadgeClass = ($row["role"] === 'admin') ? 'style="color:#8B0000; font-weight:700;"' : 'style="color:#28a745; font-weight:600;"';
                                $selectedUser = ($row["role"] === 'user') ? 'selected' : '';
                                $selectedAdmin = ($row["role"] === 'admin') ? 'selected' : '';

                                $rfidVal = $row["rfid_number"];
                                $hasRfid = ($rfidVal !== null && trim((string)$rfidVal) !== '');
                                $rfidDisplay = '';
                                $rfidFull = '';
                                if ($hasRfid) {
                                    $rfidFull = htmlspecialchars((string)$rfidVal);
                                    $len = strlen((string)$rfidVal);
                                    if ($len <= 4) {
                                        $rfidDisplay = str_repeat('*', $len);
                                    } else {
                                        $rfidDisplay = str_repeat('*', max(4, $len - 4)) . substr((string)$rfidVal, -4);
                                    }
                                }

                                echo "<tr>";
                                echo "<td style='color:#6c757d; font-weight:600;'>" . $uid . "</td>";
                                echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                                echo "<td $roleBadgeClass>" . ucfirst(htmlspecialchars($row["role"])) . "</td>";

                                echo '<td>';
                                if ($hasRfid) {
                                    echo '<div style="display:flex; flex-direction:column; gap:6px;">';
                                    echo '<div><span class="rfid-badge-assigned" title="Full value: ' . $rfidFull . '" style="display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:999px; background:linear-gradient(135deg,#e6f7ee 0%,#d4edda 100%); color:#1e7e34; font-weight:700; font-size:0.82rem; border:1px solid #c3e6cb; cursor:help;"><i class="fas fa-id-card"></i>' . htmlspecialchars($rfidDisplay) . '</span></div>';
                                    echo '<div style="display:flex; gap:6px; flex-wrap:wrap;">';
                                    echo '<button type="button" class="rfid-view-btn" data-full="' . $rfidFull . '" style="padding:5px 10px; font-size:0.76rem; border:none; border-radius:6px; background:#e9ecef; color:#495057; cursor:pointer; font-weight:600;"><i class="fas fa-eye" style="margin-right:4px;"></i>View</button>';
                                    echo '<form method="POST" style="display:inline; margin:0;" onsubmit="return confirm(\'Are you sure you want to clear this RFID card? The user will need to sign in with a password until a new card is assigned.\');">
                                            <input type="hidden" name="user_id" value="' . $uid . '">
                                            <input type="hidden" name="action" value="clear_rfid">
                                            <button type="submit" style="padding:5px 10px; font-size:0.76rem; border:none; border-radius:6px; background:#f8d7da; color:#721c24; cursor:pointer; font-weight:600;"><i class="fas fa-times-circle" style="margin-right:4px;"></i>Clear</button>
                                          </form>';
                                    echo '</div></div>';
                                } else {
                                    echo '<div style="display:flex; flex-direction:column; gap:6px;">';
                                    echo '<span style="color:#adb5bd; font-size:0.86rem; font-weight:500;"><i class="fas fa-minus-circle" style="margin-right:4px;"></i>Not assigned</span>';
                                    echo '<button type="button" class="assign-rfid-btn" data-uid="' . $uid . '" style="padding:5px 10px; font-size:0.76rem; border:none; border-radius:6px; background:linear-gradient(135deg,#8B0000 0%,#6b0000 100%); color:#fff; cursor:pointer; font-weight:700; width:max-content; box-shadow:0 2px 6px rgba(139,0,0,0.22);"><i class="fas fa-hand-pointer" style="margin-right:4px;"></i>Assign RFID</button>';
                                    echo '</div>';
                                }
                                echo '</td>';

                                echo '<td>
                                        <form method="POST" style="display:flex; gap:8px; align-items:center; margin:0;">
                                            <input type="hidden" name="user_id" value="'.$uid.'">
                                            <select name="new_role" style="margin:0; padding:7px 10px; font-size:0.88rem;">
                                                <option value="user" '.$selectedUser.'>User</option>
                                                <option value="admin" '.$selectedAdmin.'>Admin</option>
                                            </select>
                                            <button type="submit" style="padding:7px 15px; font-size:0.88rem;">Update</button>
                                        </form>
                                    </td>';
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="rfidAssignOverlay" class="modal-overlay"></div>
    <div id="rfidAssignModal" class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-id-card" style="color:#8B0000; margin-right:8px;"></i>Assign RFID Card</h3>
        </div>
        <div class="modal-body">
            <p id="assignModalUser" style="font-weight:600; margin-bottom:14px; color:#2c2c2c;"></p>

            <div class="scanner-flash" id="assignRfidFlash" style="position:static; transform:none; margin:0 auto 12px; display:inline-flex;">
                <i class="fas fa-check-circle"></i>
                <span>RFID received — click Save below</span>
            </div>

            <div class="scanner-ready-card" id="assignRfidReady" style="margin-top:0; padding:20px 18px; gap:14px;">
                <div class="scanner-ready-icon scanner-ready-icon-ready" id="assignRfidIcon" style="width:56px; height:56px; font-size:1.6rem;">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="scanner-ready-text">
                    <h3 id="assignRfidTitle" style="font-size:1.05rem;">Ready to Scan</h3>
                    <p id="assignRfidHint" style="font-size:0.82rem;">Scan the RFID card now — the number will appear below. Or type it manually.</p>
                </div>
            </div>

            <input type="text" id="assignRfidGhost" class="ghost-scanner-input" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">

            <label for="assignRfidInput" style="margin-top:16px; display:block; font-weight:600;">
                <i class="fas fa-hashtag" style="margin-right:6px; color:#8B0000;"></i>Captured RFID number:
            </label>
            <input type="text" id="assignRfidInput" placeholder="Scan a card or paste the number here..." autocomplete="off">

            <p id="assignRfidErr" style="display:none; margin-top:8px;" class="modal-error"></p>
        </div>
        <div class="modal-footer">
            <button type="button" id="assignRfidCancel" class="btn-secondary"
                    style="background:#6c757d; padding:11px 22px; border:none; border-radius:6px; color:#fff; font-weight:600; cursor:pointer; font-size:0.95rem;">
                <i class="fas fa-times" style="margin-right:6px;"></i>Cancel
            </button>
            <button type="button" id="assignRfidSave"
                    style="background:#28a745; padding:11px 22px; border:none; border-radius:6px; color:#fff; font-weight:600; cursor:pointer; font-size:0.95rem;">
                <i class="fas fa-save" style="margin-right:6px;"></i>Save RFID
            </button>
        </div>
    </div>

    <div class="footer">
        <p>&copy; Joseph Patron || <?php echo date("Y"); ?> Inventory System</p>
    </div>
    <script src="script.js?<?php echo $cacheBuster; ?>"></script>
    <script>
    (function() {
        "use strict";

        var overlay = document.getElementById('rfidAssignOverlay');
        var modal = document.getElementById('rfidAssignModal');
        var userLbl = document.getElementById('assignModalUser');
        var iconEl = document.getElementById('assignRfidIcon');
        var titleEl = document.getElementById('assignRfidTitle');
        var hintEl = document.getElementById('assignRfidHint');
        var flashEl = document.getElementById('assignRfidFlash');
        var ghostEl = document.getElementById('assignRfidGhost');
        var inputEl = document.getElementById('assignRfidInput');
        var errEl = document.getElementById('assignRfidErr');
        var cancelBtn = document.getElementById('assignRfidCancel');
        var saveBtn = document.getElementById('assignRfidSave');

        var currentUid = null;
        var currentUsername = '';
        var modalOpen = false;

        var scanBuffer = '';
        var scanBufferTimer = null;
        var autoSubmitTimer = null;
        var RFID_MIN_LEN = 4;
        var RFID_MAX_LEN = 32;

        document.querySelectorAll('.rfid-view-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var full = btn.getAttribute('data-full') || '';
                if (!full) return;
                alert('Full RFID value for this user:\n\n' + full);
            });
        });

        document.querySelectorAll('.assign-rfid-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var uid = parseInt(btn.getAttribute('data-uid'), 10);
                if (!uid) return;
                var row = btn.closest('tr');
                var usernameCell = row ? row.querySelectorAll('td')[1] : null;
                currentUid = uid;
                currentUsername = usernameCell ? usernameCell.textContent.trim() : ('User #' + uid);
                openAssignModal();
            });
        });

        function openAssignModal() {
            modalOpen = true;
            userLbl.innerHTML = '<i class="fas fa-user" style="margin-right:6px; color:#8B0000;"></i>Assigning card to: <strong style="color:#8B0000;">' + escapeHtml(currentUsername) + '</strong>';
            overlay.style.display = 'block';
            modal.classList.add('modal-show');
            inputEl.value = '';
            errEl.style.display = 'none';
            setAssignIcon('ready');
            setTimeout(function() {
                try { ghostEl.focus({preventScroll:true}); } catch(e){}
                try { inputEl.focus(); } catch(e){}
            }, 280);
        }

        function closeAssignModal() {
            modalOpen = false;
            currentUid = null;
            currentUsername = '';
            clearTimers();
            scanBuffer = '';
            if (ghostEl) ghostEl.value = '';
            modal.classList.remove('modal-show');
            overlay.style.display = 'none';
        }

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

        function setAssignIcon(state, extra) {
            if (!iconEl) return;
            if (state === 'processing') {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-processing';
                iconEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                titleEl.textContent = 'Scanning...';
                hintEl.textContent = 'Card detected — filling input field';
            } else if (state === 'error') {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-error';
                iconEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                titleEl.textContent = extra || 'Invalid card';
                hintEl.textContent = 'Try again';
            } else if (state === 'captured') {
                iconEl.className = 'scanner-ready-icon';
                iconEl.style.background = 'linear-gradient(135deg,#28a745 0%,#20c997 100%)';
                iconEl.style.boxShadow = '0 8px 22px rgba(40,167,69,0.42)';
                iconEl.innerHTML = '<i class="fas fa-check"></i>';
                titleEl.textContent = 'RFID captured';
                hintEl.textContent = extra || 'Review the number below and click Save.';
            } else {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-ready';
                iconEl.style.background = '';
                iconEl.style.boxShadow = '';
                iconEl.innerHTML = '<i class="fas fa-id-card"></i>';
                titleEl.textContent = 'Ready to Scan';
                hintEl.textContent = 'Scan the RFID card now — the number will appear below. Or type it manually.';
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
                    setAssignIcon('captured', 'Length: ' + candidate.length);
                    flash('RFID captured');
                }
            }, 260);
        }

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
                        setAssignIcon('captured', 'Length: ' + v.length);
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

        document.addEventListener('keydown', function(e) {
            if (!modalOpen) return;
            if (e.key === 'Escape') { closeAssignModal(); return; }
            if (e.target && (e.target === inputEl)) return;
            if (e.key === 'Enter' || e.keyCode === 13 || e.key === 'Tab') {
                if (scanBuffer.length > 0) {
                    e.preventDefault();
                    var code = normalizeRfid(scanBuffer);
                    clearTimers();
                    scanBuffer = '';
                    if (ghostEl) ghostEl.value = '';
                    if (code.length >= RFID_MIN_LEN && code.length <= RFID_MAX_LEN) {
                        inputEl.value = code;
                        setAssignIcon('captured', 'Length: ' + code.length);
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
            clearTimers();
            pokeCapture();
            scanBufferTimer = setTimeout(function() { scanBuffer = ''; scanBufferTimer = null; }, 1200);
        }, true);

        cancelBtn.addEventListener('click', closeAssignModal);
        overlay.addEventListener('click', closeAssignModal);

        saveBtn.addEventListener('click', function() {
            var raw = inputEl.value || '';
            var norm = normalizeRfid(raw);
            if (!norm) {
                errEl.style.display = 'block';
                errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please scan or enter an RFID number.';
                return;
            }
            if (norm.length < RFID_MIN_LEN || norm.length > RFID_MAX_LEN) {
                errEl.style.display = 'block';
                errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> RFID must be ' + RFID_MIN_LEN + '-' + RFID_MAX_LEN + ' alphanumeric characters. Current length: ' + norm.length;
                return;
            }
            if (!/^[A-Za-z0-9]+$/.test(norm)) {
                errEl.style.display = 'block';
                errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> RFID contains invalid characters (only letters and numbers allowed).';
                return;
            }
            errEl.style.display = 'none';
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            function addField(name, val) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = name; i.value = val;
                form.appendChild(i);
            }
            addField('action', 'assign_rfid');
            addField('user_id', String(currentUid));
            addField('rfid_number', norm);
            document.body.appendChild(form);
            form.submit();
        });
    })();
    </script>
</body>
</html>
