<?php
declare(strict_types=1);

/**
 * Athletics Command Center — configuration.
 *
 * Copy real values in on the server; never commit real credentials.
 * On Hostinger: hPanel → Databases → MySQL Databases for host/name/user/pass.
 */

// --- Database -------------------------------------------------------------
const DB_HOST    = 'localhost';
const DB_NAME    = 'CHANGE_ME_db_name';
const DB_USER    = 'CHANGE_ME_db_user';
const DB_PASS    = 'CHANGE_ME_db_password';
const DB_CHARSET = 'utf8mb4';

// --- Application ----------------------------------------------------------

// 'production' hides error detail from users; anything else shows it.
const APP_ENV = 'production';

// Absolute base URL without trailing slash, e.g. 'https://athletics.example.com'.
// Leave '' to use root-relative URLs.
const BASE_URL = '';

// Set to false after running /install.php (and preferably delete that file).
const INSTALL_ENABLED = true;

// Session cookie name (unique per app so multiple apps on one domain don't clash).
const SESSION_NAME = 'acc_session';
