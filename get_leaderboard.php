<?php
// api/get_leaderboard.php
header('Content-Type: application/json');
include __DIR__ . '/../php/database.php';

// Query top attempts: highest percentage first, tie-breaker recent
$sql = "SELECT id, user_id, user_name AS user, quiz_id, total_questions, correct_answers, percentage, taken_at
        FROM scores
        ORDER BY percentage DESC, taken_at DESC
        LIMIT 100";

$res = $conn->query($sql);
$out = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
}
echo json_encode($out);
