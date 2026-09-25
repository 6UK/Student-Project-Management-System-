<?php
// Include authentication check to ensure only authorized roles can access
require_once('../includes/auth_check.php');
checkRole(['coordinator']); 

// Connect to database and include header
require_once('../config/db_connect.php');
include('../includes/header.php');

// 1. DYNAMIC REGISTRATION PIPELINE (All Student Records)
$reg_query = "SELECT s.student_id, s.full_name, s.transcript_path, s.approval_status AS student_approval,
                     COALESCE(NULLIF(s.reg_no, ''), u.username, s.student_id) AS reg_no, 
                     p.project_id, p.supervisor_id, p.m1_status
              FROM student s 
              LEFT JOIN user u ON s.student_id = u.user_id
              LEFT JOIN project p ON s.student_id = p.student_id
              ORDER BY 
                CASE 
                  WHEN LOWER(p.m1_status) = 'pending review' THEN 1
                  WHEN LOWER(p.m1_status) = 'supervisor allocated' THEN 2
                  WHEN LOWER(p.m1_status) = 'transcript approved' OR LOWER(s.approval_status) = 'approved' THEN 3
                  WHEN LOWER(p.m1_status) = 'transcript uploaded' OR LOWER(s.approval_status) = 'transcript uploaded' THEN 4
                  ELSE 5
                END ASC, 
                s.student_id DESC"; 
$reg_result = mysqli_query($conn, $reg_query);

if (!$reg_result) {
    die("<div style='color:red; padding:20px; font-weight:bold;'>Registration Query Error: " . mysqli_error($conn) . "</div>");
}

// 2. DEFENSE LINEUP 
$defense_query = "SELECT p.project_id, p.project_title, p.clearance_status, p.m7_status, p.final_presentation, p.final_grade,
                         s.student_id, s.full_name,
                         COALESCE(NULLIF(s.reg_no, ''), u.username, s.student_id) AS reg_no, 
                         COALESCE(pe.total_panel_mark, p.final_grade) AS calculated_score
                  FROM project p 
                  JOIN student s ON p.student_id = s.student_id
                  LEFT JOIN user u ON s.student_id = u.user_id
                  LEFT JOIN panel_evaluation pe ON p.project_id = pe.project_id
                  WHERE LOWER(p.clearance_status) = 'cleared'
                  GROUP BY p.project_id
                  ORDER BY p.m7_status DESC";

$defense_result = mysqli_query($conn, $defense_query);

if (!$defense_result) {
    die("<div style='color:red; padding:20px; font-weight:bold;'>Defense Lineup Query Error: " . mysqli_error($conn) . "</div>");
}

// Fetch supervisor details for dropdown mapping in forms
$supervisors = mysqli_query($conn, "SELECT supervisor_id, full_name FROM supervisor");
$sup_list = ($supervisors && mysqli_num_rows($supervisors) > 0) ? mysqli_fetch_all($supervisors, MYSQLI_ASSOC) : [];
?>

