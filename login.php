<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include('db_config.php');

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$login_error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            $hashed_password = $row["password"];
            $userRole = $row["role"];

            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $row["id"];
                $_SESSION['user_role'] = $userRole;
                header("Location: index.php");
                exit();
            } else {
                $login_error = "Invalid password.";
            }
        } else {
            $login_error = "Username not found.";
        }
        $stmt->close();
    } else {
        $login_error = "Server error. Please try again.";
    }
}

$conn->close();
$cacheBuster = 'v=' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login UPLB Graduate School IS</title>
    <link rel="stylesheet" type="text/css" href="css/style.css?<?php echo $cacheBuster; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="b1">
    <div class="page-wrapper">
        <h1 class="inventory-header">Inventory System</h1>
        <div class="login-container">
            <img src="css/GS-logo.png" alt="Your Logo" class="login-logo">
            <h2>UPLB Graduate School</h2>
            <p class="login-text">Sign in to your account</p>

            <div class="rfid-login-section" style="position:relative; margin-bottom:8px;">
                <div class="scanner-flash" id="rfidFlash" style="position:relative; top:auto; left:auto; transform:none; margin:0 auto 10px; display:flex; justify-content:center; opacity:0; width:auto; pointer-events:none;">
                    <i class="fas fa-check-circle"></i>
                    <span>RFID received — logging you in...</span>
                </div>

                <div class="scanner-ready-card" id="rfidReadyCard" style="margin:0; padding:22px 20px; gap:18px; border-radius:12px;">
                    <div class="scanner-ready-icon scanner-ready-icon-ready" id="rfidIcon" style="width:64px; height:64px; font-size:1.8rem;">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="scanner-ready-text">
                        <h3 id="rfidStatusTitle" style="font-size:1.15rem;">Ready — Scan Your RFID Card</h3>
                        <p id="rfidStatusHint" style="font-size:0.9rem;">Wave your card near the reader to sign in automatically</p>
                    </div>
                </div>

                <div class="manual-entry-container" style="margin-top:14px; padding-top:10px;">
                    <p class="manual-entry-link-wrap" style="margin:0; font-size:0.9rem;">
                        <i class="fas fa-keyboard" style="margin-right:6px;"></i>
                        <a href="#" id="toggleManualRfid">No reader? Enter RFID manually</a>
                    </p>
                    <div id="manualRfidBox" class="manual-entry-box" style="display:none; margin-top:12px; padding:16px;">
                        <label for="manualRfid" style="display:block; font-weight:600; margin-bottom:8px; font-size:0.92rem; color:#2c2c2c;">
                            <i class="fas fa-id-card" style="margin-right:6px; color:#8B0000;"></i>RFID tag number:
                        </label>
                        <div style="display:flex; gap:10px; align-items:stretch; flex-wrap:wrap;">
                            <input type="text" id="manualRfid" placeholder="Type or paste RFID number here"
                                   style="flex:1 1 200px; padding:11px 14px; border:2px solid #dee2e6; border-radius:8px; font-size:0.95rem; outline:none;"
                                   autocomplete="off">
                            <button type="button" id="manualRfidBtn" class="manual-entry-btn" style="padding:11px 18px; font-size:0.92rem;">
                                <i class="fas fa-sign-in-alt" style="margin-right:6px;"></i>Sign In
                            </button>
                        </div>
                    </div>
                </div>

                <input type="text" id="rfid-ghost" class="ghost-scanner-input"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       inputmode="text" aria-label="RFID scanner input">
            </div>

            <div class="manual-entry-container" style="margin-top:14px; padding-top:8px;">
                <p class="manual-entry-link-wrap" style="margin:0; font-size:0.9rem;">
                    <i class="fas fa-key" style="margin-right:6px;"></i>
                    <a href="#" id="togglePasswordSignin">No card? Sign in with password</a>
                </p>
                <div id="passwordSigninBox" class="manual-entry-box" style="display:<?php echo isset($login_error) ? 'block' : 'none'; ?>; margin-top:12px; padding:18px 18px 14px;">
                    <div style="border-top:1px solid #e9ecef; margin:0 -14px 14px; position:relative; overflow:visible;">
                        <span style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; padding:0 12px; font-size:0.72rem; color:#868e96; font-weight:700; letter-spacing:0.6px; white-space:nowrap;">
                            SIGN IN WITH PASSWORD
                        </span>
                    </div>

                    <form method="post" action="login.php" style="margin:0;" id="passwordSigninForm">
                        <div class="compact-signin-row">
                            <div>
                                <label for="username"><i class="fas fa-user" style="margin-right:4px; color:#8B0000;"></i>Username</label>
                                <input type="text" name="username" id="username" required autocomplete="username">
                            </div>
                            <div>
                                <label for="password"><i class="fas fa-lock" style="margin-right:4px; color:#8B0000;"></i>Password</label>
                                <input type="password" name="password" id="password" required autocomplete="current-password">
                            </div>
                            <button type="submit"><i class="fas fa-sign-in-alt" style="margin-right:5px;"></i>Sign In</button>
                        </div>
                    </form>

                    <?php
                    if (isset($login_error)) {
                        echo "<p class='error' style='margin-top:14px;'>" . htmlspecialchars($login_error) . "</p>";
                    }
                    ?>
                </div>
            </div>

            <p class="create-account-link" style="margin-top:18px; padding-top:14px;">Don't have an account? <a href="registration.php">Create an account</a></p>
            <!-- 
            <details class="debug-collapsible" style="margin-top:14px;">
                <summary style="font-size:0.78rem;">
                    <i class="fas fa-terminal" style="margin-right:5px;"></i>
                    Troubleshooting / RFID Activity Log
                    <span class="debug-collapsible-hint">(click to expand)</span>
                </summary>
                <div class="on-page-debug" style="margin-top:10px;">
                    <div class="on-page-debug-title" style="padding:8px 12px; font-size:0.8rem;">
                        RFID Engine Log
                        <button type="button" id="rfidClearDebugBtn" style="float:right; background:none; border:none; color:#adb5bd; cursor:pointer; font-size:0.72rem; padding:1px 4px;">Clear</button>
                    </div>
                    <div class="on-page-debug-log" id="rfidDebugEntries" style="max-height:150px; padding:8px 12px;">
                        <div class="debug-entry debug-info"><span class="time"><?php echo date('H:i:s'); ?></span> Page loaded — RFID capture engine initializing...</div>
                    </div>
                </div>
            </details>
            -->
        </div>
    </div>
    <div class="footer">
        <p>&copy; Joseph Patron || <?php echo date("Y"); ?> Inventory System</p>
    </div>

    <script>
    (function() {
        "use strict";

        var inputEl = document.getElementById('rfid-ghost');
        var iconEl  = document.getElementById('rfidIcon');
        var titleEl = document.getElementById('rfidStatusTitle');
        var hintEl  = document.getElementById('rfidStatusHint');
        var flashEl = document.getElementById('rfidFlash');
        var debugEl = document.getElementById('rfidDebugEntries');
        var clearBtn= document.getElementById('rfidClearDebugBtn');

        var manualBox = document.getElementById('manualRfidBox');
        var toggleManual = document.getElementById('toggleManualRfid');
        var manualInput = document.getElementById('manualRfid');
        var manualBtn = document.getElementById('manualRfidBtn');

        var passwordBox = document.getElementById('passwordSigninBox');
        var togglePassword = document.getElementById('togglePasswordSignin');

        var userField = document.getElementById('username');
        var passField = document.getElementById('password');

        var scanBuffer = '';
        var scanBufferTimer = null;
        var autoSubmitTimer = null;
        var SCAN_KEYS_TIMEOUT_MS = 150;
        var AUTO_SUBMIT_IDLE_MS = 260;
        var RFID_MIN_LEN = 4;
        var RFID_MAX_LEN = 32;

        var submitInProgress = false;

        function clearTimers() {
            if (scanBufferTimer) { clearTimeout(scanBufferTimer); scanBufferTimer = null; }
            if (autoSubmitTimer) { clearTimeout(autoSubmitTimer); autoSubmitTimer = null; }
        }

        function normalizeRfid(s) {
            s = String(s || '').trim();
            s = s.replace(/[^A-Za-z0-9]/g, '');
            if (/^[a-f0-9]+$/i.test(s)) s = s.toUpperCase();
            return s;
        }

        function isPlausibleRfid(s) {
            if (!s) return false;
            var t = normalizeRfid(s);
            if (t.length < RFID_MIN_LEN || t.length > RFID_MAX_LEN) return false;
            return /^[A-Za-z0-9]+$/.test(t);
        }

        function nowTime() {
            var d = new Date();
            function p(n){return (n<10?'0':'')+n;}
            return p(d.getHours())+':'+p(d.getMinutes())+':'+p(d.getSeconds());
        }
        function debug(msg, type) {
            type = type || 'info';
            if (!debugEl) return;
            var div = document.createElement('div');
            div.className = 'debug-entry debug-' + type;
            div.innerHTML = '<span class="time">' + nowTime() + '</span> ' + msg;
            debugEl.appendChild(div);
            try { debugEl.scrollTop = debugEl.scrollHeight; } catch(e){}
        }
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (debugEl) debugEl.innerHTML = '';
            });
        }

        function setIconState(state, extra) {
            if (!iconEl) return;
            if (state === 'processing') {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-processing';
                iconEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                titleEl.textContent = extra || 'Looking up account...';
                hintEl.textContent = 'Please wait while we verify your card';
            } else if (state === 'success') {
                iconEl.className = 'scanner-ready-icon';
                iconEl.style.background = 'linear-gradient(135deg,#28a745 0%,#20c997 100%)';
                iconEl.style.boxShadow = '0 6px 18px rgba(40,167,69,0.42)';
                iconEl.innerHTML = '<i class="fas fa-check"></i>';
                titleEl.textContent = extra || 'Welcome back!';
                hintEl.textContent = 'Signing you in...';
            } else if (state === 'error') {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-error';
                iconEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                titleEl.textContent = extra || 'Card not recognized';
                hintEl.textContent = 'Ask admin to register, or sign in with password below';
            } else {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-ready';
                iconEl.style.background = '';
                iconEl.style.boxShadow = '';
                iconEl.innerHTML = '<i class="fas fa-id-card"></i>';
                titleEl.textContent = 'Ready — Scan Your RFID Card';
                hintEl.textContent = 'Wave your card near the reader to sign in automatically';
            }
        }

        function flashReceived(msg) {
            if (!flashEl) return;
            if (msg) {
                var s = flashEl.querySelector('span');
                if (s) s.textContent = msg;
            }
            flashEl.style.opacity = '1';
            flashEl.style.transform = 'translateY(0) scale(1)';
            setTimeout(function() {
                flashEl.style.opacity = '0';
                flashEl.style.transform = 'translateY(-10px) scale(0.96)';
            }, 1300);
        }

        var focusAttempts = 0;
        function ensureFocus() {
            if (submitInProgress) return;
            if (manualInput && document.activeElement === manualInput) return;
            if (userField && document.activeElement === userField) return;
            if (passField && document.activeElement === passField) return;
            if (!inputEl) return;
            try {
                var wasFocused = document.activeElement === inputEl;
                var currentVal = inputEl.value;
                inputEl.focus({ preventScroll: true });
                try { inputEl.setSelectionRange(currentVal.length, currentVal.length); } catch(e) {}
                if (!wasFocused && document.activeElement === inputEl) {
                    focusAttempts++;
                    if (focusAttempts <= 2) {
                        debug('✅ RFID ghost input focused (call #' + focusAttempts + ')', 'success');
                    }
                }
            } catch (err) {
                debug('⚠ focus() error: ' + err.message, 'warn');
            }
        }

        var aggressiveCount = 0;
        var aggressiveFocus = setInterval(function() {
            aggressiveCount++;
            ensureFocus();
            if (aggressiveCount >= 10) clearInterval(aggressiveFocus);
        }, 260);
        setTimeout(ensureFocus, 50);
        setTimeout(ensureFocus, 220);
        setTimeout(ensureFocus, 600);
        setInterval(ensureFocus, 2800);

        function escapeHtml(t) {
            return String(t).replace(/[&<>"']/g, function(c){
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        }

        function tryParseJson(text) {
            try { return { ok: true, data: JSON.parse(text) }; }
            catch (e) { return { ok: false, error: e.message }; }
        }

        function triggerAutoSubmitIfReady(sourceLabel) {
            var candidate = scanBuffer && scanBuffer.trim() ? scanBuffer : (inputEl ? inputEl.value : '');
            candidate = normalizeRfid(candidate);
            if (submitInProgress || !candidate) return;
            if (!isPlausibleRfid(candidate)) {
                if (candidate.length > 0 && candidate.length < RFID_MIN_LEN) {
                    debug('ℹ Burst idle — too short (' + candidate.length + ' chars)', 'info');
                } else if (candidate.length > RFID_MAX_LEN) {
                    debug('ℹ Burst idle — too long (' + candidate.length + ' chars)', 'info');
                } else if (candidate.length > 0) {
                    debug('ℹ Burst idle — invalid chars for RFID: "' + escapeHtml(candidate.slice(0,30)) + '"', 'info');
                }
                scanBuffer = '';
                if (inputEl) inputEl.value = '';
                return;
            }
            clearTimers();
            scanBuffer = '';
            if (inputEl) inputEl.value = '';
            debug('🤖 AUTO-SUBMIT burst-idle via ' + sourceLabel + ' (len=' + candidate.length + '): <code>' + escapeHtml(candidate) + '</code>', 'success');
            submitRfid(candidate);
        }

        function pokeAutoSubmit(sourceLabel) {
            if (autoSubmitTimer) clearTimeout(autoSubmitTimer);
            autoSubmitTimer = setTimeout(function() {
                autoSubmitTimer = null;
                triggerAutoSubmitIfReady(sourceLabel);
            }, AUTO_SUBMIT_IDLE_MS);
        }

        function ghostInputHasContent() {
            return !!(inputEl && inputEl.value && String(inputEl.value).trim().length > 0);
        }

        function submitRfid(rawCode) {
            var code = normalizeRfid(rawCode);
            if (!code) { ensureFocus(); return; }
            if (submitInProgress) {
                debug('⚠ Submit skipped — previous request still in progress', 'warn');
                return;
            }

            submitInProgress = true;
            flashReceived('RFID received — verifying...');
            setIconState('processing', 'Verifying RFID card...');
            debug('🔍 Submitting RFID: <code>' + escapeHtml(code) + '</code>', 'info');

            jQuery.ajax({
                type: 'POST',
                url: 'rfid_lookup.php',
                data: { rfid: code },
                dataType: 'text',
                timeout: 15000
            })
            .done(function(rawText) {
                var parsed = tryParseJson(rawText);
                if (!parsed.ok) {
                    debug('❌ Invalid JSON from rfid_lookup.php. First 360 chars:', 'error');
                    debug('<pre style="margin:4px 0 0 18px; font-size:0.74rem; background:#fff5f5; padding:5px; border-radius:4px; white-space:pre-wrap; color:#721c24; max-height:100px; overflow:auto;">' + escapeHtml(String(rawText).slice(0, 360)) + '</pre>', 'error');
                    alert('❌ Server returned invalid response during RFID lookup.\n\nFirst 300 chars:\n' + String(rawText).slice(0, 300));
                    setIconState('error', 'Server response error');
                    scanBuffer = '';
                    if (inputEl) inputEl.value = '';
                    submitInProgress = false;
                    setTimeout(function(){ setIconState('ready'); }, 2400);
                    ensureFocus();
                    return;
                }
                var resp = parsed.data;
                debug('📡 Lookup: ' + escapeHtml(JSON.stringify(resp).slice(0, 220)), 'info');

                if (resp.status === 'found') {
                    var uname = resp.user && resp.user.username ? resp.user.username : 'User';
                    setIconState('success', 'Welcome back, ' + uname + '!');
                    debug('✅ Account found — <strong>' + escapeHtml(uname) + '</strong> (' + escapeHtml(resp.user.role || 'user') + '). Requesting session...', 'success');
                    setTimeout(function() {
                        jQuery.ajax({
                            type: 'POST',
                            url: 'rfid_login.php',
                            data: { rfid: code },
                            dataType: 'text',
                            timeout: 15000
                        })
                        .done(function(rawText2) {
                            var p2 = tryParseJson(rawText2);
                            if (!p2.ok) {
                                debug('❌ Invalid JSON from rfid_login.php: ' + escapeHtml(String(rawText2).slice(0, 320)), 'error');
                                alert('❌ Invalid server response during RFID sign-in:\n' + String(rawText2).slice(0, 320));
                                submitInProgress = false;
                                setIconState('error', 'Sign-in error');
                                setTimeout(function(){ setIconState('ready'); }, 1800);
                                ensureFocus();
                                return;
                            }
                            var r2 = p2.data;
                            if (r2.status === 'ok') {
                                debug('🚀 Session created — redirecting to <code>' + escapeHtml(r2.redirect || 'index.php') + '</code>', 'success');
                                flashReceived('Signed in! Redirecting...');
                                setTimeout(function() {
                                    window.location.href = r2.redirect || 'index.php';
                                }, 400);
                            } else if (r2.status === 'not_found') {
                                debug('⚠ rfid_login returned not_found', 'warn');
                                setIconState('error', 'Card no longer registered');
                                submitInProgress = false;
                                setTimeout(function(){ setIconState('ready'); }, 2200);
                                ensureFocus();
                            } else {
                                var msg = r2.message || 'An error occurred during sign-in.';
                                debug('⚠ rfid_login error: ' + escapeHtml(msg), 'warn');
                                alert('⚠ ' + msg);
                                submitInProgress = false;
                                setIconState('ready');
                                ensureFocus();
                            }
                        })
                        .fail(function(xhr, status, err) {
                            debug('❌ rfid_login AJAX FAIL status=' + status + ', err=' + err + (xhr && xhr.status ? ', HTTP ' + xhr.status : ''), 'error');
                            if (xhr && xhr.responseText) {
                                debug('Response snippet:<pre style="margin:4px 0 0 18px; font-size:0.74rem; background:#fff5f5; padding:5px; border-radius:4px; white-space:pre-wrap; color:#721c24; max-height:100px; overflow:auto;">' + escapeHtml(String(xhr.responseText).slice(0, 500)) + '</pre>', 'error');
                            }
                            alert('⚠ Network / server error during RFID sign-in.\n\nHTTP: ' + (xhr && xhr.status ? xhr.status : 'unknown') + '\nStatus: ' + status);
                            submitInProgress = false;
                            setIconState('ready');
                            ensureFocus();
                        });
                    }, 550);
                } else if (resp.status === 'not_found') {
                    debug('❌ RFID NOT FOUND. Normalized: <code>' + escapeHtml(resp.debug_rfid_normalized || code) + '</code>', 'error');
                    setIconState('error', 'Card not registered');
                    scanBuffer = '';
                    if (inputEl) inputEl.value = '';
                    submitInProgress = false;
                    setTimeout(function(){ setIconState('ready'); }, 2800);
                    ensureFocus();
                } else {
                    var msg = resp.message || 'An error occurred.';
                    debug('⚠ rfid_lookup error: ' + escapeHtml(msg), 'warn');
                    if (msg && msg.indexOf('Too many') === 0) {
                        setIconState('error', 'Rate limited — slow down');
                    } else {
                        alert('⚠ ' + msg);
                    }
                    scanBuffer = '';
                    if (inputEl) inputEl.value = '';
                    submitInProgress = false;
                    setIconState('ready');
                    ensureFocus();
                }
            })
            .fail(function(xhr, status, err) {
                debug('❌ rfid_lookup AJAX FAIL status=' + status + ', err=' + err + (xhr && xhr.status ? ', HTTP ' + xhr.status : ''), 'error');
                if (xhr && xhr.responseText) {
                    debug('Response snippet:<pre style="margin:4px 0 0 18px; font-size:0.74rem; background:#fff5f5; padding:5px; border-radius:4px; white-space:pre-wrap; color:#721c24; max-height:100px; overflow:auto;">' + escapeHtml(String(xhr.responseText).slice(0, 500)) + '</pre>', 'error');
                }
                alert('⚠ Network / server error during RFID lookup.\n\nHTTP: ' + (xhr && xhr.status ? xhr.status : 'unknown') + '\nStatus: ' + status);
                scanBuffer = '';
                if (inputEl) inputEl.value = '';
                submitInProgress = false;
                setIconState('ready');
                ensureFocus();
            });
        }

        if (inputEl) {
            inputEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13 || e.which === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    var v = inputEl.value;
                    inputEl.value = '';
                    clearTimers();
                    scanBuffer = '';
                    debug('⌨️ ENTER caught via GHOST INPUT ("' + escapeHtml(String(v).slice(0, 40)) + '")', 'success');
                    submitRfid(v);
                    return false;
                }
                if (e.key === 'Tab') {
                    var tv = normalizeRfid(inputEl.value || '');
                    if (tv.length >= RFID_MIN_LEN) {
                        e.preventDefault();
                        e.stopPropagation();
                        inputEl.value = '';
                        clearTimers();
                        scanBuffer = '';
                        debug('⌨️ TAB caught via GHOST INPUT (len=' + tv.length + ')', 'success');
                        submitRfid(tv);
                        return false;
                    }
                }
            }, true);
            inputEl.addEventListener('input', function() {
                var len = inputEl.value.length;
                if (len === 1) {
                    debug('✅ 1st char in ghost input: "' + escapeHtml(inputEl.value) + '"', 'success');
                }
                if (len >= 80) inputEl.value = inputEl.value.slice(-80);
                if (len > 0) {
                    scanBuffer = inputEl.value;
                    pokeAutoSubmit('GHOST_INPUT');
                }
            });
            inputEl.addEventListener('blur', function() {
                if (ghostInputHasContent()) {
                    setTimeout(function() { triggerAutoSubmitIfReady('GHOST_BLUR'); }, 320);
                }
                setTimeout(ensureFocus, 200);
            });
            inputEl.addEventListener('focusout', function() {
                if (ghostInputHasContent()) {
                    setTimeout(function() { triggerAutoSubmitIfReady('GHOST_FOCUSOUT'); }, 260);
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
                if (el.id === 'rfid-ghost') return false;
                return true;
            }
            if (el.isContentEditable) return true;
            return false;
        }

        document.addEventListener('keydown', function(e) {
            if (isTypingField(e.target)) {
                if (e.target.id === 'manualRfid' && (e.key === 'Enter' || e.keyCode === 13)) {
                    e.preventDefault();
                    manualBtn.click();
                }
                return;
            }

            if (e.key === 'Escape') return;

            if (e.key === 'Enter' || e.keyCode === 13 || e.key === 'Tab') {
                if (scanBuffer.length > 0) {
                    e.preventDefault();
                    e.stopPropagation();
                    var code = scanBuffer;
                    clearTimers();
                    scanBuffer = '';
                    if (inputEl) inputEl.value = '';
                    var suffix = (e.key === 'Tab') ? 'TAB' : 'ENTER';
                    debug('⌨️ ' + suffix + ' caught via GLOBAL BUFFER (len=' + code.length + '): <code>' + escapeHtml(String(code).slice(0,40)) + '</code>', 'success');
                    submitRfid(code);
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
            pokeAutoSubmit('GLOBAL_BUFFER');

            scanBufferTimer = setTimeout(function() {
                if (scanBuffer.length > 0) {
                    var snap = scanBuffer;
                    var snapN = normalizeRfid(snap);
                    var status = isPlausibleRfid(snap) ? 'VALID-looking' : 'unrecognized';
                    debug('ℹ Buffer idle-cleared (no Enter/Tab), len=' + snap.length + ', <strong>' + status + '</strong>: <code>' + escapeHtml(snapN.slice(0,50)) + '</code>', 'warn');
                    if (isPlausibleRfid(snap)) {
                        debug('💡 Reader NOT sending Enter/Tab suffix. Burst-idle (260ms) should have auto-triggered. If not, enable CR suffix in reader settings.', 'warn');
                    }
                }
                scanBuffer = '';
                scanBufferTimer = null;
            }, 1200);
        }, true);

        document.addEventListener('click', function(e) {
            if (submitInProgress) return;
            var t = e.target;
            if (!t) return;
            if (t.closest && t.closest('.debug-collapsible')) return;
            if (t.closest && (t.closest('a') || t.closest('button'))) {
                if (t.id === 'toggleManualRfid' || t.id === 'manualRfidBtn' || t.id === 'rfidClearDebugBtn' || t.id === 'togglePasswordSignin') return;
                if (t.closest('#passwordSigninForm, #manualRfidBox')) return;
            }
            setTimeout(ensureFocus, 50);
        });

        if (toggleManual && manualBox) {
            toggleManual.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (passwordBox && passwordBox.style.display && passwordBox.style.display !== 'none') {
                    passwordBox.style.display = 'none';
                }
                if (manualBox.style.display === 'none') {
                    manualBox.style.display = 'block';
                    debug('📝 Manual RFID entry panel opened', 'info');
                    setTimeout(function() { try { manualInput.focus(); } catch(err){} }, 160);
                } else {
                    manualBox.style.display = 'none';
                    debug('📝 Manual RFID entry panel closed', 'info');
                    ensureFocus();
                }
            });
        }
        if (togglePassword && passwordBox) {
            if (passwordBox.style.display && passwordBox.style.display !== 'none') {
                setTimeout(function() {
                    if (userField && !userField.value) try { userField.focus(); } catch(err){}
                    else if (passField) try { passField.focus(); } catch(err){}
                }, 180);
            }
            togglePassword.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (manualBox && manualBox.style.display && manualBox.style.display !== 'none') {
                    manualBox.style.display = 'none';
                }
                if (passwordBox.style.display === 'none') {
                    passwordBox.style.display = 'block';
                    debug('🔑 Password sign-in panel opened', 'info');
                    setTimeout(function() {
                        if (userField && !userField.value) try { userField.focus(); } catch(err){}
                        else if (passField) try { passField.focus(); } catch(err){}
                    }, 160);
                } else {
                    passwordBox.style.display = 'none';
                    debug('🔑 Password sign-in panel closed', 'info');
                    ensureFocus();
                }
            });
        }
        if (manualBtn) {
            manualBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var v = manualInput.value.trim();
                if (!v) {
                    try { manualInput.focus(); } catch(err){}
                    debug('⚠ Manual submit skipped — empty RFID field', 'warn');
                    return;
                }
                manualInput.value = '';
                manualBox.style.display = 'none';
                submitRfid(v);
            });
        }

        debug('🚀 Dual RFID capture engines online — <strong>ghost input</strong> + <strong>global buffer</strong>. Ready! 👆', 'success');
    })();
    </script>
    <noscript>
        <div style="background:#fff3cd; color:#856404; padding:12px; border:2px solid #ffc107; border-radius:8px; margin:12px 0; font-weight:600; font-size:0.85rem;">
            <i class="fas fa-exclamation-triangle"></i> JavaScript is required for RFID scanner. If disabled, use username/password above.
        </div>
    </noscript>
</body>
</html>