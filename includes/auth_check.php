<?php
// 1. Always start the session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 2. DEFINE the function first so it is available to be called
 */
function checkRole($allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        // Stop execution if the user doesn't have the right permissions
        die("Access Denied: You do not have permission to view this page.");
    }
}

// 3. Global Authentication Check (Optional: ensures user is logged in at all)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
?>