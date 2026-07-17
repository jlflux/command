<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/schedule.php';

$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$event = schedule_event($id) ?? not_found();

$needs = tenant_fetch(
    'SELECT * FROM event_needs WHERE event_id = :id AND school_id = :school_id',
    ['id' => $id]
);
$workerNeeds = tenant_fetch_all(
    'SELECT wn.workers_needed, jt.name AS job_type_name
     FROM event_worker_needs wn
     JOIN job_types jt ON jt.id = wn.job_type_id
     WHERE wn.event_id = :id AND wn.school_id = :school_id
     ORDER BY jt.sort_order, jt.name',
    ['id' => $id]
);

view('event_detail', [
    'title'       => event_title($event),
    'active'      => 'schedule',
    'user'        => $user,
    'event'       => $event,
    'needs'       => $needs,
    'workerNeeds' => $workerNeeds,
    'alerts'      => readiness_for_event($id),
    'canManage'   => role_at_least($user['role'], 'manager'),
]);
