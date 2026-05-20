<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();
ensure_quiz_session_columns($pdo);

$editQuestion = null;

function admin_session_redirect(int $sessionId = 0): void
{
    $path = $sessionId > 0 ? '/admin/questions.php?session_id=' . $sessionId : '/admin/questions.php';
    redirect($path);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_session') {
        $sessionName = trim($_POST['session_name'] ?? '');
        $quizType = $_POST['quiz_type'] ?? '';
        if ($sessionName && in_array($quizType, quiz_options(), true)) {
            $stmt = $pdo->prepare(
                'INSERT INTO quiz_sessions (session_name, quiz_type, question_count, duration_minutes, total_marks, max_attempts)
                 VALUES (?, ?, 50, 30, 50, 1)'
            );
            $stmt->execute([$sessionName, $quizType]);
            flash('success', 'Quiz session created. You can now add questions.');
            admin_session_redirect((int) $pdo->lastInsertId());
        }
        flash('danger', 'Enter a session name and quiz type.');
        admin_session_redirect();
    }

    $sessionId = (int) ($_POST['session_id'] ?? 0);
    $session = selected_quiz_session($pdo, $sessionId);

    if (!$session && in_array($action, ['update_session', 'publish_session', 'unpublish_session', 'save_question', 'delete_question', 'delete_all'], true)) {
        flash('danger', 'Please select a valid quiz session.');
        admin_session_redirect();
    }

    if ($action === 'update_session') {
        $sessionName = trim($_POST['session_name'] ?? '');
        $questionCount = max(1, (int) ($_POST['question_count'] ?? 50));
        $duration = max(1, (int) ($_POST['duration_minutes'] ?? 30));
        $totalMarks = max(1, (int) ($_POST['total_marks'] ?? 50));
        $maxAttempts = max(1, (int) ($_POST['max_attempts'] ?? 1));
        $liveAt = trim($_POST['live_at'] ?? '');
        $expiresAt = trim($_POST['expires_at'] ?? '');

        $stmt = $pdo->prepare(
            'UPDATE quiz_sessions
             SET session_name = ?, question_count = ?, duration_minutes = ?, total_marks = ?, max_attempts = ?, live_at = ?, expires_at = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $sessionName ?: $session['session_name'],
            $questionCount,
            $duration,
            $totalMarks,
            $maxAttempts,
            $liveAt ? str_replace('T', ' ', $liveAt) . ':00' : null,
            $expiresAt ? str_replace('T', ' ', $expiresAt) . ':00' : null,
            $sessionId,
        ]);
        flash('success', 'Session setup saved.');
        admin_session_redirect($sessionId);
    }

    if ($action === 'publish_session') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE quiz_session_id = ? AND is_active = 1');
        $stmt->execute([$sessionId]);
        $activeQuestions = (int) $stmt->fetchColumn();

        if (!$session['live_at'] || !$session['expires_at']) {
            flash('danger', 'Set go-live and expiry date/time before publishing.');
        } elseif ($activeQuestions < (int) $session['question_count']) {
            flash('danger', 'Add or activate enough questions before publishing.');
        } else {
            $stmt = $pdo->prepare('UPDATE quiz_sessions SET is_published = 1 WHERE id = ?');
            $stmt->execute([$sessionId]);
            flash('success', 'Quiz session published.');
        }
        admin_session_redirect($sessionId);
    }

    if ($action === 'unpublish_session') {
        $stmt = $pdo->prepare('UPDATE quiz_sessions SET is_published = 0 WHERE id = ?');
        $stmt->execute([$sessionId]);
        flash('success', 'Quiz session unpublished.');
        admin_session_redirect($sessionId);
    }

    if ($action === 'save_question') {
        $id = (int) ($_POST['id'] ?? 0);
        $questionType = $_POST['question_type'] ?? 'multiple_choice';
        $question = trim($_POST['question_text'] ?? '');
        $a = trim($_POST['option_a'] ?? '');
        $b = trim($_POST['option_b'] ?? '');
        $c = trim($_POST['option_c'] ?? '');
        $d = trim($_POST['option_d'] ?? '');
        $correct = $_POST['correct_option'] ?? '';
        $correctText = trim($_POST['correct_text'] ?? '');
        $points = max(1, (int) ($_POST['points'] ?? 1));
        $validType = in_array($questionType, ['multiple_choice', 'fill_gap'], true);
        $validAnswer = $questionType === 'fill_gap'
            ? $correctText !== ''
            : ($a && $b && $c && $d && in_array($correct, ['A', 'B', 'C', 'D'], true));

        if ($validType && $question && $validAnswer) {
            if ($questionType === 'fill_gap') {
                $a = $a ?: '-';
                $b = $b ?: '-';
                $c = $c ?: '-';
                $d = $d ?: '-';
                $correct = 'A';
            }

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE questions
                     SET question_type = ?, question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, correct_text = ?, points = ?
                     WHERE id = ? AND quiz_session_id = ?'
                );
                $stmt->execute([$questionType, $question, $a, $b, $c, $d, $correct, $correctText ?: null, $points, $id, $sessionId]);
                flash('success', 'Question updated. You can continue adding more.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO questions (quiz_session_id, quiz_type, question_type, question_text, option_a, option_b, option_c, option_d, correct_option, correct_text, points)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$sessionId, $session['quiz_type'], $questionType, $question, $a, $b, $c, $d, $correct, $correctText ?: null, $points]);
                flash('success', 'Question saved. Add the next question when ready.');
            }
        } else {
            flash('danger', 'Please complete the question and its correct answer.');
        }
        admin_session_redirect($sessionId);
    }

    if ($action === 'delete_question') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM questions WHERE id = ? AND quiz_session_id = ?');
        $stmt->execute([$id, $sessionId]);
        flash('success', 'Question deleted.');
        admin_session_redirect($sessionId);
    }

    if ($action === 'delete_all') {
        $stmt = $pdo->prepare('DELETE FROM questions WHERE quiz_session_id = ?');
        $stmt->execute([$sessionId]);
        flash('success', 'All questions in this session were deleted.');
        admin_session_redirect($sessionId);
    }
}

