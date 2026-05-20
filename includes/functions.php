<?php
declare(strict_types=1);

date_default_timezone_set('Africa/Lagos');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = '';

    if (($adminPos = strpos($script, '/admin/')) !== false) {
        $base = substr($script, 0, $adminPos);
    } else {
        $base = rtrim(dirname($script), '/\\');
    }

    if ($base === '/' || $base === '.') {
        $base = '';
    }

    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    if (!preg_match('/^https?:\/\//i', $path)) {
        $path = base_url($path);
    }
    header("Location: {$path}");
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_user(): void
{
    if (!current_user()) {
        redirect('/index.php');
    }
}

function require_admin(): void
{
    if (!current_admin()) {
        redirect('/admin/login.php');
    }
}

function quiz_options(): array
{
    return ['Adult', 'YAYA'];
}

function zones(): array
{
    return ['Zone A', 'Zone B', 'Zone C', 'Zone D', 'Zone E'];
}

function app_setting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

function ensure_quiz_config(PDO $pdo, string $quizType): array
{
    $stmt = $pdo->prepare('SELECT * FROM quiz_configs WHERE quiz_type = ? LIMIT 1');
    $stmt->execute([$quizType]);
    $config = $stmt->fetch();

    if ($config) {
        return $config;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO quiz_configs (quiz_type, question_count, duration_minutes, total_marks, max_attempts)
         VALUES (?, 50, 30, 50, 1)'
    );
    $stmt->execute([$quizType]);

    $stmt = $pdo->prepare('SELECT * FROM quiz_configs WHERE quiz_type = ? LIMIT 1');
    $stmt->execute([$quizType]);
    return $stmt->fetch();
}

function ensure_quiz_session_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS quiz_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_name VARCHAR(120) NOT NULL,
            quiz_type ENUM('Adult', 'YAYA') NOT NULL,
            question_count INT NOT NULL DEFAULT 50,
            duration_minutes INT NOT NULL DEFAULT 30,
            total_marks INT NOT NULL DEFAULT 50,
            max_attempts INT NOT NULL DEFAULT 1,
            live_at DATETIME NULL,
            expires_at DATETIME NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    );

    $columns = [
        ['questions', 'quiz_session_id', 'ALTER TABLE questions ADD COLUMN quiz_session_id INT NULL AFTER id'],
        ['questions', 'question_type', "ALTER TABLE questions ADD COLUMN question_type ENUM('multiple_choice', 'fill_gap') NOT NULL DEFAULT 'multiple_choice' AFTER quiz_type"],
        ['questions', 'correct_text', 'ALTER TABLE questions ADD COLUMN correct_text VARCHAR(255) NULL AFTER correct_option'],
        ['attempts', 'quiz_session_id', 'ALTER TABLE attempts ADD COLUMN quiz_session_id INT NULL AFTER id'],
        ['attempts', 'total_marks', 'ALTER TABLE attempts ADD COLUMN total_marks INT NOT NULL DEFAULT 0 AFTER total_questions'],
        ['answers', 'answer_text', 'ALTER TABLE answers ADD COLUMN answer_text VARCHAR(255) NULL AFTER selected_option'],
    ];

    foreach ($columns as $column) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$column[0], $column[1]]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec($column[2]);
        }
    }
}

function selected_quiz_session(PDO $pdo, int $sessionId): ?array
{
    ensure_quiz_session_columns($pdo);
    $stmt = $pdo->prepare('SELECT * FROM quiz_sessions WHERE id = ? LIMIT 1');
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    return $session ?: null;
}

function active_quiz_session(PDO $pdo, string $quizType): ?array
{
    ensure_quiz_session_columns($pdo);
    $stmt = $pdo->prepare(
        'SELECT *
         FROM quiz_sessions
         WHERE quiz_type = ?
           AND is_published = 1
           AND live_at IS NOT NULL
           AND expires_at IS NOT NULL
         ORDER BY
            CASE
                WHEN NOW() BETWEEN live_at AND expires_at THEN 0
                WHEN NOW() < live_at THEN 1
                ELSE 2
            END,
            live_at DESC,
            id DESC
         LIMIT 1'
    );
    $stmt->execute([$quizType]);
    $session = $stmt->fetch();
    return $session ?: null;
}

