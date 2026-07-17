<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/schedule.php';

$user = require_role('manager');

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$event = null;
if ($id > 0) {
    $event = tenant_fetch(
        'SELECT * FROM events WHERE id = :id AND school_id = :school_id',
        ['id' => $id]
    ) ?? not_found();
}

$sports     = schedule_sports();
$levels     = schedule_levels();
$eventTypes = schedule_event_types();
$opponents  = schedule_opponents();
$venues     = schedule_venues();
$jobTypes   = tenant_fetch_all(
    'SELECT * FROM job_types WHERE school_id = :school_id AND is_active = 1 ORDER BY sort_order, name'
);

$idsOf = fn (array $rows): array => array_map(fn (array $r) => (int)$r['id'], $rows);

// --- Delete -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_csrf();
    if ($event === null) {
        not_found();
    }
    tenant_query('DELETE FROM events WHERE id = :id AND school_id = :school_id', ['id' => $id]);
    flash('Event deleted.');
    redirect('/schedule.php');
}

// --- Save -------------------------------------------------------------------
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $in = [
        'sport_id'      => (int)($_POST['sport_id'] ?? 0),
        'level_id'      => (int)($_POST['level_id'] ?? 0) ?: null,
        'event_type_id' => (int)($_POST['event_type_id'] ?? 0),
        'opponent_id'   => (int)($_POST['opponent_id'] ?? 0) ?: null,
        'home_away'     => $_POST['home_away'] ?? 'home',
        'venue_id'      => (int)($_POST['venue_id'] ?? 0) ?: null,
        'location_text' => trim($_POST['location_text'] ?? '') ?: null,
        'event_date'    => trim($_POST['event_date'] ?? ''),
        'start_time'    => trim($_POST['start_time'] ?? '') ?: null,
        'ticket_link'   => trim($_POST['ticket_link'] ?? '') ?: null,
        'broadcast_planned' => isset($_POST['broadcast_planned']) ? 1 : 0,
        'broadcast_link'    => trim($_POST['broadcast_link'] ?? '') ?: null,
        'status'        => $_POST['status'] ?? 'scheduled',
        'final_score_us'   => ($_POST['final_score_us'] ?? '') !== '' ? (int)$_POST['final_score_us'] : null,
        'final_score_them' => ($_POST['final_score_them'] ?? '') !== '' ? (int)$_POST['final_score_them'] : null,
        'result'        => $_POST['result'] ?? '',
        'notes'         => trim($_POST['notes'] ?? '') ?: null,
    ];

    if (!in_array($in['sport_id'], $idsOf($sports), true)) {
        $errors[] = 'Pick a sport.';
    }
    if ($in['level_id'] !== null && !in_array($in['level_id'], $idsOf($levels), true)) {
        $errors[] = 'Invalid level.';
    }
    if (!in_array($in['event_type_id'], $idsOf($eventTypes), true)) {
        $errors[] = 'Pick an event type.';
    }
    if ($in['opponent_id'] !== null && !in_array($in['opponent_id'], $idsOf($opponents), true)) {
        $errors[] = 'Invalid opponent.';
    }
    if (!in_array($in['home_away'], ['home', 'away', 'neutral'], true)) {
        $errors[] = 'Invalid home/away value.';
    }
    if ($in['venue_id'] !== null && !in_array($in['venue_id'], $idsOf($venues), true)) {
        $errors[] = 'Invalid venue.';
    }
    if (!DateTime::createFromFormat('Y-m-d', $in['event_date'])) {
        $errors[] = 'A valid date is required.';
    }
    if ($in['start_time'] !== null && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $in['start_time'])) {
        $errors[] = 'Invalid start time.';
    }
    foreach (['ticket_link', 'broadcast_link'] as $urlField) {
        if ($in[$urlField] !== null && !filter_var($in[$urlField], FILTER_VALIDATE_URL)) {
            $errors[] = 'Links must be full URLs (https://...).';
            break;
        }
    }
    if (!in_array($in['status'], ['scheduled', 'completed', 'postponed', 'cancelled'], true)) {
        $errors[] = 'Invalid status.';
    }
    if (!in_array($in['result'], ['', 'W', 'L', 'T'], true)) {
        $errors[] = 'Invalid result.';
    }

    // Derive result from scores when completed and not set explicitly.
    if ($in['status'] === 'completed' && $in['result'] === ''
        && $in['final_score_us'] !== null && $in['final_score_them'] !== null) {
        $in['result'] = $in['final_score_us'] <=> $in['final_score_them'];
        $in['result'] = [1 => 'W', 0 => 'T', -1 => 'L'][$in['result']];
    }
    if ($in['status'] !== 'completed') {
        $in['result'] = '';
    }

    // Needs
    $needs = [
        'equipment_needed'        => trim($_POST['equipment_needed'] ?? '') ?: null,
        'social_content_needed'   => trim($_POST['social_content_needed'] ?? '') ?: null,
        'transportation_needed'   => isset($_POST['transportation_needed']) ? 1 : 0,
        'transportation_details'  => trim($_POST['transportation_details'] ?? '') ?: null,
        'transportation_arranged' => isset($_POST['transportation_arranged']) ? 1 : 0,
    ];
    $workerNeeds = [];
    $validJobTypeIds = $idsOf($jobTypes);
    foreach ((array)($_POST['workers'] ?? []) as $jobTypeId => $count) {
        $jobTypeId = (int)$jobTypeId;
        $count = (int)$count;
        if ($count > 0 && in_array($jobTypeId, $validJobTypeIds, true)) {
            $workerNeeds[$jobTypeId] = min($count, 999);
        }
    }

    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $fields = [
                'sport_id' => $in['sport_id'], 'level_id' => $in['level_id'],
                'event_type_id' => $in['event_type_id'], 'opponent_id' => $in['opponent_id'],
                'home_away' => $in['home_away'], 'venue_id' => $in['venue_id'],
                'location_text' => $in['location_text'], 'event_date' => $in['event_date'],
                'start_time' => $in['start_time'], 'ticket_link' => $in['ticket_link'],
                'broadcast_planned' => $in['broadcast_planned'], 'broadcast_link' => $in['broadcast_link'],
                'status' => $in['status'], 'final_score_us' => $in['final_score_us'],
                'final_score_them' => $in['final_score_them'],
                'result' => $in['result'] !== '' ? $in['result'] : null,
                'notes' => $in['notes'],
            ];

            if ($event === null) {
                tenant_query(
                    'INSERT INTO events (school_id, sport_id, level_id, event_type_id, opponent_id, home_away,
                                         venue_id, location_text, event_date, start_time, ticket_link,
                                         broadcast_planned, broadcast_link, status, final_score_us,
                                         final_score_them, result, notes)
                     VALUES (:school_id, :sport_id, :level_id, :event_type_id, :opponent_id, :home_away,
                             :venue_id, :location_text, :event_date, :start_time, :ticket_link,
                             :broadcast_planned, :broadcast_link, :status, :final_score_us,
                             :final_score_them, :result, :notes)',
                    $fields
                );
                $eventId = (int)$pdo->lastInsertId();
            } else {
                $eventId = $id;
                $set = implode(', ', array_map(fn (string $k) => "$k = :$k", array_keys($fields)));
                tenant_query(
                    "UPDATE events SET $set WHERE id = :id AND school_id = :school_id",
                    $fields + ['id' => $eventId]
                );
            }

            tenant_query(
                'INSERT INTO event_needs (event_id, school_id, equipment_needed, social_content_needed,
                                          transportation_needed, transportation_details, transportation_arranged)
                 VALUES (:event_id, :school_id, :equipment_needed, :social_content_needed,
                         :transportation_needed, :transportation_details, :transportation_arranged)
                 ON DUPLICATE KEY UPDATE
                     equipment_needed = VALUES(equipment_needed),
                     social_content_needed = VALUES(social_content_needed),
                     transportation_needed = VALUES(transportation_needed),
                     transportation_details = VALUES(transportation_details),
                     transportation_arranged = VALUES(transportation_arranged)',
                $needs + ['event_id' => $eventId]
            );

            // Replace worker needs (event + job types are tenant-validated above).
            $pdo->prepare('DELETE FROM event_worker_needs WHERE event_id = ?')->execute([$eventId]);
            if ($workerNeeds) {
                $ins = $pdo->prepare(
                    'INSERT INTO event_worker_needs (school_id, event_id, job_type_id, workers_needed) VALUES (?, ?, ?, ?)'
                );
                foreach ($workerNeeds as $jobTypeId => $count) {
                    $ins->execute([current_school_id(), $eventId, $jobTypeId, $count]);
                }
            }

            $pdo->commit();
            flash($event === null ? 'Event added.' : 'Event updated.');

            if (isset($_POST['save_add_another'])) {
                // Fast entry: keep sport/level/type/date context for the next one.
                redirect('/event_edit.php?' . http_build_query([
                    'sport' => $in['sport_id'], 'level' => $in['level_id'],
                    'type' => $in['event_type_id'], 'date' => $in['event_date'],
                ]));
            }
            redirect('/event.php?id=' . $eventId);
        } catch (PDOException $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log('[event_edit] ' . $ex->getMessage());
            $errors[] = 'Could not save the event. Try again.';
        }
    }

    foreach ($errors as $error) {
        flash($error, 'error');
    }
    $old = $in;
    $oldNeeds = $needs;
    $oldWorkers = $workerNeeds;
} else {
    // Prefill: existing event, or fast-entry GET params, or defaults.
    $defaultType = null;
    foreach ($eventTypes as $et) {
        if ($et['is_competition']) { $defaultType = (int)$et['id']; break; }
    }
    $old = [
        'sport_id'      => (int)($event['sport_id'] ?? $_GET['sport'] ?? 0),
        'level_id'      => $event ? (int)$event['level_id'] : (int)($_GET['level'] ?? 0),
        'event_type_id' => (int)($event['event_type_id'] ?? $_GET['type'] ?? $defaultType ?? 0),
        'opponent_id'   => (int)($event['opponent_id'] ?? 0),
        'home_away'     => $event['home_away'] ?? 'home',
        'venue_id'      => (int)($event['venue_id'] ?? 0),
        'location_text' => $event['location_text'] ?? '',
        'event_date'    => $event['event_date'] ?? ($_GET['date'] ?? date('Y-m-d')),
        'start_time'    => $event ? substr((string)$event['start_time'], 0, 5) : '',
        'ticket_link'   => $event['ticket_link'] ?? '',
        'broadcast_planned' => (int)($event['broadcast_planned'] ?? 0),
        'broadcast_link'    => $event['broadcast_link'] ?? '',
        'status'        => $event['status'] ?? 'scheduled',
        'final_score_us'   => $event['final_score_us'] ?? '',
        'final_score_them' => $event['final_score_them'] ?? '',
        'result'        => $event['result'] ?? '',
        'notes'         => $event['notes'] ?? '',
    ];

    $needsRow = $event ? tenant_fetch(
        'SELECT * FROM event_needs WHERE event_id = :id AND school_id = :school_id',
        ['id' => $id]
    ) : null;
    $oldNeeds = [
        'equipment_needed'        => $needsRow['equipment_needed'] ?? '',
        'social_content_needed'   => $needsRow['social_content_needed'] ?? '',
        'transportation_needed'   => (int)($needsRow['transportation_needed'] ?? 0),
        'transportation_details'  => $needsRow['transportation_details'] ?? '',
        'transportation_arranged' => (int)($needsRow['transportation_arranged'] ?? 0),
    ];

    $oldWorkers = [];
    if ($event) {
        $rows = tenant_fetch_all(
            'SELECT job_type_id, workers_needed FROM event_worker_needs
             WHERE event_id = :id AND school_id = :school_id',
            ['id' => $id]
        );
        foreach ($rows as $row) {
            $oldWorkers[(int)$row['job_type_id']] = (int)$row['workers_needed'];
        }
    }
}

view('event_form', [
    'title'      => $event ? 'Edit event' : 'Add event',
    'active'     => 'schedule',
    'user'       => $user,
    'event'      => $event,
    'old'        => $old,
    'oldNeeds'   => $oldNeeds,
    'oldWorkers' => $oldWorkers,
    'sports'     => $sports,
    'levels'     => $levels,
    'eventTypes' => $eventTypes,
    'opponents'  => $opponents,
    'venues'     => $venues,
    'jobTypes'   => $jobTypes,
]);
