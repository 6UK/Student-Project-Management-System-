<?php
// STEP 1: Security check - ensures the user is logged in before loading any data
require_once('../includes/auth_check.php');

// STEP 2: Database Connection - imports the database connection variable ($conn)
require_once('../config/db_connect.php');

// STEP 3: Role Validation - verifies if the logged-in user explicitly has 'supervisor' privileges
checkRole(['supervisor']);

// STEP 4: Layout Template - includes the standard structural header HTML for the dashboard
include('../includes/header.php');

// STEP 5: Session Retrieval - extracts the unique user ID of the logged-in supervisor from session memory
$sid = $_SESSION['user_id'];

// =========================================================================
// STEP 6: FETCH SUPERVISOR INFO
// =========================================================================

// Write the SQL blueprint query to select supervisor details matching their account ID
$s_info_query = "SELECT * FROM supervisor WHERE supervisor_id = ?";

// Prepare the SQL query statement to protect against SQL Injection attacks
$s_stmt = $conn->prepare($s_info_query);

// Bind the actual supervisor ID variable ($sid) into the placeholder question mark (?) as an integer ("i")
$s_stmt->bind_param("i", $sid);

// Execute the secure query against the connected database
$s_stmt->execute();

// Fetch the execution result and save it into an associative array named $supervisor
$supervisor = $s_stmt->get_result()->fetch_assoc();

// =========================================================================
// STEP 7: FETCH ASSIGNED STUDENTS & THEIR SPECIFIC PROJECT PROGRESS
// =========================================================================

// Construct a complex SQL query joining three related database tables: project, user, and student
$query = "SELECT 
            p.student_id,
            p.project_title, 
            p.m1_status, 
            u.username AS student_reg, 
            s.full_name 
          FROM project p 
          JOIN user u ON p.student_id = u.user_id 
          LEFT JOIN student s ON p.student_id = s.student_id
          WHERE p.supervisor_id = ?
          ORDER BY FIELD(p.m1_status, 'Pending Review', 'Transcript Uploaded', 'Approved', 'Rejected') ASC, p.student_id DESC";

// Prepare the complex data retrieval query to guarantee secure processing
$stmt = $conn->prepare($query);

// Bind the supervisor ID variable ($sid) to the query's parameter placeholder
$stmt->bind_param("i", $sid);

// Run the query to search for all students assigned to this supervisor
$stmt->execute();

// Store the complete query dataset resource inside the variable $result (to loop through in the HTML below)
$result = $stmt->get_result();
?>
<div class="container" style="padding: 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 25px; border-left: 5px solid #2c3e50;">
        <h2 style="margin: 0; color: #2c3e50;">Welcome, <?php echo htmlspecialchars($supervisor['full_name'] ?? 'Supervisor'); ?></h2>
        <p style="margin: 5px 0 0 0; color: #7f8c8d;">Department: <strong><?php echo htmlspecialchars($supervisor['department'] ?? 'General'); ?></strong></p>
    </div>

    <h3 style="color: #2c3e50;">My Supervised Students</h3>

    <?php if($result->num_rows > 0): ?>
        <!-- Table displays only if students are assigned -->
        <table style="width: 100%; border-collapse: collapse; margin-top: 10px; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <thead>
                <tr style="background-color: #2c3e50; color: white; text-align: left;">
                    <th style="padding: 15px; border: 1px solid #ddd;">Student Info</th>
                    <th style="padding: 15px; border: 1px solid #ddd;">Approved Title</th>
                    <th style="padding: 15px; border: 1px solid #ddd;">Milestone 1 Status</th>
                    <th style="padding: 15px; border: 1px solid #ddd; text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td style="padding: 12px; border: 1px solid #ddd;">
                        <strong><?php echo htmlspecialchars($row['full_name'] ?? 'Pending Profile...'); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($row['student_reg']); ?></small>
                    </td>
                    <td style="padding: 12px; border: 1px solid #ddd; font-style: <?php echo (!empty($row['project_title']) && $row['project_title'] !== 'Reviewing Proposals' && $row['project_title'] !== 'Pending Title Submission') ? 'normal' : 'italic'; ?>;">
                        <?php 
                            if (empty($row['project_title']) || $row['project_title'] === 'Reviewing Proposals' || $row['project_title'] === 'Pending Title Submission') {
                                echo "No Title Approved Yet";
                            } else {
                                echo htmlspecialchars($row['project_title']); 
                            }
                        ?>
                    </td>
                    <td style="padding: 12px; border: 1px solid #ddd;">
                        <?php 
                            $status = $row['m1_status'] ?? 'Registered';
                            
                            $bg_color = "#f4f6f7"; $text_color = "#7f8c8d";
                            
                            if($status == 'Approved') { 
                                $bg_color = "#e8f5e9"; $text_color = "#27ae60"; 
                            } elseif($status == 'Pending Review') { 
                                $bg_color = "#fff3e0"; $text_color = "#e67e22"; 
                            } elseif($status == 'Transcript Uploaded') { 
                                $bg_color = "#e1f5fe"; $text_color = "#0288d1"; 
                            } elseif($status == 'Rejected') { 
                                $bg_color = "#fdeaea"; $text_color = "#e74c3c"; 
                            }
                        ?>
                        <span style="padding: 5px 10px; border-radius: 20px; font-size: 0.85em; font-weight: bold; background: <?php echo $bg_color; ?>; color: <?php echo $text_color; ?>; border: 1px solid <?php echo $text_color; ?>; display: inline-block;">
                            <?php echo htmlspecialchars($status); ?>
                        </span>
                    </td>
                    <td style="padding: 12px; border: 1px solid #ddd; text-align: center;">
                        <a href="view_student.php?id=<?php echo $row['student_id']; ?>" 
                           style="padding: 8px 15px; background: <?php echo ($status == 'Pending Review') ? '#e67e22' : '#3498db'; ?>; color: white; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: bold; display: inline-block;">
                           <?php echo ($status == 'Pending Review') ? 'Review Proposals' : 'View Details'; ?>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <!-- Professional Prompt Displayed When No Students Are Assigned Yet -->
        <div style="background: #fff8e1; border: 1px solid #ffe0b2; padding: 30px; border-radius: 8px; text-align: center; margin-top: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <h3 style="color: #f39c12; margin-top: 0; margin-bottom: 10px;">Awaiting Coordinator Approval</h3>
            <p style="color: #7f8c8d; margin: 0; font-size: 1.05em;">
                Your account has been successfully registered, but no students have been assigned to your supervision portfolio yet. 
                Please contact your department coordinator to allocate students to your account.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php include('../includes/footer.php'); ?>