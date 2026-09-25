<?php
include('../includes/auth_check.php');// authentication 
// Ensure only admins can make the assignment
checkRole(['coordinator', 'hod']); // authorization
require_once('../config/db_connect.php');// database connection
// This ensures the script only runs when a form is submitted via POST.
    // If someone tries to access it directly via URL, it won’t execute.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    // These variables come from the submitted form.
    // mysqli_real_escape_string prevents SQL injection by escaping special characters.
    $project_id = mysqli_real_escape_string($conn, $_POST['project_id']);
    $supervisor_id = mysqli_real_escape_string($conn, $_POST['supervisor_id']);
    // Both project_id and supervisor_id must not be empty.
    if (!empty($project_id) && !empty($supervisor_id)) {
        // Update the project table with the selected supervisor
         // This query updates the "project" table, assigning the chosen supervisor to the project.
        $query = "UPDATE project SET supervisor_id = '$supervisor_id' WHERE project_id = '$project_id'";
         //  Execute query
        if (mysqli_query($conn, $query)) {
            // Redirect back with a success message
             // If successful, redirect with a success messag
            header("Location: ../dashboards/manage_allocations.php?msg=allocated");
            exit();
        } else {
               // If query fails, show database error
            echo "Database Error: " . mysqli_error($conn);
        }
    } else {
        header("Location: ../dashboards/manage_allocations.php?msg=error");
        exit();
    }
} else {
    // Redirect if someone tries to access this script directly
    header("Location: ../dashboards/manage_allocations.php");
    exit();
}
?>