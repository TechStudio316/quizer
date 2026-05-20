<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $duration = max(1, (int) ($_POST['quiz_duration_minutes'] ?? 30));
    upsert_setting($pdo, 'quiz_duration_minutes', (string) $duration);
    flash('success', 'Quiz duration updated.');
    redirect('/admin/dashboard.php');
}

$metrics = [
    'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'questions' => (int) $pdo->query('SELECT COUNT(*) FROM questions WHERE is_active = 1')->fetchColumn(),
    'in_progress' => (int) $pdo->query('SELECT COUNT(*) FROM attempts WHERE status = "in_progress"')->fetchColumn(),
    'submitted' => (int) $pdo->query('SELECT COUNT(*) FROM attempts WHERE status IN ("submitted", "expired")')->fetchColumn(),
];

$stmt = $pdo->query(
    'SELECT u.full_name, u.zone, u.quiz_type, a.status, a.score, a.total_questions, a.total_marks, a.started_at, a.submitted_at
     FROM attempts a
     INNER JOIN users u ON u.id = a.user_id
     ORDER BY a.started_at DESC
     LIMIT 10'
);
$recent = $stmt->fetchAll();
$duration = app_setting($pdo, 'quiz_duration_minutes', '30');

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Admin Dashboard</h1>
        <p class="text-muted mb-0">Monitor candidates, quiz progress, and recent submissions.</p>
    </div>
    <a class="btn btn-success" href="<?= e(base_url('/admin/questions.php')) ?>">Manage Questions</a>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($metrics as $label => $value): ?>
        <div class="col-md-3">
            <div class="metric panel p-3">
                <div class="text-muted text-uppercase small"><?= e(str_replace('_', ' ', $label)) ?></div>
                <div class="fs-2 fw-bold"><?= $value ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="panel p-3 p-md-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Recent Activity</h2>
                <a href="<?= e(base_url('/admin/results.php')) ?>" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Name</th><th>Quiz</th><th>Status</th><th>Score</th><th>Started</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><?= e($row['full_name']) ?><br><span class="text-muted small"><?= e($row['zone']) ?></span></td>
                            <td><?= e($row['quiz_type']) ?></td>
                            <td><span class="badge text-bg-<?= $row['status'] === 'in_progress' ? 'warning' : 'success' ?>"><?= e($row['status']) ?></span></td>
                            <td><?= (int) $row['score'] ?>/<?= (int) ($row['total_marks'] ?: $row['total_questions']) ?></td>
                            <td><?= e($row['started_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No attempts yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel p-3 p-md-4">
            <h2 class="h5 mb-3">Quiz Settings</h2>
            <form method="post">
                <label class="form-label">Duration in minutes</label>
                <input class="form-control mb-3" type="number" min="1" name="quiz_duration_minutes" value="<?= e($duration) ?>">
                <button class="btn btn-primary w-100" type="submit">Save Settings</button>
            </form>
        </div>
    </div>
</div>
<script>refreshEvery(20);</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
