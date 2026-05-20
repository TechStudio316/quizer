<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_user();

$user = current_user();
$attempt = get_attempt($pdo, (int) $user['id']);

$pageTitle = 'Quiz Result';
require_once __DIR__ . '/includes/header.php';
?>
<div class="panel p-4 p-md-5 text-center mx-auto" style="max-width: 720px;">
    <p class="text-uppercase text-muted fw-semibold mb-2">QUIZER Result</p>
    <h1 class="h2 mb-3"><?= e($user['full_name']) ?></h1>
    <?php if (!$attempt): ?>
        <p class="lead">You have not started a quiz yet.</p>
        <a class="btn btn-primary" href="<?= e(base_url('/candidate.php')) ?>">Back to Quiz Area</a>
    <?php elseif ($attempt['status'] === 'in_progress'): ?>
        <p class="lead">Your quiz is still in progress.</p>
        <a class="btn btn-primary" href="<?= e(base_url('/candidate.php')) ?>">Continue Quiz</a>
    <?php else: ?>
        <?php $total = (int) ($attempt['total_marks'] ?: $attempt['total_questions']); ?>
        <?php $percent = $total > 0 ? round(($attempt['score'] / $total) * 100, 1) : 0; ?>
        <div class="display-4 fw-bold text-success mb-2"><?= (int) $attempt['score'] ?>/<?= $total ?></div>
        <p class="lead mb-4"><?= $percent ?>% · <?= e(ucwords(str_replace('_', ' ', $attempt['status']))) ?></p>
        <div class="row g-3 text-start">
            <div class="col-md-6"><div class="metric panel p-3"><strong>Quiz</strong><br><?= e($attempt['quiz_type']) ?></div></div>
            <div class="col-md-6"><div class="metric panel p-3"><strong>Zone</strong><br><?= e($user['zone']) ?></div></div>
            <div class="col-md-6"><div class="metric panel p-3"><strong>Started</strong><br><?= e($attempt['started_at']) ?></div></div>
            <div class="col-md-6"><div class="metric panel p-3"><strong>Submitted</strong><br><?= e($attempt['submitted_at']) ?></div></div>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
