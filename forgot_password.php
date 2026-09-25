<?php
// LINE 1: Directs the engine to initialize or resume a state-tracking session
session_start();

// LINE 2: Connects the global database abstraction hook ($conn)
require_once('config/db_connect.php');

// LINE 3: Instantiates fallback string variables
$message = "";
$step = 1;
$fetched_username = "";
$fetched_question = "";

// =========================================================================
// STEP 1: HANDLE USERNAME LOOKUP
// =========================================================================
if (isset($_POST['lookup_btn'])) {
    $username = trim($_POST['username']);
    
    $stmt = $conn->prepare("SELECT username, security_question FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Username found, switch to Step 2 and store username in session
        $_SESSION['reset_username'] = $row['username'];
        $step = 2;
    } else {
        // Vague message for security (prevents user enumeration)
        $message = "If the username exists, you will be prompted for your security question.";
        // To make debugging easier during development, you can change this to "Username not found."
    }
}

// =========================================================================
// STEP 2: HANDLE SECURITY ANSWER VERification
// =========================================================================
if (isset($_POST['verify_btn'])) {
    $username = $_SESSION['reset_username'] ?? '';
    $answer = trim($_POST['security_answer']); 

    $stmt = $conn->prepare("SELECT user_id, security_answer FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Case-insensitive comparison of the security answer
        if (strcasecmp($answer, $row['security_answer']) == 0) {
            $_SESSION['reset_uid'] = $row['user_id'];
            // Clear temporary lookup session variable
            unset($_SESSION['reset_username']);
            
            header("Location: reset_password.php");
            exit();
        } else {
            $message = "Invalid security answer. Please try again.";
            $step = 2; // Stay on step 2
        }
    } else {
        $message = "Session expired. Please start over.";
        $step = 1;
    }
}

// If we are on step 2, fetch the security question to display it on screen
if ($step == 2 && isset($_SESSION['reset_username'])) {
    $q_stmt = $conn->prepare("SELECT security_question FROM user WHERE username = ?");
    $q_stmt->bind_param("s", $_SESSION['reset_username']);
    $q_stmt->execute();
    $q_res = $q_stmt->get_result();
    if ($q_row = $q_res->fetch_assoc()) {
        $fetched_question = $q_row['security_question'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Recover Account | SPMS</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; }
        .recovery-box { 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
            max-width: 400px; 
            margin: 100px auto; 
        }
        input[type="text"] { 
            width: 100%; 
            padding: 12px; 
            margin: 10px 0; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            box-sizing: border-box; 
        }
        button { 
            width: 100%; 
            padding: 12px; 
            background: #2c3e50; 
            color: white; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: bold;
        }
        button:hover { background: #34495e; }
        .back-link { display: block; text-align: center; margin-top: 15px; color: #3498db; text-decoration: none; font-size: 0.9em; }
    </style>
</head>
<body>

    <div class="recovery-box">
        <h2 style="margin-top:0;">Account Recovery</h2>
        <p style="font-size: 0.9em; color: #666;">
            <?php echo ($step == 1) ? "Enter your username to locate your account." : "Answer your security question to verify your identity."; ?>
        </p>
        <hr style="border:0; border-top:1px solid #eee; margin-bottom: 20px;">

        <?php if($message): ?>
            <div style="color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-size: 0.85em;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <!-- STEP 1 FORM: Enter Username -->
            <form method="POST">
                <label style="font-weight:bold; font-size:0.9em;">Username / Staff ID / Reg No.</label>
                <input type="text" name="username" placeholder="Enter your username" required>
                
                <button type="submit" name="lookup_btn">Next &rarr;</button>
            </form>
        <?php else: ?>
            <!-- STEP 2 FORM: Answer Dynamic Security Question -->
            <form method="POST">
                <p style="font-size: 0.85em; color: #2c3e50; margin-bottom: 5px;">Recovering account for: <strong><?php echo htmlspecialchars($_SESSION['reset_username']); ?></strong></p>
                
                <label style="font-weight:bold; font-size:0.9em; color: #333; display: block; margin-top: 10px;">
                    <?php echo htmlspecialchars($fetched_question); ?>
                </label>
                <input type="text" name="security_answer" placeholder="Your answer" required autofocus>
                
                <button type="submit" name="verify_btn">Verify Identity</button>
            </form>
        <?php endif; ?>

        <a href="index.php" class="back-link">&larr; Back to Login</a>
    </div>

</body>
</html>