<?php
// LINE 1: Imports authentication layer
require_once('../includes/auth_check.php');

// LINE 2: Connects to database
require_once('../config/db_connect.php');

// LINE 3: Restricts access to supervisors
checkRole(['supervisor']);

// LINE 4: Session user ID
$supervisor_id = (int)$_SESSION['user_id'];

// LINE 5: Feedback tracking string
$message = "";

// LINE 6: Validate student ID parameter
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: supervisor.php");
    exit();
}

$student_id = (int)$_GET['id'];

// =========================================================================
// ACTION HANDLER 1: APPROVE PROJECT TITLE (M1)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_specific_title'])) {
    
    $chosen_title = trim($_POST['chosen_title'] ?? '');
    $comment = trim($_POST['supervisor_comment'] ?? '');

    mysqli_begin_transaction($conn);
    
    try {
        $update_query = "UPDATE project SET project_title = ?, m1_status = 'Approved', supervisor_comment = ? 
                         WHERE student_id = ? AND supervisor_id = ?";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssii", $chosen_title, $comment, $student_id, $supervisor_id);
        $stmt->execute();

        $update_student = "UPDATE student SET approval_status = 'Approved' WHERE student_id = ?";
        $stmt_stud = $conn->prepare($update_student);
        $stmt_stud->bind_param("i", $student_id);
        $stmt_stud->execute();

        mysqli_commit($conn);
        
        $message = "<div style='color: #27ae60; background: #e8f5e9; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>✅ Title Approved: " . htmlspecialchars($chosen_title) . "</div>";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "<div style='color: #e74c3c; background: #fdeaea; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>❌ Sync Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// =========================================================================
// ACTION HANDLER 2: APPROVE/REJECT MILESTONES (M2-M7)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_milestone_status'])) {
    
    $m_num = intval($_POST['m_number'] ?? 0);
    $new_status = trim($_POST['status_choice'] ?? '');
    $m_comment = trim($_POST['m_comment'] ?? '');

    // Strict Whitelist Validation for Dynamic Column Names
    if ($m_num >= 2 && $m_num <= 7 && in_array($new_status, ['Approved', 'Rejected'])) {
        $col = "m" . $m_num . "_status";
        $comm_col = "m" . $m_num . "_comment";
        
        $update_m = "UPDATE project SET {$col} = ?, {$comm_col} = ? WHERE student_id = ? AND supervisor_id = ?";
        
        $stmt = $conn->prepare($update_m);
        $stmt->bind_param("ssii", $new_status, $m_comment, $student_id, $supervisor_id);
        
        if ($stmt->execute()) {
            $message = "<div style='color: #2980b9; background: #ebf5fb; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>ℹ️ Milestone $m_num updated to " . htmlspecialchars($new_status) . " with feedback logged.</div>";
        }
    }
}

// =========================================================================
// ACTION HANDLER 3: FINAL GRADE & CLEARANCE
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_final_evaluation'])) {
    
    $grade = intval($_POST['final_grade'] ?? 0); 
    $clearance = trim($_POST['clearance'] ?? 'Not Cleared');
    
    $update_f = "UPDATE project SET supervisor_grade = ?, clearance_status = ? WHERE student_id = ? AND supervisor_id = ?";
    
    $stmt = $conn->prepare($update_f);
    $stmt->bind_param("isii", $grade, $clearance, $student_id, $supervisor_id);
    
    if ($stmt->execute()) {
        $message = "<div style='color: #8e44ad; background: #f4ecf7; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>🎓 Supervisor Evaluation Recorded successfully.</div>";
    }
}

// =========================================================================
// FRESH DATA FETCHING (Runs AFTER POST actions update the DB)
// =========================================================================
$query = "SELECT s.full_name, s.title_1, s.title_2, s.title_3, u.username as reg_no, p.* FROM student s 
          JOIN user u ON s.student_id = u.user_id 
          JOIN project p ON s.student_id = p.student_id
          WHERE s.student_id = ? AND p.supervisor_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $student_id, $supervisor_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) { 
    include('../includes/header.php');
    die("<div class='container' style='padding:40px;'><h3>Student profile not found or not assigned to you.</h3></div>"); 
}

include('../includes/header.php');
?>

