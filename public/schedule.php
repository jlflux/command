<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/schedule.php';

$user = require_login();

// --- Filters ----------------------------------------------------------------
$view    = $_GET['view'] ?? 'week';
$sportId = (int)($_GET['sport'] ?? 0);
$levelId = (int)($_GET['level'] ?? 0);
$ha      = $_GET['ha'] ?? '';
$from    = $_GET['from'] ?? '';
$to      = $_GET['to'] ?? '';

$validDate = fn (string $d): bool => DateTime::createFromFormat('Y-m-d', $d) !== false;
if (!$validDate($from)) { $from = ''; }
if (!$validDate($to))   { $to = ''; }
if (($from || $to) && $view === 'week') { $view = 'range'; }
if (!in_array($view, ['week', 'upcoming', 'past', 'all', 'range'], true)) { $view = 'week'; }

$where = [];
$params = [];

switch ($view) {
    case 'week':
        $monday = new DateTime('monday this week');
        $sunday = (clone $monday)->modify('+6 days');
        $where[] = 'e.event_date BETWEEN :from AND :to';
        $params['from'] = $monday->format('Y-m-d');
        $params['to']   = $sunday->format('Y-m-d');
        break;
    case 'upcoming':
        $where[] = 'e.event_date >= CURDATE()';
        break;
    case 'past':
        $where[] = 'e.event_date < CURDATE()';
        break;
    case 'range':
        if ($from) { $where[] = 'e.event_date >= :from'; $params['from'] = $from; }
        if ($to)   { $where[] = 'e.event_date <= :to';   $params['to'] = $to; }
        break;
    case 'all':
        break;
}

if ($sportId > 0) { $where[] = 'e.sport_id = :sport_id'; $params['sport_id'] = $sportId; }
if ($levelId > 0) { $where[] = 'e.level_id = :level_id'; $params['level_id'] = $levelId; }
if (in_array($ha, ['home', 'away', 'neutral'], true)) {
    $where[] = 'e.home_away = :ha';
    $params['ha'] = $ha;
} else {
    $ha = '';
}

$order = $view === 'past' ? 'e.event_date DESC, e.start_time DESC, e.id DESC' : 'e.event_date, e.start_time, e.id';
$events = schedule_events($where, $params, $order);
$readiness = $events ? readiness_map(array_column($events, 'id')) : [];

view('schedule_list', [
    'title'     => 'Schedule',
    'active'    => 'schedule',
    'user'      => $user,
    'events'    => $events,
    'readiness' => $readiness,
    'sports'    => schedule_sports(),
    'levels'    => schedule_levels(),
    'filters'   => ['view' => $view, 'sport' => $sportId, 'level' => $levelId, 'ha' => $ha, 'from' => $from, 'to' => $to],
    'canManage' => role_at_least($user['role'], 'manager'),
]);
