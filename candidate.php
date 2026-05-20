<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_user();

$user = current_user();
$session = active_quiz_session($pdo, $user['quiz_type']);
$attempt = $session ? get_session_attempt($pdo, (int) $user['id'], (int) $session['id']) : null;
$window = $session ? quiz_window_status($session) : ['open' => false, 'message' => 'No published quiz session is currently available for your category.'];
$attemptCount = $session ? user_session_attempt_count($pdo, (int) $user['id'], (int) $session['id']) : 0;

$stmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE quiz_session_id = ? AND is_active = 1');
$stmt->execute([$session ? (int) $session['id'] : 0]);
$questionCount = (int) $stmt->fetchColumn();

$availableCount = $session ? min($questionCount, (int) $session['question_count']) : 0;
$hasAttemptsLeft = $session && $attemptCount < (int) $session['max_attempts'];
$canStart = $window['open'] && $availableCount > 0 && (!$attempt || $attempt['status'] === 'in_progress' || $hasAttemptsLeft);
$startUrl = $session
    ? ($attempt && $attempt['status'] === 'in_progress' ? base_url('/quiz.php?session_id=' . (int) $session['id']) : base_url('/quiz.php?new=1&session_id=' . (int) $session['id']))
    : '#';
$buttonText = $attempt && $attempt['status'] === 'in_progress' ? 'Continue Quiz' : 'Start Quiz';
$statusLabel = 'No Published Quiz';
if ($attempt) {
    $statusLabel = ucwords(str_replace('_', ' ', $attempt['status']));
} elseif ($session && $window['open']) {
    $statusLabel = 'Available';
} elseif ($session && (int) $session['is_published']) {
    $statusLabel = 'Published';
}

$pageTitle = 'Candidate Quiz Area';
require_once __DIR__ . '/includes/header.php';
?>
<div class="row g-4 align-items-stretch">
    <div class="col-lg-4">
        <div class="panel p-4 h-100">
            <p class="text-uppercase text-muted fw-semibold mb-2">Candidate</p>
            <h1 class="h3 mb-3"><?= e($user['full_name']) ?></h1>
            <div class="mb-2"><strong>Zone:</strong> <?= e($user['zone']) ?></div>
            <div class="mb-2"><strong>Email:</strong> <?= e($user['email']) ?></div>
            <div><strong>Phone:</strong> <?= e($user['phone']) ?></div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel p-4 h-100">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <p class="text-uppercase text-muted fw-semibold mb-2">Available Quiz</p>
                    <h2 class="h3 mb-1"><?= e($user['quiz_type']) ?> Quiz</h2>
                    <p class="text-muted mb-0"><?= $session ? e($session['session_name']) : 'Only your registered quiz category is shown here.' ?></p>
                </div>
                <span class="badge text-bg-primary fs-6"><?= $session ? (int) $session['duration_minutes'] : 0 ?> minutes</span>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="metric panel p-3">
                        <div class="text-muted small text-uppercase">Questions</div>
                        <div class="fs-3 fw-bold"><?= $availableCount ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric panel p-3">
                        <div class="text-muted small text-uppercase">Status</div>
                        <div class="fs-5 fw-bold"><?= e($statusLabel) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric panel p-3">
                        <div class="text-muted small text-uppercase">Score</div>
                        <div class="fs-3 fw-bold"><?= $attempt ? (int) $attempt['score'] . '/' . (int) ($attempt['total_marks'] ?: $attempt['total_questions']) : '-' ?></div>
                    </div>
                </div>
            </div>

            <div class="mb-3 text-muted">
                Opens: <?= e($session['live_at'] ?? 'Not set') ?> · Expires: <?= e($session['expires_at'] ?? 'Not set') ?> · Attempts: <?= $attemptCount ?>/<?= $session ? (int) $session['max_attempts'] : 0 ?>
            </div>

            <?php if (!$window['open']): ?>
                <div class="alert alert-warning mb-0"><?= e($window['message']) ?></div>
            <?php elseif ($attempt && $attempt['status'] !== 'in_progress' && !$hasAttemptsLeft): ?>
                <a class="btn btn-success btn-lg" href="<?= e(base_url('/result.php')) ?>">View Result</a>
            <?php elseif ($canStart): ?>
                <a class="btn btn-primary btn-lg" href="<?= e($startUrl) ?>"><?= e($buttonText) ?></a>
            <?php else: ?>
                <div class="alert alert-warning mb-0">No active <?= e($user['quiz_type']) ?> questions are available yet, or your allowed attempts are finished.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
