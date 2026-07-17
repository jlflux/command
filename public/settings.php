<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/migrate.php';

$user = require_role('admin');
$schoolId = current_school_id();

$isHex = fn (string $c): bool => (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $c);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    try {
        switch ($_POST['action'] ?? '') {
            case 'identity':
                $name   = trim($_POST['school_name'] ?? '');
                $mascot = trim($_POST['mascot'] ?? '');
                $tz     = $_POST['timezone'] ?? '';
                if ($name === '' || mb_strlen($name) > 150) {
                    flash('School name is required (max 150 characters).', 'error');
                    break;
                }
                if (!in_array($tz, DateTimeZone::listIdentifiers(), true)) {
                    flash('Invalid timezone.', 'error');
                    break;
                }
                // schools.id IS the tenant key here.
                db()->prepare('UPDATE schools SET name = ? WHERE id = ?')->execute([$name, $schoolId]);
                set_school_setting('mascot', mb_substr($mascot, 0, 80));
                set_school_setting('timezone', $tz);
                flash('Identity saved.');
                break;

            case 'colors':
                $primary   = strtolower(trim($_POST['color_primary'] ?? ''));
                $secondary = strtolower(trim($_POST['color_secondary'] ?? ''));
                if (!$isHex($primary) || !$isHex($secondary)) {
                    flash('Colors must be hex values like #1d4ed8.', 'error');
                    break;
                }
                set_school_setting('color_primary', $primary);
                set_school_setting('color_secondary', $secondary);
                flash('Colors saved — the theme is updated.');
                break;

            case 'logo':
                $file = $_FILES['logo'] ?? null;
                if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                    flash('Upload failed — choose a file and try again.', 'error');
                    break;
                }
                if ($file['size'] > 2 * 1024 * 1024) {
                    flash('Logo must be 2 MB or smaller.', 'error');
                    break;
                }
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
                if (!isset($extensions[$mime])) {
                    flash('Logo must be a PNG, JPG, or WebP image.', 'error');
                    break;
                }
                $ext = $extensions[$mime];
                $dir = __DIR__ . '/uploads/' . $schoolId;
                if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                    flash('Could not create the upload directory.', 'error');
                    break;
                }
                foreach (glob($dir . '/logo.*') ?: [] as $oldLogo) {
                    unlink($oldLogo);
                }
                if (!move_uploaded_file($file['tmp_name'], $dir . '/logo.' . $ext)) {
                    flash('Could not save the uploaded file.', 'error');
                    break;
                }
                set_school_setting('logo_path', 'uploads/' . $schoolId . '/logo.' . $ext);
                flash('Logo uploaded.');
                break;

            case 'level_add':
                $name = trim($_POST['name'] ?? '');
                $color = strtolower(trim($_POST['color'] ?? '#1d4ed8'));
                if ($name === '' || mb_strlen($name) > 80 || !$isHex($color)) {
                    flash('Level needs a name (max 80 chars) and a valid color.', 'error');
                    break;
                }
                tenant_query(
                    'INSERT INTO sponsorship_levels (school_id, name, color, sort_order)
                     VALUES (:school_id, :name, :color, :sort_order)',
                    ['name' => $name, 'color' => $color, 'sort_order' => (int)($_POST['sort_order'] ?? 0)]
                );
                flash("Sponsorship level \"$name\" added.");
                break;

            case 'level_row':
                $id = (int)($_POST['id'] ?? 0);
                $level = tenant_fetch(
                    'SELECT * FROM sponsorship_levels WHERE id = :id AND school_id = :school_id',
                    ['id' => $id]
                ) ?? not_found();
                if (($_POST['do'] ?? '') === 'delete') {
                    tenant_query(
                        'DELETE FROM sponsorship_levels WHERE id = :id AND school_id = :school_id',
                        ['id' => $id]
                    );
                    flash("Sponsorship level \"{$level['name']}\" deleted.");
                    break;
                }
                $name = trim($_POST['name'] ?? '');
                $color = strtolower(trim($_POST['color'] ?? ''));
                if ($name === '' || mb_strlen($name) > 80 || !$isHex($color)) {
                    flash('Level needs a name (max 80 chars) and a valid color.', 'error');
                    break;
                }
                tenant_query(
                    'UPDATE sponsorship_levels SET name = :name, color = :color, sort_order = :sort_order
                     WHERE id = :id AND school_id = :school_id',
                    ['id' => $id, 'name' => $name, 'color' => $color, 'sort_order' => (int)($_POST['sort_order'] ?? 0)]
                );
                flash('Sponsorship level saved.');
                break;

            case 'jobtype_add':
                $name = trim($_POST['name'] ?? '');
                if ($name === '' || mb_strlen($name) > 80) {
                    flash('Job type needs a name (max 80 characters).', 'error');
                    break;
                }
                $max = tenant_fetch(
                    'SELECT COALESCE(MAX(sort_order), 0) AS m FROM job_types WHERE school_id = :school_id'
                );
                tenant_query(
                    'INSERT INTO job_types (school_id, name, sort_order)
                     VALUES (:school_id, :name, :sort_order)',
                    ['name' => $name, 'sort_order' => (int)($max['m'] ?? 0) + 10]
                );
                flash("Job type \"$name\" added.");
                break;

            case 'jobtype_row':
                $id = (int)($_POST['id'] ?? 0);
                $jobType = tenant_fetch(
                    'SELECT * FROM job_types WHERE id = :id AND school_id = :school_id',
                    ['id' => $id]
                ) ?? not_found();
                if (($_POST['do'] ?? '') === 'toggle') {
                    tenant_query(
                        'UPDATE job_types SET is_active = 1 - is_active WHERE id = :id AND school_id = :school_id',
                        ['id' => $id]
                    );
                    flash($jobType['is_active']
                        ? "Job type \"{$jobType['name']}\" deactivated."
                        : "Job type \"{$jobType['name']}\" reactivated.");
                    break;
                }
                $name = trim($_POST['name'] ?? '');
                if ($name === '' || mb_strlen($name) > 80) {
                    flash('Job type needs a name (max 80 characters).', 'error');
                    break;
                }
                tenant_query(
                    'UPDATE job_types SET name = :name, sort_order = :sort_order
                     WHERE id = :id AND school_id = :school_id',
                    ['id' => $id, 'name' => $name, 'sort_order' => (int)($_POST['sort_order'] ?? 0)]
                );
                flash('Job type saved.');
                break;

            case 'sport_add':
            case 'level_add':
            case 'eventtype_add':
            case 'venue_add': {
                $table = ['sport_add' => 'sports', 'level_add' => 'levels',
                          'eventtype_add' => 'event_types', 'venue_add' => 'venues'][$_POST['action']];
                $name = trim($_POST['name'] ?? '');
                if ($name === '' || mb_strlen($name) > ($table === 'venues' || $table === 'opponents' ? 150 : 80)) {
                    flash('A name is required (keep it short).', 'error');
                    break;
                }
                $max = tenant_fetch(
                    "SELECT COALESCE(MAX(sort_order), 0) AS m FROM $table WHERE school_id = :school_id"
                );
                $sort = (int)($max['m'] ?? 0) + 10;
                switch ($table) {
                    case 'sports':
                        tenant_query(
                            'INSERT INTO sports (school_id, name, icon, sort_order) VALUES (:school_id, :name, :icon, :sort)',
                            ['name' => $name, 'icon' => mb_substr(trim($_POST['icon'] ?? ''), 0, 8), 'sort' => $sort]
                        );
                        break;
                    case 'levels':
                        tenant_query(
                            'INSERT INTO levels (school_id, name, sort_order) VALUES (:school_id, :name, :sort)',
                            ['name' => $name, 'sort' => $sort]
                        );
                        break;
                    case 'event_types':
                        tenant_query(
                            'INSERT INTO event_types (school_id, name, is_competition, sort_order)
                             VALUES (:school_id, :name, :comp, :sort)',
                            ['name' => $name, 'comp' => isset($_POST['is_competition']) ? 1 : 0, 'sort' => $sort]
                        );
                        break;
                    case 'venues':
                        tenant_query(
                            'INSERT INTO venues (school_id, name, address, is_home, sort_order)
                             VALUES (:school_id, :name, :address, :home, :sort)',
                            ['name' => $name, 'address' => trim($_POST['address'] ?? '') ?: null,
                             'home' => isset($_POST['is_home']) ? 1 : 0, 'sort' => $sort]
                        );
                        break;
                }
                flash("\"$name\" added.");
                break;
            }

            case 'sport_row':
            case 'level_row_sched':
            case 'eventtype_row':
            case 'venue_row': {
                $table = ['sport_row' => 'sports', 'level_row_sched' => 'levels',
                          'eventtype_row' => 'event_types', 'venue_row' => 'venues'][$_POST['action']];
                $id = (int)($_POST['id'] ?? 0);
                $row = tenant_fetch(
                    "SELECT * FROM $table WHERE id = :id AND school_id = :school_id",
                    ['id' => $id]
                ) ?? not_found();
                $do = $_POST['do'] ?? 'save';

                if ($do === 'toggle' && $table !== 'venues') {
                    tenant_query(
                        "UPDATE $table SET is_active = 1 - is_active WHERE id = :id AND school_id = :school_id",
                        ['id' => $id]
                    );
                    flash("\"{$row['name']}\" " . ($row['is_active'] ? 'deactivated.' : 'reactivated.'));
                    break;
                }
                if ($do === 'delete' && $table === 'venues') {
                    // events.venue_id is ON DELETE SET NULL — safe to remove.
                    tenant_query(
                        'DELETE FROM venues WHERE id = :id AND school_id = :school_id',
                        ['id' => $id]
                    );
                    flash("\"{$row['name']}\" deleted.");
                    break;
                }

                $name = trim($_POST['name'] ?? '');
                if ($name === '' || mb_strlen($name) > 150) {
                    flash('A name is required.', 'error');
                    break;
                }
                $sort = (int)($_POST['sort_order'] ?? $row['sort_order']);
                switch ($table) {
                    case 'sports':
                        tenant_query(
                            'UPDATE sports SET name = :name, icon = :icon, sort_order = :sort
                             WHERE id = :id AND school_id = :school_id',
                            ['id' => $id, 'name' => $name,
                             'icon' => mb_substr(trim($_POST['icon'] ?? ''), 0, 8), 'sort' => $sort]
                        );
                        break;
                    case 'levels':
                        tenant_query(
                            'UPDATE levels SET name = :name, sort_order = :sort
                             WHERE id = :id AND school_id = :school_id',
                            ['id' => $id, 'name' => $name, 'sort' => $sort]
                        );
                        break;
                    case 'event_types':
                        tenant_query(
                            'UPDATE event_types SET name = :name, is_competition = :comp, sort_order = :sort
                             WHERE id = :id AND school_id = :school_id',
                            ['id' => $id, 'name' => $name,
                             'comp' => isset($_POST['is_competition']) ? 1 : 0, 'sort' => $sort]
                        );
                        break;
                    case 'venues':
                        tenant_query(
                            'UPDATE venues SET name = :name, address = :address, is_home = :home, sort_order = :sort
                             WHERE id = :id AND school_id = :school_id',
                            ['id' => $id, 'name' => $name, 'address' => trim($_POST['address'] ?? '') ?: null,
                             'home' => isset($_POST['is_home']) ? 1 : 0, 'sort' => $sort]
                        );
                        break;
                }
                flash('Saved.');
                break;
            }

            case 'migrate':
                $ran = run_pending_migrations();
                flash($ran ? 'Applied: ' . implode(', ', $ran) : 'Database already up to date.');
                break;

            default:
                flash('Unknown action.', 'error');
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            flash('That name is already in use.', 'error');
        } else {
            error_log('[settings] ' . $ex->getMessage());
            flash('Could not save. Try again.', 'error');
        }
    }

    redirect('/settings.php');
}

