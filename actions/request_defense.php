<?php
// Step 1: Start the session
session_start(); 
// Sessions allow you to track user activity across pages if needed.

// Step 2: Connect to the database
require_once('../config/db_connect.php'); 
// Includes the database connection file and provides the $conn object.

// Step 3: Check if the "push to panel" button was clicked
if (isset($_POST['push_to_panel'])) {
    // Collect the project ID from the form
    $pid = intval($_POST['project_id']); 
    // intval() ensures the project_id is treated as an integer for safety.

    // Step 4: Redirect the student to their dashboard with a message
    // The header() function sends an HTTP redirect.
    // "../dashboards/student.php?msg=DefenseRequested" means:
    // - Go to the student dashboard page
    // - Pass a query string parameter msg=DefenseRequested
    //   (this can be used in student.php to show a notification like "Defense requested")
    header("Location: ../dashboards/student.php?msg=DefenseRequested");
    exit(); // Stop script execution after redirect
}
