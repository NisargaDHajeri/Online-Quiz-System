<?php
session_start();
include __DIR__ . '/../php/database.php'; // make sure path is correct
header('Content-Type: application/json');

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

if ($quiz_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT id, question, option1, option2, option3, option4, correct_option FROM questions WHERE quiz_id = ?");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$result = $stmt->get_result();

$questions = [];
while ($row = $result->fetch_assoc()) {
    $row['option1'] = $row['option1'] ?? '';
    $row['option2'] = $row['option2'] ?? '';
    $row['option3'] = $row['option3'] ?? '';
    $row['option4'] = $row['option4'] ?? '';
    $questions[] = $row;
}

echo json_encode($questions);
