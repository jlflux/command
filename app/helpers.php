<?php
declare(strict_types=1);

/**
 * Athletics Command Center — shared helpers.
 *
 * Every entry-point script (pages in /public, API wrappers) starts with:
 *     require __DIR__ . '/../app/helpers.php';
 * which loads config + db and starts the session, then calls
 * require_login() / require_role() as its guard. Tenant scoping is applied
 * through tenant_query() and friends — see the multi-tenancy rules in
 * CLAUDE.md.
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
// Flash messages (redirect-after-POST feedback)
// ---------------------------------------------------------------------------

/** Queue a message to show on the next rendered page. $type: 'success' | 'error'. */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

/** Pull queued flash messages (clears the queue). */
function consume_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ---------------------------------------------------------------------------
// View rendering
// ---------------------------------------------------------------------------

/**
 * Render a template from app/views/ inside the base layout.
 * $data keys become local variables in the template; 'title' and 'active'
 * (nav highlight key) are also used by the layout header.
 * Only call on authenticated pages — the layout needs the current user/school.
 */
function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    $flashes = consume_flashes();
    require __DIR__ . '/views/layout_header.php';
    require __DIR__ . '/views/' . $template . '.php';
    require __DIR__ . '/views/layout_footer.php';
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

/** The current user's school row (cached per request). */
function current_school(): array
{
    static $school = null;

    if ($school === null) {
        $stmt = db()->prepare('SELECT * FROM schools WHERE id = ?');
        $stmt->execute([current_school_id()]);
        $school = $stmt->fetch();
        if ($school === false) {
            throw new RuntimeException('Current school not found');
        }
    }

    return $school;
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

/** All settings for the current school as key => value (cached per request). */
function school_settings_all(bool $reload = false): array
{
    static $cache = null;

    if ($cache === null || $reload) {
        $cache = [];
        $rows = tenant_fetch_all(
            'SELECT setting_key, setting_value FROM school_settings WHERE school_id = :school_id'
        );
        foreach ($rows as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }

    return $cache;
}

/** Read one setting for the current school, with a default. */
function school_setting(string $key, ?string $default = null): ?string
{
    $value = school_settings_all()[$key] ?? null;
    return ($value === null || $value === '') ? $default : $value;
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
    school_settings_all(true);
}

// ---------------------------------------------------------------------------
// Theming (school colors + logo, applied by the base layout)
// ---------------------------------------------------------------------------

/** Validated theme color for 'primary' or 'secondary' (falls back to defaults). */
function theme_color(string $which): string
{
    $default = $which === 'primary' ? '#1d4ed8' : '#111827';
    $color = school_setting('color_' . $which, $default) ?? $default;
    return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : $default;
}

/** Black or white, whichever reads better on the given hex background. */
function contrast_color(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '#ffffff';
    }
    [$r, $g, $b] = array_map(fn (int $i) => hexdec(substr($hex, $i, 2)), [0, 2, 4]);
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 150 ? '#111827' : '#ffffff';
}

/** URL of the school logo (cache-busted), or null if none uploaded. */
function school_logo_url(): ?string
{
    $path = school_setting('logo_path');
    if ($path === null) {
        return null;
    }
    $abs = __DIR__ . '/../public/' . $path;
    if (!is_file($abs)) {
        return null;
    }
    return BASE_URL . '/' . $path . '?v=' . filemtime($abs);
}
