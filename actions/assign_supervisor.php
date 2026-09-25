<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('../includes/auth_check.php');
checkRole(['coordinator', 'hod']);
require_once('../config/db_connect.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and validate project_id
    $project_id = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$project_id) {
        die("Invalid project ID provided.");
    }

    $raw_sup_id = $_POST['supervisor_id'] ?? null;

    // Check if a supervisor was chosen or if it's blank (Unassign)
    if (!empty($raw_sup_id)) {
        $supervisor_id = filter_var($raw_sup_id, FILTER_VALIDATE_INT);
        
        if (!$supervisor_id) {
            die("Invalid supervisor ID provided.");
        }

        // OPTION A: Assign or re-assign a supervisor
        $update_query = "UPDATE project SET supervisor_id = ?, m1_status = 'Supervisor Allocated' WHERE project_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ii", $supervisor_id, $project_id);

    } else {
        // OPTION B: Unassign (set supervisor_id to NULL in database)
        $update_query = "UPDATE project SET supervisor_id = NULL WHERE project_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $project_id);
    }

    if ($stmt) {
        if ($stmt->execute()) {
            $stmt->close();
            // Redirect back to the allocations dashboard
            header("Location: ../dashboards/manage_allocations.php?msg=updated");
            exit();
        } else {
            die("Execution error: " . htmlspecialchars($stmt->error));
        }
    } else {
        die("Preparation error: " . htmlspecialchars($conn->error));
    }
} else {
    // Direct GET access protection
    header("Location: ../dashboards/manage_allocations.php");
    exit();
}