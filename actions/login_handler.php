<?php
// Step 1: Start session
session_start(); 

// Step 2: Connect to database
require_once('../config/db_connect.php'); 

// Step 3: Check if the login form was submitted
if (isset($_POST['login_btn'])) {

    try {
        // Collect input and clean whitespace
        $username = trim($_POST['username']); 
        $password = $_POST['password']; 

        // Step 4: Fetch user using prepared statement
        $stmt = $conn->prepare("SELECT user_id, username, password, role FROM user WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute(); 
        $result = $stmt->get_result(); 

        // Step 5: Check if user exists
        if ($row = $result->fetch_assoc()) {

            // Step 6: Verify password hash
            if (password_verify($password, $row['password'])) {
                
                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                // Normalize role string (lowercase and remove whitespace)
                $role = strtolower(trim($row['role']));

                // Step 7: Store user details in session
                $_SESSION['user_id']  = $row['user_id']; 
                $_SESSION['role']     = $role; 
                $_SESSION['username'] = $row['username']; 

                // Step 8: Safe Role-Based Mapping
                switch ($role) {
                    case 'student':
                        header("Location: ../dashboards/student.php");
                        exit();

                    case 'supervisor':
                        header("Location: ../dashboards/supervisor.php");
                        exit();

                    case 'coordinator':
                    case 'department coordinator':
                    case 'hod':
                        header("Location: ../dashboards/coordinator.php");
                        exit();

                    default:
                        $_SESSION['error'] = "Invalid user role assigned.";
                        header("Location: ../index.php");
                        exit();
                }

            } else {
                // Password incorrect
                $_SESSION['error'] = "Invalid username or password."; 
            }
        } else {
            // User not found
            $_SESSION['error'] = "Invalid username or password."; 
        }
        
        if (isset($stmt)) {
            $stmt->close();
        }

    } catch (Exception $e) {
        // Catch any database connectivity or query crashes gracefully
        $_SESSION['error'] = "System error: " . $e->getMessage();
    }
}

// Step 9: Fallback redirect if login fails, exception occurs, or script accessed directly
header("Location: ../index.php"); 
exit();
?>