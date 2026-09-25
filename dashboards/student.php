<?php
/**
 * STUDENT DASHBOARD - PMS
 * Visualizes academic status, transcript verification, score summaries, 
 * supervisor feedback, and sequential milestone progress roadmap.
 */

require_once('../includes/auth_check.php');
require_once('../config/db_connect.php');

// STEP 1: Role check - Only students can access this page.
checkRole(['student']);
include('../includes/header.php');

// STEP 2: Capture current registration number (username) from session
$reg_no = $_SESSION['user_id'] ?? ''; 

// STEP 3: Fetch project data + supervisor comment + average panel score
$query = "SELECT p.*, p.supervisor_comment, s.full_name, s.transcript_path, s.approval_status as student_approval_status, u.username as reg_no,
          AVG(pe.total_panel_mark) as avg_panel_score
          FROM user u
          LEFT JOIN student s ON u.username = s.student_id
          LEFT JOIN project p ON s.student_id = p.student_id
          LEFT JOIN panel_evaluation pe ON p.project_id = pe.project_id
          WHERE u.username = ?
          GROUP BY u.username, s.student_id, p.project_id";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $reg_no);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

// STEP 4: Initialize default parameters if no record exists
if (!$project) {
    $project = [
        'full_name' => 'Not Registered', 
        'reg_no' => $reg_no, 
        'project_title' => 'TBD', 
        'supervisor_grade' => null, 
        'supervisor_comment' => null,
        'avg_panel_score' => null,
        'transcript_path' => null,
        'student_approval_status' => 'Registered'
    ];
}

// STEP 5: Score Calculation logic
$sup_grade = $project['supervisor_grade']; 
$pan_grade = $project['avg_panel_score'];

$calc_sup = $sup_grade ?? 0;
$calc_pan = $pan_grade ?? 0;

// Weighted total: Supervisor (40%) + Panel (60%)
$total_weighted = round(($calc_sup * 0.4) + ($calc_pan * 0.6));

// STEP 6: Transcript submission check
$has_submitted = !empty($project['transcript_path']);
?>

