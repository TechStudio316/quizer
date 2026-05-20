<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_user();

$user = current_user();
$attempt = get_active_attempt($pdo, (int) $user['id']);

if (!$attempt || $attempt['status'] !== 'in_progress') {
    redirect('/result.php');
}

$answers = $_POST['answers'] ?? [];
$optionStmt = $pdo->prepare(
    'UPDATE answers
     SET selected_option = ?, answer_text = NULL, answered_at = NOW()
     WHERE attempt_id = ? AND question_id = ?'
);

foreach ($answers as $questionId => $selected) {
    if (in_array($selected, ['A', 'B', 'C', 'D'], true)) {
        $optionStmt->execute([$selected, (int) $attempt['id'], (int) $questionId]);
    }
}

$textAnswers = $_POST['text_answers'] ?? [];
$textStmt = $pdo->prepare(
    'UPDATE answers
     SET answer_text = ?, selected_option = NULL, answered_at = NOW()
     WHERE attempt_id = ? AND question_id = ?'
);

foreach ($textAnswers as $questionId => $answerText) {
    $answerText = trim((string) $answerText);
    if ($answerText !== '') {
        $textStmt->execute([$answerText, (int) $attempt['id'], (int) $questionId]);
    }
}

$score = score_attempt($pdo, (int) $attempt['id']);
$status = attempt_remaining_seconds($attempt) <= 0 ? 'expired' : 'submitted';
$stmt = $pdo->prepare('UPDATE attempts SET status = ?, submitted_at = NOW(), score = ?, total_marks = ? WHERE id = ?');
$stmt->execute([$status, $score['score'], $score['total'], (int) $attempt['id']]);

redirect('/result.php');
