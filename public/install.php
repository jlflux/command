<?php
declare(strict_types=1);

/**
 * One-time installer: runs pending migrations and creates the first
 * school + admin user. After installing, set INSTALL_ENABLED = false in
 * app/config.php and delete this file.
 */

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/migrate.php';
require __DIR__ . '/../app/seed.php';

if (!INSTALL_ENABLED) {
    http_response_code(404);
    exit('Not found.');
}

$errors  = [];
$success = false;

// --- Database connectivity -------------------------------------------------
try {
    db();
    $dbOk = true;
} catch (PDOException $e) {
    $dbOk = false;
    $errors[] = 'Cannot connect to the database. Fill in real credentials in app/config.php. '
        . 'Driver said: ' . $e->getMessage();
}

// --- Helpers ---------------------------------------------------------------

/** Is the app already installed (users table exists and has at least one row)? */
function is_installed(): bool
{
    try {
        return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (PDOException) {
        return false; // table doesn't exist yet
    }
}

/** Make a URL-safe slug out of a school name. */
function slugify(string $name): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'));
    return $slug !== '' ? substr($slug, 0, 80) : 'school-' . bin2hex(random_bytes(3));
}

$installed = $dbOk && is_installed();

// --- Handle submission -----------------------------------------------------
if ($dbOk && !$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $schoolName = trim($_POST['school_name'] ?? '');
    $adminName  = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $password   = $_POST['admin_password'] ?? '';
    $password2  = $_POST['admin_password2'] ?? '';

    if ($schoolName === '') {
        $errors[] = 'School name is required.';
    }
    if ($adminName === '') {
        $errors[] = 'Admin name is required.';
    }
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid admin email is required.';
    }
    if (strlen($password) < 10) {
        $errors[] = 'Password must be at least 10 characters.';
    }
    if ($password !== $password2) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            run_pending_migrations();

            $pdo = db();
            $pdo->beginTransaction();

            $pdo->prepare('INSERT INTO schools (name, slug) VALUES (?, ?)')
                ->execute([$schoolName, slugify($schoolName)]);
            $schoolId = (int)$pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO users (school_id, name, email, password_hash, role)
                 VALUES (?, ?, ?, ?, \'admin\')'
            )->execute([$schoolId, $adminName, $adminEmail, password_hash($password, PASSWORD_DEFAULT)]);

            seed_school_defaults($pdo, $schoolId);

            $pdo->commit();
            $success   = true;
            $installed = true;
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = APP_ENV === 'production'
                ? 'Installation failed. Check the server error log.'
                : 'Installation failed: ' . $e->getMessage();
            error_log('[install] ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install — Athletics Command Center</title>
<style>
    :root { --primary: #1d4ed8; --danger: #b91c1c; --ok: #15803d; }
    * { box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, sans-serif; margin: 0; background: #f3f4f6; color: #111827; }
    .wrap { max-width: 480px; margin: 2rem auto; padding: 0 1rem; }
    .card { background: #fff; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.12); }
    h1 { font-size: 1.3rem; margin: 0 0 .25rem; }
    p.sub { margin: 0 0 1.25rem; color: #6b7280; font-size: .9rem; }
    label { display: block; font-weight: 600; font-size: .85rem; margin: .9rem 0 .3rem; }
    input { width: 100%; padding: .65rem .75rem; font-size: 1rem; border: 1px solid #d1d5db; border-radius: 6px; }
    input:focus { outline: 2px solid var(--primary); border-color: var(--primary); }
    button { width: 100%; margin-top: 1.25rem; padding: .8rem; font-size: 1rem; font-weight: 600;
             color: #fff; background: var(--primary); border: 0; border-radius: 6px; cursor: pointer; }
    .msg { padding: .75rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: .9rem; }
    .err { background: #fef2f2; color: var(--danger); border: 1px solid #fecaca; }
    .ok  { background: #f0fdf4; color: var(--ok);     border: 1px solid #bbf7d0; }
    fieldset { border: 0; padding: 0; margin: 0; }
    legend { font-weight: 700; font-size: .95rem; margin-top: 1.25rem; padding: 0; }
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Athletics Command Center</h1>
        <p class="sub">First-time setup</p>

        <?php foreach ($errors as $error): ?>
            <div class="msg err"><?= e($error) ?></div>
        <?php endforeach; ?>

        <?php if ($success): ?>
            <div class="msg ok">
                Installed! Your school and admin account were created.<br><br>
                <strong>Now set <code>INSTALL_ENABLED = false</code> in
                <code>app/config.php</code> and delete <code>install.php</code>.</strong>
            </div>
            <p><a href="<?= e(BASE_URL) ?>/">Go to the app &rarr;</a></p>
        <?php elseif ($installed): ?>
            <div class="msg ok">
                Already installed. Set <code>INSTALL_ENABLED = false</code> in
                <code>app/config.php</code> and delete this file.
            </div>
        <?php elseif ($dbOk): ?>
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <fieldset>
                    <legend>School</legend>
                    <label for="school_name">School name</label>
                    <input id="school_name" name="school_name" required
                           value="<?= e($_POST['school_name'] ?? '') ?>"
                           placeholder="Homewood High School">
                </fieldset>
                <fieldset>
                    <legend>Administrator (athletic director)</legend>
                    <label for="admin_name">Name</label>
                    <input id="admin_name" name="admin_name" required
                           value="<?= e($_POST['admin_name'] ?? '') ?>">
                    <label for="admin_email">Email</label>
                    <input id="admin_email" name="admin_email" type="email" required
                           value="<?= e($_POST['admin_email'] ?? '') ?>">
                    <label for="admin_password">Password (10+ characters)</label>
                    <input id="admin_password" name="admin_password" type="password" required minlength="10">
                    <label for="admin_password2">Confirm password</label>
                    <input id="admin_password2" name="admin_password2" type="password" required minlength="10">
                </fieldset>
                <button type="submit">Run migrations &amp; install</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
