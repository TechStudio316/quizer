<?php require_once __DIR__ . '/functions.php'; ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'QUIZER') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(base_url('/assets/css/style.css')) ?>" rel="stylesheet">
    <script src="<?= e(base_url('/assets/js/app.js')) ?>"></script>
</head>
<body data-base-url="<?= e(rtrim(base_url('/'), '/')) ?>">
<nav class="navbar navbar-expand-lg navbar-dark app-nav">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(base_url('/index.php')) ?>">QUIZER</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <div class="navbar-nav ms-auto">
                <?php if (current_user()): ?>
                    <a class="nav-link" href="<?= e(base_url('/candidate.php')) ?>">Quiz</a>
                    <a class="nav-link" href="<?= e(base_url('/result.php')) ?>">Result</a>
                    <a class="nav-link" href="<?= e(base_url('/logout.php')) ?>">Logout</a>
                <?php elseif (current_admin()): ?>
                    <a class="nav-link" href="<?= e(base_url('/admin/dashboard.php')) ?>">Dashboard</a>
                    <a class="nav-link" href="<?= e(base_url('/admin/questions.php')) ?>">Questions</a>
                    <a class="nav-link" href="<?= e(base_url('/admin/results.php')) ?>">Results</a>
                    <a class="nav-link" href="<?= e(base_url('/admin/logout.php')) ?>">Logout</a>
                <?php else: ?>
                    <a class="nav-link" href="<?= e(base_url('/index.php')) ?>">Candidate</a>
                    <a class="nav-link" href="<?= e(base_url('/admin/login.php')) ?>">Admin</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main class="py-4">
    <div class="container">
        <?php foreach (get_flashes() as $message): ?>
            <div class="alert alert-<?= e($message['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($message['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
