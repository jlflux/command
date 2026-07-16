<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';

if (current_user() !== null) {
    redirect('/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);
    if (time() < $lockedUntil) {
        $error = 'Too many failed attempts. Wait a minute and try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Intentional non-tenant-scoped query: login resolves the tenant.
        // Emails are globally unique, and inactive users/schools can't log in.
        $stmt = db()->prepare(
            'SELECT u.* FROM users u
             JOIN schools s ON s.id = u.school_id
             WHERE u.email = ? AND u.is_active = 1 AND s.is_active = 1'
        );
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        if ($account && password_verify($password, $account['password_hash'])) {
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            login_user($account);
            redirect('/');
        }

        usleep(300000); // dampen brute force
        $attempts = (int)($_SESSION['login_attempts'] ?? 0) + 1;
        if ($attempts >= 5) {
            $_SESSION['login_locked_until'] = time() + 60;
            $attempts = 0;
        }
        $_SESSION['login_attempts'] = $attempts;
        $error = 'Invalid email or password.';
    }
}

$flashes = consume_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in — Athletics Command Center</title>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/app.css">
</head>
<body class="auth-page">
<div class="auth-wrap">
    <div class="card auth-card">
        <h1>Athletics Command Center</h1>
        <p class="muted">Sign in to your school</p>

        <?php foreach ($flashes as $flash): ?>
            <div class="msg <?= $flash['type'] === 'error' ? 'err' : 'ok' ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
        <?php if ($error !== ''): ?>
            <div class="msg err"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(BASE_URL) ?>/login.php">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="username"
                       value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">Log in</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
