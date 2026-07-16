<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';

// Not installed yet? Send to the installer.
if (INSTALL_ENABLED && current_user() === null) {
    try {
        $hasUsers = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (PDOException) {
        $hasUsers = false;
    }
    if (!$hasUsers) {
        redirect('/install.php');
    }
}

$user = require_login();

view('dashboard', [
    'title'  => 'Dashboard',
    'active' => 'dashboard',
    'user'   => $user,
]);
