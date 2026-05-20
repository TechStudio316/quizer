<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'login';

    if ($mode === 'register') {
        $fullName = trim($_POST['full_name'] ?? '');
        $zone = trim($_POST['zone'] ?? '');
        $quizType = trim($_POST['quiz_type'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!$fullName || !$zone || !in_array($quizType, quiz_options(), true) || !$phone || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Please complete all registration fields correctly.');
            redirect('/index.php');
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO users (full_name, zone, quiz_type, phone, email) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$fullName, $zone, $quizType, $phone, $email]);
            $_SESSION['user'] = [
                'id' => (int) $pdo->lastInsertId(),
                'full_name' => $fullName,
                'zone' => $zone,
                'quiz_type' => $quizType,
                'phone' => $phone,
                'email' => $email,
            ];
            redirect('/candidate.php');
        } catch (PDOException $e) {
            flash('warning', 'That email is already registered. Sign in with your email and phone number.');
            redirect('/index.php');
        }
    }

    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND phone = ? LIMIT 1');
    $stmt->execute([$email, $phone]);
    $user = $stmt->fetch();

    if (!$user) {
        flash('danger', 'Invalid email or phone number.');
        redirect('/index.php');
    }

    $_SESSION['user'] = $user;
    redirect('/candidate.php');
}

$pageTitle = 'QUIZER Candidate Portal';
require_once __DIR__ . '/includes/header.php';
?>
<section class="hero mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <p class="text-uppercase fw-semibold mb-2">Sunday School Quiz Competition</p>
            <h1 class="display-5 fw-bold mb-3">QUIZER</h1>
            <p class="lead mb-0">Register once, sign in with your email and phone number, and take your timed Adult or YAYA quiz.</p>
        </div>
        <div class="col-lg-5">
            <div class="panel p-3 p-md-4 text-dark">
                <ul class="nav nav-pills mb-3" id="authTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#login" type="button">Sign in</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#register" type="button">Register</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="login">
                        <form method="post">
                            <input type="hidden" name="mode" value="login">
                            <div class="mb-3">
                                <label class="form-label">Email address</label>
                                <input class="form-control" type="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone number as password</label>
                                <input class="form-control" type="password" name="phone" required>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Enter Quiz Area</button>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="register">
                        <form method="post">
                            <input type="hidden" name="mode" value="register">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Full name</label>
                                    <input class="form-control" name="full_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Zone</label>
                                    <input class="form-control" name="zone" list="zoneList" required>
                                    <datalist id="zoneList">
                                        <?php foreach (zones() as $zone): ?>
                                            <option value="<?= e($zone) ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Quiz</label>
                                    <select class="form-select" name="quiz_type" required>
                                        <option value="">Select</option>
                                        <?php foreach (quiz_options() as $option): ?>
                                            <option value="<?= e($option) ?>"><?= e($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone number</label>
                                    <input class="form-control" name="phone" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email address</label>
                                    <input class="form-control" type="email" name="email" required>
                                </div>
                            </div>
                            <button class="btn btn-success w-100 mt-3" type="submit">Create Account</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
