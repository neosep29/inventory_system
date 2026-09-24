# RFID Login Implementation Plan

## Repository Research

### Current Architecture
- **Users table** (confirmed from queries in code): columns are `id`, `username`, `password`, `role`. No `rfid_number` field exists yet.
- **Login flow** ([login.php](file:///c:/xampp/htdocs/GS-IS/inventory_system/login.php), [login_process.php](file:///c:/xampp/htdocs/GS-IS/inventory_system/login_process.php)): Accepts `username` + `password` via POST, uses `password_verify()` against bcrypt hash, sets `$_SESSION['user_id']` and `$_SESSION['user_role']`, redirects to `index.php`.
- **Registration flow** ([registration_process.php](file:///c:/xampp/htdocs/GS-IS/inventory_system/registration_process.php)): INSERTs into `users (username, password, role)` — role defaults to `"user"`.
- **Admin users list** ([users_list.php](file:///c:/xampp/htdocs/GS-IS/inventory_system/users_list.php)): Displays `id | username | role`, allows admins to change role per user.

### Proven Scanner Pattern in Use
The dashboard [index.php](file:///c:/xampp/htdocs/GS-IS/inventory_system/index.php) already implements a robust dual capture engine that works with ANY USB HID scanner (barcode OR RFID — both "type" characters as a keyboard):
1. **Ghost input** — a 3×3px invisible-but-focusable text field, aggressively refocused every ~2.4s. Bypasses browser `focus()` rejection of off-screen elements.
2. **Global key buffer** — a `document.addEventListener('keydown', ...)` capture that accumulates characters independently of focus, skipping typing fields.
3. **Burst-idle auto-submit** — after ~260ms of silence with a valid-length buffer, submits automatically. This handles scanners that do NOT send a trailing Enter/Tab.
4. **Activity log** — a collapsible on-page terminal for diagnosing scan state.

This same dual-engine pattern will be reused for the login page RFID capture.

### How USB RFID Scanners Work
Nearly all consumer/office USB RFID readers (125kHz EM4100, 13.56MHz Mifare, NFC) enumerate as **HID Keyboard devices**. When a tag is presented, they "type" the tag's unique serial number (typically 8–14 hex or decimal characters) and optionally suffix with Enter/Tab. **No driver or serial-port code is needed** — the web browser receives the exact same keystroke stream a barcode scanner produces.

> ⚠ If the user's RFID scanner is a **serial (COM-port)** model, a small local bridge app (e.g. Python + pyserial writing to a virtual HID, or Node-RED) would be required on the PC. The plan assumes the common USB HID type; a serial fallback note is included in Risks.

---

## Files and Modules

| File | Expected Change |
|---|---|
| *(new)* `supabase/migrations/0001_add_rfid_to_users.sql` | SQL script to add `rfid_number VARCHAR(32) NULL UNIQUE` column to `users` table (user will run this in phpMyAdmin manually since this project uses raw mysqli, not Supabase migrations; placed here for traceability). |
| [login.php](file:///c:/xampp/htdocs/GS-IS/inventory_system/login.php) | Rewrite to add: RFID scanner UI card (pulse animation), ghost input, dual capture engine (keyboard + burst-idle), activity log, AJAX RFID lookup that triggers a server-side auto-login POST. Keep the username/password form as a visible fallback below the RFID card. |
| *(new)* `rfid_lookup.php` | AJAX endpoint: accepts `POST { rfid: '...' }`, looks up user by `rfid_number`, returns `{ status: 'found'|'not_found', user?: {id, username, role} }` or error. Used to show "Welcome back, <name>!" before the auto-login POST fires. Also validates plausibility (length/charset). |
| *(new)* `rfid_login.php` | Server-side login handler: accepts `POST { rfid: '...' }`, verifies user by `rfid_number` (same lookup), sets `$_SESSION['user_id']` and `$_SESSION['user_role']`, returns JSON `{status:'ok', redirect:'index.php'}`. This is the ONLY place session is set (client-side cannot be trusted). |
| [users_list.php](file:///c:/xampp/htdocs/GS-IS/inventory_system/users_list.php) | Add an RFID column. For each user row, add an inline "Assign RFID" button that (a) pops a small card with a capture field using the same ghost-input engine pattern, or (b) simply adds a text input + scan-now hint. Also allow clearing an assigned RFID. Wire POST to update the column. |
| [registration.php](file:///c:/xampp/htdocs/GS-IS/inventory_system/registration.php) + [registration_process.php](file:///c:/xampp/htdocs/GS-IS/inventory_system/registration_process.php) | Add optional `rfid_number` input during signup (labeled "RFID Card Number (optional) — place cursor here then scan your card"). Validate uniqueness and save alongside user. |
| [change_password.php](file:///c:/xampp/htdocs/GS-IS/inventory_system/change_password.php) | Add a "Link / Update My RFID Card" panel so logged-in users can self-assign a tag without admin help. Uses same mini capture engine as login. |

---

## Implementation Steps (Dependency Order)

1. **Add `rfid_number` column to `users` table** (manual DB step)
   - Provide a one-click-runnable SQL snippet in the plan output:
     ```sql
     ALTER TABLE users ADD COLUMN rfid_number VARCHAR(32) NULL DEFAULT NULL,
     ADD UNIQUE KEY idx_users_rfid (rfid_number);
     ```
   - `VARCHAR(32)` accommodates EM4100 (10 dec), Mifare UID (8–14 hex), and padded formats. `UNIQUE` prevents one card being assigned to two accounts and allows fast index lookups.

2. **Create `rfid_lookup.php` (AJAX read-only probe)**
   - No session write here. Pure lookup + plausibility check.
   - Prepared statement: `SELECT id, username, role FROM users WHERE rfid_number = ? LIMIT 1`
   - Returns JSON. Rejects tags <4 chars or containing whitespace/special chars beyond `[A-Fa-f0-9]`.

3. **Create `rfid_login.php` (trusted session setter)**
   - `session_start()`, include `db_config.php`.
   - Prepared statement same as above.
   - On match: `$_SESSION['user_id'] = $row['id']; $_SESSION['user_role'] = $row['role'];` → JSON `{status:'ok', redirect:'index.php'}`.
   - On no match: JSON `{status:'not_found'}`.
   - Rate-limit per IP to 30 attempts/min using a simple in-memory array or flat file (prevents RFID tag enumeration brute force).

4. **Rewrite `login.php`**
   - Keep the existing `<form method="post" action="login.php">` block for user/pass fallback.
   - **Above** the form, insert an RFID-ready card (styled with maroon/gold gradient + pulse animation, matching the scanner card on index.php).
   - Insert the `#rfid-ghost` 3×3px ghost input at `top:64px; left:64px;`, identical CSS class `.ghost-scanner-input`.
   - Port the dual capture engine from `index.php` inline (login page has no script.js today — keep it inline to avoid file-load dependency issues on first paint).
   - On valid RFID capture (either Enter/Tab or burst-idle 260ms):
     1. Visual flash → "Looking up…" spinner on RFID card.
     2. POST to `rfid_lookup.php` → if found, show "Welcome back, <username>! Signing you in…" for ~600ms.
     3. POST to `rfid_login.php` → on `{status:'ok'}` do `window.location.href = resp.redirect`.
   - If lookup returns `not_found`, shake the icon red, show inline "RFID not registered — ask an admin to link this card, or sign in with username/password below", append tag ID to debug log.
   - Add collapsible "Activity Log / Troubleshooting" panel identical to index.php.

5. **Update `users_list.php` (admin RFID management)**
   - Add new column "RFID Card" between Role and Change Role.
   - If `rfid_number` is NULL, show `<span class="text-muted">Not assigned</span>` + a `[Assign RFID]` small button.
   - If `rfid_number` is set, show truncated `****ABCD` + `[View]`/`[Clear]` buttons.
   - Assign UI: clicking [Assign RFID] reveals an inline mini scanner card (ghost input field + status text) that captures via a mini burst-idle engine, then POSTs `{user_id, rfid_number}` to self to update the row.
   - Clear UI: POSTs `{user_id, clear_rfid: 1}` → sets column to NULL.
   - Handle uniqueness violation if admin tries to assign a card already taken by another user → inline error.

6. **Update `registration.php` + `registration_process.php`**
   - Add `rfid_number` text input below password (optional field, placeholder "Scan RFID card here or leave blank").
   - In `registration_process.php`: if `rfid_number` is provided, `trim()` it, validate length/charset, check uniqueness against existing users, then bind into the INSERT as a 4th `?` param (`sss` → `ssss`).
   - On duplicate RFID, return the error gracefully.

7. **Update `change_password.php` (self-service RFID linking)**
   - Below the password change form, add a new card "Link or Update My RFID Card" with:
     - Current status: "No card linked" or "Last 4: XXXX"
     - A capture zone (ghost input + ready card styled consistently)
     - Save/Clear buttons that POST to self with the captured tag to update the row for `$_SESSION['user_id']`.

8. **CSS touch-ups in `style.css`** (minimal)
   - The `.ghost-scanner-input`, `.scanner-ready-card*`, `.on-page-debug*` classes already exist and cover 99% of needs.
   - Add an `.rfid-login-section` wrapper max-width to match the login card.
   - Add a subtle divider between RFID section and user/pass fallback.

---

## Dependencies and Considerations

- **jQuery**: Already loaded on `index.php`, `users_list.php`, `change_password.php`. On `login.php` we add the same `<script src="https://code.jquery.com/jquery-3.6.0.min.js">` before the inline capture engine (we avoid pulling `script.js` since login page is pre-auth and that file may contain post-auth code).
- **Existing `login_process.php`**: Left untouched — `login.php`'s own top PHP block already handles the user/pass POST. Reduces change surface.
- **RFID Tag Format Normalization**: Different readers output the same tag UID in different orders (little-endian vs big-endian). In `rfid_lookup.php` and `rfid_login.php`, normalize the input by: (1) strip all non-alphanumerics, (2) uppercase hex, (3) try raw lookup first, then if not found, try the byte-reversed form too — or simply store whatever the scanner emits and document "only use this specific reader model for enrollment and login." The plan implements the simpler, safer approach: **store and match exactly what the scanner types**, plus a normalization step that strips spaces/dashes.
- **Session Security**: RFID capture triggers an AJAX call to `rfid_login.php` which is the *only* place `$_SESSION` is written. Client JS never sets auth cookies directly.
- **Rate Limiting**: RFID tags are easy to enumerate if you can scan many cards quickly. A per-IP rate limit (file-based counter keyed by `$_SERVER['REMOTE_ADDR']`, 1-minute window, cap 30 attempts) is added to both `rfid_lookup.php` and `rfid_login.php`.
- **XSS Protection**: All dynamic output in login page messages, users_list, etc. uses `htmlspecialchars()` per the existing project convention.

---

## Validation

After implementing, run the following checks in order:

1. **DB migration**: In phpMyAdmin, run the ALTER TABLE statement. Confirm `DESCRIBE users;` shows the new nullable `rfid_number` column with UNIQUE key.
2. **Assign RFID via users_list**: Login as admin, go to Users List, click Assign RFID on a test user, scan a card with the scanner, confirm the column updates and the UI shows `****ABCD`.
3. **Duplicate protection**: Try to assign the same card to a second user — expect an inline error without crashing.
4. **Self-link via change_password**: Login as the test user, go to Change Password, use the new RFID panel to scan a different card and save. Confirm DB row updated.
5. **RFID-only login on login.php**:
   - Log out, load `login.php`.
   - Confirm the green pulsing RFID card shows "Ready — scan your RFID card".
   - Scan the assigned card. Verify:
     - Flash animation fires.
     - Activity log shows capture, lookup, found user, session set, redirect.
     - Page redirects to `index.php` and the nav bar shows the correct username / role.
6. **Unregistered card**: Scan a tag not in DB. Verify red shake, inline error message, no redirect, fallback form still usable.
7. **User/pass fallback intact**: Type the test user's credentials into the form below, submit, confirm login still works.
8. **Registration + optional RFID**: Register a new user with an RFID scanned into the new field. Confirm both login methods work for the new account.
9. **Burst-idle test**: Use a scanner that does NOT send Enter suffix (or simulate by pasting quickly into the debug input). Confirm 260ms silence triggers the submit automatically.
10. **Admin clear RFID**: In users_list, clear the RFID for a user. Confirm a subsequent RFID login for that card now fails correctly.

---

## Risks

| Risk | Handling / Fallback |
|---|---|
| User's RFID reader is a **serial (COM)** device, not USB HID — no keystrokes appear. | Document this limitation first. If confirmed, recommend a small local bridge: install Python + `pyserial` on the kiosk PC, write a ~20-line script that reads the COM port and writes received bytes using `keyboard` PyPI module, emulating HID. This is out of scope for the PHP web app but the plan's JSON endpoints (rfid_lookup / rfid_login) work identically once the data arrives as keystrokes. |
| Scanner outputs variable-length garbage or is misconfigured (CR suffix off, wrong baud rate if serial). | The on-page Activity Log shows every captured keystroke + buffer state; user can diagnose visually. Burst-idle threshold (260ms) + min/max length guards prevent spurious submits. |
| Two different readers emit the same tag in two different formats (byte order). | For now, **standardize on one reader model** and document it; the normalization step (strip non-alnum, uppercase) catches most formatting deltas. If this becomes an issue later, we can add a `rfid_aliases` table or store multiple normalized variants per user. |
| Lost or cloned RFID cards. | Admin can clear the `rfid_number` column instantly from `users_list.php`. Username+password login remains a permanent fallback. If stronger auth is desired later, the architecture supports adding an RFID + short PIN flow (the lookup response can return a "pin_required" flag that pops a 4-digit modal PIN pad before calling rfid_login). |
| Brute force / card enumeration attack. | Per-IP rate limit (30/min) on both `rfid_lookup.php` and `rfid_login.php`. UNIQUE index allows fast existence checks but `rfid_number` is nullable — we don't expose "is this tag registered" publicly without the rate-limit guard. |
| `login.php` previously had no JS/jQuery dependency; adding it might break offline or strict-CSP environments. | jQuery is loaded from the same CDN already used on every other page (`cdnjs.cloudflare.com`), so if the rest of the app works, login works too. We keep the user/pass form 100% functional without JS (plain HTML POST) as an ultimate fallback. |
