<?php
// STEP 1: Force error reporting during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// STEP 2: Include authentication and database connection
require_once('../includes/auth_check.php');
require_once('../config/db_connect.php');

// STEP 3: Strict role check
checkRole(['hod']);
include('../includes/header.php');

// STEP 4: Capture filter parameters & Report Type
$selected_report = isset($_GET['report_type']) ? $_GET['report_type'] : 'progress';
$selected_year = isset($_GET['cohort_year']) ? (int)$_GET['cohort_year'] : (int)date('Y');
$selected_semester = isset($_GET['cohort_semester']) ? $_GET['cohort_semester'] : '';
$selected_status = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'All';
$selected_supervisor = isset($_GET['supervisor_filter']) ? $_GET['supervisor_filter'] : 'All';
$search_keyword = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';

// STEP 5: Auto-detect semester if none selected
if (empty($selected_semester)) {
    $m = (int)date('n');
    if ($m >= 1 && $m <= 4) $selected_semester = 'Jan-Apr';
    elseif ($m >= 5 && $m <= 8) $selected_semester = 'May-Aug';
    else $selected_semester = 'Sep-Dec';
}

// STEP 5B: Fetch list of supervisors for the filter dropdown (Fixed to use supervisor.full_name)
$supervisors_query = "SELECT s.supervisor_id, s.full_name 
                      FROM supervisor s 
                      ORDER BY s.full_name ASC";
$supervisors_result = $conn->query($supervisors_query);

// STEP 6: Build Dynamic SQL Query with Prepared Statements / Parameter Binding
$where_clauses = [];
$params = [];
$types = "";

if ($selected_semester === 'Jan-Apr') {
    $where_clauses[] = "YEAR(COALESCE(p.created_at, NOW())) = ? AND MONTH(COALESCE(p.created_at, NOW())) BETWEEN 1 AND 4";
} elseif ($selected_semester === 'May-Aug') {
    $where_clauses[] = "YEAR(COALESCE(p.created_at, NOW())) = ? AND MONTH(COALESCE(p.created_at, NOW())) BETWEEN 5 AND 8";
} else {
    $where_clauses[] = "YEAR(COALESCE(p.created_at, NOW())) = ? AND MONTH(COALESCE(p.created_at, NOW())) BETWEEN 9 AND 12";
}
$params[] = $selected_year;
$types .= "i";

if ($selected_status === 'Cleared') {
    $where_clauses[] = "p.clearance_status = 'Cleared'";
} elseif ($selected_status === 'Not Cleared') {
    $where_clauses[] = "(p.clearance_status != 'Cleared' OR p.clearance_status IS NULL)";
}

if ($selected_supervisor === 'Unassigned') {
    $where_clauses[] = "p.supervisor_id IS NULL";
} elseif ($selected_supervisor !== 'All') {
    $where_clauses[] = "p.supervisor_id = ?";
    $params[] = (int)$selected_supervisor;
    $types .= "i";
}

if (!empty($search_keyword)) {
    $where_clauses[] = "(u.username LIKE ? OR s.full_name LIKE ? OR sup_table.full_name LIKE ? OR p.project_title LIKE ?)";
    $like_val = "%" . $search_keyword . "%";
    $params[] = $like_val;
    $params[] = $like_val;
    $params[] = $like_val;
    $params[] = $like_val;
    $types .= "ssss";
}

$sql_where = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// FIXED MAIN QUERY: Joins supervisor table properly to fetch full_name instead of user id/number
$query = "SELECT s.student_id, s.full_name, u.username as reg_no, p.*, 
                 p.supervisor_id as supervisor_id_val, sup_table.full_name as supervisor_name,
                 pe.total_panel_mark
          FROM student s
          LEFT JOIN user u ON s.student_id = u.user_id
          LEFT JOIN project p ON s.student_id = p.student_id
          LEFT JOIN supervisor sup_table ON p.supervisor_id = sup_table.supervisor_id
          LEFT JOIN (
              SELECT project_id, AVG(total_panel_mark) as total_panel_mark
              FROM panel_evaluation
              GROUP BY project_id
          ) pe ON p.project_id = pe.project_id
          {$sql_where}
          ORDER BY u.username ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// STEP 7: Initialize counters and data arrays
