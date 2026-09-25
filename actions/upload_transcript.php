<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('../includes/auth_check.php');
// Ensure only students can upload transcripts
checkRole(['student']); 

require_once('../config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['transcript'])) {
    // u.username contains the actual student registration number / student ID (e.g., '1010001')
    $student_id = $_SESSION['user_id'] ?? ''; 
    
    if (empty($student_id)) {
        die("Error: Student session expired. Please log in again.");
    }

    // 1. Validate File Upload
    $file = $_FILES['transcript'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_error = $file['error'];
    
    if ($file_error !== 0) {
        die("Error occurred during file upload. Code: " . $file_error);
    }

    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if ($file_ext !== 'pdf') {
        die("Error: Only PDF documents are allowed.");
    }
    
    // 2. Define Upload Directory Paths
    // Using relative pathing to save to 'SPMS/uploads/transcripts/'
    $upload_dir = "../uploads/transcripts/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Build a unique safe filename using the student ID and timestamp
    $new_file_name = "Transcript_" . $student_id . "_" . time() . ".pdf";
    $dest_path = $upload_dir . $new_file_name;
    
    // 3. Clean up the old transcript file if it exists to save disk space
    $stmt = $conn->prepare("SELECT transcript_path FROM student WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $old_path = $row['transcript_path'];
        if (!empty($old_path) && file_exists($old_path)) {
            unlink($old_path); // Physically delete old PDF
        }
    }
    $stmt->close();
    
    // 4. Move the uploaded file and update database records
    if (move_uploaded_file($file_tmp, $dest_path)) {
        $conn->begin_transaction();
        
        try {
            // Update the student table with the new transcript path and reset status
            $stmt1 = $conn->prepare("UPDATE student SET transcript_path = ?, approval_status = 'Transcript Uploaded' WHERE student_id = ?");
            $stmt1->bind_param("ss", $dest_path, $student_id);
            $stmt1->execute();
            $stmt1->close();
            
            // Check if they already have a project row initialized; if so, keep Milestone 1 in sync
            $stmt2 = $conn->prepare("UPDATE project SET m1_status = 'Transcript Uploaded' WHERE student_id = ?");
            $stmt2->bind_param("s", $student_id);
            $stmt2->execute();
            $stmt2->close();
            
            $conn->commit();
            
            // Redirect back to dashboard with success message
            header("Location: ../dashboards/student.php?upload=success");
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            die("Database update failed: " . $e->getMessage());
        }
    } else {
        die("Error: Failed to save the file to the server. Please check directory permissions.");
    }
} else {
    header("Location: ../dashboards/student.php");
    exit();
}