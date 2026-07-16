<?php
declare(strict_types=1);

/**
 * Athletics Command Center — shared helpers.
 *
 * Every entry-point script (pages in /public, API wrappers) starts with:
 *     require __DIR__ . '/../app/helpers.php';
 * which loads config + db and starts the session.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------------------
// Session bootstrap
// ---------------------------------------------------------------------------

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------------------------------------------------------------------
// Output / request helpers
// ---------------------------------------------------------------------------

/** HTML-escape for output. Every echo of dynamic data goes through this. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Redirect (root-relative path or absolute URL) and stop. */
function redirect(string $path): never
{
    if (!preg_match('#^https?://#', $path)) {
        $path = BASE_URL . $path;
    }
    header('Location: ' . $path);
    exit;
}

/** Emit a JSON response and stop. Used by everything in app/api/. */
function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

/** Render a 404 and stop. Cross-tenant lookups must land here, same as missing rows. */
function not_found(string $message = 'Not found'): never
{
    http_response_code(404);
    echo e($message);
    exit;
}

// ---------------------------------------------------------------------------
// CSRF protection — all state-changing requests are POST + CSRF-checked
// ---------------------------------------------------------------------------

/** Get (creating if needed) the CSRF token for this session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input for HTML forms. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** <meta> tag so page JS can send the token on fetch() requests. */
function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . e(csrf_token()) . '">';
}

/**
 * Validate the CSRF token on a mutating request. Accepts the token from the
 * `csrf_token` POST field or the `X-CSRF-Token` header (for fetch()).
 * Dies with 403 on failure.
 */
function require_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$sent)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------

/** Role hierarchy — higher number means more access. */
const ROLE_LEVELS = ['viewer' => 1, 'staff' => 2, 'manager' => 3, 'admin' => 4];

/** Log a user in (call after password_verify succeeds). */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['school_id'] = (int)$user['school_id'];
    unset($_SESSION['csrf_token']); // fresh token for the new session

    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
        ->execute([(int)$user['id']]);
}

/** Log out and destroy the session. */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * The logged-in user row (from DB, cached per request), or null.
 * Inactive users and users of deactivated schools are treated as logged out.
 */
function current_user(): ?array
{
    static $user = false;

    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare(
                'SELECT u.* FROM users u
                 JOIN schools s ON s.id = u.school_id
                 WHERE u.id = ? AND u.is_active = 1 AND s.is_active = 1'
            );
            $stmt->execute([(int)$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }

    return $user;
}

/** Require a logged-in user; redirect to login otherwise. */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        if (str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', '/api/') || wants_json()) {
            json_response(['error' => 'Not authenticated'], 401);
        }
        redirect('/login.php');
    }
    return $user;
}

/**
 * Require the given role *or above* (viewer < staff < manager < admin).
 * e.g. require_role('manager') allows managers and admins.
 */
function require_role(string $minRole): array
{
    $user = require_login();
    if (!role_at_least($user['role'], $minRole)) {
        if (wants_json()) {
            json_response(['error' => 'Forbidden'], 403);
        }
        http_response_code(403);
        exit('You do not have permission to do that.');
    }
    return $user;
}

/** True if $role meets or exceeds $minRole in the hierarchy. */
function role_at_least(string $role, string $minRole): bool
{
    return (ROLE_LEVELS[$role] ?? 0) >= (ROLE_LEVELS[$minRole] ?? PHP_INT_MAX);
}

/** Heuristic: did the client ask for JSON? */
function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
}

// ---------------------------------------------------------------------------
// Tenant scoping — THE core multi-tenancy rule
// ---------------------------------------------------------------------------

/**
 * The current user's school id. Never trust a school_id from request input.
 * Fails hard if called without a logged-in user.
 */
function current_school_id(): int
{
    $user = current_user();
    if ($user === null) {
        throw new RuntimeException('current_school_id() called without a logged-in user');
    }
    return (int)$user['school_id'];
}

/**
 * Run a prepared query that is guaranteed tenant-scoped: the SQL must
 * reference :school_id (enforced), and it is bound automatically from the
 * session — callers cannot supply it.
 *
 *   $stmt = tenant_query('SELECT * FROM events WHERE school_id = :school_id AND id = :id', ['id' => $id]);
 */
function tenant_query(string $sql, array $params = []): PDOStatement
{
    if (!str_contains($sql, ':school_id')) {
        throw new LogicException('tenant_query(): SQL must be scoped with :school_id — ' . $sql);
    }
    unset($params['school_id'], $params[':school_id']);
    $params['school_id'] = current_school_id();

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Tenant-scoped single-row fetch. Returns null when no row (or wrong tenant). */
function tenant_fetch(string $sql, array $params = []): ?array
{
    $row = tenant_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Tenant-scoped multi-row fetch. */
function tenant_fetch_all(string $sql, array $params = []): array
{
    return tenant_query($sql, $params)->fetchAll();
}

// ---------------------------------------------------------------------------
// Per-school settings (school_settings key/value table)
// ---------------------------------------------------------------------------

/** Read one setting for the current school, with a default. */
function school_setting(string $key, ?string $default = null): ?string
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        $rows = tenant_fetch_all(
            'SELECT setting_key, setting_value FROM school_settings WHERE school_id = :school_id'
        );
        foreach ($rows as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $cache[$key] ?? $default;
}

/** Write one setting for the current school (insert or update). */
function set_school_setting(string $key, string $value): void
{
    tenant_query(
        'INSERT INTO school_settings (school_id, setting_key, setting_value)
         VALUES (:school_id, :k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        ['k' => $key, 'v' => $value]
    );
}
