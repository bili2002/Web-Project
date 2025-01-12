<?php
// Enable error reporting and logging to the terminal
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php://stderr');

// Include the database connection
include 'includes/db.php';

// Log when the script is accessed
error_log("register.php accessed");

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("Received POST request with data: " . json_encode($_POST));

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = trim($_POST['role']);

    // Check for empty fields
    if (empty($username) || empty($password) || empty($role)) {
        error_log("Registration failed: Missing required fields");
        die("All fields are required.");
    }

    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    error_log("Password hashed for username: $username");

    // Prepare an SQL statement to insert the new user
    $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("sss", $username, $hashed_password, $role);

        // Execute the statement
        if ($stmt->execute()) {
            error_log("Registration successful for username: $username");
            echo "Registration successful! <a href='index.php'>Go to login</a>";
        } else {
            error_log("Error executing query: " . $stmt->error);
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    } else {
        error_log("Failed to prepare the statement: " . $conn->error);
        echo "Failed to prepare the statement.";
    }
}

// Close the database connection
$conn->close();
error_log("Database connection closed");
?>
