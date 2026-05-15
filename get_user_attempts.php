<?php
// api/get_user_attempts.php
header('Content-Type: application/json');
include __DIR__ . '/../php/database.php';

// required param: user
$user = isset($_GET['user']) ? trim($_GET['user']) : '';
if ($user === '') {
    echo json_encode(['ok'=>false,'error'=>'user required']);
    exit;
}

// fetch last 12 attempts for this user, order by taken_at DESC
$stmt = $conn->prepare("SELECT quiz_id, user_name, percentage, total_questions, correct_answers, taken_at FROM scores WHERE user_name = ? ORDER BY taken_at DESC LIMIT 12");
$stmt->bind_param('s', $user);
$stmt->execute();
$res = $stmt->get_result();
$attempts = [];
while($r = $res->fetch_assoc()){
    $attempts[] = $r;
}

// return in reverse chronological order (old -> new) for chart readability
$attempts = array_reverse($attempts);

echo json_encode(['ok'=>true,'attempts'=>$attempts]);
