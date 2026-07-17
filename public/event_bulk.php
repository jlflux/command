<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/schedule.php';

$user = require_role('manager');

const BULK_ROWS = 12;

$sports     = schedule_sports();
$levels     = schedule_levels();
$eventTypes = schedule_event_types();
$opponents  = schedule_opponents();
$venues     = schedule_venues();

$idsOf = fn (array $rows): array => array_map(fn (array $r) => (int)$r['id'], $rows);

$old = [
    'sport_id'      => (int)($_POST['sport_id'] ?? 0),
    'level_id'      => (int)($_POST['level_id'] ?? 0),
    'event_type_id' => (int)($_POST['event_type_id'] ?? 0),
    'rows'          => [],
];
if ($old['event_type_id'] === 0) {
    foreach ($eventTypes as $et) {
        if ($et['is_competition']) { $old['event_type_id'] = (int)$et['id']; break; }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $errors = [];
    $sportId = $old['sport_id'];
    $levelId = $old['level_id'] ?: null;
    $typeId  = $old['event_type_id'];

    if (!in_array($sportId, $idsOf($sports), true)) {
        $errors[] = 'Pick a sport for this batch.';
    }
    if ($levelId !== null && !in_array($levelId, $idsOf($levels), true)) {
        $errors[] = 'Invalid level.';
    }
    if (!in_array($typeId, $idsOf($eventTypes), true)) {
        $errors[] = 'Invalid event type.';
    }

    // Collect non-empty rows and validate everything before saving anything.
    $rows = [];
    $rawRows = (array)($_POST['rows'] ?? []);
    foreach ($rawRows as $i => $raw) {
        $row = [
            'date'     => trim($raw['date'] ?? ''),
            'time'     => trim($raw['time'] ?? ''),
            'opponent' => trim($raw['opponent'] ?? ''),
            'ha'       => $raw['ha'] ?? 'home',
            'location' => trim($raw['location'] ?? ''),
        ];
        $old['rows'][$i] = $row;

        if ($row['date'] === '' && $row['opponent'] === '' && $row['location'] === '') {
            continue; // blank row
        }
        $lineNo = (int)$i + 1;
        if (!DateTime::createFromFormat('Y-m-d', $row['date'])) {
            $errors[] = "Row $lineNo: a valid date is required.";
        }
        if ($row['time'] !== '' && !preg_match('/^\d{2}:\d{2}$/', $row['time'])) {
            $errors[] = "Row $lineNo: invalid time.";
        }
        if (!in_array($row['ha'], ['home', 'away', 'neutral'], true)) {
            $errors[] = "Row $lineNo: invalid home/away.";
        }
        if (mb_strlen($row['opponent']) > 150) {
            $errors[] = "Row $lineNo: opponent name too long.";
        }
        $rows[] = $row;
    }

    if (!$rows && !$errors) {
        $errors[] = 'No rows filled in.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // Map existing opponent names (case-insensitive) for this school.
            $opponentIdByName = [];
            foreach ($opponents as $o) {
                $opponentIdByName[mb_strtolower($o['name'])] = (int)$o['id'];
            }

            $added = 0;
            foreach ($rows as $row) {
                $opponentId = null;
                if ($row['opponent'] !== '') {
                    $key = mb_strtolower($row['opponent']);
                    if (!isset($opponentIdByName[$key])) {
                        // Auto-create a bare opponent; details can be filled in later.
                        tenant_query(
                            'INSERT INTO opponents (school_id, name) VALUES (:school_id, :name)',
                            ['name' => $row['opponent']]
                        );
                        $opponentIdByName[$key] = (int)$pdo->lastInsertId();
                    }
                    $opponentId = $opponentIdByName[$key];
                }

                tenant_query(
                    'INSERT INTO events (school_id, sport_id, level_id, event_type_id, opponent_id,
                                         home_away, location_text, event_date, start_time)
                     VALUES (:school_id, :sport_id, :level_id, :event_type_id, :opponent_id,
                             :home_away, :location_text, :event_date, :start_time)',
                    [
                        'sport_id'      => $sportId,
                        'level_id'      => $levelId,
                        'event_type_id' => $typeId,
                        'opponent_id'   => $opponentId,
                        'home_away'     => $row['ha'],
                        'location_text' => $row['location'] !== '' ? $row['location'] : null,
                        'event_date'    => $row['date'],
                        'start_time'    => $row['time'] !== '' ? $row['time'] : null,
                    ]
                );
                $eventId = (int)$pdo->lastInsertId();
                tenant_query(
                    'INSERT INTO event_needs (event_id, school_id) VALUES (:event_id, :school_id)',
                    ['event_id' => $eventId]
                );
                $added++;
            }

            $pdo->commit();
            flash("$added event(s) added.");
            redirect('/schedule.php?view=upcoming&sport=' . $sportId);
        } catch (PDOException $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log('[event_bulk] ' . $ex->getMessage());
            $errors[] = 'Could not save the events. Nothing was added — try again.';
        }
    }

    foreach ($errors as $error) {
        flash($error, 'error');
    }
}

view('event_bulk', [
    'title'      => 'Bulk add events',
    'active'     => 'schedule',
    'user'       => $user,
    'old'        => $old,
    'rowCount'   => max(BULK_ROWS, count($old['rows'])),
    'sports'     => $sports,
    'levels'     => $levels,
    'eventTypes' => $eventTypes,
    'opponents'  => $opponents,
]);
