<?php
require_once('../includes/auth_check.php');
require_once('../config/db_connect.php');

// Strict role check
checkRole(['hod']); 
include('../includes/header.php');

$message = "";

// ============================================================================
// VIEW 1: INDIVIDUAL STUDENT TRACKING DETAILS (Runs if ?id= is in the URL)
// ============================================================================
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $student_id = (int)$_GET['id'];

    $query = "SELECT s.full_name, 
                     u.username as reg_no, 
                     p.*, sup.full_name as supervisor_name,
                     pe.presentation_score, pe.documentation_score, pe.references_score, 
                     pe.logic_score, pe.code_understanding_score, pe.security_score, 
                     pe.reports_score, pe.comments, pe.total_panel_mark
              FROM student s 
              LEFT JOIN user u ON s.student_id = u.user_id 
              LEFT JOIN project p ON s.student_id = p.student_id
              LEFT JOIN supervisor sup ON p.supervisor_id = sup.supervisor_id
              LEFT JOIN panel_evaluation pe ON p.project_id = pe.project_id
              WHERE s.student_id = ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("<div class='container' style='color:red;'>SQL Error: " . htmlspecialchars($conn->error) . "</div>");
    }
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res ? $res->fetch_assoc() : null;

    // Fallback Query if student record is detached or project table missing link
    if (!$data || empty($data['project_title'])) { 
        $fallback_query = "SELECT p.*, pe.total_panel_mark, pe.presentation_score, pe.documentation_score, 
                                  pe.references_score, pe.logic_score, pe.code_understanding_score, 
                                  pe.security_score, pe.reports_score, pe.comments, sup.full_name as supervisor_name
                           FROM project p 
                           LEFT JOIN panel_evaluation pe ON p.project_id = pe.project_id 
                           LEFT JOIN supervisor sup ON p.supervisor_id = sup.supervisor_id
                           WHERE p.student_id = ?";
        $f_stmt = $conn->prepare($fallback_query);
        if ($f_stmt) {
            $f_stmt->bind_param("i", $student_id);
            $f_stmt->execute();
            $f_res = $f_stmt->get_result();
            $fallback_data = $f_res ? $f_res->fetch_assoc() : null;
            
            if ($fallback_data) {
                $data = array_merge($data ?? [], $fallback_data);
                if (empty($data['full_name'])) $data['full_name'] = "Student ID: " . $student_id;
                if (empty($data['reg_no'])) $data['reg_no'] = $student_id;
                if (empty($data['supervisor_name'])) $data['supervisor_name'] = "Unassigned";
            }
        }
    }

    if (!$data) { 
        die("<div style='padding: 20px; max-width: 600px; margin: 40px auto; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); font-family: Segoe UI, sans-serif;'><h3 style='color: #c0392b; margin-top: 0;'>Student project records not found.</h3><p style='color: #7f8c8d;'>The requested student profile could not be located in the database.</p><a href='hod.php' style='display: inline-block; margin-top: 10px; background: #3498db; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; font-weight: bold;'>&larr; Back to Dashboard</a></div>"); 
    }

    // Determine Academic Semester Cohort dynamically
    $project_date = !empty($data['created_at']) ? strtotime($data['created_at']) : time();
    $project_month = (int)date('n', $project_date);
    $project_year = date('Y', $project_date);

    if ($project_month >= 1 && $project_month <= 4) { 
        $cohort = "January - April " . $project_year; 
    } elseif ($project_month >= 5 && $project_month <= 8) { 
        $cohort = "May - August " . $project_year; 
    } else { 
        $cohort = "September - December " . $project_year; 
    }

    $clearance_status_clean = strtolower(trim($data['clearance_status'] ?? ''));
    $is_cleared = ($clearance_status_clean === 'cleared' || $clearance_status_clean === 'approved');
    $has_project = !empty($data['project_id']);
