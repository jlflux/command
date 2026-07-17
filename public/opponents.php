<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/schedule.php';

$user = require_role('manager');

// POST: delete an opponent (blocked by FK if events reference it)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $opponent = tenant_fetch(
            'SELECT * FROM opponents WHERE id = :id AND school_id = :school_id',
            ['id' => $id]
        ) ?? not_found();

        try {
            tenant_query('DELETE FROM opponents WHERE id = :id AND school_id = :school_id', ['id' => $id]);
            if (!empty($opponent['logo_path'])) {
                $abs = __DIR__ . '/' . $opponent['logo_path'];
                if (is_file($abs)) {
                    unlink($abs);
                }
            }
            flash("{$opponent['name']} deleted.");
        } catch (PDOException) {
            flash("{$opponent['name']} has events on the schedule and can't be deleted.", 'error');
        }
    }

    redirect('/opponents.php');
}

$opponents = tenant_fetch_all(
    'SELECT o.*, COUNT(e.id) AS event_count
     FROM opponents o
     LEFT JOIN events e ON e.opponent_id = o.id
     WHERE o.school_id = :school_id
     GROUP BY o.id
     ORDER BY o.name'
);

view('opponents_list', [
    'title'     => 'Opponents',
    'active'    => 'schedule',
    'user'      => $user,
    'opponents' => $opponents,
]);