<div class="container" style="padding: 20px; max-width: 900px; margin: auto; font-family: 'Segoe UI', sans-serif;">
    
    <!-- HEADER CARD -->
    <div style="background: #2c3e50; color: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <h1 style="margin: 0; font-size: 28px;">Welcome, <?php echo htmlspecialchars($project['full_name'] ?? 'Student'); ?>!</h1>
        <p style="margin: 5px 0 0 0; color: #bdc3c7; font-size: 15px;">
            Registration Number: <strong><?php echo htmlspecialchars($project['reg_no']); ?></strong>
        </p>
    </div>

    <!-- GENERAL SUPERVISOR COMMENT / REMARKS BLOCK -->
    <?php if (!empty($project['supervisor_comment'])): ?>
        <div style="background: #ebf5fb; border-left: 5px solid #3498db; padding: 18px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            <h4 style="margin: 0 0 8px 0; color: #2c3e50; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                💬 Overall Supervisor Remarks & Feedback
            </h4>
            <p style="margin: 0; color: #34495e; font-style: italic; white-space: pre-line; line-height: 1.5;">
                "<?php echo htmlspecialchars($project['supervisor_comment']); ?>"
            </p>
        </div>
    <?php endif; ?>

    <!-- ACADEMIC TRANSCRIPT VERIFICATION SECTION -->
    <div style="background: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
        <h3 style="margin-top: 0; color: #2c3e50; font-size: 18px; border-bottom: 1px solid #edf2f7; padding-bottom: 10px;">📄 Academic Transcript Verification</h3>
        
        <?php if ($has_submitted): ?>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <span style="display: block; color: #64748b; font-size: 12px; font-weight: 600; text-transform: uppercase;">Active File</span>
                    <a href="<?php echo htmlspecialchars($project['transcript_path']); ?>" target="_blank" style="color: #2980b9; font-weight: bold; text-decoration: none;">
                        View Submitted Transcript ↗
                    </a>
                </div>
                <div>
                    <?php 
                    $approval_status = strtolower(trim($project['student_approval_status'] ?? ''));
                    if ($approval_status == 'approved' || $approval_status == 'transcript approved') {
                        $badge_bg = '#e8f5e9'; $badge_txt = '#2e7d32';
                    } elseif ($approval_status == 'rejected') {
                        $badge_bg = '#ffebee'; $badge_txt = '#c62828';
                    } else {
                        $badge_bg = '#e1f5fe'; $badge_txt = '#0288d1';
                    }
                    ?>
                    <span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; background: <?php echo $badge_bg; ?>; color: <?php echo $badge_txt; ?>;">
                        Status: <?php echo htmlspecialchars($project['student_approval_status'] ?? 'Pending Review'); ?>
                    </span>
                </div>
            </div>

            <?php if ($approval_status !== 'approved' && $approval_status !== 'transcript approved'): ?>
                <div style="margin-top: 15px;">
                    <details style="background: #fdfefe; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 10px;">
                        <summary style="font-weight: bold; color: #475569; cursor: pointer; font-size: 14px; outline: none;">
                            🔄 Need to Resubmit? Click here to upload an updated transcript
                        </summary>
                        <form action="../actions/upload_transcript.php" method="POST" enctype="multipart/form-data" style="margin-top: 12px; padding: 10px; background: #fff; border-radius: 6px;">
                            <div style="margin-bottom: 12px;">
                                <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px; color: #334155;">Select New Document (PDF only)</label>
                                <input type="file" name="transcript" accept=".pdf" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                <small style="color: #64748b; font-size: 11px; display: block; margin-top: 4px;">Uploading a new file will overwrite your previous submission and alert the coordinator.</small>
                            </div>
                            <button type="submit" style="background: #3498db; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-weight: bold; font-size: 13px; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                Upload & Replace File
                            </button>
                        </form>
                    </details>
                </div>
            <?php else: ?>
                <div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 12px; border-radius: 6px; margin-top: 15px; color: #065f46; font-size: 13px; font-weight: 500;">
                    ✓ Your academic transcript has been successfully verified and approved.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <form action="../actions/upload_transcript.php" method="POST" enctype="multipart/form-data" style="margin-top: 10px;">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; color: #334155;">Upload Academic Transcript (PDF only)</label>
                    <input type="file" name="transcript" accept=".pdf" required style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                    <small style="color: #64748b; font-size: 12px; display: block; margin-top: 4px;">Please upload your academic transcript to enable supervisor allocation.</small>
                </div>
                <button type="submit" style="background: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                    Submit Transcript
                </button>
            </form>
        <?php endif; ?>
    </div>

    <!-- PERFORMANCE / ACADEMIC MARKS CARD -->
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin: 20px 0; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #27ae60;">
        <div style="text-align: center; border-right: 1px solid #eee;">
            <small style="color: #7f8c8d; text-transform: uppercase; font-size: 0.7em; font-weight: bold;">Supervisor (40%)</small>
            <div style="font-size: 1.8em; font-weight: bold; color: #34495e; margin-top: 5px;">
                <?php echo ($sup_grade !== null) ? round($sup_grade) . "%" : "--"; ?>
            </div>
        </div>

        <div style="text-align: center; border-right: 1px solid #eee;">
            <small style="color: #7f8c8d; text-transform: uppercase; font-size: 0.7em; font-weight: bold;">Panel Avg (60%)</small>
            <div style="font-size: 1.8em; font-weight: bold; color: #27ae60; margin-top: 5px;">
                <?php echo ($pan_grade !== null) ? round($pan_grade) . "%" : "--"; ?>
            </div>
        </div>

        <div style="text-align: center;">
            <small style="color: #7f8c8d; text-transform: uppercase; font-size: 0.7em; font-weight: bold;">Final Mark</small>
            <div style="font-size: 2.2em; font-weight: bold; color: #2c3e50;">
                <?php echo ($sup_grade !== null || $pan_grade !== null) ? $total_weighted . "%" : "--"; ?>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['upload']) && $_GET['upload'] == 'success'): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745;">
            <strong>✅ Success!</strong> Your milestones and academic documents have been uploaded and processed.
        </div>
    <?php endif; ?>

    <!-- PROJECT ROADMAP -->
    <h2 style="color: #2c3e50; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; margin-top: 30px;">Project Roadmap</h2>

    <div style="display: flex; flex-direction: column; gap: 15px; margin-top: 15px;">
        <?php 
        $milestones = [
            1 => "Title Proposal", 
            2 => "Problem Statement", 
            3 => "Literature Review",
            4 => "System Design", 
            5 => "Implementation", 
            6 => "Testing", 
            7 => "Final Documentation / Clearance for Defense"
        ];

        // Retrieve global clearance status
        $clearance_status = strtolower(trim($project['clearance_status'] ?? ''));

        for ($i = 1; $i <= 7; $i++): 
            $curr_status = strtolower(trim($project["m{$i}_status"] ?? 'locked'));
            $m_comment = $project["m{$i}_comment"] ?? null; 

            // Calculate previous milestone status for locking mechanism
            $prev_m = $i - 1;
            $prev_status = ($i == 1) ? 'approved' : strtolower(trim($project["m{$prev_m}_status"] ?? 'pending'));
            
            // Comprehensive check for clearance & completion
            $is_approved = (
                $curr_status === 'approved' || 
                $curr_status === 'cleared' || 
                $curr_status === 'completed' ||
                strpos($curr_status, 'cleared for defense') !== false ||
                ($i == 7 && ($clearance_status === 'cleared' || $clearance_status === 'approved')) ||
                ($i == 1 && !empty($project['project_title']) && $project['project_title'] !== 'TBD')
            );
            
            $is_rejected = ($curr_status === 'rejected' || $curr_status === 'revision required');
            $is_pending = ($curr_status === 'pending' || $curr_status === 'pending review');
            
            // Lock rules: lock if previous milestone is not approved/cleared
            $prev_is_cleared = (
                $prev_status === 'approved' || 
                $prev_status === 'cleared' || 
                $prev_status === 'completed' ||
                strpos($prev_status, 'cleared for defense') !== false
            );
            $is_locked = (!$prev_is_cleared && $i > 1);
                
            $bg = $is_approved ? "#f0fff4" : ($is_rejected ? "#fff5f5" : ($is_pending ? "#fffaf0" : "#fff"));
            $accent = $is_approved ? "#27ae60" : ($is_rejected ? "#e74c3c" : ($is_pending ? "#e67e22" : "#3498db"));

            // Dynamic title display
            $display_title = $milestones[$i];
            if ($i == 1 && !empty($project['project_title']) && $project['project_title'] !== 'TBD') {
                $display_title = "Title Approved: <strong>\"" . htmlspecialchars($project['project_title']) . "\"</strong>";
            }
        ?>

            <div style="background: <?php echo $bg; ?>; border: 1px solid #eee; padding: 18px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 10px;">
                
                <!-- TOP ROW: Milestone Header & Action -->
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 35px; height: 35px; border-radius: 50%; background: <?php echo $is_locked ? '#bdc3c7' : $accent; ?>; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; flex-shrink: 0;">
                            <?php echo $i; ?>
                        </div>
                        <div>
                            <h4 style="margin: 0; color: #2c3e50; font-size: 16px; font-weight: 500;"><?php echo $display_title; ?></h4>
                            <small style="color: #7f8c8d; font-weight: 500;">
                                <?php 
                                if ($is_locked) {
                                    echo "Locked";
                                } elseif ($is_approved) {
                                    echo ($i == 7) ? "Cleared for Defense" : "Approved";
                                } elseif ($is_rejected) {
                                    echo "Revision Required";
                                } elseif ($is_pending) {
                                    echo "Pending Review";
                                } else {
                                    echo "Available";
                                }
                                ?>
                            </small>
                        </div>
                    </div>
                    <div>
                        <?php if ($is_approved): ?>
                            <span style="color: #27ae60; font-weight: bold; font-size: 14px;">
                                <?php echo ($i == 7) ? '✓ Cleared for Defense' : '✓ Completed'; ?>
                            </span>
                        <?php elseif (!$is_locked): ?>
                            <a href="submit_milestone.php?m=<?php echo $i; ?>" style="background: <?php echo $accent; ?>; color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; font-size: 0.85em; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: inline-block;">
                                <?php echo $is_rejected ? 'Resubmit' : 'Upload'; ?>
                            </a>
                        <?php else: ?>
                            <span style="color: #bdc3c7; font-weight: 500; font-size: 14px;">🔒 Locked</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- BOTTOM ROW: Milestone Supervisor Feedback (M2 to M7) -->
                <?php if ($i >= 2 && !empty($m_comment)): ?>
                    <div style="margin-top: 5px; padding: 10px 14px; background: rgba(255, 255, 255, 0.8); border-left: 3px solid <?php echo $accent; ?>; border-radius: 6px; font-size: 13px;">
                        <strong style="color: #34495e;">💬 Supervisor Feedback:</strong>
                        <span style="color: #555; font-style: italic; margin-left: 5px;">"<?php echo htmlspecialchars($m_comment); ?>"</span>
                    </div>
                <?php endif; ?>

            </div>
        <?php endfor; ?>
    </div>
</div>

<?php include('../includes/footer.php'); ?>

