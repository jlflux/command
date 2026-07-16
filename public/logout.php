<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';

// State change only via POST + CSRF; a GET here just bounces to login.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    logout_user();
}

redirect('/login.php');
