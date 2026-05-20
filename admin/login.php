<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM admins WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        flash('danger', 'Invalid admin credentials.');
        redirect('/admin/login.php');
    }

    $_SESSION['admin'] = $admin;
    redirect('/admin/dashboard.php');
}

$pageTitle = 'Admin Login';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="panel p-4 p-md-5 mx-auto" style="max-width: 480px;">
    <h1 class="h3 mb-1">Admin Login</h1>
    <p class="text-muted mb-4">Default login: admin@quizer.local / admin123</p>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Email address</label>
            <input class="form-control" type="email" name="email" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" required>
        </div>
        <button class="btn btn-primary w-100" type="submit">Sign In</button>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
