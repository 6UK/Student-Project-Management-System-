<?php
// ==========================================
// STEP 1: INITIALIZATION & DATABASE SETUP
// ==========================================
session_start();
require_once('config/db_connect.php');

// ==========================================
// STEP 2: AUTO-REDIRECT IF ALREADY LOGGED IN
// ==========================================
if (isset($_SESSION['user_id'])) {
    header("Location: dashboards/" . $_SESSION['role'] . ".php");
    exit();
}

$error = "";

// ==========================================
// STEP 3: LOGIN FORM SUBMISSION PROCESSING
// ==========================================
if (isset($_POST['login_btn'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $selected_tab_role = isset($_POST['selected_role']) ? strtolower(trim($_POST['selected_role'])) : '';

    // ==========================================
    // STEP 4: SECURE DATABASE LOOKUP
    // ==========================================
    $stmt = $conn->prepare("SELECT * FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        
        // ==========================================
        // STEP 5: PASSWORD VERIFICATION
        // ==========================================
        if (password_verify($password, $row['password'])) {
            $db_role = strtolower(trim($row['role']));
            $valid_roles = ['student', 'hod', 'coordinator', 'supervisor', 'admin'];

            if (!in_array($db_role, $valid_roles)) {
                $error = "Access denied: Unrecognized user role configuration in database.";
            } elseif ($db_role !== 'admin' && $selected_tab_role !== $db_role) {
                $error = "Access denied: This account is registered as a " . ucfirst($db_role) . ". Please select the correct tab.";
            } else {
                // ==========================================
                // STEP 6: SESSION CREATION & REDIRECTION
                // ==========================================
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['role'] = $db_role;
                
                header("Location: dashboards/" . $db_role . ".php");
                exit();
            }
        } else {
            $error = "Invalid password. Please check your credentials.";
        }
    } else {
        $error = "Account not found with that username/registration number.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SPMS - System Login</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .login-card { max-width: 420px; margin: 50px auto; padding: 30px; border: 1px solid #e1e1e1; border-radius: 12px; background: white; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        .success-box { background: #e8f5e9; border: 1px solid #2ecc71; color: #27ae60; padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        .error-msg { color: #e74c3c; background: #fdeaea; padding: 12px; border-radius: 6px; text-align: center; font-size: 0.95em; margin-bottom: 15px; }
        .role-tabs { display: flex; background: #f1f5f9; padding: 4px; border-radius: 8px; margin-bottom: 20px; gap: 4px; }
        .role-tab { flex: 1; text-align: center; padding: 8px 2px; font-size: 0.75em; font-weight: 600; color: #64748b; background: transparent; border: none; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; }
        .role-tab.active { background: #ffffff; color: #1e293b; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        label { font-weight: 600; color: #34495e; font-size: 0.9em; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; margin-top: 6px; margin-bottom: 15px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1em; }
        input:focus { border-color: #3498db; outline: none; box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15); }
        button[type="submit"] { width: 100%; padding: 12px; background: #1e293b; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 1em; transition: background 0.2s; }
        button[type="submit"]:hover { background: #0f172a; }
        .footer-links { text-align: center; margin-top: 25px; border-top: 1px solid #f1f5f9; padding-top: 20px; }
        .btn-register { display: block; width: 100%; padding: 10px; background: #10b981; color: white; text-decoration: none; border-radius: 6px; box-sizing: border-box; font-weight: bold; text-align: center; margin-top: 10px; transition: background 0.2s; }
        .btn-register:hover { background: #059669; }
        .btn-supervisor { display: inline-block; padding: 10px 20px; background: #64748b; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 0.9em; transition: background 0.2s; }
        .btn-supervisor:hover { background: #475569; }
    </style>
</head>
<body>

<div class="login-card">
    
    <!-- STEP 7: REGISTRATION SUCCESS NOTIFICATION -->
    <?php if (isset($_GET['registered']) && $_GET['registered'] == 'success' && isset($_GET['new_student'])): ?>
        <div class="success-box">
            <strong style="display: block; font-size: 1.2em;">Registration Successful!</strong>
            <p style="margin: 8px 0; color: #2c3e50;">Your official Registration Number is:</p>
            <div style="font-size: 1.6em; font-weight: bold; background: #fff; padding: 8px; border-radius: 4px; border: 2px dashed #27ae60; color: #1e293b;">
                <?php echo htmlspecialchars($_GET['new_student']); ?>
            </div>
            <p style="font-size: 0.85em; margin-top: 8px; color: #555;">Please use this number to sign in below.</p>
        </div>
    <?php endif; ?>

    <h2 style="text-align: center; color: #1e293b; margin-top: 0;">SPMS Login</h2>
    <p style="text-align: center; color: #64748b; font-size: 0.9em; margin-bottom: 20px;">Student Project Management System</p>
    
    <?php if($error): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- STEP 8: LOGIN FORM INTERFACE -->
    <form method="POST" id="loginForm">
        <input type="hidden" name="selected_role" id="selectedRoleInput" value="student">

        <div class="role-tabs">
            <button type="button" class="role-tab active" onclick="setRoleTab('student', this)">Student</button>
            <button type="button" class="role-tab" onclick="setRoleTab('hod', this)">HOD</button>
            <button type="button" class="role-tab" onclick="setRoleTab('coordinator', this)">Coordinator</button>
            <button type="button" class="role-tab" onclick="setRoleTab('supervisor', this)">Supervisor</button>
        </div>

        <label id="usernameLabel">Registration Number</label>
        <input type="text" name="username" id="usernameInput" required placeholder="Enter Reg Number">
        
        <label>Password</label>
        <input type="password" name="password" required placeholder="Enter Password">
        
        <button type="submit" name="login_btn">Sign In</button>
    </form>

    <div class="footer-links">
        <p style="margin-bottom: 5px; color: #64748b; font-size: 0.9em;">Don't have an account?</p>
        <a href="register.php" class="btn-register">Register Student Account</a>
        <p style="margin-top: 15px; margin-bottom: 0;">
            <a href="forgot_password.php" style="color: #3b82f6; text-decoration: none; font-size: 0.85em;">Forgot password?</a>
        </p>
    </div>
</div>

<div style="margin-bottom: 40px; text-align: center;">
    <p style="color: #64748b; font-size: 0.9em; margin-bottom: 10px;">New Faculty Member?</p>
    <a href="register_supervisor.php" class="btn-supervisor">Register as Supervisor</a>
</div>

<!-- STEP 9: FRONTEND JAVASCRIPT LOGIC -->
<script>
    function setRoleTab(role, element) {
        const tabs = document.querySelectorAll('.role-tab');
        tabs.forEach(tab => tab.classList.remove('active'));
        element.classList.add('active');
        document.getElementById('selectedRoleInput').value = role;
        
        const usernameLabel = document.getElementById('usernameLabel');
        const usernameInput = document.getElementById('usernameInput');
        
        if (role === 'student') {
            usernameLabel.textContent = "Registration Number";
            usernameInput.placeholder = "Enter Registration Number";
        } else {
            usernameLabel.textContent = "Staff Username / ID";
            usernameInput.placeholder = "Enter Username";
        }
    }
</script>

<?php include('includes/footer.php'); ?>