$total_students = 0;
$cleared_count = 0;
$pending_count = 0;
$running_grade_total = 0;
$graded_students = 0;
$students_data = [];

$letter_grades_count = [
    'A'  => 0, 'B+' => 0, 'B' => 0, 'C+' => 0, 
    'C'  => 0, 'D+' => 0, 'D' => 0, 'E' => 0  
];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $total_students++;
        $current_clearance = ($row['clearance_status'] ?? '') === 'Cleared' ? 'Cleared' : 'Not Cleared';
        
        if ($current_clearance === 'Cleared') { 
            $cleared_count++; 
        } else { 
            $pending_count++; 
        }

        $sup_grade = $row['supervisor_grade'] ?? $row['supervisor_mark'] ?? 0;
        $pan_grade = $row['total_panel_mark'] ?? $row['panel_grade'] ?? 0;
        $composite = ($sup_grade * 0.4) + ($pan_grade * 0.6);
        $row['calculated_final'] = $composite;

        if ($sup_grade > 0 || $pan_grade > 0) {
            $running_grade_total += $composite;
            $graded_students++;

            if ($composite >= 70) $letter_grades_count['A']++;
            elseif ($composite >= 65) $letter_grades_count['B+']++;
            elseif ($composite >= 60) $letter_grades_count['B']++;
            elseif ($composite >= 55) $letter_grades_count['C+']++;
            elseif ($composite >= 50) $letter_grades_count['C']++;
            elseif ($composite >= 45) $letter_grades_count['D+']++;
            elseif ($composite >= 40) $letter_grades_count['D']++;
            else $letter_grades_count['E']++;
        }

        $students_data[] = $row;
    }
}

$class_average = $graded_students > 0 ? ($running_grade_total / $graded_students) : 0;

$workload_data = [];
if ($selected_report === 'workload') {
    // FIXED WORKLOAD QUERY: Pulls full_name directly from the supervisor table
    $wl_query = "SELECT s.supervisor_id, s.full_name AS supervisor_name, 
                        COUNT(p.project_id) AS total_assigned,
                        SUM(CASE WHEN p.clearance_status = 'Cleared' THEN 1 ELSE 0 END) AS cleared_count,
                        SUM(CASE WHEN p.clearance_status != 'Cleared' OR p.clearance_status IS NULL THEN 1 ELSE 0 END) AS pending_count
                 FROM supervisor s
                 LEFT JOIN project p ON s.supervisor_id = p.supervisor_id
                 GROUP BY s.supervisor_id, s.full_name
                 ORDER BY total_assigned DESC";
    $wl_res = $conn->query($wl_query);
    if ($wl_res) {
        while ($w_row = $wl_res->fetch_assoc()) {
            $workload_data[] = $w_row;
        }
    }
}
?>

