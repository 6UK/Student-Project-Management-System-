<?php
// Step 1: Start the session
session_start(); 
// Sessions allow us to track the logged-in user (e.g., user_id).

// Step 2: Connect to the database
require_once('../config/db_connect.php'); 
// Provides the $conn object for database queries.

//  IF statement
// This ensures the upload logic only runs when the "submit_upload" button is clicked.
// If the form wasn’t submitted, nothing happens.
if (isset($_POST['submit_upload'])) {
    $uid = $_SESSION['user_id']; // Logged-in student ID from session
    
    // Sanitize input fields to prevent SQL injection
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $reg_no    = mysqli_real_escape_string($conn, $_POST['reg_no']);

    // --- 1. File Upload Logic ---
    $target_dir = "../uploads/"; // Directory where files will be stored
    // Create a unique filename by prefixing with current timestamp
    $file_name = time() . "_" . basename($_FILES["doc"]["name"]);
    $target_file = $target_dir . $file_name; // Full path for saving the file

    // Inner IF statement
    // move_uploaded_file() returns true if the file was successfully uploaded
    if (move_uploaded_file($_FILES["doc"]["tmp_name"], $target_file)) {
        
        // Begin transaction so both queries succeed together or fail together
        mysqli_begin_transaction($conn);
        try {
            // --- 2. Update Student Table ---
            // Insert student bio and transcript path.
            // If the student already exists, update their record instead.
            $stmt1 = $conn->prepare("INSERT INTO student (student_id, full_name, reg_no, transcript_path) 
                                    VALUES (?, ?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE full_name=?, reg_no=?, transcript_path=?");
            $stmt1->bind_param("issssss", $uid, $full_name, $reg_no, $file_name, $full_name, $reg_no, $file_name);
            $stmt1->execute();

            // --- 3. Update Project Table ---
            // Change project status to 'Pending Review' so the coordinator can see it.
            $stmt2 = $conn->prepare("UPDATE project SET m1_status = 'Pending Review' WHERE student_id = ?");
            $stmt2->bind_param("i", $uid);
            $stmt2->execute();

            // If both queries succeed, commit transaction
            mysqli_commit($conn);

            // Redirect student back to their dashboard with success message
            header("Location: ../dashboards/student.php?upload=success");
        } catch (Exception $e) {
            //  If any error occurs, rollback transaction
            mysqli_rollback($conn);
            echo "Database Error: " . $e->getMessage();
        }
    } else {
        //  Inner ELSE condition
        // If file upload fails, show error message
        echo "File upload failed.";
    }
}
// End of IF statement
?>