function quiz_window_status(array $config): array
{
    $now = time();
    $liveAt = $config['live_at'] ? strtotime($config['live_at']) : null;
    $expiresAt = $config['expires_at'] ? strtotime($config['expires_at']) : null;

    if (array_key_exists('is_published', $config) && !(int) $config['is_published']) {
        return ['open' => false, 'message' => 'This quiz has not been published yet.'];
    }

    if (!$liveAt || !$expiresAt) {
        return ['open' => false, 'message' => 'This quiz schedule has not been published yet.'];
    }

    if ($now < $liveAt) {
        return ['open' => false, 'message' => 'This quiz is not live yet.'];
    }

    if ($now > $expiresAt) {
        return ['open' => false, 'message' => 'This quiz has expired.'];
    }

    return ['open' => true, 'message' => 'Quiz is open.'];
}

function user_attempt_count(PDO $pdo, int $userId, string $quizType): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE user_id = ? AND quiz_type = ?');
    $stmt->execute([$userId, $quizType]);
    return (int) $stmt->fetchColumn();
}

function user_session_attempt_count(PDO $pdo, int $userId, int $sessionId): int
{
    ensure_quiz_session_columns($pdo);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM attempts WHERE user_id = ? AND quiz_session_id = ?');
    $stmt->execute([$userId, $sessionId]);
    return (int) $stmt->fetchColumn();
}

function upsert_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function get_attempt(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM attempts WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $attempt = $stmt->fetch();
    return $attempt ?: null;
}

function get_session_attempt(PDO $pdo, int $userId, int $sessionId): ?array
{
    ensure_quiz_session_columns($pdo);
    $stmt = $pdo->prepare('SELECT * FROM attempts WHERE user_id = ? AND quiz_session_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId, $sessionId]);
    $attempt = $stmt->fetch();
    return $attempt ?: null;
}

function get_active_attempt(PDO $pdo, int $userId): ?array
{
    ensure_quiz_session_columns($pdo);
    $stmt = $pdo->prepare('SELECT * FROM attempts WHERE user_id = ? AND status = "in_progress" ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $attempt = $stmt->fetch();
    return $attempt ?: null;
}

function attempt_remaining_seconds(array $attempt): int
{
    $end = strtotime($attempt['ends_at']);
    return max(0, $end - time());
}

function score_attempt(PDO $pdo, int $attemptId): array
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(q.points), 0) AS raw_total,
                COALESCE(SUM(
                    CASE
                        WHEN q.question_type = "fill_gap"
                             AND LOWER(TRIM(a.answer_text)) = LOWER(TRIM(q.correct_text)) THEN q.points
                        WHEN q.question_type = "multiple_choice"
                             AND a.selected_option = q.correct_option THEN q.points
                        ELSE 0
                    END
                ), 0) AS raw_score,
                MAX(at.total_marks) AS configured_total
         FROM answers a
         INNER JOIN attempts at ON at.id = a.attempt_id
         INNER JOIN questions q ON q.id = a.question_id
         WHERE a.attempt_id = ?'
    );
    $stmt->execute([$attemptId]);
    $row = $stmt->fetch() ?: ['raw_total' => 0, 'raw_score' => 0, 'configured_total' => 0];
    $rawTotal = (int) $row['raw_total'];
    $configuredTotal = (int) $row['configured_total'];
    $total = $configuredTotal > 0 ? $configuredTotal : $rawTotal;
    $score = $rawTotal > 0 ? (int) round(((int) $row['raw_score'] / $rawTotal) * $total) : 0;

    return ['total' => $total, 'score' => $score];
}
