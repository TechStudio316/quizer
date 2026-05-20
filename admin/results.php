<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$quizFilter = $_GET['quiz_type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$where = [];
$params = [];

if (in_array($quizFilter, quiz_options(), true)) {
    $where[] = 'u.quiz_type = ?';
    $params[] = $quizFilter;
}

if (in_array($statusFilter, ['in_progress', 'submitted', 'expired'], true)) {
    $where[] = 'a.status = ?';
    $params[] = $statusFilter;
}

$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare(
    "SELECT u.full_name, u.zone, u.email, u.phone, u.quiz_type,
            a.id AS attempt_id, a.status, a.score, a.total_questions, a.total_marks, a.started_at, a.ends_at, a.submitted_at,
            COUNT(ans.selected_option) AS answered_count
     FROM attempts a
     INNER JOIN users u ON u.id = a.user_id
     LEFT JOIN answers ans ON ans.attempt_id = a.id
     {$sqlWhere}
     GROUP BY a.id, u.id
     ORDER BY a.started_at DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Results and Progress';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Results and Progress</h1>
        <p class="text-muted mb-0">This page refreshes automatically so admins can track candidates during the quiz.</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(base_url('/admin/export_results.php')) ?>">Export CSV</a>
</div>

<div class="panel p-3 p-md-4 mb-4">
    <form class="row g-3 align-items-end" method="get">
        <div class="col-md-4">
            <label class="form-label">Quiz type</label>
            <select class="form-select" name="quiz_type">
                <option value="">All</option>
                <?php foreach (quiz_options() as $option): ?>
                    <option value="<?= e($option) ?>" <?= $quizFilter === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="">All</option>
                <?php foreach (['in_progress', 'submitted', 'expired'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <button class="btn btn-primary w-100" type="submit">Apply Filters</button>
        </div>
    </form>
</div>

<div class="panel p-3 p-md-4">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Candidate</th>
                <th>Quiz</th>
                <th>Status</th>
                <th>Progress</th>
                <th>Score</th>
                <th>Time</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $progress = $row['total_questions'] > 0 ? round(((int) $row['answered_count'] / (int) $row['total_questions']) * 100) : 0;
                $remaining = $row['status'] === 'in_progress' ? max(0, strtotime($row['ends_at']) - time()) : 0;
                ?>
                <tr>
                    <td>
                        <strong><?= e($row['full_name']) ?></strong><br>
                        <span class="text-muted small"><?= e($row['zone']) ?> · <?= e($row['email']) ?> · <?= e($row['phone']) ?></span>
                    </td>
                    <td><?= e($row['quiz_type']) ?></td>
                    <td><span class="badge text-bg-<?= $row['status'] === 'in_progress' ? 'warning' : 'success' ?>"><?= e(ucwords(str_replace('_', ' ', $row['status']))) ?></span></td>
                    <td style="min-width: 180px;">
                        <div class="progress" role="progressbar" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: <?= $progress ?>%"><?= $progress ?>%</div>
                        </div>
                        <span class="text-muted small"><?= (int) $row['answered_count'] ?>/<?= (int) $row['total_questions'] ?> answered</span>
                    </td>
                    <td><?= (int) $row['score'] ?>/<?= (int) ($row['total_marks'] ?: $row['total_questions']) ?></td>
                    <td>
                        <?php if ($row['status'] === 'in_progress'): ?>
                            <?= floor($remaining / 60) ?>m <?= $remaining % 60 ?>s left
                        <?php else: ?>
                            <?= e($row['submitted_at']) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No results found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>refreshEvery(15);</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
