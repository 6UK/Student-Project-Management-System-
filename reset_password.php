<?php
require_once('config/db_connect.php');
session_start();

$message = "";

// --- 1. SESSION LOGIC ---
// Check if user is coming from forgot_password.php (reset_uid) 
// OR is already logged in (user_id)
if (isset($_SESSION['reset_uid'])) {
    $uid = $_SESSION['reset_uid'];
} elseif (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
} else {
    // If neither session exists, they shouldn't be here
    header("Location: index.php"); 
    exit();
}

// --- 2. PROCESSING THE FORM ---
if (isset($_POST['reset_pwd_btn'])) {
    $new_pass = $_POST['new_password'];
    $conf_pass = $_POST['confirm_password'];

    if ($new_pass !== $conf_pass) {
        $message = "<div style='color:red; margin-bottom:15px;'>❌ Passwords do not match!</div>";
    } elseif (strlen($new_pass) < 6) {
        $message = "<div style='color:red; margin-bottom:15px;'>❌ Password must be at least 6 characters.</div>";
    } else {
        // Securely hash the new password
        $hashed_pwd = password_hash($new_pass, PASSWORD_DEFAULT);

        $query = "UPDATE user SET password = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $hashed_pwd, $uid);

        if ($stmt->execute()) {
            $message = "<div style='color:green; margin-bottom:15px;'>✅ Password updated successfully! Redirecting...</div>";
            
            // Clear the recovery session so they can't reuse it
            unset($_SESSION['reset_uid']); 
            
            // Redirect to login/index after 3 seconds
            header("refresh:3;url=index.php");
        } else {
            $message = "<div style='color:red; margin-bottom:15px;'>❌ Error updating database.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | SPMS</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .reset-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { color: #2c3e50; margin-top: 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9em; color: #34495e; }
        input { width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #3498db; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        button:hover { background: #2980b9; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #7f8c8d; text-decoration: none; font-size: 0.9em; }
    </style>
</head>
<body>

<div class="reset-box">
    <h2>Reset Password</h2>
    <p style="color: #666; font-size: 0.9em;">Enter your new secure password below.</p>
    <hr style="border: 0; border-top: 1px solid #eee; margin-bottom: 20px;">
    
    <?php echo $message; ?>

    <form method="POST">
        <label>New Password</label>
        <input type="password" name="new_password" required placeholder="Min. 6 characters">
        
        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required placeholder="Repeat new password">
        
        <button type="submit" name="reset_pwd_btn">Update Password</button>
    </form>

    <a href="<?php echo isset($_SESSION['user_id']) ? 'dashboard.php' : 'index.php'; ?>" class="back-link">
        &larr; Back to <?php echo isset($_SESSION['user_id']) ? 'Dashboard' : 'Login'; ?>
    </a>
</div>

</body>
</html>