<?php
/**
 * STUDENT PROJECT MANAGEMENT SYSTEM (SPMS) - DEFENSE EVALUATION CONTROLLER
 * Description: Processes, calculates, and records panel evaluation metrics 
 * for undergraduate senior projects. Supports dynamic transitions
 * between initial grading (INSERT) and grade revision (UPDATE) modes.
 */

// ==========================================
// STEP 0: DEBUGGING & RUNTIME CONFIGURATION
// ==========================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==========================================
// STEP 1: AUTHENTICATION & ACCESS CONTROL
// ==========================================
require_once('../includes/auth_check.php');
// Allow panel members and supervisors along with coordinators/HODs
checkRole(['coordinator', 'hod', 'supervisor']);

require_once('../config/db_connect.php');
include('../includes/header.php');

// ==========================================
// STEP 2: STATE ARCHITECTURE & IDENTIFIERS
// ==========================================
$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
$examiner_id = $_SESSION['user_id'] ?? 0; 

// ==========================================
// STEP 3: DATA RETRIEVAL (PROJECT & STUDENT)
// ==========================================
$stmt = $conn->prepare("SELECT p.*, s.full_name, s.student_id 
                        FROM project p 
                        JOIN student s ON p.student_id = s.student_id 
                        WHERE p.project_id = ?");
$stmt->bind_param("i", $pid);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) {
    die("<div class='container' style='padding:20px; color:red;'><h3>Error: Project record not found.</h3></div>");
}

$student_id = intval($project['student_id'] ?? 0);
$user_role = $_SESSION['role'] ?? 'coordinator';

// Determine dynamic return page based on role and student context
if ($user_role === 'supervisor') {
    $redirect_page = "view_student.php?id=" . $student_id;
} elseif ($user_role === 'hod') {
    $redirect_page = "hod.php";
} else {
    $redirect_page = "coordinator.php";
}

// ==========================================
// 3-MEMBER PANEL VALIDATION CHECK
// ==========================================
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_user_role = $_SESSION['role'] ?? '';

// Get project's assigned supervisor
$sup_check = $conn->prepare("SELECT supervisor_id FROM project WHERE project_id = ?");
$sup_check->bind_param("i", $pid);
$sup_check->execute();
$sup_res = $sup_check->get_result()->fetch_assoc();
$assigned_supervisor_id = intval($sup_res['supervisor_id'] ?? 0);

// Verify if the current user is legally part of the 3-member panel (Supervisor, HOD, or Coordinator)
$is_authorized_panelist = false;

if ($current_user_id === $assigned_supervisor_id) {
    $is_authorized_panelist = true; // They are the project supervisor
} elseif ($current_user_role === 'hod' || $current_user_role === 'coordinator') {
    $is_authorized_panelist = true; // They are HOD or Coordinator
}

if (!$is_authorized_panelist) {
    die("<div class='container' style='padding:20px; color:red;'><h3>Access Denied: You are not assigned as part of the 3-member defense panel (Supervisor, HOD, or Coordinator) for this project.</h3></div>");
}

// ==========================================
// STEP 3B: CHECK CLEARANCE & SCHEDULING STATUS
// ==========================================
$is_scheduled = (strtolower(trim($project['m7_status'] ?? '')) === 'scheduled' || strtolower(trim($project['m7_status'] ?? '')) === 'completed');
$is_cleared = (strtolower(trim($project['clearance_status'] ?? '')) === 'cleared');

// Ensure both coordinator scheduling and supervisor clearance are met
$can_evaluate = $is_cleared && ($is_scheduled || $current_user_role === 'coordinator' || $current_user_role === 'hod');

// ==========================================
// STEP 4: EVALUATION STATE DETERMINATION
// ==========================================
$check_stmt = $conn->prepare("SELECT * FROM panel_evaluation WHERE project_id = ? AND examiner_id = ?");
$check_stmt->bind_param("ii", $pid, $examiner_id);
$check_stmt->execute();
$existing_eval = $check_stmt->get_result()->fetch_assoc();

$is_update_mode = $existing_eval ? true : false;

