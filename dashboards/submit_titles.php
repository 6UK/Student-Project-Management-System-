<?php
require_once('../includes/auth_check.php');
require_once('../config/db_connect.php');

// STEP 1: Authorize ONLY for student role
// WHY: Prevents unauthorized users (panel, supervisor, HOD, coordinator) from submitting titles.
checkRole(['student']);

include('../includes/header.php');

$student_id = $_SESSION['user_id'];
$message = "";

// STEP 2: Fetch current status, registration number, and transcript presence
$status_query = "SELECT approval_status, transcript_path, reg_no FROM student WHERE student_id = ?";
$stmt_status = $conn->prepare($status_query);
$stmt_status->bind_param("i", $student_id);
$stmt_status->execute();
$student_status_data = $stmt_status->get_result()->fetch_assoc();

// IF: No approval_status found → default to "Registered"
// WHY: Ensures a safe fallback if the student record is incomplete.
$current_status = $student_status_data['approval_status'] ?? 'Registered';

// IF: transcript_path is not empty → student has uploaded transcript
// WHY: Used to enforce that titles can only be submitted after transcript upload.
$has_transcript = !empty($student_status_data['transcript_path']);

$current_reg_no = $student_status_data['reg_no'];

// STEP 3: Check if HOD has approved a final project title
$check_query = "SELECT project_title, m1_status FROM project WHERE student_id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$check_res = $stmt->get_result()->fetch_assoc();

// IF: Project milestone 1 status is "Approved"
// WHY: Prevents resubmission of titles once HOD has already approved.
if (isset($check_res['m1_status']) && $check_res['m1_status'] === 'Approved') {
    $message = "<div style='color: #27ae60; background: #e8f5e9; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>✅ Your project title has been officially approved: <strong>" . htmlspecialchars($check_res['project_title']) . "</strong></div>";
    $already_approved = true;
} else {
    $already_approved = false;
}

// STEP 4: Handle Title Submission
// IF: Student clicked submit button, titles are not already approved, and transcript exists.
// WHY: Enforces workflow order (transcript first, then titles).
if (isset($_POST['submit_titles_btn']) && !$already_approved && $has_transcript) {
    $t1 = mysqli_real_escape_string($conn, $_POST['title_1']);
    $t2 = mysqli_real_escape_string($conn, $_POST['title_2']);
    $t3 = mysqli_real_escape_string($conn, $_POST['title_3']);

    // IF: Registration number missing → fallback to student_id
    // WHY: Ensures reg_no is always stored.
    $reg_no_to_save = empty($current_reg_no) ? $student_id : $current_reg_no;

    mysqli_begin_transaction($conn); // Start transaction for atomic updates

    try {
        // Update student record with submitted titles and status
        $update_student = "UPDATE student SET title_1 = ?, title_2 = ?, title_3 = ?, approval_status = 'Titles Submitted', reg_no = ? WHERE student_id = ?";
        $stmt1 = $conn->prepare($update_student);
        $stmt1->bind_param("ssssi", $t1, $t2, $t3, $reg_no_to_save, $student_id);
        $stmt1->execute();

        // Push project tracker into HOD validation queue
        $check_proj = $conn->query("SELECT student_id FROM project WHERE student_id = $student_id");
        if ($check_proj->num_rows == 0) {
            // IF: No project record exists → insert new one
            $conn->query("INSERT INTO project (student_id, project_title, m1_status) VALUES ($student_id, 'Reviewing Proposals', 'Pending Review')");
        } else {
            // ELSE: Update existing project record to "Pending Review"
            $conn->query("UPDATE project SET m1_status = 'Pending Review' WHERE student_id = $student_id");
        }

        mysqli_commit($conn); // Commit transaction if all updates succeed
        $current_status = 'Titles Submitted';
        $message = "<div style='color: #27ae60; background: #e8f5e9; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>🚀 Titles submitted successfully! Sent to HOD for official approval.</div>";
    } catch (Exception $e) {
        // CATCH: Rollback if any error occurs
        // WHY: Ensures database consistency and prevents partial updates.
        mysqli_rollback($conn);
        $message = "<div style='color: #e74c3c; background: #fdeaea; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>❌ Error: " . $e->getMessage() . "</div>";
    }
}
?>


<div class="container" style="max-width: 700px; margin: 40px auto; padding: 20px; font-family: 'Segoe UI', sans-serif;">
    <h1 style="color: #2c3e50;">Project Title Proposals</h1>
    <p style="color: #7f8c8d;">Please submit three distinct project titles for your department head to choose from.</p>
    <hr style="border:0; border-top: 1px solid #eee; margin-bottom:20px;">

    <?php echo $message; ?>

    <?php if (!$has_transcript): ?>
        <div style="background: #fff3cd; color: #856404; padding: 20px; border-radius: 6px; border: 1px solid #ffeeba; text-align: center; font-weight: bold;">
            ⚠️ Access Warning: You must upload your Academic Transcript before you can submit project titles.
            <br><br>
            <a href="submit_milestone.php?m=1" style="background: #856404; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; display: inline-block;">
                Go to Transcript Upload Box &rarr;
            </a>
        </div>
    <?php elseif ($current_status === 'Transcript Uploaded'): ?>
        <div style="background: #eef2f7; color: #4a5568; padding: 30px; border-radius: 8px; border: 1px solid #cbd5e0; text-align: center;">
            <div style="font-size: 40px; margin-bottom: 10px;">⏳</div>
            <h3 style="margin-top: 0; color: #2d3748;">Titles Locked Pending Transcript Audit</h3>
            <p style="margin-bottom: 20px; font-size: 14px; color: #718096;">The coordinator must open and verify your uploaded transcript file. Your title proposal form will unlock automatically as soon as a faculty supervisor is assigned to your account profile.</p>
            <a href="student.php" style="color: #3182ce; font-weight: bold; text-decoration: none;">Return to Central Dashboard</a>
        </div>
    <?php elseif (!$already_approved): ?>
        <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;">
            <form method="POST">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #34495e;">Proposed Title 1 (Preferred Choice)</label>
                    <textarea name="title_1" placeholder="e.g., Car Wash Management System" required rows="2" style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 5px; font-family: inherit; box-sizing: border-box;"></textarea>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #34495e;">Proposed Title 2</label>
                    <textarea name="title_2" placeholder="e.g., Supermarket Management System" required rows="2" style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 5px; font-family: inherit; box-sizing: border-box;"></textarea>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #34495e;">Proposed Title 3</label>
                    <textarea name="title_3" placeholder="e.g., HP Management System" required rows="2" style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 5px; font-family: inherit; box-sizing: border-box;"></textarea>
                </div>

                <button type="submit" name="submit_titles_btn" style="width: 100%; padding: 15px; background: #27ae60; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer;">
                    Submit Titles for Departmental Review
                </button>
            </form>
        </div>
    <?php endif; ?>

    <br>
    <a href="student.php" style="color: #3498db; text-decoration: none; font-weight: bold;">&larr; Back to Dashboard</a>
</div>

<?php include('../includes/footer.php'); ?>