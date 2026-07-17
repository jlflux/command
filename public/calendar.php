<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/schedule.php';

$user = require_login();

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month) || !DateTime::createFromFormat('Y-m-d', $month . '-01')) {
    $month = date('Y-m');
}
$first = new DateTime($month . '-01');
$last  = (clone $first)->modify('last day of this month');

$events = schedule_events(
    ['e.event_date BETWEEN :from AND :to'],
    ['from' => $first->format('Y-m-d'), 'to' => $last->format('Y-m-d')]
);

$byDay = [];
foreach ($events as $ev) {
    $byDay[$ev['event_date']][] = $ev;
}

// Build the grid: weeks of Sun..Sat covering the month.
$gridStart = clone $first;
if ($gridStart->format('w') !== '0') {
    $gridStart->modify('last sunday');
}
$gridEnd = clone $last;
if ($gridEnd->format('w') !== '6') {
    $gridEnd->modify('next saturday');
}

$weeks = [];
$cursor = clone $gridStart;
while ($cursor <= $gridEnd) {
    $week = [];
    for ($i = 0; $i < 7; $i++) {
        $week[] = $cursor->format('Y-m-d');
        $cursor->modify('+1 day');
    }
    $weeks[] = $week;
}

view('calendar', [
    'title'     => 'Calendar',
    'active'    => 'schedule',
    'user'      => $user,
    'month'     => $month,
    'monthLabel' => $first->format('F Y'),
    'prevMonth' => (clone $first)->modify('-1 month')->format('Y-m'),
    'nextMonth' => (clone $first)->modify('+1 month')->format('Y-m'),
    'weeks'     => $weeks,
    'byDay'     => $byDay,
    'readiness' => $events ? readiness_map(array_column($events, 'id')) : [],
    'canManage' => role_at_least($user['role'], 'manager'),
]);
