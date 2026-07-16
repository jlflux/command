<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';

$user = require_login();

view('coming_soon', ['title' => 'Incidents', 'active' => 'incidents', 'user' => $user]);
