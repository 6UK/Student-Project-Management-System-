<?php
// Step 1: Start the session
session_start(); 
// You must start the session before you can modify or destroy it.

// Step 2: Clear all session variables
session_unset(); 
// Removes all variables stored in the session (like user_id, role, username).
// This ensures no leftover data remains after logout.

// Step 3: Destroy the session completely
session_destroy(); 
// Ends the session itself and deletes the session file on the server.
// This makes sure the user is fully logged out.

// Step 4: Redirect back to the login page
header("Location: ../index.php"); 
// After logout, send the user back to the login page (index.php).

exit(); 
// Stop script execution immediately after redirect.
?>
