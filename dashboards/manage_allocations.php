<?php
// 1. Error Reporting Configuration
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Start Output Buffering
ob_start();

require_once('../includes/auth_check.php');
checkRole(['coordinator', 'hod']); 
require_once('../config/db_connect.php');

include('../includes/header.php');

// 3. Fetch Supervisors for Dropdown Selection
$sup_query = "SELECT supervisor_id, full_name FROM supervisor ORDER BY full_name ASC";
$supervisors_res = mysqli_query($conn, $sup_query);

if (!$supervisors_res) {
    die("<div style='color:red; padding:20px;'>Supervisor Query Error: " . htmlspecialchars(mysqli_error($conn)) . "</div>");
}
$all_sups = mysqli_fetch_all($supervisors_res, MYSQLI_ASSOC);

// 3.5 Capture Milestone Filter Parameter (Default to 'all')
$milestone_filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';

// 4. Fetch All Allocations Pipeline with Conditional Filtering
$query = "SELECT 
            p.project_id, 
            p.student_id,
            p.supervisor_id,
            p.project_title,
            p.m1_status,
            s.full_name AS student_name, 
            s.transcript_path,
            u.username AS reg_no, 
            sup.full_name AS supervisor_name 
          FROM project p 
          JOIN user u ON p.student_id = u.user_id 
          LEFT JOIN student s ON p.student_id = s.student_id
          LEFT JOIN supervisor sup ON p.supervisor_id = sup.supervisor_id";

// Apply SQL filter logic based on m1_status
if ($milestone_filter === 'approved') {
    $query .= " WHERE LOWER(TRIM(p.m1_status)) = 'approved'";
} elseif ($milestone_filter === 'pending') {
    $query .= " WHERE (p.m1_status IS NULL OR LOWER(TRIM(p.m1_status)) <> 'approved')";
}

$query .= " ORDER BY (p.supervisor_id IS NULL) DESC, p.project_id DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("<div style='color:red; padding:20px;'>Main Allocation Query Error: " . htmlspecialchars(mysqli_error($conn)) . "</div>");
}
?>