if (isset($_GET['toggle'], $_GET['session_id'])) {
    $id = (int) $_GET['toggle'];
    $sessionId = (int) $_GET['session_id'];
    $stmt = $pdo->prepare('UPDATE questions SET is_active = 1 - is_active WHERE id = ? AND quiz_session_id = ?');
    $stmt->execute([$id, $sessionId]);
    flash('success', 'Question status updated.');
    admin_session_redirect($sessionId);
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_GET['edit']]);
    $editQuestion = $stmt->fetch() ?: null;
}

$sessionRows = $pdo->query(
    'SELECT s.*,
            COUNT(q.id) AS total_questions_added,
            COALESCE(SUM(q.is_active = 1), 0) AS active_questions
     FROM quiz_sessions s
     LEFT JOIN questions q ON q.quiz_session_id = s.id
     GROUP BY s.id
     ORDER BY s.created_at DESC, s.id DESC'
)->fetchAll();

$selectedSessionId = (int) ($_GET['session_id'] ?? ($editQuestion['quiz_session_id'] ?? ($sessionRows[0]['id'] ?? 0)));
$selectedSession = $selectedSessionId ? selected_quiz_session($pdo, $selectedSessionId) : null;

if ($editQuestion && $selectedSession && (int) $editQuestion['quiz_session_id'] !== (int) $selectedSession['id']) {
    $editQuestion = null;
}

$questions = [];
$sessionStats = ['total_questions_added' => 0, 'active_questions' => 0];
if ($selectedSession) {
    $stmt = $pdo->prepare('SELECT * FROM questions WHERE quiz_session_id = ? ORDER BY id ASC');
    $stmt->execute([(int) $selectedSession['id']]);
    $questions = $stmt->fetchAll();

    foreach ($sessionRows as $row) {
        if ((int) $row['id'] === (int) $selectedSession['id']) {
            $sessionStats = $row;
            break;
        }
    }
}