<div class="container" style="padding: 20px; max-width: 1200px; margin: auto; font-family: 'Segoe UI', sans-serif;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
        <h1 style="color: #2c3e50; margin: 0;">Coordinator Control Panel</h1>
        <a href="manage_allocations.php" style="text-decoration: none; background: #3498db; color: white; padding: 8px 14px; border-radius: 4px; font-weight: bold; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">⚙️ Go to Allocations Board</a>
    </div>
    <p style="color: #7f8c8d; margin-top: 0; margin-bottom: 30px;">Manage student supervisor allocations and evaluate final presentation queues.</p>

    <h3 style="color: #2c3e50; margin-top: 30px; border-bottom: 2px solid #3498db; padding-bottom: 10px;">Phase 1: Registration & Supervisor Allocation</h3>
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 6px; overflow: hidden;">
        <thead>
            <tr style="background-color: #2c3e50; color: white; text-align: left;">
                <th style="padding: 12px; border: 1px solid #ddd;">Student Info</th>
                <th style="padding: 12px; border: 1px solid #ddd;">Verification Dossier</th>
                <th style="padding: 12px; border: 1px solid #ddd;">Milestone 1 State</th>
                <th style="padding: 12px; border: 1px solid #ddd;">Assign Supervisor</th>
            </tr>
        </thead>
        <tbody>
            <?php if($reg_result && mysqli_num_rows($reg_result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($reg_result)): ?>
                <tr>
                    <td style="padding: 12px; border: 1px solid #ddd;">
                        <strong><?php echo htmlspecialchars($row['full_name'] ?? 'Not Set'); ?></strong><br>
                        <small style="color: #666; font-weight: bold;"><?php echo htmlspecialchars($row['reg_no']); ?></small>
                    </td>

                    <td style="padding: 12px; border: 1px solid #ddd;">
                        <?php if(!empty($row['transcript_path'])): ?>
                            <a href="view_transcript.php?pid=<?php echo $row['project_id'] ?? 0; ?>&sid=<?php echo $row['student_id']; ?>" target="_blank" style="color: #2980b9; font-weight: bold; text-decoration: none; display: inline-block; background: #ebf5fb; padding: 6px 12px; border-radius: 4px; border: 1px solid #a9cce3;">
                                📄 View Transcript
                            </a>
                        <?php else: ?>
                            <span style="color: #e74c3c; font-style: italic;">No Transcript Found</span>
                        <?php endif; ?>
                    </td>

                    <td style="padding: 12px; border: 1px solid #ddd;">
                        <?php 
                            $m1_st = !empty($row['m1_status']) ? $row['m1_status'] : (!empty($row['transcript_path']) ? 'Transcript Uploaded' : ($row['student_approval'] ?? 'Registered'));
                            $normalized_status = strtolower(trim($m1_st));

                            if ($normalized_status == 'pending review' || $normalized_status == 'titles submitted') {
                                $badge_bg = '#e8f5e9'; $badge_txt = '#2e7d32';
                            } elseif ($normalized_status == 'supervisor allocated') {
                                $badge_bg = '#fff3e0'; $badge_txt = '#e67e22';
                            } elseif ($normalized_status == 'transcript approved' || $normalized_status == 'approved') {
                                $badge_bg = '#f3e5f5'; $badge_txt = '#6a1b9a';
                            } elseif ($normalized_status == 'registered') {
                                $badge_bg = '#f5f5f5'; $badge_txt = '#616161';
                            } else {
                                $badge_bg = '#e1f5fe'; $badge_txt = '#0288d1';
                            }
                        ?>
                        <span style="padding: 6px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; background: <?php echo $badge_bg; ?>; color: <?php echo $badge_txt; ?>; display: inline-block; text-transform: capitalize;">
                            <?php echo htmlspecialchars($m1_st); ?>
                        </span>
                    </td>

                    <td style="padding: 12px; border: 1px solid #ddd;">
                        <form action="../actions/assign_supervisor.php" method="POST" style="display: flex; gap: 5px; margin: 0;">
                            <input type="hidden" name="project_id" value="<?php echo $row['project_id'] ?? ''; ?>">
                            <input type="hidden" name="student_id" value="<?php echo $row['student_id']; ?>">
                            
                            <!-- Dropdown with Don't Assign option and NO required attribute -->
                            <select name="supervisor_id" style="padding: 6px; flex-grow: 1; border: 1px solid #cbd5e0; border-radius: 4px;">
                                <option value="">-- Don't Assign / Unassign --</option>
                                <?php foreach($sup_list as $sup): ?>
                                    <option value="<?php echo $sup['supervisor_id']; ?>" <?php echo (($row['supervisor_id'] ?? '') == $sup['supervisor_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sup['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" style="padding: 6px 12px; background: #2c3e50; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;">Assign</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="padding: 30px; text-align: center; color: #7f8c8d; font-style: italic;">No student records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h3 style="color: #2c3e50; margin-top: 50px; border-bottom: 2px solid #27ae60; padding-bottom: 10px;">Phase 2: Final Defense Queue</h3>
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 6px; overflow: hidden;">
        <thead>
            <tr style="background-color: #27ae60; color: white; text-align: left;">
                <th style="padding: 12px; border: 1px solid #ddd; width: 25%;">Student Info</th>
                <th style="padding: 12px; border: 1px solid #ddd; width: 40%;">Project Title</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: center; width: 15%;">Defense Grade</th>
                <th style="padding: 12px; border: 1px solid #ddd; width: 10%;">Defense Status</th>
                <th style="padding: 12px; border: 1px solid #ddd; text-align: center; width: 10%;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if($defense_result && mysqli_num_rows($defense_result) > 0): ?>
                <?php while($def = mysqli_fetch_assoc($defense_result)): ?>
                    <?php 
                        $score = $def['calculated_score'];
                        $is_presentation_done = (!empty($def['final_presentation']) && strtolower(trim($def['final_presentation'])) === 'yes');
                        $is_graded = (!is_null($score) && $score > 0) || $is_presentation_done;
                    ?>
                <tr>
                    <td style="padding: 12px; border: 1px solid #ddd;">
                        <strong><?php echo htmlspecialchars($def['full_name'] ?? 'Not Set'); ?></strong><br>
                        <small style="color: #666; font-weight: bold;"><?php echo htmlspecialchars($def['reg_no']); ?></small>
                    </td>
                    
                    <td style="padding: 12px; border: 1px solid #ddd; font-weight: 500; color: #2c3e50;">
                        <?php echo htmlspecialchars($def['project_title'] ?? 'No Approved Title'); ?>
                    </td>
                    
                    <td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; text-align: center; color: <?php echo $is_graded ? '#27ae60' : '#e74c3c'; ?>; font-size: 15px;">
                        <?php echo $is_graded ? htmlspecialchars($score ?? '0') . "%" : "Not Graded"; ?>
                    </td>
                    
                    <td style="padding: 12px; border: 1px solid #ddd;">
                        <?php if($is_graded): ?>
                            <span style="color: #27ae60; font-weight: bold; display: flex; align-items: center; gap: 4px;">✅ Completed</span>
                        <?php else: ?>
                            <span style="color: #e67e22; font-weight: bold; display: flex; align-items: center; gap: 4px;">⏳ Ready for Defense</span>
                        <?php endif; ?>
                    </td>
                    
                    <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                        <div style="display: flex; gap: 5px; justify-content: center; align-items: center;">
                            
                            <!-- Schedule Button -->
                            <?php if (strtolower(trim($def['m7_status'] ?? '')) !== 'scheduled'): ?>
                                <form action="../actions/schedule_defense.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="project_id" value="<?php echo $def['project_id']; ?>">
                                    <button type="submit" style="padding: 6px 10px; background: #e67e22; color: white; border: none; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer;" title="Schedule Defense">
                                        📅 Schedule
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Grade / View Score Button -->
                            <a href="evaluate_defense.php?pid=<?php echo $def['project_id']; ?>" 
                               style="padding: 6px 12px; background: <?php echo $is_graded ? '#27ae60' : '#3498db'; ?>; color: white; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: bold; display: inline-block;">
                               <?php echo $is_graded ? 'View Score' : 'Grade Defense'; ?>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5" style="padding: 30px; text-align: center; color: #7f8c8d; font-style: italic;">No students currently in the final defense queue.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include('../includes/footer.php'); ?>