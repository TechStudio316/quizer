<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_user();

$user = current_user();
$sessionId = (int) ($_GET['session_id'] ?? 0);
$session = $sessionId ? selected_quiz_session($pdo, $sessionId) : active_quiz_session($pdo, $user['quiz_type']);

if (!$session || $session['quiz_type'] !== $user['quiz_type']) {
    flash('warning', 'No published quiz session is available for your category.');
    redirect('/candidate.php');
}

$attempt = get_session_attempt($pdo, (int) $user['id'], (int) $session['id']);
$window = quiz_window_status($session);
$attemptCount = user_session_attempt_count($pdo, (int) $user['id'], (int) $session['id']);

if (!$window['open']) {
    flash('warning', $window['message']);
    redirect('/candidate.php');
}

if ($attempt && $attempt['status'] !== 'in_progress' && !isset($_GET['new'])) {
    redirect('/result.php');
}

if ($attempt && $attempt['status'] !== 'in_progress' && $attemptCount >= (int) $session['max_attempts']) {
    flash('warning', 'You have used all allowed attempts for this quiz.');
    redirect('/result.php');
}

if (isset($_GET['new']) && $attempt && $attempt['status'] !== 'in_progress') {
    $attempt = null;
}

if (!$attempt) {
    $duration = (int) $session['duration_minutes'];
    $limit = (int) $session['question_count'];
    $stmt = $pdo->prepare("SELECT id FROM questions WHERE quiz_session_id = ? AND is_active = 1 ORDER BY id LIMIT {$limit}");
    $stmt->execute([(int) $session['id']]);
    $questionIds = array_column($stmt->fetchAll(), 'id');

    if (!$questionIds) {
        flash('warning', 'No active questions are available for your quiz yet.');
        redirect('/candidate.php');
    }

    $started = date('Y-m-d H:i:s');
    $ends = date('Y-m-d H:i:s', time() + ($duration * 60));
    $stmt = $pdo->prepare('INSERT INTO attempts (quiz_session_id, user_id, quiz_type, started_at, ends_at, total_questions, total_marks) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([(int) $session['id'], (int) $user['id'], $user['quiz_type'], $started, $ends, count($questionIds), (int) $session['total_marks']]);
    $attemptId = (int) $pdo->lastInsertId();

    $answerStmt = $pdo->prepare('INSERT INTO answers (attempt_id, question_id) VALUES (?, ?)');
    foreach ($questionIds as $questionId) {
        $answerStmt->execute([$attemptId, (int) $questionId]);
    }

    $attempt = get_session_attempt($pdo, (int) $user['id'], (int) $session['id']);
}

if (attempt_remaining_seconds($attempt) <= 0) {
    $score = score_attempt($pdo, (int) $attempt['id']);
    $stmt = $pdo->prepare('UPDATE attempts SET status = "expired", submitted_at = NOW(), score = ?, total_marks = ? WHERE id = ?');
    $stmt->execute([$score['score'], $score['total'], (int) $attempt['id']]);
    redirect('/result.php');
}

$stmt = $pdo->prepare(
    'SELECT q.*, a.selected_option, a.answer_text
     FROM answers a
     INNER JOIN questions q ON q.id = a.question_id
     WHERE a.attempt_id = ?
     ORDER BY q.id'
);
$stmt->execute([(int) $attempt['id']]);
$questions = $stmt->fetchAll();

$pageTitle = 'Take Quiz';
require_once __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($user['quiz_type']) ?> Quiz</h1>
        <p class="text-muted mb-0"><?= e($session['session_name']) ?> · <?= e($user['full_name']) ?> · <?= e($user['zone']) ?></p>
    </div>
    <div class="panel px-3 py-2">
        <span class="text-muted me-2">Time left</span>
        <strong class="timer fs-4" id="timer">--:--</strong>
    </div>
</div>

<form method="post" action="<?= e(base_url('/submit_quiz.php')) ?>" id="quizForm">
    <?php foreach ($questions as $index => $question): ?>
        <section class="question-card bg-white p-3 p-md-4 mb-3">
            <h2 class="h5 mb-3"><?= ($index + 1) ?>. <?= e($question['question_text']) ?></h2>
            <?php if (($question['question_type'] ?? 'multiple_choice') === 'fill_gap'): ?>
                <label class="form-label">Type your answer</label>
                <input class="form-control form-control-lg"
                       name="text_answers[<?= (int) $question['id'] ?>]"
                       data-question-id="<?= (int) $question['id'] ?>"
                       value="<?= e($question['answer_text'] ?? '') ?>"
                       placeholder="Enter answer">
            <?php else: ?>
                <div class="row g-2">
                    <?php foreach (['A', 'B', 'C', 'D'] as $letter): ?>
                        <?php $field = 'option_' . strtolower($letter); ?>
                        <div class="col-md-6">
                            <div class="form-check p-0">
                                <input class="form-check-input visually-hidden" type="radio"
                                       name="answers[<?= (int) $question['id'] ?>]"
                                       id="q<?= (int) $question['id'] ?><?= $letter ?>"
                                       data-question-id="<?= (int) $question['id'] ?>"
                                       value="<?= $letter ?>"
                                    <?= $question['selected_option'] === $letter ? 'checked' : '' ?>>
                                <label class="choice-label" for="q<?= (int) $question['id'] ?><?= $letter ?>">
                                    <strong><?= $letter ?>.</strong> <?= e($question[$field]) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    <button class="btn btn-success btn-lg w-100" type="submit">Submit Quiz</button>
</form>
<script>
    startQuizTimer(<?= attempt_remaining_seconds($attempt) ?>, 'timer', 'quizForm');
    enableAnswerAutosave('quizForm');
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