$pageTitle = 'Question Manager';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Question Manager</h1>
        <p class="text-muted mb-0">Create a quiz session, save questions gradually, then publish when ready.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= e(base_url('/templates/questions_template.csv')) ?>">Download Template</a>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="panel p-3 p-md-4 mb-4">
            <h2 class="h5 mb-3">Create Quiz Session</h2>
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="create_session">
                <div class="col-12">
                    <label class="form-label">Session name</label>
                    <input class="form-control" name="session_name" placeholder="2025/2026 SESSION" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Quiz type</label>
                    <select class="form-select" name="quiz_type" required>
                        <?php foreach (quiz_options() as $option): ?>
                            <option value="<?= e($option) ?>"><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-success w-100" type="submit">Create Session</button>
                </div>
            </form>
        </div>

        <div class="panel p-3 p-md-4">
            <h2 class="h5 mb-3">Saved Sessions</h2>
            <div class="list-group">
                <?php foreach ($sessionRows as $row): ?>
                    <a class="list-group-item list-group-item-action <?= $selectedSession && (int) $selectedSession['id'] === (int) $row['id'] ? 'active' : '' ?>"
                       href="<?= e(base_url('/admin/questions.php?session_id=' . (int) $row['id'])) ?>">
                        <div class="d-flex justify-content-between">
                            <strong><?= e($row['session_name']) ?></strong>
                            <span><?= e($row['quiz_type']) ?></span>
                        </div>
                        <small><?= (int) $row['active_questions'] ?>/<?= (int) $row['question_count'] ?> active · <?= $row['is_published'] ? 'Published' : 'Draft' ?></small>
                    </a>
                <?php endforeach; ?>
                <?php if (!$sessionRows): ?>
                    <div class="text-muted">No quiz session created yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if ($selectedSession): ?>
            <?php $status = quiz_window_status($selectedSession); ?>
            <div class="panel p-3 p-md-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1"><?= e($selectedSession['session_name']) ?> · <?= e($selectedSession['quiz_type']) ?></h2>
                        <p class="text-muted mb-0"><?= (int) $sessionStats['active_questions'] ?> active of <?= (int) $selectedSession['question_count'] ?> required questions</p>
                    </div>
                    <span class="badge text-bg-<?= $selectedSession['is_published'] ? 'success' : 'secondary' ?>"><?= $selectedSession['is_published'] ? 'Published' : 'Draft' ?></span>
                </div>
                <form method="post" class="row g-3">
                    <input type="hidden" name="action" value="update_session">
                    <input type="hidden" name="session_id" value="<?= (int) $selectedSession['id'] ?>">
                    <div class="col-md-6">
                        <label class="form-label">Session name</label>
                        <input class="form-control" name="session_name" value="<?= e($selectedSession['session_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Question type</label>
                        <input class="form-control" value="<?= e($selectedSession['quiz_type']) ?>" disabled>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">No. questions</label>
                        <input class="form-control" type="number" min="1" name="question_count" value="<?= (int) $selectedSession['question_count'] ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Duration</label>
                        <input class="form-control" type="number" min="1" name="duration_minutes" value="<?= (int) $selectedSession['duration_minutes'] ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total marks</label>
                        <input class="form-control" type="number" min="1" name="total_marks" value="<?= (int) $selectedSession['total_marks'] ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Attempts</label>
                        <input class="form-control" type="number" min="1" name="max_attempts" value="<?= (int) $selectedSession['max_attempts'] ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Go live date/time</label>
                        <input class="form-control" type="datetime-local" name="live_at" value="<?= e($selectedSession['live_at'] ? date('Y-m-d\TH:i', strtotime($selectedSession['live_at'])) : '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Expire date/time</label>
                        <input class="form-control" type="datetime-local" name="expires_at" value="<?= e($selectedSession['expires_at'] ? date('Y-m-d\TH:i', strtotime($selectedSession['expires_at'])) : '') ?>">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Save Progress</button>
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php if ($selectedSession['is_published']): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="unpublish_session">
                            <input type="hidden" name="session_id" value="<?= (int) $selectedSession['id'] ?>">
                            <button class="btn btn-outline-warning" type="submit">Unpublish</button>
                        </form>
                    <?php else: ?>
                        <form method="post">
                            <input type="hidden" name="action" value="publish_session">
                            <input type="hidden" name="session_id" value="<?= (int) $selectedSession['id'] ?>">
                            <button class="btn btn-success" type="submit">Publish Quiz</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="text-muted mt-3"><?= e($status['message']) ?></div>
            </div>

            <div class="panel p-3 p-md-4 mb-4">
                <h2 class="h5 mb-3"><?= $editQuestion ? 'Edit Question' : 'Add More Question' ?></h2>
                <form method="post">
                    <input type="hidden" name="action" value="save_question">
                    <input type="hidden" name="session_id" value="<?= (int) $selectedSession['id'] ?>">
                    <input type="hidden" name="id" value="<?= (int) ($editQuestion['id'] ?? 0) ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Question style</label>
                            <select class="form-select" name="question_type" id="questionType">
                                <option value="multiple_choice" <?= (($editQuestion['question_type'] ?? 'multiple_choice') === 'multiple_choice') ? 'selected' : '' ?>>Multiple Choice</option>
                                <option value="fill_gap" <?= (($editQuestion['question_type'] ?? '') === 'fill_gap') ? 'selected' : '' ?>>Fill in the Gap</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Question for <?= e($selectedSession['quiz_type']) ?></label>
                            <textarea class="form-control" name="question_text" rows="3" required><?= e($editQuestion['question_text'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Correct option</label>
                            <select class="form-select" name="correct_option">
                                <?php foreach (['A', 'B', 'C', 'D'] as $letter): ?>
                                    <option value="<?= $letter ?>" <?= (($editQuestion['correct_option'] ?? 'A') === $letter) ? 'selected' : '' ?>><?= $letter ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Correct typed answer</label>
                            <input class="form-control" name="correct_text" value="<?= e($editQuestion['correct_text'] ?? '') ?>" placeholder="For fill in the gap">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Marks</label>
                            <input class="form-control" type="number" min="1" name="points" value="<?= (int) ($editQuestion['points'] ?? 1) ?>">
                        </div>
                        <?php foreach (['a', 'b', 'c', 'd'] as $letter): ?>
                            <div class="col-md-6">
                                <label class="form-label">Option <?= strtoupper($letter) ?></label>
                                <input class="form-control" name="option_<?= $letter ?>" value="<?= e($editQuestion['option_' . $letter] ?? '') ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-success" type="submit"><?= $editQuestion ? 'Save Question' : 'Save and Add More' ?></button>
                        <?php if ($editQuestion): ?>
                            <a class="btn btn-outline-secondary" href="<?= e(base_url('/admin/questions.php?session_id=' . (int) $selectedSession['id'])) ?>">Cancel Edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="panel p-3 p-md-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Questions Added</h2>
                    <form method="post" onsubmit="return confirm('Delete all questions in this session?');">
                        <input type="hidden" name="action" value="delete_all">
                        <input type="hidden" name="session_id" value="<?= (int) $selectedSession['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete All</button>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>#</th><th>Type</th><th>Question</th><th>Correct</th><th>Marks</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($questions as $index => $question): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= e(($question['question_type'] ?? 'multiple_choice') === 'fill_gap' ? 'Fill Gap' : 'MCQ') ?></td>
                                <td><?= e(strlen($question['question_text']) > 90 ? substr($question['question_text'], 0, 87) . '...' : $question['question_text']) ?></td>
                                <td><?= e(($question['question_type'] ?? 'multiple_choice') === 'fill_gap' ? ($question['correct_text'] ?? '') : $question['correct_option']) ?></td>
                                <td><?= (int) $question['points'] ?></td>
                                <td><span class="badge text-bg-<?= $question['is_active'] ? 'success' : 'secondary' ?>"><?= $question['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/admin/questions.php?session_id=' . (int) $selectedSession['id'] . '&edit=' . (int) $question['id'])) ?>">Edit</a>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('/admin/questions.php?session_id=' . (int) $selectedSession['id'] . '&toggle=' . (int) $question['id'])) ?>">Toggle</a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this question?');">
                                        <input type="hidden" name="action" value="delete_question">
                                        <input type="hidden" name="session_id" value="<?= (int) $selectedSession['id'] ?>">
                                        <input type="hidden" name="id" value="<?= (int) $question['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$questions): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No questions have been added to this session yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="panel p-4 text-center text-muted">Create a quiz session to begin adding questions.</div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
