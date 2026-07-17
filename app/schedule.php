<?php
declare(strict_types=1);

/**
 * Schedule module helpers: the readiness engine plus shared fetch/display
 * functions. Include from any page that touches events:
 *     require __DIR__ . '/../app/schedule.php';
 * (helpers.php must already be loaded — every entry script does that first.)
 */

// ---------------------------------------------------------------------------
// Readiness engine
//
// The platform's core promise: always know what's missing for each upcoming
// event. readiness_map() returns  [event_id => [alert, ...]]  where each
// alert is ['key' => ..., 'label' => ..., 'severity' => 'warn'|'crit'].
// The dashboard (Phase 8) and the schedule list both feed off this.
// ---------------------------------------------------------------------------

/**
 * Compute readiness alerts for upcoming scheduled events of the current
 * school. Pass $eventIds to restrict to specific events (e.g. one detail
 * page, or the ids visible on a list); null = all upcoming.
 *
 * Rules (an alert appears only where it applies):
 *  - ticket:    home competition with no ticket_link
 *  - broadcast: broadcast planned but no broadcast_link
 *  - opp_logo:  opponent attached but opponent has no logo
 *  - workers:   workers needed but not fully assigned (assignments arrive in
 *               Phase 4 — until then assigned counts are 0)
 *  - transport: transportation needed but not arranged
 *  - sponsor:   sponsor obligations due (stub until Phase 3)
 */
function readiness_map(?array $eventIds = null): array
{
    $params = [];
    $idFilter = '';
    if ($eventIds !== null) {
        $eventIds = array_values(array_filter(array_map('intval', $eventIds), fn (int $i) => $i > 0));
        if (!$eventIds) {
            return [];
        }
        // ids are forced to int above — safe to inline for the IN clause
        $idFilter = ' AND e.id IN (' . implode(',', $eventIds) . ')';
    }

    $rows = tenant_fetch_all(
        "SELECT e.id, e.home_away, e.ticket_link, e.broadcast_planned, e.broadcast_link,
                e.opponent_id, et.is_competition,
                o.logo_path AS opponent_logo,
                n.transportation_needed, n.transportation_arranged,
                COALESCE(w.total_needed, 0)   AS workers_needed,
                COALESCE(w.job_type_count, 0) AS worker_job_types
         FROM events e
         JOIN event_types et ON et.id = e.event_type_id
         LEFT JOIN opponents o ON o.id = e.opponent_id
         LEFT JOIN event_needs n ON n.event_id = e.id
         LEFT JOIN (
             SELECT event_id, SUM(workers_needed) AS total_needed, COUNT(*) AS job_type_count
             FROM event_worker_needs
             GROUP BY event_id
         ) w ON w.event_id = e.id
         WHERE e.school_id = :school_id
           AND e.status = 'scheduled'
           AND e.event_date >= CURDATE()" . $idFilter,
        $params
    );

    $map = [];
    foreach ($rows as $row) {
        $alerts = [];

        if ($row['is_competition'] && $row['home_away'] === 'home' && empty($row['ticket_link'])) {
            $alerts[] = ['key' => 'ticket', 'label' => 'No ticket link', 'severity' => 'warn'];
        }
        if ($row['broadcast_planned'] && empty($row['broadcast_link'])) {
            $alerts[] = ['key' => 'broadcast', 'label' => 'No broadcast link', 'severity' => 'warn'];
        }
        if ($row['opponent_id'] !== null && empty($row['opponent_logo'])) {
            $alerts[] = ['key' => 'opp_logo', 'label' => 'Opponent logo missing', 'severity' => 'warn'];
        }
        if ((int)$row['workers_needed'] > 0) {
            $assigned = readiness_workers_assigned((int)$row['id']);
            if ($assigned < (int)$row['workers_needed']) {
                $alerts[] = [
                    'key'      => 'workers',
                    'label'    => sprintf('Workers %d/%d', $assigned, (int)$row['workers_needed']),
                    'severity' => 'crit',
                ];
            }
        }
        if (!empty($row['transportation_needed']) && empty($row['transportation_arranged'])) {
            $alerts[] = ['key' => 'transport', 'label' => 'Transportation not arranged', 'severity' => 'crit'];
        }
        foreach (readiness_sponsor_obligations((int)$row['id']) as $obligation) {
            $alerts[] = ['key' => 'sponsor', 'label' => $obligation, 'severity' => 'warn'];
        }

        if ($alerts) {
            $map[(int)$row['id']] = $alerts;
        }
    }

    return $map;
}

/** Readiness for a single event (empty array = ready or not applicable). */
function readiness_for_event(int $eventId): array
{
    return readiness_map([$eventId])[$eventId] ?? [];
}

/**
 * Workers assigned to an event. Stub: the staffing module (Phase 4) will
 * count accepted assignments; until then nothing is assignable.
 */
