<?php
require_once('config/db_connect.php');

$error = "";

// Allowed list of security questions
$allowed_questions = [
    "What is your high school name?",
    "What was your childhood nickname?",
    "What is your favorite pet's name?",
    "In what town were you born?"
];

if (isset($_POST['register_btn'])) {
    $raw_password = $_POST['password'];
    
    // Clean and sanitize incoming strings
    $full_name = trim(mysqli_real_escape_string($conn, $_POST['full_name']));
    $student_id = trim(mysqli_real_escape_string($conn, $_POST['student_id']));
    $sec_ques = trim($_POST['security_question']);
    $sec_ans = trim(mysqli_real_escape_string($conn, $_POST['security_answer']));

    // ==========================================
    // VALIDATION BLOCK
    // ==========================================
    if (empty($full_name) || empty($student_id) || empty($raw_password) || empty($sec_ques) || empty($sec_ans)) {
        $error = "All fields are required.";
    } 
    // 1. Strict Name Validation: ONLY letters and spaces allowed (No numbers!)
    else if (!preg_match("/^[a-zA-Z]{2,}(?:\s+[a-zA-Z]{2,})+$/", $full_name)) {
        $error = "Please enter your official full name (letters only, at least two names e.g., Grace Mwangi).";
    }
    // 2. Student ID Validation: Ensure it is a valid numeric string
    else if (!preg_match("/^[0-9]{6,10}$/", $student_id)) {
        $error = "Invalid Student Number format. It must be numeric and between 6 to 10 digits.";
    } 
    // 3. Strong Password Validation (Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char)
    else if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/", $raw_password)) {
        $error = "Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.";
    }
    // 4. Security Question Validation: Ensure it matches one of the allowed options
    else if (!in_array($sec_ques, $allowed_questions)) {
        $error = "Please select a valid security question from the list.";
    }
    else {
        $password = password_hash($raw_password, PASSWORD_DEFAULT);
        $student_id_int = intval($student_id);

        mysqli_begin_transaction($conn);
        try {
            // DUPLICATE CHECK: Verify the Student ID does not already exist globally in the 'user' table
            $check_stmt = $conn->prepare("SELECT username FROM user WHERE username = ?");
            $check_stmt->bind_param("s", $student_id);
            $check_stmt->execute();
            $check_stmt->store_result();
            
            if ($check_stmt->num_rows > 0) {
                throw new Exception("Student Number '$student_id' is already registered in our system.");
            }
            $check_stmt->close();

            // 1. Insert into core 'user' table
            $stmt1 = $conn->prepare("INSERT INTO user (user_id, username, password, role, security_question, security_answer) VALUES (?, ?, ?, 'student', ?, ?)");
            $stmt1->bind_param("issss", $student_id_int, $student_id, $password, $sec_ques, $sec_ans);
            $stmt1->execute();
            $stmt1->close();
            
            // 2. Insert into 'student' profile table
            $stmt2 = $conn->prepare("INSERT INTO student (student_id, full_name, reg_no, approval_status) VALUES (?, ?, ?, 'Registered')");
            $stmt2->bind_param("iss", $student_id_int, $full_name, $student_id);
            $stmt2->execute();
            $stmt2->close();

            // 3. Initialize Project Record
            $stmt3 = $conn->prepare("INSERT INTO project (student_id, m1_status) VALUES (?, 'Pending')");
            $stmt3->bind_param("i", $student_id_int);
            $stmt3->execute();
            $stmt3->close();

            // Commit transaction safely
            mysqli_commit($conn);
            
            header("Location: index.php?registered=success&new_student=" . urlencode($student_id));
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<div class="container" style="max-width: 500px; margin: 40px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background: white; font-family: sans-serif; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
    <h2 style="text-align: center; color: #2c3e50; margin-bottom: 5px;">Student Registration</h2>
    <p style="text-align: center; color: #7f8c8d; font-size: 0.9em; margin-bottom: 20px;">Student Project Management System</p>
    
    <?php if($error): ?>
        <p style="color: #e74c3c; background: #fdeaea; padding: 10px; border-radius: 4px; border-left: 4px solid #e74c3c; font-weight: 500;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST">
        <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #34495e;">Full Name</label>
        <input type="text" name="full_name" placeholder="e.g., Grace Mwangi" required 
               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
               style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        
        <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #34495e;">Student Number</label>
        <input type="text" name="student_id" placeholder="e.g., 1030011" required 
               value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>"
               style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        
        <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #34495e;">Password</label>
        <input type="password" name="password" placeholder="Min 8 chars (Upper, Lower, Number, Symbol)" required 
               style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        
        <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #34495e;">Security Question</label>
        <select name="security_question" required style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: white;">
            <option value="">-- Choose a security question --</option>
            <?php foreach ($allowed_questions as $q): ?>
                <option value="<?php echo htmlspecialchars($q); ?>" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] == $q) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($q); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #34495e;">Your Answer</label>
        <input type="text" name="security_answer" placeholder="Enter your recovery answer" required 
               value="<?php echo isset($_POST['security_answer']) ? htmlspecialchars($_POST['security_answer']) : ''; ?>"
               style="width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        
        <button type="submit" name="register_btn" 
                style="width: 100%; padding: 12px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; transition: background 0.2s;">
            Register Student Account
        </button>
    </form>
</div>