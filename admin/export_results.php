<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="quizer-results.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['name', 'zone', 'email', 'phone', 'quiz_type', 'status', 'score', 'total_marks', 'total_questions', 'started_at', 'submitted_at']);

$stmt = $pdo->query(
    'SELECT u.full_name, u.zone, u.email, u.phone, u.quiz_type,
            a.status, a.score, a.total_marks, a.total_questions, a.started_at, a.submitted_at
     FROM attempts a
     INNER JOIN users u ON u.id = a.user_id
     ORDER BY a.started_at DESC'
);

while ($row = $stmt->fetch()) {
    fputcsv($out, [
        $row['full_name'],
        $row['zone'],
        $row['email'],
        $row['phone'],
        $row['quiz_type'],
        $row['status'],
        $row['score'],
        $row['total_marks'],
        $row['total_questions'],
        $row['started_at'],
        $row['submitted_at'],
    ]);
}

fclose($out);