<div class="container" style="padding: 20px; max-width: 1200px; margin: auto; font-family: 'Segoe UI', sans-serif;">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; flex-wrap: wrap; gap: 10px;">
        <h1 style="color: #2c3e50; margin: 0;">Supervisor Allocations Management</h1>
        <a href="coordinator.php" style="text-decoration: none; background: #7f8c8d; color: white; padding: 8px 14px; border-radius: 4px; font-weight: bold; font-size: 14px;">&larr; Back to Control Panel</a>
    </div>
    <p style="color: #7f8c8d; margin-top: 0; margin-bottom: 20px;">Pair students with faculty members or re-allocate supervisors for active projects.</p>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'allocated'): ?>
        <div style="background: #e8f5e9; color: #27ae60; padding: 12px; border-radius: 4px; border: 1px solid #d4edda; font-weight: bold; margin-bottom: 20px;">
            Faculty supervisor updated and project status set to Approved!
        </div>
    <?php endif; ?>

    <div style="background: white; padding: 15px 20px; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <span style="font-size: 0.9em; font-weight: 600; color: #4a5568;">Filter Milestone Status:</span>
        <a href="manage_allocations.php?filter=all" style="text-decoration: none; padding: 6px 14px; border-radius: 4px; font-size: 0.85em; font-weight: bold; background: <?php echo ($milestone_filter === 'all') ? '#3498db' : '#edf2f7'; ?>; color: <?php echo ($milestone_filter === 'all') ? 'white' : '#4a5568'; ?>;">All</a>
        <a href="manage_allocations.php?filter=approved" style="text-decoration: none; padding: 6px 14px; border-radius: 4px; font-size: 0.85em; font-weight: bold; background: <?php echo ($milestone_filter === 'approved') ? '#27ae60' : '#edf2f7'; ?>; color: <?php echo ($milestone_filter === 'approved') ? 'white' : '#4a5568'; ?>;">Approved</a>
        <a href="manage_allocations.php?filter=pending" style="text-decoration: none; padding: 6px 14px; border-radius: 4px; font-size: 0.85em; font-weight: bold; background: <?php echo ($milestone_filter === 'pending') ? '#e67e22' : '#edf2f7'; ?>; color: <?php echo ($milestone_filter === 'pending') ? 'white' : '#4a5568'; ?>;">Pending</a>
    </div>

    <table style="width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 6px; overflow: hidden;">
        <thead>
            <tr style="background-color: #2c3e50; color: white; text-align: left;">
                <th style="padding: 12px; border: 1px solid #ddd; width: 35%;">Student & Core Deliverable</th>
                <th style="padding: 12px; border: 1px solid #ddd; width: 20%;">Milestone Status</th>
                <th style="padding: 12px; border: 1px solid #ddd; width: 20%;">Current Allocated Faculty</th>
                <th style="padding: 12px; border: 1px solid #ddd; width: 25%;">Re-assign / Allocate Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php 
                        $m1_st = $row['m1_status'];
                        $is_approved = ($m1_st === 'Approved');
                        
                        $badge_bg = $is_approved ? '#e8f5e9' : '#fdeaea';
                        $badge_txt = $is_approved ? '#27ae60' : '#c0392b';
                        $display_text = $is_approved ? "● Approved ✔" : "● " . htmlspecialchars($m1_st ?? 'Pending');
                    ?>
                    <tr>
                        <td style="padding: 12px; border: 1px solid #ddd;">
                            <strong style="font-size: 15px; color: #2c3e50; text-transform: capitalize;">
                                <?php echo htmlspecialchars($row['student_name'] ?? 'Not Set'); ?>
                            </strong><br>
                            <small style="color: #666; font-weight: bold; display: block; margin-bottom: 6px;">
                                <?php echo htmlspecialchars($row['reg_no']); ?>
                            </small>
                            
                            <?php if (!empty($row['project_title']) && $row['project_title'] !== 'Pending Title Submission'): ?>
                                <div style="font-size: 13px; color: #34495e; margin-bottom: 8px; font-style: italic;">
                                    <strong>Title:</strong> <?php echo htmlspecialchars($row['project_title']); ?>
                                </div>
                            <?php endif; ?>

                            <div style="display: flex; gap: 8px; align-items: center; margin-top: 5px;">
                                <?php if (!empty($row['transcript_path'])): ?>
                                    <a href="view_transcript.php?pid=<?php echo urlencode($row['project_id']); ?>&sid=<?php echo urlencode($row['student_id']); ?>" target="_blank" style="color: #2980b9; font-weight: bold; text-decoration: none; font-size: 12px; background: #ebf5fb; padding: 4px 8px; border-radius: 4px; border: 1px solid #a9cce3;">
                                        📄 View Transcript
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        
                        <td style="padding: 12px; border: 1px solid #ddd;">
                            <span style="padding: 6px 12px; border-radius: 12px; font-size: 13px; font-weight: bold; background: <?php echo $badge_bg; ?>; color: <?php echo $badge_txt; ?>; display: inline-block;">
                                <?php echo $display_text; ?>
                            </span>
                        </td>

                        <td style="padding: 12px; border: 1px solid #ddd; font-weight: 600; color: #34495e; font-size: 14px;">
                            <?php echo !empty($row['supervisor_name']) ? htmlspecialchars($row['supervisor_name']) : '<span style="color: #e74c3c; font-style: italic;">None paired yet</span>'; ?>
                        </td>

                        <td style="padding: 12px; border: 1px solid #ddd;">
                            <form action="../actions/assign_supervisor.php" method="POST" style="display: flex; gap: 8px; margin: 0; align-items: center;">
                                <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($row['project_id']); ?>">
                                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($row['student_id']); ?>">
                                
                                <select name="supervisor_id" required style="padding: 8px; border-radius: 4px; border: 1px solid #cbd5e0; flex-grow: 1; font-size: 13px; background: #fff;">
                                    <option value="">-- Choose Faculty Member --</option>
                                    <?php foreach ($all_sups as $sup): ?>
                                        <option value="<?php echo htmlspecialchars($sup['supervisor_id']); ?>" <?php echo ($row['supervisor_id'] == $sup['supervisor_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sup['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <button type="submit" style="padding: 8px 14px; background: #2c3e50; color: white; border: none; border-radius: 4px; font-weight: bold; font-size: 13px; cursor: pointer;">
                                    <?php echo empty($row['supervisor_id']) ? 'Allocate' : 'Re-assign'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="padding: 40px; text-align: center; color: #7f8c8d; font-style: italic;">
                        No student project records match the selected milestone filter.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php 
include('../includes/footer.php'); 
ob_end_flush(); 
?>