<?php
session_start();
session_unset(); // Unset all session variables

// Expire the session cookie in the browser too, not just the server-side session data,
// so no stale cookie/cache can make a later request look still logged in.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy(); // Destroy the session

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Location: login.php"); // Redirect to the login page
exit;
?>