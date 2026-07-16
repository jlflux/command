<?php
declare(strict_types=1);

/**
 * Athletics Command Center — configuration.
 *
 * Deployment: copy real values into app/config.local.php (gitignored) —
 * define() any constant there to override the defaults below. Never commit
 * real credentials. On Hostinger: hPanel → Databases → MySQL Databases for
 * host/name/user/pass.
 */

if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// --- Database -------------------------------------------------------------
defined('DB_HOST')    || define('DB_HOST', 'localhost');
defined('DB_PORT')    || define('DB_PORT', 3306);
defined('DB_NAME')    || define('DB_NAME', 'CHANGE_ME_db_name');
defined('DB_USER')    || define('DB_USER', 'CHANGE_ME_db_user');
defined('DB_PASS')    || define('DB_PASS', 'CHANGE_ME_db_password');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// --- Application ----------------------------------------------------------

// 'production' hides error detail from users; anything else shows it.
defined('APP_ENV') || define('APP_ENV', 'production');

// Absolute base URL without trailing slash, e.g. 'https://athletics.example.com'.
// Leave '' to use root-relative URLs.
defined('BASE_URL') || define('BASE_URL', '');

// Set to false after running /install.php (and preferably delete that file).
defined('INSTALL_ENABLED') || define('INSTALL_ENABLED', true);

// Session cookie name (unique per app so multiple apps on one domain don't clash).
defined('SESSION_NAME') || define('SESSION_NAME', 'acc_session');
