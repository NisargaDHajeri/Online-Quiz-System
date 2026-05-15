<?php 
include '../php/database.php'; 
header('Content-Type: application/json'); 
$res = $conn->query('SELECT id,title,description FROM quizzes ORDER BY id DESC'); 
echo json_encode($res->fetch_all(MYSQLI_ASSOC)); 
?>