<div class="container animate-print" style="padding: 20px; max-width: 1200px; margin: auto; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <a href="hod.php" style="text-decoration: none; color: #3498db; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
            &larr; Return to Dashboard
        </a>
        <button onclick="triggerSystemPrint();" style="background: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            Execute Print / Save PDF
        </button>
    </div>

    <div style="background: #2c3e50; color: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; border-left: 6px solid #8e44ad;">
        <h1 style="margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 0.5px;">Departmental Management Reports</h1>
        <p style="margin: 5px 0 0 0; color: #b2bec3; font-size: 14px;">
            Target Cohort: <strong><?php echo $selected_semester . " - " . $selected_year; ?></strong> | Active Report: <strong><?php echo ucwords(str_replace('_', ' ', $selected_report)); ?></strong>
            <?php if (!empty($search_keyword)): ?>
                | Search Filter: <strong>"<?php echo htmlspecialchars($search_keyword); ?>"</strong>
            <?php endif; ?>
        </p>
    </div>

    <!-- FILTER MATRIX & REPORT SELECTOR FORM -->
    <div class="no-print" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 25px;">
        <form method="GET" action="hod_reports.php" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            
            <div>
                <label style="font-weight: bold; color: #4a5568; display: block; margin-bottom: 5px; font-size: 13px;">Select Report Module:</label>
                <select name="report_type" onchange="this.form.submit()" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e0; background: white; font-weight: bold; color: #2c3e50;">
                    <option value="progress" <?php echo ($selected_report === 'progress') ? 'selected' : ''; ?>>1. Student Progress & Grades</option>
                    <option value="workload" <?php echo ($selected_report === 'workload') ? 'selected' : ''; ?>>2. Faculty Supervisor Workload</option>
                    <option value="grades" <?php echo ($selected_report === 'grades') ? 'selected' : ''; ?>>3. Grade Classification Breakdown</option>
                    <option value="exceptions" <?php echo ($selected_report === 'exceptions') ? 'selected' : ''; ?>>4. Exception & Unassigned Audit</option>
                </select>
            </div>

            <div>
                <label style="font-weight: bold; color: #4a5568; display: block; margin-bottom: 5px; font-size: 13px;">Filter Graduation Year:</label>
                <select name="cohort_year" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e0; background: white;">
                    <?php for($y = (int)date('Y'); $y >= (int)date('Y') - 2; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($selected_year === $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div>
                <label style="font-weight: bold; color: #4a5568; display: block; margin-bottom: 5px; font-size: 13px;">Filter Semester Cohort:</label>
                <select name="cohort_semester" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e0; background: white;">
                    <option value="Jan-Apr" <?php echo ($selected_semester === 'Jan-Apr') ? 'selected' : ''; ?>>January - April Term</option>
                    <option value="May-Aug" <?php echo ($selected_semester === 'May-Aug') ? 'selected' : ''; ?>>May - August Term</option>
                    <option value="Sep-Dec" <?php echo ($selected_semester === 'Sep-Dec') ? 'selected' : ''; ?>>September - December Term</option>
                </select>
            </div>

            <div>
                <label style="font-weight: bold; color: #4a5568; display: block; margin-bottom: 5px; font-size: 13px;">Filter Clearance Status:</label>
                <select name="status_filter" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e0; background: white;">
                    <option value="All" <?php echo ($selected_status === 'All') ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="Cleared" <?php echo ($selected_status === 'Cleared') ? 'selected' : ''; ?>>Cleared Only</option>
                    <option value="Not Cleared" <?php echo ($selected_status === 'Not Cleared') ? 'selected' : ''; ?>>Not Cleared / Pending Only</option>
                </select>
            </div>

            <div>
                <label style="font-weight: bold; color: #4a5568; display: block; margin-bottom: 5px; font-size: 13px;">Filter Assigned Adviser:</label>
                <select name="supervisor_filter" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e0; background: white;">
                    <option value="All" <?php echo ($selected_supervisor === 'All') ? 'selected' : ''; ?>>All Advisers</option>
                    <option value="Unassigned" <?php echo ($selected_supervisor === 'Unassigned') ? 'selected' : ''; ?>>-- Unassigned --</option>
                    <?php if ($supervisors_result && $supervisors_result->num_rows > 0): ?>
                        <?php while ($sup = $supervisors_result->fetch_assoc()): ?>
                            <option value="<?php echo $sup['supervisor_id']; ?>" <?php echo ((string)$selected_supervisor === (string)$sup['supervisor_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sup['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div>
                <label style="font-weight: bold; color: #4a5568; display: block; margin-bottom: 5px; font-size: 13px;">Search Student / Reg No:</label>
                <input type="text" name="search_query" value="<?php echo htmlspecialchars($search_keyword); ?>" placeholder="e.g. CS/001/2022 or John" style="padding: 8px 12px; border-radius: 4px; border: 1px solid #cbd5e0; background: white; width: 180px;" />
            </div>

            <div style="align-self: flex-end;">
                <button type="submit" style="background: #3498db; color: white; border: none; padding: 9px 20px; border-radius: 4px; font-weight: bold; cursor: pointer;">
                    Apply Filter Matrix
                </button>
            </div>
        </form>
    </div>

    <!-- SUMMARY METRICS CARDS -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-top: 4px solid #3498db; text-align: center;">
            <span style="color: #7f8c8d; font-size: 12px; font-weight: bold; text-transform: uppercase;">Filtered Cohort Size</span>
            <p style="margin: 8px 0 0 0; font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo $total_students; ?></p>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-top: 4px solid #27ae60; text-align: center;">
            <span style="color: #7f8c8d; font-size: 12px; font-weight: bold; text-transform: uppercase;">Cleared Profiles</span>
            <p style="margin: 8px 0 0 0; font-size: 24px; font-weight: bold; color: #27ae60;"><?php echo $cleared_count; ?></p>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-top: 4px solid #e74c3c; text-align: center;">
            <span style="color: #7f8c8d; font-size: 12px; font-weight: bold; text-transform: uppercase;">Unfinished Review</span>
            <p style="margin: 8px 0 0 0; font-size: 24px; font-weight: bold; color: #e74c3c;"><?php echo $pending_count; ?></p>
        </div>
        <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-top: 4px solid #8e44ad; text-align: center;">
            <span style="color: #7f8c8d; font-size: 12px; font-weight: bold; text-transform: uppercase;">Cohort Grade Mean</span>
            <p style="margin: 8px 0 0 0; font-size: 24px; font-weight: bold; color: #8e44ad;"><?php echo number_format($class_average, 2); ?>%</p>
        </div>
    </div>

    <!-- REPORT VIEW SWITCHER -->
    <?php if ($selected_report === 'progress' || $selected_report === 'exceptions'): ?>
        <!-- REPORT 1 & 4: STUDENT DETAILS TABLE -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0; color: #2c3e50; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px;">
                <?php echo ($selected_report === 'exceptions') ? 'Exception Audit: Unassigned or Incomplete Records' : 'Student Progress & Final Grades List'; ?>
            </h3>
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #34495e; color: white;">
                        <th style="padding: 12px; border: 1px solid #ddd;">Registration Number</th>
                        <th style="padding: 12px; border: 1px solid #ddd;">Student Full Name</th>
                        <th style="padding: 12px; border: 1px solid #ddd;">Project Running Title</th>
                        <th style="padding: 12px; border: 1px solid #ddd;">Assigned Adviser</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: center;">Total Mark</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $display_data = $students_data;
                    if ($selected_report === 'exceptions') {
                        $display_data = array_filter($students_data, function($st) {
                            return empty($st['supervisor_id']) || ($st['clearance_status'] ?? '') !== 'Cleared';
                        });
                    }
                    ?>
                    <?php if (count($display_data) > 0): ?>
                        <?php foreach ($display_data as $student): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px; border: 1px solid #ddd; font-weight: bold;"><?php echo htmlspecialchars($student['reg_no'] ?? 'N/A'); ?></td>
                                <td style="padding: 12px; border: 1px solid #ddd;"><?php echo htmlspecialchars($student['full_name'] ?? 'N/A'); ?></td>
                                <td style="padding: 12px; border: 1px solid #ddd; font-style: italic;"><?php echo htmlspecialchars($student['project_title'] ?? 'No Title Submitted'); ?></td>
                                <td style="padding: 12px; border: 1px solid #ddd; color: #555;"><?php echo htmlspecialchars($student['supervisor_name'] ?? 'Unassigned ⚠️'); ?></td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold; color: #2c3e50;">
                                    <?php echo number_format($student['calculated_final'], 2); ?>%
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                    <span style="font-weight: bold; color: <?php echo (($student['clearance_status'] ?? '') === 'Cleared') ? '#27ae60' : '#d35400'; ?>;">
                                        <?php echo htmlspecialchars($student['clearance_status'] ?? 'Pending'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #7f8c8d; font-style: italic;">No student records match the search or filter criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($selected_report === 'workload'): ?>
        <!-- REPORT 2: FACULTY WORKLOAD TABLE (Standard Native HTML Table for Full Compatibility) -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0; color: #2c3e50; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; margin-bottom: 15px;">Faculty Supervisor Workload Distribution</h3>
            
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #34495e; color: white;">
                        <th style="padding: 12px; border: 1px solid #ddd; width: 30%;">Supervisor Name</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: center; width: 15%;">Assigned Load</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: center; width: 15%;">Cleared Students</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: center; width: 15%;">Pending Clearance</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: center; width: 25%;">Capacity Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($workload_data) > 0): ?>
                        <?php $i = 0; foreach ($workload_data as $fac): $i++; ?>
                            <tr style="background: <?php echo ($i % 2 == 0) ? '#fcfdff' : '#ffffff'; ?>; border-bottom: 1px solid #eee;">
                                <td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; color: #2c3e50;">
                                    <?php echo htmlspecialchars($fac['supervisor_name']); ?>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">
                                    <?php echo $fac['total_assigned']; ?>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center; color: #27ae60; font-weight: bold;">
                                    <?php echo $fac['cleared_count']; ?>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center; color: #d35400; font-weight: bold;">
                                    <?php echo $fac['pending_count']; ?>
                                </td>
                                <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                                    <?php 
                                        $load = (int)$fac['total_assigned'];
                                        if ($load > 10) {
                                            echo '<span style="color: #c0392b; font-weight: bold; background: #fadbd8; padding: 3px 8px; border-radius: 4px;">High Load ⚠️</span>';
                                        } elseif ($load > 5) {
                                            echo '<span style="color: #d68910; font-weight: bold; background: #fcf3cf; padding: 3px 8px; border-radius: 4px;">Optimal</span>';
                                        } else {
                                            echo '<span style="color: #27ae60; font-weight: bold; background: #d4efdf; padding: 3px 8px; border-radius: 4px;">Available Capacity</span>';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 30px; color: #7f8c8d; font-style: italic;">No faculty workload records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($selected_report === 'grades'): ?>
        <!-- REPORT 3: UNIT LETTER GRADING SCALE & COHORT BREAKDOWN -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; margin-bottom: 15px;">
                <h3 style="margin: 0; color: #2c3e50;">Unit Letter Grading Scale & Cohort Breakdown</h3>
            </div>
            
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #34495e; color: white;">
                        <th style="padding: 12px; border: 1px solid #ddd;">Letter Grade</th>
                        <th style="padding: 12px; border: 1px solid #ddd;">Mark Range (%)</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: center;">GPA Points</th>
                        <th style="padding: 12px; border: 1px solid #ddd;">Description / Remarks</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: center;">Student Count</th>
                        <th style="padding: 12px; border: 1px solid #ddd; text-align: center;">Percentage of Graded Cohort</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grading_scale_rows = [
                        ['A', '70 – 100%', '4.0', 'Excellent'],
                        ['B+', '65 – 69%', '3.5', 'Very Good'],
                        ['B', '60 – 64%', '3.0', 'Good'],
                        ['C+', '55 – 59%', '2.5', 'Fairly Good'],
                        ['C', '50 – 54%', '2.0', 'Average'],
                        ['D+', '45 – 49%', '1.5', 'Below Average'],
                        ['D', '40 – 44%', '1.0', 'Pass (Minimum pass mark is 40%)'],
                        ['E', 'Below 40%', '0.0', 'Fail (Eligible for Supplementary)']
                    ];

                    foreach ($grading_scale_rows as $row_item) {
                        $grade_key = $row_item[0];
                        $count = $letter_grades_count[$grade_key] ?? 0;
                        $percentage = $graded_students > 0 ? ($count / $graded_students) * 100 : 0;
                        
                        echo '<tr style="border-bottom: 1px solid #eee;">';
                        echo '<td style="padding: 12px; border: 1px solid #ddd; font-weight: bold; color: #2c3e50;">' . $grade_key . '</td>';
                        echo '<td style="padding: 12px; border: 1px solid #ddd;">' . $row_item[1] . '</td>';
                        echo '<td style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">' . $row_item[2] . '</td>';
                        echo '<td style="padding: 12px; border: 1px solid #ddd;">' . $row_item[3] . '</td>';
                        echo '<td style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold; color: #2980b9;">' . $count . '</td>';
                        echo '<td style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold; color: #27ae60;">' . number_format($percentage, 1) . '%</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</div>

<style>
@media print {
    .no-print, form, button, nav, header, footer, .navbar, .sidebar { 
        display: none !important; 
    }
    body, .container { 
        background: #fff !important; 
        color: #000 !important; 
        width: 100% !important; 
        max-width: 100% !important; 
        margin: 0 !important; 
        padding: 0 !important; 
    }
    div[style*="box-shadow"] { 
        box-shadow: none !important; 
        border: 1px solid #000 !important; 
    }
    table { width: 100% !important; }
    th, td { border: 1px solid #000 !important; padding: 8px !important; font-size: 12px !important; }
    tr[style*="background: #34495e"] { background: #eee !important; color: #000 !important; }
}
</style>

<script>
function triggerSystemPrint() {
    setTimeout(function() {
        window.print();
    }, 250);
}
</script>

<?php include('../includes/footer.php'); ?>