<?php
// 1. DATABASE & LOGIC
require_once('config/db_connect.php');

$error = "";
$success_msg = "";

// Check if redirected with success
if (isset($_GET['registered']) && $_GET['registered'] == 'success') {
    $new_id = $_GET['new_id'] ?? '';
    $success_msg = "Supervisor registration successful! Login ID: <strong>" . htmlspecialchars($new_id) . "</strong>. You can now log in.";
}

if (isset($_POST['register_supervisor_btn'])) {
    $staff_id = trim($_POST['staff_id']); // Manual ID typed by the supervisor
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $password_raw = $_POST['password'];
    $sec_ans = mysqli_real_escape_string($conn, $_POST['security_answer']);

    // Strong Password Validation Rules
    if (strlen($password_raw) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password_raw)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password_raw)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password_raw)) {
        $error = "Password must contain at least one number.";
    } else {
        $password = password_hash($password_raw, PASSWORD_DEFAULT);

        // Start Transaction
        mysqli_begin_transaction($conn);

        try {
            // Step 1: Insert into user table using the manual staff_id for both user_id and username
            $stmt1 = $conn->prepare("INSERT INTO user (user_id, username, password, role, security_answer) VALUES (?, ?, ?, 'supervisor', ?)");
            $stmt1->bind_param("isss", $staff_id, $staff_id, $password, $sec_ans);
            $stmt1->execute();

            // Step 2: Insert into supervisor profile table using the same staff_id
            $stmt2 = $conn->prepare("INSERT INTO supervisor (supervisor_id, full_name, department) VALUES (?, ?, ?)");
            $stmt2->bind_param("iss", $staff_id, $full_name, $department);
            $stmt2->execute();

            mysqli_commit($conn);
            
            // Success: Redirect back to show success message
            header("Location: register_supervisor.php?registered=success&new_id=" . $staff_id);
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Registration failed (ID might already exist): " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SPMS | Register Supervisor</title>
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0;">

    <div style="background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; margin: 20px;">
        <h2 style="text-align: center; color: #2c3e50; margin-bottom: 30px;">Faculty Registration</h2>
        
        <?php if($success_msg): ?>
            <div style="color: #27ae60; background: #e8f8f5; padding: 12px; border-radius: 5px; text-align: center; border: 1px solid #a3e4d7; margin-bottom: 20px; font-size: 0.95em;">
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <?php if($error): ?>
            <div style="color: #e74c3c; background: #fdeaea; padding: 12px; border-radius: 5px; text-align: center; border: 1px solid #fadbd8; margin-bottom: 20px; font-size: 0.95em;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register_supervisor.php">
            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom: 5px; font-weight: bold;">Staff ID / Number</label>
                <input type="text" name="staff_id" required placeholder="e.g., 1030060" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom: 5px; font-weight: bold;">Full Name & Title</label>
                <input type="text" name="full_name" required placeholder="Dr. Mary Kimani" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom: 5px; font-weight: bold;">Department</label>
                <select name="department" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; background: white;">
                    <option value="">-- Choose Department --</option>
                    <option value="Computer Science">Computer Science</option>
                    <option value="Information Technology">Information Technology</option>
                </select>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom: 5px; font-weight: bold;">Password</label>
                <input type="password" name="password" required 
                       placeholder="Min 8 chars, 1 uppercase, 1 number" 
                       pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" 
                       title="Must contain at least one number, one uppercase and lowercase letter, and at least 8 or more characters"
                       style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box;">
                <small style="color: #7f8c8d; font-size: 0.75em;">Must be 8+ chars with uppercase, lowercase, and a number.</small>
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display:block; margin-bottom: 5px; font-weight: bold;">High School Name (Security)</label>
                <input type="text" name="security_answer" required placeholder="For account recovery" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box;">
            </div>

            <button type="submit" name="register_supervisor_btn" style="width: 100%; padding: 14px; background: #2c3e50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold;">
                Register Supervisor
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 25px; border-top: 1px solid #eee; padding-top: 20px;">
            <a href="index.php" style="color: #3498db; text-decoration: none; font-size: 0.9em;">Already have an account? Login</a>
        </div>
    </div>

</body>
</html>