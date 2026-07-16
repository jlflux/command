<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';

// Not installed yet? Send to the installer.
if (INSTALL_ENABLED) {
    try {
        $hasUsers = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (PDOException) {
        $hasUsers = false;
    }
    if (!$hasUsers) {
        redirect('/install.php');
    }
}

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= csrf_meta() ?>
<title>Athletics Command Center</title>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/app.css">
</head>
<body>
<main class="wrap">
    <h1>Athletics Command Center</h1>
    <?php if ($user): ?>
        <p>Signed in as <?= e($user['name']) ?> (<?= e($user['role']) ?>).</p>
    <?php else: ?>
        <p>Skeleton installed. Auth &amp; module pages arrive in the next phase.</p>
    <?php endif; ?>
</main>
</body>
</html>
