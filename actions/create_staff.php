// In actions/create_staff.php

// 1. Collect form data sent via POST
$username = $_POST['username'];   // Staff username (e.g., registration number or login name)
$role = $_POST['role'];           // Staff role: 'supervisor', 'panel', or 'hod'

// 2. Securely hash the password before saving
// password_hash() uses a strong algorithm (default is bcrypt) to protect passwords.
// This means the actual password is never stored in plain text in the database.
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

// 3. Prepare an SQL statement to insert the new staff record into the 'user' table
// Using placeholders (?) prevents SQL injection attacks.
$stmt = $conn->prepare("INSERT INTO user (username, password, role) VALUES (?, ?, ?)");

// 4. Bind the actual values to the placeholders
// "sss" means all three parameters are strings: username, password, role.
$stmt->bind_param("sss", $username, $password, $role);

// 5. Execute the query
// This inserts the new staff member into the database with their username, hashed password, and role.
$stmt->execute();