// ==========================================
// STEP 5: TRANSACTION PROCESSING ENGINE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $can_evaluate) {
    
    // Standardized scores matching panel metrics (Total: 100)
    $s1 = intval($_POST['presentation'] ?? 0);        // Max: 15
    $s2 = intval($_POST['documentation'] ?? 0);       // Max: 20
    $s3 = intval($_POST['references'] ?? 0);          // Max: 10
    $s4 = intval($_POST['logic'] ?? 0);               // Max: 15
    $s5 = intval($_POST['code_understanding'] ?? 0);  // Max: 20
    $s6 = intval($_POST['security'] ?? 0);            // Max: 10
    $s7 = intval($_POST['reports'] ?? 0);             // Max: 10
    
    $comments = trim($_POST['comments'] ?? '');
    
    // Calculate total score for this specific examiner
    $total = $s1 + $s2 + $s3 + $s4 + $s5 + $s6 + $s7;

    if ($is_update_mode) {
        // STEP 5A: UPDATE EXISTING EXAMINER SCORE
        $eval_query = "UPDATE panel_evaluation SET 
                        presentation_score = ?, documentation_score = ?, references_score = ?, 
                        logic_score = ?, code_understanding_score = ?, security_score = ?, 
                        reports_score = ?, comments = ?, total_panel_mark = ? 
                       WHERE project_id = ? AND examiner_id = ?";
        
        $stmt_eval = $conn->prepare($eval_query);
        if (!$stmt_eval) {
            die("<div class='container' style='color:red;'>SQL Prepare Error (Update Phase): " . $conn->error . "</div>");
        }
        
        $stmt_eval->bind_param("iiiiiiisiii", $s1, $s2, $s3, $s4, $s5, $s6, $s7, $comments, $total, $pid, $examiner_id);
    } else {
        // STEP 5B: INSERT FRESH EXAMINER SCORE
        $eval_query = "INSERT INTO panel_evaluation 
            (project_id, examiner_id, presentation_score, documentation_score, references_score, logic_score, code_understanding_score, security_score, reports_score, comments, total_panel_mark) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_eval = $conn->prepare($eval_query);
        if (!$stmt_eval) {
            die("<div class='container' style='color:red;'>SQL Prepare Error (Insert Phase): " . $conn->error . "</div>");
        }
        
        $stmt_eval->bind_param("iiiiiiiiisi", $pid, $examiner_id, $s1, $s2, $s3, $s4, $s5, $s6, $s7, $comments, $total);
    }
    
    // ==========================================
    // STEP 6: EXECUTION & OVERALL AVERAGE SYNCHRONIZATION
    // ==========================================
    if ($stmt_eval->execute()) {
        
        // Calculate the overall AVERAGE panel score across all evaluators (HOD, Coordinator, Panel)
        $avg_stmt = $conn->prepare("SELECT ROUND(AVG(total_panel_mark), 2) as avg_mark FROM panel_evaluation WHERE project_id = ?");
        $avg_stmt->bind_param("i", $pid);
        $avg_stmt->execute();
        $avg_result = $avg_stmt->get_result()->fetch_assoc();
        $average_panel_mark = $avg_result['avg_mark'] ?? $total;

        // Synchronize project final indicators with the calculated AVERAGE mark
        $update_query = "UPDATE project SET final_presentation = 'Yes', final_grade = ?, m7_status = 'Completed' WHERE project_id = ?";
        $stmt_project = $conn->prepare($update_query);
        if ($stmt_project) {
            $stmt_project->bind_param("di", $average_panel_mark, $pid);
            $stmt_project->execute();
        }
        
        $msg = $is_update_mode ? "Evaluation Changes Resubmitted! Your Score: " . $total . "% (Panel Average: " . $average_panel_mark . "%)" 
                              : "Evaluation Submitted! Your Score: " . $total . "% (Panel Average: " . $average_panel_mark . "%)";
        
        // Append status parameter cleanly whether URL already has query parameters or not
        $separator = (strpos($redirect_page, '?') !== false) ? '&' : '?';
        
        echo "<script>
                alert('" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "'); 
                window.location.href='" . $redirect_page . $separator . "status=evaluated';
              </script>";
        exit();
    } else {
        echo "<div class='container' style='color:red; padding:20px;'>Transaction Execution Error: " . htmlspecialchars($stmt_eval->error) . "</div>";
    }
}
?>

