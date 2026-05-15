<?php
$servername = "localhost";
$username = "root";
$password = "Mysql@123";
$dbname = "quiz_db";

// Connect to MySQL (without selecting DB first)
$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create DB if it does not exist
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

// Now select DB
$conn->select_db($dbname);

// Create tables
$schema = <<<SQL
CREATE TABLE IF NOT EXISTS admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL
);
CREATE TABLE IF NOT EXISTS quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  question TEXT NOT NULL,
  option1 VARCHAR(255) NOT NULL,
  option2 VARCHAR(255) NOT NULL,
  option3 VARCHAR(255) DEFAULT NULL,
  option4 VARCHAR(255) DEFAULT NULL,
  correct_option TINYINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_name VARCHAR(150) NOT NULL,
  quiz_id INT NOT NULL,
  total_questions INT NOT NULL,
  correct_answers INT NOT NULL,
  percentage FLOAT NOT NULL,
  taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);
SQL;

$conn->multi_query($schema);
while ($conn->more_results() && $conn->next_result()) {}

// Ensure admin (ADMIN / 1234)
$adminUser = 'ADMIN';
$adminPassPlain = '1234';
$hash = password_hash($adminPassPlain, PASSWORD_DEFAULT);
$conn->query("INSERT IGNORE INTO admin (username, password) VALUES ('$adminUser', '$hash')");
?>
