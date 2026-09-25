<?php
require_once('../includes/auth_check.php');
require_once('../config/db_connect.php');

// STEP 1: Role check
checkRole(['student']);
include('../includes/header.php');

$uid = $_SESSION['user_id'];

// STEP 2: Capture milestone number from URL
$m = isset($_GET['m']) ? intval($_GET['m']) : 1; 

// STEP 3: Security boundary
if ($m < 1 || $m > 7) { 
    die("<div class='container' style='padding:20px; color:#c0392b;'><h3>❌ Invalid Milestone.</h3><a href='student.php'>Back to Dashboard</a></div>"); 
}

$message = "";

// AUTOMATED SCHEMA GUARD: Verify/inject required fields if missing
$check_cols = $conn->query("SHOW COLUMNS FROM project LIKE 'title_1'");
if ($check_cols && $check_cols->num_rows == 0) {
    $conn->query("ALTER TABLE project ADD COLUMN title_1 VARCHAR(255) DEFAULT NULL AFTER student_id");
    $conn->query("ALTER TABLE project ADD COLUMN title_2 VARCHAR(255) DEFAULT NULL AFTER title_1");
    $conn->query("ALTER TABLE project ADD COLUMN title_3 VARCHAR(255) DEFAULT NULL AFTER title_2");
    $conn->query("ALTER TABLE project ADD COLUMN m1_status VARCHAR(50) DEFAULT 'Pending' AFTER title_3");
}

