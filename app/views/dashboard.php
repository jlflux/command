<?php /** Dashboard placeholder — the readiness view arrives with the schedule module. */ ?>
<h1>Dashboard</h1>
<p class="muted">
    Welcome back, <?= e($user['name']) ?>
    <?php if ($mascot = school_setting('mascot')): ?>
        — go <?= e($mascot) ?>!
    <?php endif; ?>
</p>

<div class="cards">
    <div class="card">
        <h2>Readiness</h2>
        <p class="muted">No upcoming events yet. The master schedule module is next —
        once events exist, this card shows what's missing for each one
        (workers, ticket link, opponent logo, sponsor obligations).</p>
    </div>
    <?php if (role_at_least($user['role'], 'admin')): ?>
    <div class="card">
        <h2>Quick admin</h2>
        <p><a href="<?= e(BASE_URL) ?>/users.php">Manage users</a> &middot;
           <a href="<?= e(BASE_URL) ?>/settings.php">School settings</a></p>
    </div>
    <?php endif; ?>
</div>
