<?php
$host = "localhost"; // Database host
$username = "root";  // Database username
$password = "";      // Database password
$dbname = "project_management"; // Name of the database

// Connect to MySQL server
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Database created or already exists.<br>";
} else {
    die("Error creating database: " . $conn->error);
}

// Use the database
$conn->select_db($dbname);

// Create `users` table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('team_leader', 'developer', 'observer') DEFAULT 'observer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "Table `users` created or already exists.<br>";
} else {
    die("Error creating table: " . $conn->error);
}

// Close the connection
$conn->close();

echo "Setup completed successfully.";
?>
