<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_user();

header('Content-Type: application/json');

$user = current_user();
$attempt = get_active_attempt($pdo, (int) $user['id']);
$questionId = (int) ($_POST['question_id'] ?? 0);
$selected = $_POST['selected_option'] ?? '';
$answerText = trim($_POST['answer_text'] ?? '');

if (!$attempt || $attempt['status'] !== 'in_progress' || attempt_remaining_seconds($attempt) <= 0) {
    http_response_code(409);
    echo json_encode(['ok' => false]);
    exit;
}

if (!$questionId || (!in_array($selected, ['A', 'B', 'C', 'D'], true) && $answerText === '')) {
    http_response_code(422);
    echo json_encode(['ok' => false]);
    exit;
}

if ($answerText !== '') {
    $stmt = $pdo->prepare(
        'UPDATE answers
         SET answer_text = ?, selected_option = NULL, answered_at = NOW()
         WHERE attempt_id = ? AND question_id = ?'
    );
    $stmt->execute([$answerText, (int) $attempt['id'], $questionId]);
} else {
    $stmt = $pdo->prepare(
        'UPDATE answers
         SET selected_option = ?, answer_text = NULL, answered_at = NOW()
         WHERE attempt_id = ? AND question_id = ?'
    );
    $stmt->execute([$selected, (int) $attempt['id'], $questionId]);
}

echo json_encode(['ok' => true]);
