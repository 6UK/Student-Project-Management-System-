<?php
include('../includes/auth_check.php');
// 1. Role Check: Allow active panel roles (removing supervisor if needed configure panel)
checkRole(['supervisor', 'hod', 'coordinator']); 
require_once('../config/db_connect.php');

if (isset($_POST['submit_eval'])) {

    // --- Collect Identifiers ---
    $pid         = intval($_POST['project_id']); 
    $examiner_id = $_SESSION['user_id']; 
    
    // --- 1. Gather Individual Scores ---
    $p_score   = intval($_POST['presentation']);
    $d_score   = intval($_POST['documentation']);
    $r_score   = intval($_POST['references']);
    $l_score   = intval($_POST['logic']);
    $c_score   = intval($_POST['code_understanding']);
    $s_score   = intval($_POST['security']);
    $rep_score = intval($_POST['reports']);
    
    // Prepared statements handle escaping safely; trim removes extra whitespace
    $comments  = trim($_POST['comments']); 
    
    // Sum for THIS specific evaluator (out of 100)
    $evaluator_total = $p_score + $d_score + $r_score + $l_score + $c_score + $s_score + $rep_score;

    // --- Begin Database Transaction ---
    mysqli_begin_transaction($conn);

    try {
        // --- 2. Insert or Update Examiner Score ---
        // ON DUPLICATE KEY UPDATE allows an evaluator to edit their existing mark safely
        $sql = "INSERT INTO evaluation (
                    project_id, examiner_id, presentation_score, documentation_score, 
                    references_score, logic_score, code_understanding_score, 
                    security_score, reports_score, commentstext, total_panel_mark
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    presentation_score = VALUES(presentation_score),
                    documentation_score = VALUES(documentation_score),
                    references_score = VALUES(references_score),
                    logic_score = VALUES(logic_score),
                    code_understanding_score = VALUES(code_understanding_score),
                    security_score = VALUES(security_score),
                    reports_score = VALUES(reports_score),
                    commentstext = VALUES(commentstext),
                    total_panel_mark = VALUES(total_panel_mark)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiiiiissi", 
            $pid, $examiner_id, $p_score, $d_score, 
            $r_score, $l_score, $c_score, 
            $s_score, $rep_score, $comments, $evaluator_total
        );
        $stmt->execute();

        // --- 3. Compute Dynamic Panel Defense Average ---
        // Works seamlessly whether 1, 2, or 3 evaluators have submitted
        $avg_query = "SELECT AVG(total_panel_mark) AS avg_defense_score 
                      FROM evaluation 
                      WHERE project_id = ?";
        $stmt_avg = $conn->prepare($avg_query);
        $stmt_avg->bind_param("i", $pid);
        $stmt_avg->execute();
        $avg_res = $stmt_avg->get_result()->fetch_assoc();
        
        $raw_panel_avg = $avg_res['avg_defense_score'] ? floatval($avg_res['avg_defense_score']) : 0;
        
        // Scale panel defense average to 60%
        $panel_score_60 = ($raw_panel_avg / 100) * 60;

        // --- 4. Fetch Supervisor Continuous Assessment Mark (40%) ---
        $sup_query = "SELECT supervisor_score_40 FROM project WHERE project_id = ?";
        $stmt_sup = $conn->prepare($sup_query);
        $stmt_sup->bind_param("i", $pid);
        $stmt_sup->execute();
        $sup_res = $stmt_sup->get_result()->fetch_assoc();
        
        $supervisor_score_40 = $sup_res['supervisor_score_40'] ? floatval($sup_res['supervisor_score_40']) : 0;

        // --- 5. Calculate Final Grade ---
        $final_grade = round($supervisor_score_40 + $panel_score_60, 2);

        // --- 6. Update Project Table ---
        $update = $conn->prepare("UPDATE project 
                                   SET panel_score_60 = ?, 
                                       final_grade = ?, 
                                       m7_status = 'Approved' 
                                   WHERE project_id = ?");
        $update->bind_param("ddi", $panel_score_60, $final_grade, $pid);
        $update->execute();

        // Commit all changes
        mysqli_commit($conn);
        
        // --- 7. Redirect to Dashboard ---
        $redirect_dashboard = ($_SESSION['role'] === 'supervisor') ? 'supervisor.php' : 'coordinator.php';
        header("Location: ../dashboards/" . $redirect_dashboard . "?success=evaluated&pid=" . $pid . "&final=" . $final_grade);
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        die("Error processing evaluation: " . $e->getMessage());
    }
} else {
    header("Location: ../dashboards/coordinator.php");
    exit();
}
?>