?>

    <div style="padding: 20px; max-width: 1000px; margin: 20px auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; box-sizing: border-box;">
        <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <a href="hod.php" style="text-decoration: none; color: #3498db; font-weight: bold; font-size: 0.95em;">&larr; Back to Main Dashboard</a>
            <div style="display: flex; gap: 10px; align-items: center;">
                <?php if ($has_project && $is_cleared): ?>
                    <a href="evaluate_defense.php?pid=<?php echo (int)$data['project_id']; ?>" style="text-decoration: none; background: #27ae60; color: white; padding: 8px 15px; border-radius: 4px; font-weight: bold; font-size: 0.9em; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        🎓 <?php echo isset($data['total_panel_mark']) ? 'Edit Defense Grade' : 'Grade Defense'; ?>
                    </a>
                <?php endif; ?>
                <a href="hod_reports.php" style="text-decoration: none; background: #8e44ad; color: white; padding: 8px 15px; border-radius: 4px; font-weight: bold; font-size: 0.9em; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">View Full Semester Reports</a>
            </div>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-left: 4px solid #8e44ad; margin-bottom: 20px;">
            <h1 style="margin: 0; color: #2c3e50; font-size: 24px; font-weight: 600;">Project Oversight: <?php echo htmlspecialchars($data['full_name']); ?></h1>
            <p style="margin: 8px 0 0 0; color: #7f8c8d; font-size: 0.95em;">
                <strong style="color: #34495e;">Supervisor:</strong> <?php echo htmlspecialchars($data['supervisor_name'] ?? 'Not Assigned'); ?> 
                <span style="margin: 0 10px; color: #ccc;">|</span>
                <strong style="color: #34495e;">Academic Cohort:</strong> <span style="color: #8e44ad; font-weight: bold;"><?php echo $cohort; ?></span>
            </p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #3498db;">
                <h3 style="margin: 0 0 12px 0; color: #3498db; font-size: 1.1em; font-weight: 600;">Student Information</h3>
                <p style="margin: 8px 0; color: #4a5568; font-size: 0.95em;"><strong style="color: #2d3748;">Reg No / Username:</strong> <?php echo htmlspecialchars($data['reg_no'] ?? $student_id); ?></p>
                <p style="margin: 8px 0; color: #4a5568; font-size: 0.95em;"><strong style="color: #2d3748;">Approved Title:</strong> <br>
                    <span style="color: #27ae60; font-weight: bold; display: inline-block; margin-top: 4px;">
                        <?php echo htmlspecialchars($data['project_title'] ?? 'Title Pending'); ?>
                    </span>
                </p>
                <p style="margin: 8px 0; color: #4a5568; font-size: 0.95em;"><strong style="color: #2d3748;">Clearance Status:</strong> 
                    <span style="padding: 3px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; background: <?php echo $is_cleared ? '#e8f5e9' : '#ffebee'; ?>; color: <?php echo $is_cleared ? '#27ae60' : '#c0392b'; ?>; display: inline-block; margin-top: 2px;">
                        <?php echo htmlspecialchars($data['clearance_status'] ?? 'Not Cleared'); ?>
                    </span>
                </p>
            </div>

            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #e67e22;">
                <h3 style="margin: 0 0 12px 0; color: #e67e22; font-size: 1.1em; font-weight: 600;">Departmental Scoring</h3>
                <p style="margin: 8px 0; color: #4a5568; font-size: 0.95em;"><strong style="color: #2d3748;">Supervisor Mark (40%):</strong> <?php echo htmlspecialchars($data['supervisor_grade'] ?? '0'); ?>%</p>
                <p style="margin: 8px 0; color: #4a5568; font-size: 0.95em;"><strong style="color: #2d3748;">Panel Mark (60%):</strong> <?php echo htmlspecialchars($data['total_panel_mark'] ?? '0'); ?>%</p>
                <p style="margin: 8px 0; color: #4a5568; font-size: 0.95em;"><strong style="color: #2d3748;">Total Composite Weight:</strong> 
                    <span style="font-size: 1.2em; font-weight: bold; color: #2c3e50; display: block; margin-top: 4px;">
                        <?php 
                         $sup_component = ((float)($data['supervisor_grade'] ?? 0)) * 0.4;
                         $panel_component = ((float)($data['total_panel_mark'] ?? 0)) * 0.6;
                         $final = $sup_component + $panel_component;
                         echo number_format($final, 2);
                        ?>%
                    </span>
                </p>
            </div>
        </div>

        <?php if (isset($data['total_panel_mark'])): ?>
        <div style="background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-top: 4px solid #27ae60;">
            <h3 style="margin: 0 0 5px 0; color: #27ae60; font-size: 1.15em; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <span>Coordinator Panel Evaluation Roster</span>
                <span style="background: #e8f5e9; color: #27ae60; padding: 4px 12px; border-radius: 20px; font-size: 0.8em; font-weight: bold;">Final Mark: <?php echo htmlspecialchars($data['total_panel_mark']); ?>%</span>
            </h3>
            <p style="color: #7f8c8d; font-size: 0.9em; margin: 0 0 15px 0;">Detailed breakdown of evaluation criteria evaluated during oral presentation defense.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 10px; border: 1px solid #edf2f7;">
                <div style="border-right: 1px solid #e2e8f0; padding-right: 10px;">
                    <small style="color: #7f8c8d; display: block; text-transform: uppercase; font-size: 0.75em; font-weight: 600;">Presentation</small>
                    <strong style="font-size: 1.2em; color: #2c3e50; display: block; margin-top: 3px;"><?php echo (int)($data['presentation_score'] ?? 0); ?></strong>
                </div>
                <div style="border-right: 1px solid #e2e8f0; padding-right: 10px; padding-left: 5px;">
                    <small style="color: #7f8c8d; display: block; text-transform: uppercase; font-size: 0.75em; font-weight: 600;">Documentation</small>
                    <strong style="font-size: 1.2em; color: #2c3e50; display: block; margin-top: 3px;"><?php echo (int)($data['documentation_score'] ?? 0); ?></strong>
                </div>
                <div style="border-right: 1px solid #e2e8f0; padding-right: 10px; padding-left: 5px;">
                    <small style="color: #7f8c8d; display: block; text-transform: uppercase; font-size: 0.75em; font-weight: 600;">References</small>
                    <strong style="font-size: 1.2em; color: #2c3e50; display: block; margin-top: 3px;"><?php echo (int)($data['references_score'] ?? 0); ?></strong>
                </div>
                <div style="padding-left: 5px;">
                    <small style="color: #7f8c8d; display: block; text-transform: uppercase; font-size: 0.75em; font-weight: 600;">System Logic</small>
                    <strong style="font-size: 1.2em; color: #27ae60; display: block; margin-top: 3px;"><?php echo (int)($data['logic_score'] ?? 0); ?></strong>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; background: #f9f9f9; padding: 15px; border-radius: 6px; border: 1px solid #edf2f7;">
                <div style="border-right: 1px solid #e2e8f0; padding-right: 10px;">
                    <small style="color: #7f8c8d; display: block; text-transform: uppercase; font-size: 0.75em; font-weight: 600;">Code Understanding</small>
                    <strong style="font-size: 1.2em; color: #2c3e50; display: block; margin-top: 3px;"><?php echo (int)($data['code_understanding_score'] ?? 0); ?></strong>
                </div>
                <div style="border-right: 1px solid #e2e8f0; padding-right: 10px; padding-left: 5px;">
                    <small style="color: #7f8c8d; display: block; text-transform: uppercase; font-size: 0.75em; font-weight: 600;">System Security</small>
                    <strong style="font-size: 1.2em; color: #2c3e50; display: block; margin-top: 3px;"><?php echo (int)($data['security_score'] ?? 0); ?></strong>
                </div>
                <div style="padding-left: 5px;">
                    <small style="color: #7f8c8d; display: block; text-transform: uppercase; font-size: 0.75em; font-weight: 600;">Report Layout</small>
                    <strong style="font-size: 1.2em; color: #2c3e50; display: block; margin-top: 3px;"><?php echo (int)($data['reports_score'] ?? 0); ?></strong>
                </div>
            </div>

            <div style="margin-top: 15px; padding: 12px; background: #edf2f7; border-left: 4px solid #3182ce; border-radius: 4px;">
                <strong style="display: block; font-size: 0.85em; color: #2b6cb0; text-transform: uppercase; margin-bottom: 3px; letter-spacing: 0.5px;">Panel Examination Comments:</strong>
                <span style="color: #2d3748; font-style: italic; font-size: 0.95em;">"<?php echo htmlspecialchars($data['comments'] ?? 'No comments provided.'); ?>"</span>
            </div>
        </div>
        <?php else: ?>
        <div style="background: #fff5f5; border-left: 4px solid #e53e3e; padding: 15px; border-radius: 6px; margin-bottom: 20px; color: #c53030; font-weight: 500; font-size: 0.95em; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            ⚠️ Notice: No panel oral defense records have been finalized by the evaluation committee for this student yet.
        </div>
        <?php endif; ?>

        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); overflow-x: auto;">
            <h3 style="margin: 0 0 15px 0; color: #2c3e50; font-size: 1.15em; font-weight: 600;">Milestone Progress Tracker</h3>
            <table style="width: 100%; border-collapse: collapse; min-width: 500px;">
                <thead>
                    <tr style="background: #f4f4f4; text-align: left;">
                        <th style="padding: 12px; border: 1px solid #ddd; color: #2c3e50; font-size: 0.9em;">Milestone</th>
                        <th style="padding: 12px; border: 1px solid #ddd; color: #2c3e50; font-size: 0.9em;">Current Status</th>
                        <th style="padding: 12px; border: 1px solid #ddd; color: #2c3e50; font-size: 0.9em;">Evidence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    for($i=1; $i<=7; $i++): 
                        $raw_st = $data["m{$i}_status"] ?? 'Not Started'; 
                        $st_clean = strtolower(trim($raw_st));
                        
                        $is_m_approved = ($st_clean === 'approved' || $st_clean === 'cleared' || $st_clean === 'completed' || ($i == 7 && $is_cleared));
                        $is_m_pending = ($st_clean === 'pending' || $st_clean === 'pending review');
                        
                        // Dynamic document existence check
                        $doc_pattern = "../uploads/milestones/M{$i}_{$student_id}.*";
                        $matching_files = glob($doc_pattern);
                        $has_doc = !empty($matching_files);
                        $file_path = $has_doc ? $matching_files[0] : '';
                    ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; border: 1px solid #ddd; color: #2d3748;"><strong style="color: #2c3e50;">Milestone <?php echo $i; ?></strong></td>
                        <td style="padding: 12px; border: 1px solid #ddd;">
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; background: <?php echo $is_m_approved ? '#e8f5e9' : ($is_m_pending ? '#fff3e0' : '#f5f5f5'); ?>; color: <?php echo $is_m_approved ? '#27ae60' : ($is_m_pending ? '#e67e22' : '#7f8c8d'); ?>; display: inline-block;">
                                <?php echo htmlspecialchars($is_m_approved ? ($i == 7 ? 'Cleared' : 'Approved') : $raw_st); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; border: 1px solid #ddd;">
                            <?php if($has_doc): ?>
                                <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" style="color: #3498db; text-decoration: none; font-weight: bold; font-size: 0.9em;">📂 View Document</a>
                            <?php else: ?>
                                <span style="color: #cbd5e0;">---</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php 