function readiness_workers_assigned(int $eventId): int
{
    return 0;
}

/**
 * Sponsor obligations due for an event, as label strings.
 * Stub until the sponsorship module (Phase 3) exists.
 */
function readiness_sponsor_obligations(int $eventId): array
{
    return [];
}

// ---------------------------------------------------------------------------
// Shared fetch helpers (all tenant-scoped)
// ---------------------------------------------------------------------------

/** Active sports for selects. */
function schedule_sports(): array
{
    return tenant_fetch_all(
        'SELECT * FROM sports WHERE school_id = :school_id AND is_active = 1 ORDER BY sort_order, name'
    );
}

/** Active levels for selects. */
function schedule_levels(): array
{
    return tenant_fetch_all(
        'SELECT * FROM levels WHERE school_id = :school_id AND is_active = 1 ORDER BY sort_order, name'
    );
}

/** Active event types for selects. */
function schedule_event_types(): array
{
    return tenant_fetch_all(
        'SELECT * FROM event_types WHERE school_id = :school_id AND is_active = 1 ORDER BY sort_order, name'
    );
}

/** All opponents, alphabetical. */
function schedule_opponents(): array
{
    return tenant_fetch_all(
        'SELECT * FROM opponents WHERE school_id = :school_id ORDER BY name'
    );
}

/** All venues, home venues first. */
function schedule_venues(): array
{
    return tenant_fetch_all(
        'SELECT * FROM venues WHERE school_id = :school_id ORDER BY is_home DESC, sort_order, name'
    );
}

/**
 * Fetch events with everything the list/calendar/detail views need joined
 * in. $where fragments may reference the joined aliases; each must use bound
 * placeholders (never interpolate user input).
 */
function schedule_events(array $where = [], array $params = [], string $order = 'e.event_date, e.start_time, e.id'): array
{
    $conditions = array_merge(['e.school_id = :school_id'], $where);

    return tenant_fetch_all(
        'SELECT e.*,
                s.name AS sport_name, s.icon AS sport_icon,
                l.name AS level_name,
                et.name AS event_type_name, et.is_competition,
                o.name AS opponent_name, o.mascot AS opponent_mascot, o.logo_path AS opponent_logo,
                v.name AS venue_name
         FROM events e
         JOIN sports s       ON s.id = e.sport_id
         LEFT JOIN levels l  ON l.id = e.level_id
         JOIN event_types et ON et.id = e.event_type_id
         LEFT JOIN opponents o ON o.id = e.opponent_id
         LEFT JOIN venues v  ON v.id = e.venue_id
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY ' . $order,
        $params
    );
}

/** One event by id (with joins), tenant-scoped. Null if missing/foreign. */
function schedule_event(int $id): ?array
{
    $rows = schedule_events(['e.id = :id'], ['id' => $id]);
    return $rows[0] ?? null;
}

// ---------------------------------------------------------------------------
// Display helpers
// ---------------------------------------------------------------------------

/** "Varsity Football vs Central High" / "at Central High" / "Football Media Day". */
function event_title(array $e): string
{
    $team = trim(($e['level_name'] ?? '') . ' ' . $e['sport_name']);

    if (!$e['is_competition']) {
        return $team . ' — ' . $e['event_type_name'];
    }
    if (!empty($e['opponent_name'])) {
        $prefix = $e['home_away'] === 'away' ? 'at' : 'vs';
        return "$team $prefix {$e['opponent_name']}";
    }
    return $team;
}

/** Where the event happens, as display text. */
function event_location(array $e): string
{
    if (!empty($e['venue_name'])) {
        return $e['venue_name'];
    }
    if (!empty($e['location_text'])) {
        return $e['location_text'];
    }
    return ['home' => 'Home', 'away' => 'Away', 'neutral' => 'Neutral site'][$e['home_away']] ?? '';
}

/** "Fri, Aug 21" from a Y-m-d date. */
function fmt_date(string $date): string
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt ? $dt->format('D, M j') : $date;
}

/** "7:00 PM" from a H:i:s time, '' when null/empty. */
function fmt_time(?string $time): string
{
    if ($time === null || $time === '') {
        return '';
    }
    $dt = DateTime::createFromFormat('H:i:s', $time) ?: DateTime::createFromFormat('H:i', $time);
    return $dt ? $dt->format('g:i A') : $time;
}

/** "W 28-14" for completed events with a result. */
function event_result_label(array $e): string
{
    if ($e['status'] !== 'completed' || empty($e['result'])) {
        return '';
    }
    $label = $e['result'];
    if ($e['final_score_us'] !== null && $e['final_score_them'] !== null) {
        $label .= sprintf(' %d-%d', (int)$e['final_score_us'], (int)$e['final_score_them']);
    }
    return $label;
}
