<?php
header('Content-Type: application/json');
include __DIR__ . '/../php/database.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$user_id = !empty($data['user_id']) ? intval($data['user_id']) : null;
$user_name = !empty($data['user']) ? trim($data['user']) : 'Anonymous';
$quiz_id = intval($data['quiz_id'] ?? 0);
$total_questions = intval($data['total_questions'] ?? 0);
$correct_answers = intval($data['correct_answers'] ?? 0);

if ($quiz_id <= 0 || $total_questions <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing fields']);
    exit;
}

$percentage = round(($correct_answers / $total_questions) * 100, 2);

// FIXED QUERY
$stmt = $conn->prepare("
    INSERT INTO scores 
    (user_id, user_name, quiz_id, total_questions, correct_answers, percentage)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    'isiiid',
    $user_id,
    $user_name,
    $quiz_id,
    $total_questions,
    $correct_answers,
    $percentage
);

$ok = $stmt->execute();

echo json_encode(['ok' => $ok, 'error' => $ok ? null : $stmt->error]);