$levels = tenant_fetch_all(
    'SELECT * FROM sponsorship_levels WHERE school_id = :school_id ORDER BY sort_order, name'
);
$jobTypes = tenant_fetch_all(
    'SELECT * FROM job_types WHERE school_id = :school_id ORDER BY is_active DESC, sort_order, name'
);
$maxSort = $levels ? max(array_map(fn (array $l) => (int)$l['sort_order'], $levels)) : 0;

// Schedule lookups may not exist yet if 003 hasn't been applied — the page
// must still render so the admin can reach the "apply migrations" button.
$scheduleLookups = ['sports' => [], 'schedLevels' => [], 'eventTypes' => [], 'venues' => []];
try {
    $scheduleLookups['sports'] = tenant_fetch_all(
        'SELECT * FROM sports WHERE school_id = :school_id ORDER BY is_active DESC, sort_order, name'
    );
    $scheduleLookups['schedLevels'] = tenant_fetch_all(
        'SELECT * FROM levels WHERE school_id = :school_id ORDER BY is_active DESC, sort_order, name'
    );
    $scheduleLookups['eventTypes'] = tenant_fetch_all(
        'SELECT * FROM event_types WHERE school_id = :school_id ORDER BY is_active DESC, sort_order, name'
    );
    $scheduleLookups['venues'] = tenant_fetch_all(
        'SELECT * FROM venues WHERE school_id = :school_id ORDER BY is_home DESC, sort_order, name'
    );
    $scheduleTablesReady = true;
} catch (PDOException) {
    $scheduleTablesReady = false;
}

view('settings', [
    'title'               => 'School settings',
    'active'              => 'settings',
    'user'                => $user,
    'school'              => current_school(),
    'levels'              => $levels,
    'jobTypes'            => $jobTypes,
    'nextLevelSort'       => $maxSort + 10,
    'timezones'           => DateTimeZone::listIdentifiers(),
    'pendingMigrations'   => migrations_pending(),
    'scheduleTablesReady' => $scheduleTablesReady,
    ...$scheduleLookups,
]);
