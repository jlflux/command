<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';

$user = require_role('admin');

const USER_ROLES = ['admin', 'manager', 'staff', 'viewer'];

// Load the user being edited (create mode when no id). Tenant-scoped: a user
// from another school 404s exactly like a missing one.
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$target = null;
if ($id > 0) {
    $target = tenant_fetch(
        'SELECT * FROM users WHERE id = :id AND school_id = :school_id',
        ['id' => $id]
    ) ?? not_found();
}
$isSelf = $target !== null && (int)$target['id'] === (int)$user['id'];

$jobTypes = tenant_fetch_all(
    'SELECT * FROM job_types WHERE school_id = :school_id AND is_active = 1 ORDER BY sort_order, name'
);
$validJobTypeIds = array_map(fn (array $jt) => (int)$jt['id'], $jobTypes);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $role     = $_POST['role'] ?? 'staff';
    $password = $_POST['password'] ?? '';
    $jobTypeIds = array_values(array_unique(array_map('intval', (array)($_POST['job_type_ids'] ?? []))));

    if ($name === '' || mb_strlen($name) > 120) {
        $errors[] = 'Full name is required (max 120 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email is required.';
    }
    if (mb_strlen($phone) > 30) {
        $errors[] = 'Phone must be 30 characters or fewer.';
    }
    if (!in_array($role, USER_ROLES, true)) {
        $errors[] = 'Invalid role.';
    }
    if ($password !== '' && strlen($password) < 10) {
        $errors[] = 'Password must be at least 10 characters.';
    }
    if (array_diff($jobTypeIds, $validJobTypeIds)) {
        $errors[] = 'Invalid job type selection.';
    }

    // Role-change guards: no self-demotion; never remove the last active admin.
    if ($target !== null && $target['role'] === 'admin' && $role !== 'admin') {
        if ($isSelf) {
            $errors[] = 'You cannot change your own role.';
            $role = 'admin';
        } else {
            $row = tenant_fetch(
                "SELECT COUNT(*) AS n FROM users
                 WHERE school_id = :school_id AND role = 'admin' AND is_active = 1 AND id != :id",
                ['id' => (int)$target['id']]
            );
            if ((int)($row['n'] ?? 0) === 0) {
                $errors[] = 'You cannot demote the last active admin.';
            }
        }
    }

    if (!$errors) {
        $generatedPassword = null;
        try {
            $pdo = db();
            $pdo->beginTransaction();

            if ($target === null) {
                if ($password === '') {
                    $generatedPassword = bin2hex(random_bytes(6)); // 12 chars
                    $password = $generatedPassword;
                }
                tenant_query(
                    'INSERT INTO users (school_id, name, email, phone, role, password_hash)
                     VALUES (:school_id, :name, :email, :phone, :role, :hash)',
                    [
                        'name'  => $name,
                        'email' => $email,
                        'phone' => $phone !== '' ? $phone : null,
                        'role'  => $role,
                        'hash'  => password_hash($password, PASSWORD_DEFAULT),
                    ]
                );
                $targetId = (int)$pdo->lastInsertId();
            } else {
                $targetId = (int)$target['id'];
                $params = [
                    'id'    => $targetId,
                    'name'  => $name,
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : null,
                    'role'  => $role,
                ];
                $passwordSql = '';
                if ($password !== '') {
                    $passwordSql = ', password_hash = :hash';
                    $params['hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                tenant_query(
                    "UPDATE users SET name = :name, email = :email, phone = :phone, role = :role $passwordSql
                     WHERE id = :id AND school_id = :school_id",
                    $params
                );
            }

            // Sync default job types. The junction table has no school_id;
            // the user and every job type id were tenant-validated above.
            $pdo->prepare('DELETE FROM user_job_types WHERE user_id = ?')->execute([$targetId]);
            if ($jobTypeIds) {
                $ins = $pdo->prepare('INSERT INTO user_job_types (user_id, job_type_id) VALUES (?, ?)');
                foreach ($jobTypeIds as $jobTypeId) {
                    $ins->execute([$targetId, $jobTypeId]);
                }
            }

            $pdo->commit();

            if ($target === null) {
                flash($generatedPassword !== null
                    ? "$name was created. Temporary password: $generatedPassword — share it securely."
                    : "$name was created.");
            } else {
                flash("$name was updated." . ($password !== '' && $generatedPassword === null ? ' Password changed.' : ''));
            }
            redirect('/users.php');
        } catch (PDOException $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            if ($ex->getCode() === '23000') {
                $errors[] = 'That email address is already in use.';
            } else {
                error_log('[user_edit] ' . $ex->getMessage());
                $errors[] = 'Could not save the user. Try again.';
            }
        }
    }

    foreach ($errors as $error) {
        flash($error, 'error');
    }

    // Re-render with submitted values
    $old = ['name' => $name, 'email' => $email, 'phone' => $phone, 'role' => $role];
    $selectedJobTypeIds = $jobTypeIds;
} else {
    $selectedIds = $target
        ? tenant_fetch_all(
            'SELECT ujt.job_type_id FROM user_job_types ujt
             JOIN users u ON u.id = ujt.user_id
             WHERE ujt.user_id = :id AND u.school_id = :school_id',
            ['id' => (int)$target['id']]
        )
        : [];
    $selectedJobTypeIds = array_map(fn (array $r) => (int)$r['job_type_id'], $selectedIds);

    $old = [
        'name'  => $target['name'] ?? '',
        'email' => $target['email'] ?? '',
        'phone' => $target['phone'] ?? '',
        'role'  => $target['role'] ?? 'staff',
    ];
}

view('user_form', [
    'title'              => $target ? 'Edit user' : 'Add user',
    'active'             => 'users',
    'user'               => $user,
    'target'             => $target,
    'isSelf'             => $isSelf,
    'old'                => $old,
    'jobTypes'           => $jobTypes,
    'selectedJobTypeIds' => $selectedJobTypeIds,
]);
