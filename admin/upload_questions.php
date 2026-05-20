<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if (!isset($_FILES['question_file']) || $_FILES['question_file']['error'] !== UPLOAD_ERR_OK) {
    flash('danger', 'Please choose a valid CSV file.');
    redirect('/admin/questions.php');
}

$file = $_FILES['question_file']['tmp_name'];
$handle = fopen($file, 'r');
if (!$handle) {
    flash('danger', 'Unable to read the uploaded file.');
    redirect('/admin/questions.php');
}

$headers = fgetcsv($handle);
$expected = ['quiz_type', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'points'];
if (!$headers || array_map('trim', $headers) !== $expected) {
    fclose($handle);
    flash('danger', 'Template headers do not match. Download the template and keep the first row unchanged.');
    redirect('/admin/questions.php');
}

$stmt = $pdo->prepare(
    'INSERT INTO questions (quiz_type, question_text, option_a, option_b, option_c, option_d, correct_option, points)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

$created = 0;
$skipped = 0;
while (($row = fgetcsv($handle)) !== false) {
    $data = array_combine($expected, array_pad($row, count($expected), ''));
    $quizType = trim($data['quiz_type']);
    $question = trim($data['question_text']);
    $a = trim($data['option_a']);
    $b = trim($data['option_b']);
    $c = trim($data['option_c']);
    $d = trim($data['option_d']);
    $correct = strtoupper(trim($data['correct_option']));
    $points = max(1, (int) trim($data['points']));

    if (!in_array($quizType, quiz_options(), true) || !$question || !$a || !$b || !$c || !$d || !in_array($correct, ['A', 'B', 'C', 'D'], true)) {
        $skipped++;
        continue;
    }

    $stmt->execute([$quizType, $question, $a, $b, $c, $d, $correct, $points]);
    $created++;
}
fclose($handle);

flash('success', "{$created} questions uploaded. {$skipped} rows skipped.");
redirect('/admin/questions.php');