// STEP 4: Fetch baseline student info and real-time project metrics
$status_check = $conn->prepare("
    SELECT s.approval_status, p.supervisor_id, p.title_1, p.title_2, p.title_3, p.m1_status 
    FROM student s 
    LEFT JOIN project p ON s.student_id = p.student_id 
    WHERE s.student_id = ?
");
$status_check->bind_param("i", $uid);
$status_check->execute();
$res = $status_check->get_result()->fetch_assoc();

$current_status = $res['approval_status'] ?? 'Registered';
$supervisor_id  = $res['supervisor_id'] ?? null;
$t1             = $res['title_1'] ?? '';
$t2             = $res['title_2'] ?? '';
$t3             = $res['title_3'] ?? '';
$m1_status      = $res['m1_status'] ?? 'Pending';
$status_check->close();

// HYBRID ACCESS CHECKER: Unlocked if status is explicitly approved OR if a supervisor has been allocated 
$is_unlocked = (!in_array(strtolower(trim($current_status)), ['registered', 'transcript uploaded']) || !empty($supervisor_id));

// STEP 5: Handle form submissions
if (isset($_POST['upload_m_btn'])) {
    if ($m == 1) {
        // --- MILESTONE 1: 3-Title Proposal Submission Routine ---
        if (!$is_unlocked) {
            $message = "<div style='color:red; background:#ffebee; padding:15px; border-radius:5px;'>❌ Error: You cannot submit titles until your transcript is approved and a supervisor is allocated.</div>";
        } else {
            $title1 = trim($_POST['title_1'] ?? '');
            $title2 = trim($_POST['title_2'] ?? '');
            $title3 = trim($_POST['title_3'] ?? '');

            if (empty($title1) || empty($title2) || empty($title3)) {
                $message = "<div style='color:red; background:#ffebee; padding:15px; border-radius:5px;'>❌ Error: All three project title options are mandatory.</div>";
            } else {
                mysqli_begin_transaction($conn);
                try {
                    $check_proj = $conn->prepare("SELECT student_id FROM project WHERE student_id = ?");
                    $check_proj->bind_param("i", $uid);
                    $check_proj->execute();
                    $exists = $check_proj->get_result()->num_rows > 0;
                    $check_proj->close();

                    if (!$exists) {
                        $stmt = $conn->prepare("INSERT INTO project (student_id, title_1, title_2, title_3, m1_status) VALUES (?, ?, ?, ?, 'Pending Review')");
                        $stmt->bind_param("isss", $uid, $title1, $title2, $title3);
                    } else {
                        $stmt = $conn->prepare("UPDATE project SET title_1 = ?, title_2 = ?, title_3 = ?, m1_status = 'Pending Review' WHERE student_id = ?");
                        $stmt->bind_param("sssi", $title1, $title2, $title3, $uid);
                    }
                    $stmt->execute();
                    $stmt->close();

                    $update_student = $conn->prepare("UPDATE student SET approval_status = 'Titles Submitted' WHERE student_id = ?");
                    $update_student->bind_param("i", $uid);
                    $update_student->execute();
                    $update_student->close();

                    mysqli_commit($conn);
                    
                    $current_status = 'Titles Submitted';
                    $m1_status = 'Pending Review';
                    $t1 = $title1; $t2 = $title2; $t3 = $title3;
                    
                    $message = "<div style='color:green; background:#e8f5e9; padding:15px; border-radius:5px;'>✅ All 3 title proposals submitted successfully! Awaiting review.</div>";
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $message = "<div style='color:red; background:#ffebee; padding:15px; border-radius:5px;'>❌ Database error: Could not process title updates.</div>";
                }
            }
        }
    } else {
        // --- MILESTONES 2–7: Standard PDF document upload routine ---
        $target_dir = "../uploads/milestones/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $file_ext = strtolower(pathinfo($_FILES["m_file"]["name"], PATHINFO_EXTENSION));
        
        if ($file_ext !== 'pdf') {
            $message = "<div style='color:red; background:#ffebee; padding:15px; border-radius:5px;'>❌ Error: Only PDF files are allowed.</div>";
        } else {
            $file_name = "M" . $m . "_" . $uid . ".pdf";
            $target_file = $target_dir . $file_name;

            if (move_uploaded_file($_FILES["m_file"]["tmp_name"], $target_file)) {
                $col_status = "m" . $m . "_status";
                
                $allowed_columns = ['m2_status', 'm3_status', 'm4_status', 'm5_status', 'm6_status', 'm7_status'];
                if (in_array($col_status, $allowed_columns)) {
                    $query = "UPDATE project SET $col_status = 'Pending Review' WHERE student_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $uid);
                    
                    if ($stmt->execute()) {
                        $message = "<div style='color:green; background:#e8f5e9; padding:15px; border-radius:5px;'>✅ Milestone $m report uploaded successfully.</div>";
                    }
                    $stmt->close();
                }
            } else {
                $message = "<div style='color:red; background:#ffebee; padding:15px; border-radius:5px;'>❌ Failed to process upload file to directory.</div>";
            }
        }
    }
}
?>

<div class="container" style="max-width: 650px; margin: 40px auto; padding: 25px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); font-family: 'Segoe UI', sans-serif;">
    
    <?php echo $message; ?>

    <?php if ($m == 1): ?>
        
        <?php if (!$is_unlocked): ?>
            <!-- LOCKED STATE -->
            <div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 6px; border: 1px solid #f5c6cb; text-align: center;">
                <span style="font-size: 20px; display: block; margin-bottom: 10px;">🔒 Proposals Locked</span>
                <p style="margin: 0; font-size: 14px;">Your academic transcript verification must be approved by the department coordinator. Once verified and a supervisor is assigned, this panel will accept your inputs.</p>
            </div>

        <?php else: ?>
            <!-- SUCCESS NOTICE HEADER -->
            <div style="background: #e8f5e9; padding: 15px; border-radius: 6px; border: 1px solid #c3e6cb; margin-bottom: 20px; text-align: center;">
                <span style="color: #2e7d32; font-weight: bold; display: block; font-size: 15px;">✔ Transcript Verified & Faculty Allocated!</span>
                <span style="color: #388e3c; font-size: 13px; font-weight: 600; display: inline-block; margin-top: 4px;">Proceed to Step 2: Submit 3 Titles &rarr;</span>
            </div>

            <!-- UNLOCKED STATE: Show the updated 3-Title Form -->
            <h3 style="color: #2c3e50; margin-top: 0; margin-bottom: 5px;">Project Title Proposals</h3>
            <p style="color: #7f8c8d; font-size: 14px; margin-bottom: 20px;">Please submit three distinct project titles for your department head to choose from.</p>

            <form method="POST">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 6px; color: #34495e; font-size: 14px;">Proposed Title 1 (Preferred Choice):</label>
                    <input type="text" name="title_1" value="<?php echo htmlspecialchars($t1); ?>" required placeholder="Enter primary title option" style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 6px; color: #34495e; font-size: 14px;">Proposed Title 2:</label>
                    <input type="text" name="title_2" value="<?php echo htmlspecialchars($t2); ?>" required placeholder="Enter alternative title option" style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box;">
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 6px; color: #34495e; font-size: 14px;">Proposed Title 3:</label>
                    <input type="text" name="title_3" value="<?php echo htmlspecialchars($t3); ?>" required placeholder="Enter alternative title option" style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box;">
                </div>

                <?php if ($m1_status !== 'Approved'): ?>
                    <button type="submit" name="upload_m_btn" style="width: 100%; padding: 14px; background: #3498db; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 15px;">
                        Submit Titles for Departmental Review
                    </button>
                <?php endif; ?>
            </form>

            <?php if ($m1_status === 'Pending Review' || strtolower($m1_status) === 'pending review'): ?>
                <div style="margin-top: 25px; background: #fff3cd; padding: 15px; border-radius: 6px; border: 1px solid #ffeeba; text-align: center;">
                    <span style="color: #856404; font-weight: bold; display: block; margin-bottom: 5px;">⏳ Sent to Department Head</span>
                    <p style="margin: 5px 0; font-size: 13px; color: #666;">Your three title options are logged. Your supervisor and department head are checking your entries to select the optimal track.</p>
                </div>
            <?php elseif ($m1_status === 'Approved' || strtolower($m1_status) === 'approved'): ?>
                <div style="margin-top: 25px; background: #e8f5e9; padding: 15px; border-radius: 6px; text-align: center; border: 1px solid #c3e6cb;">
                    <span style="color: #2e7d32; font-weight: bold; display: block; margin-bottom: 5px;">✔ Title Selection Finalized!</span>
                    <p style="margin: 5px 0 12px 0; font-size: 13px; color: #2e7d32;">Your official capstone project title has been chosen and locked in by the department.</p>
                    <a href="student.php" style="background: #27ae60; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: bold; display: inline-block; font-size: 14px;">
                        Return to Dashboard &rarr;
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <?php else: ?>
        <!-- Milestones 2-7: Standard PDF Report Upload Interface -->
        <h2 style="color: #2c3e50; margin-top: 0;">Submit Milestone <?php echo $m; ?></h2>
        <p style="color: #7f8c8d; font-size: 14px;">Upload your compiled documentation file for analysis by your assigned supervisor.</p>
        <hr style="border: 0; border-top: 1px solid #eee; margin-bottom: 20px;">

        <form method="POST" enctype="multipart/form-data">
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: bold; margin-bottom: 10px; color: #34495e;">Select PDF Report Document:</label>
                <input type="file" name="m_file" accept=".pdf" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box;">
            </div>
            <button type="submit" name="upload_m_btn" style="width: 100%; padding: 12px; background: #3498db; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 15px;">
                Upload Milestone <?php echo $m; ?>
            </button>
        </form>
    <?php endif; ?>
    
    <br>
    <a href="student.php" style="color: #3498db; text-decoration: none; font-weight: bold; display: inline-block; margin-top: 15px;">&larr; Back to Dashboard</a>
</div>

<?php include('../includes/footer.php'); ?>