<div class="container" style="padding: 20px; max-width: 1000px; margin: auto; font-family: 'Segoe UI', sans-serif;">
    <a href="supervisor.php" style="text-decoration: none; color: #3498db; font-weight: bold;">&larr; Back to Dashboard</a>
    
    <h1 style="margin-top: 20px; color: #2c3e50;">Supervision Dashboard: <?php echo htmlspecialchars($data['full_name']); ?></h1>
    <hr style="border: 0; border-top: 1px solid #eee; margin-bottom: 20px;">
    
    <?php echo $message; ?>

    <!-- Summary Cards -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #3498db;">
            <h3 style="margin: 0; margin-bottom: 10px; color: #2c3e50;">Student Information</h3>
            <p style="margin: 5px 0;"><strong>Reg No:</strong> <?php echo htmlspecialchars($data['reg_no']); ?></p>
            <p style="margin: 5px 0;"><strong>Approved Title:</strong> <br>
                <span style="color: #27ae60; font-weight: bold;">
                    <?php echo (!empty($data['project_title']) && $data['project_title'] !== 'Reviewing Proposals') ? htmlspecialchars($data['project_title']) : 'Awaiting M1 Proposal Approval'; ?>
                </span>
            </p>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #8e44ad;">
            <h3 style="margin: 0; margin-bottom: 10px; color: #2c3e50;">Supervisor Evaluation</h3>
            <p style="margin: 5px 0;"><strong>Supervisor Grade:</strong> <?php echo isset($data['supervisor_grade']) ? htmlspecialchars($data['supervisor_grade']) . "%" : '---'; ?></p>
            <p style="margin: 5px 0;"><strong>Clearance Status:</strong> <span style="font-weight: bold; color: #8e44ad;"><?php echo htmlspecialchars($data['clearance_status'] ?? 'Not Cleared'); ?></span></p>
            
            <?php if (strtolower(trim($data['clearance_status'] ?? '')) === 'cleared'): ?>
                <div style="margin-top: 15px;">
                    <a href="evaluate_defense.php?pid=<?php echo (int)$data['project_id']; ?>" 
                       style="display: block; text-align: center; background: #27ae60; color: white; padding: 10px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                        🎓 Evaluate Defense Form
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- M1: Review Proposed Titles Section -->
    <?php if(($data['m1_status'] ?? '') != 'Approved'): ?>
    <div style="background: #fff; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #e67e22;">
        <h3 style="margin-top: 0; color: #2c3e50;">M1: Review Proposed Titles</h3>
        
        <label style="font-weight: bold; display: block; margin-bottom: 5px; color: #34495e;">Supervisor Feedback / Remarks:</label>
        <textarea id="main_supervisor_comment" placeholder="Provide strategic directional feedback for the chosen implementation..." required style="width:100%; height:80px; margin-bottom:15px; padding:10px; border: 1px solid #cbd5e0; border-radius:4px; box-sizing: border-box; font-family: inherit;"><?php echo htmlspecialchars($data['supervisor_comment'] ?? ''); ?></textarea>
        
        <?php for($i=1; $i<=3; $i++): $t = $data["title_$i"] ?? null; if(!empty($t)): ?>
            <div style="background:#f9f9f9; padding:15px; margin-bottom:10px; border-radius:5px; display:flex; justify-content:space-between; align-items:center; border: 1px solid #edf2f7;">
                <p style="margin:0; padding-right: 15px;"><strong>Option <?php echo $i; ?>:</strong> <?php echo htmlspecialchars($t); ?></p>
                
                <form method="POST" onsubmit="return syncComment(this);" style="margin:0;">
                    <input type="hidden" name="supervisor_comment" class="hidden-comment-field">
                    <input type="hidden" name="chosen_title" value="<?php echo htmlspecialchars($t); ?>">
                    <button type="submit" name="approve_specific_title" style="background:#27ae60; color:white; border:none; padding:8px 15px; border-radius:4px; cursor:pointer; font-weight: bold; white-space: nowrap;">Approve This</button>
                </form>
            </div>
        <?php endif; endfor; ?>
    </div>

    <script>
    function syncComment(formElement) {
        var commentText = document.getElementById('main_supervisor_comment').value.trim();
        if (commentText === '') {
            alert('Please provide supervisor feedback/remarks before approving a title.');
            return false;
        }
        formElement.querySelector('.hidden-comment-field').value = commentText;
        return true;
    }
    </script>
    <?php endif; ?>

    <!-- Milestone Submission Tracker -->
    <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 25px;">
        <h3 style="margin-top: 0; color: #2c3e50;">Milestone Submission Tracker</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f4f6f7; text-align: left; color: #34495e;">
                    <th style="padding: 12px; border: 1px solid #ddd;">Milestone Block</th>
                    <th style="padding: 12px; border: 1px solid #ddd;">Status Flag</th>
                    <th style="padding: 12px; border: 1px solid #ddd;">File & Evaluation Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php for($i = 2; $i <= 7; $i++): 
                    $st = $data["m{$i}_status"] ?? 'Pending Submission'; 
                    $existing_comment = $data["m{$i}_comment"] ?? '';
                    
                    // Server-side physical file verification
                    $file_name = "M{$i}_{$student_id}.pdf";
                    $file_path = "../uploads/milestones/" . $file_name;
                    $file_exists = file_exists($file_path);
                    
                    // Calculate visual status flag
                    $display_status = $st;
                    if ($st === 'Pending Submission' && $file_exists) {
                        $display_status = 'Pending Review';
                    }
                ?>
                <tr>
                    <td style="padding: 12px; border: 1px solid #ddd;"><strong>Milestone <?php echo $i; ?></strong></td>
                    <td style="padding: 12px; border: 1px solid #ddd;">
                        <span style="color: <?php echo ($display_status == 'Approved') ? '#27ae60' : (($display_status == 'Pending Review') ? '#e67e22' : (($display_status == 'Rejected') ? '#e74c3c' : '#7f8c8d')); ?>; font-weight:bold;">
                            <?php echo htmlspecialchars($display_status); ?>
                        </span>
                    </td>
                    <td style="padding: 12px; border: 1px solid #ddd;">
                        <?php if($file_exists || in_array($st, ['Pending Review', 'Approved', 'Rejected'])): ?>
                            <a href="../uploads/milestones/<?php echo $file_name; ?>" target="_blank" style="color:#3498db; font-weight:bold; text-decoration: none;">📂 Open File Deliverable (PDF)</a>
                            
                            <form method="POST" style="margin-top:10px; display: flex; flex-direction: column; gap: 8px;">
                                <input type="hidden" name="m_number" value="<?php echo $i; ?>">
                                
                                <textarea name="m_comment" rows="2" placeholder="Enter evaluation feedback / reason for approval or rejection..." required style="width:100%; padding:8px; border: 1px solid #cbd5e0; border-radius: 4px; box-sizing: border-box; font-family: inherit; font-size: 13px;"><?php echo htmlspecialchars($existing_comment); ?></textarea>
                                
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <select name="status_choice" style="padding:6px; border: 1px solid #cbd5e0; border-radius: 4px;">
                                        <option value="Approved" <?php echo ($st == 'Approved') ? 'selected' : ''; ?>>Approve Milestone</option>
                                        <option value="Rejected" <?php echo ($st == 'Rejected') ? 'selected' : ''; ?>>Reject/Revise Milestone</option>
                                    </select>
                                    <button type="submit" name="update_milestone_status" style="background:#2c3e50; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight: bold;">Save Status & Feedback</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <span style="color:#b2bec3; font-style: italic;">No Submission Recorded</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <!-- Final Evaluation Form & Defense Navigation -->
    <?php if(($data['m7_status'] ?? '') == 'Approved'): ?>
    <div style="background: #2c3e50; color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <h3 style="margin-top:0; color: #f1c40f;">Graduation Evaluation & Clearance Form</h3>
        <p style="color: #b2bec3; margin-top: -10px; font-size: 14px;">Milestone 7 has been approved. Provide final mark metrics to queue the student for departmental defense.</p>
        <form method="POST">
            <div style="display: flex; gap: 20px;">
                <div style="flex:1;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">Supervisor Internal Mark Score (0 - 100%):</label>
                    <input type="number" name="final_grade" min="0" max="100" value="<?php echo htmlspecialchars($data['supervisor_grade'] ?? '0'); ?>" required style="width:100%; padding:10px; margin-top:5px; border-radius:4px; border: none;">
                </div>
                <div style="flex:1;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">Departmental Clearance:</label>
                    <select name="clearance" style="width:100%; padding:10px; margin-top:5px; border-radius:4px; border: none; height: 38px;">
                        <option value="Not Cleared" <?php echo (($data['clearance_status'] ?? '') == 'Not Cleared') ? 'selected' : ''; ?>>Not Cleared</option>
                        <option value="Cleared" <?php echo (($data['clearance_status'] ?? '') == 'Cleared') ? 'selected' : ''; ?>>Cleared (Proceed to Defense Queue)</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="submit_final_evaluation" style="width:100%; background:#f1c40f; color:#2c3e50; font-weight:bold; border:none; padding:15px; margin-top:20px; cursor:pointer; border-radius:5px; font-size: 15px;">
                COMPLETE STUDENT SUPERVISION & LOCK RECORD
            </button>
        </form>

        <!-- Defense Evaluation Action Module -->
        <?php if (strtolower(trim($data['clearance_status'] ?? '')) === 'cleared'): ?>
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #27ae60; margin-top: 20px; text-align: center;">
                <h3 style="margin: 0; margin-bottom: 10px; color: #2c3e50;">Defense Evaluation Queue</h3>
                <p style="color: #7f8c8d; font-size: 14px; margin-bottom: 15px;">
                    Student is cleared for defense. Click below to grade metrics or update existing scores.
                </p>
                <a href="evaluate_defense.php?pid=<?php echo (int)$data['project_id']; ?>" 
                   style="display: inline-block; background: #27ae60; color: white; padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.15);">
                    🎓 Evaluate Defense Performance
                </a>
            </div>
        <?php else: ?>
            <div style="background: #fdf2e9; padding: 15px; border-radius: 8px; border-left: 4px solid #e67e22; margin-top: 20px;">
                <p style="margin: 0; color: #a04000; font-size: 13.5px;">
                    🔒 <strong>Defense Evaluation Locked:</strong> Change Departmental Clearance status to <strong>Cleared</strong> above to unlock panel defense grading.
                </p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include('../includes/footer.php'); ?>