<div class="container" style="padding: 20px; max-width: 800px; margin: auto; font-family: 'Segoe UI', sans-serif;">
    
    <!-- Top Action Navigation Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <a href="<?php echo $redirect_page; ?>" style="text-decoration: none; background: #7f8c8d; color: white; padding: 8px 14px; border-radius: 4px; font-weight: bold; font-size: 14px; display: inline-flex; align-items: center; gap: 5px;">
            &larr; Return to Dashboard / Control Panel
        </a>
    </div>

    <?php if (!$can_evaluate): ?>
        <div style="background: #fdf2e9; border-left: 5px solid #e67e22; padding: 20px; border-radius: 6px; margin-bottom: 20px; color: #a04000;">
            🔒 <strong>Defense Evaluation Locked:</strong> 
            <?php if (!$is_cleared): ?>
                This student has not received supervisor clearance yet.
            <?php else: ?>
                This student defense has not been officially scheduled by the coordinator yet.
            <?php endif; ?>
            Evaluation forms remain disabled until both clearance and scheduling criteria are met.
            <br><br>
            <a href="<?php echo $redirect_page; ?>" style="color: #d35400; font-weight: bold; text-decoration: underline;">&larr; Return to Page</a>
        </div>
    <?php else: ?>

        <?php if ($is_update_mode): ?>
            <div style="background: #e3f2fd; border-left: 5px solid #2196f3; padding: 15px; border-radius: 6px; margin-bottom: 20px; color: #0d47a1;">
                <strong>Review Mode:</strong> You previously recorded an evaluation score of <strong><?php echo htmlspecialchars($existing_eval['total_panel_mark']); ?>%</strong>. You may revise your metrics below and resubmit.
            </div>
        <?php endif; ?>

        <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
            <h2 style="color: #2c3e50; border-bottom: 2px solid <?php echo $is_update_mode ? '#2196f3' : '#27ae60'; ?>; padding-bottom: 10px; margin-top: 0;">
                <?php echo $is_update_mode ? 'Modify Defense Evaluation:' : 'Final Defense Evaluation:'; ?> <?php echo htmlspecialchars($project['full_name']); ?>
            </h2>
            <p style="margin-top: 10px; color:#555;"><strong>Project Title:</strong> <?php echo htmlspecialchars($project['project_title']); ?></p>

            <form method="POST" style="margin-top: 25px;">
                <div style="display: grid; grid-template-columns: 1fr 120px; gap: 20px; align-items: center;">
                    
                    <label style="font-weight: 600; color:#34495e;">1. Presentation & Delivery (0-15)</label>
                    <input type="number" name="presentation" min="0" max="15" required style="padding: 10px; border-radius: 4px; border:1px solid #ccc; text-align: center;"
                           value="<?php echo $is_update_mode ? htmlspecialchars($existing_eval['presentation_score']) : ''; ?>">

                    <label style="font-weight: 600; color:#34495e;">2. Documentation Quality (0-20)</label>
                    <input type="number" name="documentation" min="0" max="20" required style="padding: 10px; border-radius: 4px; border:1px solid #ccc; text-align: center;"
                           value="<?php echo $is_update_mode ? htmlspecialchars($existing_eval['documentation_score']) : ''; ?>">

                    <label style="font-weight: 600; color:#34495e;">3. Citations & Literature Review (0-10)</label>
                    <input type="number" name="references" min="0" max="10" required style="padding: 10px; border-radius: 4px; border:1px solid #ccc; text-align: center;"
                           value="<?php echo $is_update_mode ? htmlspecialchars($existing_eval['references_score']) : ''; ?>">

                    <label style="font-weight: 600; color:#34495e;">4. System Logic & Architecture (0-15)</label>
                    <input type="number" name="logic" min="0" max="15" required style="padding: 10px; border-radius: 4px; border:1px solid #ccc; text-align: center;"
                           value="<?php echo $is_update_mode ? htmlspecialchars($existing_eval['logic_score']) : ''; ?>">

                    <label style="font-weight: 600; color:#34495e;">5. Code Understanding (0-20)</label>
                    <input type="number" name="code_understanding" min="0" max="20" required style="padding: 10px; border-radius: 4px; border:1px solid #ccc; text-align: center;"
                           value="<?php echo $is_update_mode ? htmlspecialchars($existing_eval['code_understanding_score']) : ''; ?>">

                    <label style="font-weight: 600; color:#34495e;">6. Security Implementation (0-10)</label>
                    <input type="number" name="security" min="0" max="10" required style="padding: 10px; border-radius: 4px; border:1px solid #ccc; text-align: center;"
                           value="<?php echo $is_update_mode ? htmlspecialchars($existing_eval['security_score']) : ''; ?>">

                    <label style="font-weight: 600; color:#34495e;">7. System Reports & Analytics (0-10)</label>
                    <input type="number" name="reports" min="0" max="10" required style="padding: 10px; border-radius: 4px; border:1px solid #ccc; text-align: center;"
                           value="<?php echo $is_update_mode ? htmlspecialchars($existing_eval['reports_score']) : ''; ?>">
                </div>

                <div style="margin-top: 25px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 8px; color:#34495e;">Examiner Comments</label>
                    <textarea name="comments" rows="4" placeholder="Optional comments regarding system architectural logic or presentation feedback..." style="width: 100%; padding: 12px; border-radius: 6px; border: 1px solid #ccc; font-family:inherit; box-sizing: border-box;"><?php 
                        if ($is_update_mode && isset($existing_eval['comments']) && trim($existing_eval['comments']) !== '0') {
                            echo htmlspecialchars($existing_eval['comments'], ENT_QUOTES, 'UTF-8');
                        }
                    ?></textarea>
                </div>

                <div style="margin-top: 25px; display:flex; gap:15px;">
                    <a href="<?php echo $redirect_page; ?>" style="width: 30%; text-align:center; padding: 15px; background: #95a5a6; color: white; text-decoration:none; border-radius: 8px; font-weight: bold; font-size: 1.05em; display: inline-block;">
                        CANCEL
                    </a>
                    <button type="submit" style="width: 70%; padding: 15px; background: <?php echo $is_update_mode ? '#2196f3' : '#27ae60'; ?>; color: white; border: none; border-radius: 8px; font-weight: bold; font-size: 1.05em; cursor: pointer;">
                        <?php echo $is_update_mode ? 'RESUBMIT GRADE CHANGES' : 'SUBMIT FINAL EVALUATION'; ?>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include('../includes/footer.php'); ?>