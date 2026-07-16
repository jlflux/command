<?php
/**
 * Base layout header. Rendered by view() — expects optional $title, $active
 * (nav key), and $flashes (from view()). Requires a logged-in user.
 */
$_user   = current_user();
$_school = current_school();
$_logo   = school_logo_url();
$_title  = isset($title) ? $title . ' — ' . $_school['name'] : $_school['name'];
$_active = $active ?? '';

$_nav = [
    'dashboard' => ['Dashboard', '/'],
    'schedule'  => ['Schedule',  '/schedule.php'],
    'events'    => ['Events',    '/events.php'],
    'sponsors'  => ['Sponsors',  '/sponsors.php'],
    'teams'     => ['Teams',     '/teams.php'],
    'assets'    => ['Assets',    '/assets.php'],
    'incidents' => ['Incidents', '/incidents.php'],
    'reports'   => ['Reports',   '/reports.php'],
];
if ($_user['role'] === 'admin') {
    $_nav['settings'] = ['Settings', '/settings.php'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= csrf_meta() . "\n" ?>
<title><?= e($_title) ?></title>
<style>
:root {
    --color-primary: <?= e(theme_color('primary')) ?>;
    --color-secondary: <?= e(theme_color('secondary')) ?>;
    --on-primary: <?= e(contrast_color(theme_color('primary'))) ?>;
    --on-secondary: <?= e(contrast_color(theme_color('secondary'))) ?>;
}
</style>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/app.css">
<script src="<?= e(BASE_URL) ?>/assets/js/app.js" defer></script>
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e(BASE_URL) ?>/">
        <?php if ($_logo): ?>
            <img src="<?= e($_logo) ?>" alt="" class="brand-logo">
        <?php endif; ?>
        <span class="brand-name"><?= e($_school['name']) ?></span>
    </a>
    <button type="button" class="nav-toggle" aria-label="Menu" aria-expanded="false" aria-controls="mainnav">&#9776;</button>
    <nav class="mainnav" id="mainnav">
        <ul class="nav-links">
            <?php foreach ($_nav as $_key => [$_label, $_href]): ?>
                <li><a href="<?= e(BASE_URL . $_href) ?>"<?= $_key === $_active ? ' class="active"' : '' ?>><?= e($_label) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="nav-user">
            <?php if ($_user['role'] === 'admin'): ?>
                <a href="<?= e(BASE_URL) ?>/users.php"<?= $_active === 'users' ? ' class="active"' : '' ?>>Users</a>
            <?php endif; ?>
            <span class="nav-username"><?= e($_user['name']) ?></span>
            <form method="post" action="<?= e(BASE_URL) ?>/logout.php">
                <?= csrf_field() ?>
                <button type="submit" class="btn-link">Log out</button>
            </form>
        </div>
    </nav>
</header>
<main class="wrap">
<?php foreach (($flashes ?? []) as $_flash): ?>
    <div class="msg <?= $_flash['type'] === 'error' ? 'err' : 'ok' ?>"><?= e($_flash['message']) ?></div>
<?php endforeach; ?>
