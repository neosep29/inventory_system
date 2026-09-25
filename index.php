<?php
include('db_config.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userRole = $_SESSION['user_role'];

if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$sql = "SELECT role FROM users WHERE id = $userId";
$result = $conn->query($sql);
if ($result && $result->num_rows == 1) {
    $row = $result->fetch_assoc();
    $userRole = $row['role'];
}

$cacheBuster = 'v=' . date('YmdHis');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory System</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="css/style.css?<?php echo $cacheBuster; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <?php include('nav.php'); ?>

    <div class="page-wrapper">
        <h1 class="inventory-header">Inventory System</h1>
        <div class="index-container">
            <div class="box-container">
                <div class="button-container">
                    <h2>Withdraw an Item</h2>
                    <p class="subtitle" style="margin-bottom:6px;">Just scan the barcode below — the quantity popup will open automatically</p>
                </div>

                <div class="scanner-ready-card" id="scannerReadyCard">
                    <div class="scanner-ready-icon scanner-ready-icon-ready" id="scannerIcon">
                        <i class="fas fa-barcode"></i>
                    </div>
                    <div class="scanner-ready-text">
                        <h3 id="scannerStatusTitle">Ready to Scan</h3>
                        <p id="scannerStatusHint">Scan any item barcode with your scanner to begin withdrawal</p>
                    </div>
                </div>

                <div class="scanner-flash" id="scannerFlash">
                    <i class="fas fa-check-circle"></i>
                    <span>Barcode received — looking up...</span>
                </div>

                <div class="manual-entry-container">
                    <p class="manual-entry-link-wrap">
                        <i class="fas fa-keyboard" style="margin-right:6px;"></i>
                        <a href="#" id="toggleManualEntry">No scanner? Enter barcode manually</a>
                    </p>
                    <div id="manualEntryBox" class="manual-entry-box" style="display:none;">
                        <label for="manualBarcode" style="display:block; font-weight:600; margin-bottom:8px; color:#2c2c2c;">
                            <i class="fas fa-barcode" style="margin-right:6px; color:#8B0000;"></i>Barcode number:
                        </label>
                        <div style="display:flex; gap:10px; align-items:stretch; flex-wrap:wrap;">
                            <input type="text" id="manualBarcode" placeholder="Type or paste barcode here"
                                   style="flex:1 1 220px; padding:11px 14px; border:2px solid #dee2e6; border-radius:8px; font-size:0.95rem; outline:none;"
                                   autocomplete="off">
                            <button type="button" id="manualSubmitBtn" class="manual-entry-btn">
                                <i class="fas fa-search" style="margin-right:6px;"></i>Look Up Item
                            </button>
                        </div>
                    </div>
                </div>
                <!--
                <details class="debug-collapsible" style="margin-top:18px;">
                    <summary>
                        <i class="fas fa-terminal" style="margin-right:6px;"></i>
                        Troubleshooting / Activity Log
                        <span class="debug-collapsible-hint">(click to expand)</span>
                    </summary>
                    <div class="on-page-debug" style="margin-top:12px;">
                        <div class="on-page-debug-title">
                            Activity Log
                            <button type="button" id="clearDebugBtn" style="float:right; background:none; border:none; color:#adb5bd; cursor:pointer; font-size:0.78rem; padding:2px 4px;">Clear</button>
                        </div>
                        <div class="on-page-debug-log" id="debugLogEntries">
                            <div class="debug-entry debug-info"><span class="time"><?php echo date('H:i:s'); ?></span> Page loaded — scanner engine initializing...</div>
                        </div>
                    </div>
                </details>
                -->
                <input type="text" id="scanned-barcode" class="ghost-scanner-input"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       inputmode="numeric" aria-label="Barcode scanner input">
            </div>
        </div>
    </div>

    <div id="quantityModalOverlay" class="modal-overlay"></div>
    <div id="quantityModal" class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-box-open" style="color:#8B0000; margin-right:8px;"></i>Withdraw Item</h3>
        </div>
        <div class="modal-body">
            <p id="modalItemName" style="font-size:1.05rem; font-weight:600; color:#2c2c2c; margin-bottom:6px;"></p>
            <p id="modalItemStock" style="color:#6c757d; margin-bottom:18px;"></p>
            <label for="withdrawQuantity" style="display:block; font-weight:600; margin-bottom:8px;">
                <i class="fas fa-hashtag" style="margin-right:6px; color:#8B0000;"></i>Quantity to Withdraw:
            </label>
            <input type="number" id="withdrawQuantity" min="1" value="1"
                   style="width:100%; padding:12px 14px; font-size:1rem; border:2px solid #dee2e6; border-radius:8px; outline:none; transition:all 0.2s;">
            <p id="modalError" class="modal-error" style="display:none; margin-top:12px;"></p>
        </div>
        <div class="modal-footer">
            <button type="button" id="cancelWithdraw" class="btn-secondary"
                    style="background:#6c757d; padding:11px 22px; border:none; border-radius:6px; color:#fff; font-weight:600; cursor:pointer; font-size:0.95rem; transition:all 0.2s;">
                <i class="fas fa-times" style="margin-right:6px;"></i>Cancel
            </button>
            <button type="button" id="confirmWithdraw"
                    style="background:#28a745; padding:11px 22px; border:none; border-radius:6px; color:#fff; font-weight:600; cursor:pointer; font-size:0.95rem; transition:all 0.2s;">
                <i class="fas fa-check" style="margin-right:6px;"></i>Confirm Withdrawal
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

        var currentItem = null;
        var modalOpen = false;
        var submitInProgress = false;

        var inputEl = document.getElementById('scanned-barcode');
        var iconEl  = document.getElementById('scannerIcon');
        var titleEl = document.getElementById('scannerStatusTitle');
        var hintEl  = document.getElementById('scannerStatusHint');
        var flashEl = document.getElementById('scannerFlash');
        var debugEl = document.getElementById('debugLogEntries');
        var clearBtn= document.getElementById('clearDebugBtn');

        var manualBox = document.getElementById('manualEntryBox');
        var toggleManual = document.getElementById('toggleManualEntry');
        var manualInput = document.getElementById('manualBarcode');
        var manualBtn = document.getElementById('manualSubmitBtn');

        var scanBuffer = '';
        var scanBufferTimer = null;
        var autoSubmitTimer = null;
        var SCAN_KEYS_TIMEOUT_MS = 150;
        var AUTO_SUBMIT_IDLE_MS = 260;
        var BARCODE_MIN_LEN = 4;
        var BARCODE_MAX_LEN = 40;

        function clearTimers() {
            if (scanBufferTimer) { clearTimeout(scanBufferTimer); scanBufferTimer = null; }
            if (autoSubmitTimer) { clearTimeout(autoSubmitTimer); autoSubmitTimer = null; }
        }

        function isPlausibleBarcode(s) {
            if (!s) return false;
            var trimmed = String(s).trim();
            if (trimmed.length < BARCODE_MIN_LEN || trimmed.length > BARCODE_MAX_LEN) return false;
            return /^[A-Za-z0-9\-\_\.\+\s]+$/.test(trimmed);
        }

        function triggerAutoSubmitIfReady(sourceLabel) {
            var candidate = scanBuffer && scanBuffer.trim() ? scanBuffer : (inputEl ? inputEl.value : '');
            candidate = String(candidate || '').trim();
            if (submitInProgress || modalOpen || !candidate) return;
            if (!isPlausibleBarcode(candidate)) {
                if (candidate.length > 0 && candidate.length < BARCODE_MIN_LEN) {
                    debug('ℹ Burst idle — too short for barcode (' + candidate.length + ' chars, min ' + BARCODE_MIN_LEN + '): "' + escapeHtml(candidate.slice(0,30)) + '"', 'info');
                } else if (candidate.length > BARCODE_MAX_LEN) {
                    debug('ℹ Burst idle — too long for barcode (' + candidate.length + ' chars, max ' + BARCODE_MAX_LEN + ')', 'info');
                } else if (candidate.length > 0) {
                    debug('ℹ Burst idle — chars invalid for barcode: "' + escapeHtml(candidate.slice(0,30)) + '"', 'info');
                }
                scanBuffer = '';
                if (inputEl) inputEl.value = '';
                return;
            }
            clearTimers();
            scanBuffer = '';
            if (inputEl) inputEl.value = '';
            debug('🤖 AUTO-SUBMIT on scan-burst via ' + sourceLabel + ' (no Enter detected, length=' + candidate.length + '): <code>' + escapeHtml(candidate) + '</code>', 'success');
            submitBarcode(candidate);
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

        debug('✅ jQuery loaded: v' + (window.jQuery ? jQuery.fn.jquery : 'NOT LOADED'),
              window.jQuery ? 'success' : 'error');

        function setIconState(state, extra) {
            if (!iconEl) return;
            if (state === 'processing') {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-processing';
                iconEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                titleEl.textContent = 'Looking up item...';
                hintEl.textContent = 'Please wait while we fetch stock information';
            } else if (state === 'error') {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-error';
                iconEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                titleEl.textContent = extra || 'Error';
                hintEl.textContent = 'Ready to try again — just scan another barcode';
            } else {
                iconEl.className = 'scanner-ready-icon scanner-ready-icon-ready';
                iconEl.innerHTML = '<i class="fas fa-barcode"></i>';
                titleEl.textContent = 'Ready to Scan';
                hintEl.textContent = 'Scan any item barcode with your scanner to begin withdrawal';
            }
        }

        function flashReceived() {
            if (!flashEl) return;
            flashEl.style.opacity = '1';
            flashEl.style.transform = 'translateY(0) scale(1)';
            setTimeout(function() {
                flashEl.style.opacity = '0';
                flashEl.style.transform = 'translateY(-12px) scale(0.96)';
            }, 1300);
        }

        var focusAttempts = 0;
        function ensureFocus() {
            if (modalOpen) return;
            if (manualInput && document.activeElement === manualInput) return;
            var qtyField = document.getElementById('withdrawQuantity');
            if (qtyField && document.activeElement === qtyField) return;
            if (!inputEl) return;
            try {
                var wasFocused = document.activeElement === inputEl;
                var currentVal = inputEl.value;
                inputEl.focus({ preventScroll: true });
                try { inputEl.setSelectionRange(currentVal.length, currentVal.length); } catch(e) {}
                if (!wasFocused && document.activeElement === inputEl) {
                    focusAttempts++;
                    if (focusAttempts <= 2) {
                        debug('✅ Scanner input successfully focused (call #' + focusAttempts + ')', 'success');
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
        }, 220);
        setTimeout(ensureFocus, 40);
        setTimeout(ensureFocus, 180);
        setTimeout(ensureFocus, 500);
        setInterval(ensureFocus, 2400);

        function openModal(item) {
            currentItem = item;
            modalOpen = true;
            document.getElementById('modalItemName').textContent = item.name;
            document.getElementById('modalItemStock').innerHTML =
                '<i class="fas fa-warehouse" style="margin-right:5px;"></i>Available in stock: <strong style="color:#28a745;">' + item.quantity + '</strong>';
            var qtyField = document.getElementById('withdrawQuantity');
            qtyField.setAttribute('max', item.quantity);
            qtyField.value = '1';
            document.getElementById('modalError').style.display = 'none';
            document.getElementById('quantityModalOverlay').style.display = 'block';
            document.getElementById('quantityModal').classList.add('modal-show');
            debug('📦 Quantity modal opened for: <strong>' + escapeHtml(item.name) + '</strong> (stock: ' + item.quantity + ')', 'success');
            setTimeout(function() {
                try { qtyField.focus(); qtyField.select(); } catch(e){}
            }, 340);
        }

        function closeModal() {
            currentItem = null;
            modalOpen = false;
            document.getElementById('quantityModal').classList.remove('modal-show');
            document.getElementById('quantityModalOverlay').style.display = 'none';
            scanBuffer = '';
            if (inputEl) inputEl.value = '';
            setIconState('ready');
            debug('Modal closed — scanner refocused and ready for next scan', 'info');
            ensureFocus();
        }

        function escapeHtml(t) {
            return String(t).replace(/[&<>"']/g, function(c){
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        }

        function tryParseJson(text) {
            try { return { ok: true, data: JSON.parse(text) }; }
            catch (e) { return { ok: false, error: e.message }; }
        }

        function submitBarcode(rawCode) {
            var code = String(rawCode || '').trim();
            if (!code) { ensureFocus(); return; }
            if (submitInProgress) {
                debug('⚠ Submit skipped — previous request still in progress (barcode: ' + escapeHtml(code) + ')', 'warn');
                return;
            }
            if (modalOpen) return;

            submitInProgress = true;
            flashReceived();
            setIconState('processing');
            debug('🔍 Submitting barcode: <code>' + escapeHtml(code) + '</code>', 'info');

            jQuery.ajax({
                type: 'POST',
                url: 'barcode_scanner.php',
                data: { action: 'lookup', scannedBarcode: code },
                dataType: 'text',
                timeout: 15000
            })
            .done(function(rawText) {
                var parsed = tryParseJson(rawText);
                if (!parsed.ok) {
                    debug('❌ Invalid JSON from server — response below (likely PHP warning/error):', 'error');
                    debug('<pre style="margin:4px 0 0 18px; font-size:0.76rem; background:#fff5f5; padding:6px; border-radius:4px; white-space:pre-wrap; color:#721c24; max-height:120px; overflow:auto;">' + escapeHtml(String(rawText).slice(0, 700)) + '</pre>', 'error');
                    alert('❌ Server returned invalid response.\n\nFirst 350 chars:\n' + String(rawText).slice(0, 350));
                    setIconState('error', 'Server response error');
                    scanBuffer = '';
                    if (inputEl) inputEl.value = '';
                    setTimeout(function(){ setIconState('ready'); }, 2600);
                    ensureFocus();
                    return;
                }
                var resp = parsed.data;
                debug('📡 Server response: ' + escapeHtml(JSON.stringify(resp).slice(0, 240)), 'info');

                if (resp.status === 'logout') {
                    debug('👋 Own RFID card re-scanned — logging out...', 'success');
                    setIconState('ready');
                    titleEl.textContent = 'Logging out...';
                    hintEl.textContent = 'See you next time!';
                    window.location.href = 'login.php';
                } else if (resp.status === 'found') {
                    if (parseInt(resp.item.quantity, 10) <= 0) {
                        alert('⚠ Sorry, "' + resp.item.name + '" is currently out of stock.');
                        debug('ℹ Item found but OUT OF STOCK', 'warn');
                        scanBuffer = '';
                        if (inputEl) inputEl.value = '';
                        setIconState('ready');
                        ensureFocus();
                    } else {
                        openModal(resp.item);
                    }
                } else if (resp.status === 'not_found') {
                    alert('❌ Barcode not found in the database.\nScanned: ' + code);
                    debug('❌ Barcode NOT FOUND in database', 'error');
                    setIconState('error', 'Barcode not found');
                    scanBuffer = '';
                    if (inputEl) inputEl.value = '';
                    setTimeout(function(){ setIconState('ready'); }, 2400);
                    ensureFocus();
                } else {
                    var msg = resp.message || 'An error occurred.';
                    alert('⚠ ' + msg);
                    debug('⚠ Server error: ' + msg, 'warn');
                    scanBuffer = '';
                    if (inputEl) inputEl.value = '';
                    setIconState('ready');
                    ensureFocus();
                }
            })
            .fail(function(xhr, status, err) {
                debug('❌ AJAX FAIL — status=' + status + ', err=' + err +
                      (xhr && xhr.status ? ', HTTP ' + xhr.status : ''), 'error');
                if (xhr && xhr.responseText) {
                    debug('Raw response (first 600 chars):<pre style="margin:4px 0 0 18px; font-size:0.76rem; background:#fff5f5; padding:6px; border-radius:4px; white-space:pre-wrap; color:#721c24; max-height:120px; overflow:auto;">' + escapeHtml(String(xhr.responseText).slice(0, 600)) + '</pre>', 'error');
                }
                alert('⚠ Network / server error.\n\nHTTP: ' + (xhr && xhr.status ? xhr.status : 'unknown') +
                      '\nStatus: ' + status +
                      (xhr && xhr.responseText ? '\n\nResponse: ' + String(xhr.responseText).slice(0, 250) : ''));
                scanBuffer = '';
                if (inputEl) inputEl.value = '';
                setIconState('ready');
                ensureFocus();
            })
            .always(function() {
                submitInProgress = false;
            });
        }

        /* =========================================================
           CAPTURE #1: Ghost input (always focused, hidden but real)
           ========================================================= */
        if (inputEl) {
            inputEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13 || e.which === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    var v = inputEl.value;
                    inputEl.value = '';
                    clearTimers();
                    scanBuffer = '';
                    debug('⌨️ ENTER caught via GHOST INPUT (value="' + escapeHtml(String(v).slice(0, 40)) + '")', 'success');
                    submitBarcode(v);
                    return false;
                }
                if (e.key === 'Tab') {
                    var tv = String(inputEl.value || '').trim();
                    if (tv.length >= BARCODE_MIN_LEN) {
                        e.preventDefault();
                        e.stopPropagation();
                        inputEl.value = '';
                        clearTimers();
                        scanBuffer = '';
                        debug('⌨️ TAB caught via GHOST INPUT (length=' + tv.length + '): "' + escapeHtml(tv.slice(0, 30)) + '"', 'success');
                        submitBarcode(tv);
                        return false;
                    }
                }
            }, true);
            inputEl.addEventListener('input', function() {
                var len = inputEl.value.length;
                if (len === 1) {
                    debug('✅ First character entered into ghost input: "' + escapeHtml(inputEl.value) + '"', 'success');
                }
                if (len >= 80) inputEl.value = inputEl.value.slice(-80);
                if (len > 0) {
                    scanBuffer = inputEl.value;
                    pokeAutoSubmit('GHOST_INPUT');
                }
            });
            inputEl.addEventListener('blur', function() {
                if (ghostInputHasContent()) {
                    setTimeout(function() {
                        triggerAutoSubmitIfReady('GHOST_BLUR');
                    }, 320);
                }
                setTimeout(ensureFocus, 180);
            });
            inputEl.addEventListener('focusout', function() {
                if (ghostInputHasContent()) {
                    setTimeout(function() { triggerAutoSubmitIfReady('GHOST_FOCUSOUT'); }, 260);
                }
            });
        }

        /* =========================================================
           CAPTURE #2: Global document-level buffer (indep. of focus)
           ========================================================= */
        function isTypingField(el) {
            if (!el) return false;
            var tag = (el.tagName || '').toLowerCase();
            var type = (el.type || '').toLowerCase();
            if (tag === 'textarea') return true;
            if (tag === 'select') return true;
            if (tag === 'input' && type !== 'submit' && type !== 'button' && type !== 'radio' && type !== 'checkbox') {
                if (el.id === 'scanned-barcode') return false;
                return true;
            }
            if (el.isContentEditable) return true;
            return false;
        }

        document.addEventListener('keydown', function(e) {
            if (modalOpen) {
                if (e.key === 'Escape') { e.preventDefault(); closeModal(); }
                return;
            }

            if (isTypingField(e.target)) {
                if (e.target.id === 'manualBarcode' && (e.key === 'Enter' || e.keyCode === 13)) {
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
                    debug('⌨️ ' + suffix + ' caught via GLOBAL BUFFER (length=' + code.length + '): <code>' + escapeHtml(String(code).slice(0,40)) + '</code>', 'success');
                    submitBarcode(code);
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
                    var status = isPlausibleBarcode(snap) ? 'VALID-looking barcode' : 'unrecognized input';
                    debug('ℹ Scan buffer idle cleared — no Enter/Tab, length=' + snap.length + ', <strong>' + status + '</strong>: <code>' + escapeHtml(String(snap).slice(0,50)) + '</code>', 'warn');
                    if (isPlausibleBarcode(snap)) {
                        debug('💡 TIP: Your scanner is NOT sending an Enter/Tab suffix. Auto-submit on burst-idle should have already triggered. If you still see this message, contact scanner support OR enable "CR suffix" in scanner settings.', 'warn');
                    }
                }
                scanBuffer = '';
                scanBufferTimer = null;
            }, 1200);
        }, true);

        document.addEventListener('click', function(e) {
            if (modalOpen) return;
            var t = e.target;
            if (!t) return;
            if (t.closest && t.closest('#quantityModal, .modal-overlay, .sidenav, #sidebarToggleBtn')) return;
            if (t.closest && t.closest('.debug-collapsible')) return;
            if (t.closest && (t.closest('a') || t.closest('button'))) {
                if (t.id === 'toggleManualEntry' || t.id === 'manualSubmitBtn' || t.id === 'clearDebugBtn') return;
            }
            setTimeout(ensureFocus, 50);
        });

        /* =========================================================
           Manual entry toggle
           ========================================================= */
        if (toggleManual && manualBox) {
            toggleManual.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (manualBox.style.display === 'none') {
                    manualBox.style.display = 'block';
                    debug('📝 Manual entry panel opened', 'info');
                    setTimeout(function() { try { manualInput.focus(); } catch(err){} }, 160);
                } else {
                    manualBox.style.display = 'none';
                    debug('📝 Manual entry panel closed', 'info');
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
                    debug('⚠ Manual submit skipped — empty barcode field', 'warn');
                    return;
                }
                manualInput.value = '';
                manualBox.style.display = 'none';
                submitBarcode(v);
            });
        }

        /* =========================================================
           Modal handlers
           ========================================================= */
        document.getElementById('cancelWithdraw').addEventListener('click', closeModal);
        document.getElementById('quantityModalOverlay').addEventListener('click', closeModal);

        var qtyInput = document.getElementById('withdrawQuantity');
        if (qtyInput) {
            qtyInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    document.getElementById('confirmWithdraw').click();
                }
            });
        }

        document.getElementById('confirmWithdraw').addEventListener('click', function(e) {
            e.preventDefault();
            var qty = parseInt(qtyInput.value, 10);
            var maxQty = parseInt(currentItem.quantity, 10);
            var errEl = document.getElementById('modalError');

            if (!qty || qty < 1) {
                errEl.style.display = 'block';
                errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter a valid quantity (at least 1).';
                debug('⚠ Quantity input invalid: ' + escapeHtml(String(qtyInput.value)), 'warn');
                return;
            }
            if (qty > maxQty) {
                errEl.style.display = 'block';
                errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> Only ' + maxQty + ' item(s) available in stock.';
                debug('⚠ Quantity ' + qty + ' exceeds stock ' + maxQty, 'warn');
                return;
            }
            errEl.style.display = 'none';

            var btn = e.currentTarget;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            debug('🚀 Confirming withdrawal of ' + qty + ' × ' + escapeHtml(currentItem.name), 'info');

            jQuery.ajax({
                type: 'POST',
                url: 'barcode_scanner.php',
                data: { action: 'withdraw', scannedBarcode: currentItem.barcode, quantity: qty },
                dataType: 'text',
                timeout: 15000
            })
            .done(function(rawText) {
                var parsed = tryParseJson(rawText);
                if (!parsed.ok) {
                    debug('❌ Withdraw response INVALID JSON: ' + escapeHtml(String(rawText).slice(0, 360)), 'error');
                    alert('❌ Invalid server response during withdrawal:\n' + String(rawText).slice(0, 360));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Confirm Withdrawal';
                    return;
                }
                var resp = parsed.data;
                if (resp.status === 'success') {
                    var remaining = maxQty - qty;
                    debug('✅ SUCCESS — withdrew ' + qty + ' units. Remaining stock: ' + remaining, 'success');
                    alert('✅ Successfully withdrew ' + qty + ' unit(s) of "' + currentItem.name + '".\nRemaining stock: ' + remaining);
                    closeModal();
                } else {
                    var m = resp.message || 'Failed to process withdrawal.';
                    debug('⚠ Withdraw failed: ' + m, 'warn');
                    alert('⚠ ' + m);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Confirm Withdrawal';
                }
            })
            .fail(function() {
                alert('⚠ Network error during withdrawal. Please try again.');
                debug('❌ Withdraw AJAX failed', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Confirm Withdrawal';
            });
        });

        debug('🚀 Both capture engines online — <strong>ghost input focus</strong> + <strong>global key buffer</strong>. Just scan! 👆', 'success');
    })();
    </script>
    <noscript>
        <div style="background:#fff3cd; color:#856404; padding:15px; border:2px solid #ffc107; border-radius:8px; margin:14px; font-weight:600;">
            <i class="fas fa-exclamation-triangle"></i> JavaScript is required for the barcode scanner to work. Please enable JavaScript.
        </div>
    </noscript>
</body>
</html>