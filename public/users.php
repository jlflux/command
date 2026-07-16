<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';

$user = require_role('admin');

// POST: activate/deactivate a user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (($_POST['action'] ?? '') === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $target = tenant_fetch(
            'SELECT * FROM users WHERE id = :id AND school_id = :school_id',
            ['id' => $id]
        ) ?? not_found();

        if ((int)$target['id'] === (int)$user['id']) {
            flash('You cannot deactivate your own account.', 'error');
        } elseif ($target['is_active'] && $target['role'] === 'admin' && count_other_active_admins($id) === 0) {
            flash('You cannot deactivate the last active admin.', 'error');
        } else {
            tenant_query(
                'UPDATE users SET is_active = 1 - is_active WHERE id = :id AND school_id = :school_id',
                ['id' => $id]
            );
            flash($target['is_active']
                ? e_name($target) . ' was deactivated.'
                : e_name($target) . ' was reactivated.');
        }
    }

    redirect('/users.php');
}

/** Active admins in this school other than $excludeId. */
function count_other_active_admins(int $excludeId): int
{
    $row = tenant_fetch(
        "SELECT COUNT(*) AS n FROM users
         WHERE school_id = :school_id AND role = 'admin' AND is_active = 1 AND id != :id",
        ['id' => $excludeId]
    );
    return (int)($row['n'] ?? 0);
}

/** Plain-text name for flash messages (flashes are escaped on output). */
function e_name(array $row): string
{
    return $row['name'];
}

$users = tenant_fetch_all(
    'SELECT u.*, GROUP_CONCAT(jt.name ORDER BY jt.sort_order, jt.name SEPARATOR ", ") AS job_types
     FROM users u
     LEFT JOIN user_job_types ujt ON ujt.user_id = u.id
     LEFT JOIN job_types jt ON jt.id = ujt.job_type_id
     WHERE u.school_id = :school_id
     GROUP BY u.id
     ORDER BY u.is_active DESC, u.name'
);

view('users_list', [
    'title'  => 'Users',
    'active' => 'users',
    'user'   => $user,
    'users'  => $users,
]);