// =========================================================================
// VIEW 2: GLOBAL HOD DASHBOARD (Runs if NO ?id= is present in URL)
// =========================================================================
} else { 
    // Capture filter parameter from URL (default to 'all')
    $filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';

    $list_query = "SELECT s.student_id, s.full_name, 
                            u.username as reg_no, 
                            p.project_id, p.project_title, p.clearance_status,
                            (SELECT ROUND(AVG(pe.total_panel_mark), 2) 
                             FROM panel_evaluation pe 
                             WHERE pe.project_id = p.project_id) as total_panel_mark
                   FROM student s
                   LEFT JOIN user u ON s.student_id = u.user_id
                   LEFT JOIN project p ON s.student_id = p.student_id";

    // Apply conditional SQL filtering based on clearance status
    if ($filter === 'cleared') {
        $list_query .= " WHERE LOWER(TRIM(p.clearance_status)) IN ('cleared', 'approved')";
    } elseif ($filter === 'not_cleared') {
        $list_query .= " WHERE (p.clearance_status IS NULL OR LOWER(TRIM(p.clearance_status)) NOT IN ('cleared', 'approved'))";
    }

    $list_query .= " ORDER BY u.username ASC";
    $result = $conn->query($list_query);
    ?>

    <div style="padding: 20px; max-width: 1100px; margin: 20px auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; box-sizing: border-box;">
        <h1 style="color: #2c3e50; font-size: 26px; margin: 0 0 5px 0; font-weight: 600;">HOD Management Panel</h1>
        <p style="color: #7f8c8d; font-size: 0.95em; margin: 0 0 15px 0;">Welcome, <strong style="color: #34495e;">HOD / Department Chair</strong>. Select a student below to audit progress, clear milestones, or grade oral panel defenses.</p>
        <hr style="border: none; border-top: 1px solid #e2e8f0; margin-bottom: 20px;">

        <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <h3 style="margin: 0; color: #2c3e50; font-size: 1.15em; font-weight: 600;">Departmental Student Roster</h3>
                
                <!-- Clearance Filter Toolbar -->
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span style="font-size: 0.85em; font-weight: 600; color: #4a5568;">Filter Clearance:</span>
                    <a href="hod.php?filter=all" style="text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 0.85em; font-weight: bold; background: <?php echo ($filter === 'all') ? '#3498db' : '#edf2f7'; ?>; color: <?php echo ($filter === 'all') ? 'white' : '#4a5568'; ?>; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">All</a>
                    <a href="hod.php?filter=cleared" style="text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 0.85em; font-weight: bold; background: <?php echo ($filter === 'cleared') ? '#27ae60' : '#edf2f7'; ?>; color: <?php echo ($filter === 'cleared') ? 'white' : '#4a5568'; ?>; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">Cleared / Approved</a>
                    <a href="hod.php?filter=not_cleared" style="text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 0.85em; font-weight: bold; background: <?php echo ($filter === 'not_cleared') ? '#c0392b' : '#edf2f7'; ?>; color: <?php echo ($filter === 'not_cleared') ? 'white' : '#4a5568'; ?>; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">Not Cleared</a>
                </div>

                <a href="hod_reports.php" style="text-decoration: none; background: #8e44ad; color: white; padding: 8px 15px; border-radius: 4px; font-weight: bold; font-size: 0.9em; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"> Generate Semester Reports</a>
            </div>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; min-width: 700px;">
                    <thead>
                        <tr style="background: #34495e; color: white; text-align: left;">
                            <th style="padding: 12px; border: 1px solid #2c3e50; font-size: 0.9em;">Reg No / User</th>
                            <th style="padding: 12px; border: 1px solid #2c3e50; font-size: 0.9em;">Student Name</th>
                            <th style="padding: 12px; border: 1px solid #2c3e50; font-size: 0.9em;">Project Title</th>
                            <th style="padding: 12px; border: 1px solid #2c3e50; text-align: center; font-size: 0.9em;">Clearance</th>
                            <th style="padding: 12px; border: 1px solid #2c3e50; text-align: center; font-size: 0.9em;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): 
                                $row_clearance = strtolower(trim($row['clearance_status'] ?? ''));
                                $is_cleared = ($row_clearance === 'cleared' || $row_clearance === 'approved');
                                $has_project = !empty($row['project_id']);
                                $is_graded = isset($row['total_panel_mark']);
                            ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; color: #2d3748; font-size: 0.95em;"><?php echo htmlspecialchars($row['reg_no'] ?? $row['student_id']); ?></td>
                                <td style="padding: 12px; border: 1px solid #ddd; color: #2d3748; font-size: 0.95em;"><?php echo htmlspecialchars($row['full_name'] ?? 'Unknown Student'); ?></td>
                                <td style="padding: 12px; border: 1px solid #ddd; font-style: italic; color: #4a5568; font-size: 0.95em;"><?php echo htmlspecialchars($row['project_title'] ?? 'No Title Approved Yet'); ?></td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 0.85em; font-weight: bold; background: <?php echo $is_cleared ? '#e8f5e9' : '#ffebee'; ?>; color: <?php echo $is_cleared ? '#27ae60' : '#c0392b'; ?>; display: inline-block;">
                                        <?php echo htmlspecialchars($row['clearance_status'] ?? 'Not Cleared'); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                    <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                                        <a href="hod.php?id=<?php echo (int)$row['student_id']; ?>" style="background: #3498db; color: white; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 0.85em; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                            Track
                                        </a>

                                        <?php if ($has_project && $is_cleared): ?>
                                            <a href="evaluate_defense.php?pid=<?php echo (int)$row['project_id']; ?>" style="background: <?php echo $is_graded ? '#27ae60' : '#8e44ad'; ?>; color: white; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; font-size: 0.85em; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                                <?php echo $is_graded ? 'Edit Grade' : 'Grade Defense'; ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 25px; color: #7f8c8d; font-size: 0.95em;">No student records match the selected clearance filter.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } ?>

<?php include('../includes/footer.php'); ?>