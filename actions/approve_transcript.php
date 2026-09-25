<?php
// Step 1: Include authentication and authorization
require_once('../includes/auth_check.php');
// Ensures only logged-in users with the role "coordinator" or "hod" can run this script.
checkRole(['coordinator', 'hod']);
// Step 2: Connect to the database
require_once('../config/db_connect.php');
// Loads the database connection file and gives us the $conn object.

// Step 3: Check if required GET parameters are present
if (isset($_GET['pid']) && isset($_GET['sid'])) {
    $pid = intval($_GET['pid']);
    $sid = intval($_GET['sid']);
    // pid = project_id, sid = student_id
    // Step 4: Begin a transaction
    mysqli_begin_transaction($conn);
    // Step 5: Update project milestone status
    try {
        // --- TRY BLOCK ---
        // The code inside "try" is executed normally.
        // If any error occurs here, PHP will throw an Exception
        // and control will jump to the "catch" block.
        // Advance the project state milestone so it drops out of Step 1
        $stmt1 = $conn->prepare("UPDATE project SET m1_status = 'Transcript Approved' WHERE project_id = ?");
        $stmt1->bind_param("i", $pid); // Bind project_id as integer
        $stmt1->execute();             // Execute query
        $stmt1->close();               // Close statement

        // Keep the student table profile status synchronized 
        $stmt2 = $conn->prepare("UPDATE student SET approval_status = 'Transcript Approved' WHERE student_id = ?");
        $stmt2->bind_param("i", $sid);   // Bind student_id as integer
        $stmt2->execute();               // Execute query
        $stmt2->close();                  // Close statement
        // Step 7: Commit transaction if both updates succeed
        mysqli_commit($conn);
    } catch (Exception $e) {
        // --- CATCH BLOCK ---
        // If any error happens inside the "try" block,
        // this section will run instead.
        // Here we rollback the transaction to undo all changes,
        // ensuring the database stays consistent.
        // Step 8: Rollback transaction if any error occurs
        mysqli_rollback($conn);
    }
}

// Redirect back to the coordinator dashboard instantly
// Step 9: Redirect back to coordinator dashboard
header("Location: ../views/coordinator.php");
exit;
?>