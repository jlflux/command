<?php
declare(strict_types=1);

/**
 * Default per-school data, inserted whenever a new school is created
 * (installer today; self-serve school signup later).
 *
 * NOTE: this runs before any user session exists, so it takes an explicit
 * $schoolId and uses plain PDO — an intentional, contained exception to the
 * tenant_query() rule. Keep these lists in sync with the backfill seeds in
 * migrations/002_auth.sql.
 */

/** name => sort_order */
const DEFAULT_JOB_TYPES = [
    'Ticket Worker'        => 10,
    'Gate'                 => 20,
    'PA Announcer'         => 30,
    'Scoreboard Operator'  => 40,
    'Video Board Operator' => 50,
    'Broadcast'            => 60,
    'Security'             => 70,
    'Athletic Trainer'     => 80,
    'Concessions'          => 90,
    'Intern'               => 100,
    'Bus Driver'           => 110,
    'Game Administrator'   => 120,
];

/** [name, color, sort_order] */
const DEFAULT_SPONSORSHIP_LEVELS = [
    ['Red',             '#dc2626', 10],
    ['White',           '#ffffff', 20],
    ['Blue',            '#2563eb', 30],
    ['Patriot Partner', '#1e3a8a', 40],
];

/** name => [icon, sort_order] */
const DEFAULT_SPORTS = [
    'Football'           => ['🏈', 10],
    'Volleyball'         => ['🏐', 20],
    'Basketball (Boys)'  => ['🏀', 30],
    'Basketball (Girls)' => ['🏀', 40],
    'Baseball'           => ['⚾', 50],
    'Softball'           => ['🥎', 60],
    'Soccer (Boys)'      => ['⚽', 70],
    'Soccer (Girls)'     => ['⚽', 80],
    'Track & Field'      => ['🏃', 90],
    'Cross Country'      => ['🏃', 100],
    'Swimming'           => ['🏊', 110],
    'Wrestling'          => ['🤼', 120],
    'Tennis'             => ['🎾', 130],
    'Golf'               => ['⛳', 140],
    'Cheer'              => ['📣', 150],
];

/** name => sort_order */
const DEFAULT_LEVELS = [
    'Varsity'       => 10,
    'JV'            => 20,
    'Freshman'      => 30,
    'Middle School' => 40,
];

/** name => [is_competition, sort_order] */
const DEFAULT_EVENT_TYPES = [
    'Competition'   => [1, 10],
    'Media Day'     => [0, 20],
    'Banquet'       => [0, 30],
    'Special Event' => [0, 40],
    'Practice'      => [0, 50],
];

/** setting_key => default value */
const DEFAULT_SCHOOL_SETTINGS = [
    'color_primary'   => '#1d4ed8',
    'color_secondary' => '#111827',
    'logo_path'       => '',
    'mascot'          => '',
    'timezone'        => 'America/Chicago',
];

function seed_school_defaults(PDO $pdo, int $schoolId): void
{
    $jobType = $pdo->prepare(
        'INSERT IGNORE INTO job_types (school_id, name, sort_order) VALUES (?, ?, ?)'
    );
    foreach (DEFAULT_JOB_TYPES as $name => $sortOrder) {
        $jobType->execute([$schoolId, $name, $sortOrder]);
    }

    $level = $pdo->prepare(
        'INSERT IGNORE INTO sponsorship_levels (school_id, name, color, sort_order) VALUES (?, ?, ?, ?)'
    );
    foreach (DEFAULT_SPONSORSHIP_LEVELS as [$name, $color, $sortOrder]) {
        $level->execute([$schoolId, $name, $color, $sortOrder]);
    }

    $setting = $pdo->prepare(
        'INSERT IGNORE INTO school_settings (school_id, setting_key, setting_value) VALUES (?, ?, ?)'
    );
    foreach (DEFAULT_SCHOOL_SETTINGS as $key => $value) {
        $setting->execute([$schoolId, $key, $value]);
    }

    $sport = $pdo->prepare(
        'INSERT IGNORE INTO sports (school_id, name, icon, sort_order) VALUES (?, ?, ?, ?)'
    );
    foreach (DEFAULT_SPORTS as $name => [$icon, $sortOrder]) {
        $sport->execute([$schoolId, $name, $icon, $sortOrder]);
    }

    $level = $pdo->prepare(
        'INSERT IGNORE INTO levels (school_id, name, sort_order) VALUES (?, ?, ?)'
    );
    foreach (DEFAULT_LEVELS as $name => $sortOrder) {
        $level->execute([$schoolId, $name, $sortOrder]);
    }

    $eventType = $pdo->prepare(
        'INSERT IGNORE INTO event_types (school_id, name, is_competition, sort_order) VALUES (?, ?, ?, ?)'
    );
    foreach (DEFAULT_EVENT_TYPES as $name => [$isCompetition, $sortOrder]) {
        $eventType->execute([$schoolId, $name, $isCompetition, $sortOrder]);
    }
}
