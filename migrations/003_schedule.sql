-- 003_schedule.sql — Master athletic schedule: sports, levels, event types,
-- opponents, venues, events, event needs. The events table is the spine of
-- the platform; later modules (sponsorship, staffing, assets, incidents)
-- all reference events(id).
--
-- New schools get default sports/levels/event types from app/seed.php;
-- the INSERT ... SELECT statements at the bottom backfill existing schools.
-- Keep the two lists in sync.

-- Sports offered by the school (editable per school).
CREATE TABLE sports (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    school_id   INT UNSIGNED  NOT NULL,
    name        VARCHAR(80)   NOT NULL,
    icon        VARCHAR(16)   NOT NULL DEFAULT '',
    sort_order  INT           NOT NULL DEFAULT 0,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sports_school_name (school_id, name),
    CONSTRAINT fk_sports_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Competition levels (Varsity, JV, ... — editable per school).
CREATE TABLE levels (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    school_id   INT UNSIGNED  NOT NULL,
    name        VARCHAR(80)   NOT NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_levels_school_name (school_id, name),
    CONSTRAINT fk_sched_levels_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event types (editable per school). is_competition drives scoring UI and
-- readiness rules (ticket links are only expected for competitions).
CREATE TABLE event_types (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    school_id       INT UNSIGNED  NOT NULL,
    name            VARCHAR(80)   NOT NULL,
    is_competition  TINYINT(1)    NOT NULL DEFAULT 0,
    sort_order      INT           NOT NULL DEFAULT 0,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_types_school_name (school_id, name),
    CONSTRAINT fk_event_types_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opponent schools — reusable across events, with branding/social info.
CREATE TABLE opponents (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    school_id   INT UNSIGNED  NOT NULL,
    name        VARCHAR(150)  NOT NULL,
    mascot      VARCHAR(80)   NULL,
    city        VARCHAR(120)  NULL,
    logo_path   VARCHAR(255)  NULL,
    website     VARCHAR(255)  NULL,
    twitter     VARCHAR(80)   NULL,
    instagram   VARCHAR(80)   NULL,
    notes       TEXT          NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_opponents_school_name (school_id, name),
    CONSTRAINT fk_opponents_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Venues (home fields/gyms and recurring away/neutral sites).
CREATE TABLE venues (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    school_id   INT UNSIGNED  NOT NULL,
    name        VARCHAR(150)  NOT NULL,
    address     VARCHAR(255)  NULL,
    is_home     TINYINT(1)    NOT NULL DEFAULT 0,
    sort_order  INT           NOT NULL DEFAULT 0,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_venues_school_name (school_id, name),
    CONSTRAINT fk_venues_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The master schedule. Everything else in the platform hangs off this table.
CREATE TABLE events (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    school_id         INT UNSIGNED  NOT NULL,
    sport_id          INT UNSIGNED  NOT NULL,
    level_id          INT UNSIGNED  NULL,
    event_type_id     INT UNSIGNED  NOT NULL,
    opponent_id       INT UNSIGNED  NULL,
    home_away         ENUM('home','away','neutral') NOT NULL DEFAULT 'home',
    venue_id          INT UNSIGNED  NULL,
    location_text     VARCHAR(255)  NULL,
    event_date        DATE          NOT NULL,
    start_time        TIME          NULL,
    ticket_link       VARCHAR(255)  NULL,
    broadcast_planned TINYINT(1)    NOT NULL DEFAULT 0,
    broadcast_link    VARCHAR(255)  NULL,
    status            ENUM('scheduled','completed','postponed','cancelled') NOT NULL DEFAULT 'scheduled',
    final_score_us    INT           NULL,
    final_score_them  INT           NULL,
    result            ENUM('W','L','T') NULL,
    notes             TEXT          NULL,
    created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_events_school_date (school_id, event_date),
    KEY idx_events_school_sport_date (school_id, sport_id, event_date),
    KEY idx_events_school_status_date (school_id, status, event_date),
    CONSTRAINT fk_events_school     FOREIGN KEY (school_id)     REFERENCES schools (id)     ON DELETE CASCADE,
    CONSTRAINT fk_events_sport      FOREIGN KEY (sport_id)      REFERENCES sports (id),
    CONSTRAINT fk_events_level      FOREIGN KEY (level_id)      REFERENCES levels (id),
    CONSTRAINT fk_events_event_type FOREIGN KEY (event_type_id) REFERENCES event_types (id),
    CONSTRAINT fk_events_opponent   FOREIGN KEY (opponent_id)   REFERENCES opponents (id),
    CONSTRAINT fk_events_venue      FOREIGN KEY (venue_id)      REFERENCES venues (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Operational needs, one row per event (created with the event).
CREATE TABLE event_needs (
    event_id                INT UNSIGNED  NOT NULL,
    school_id               INT UNSIGNED  NOT NULL,
    equipment_needed        TEXT          NULL,
    social_content_needed   TEXT          NULL,
    transportation_needed   TINYINT(1)    NOT NULL DEFAULT 0,
    transportation_details  VARCHAR(255)  NULL,
    transportation_arranged TINYINT(1)    NOT NULL DEFAULT 0,
    updated_at              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_event_needs_school (school_id),
    CONSTRAINT fk_event_needs_event  FOREIGN KEY (event_id)  REFERENCES events (id)  ON DELETE CASCADE,
    CONSTRAINT fk_event_needs_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Worker requirements per event, by job type. Assignment counts come from
-- the Phase 4 staffing table; until then readiness treats assigned as 0.
CREATE TABLE event_worker_needs (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    school_id      INT UNSIGNED  NOT NULL,
    event_id       INT UNSIGNED  NOT NULL,
    job_type_id    INT UNSIGNED  NOT NULL,
    workers_needed INT UNSIGNED  NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ewn_event_job (event_id, job_type_id),
    KEY idx_ewn_school (school_id),
    CONSTRAINT fk_ewn_event    FOREIGN KEY (event_id)    REFERENCES events (id)    ON DELETE CASCADE,
    CONSTRAINT fk_ewn_job_type FOREIGN KEY (job_type_id) REFERENCES job_types (id),
    CONSTRAINT fk_ewn_school   FOREIGN KEY (school_id)   REFERENCES schools (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Backfill defaults for schools created before this migration
-- (keep in sync with app/seed.php)
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO sports (school_id, name, icon, sort_order)
SELECT s.id, d.name, d.icon, d.sort_order
FROM schools s
CROSS JOIN (
              SELECT 'Football'           AS name, '🏈' AS icon, 10  AS sort_order
    UNION ALL SELECT 'Volleyball',                 '🏐',          20
    UNION ALL SELECT 'Basketball (Boys)',          '🏀',          30
    UNION ALL SELECT 'Basketball (Girls)',         '🏀',          40
    UNION ALL SELECT 'Baseball',                   '⚾',          50
    UNION ALL SELECT 'Softball',                   '🥎',          60
    UNION ALL SELECT 'Soccer (Boys)',              '⚽',          70
    UNION ALL SELECT 'Soccer (Girls)',             '⚽',          80
    UNION ALL SELECT 'Track & Field',              '🏃',          90
    UNION ALL SELECT 'Cross Country',              '🏃',          100
    UNION ALL SELECT 'Swimming',                   '🏊',          110
    UNION ALL SELECT 'Wrestling',                  '🤼',          120
    UNION ALL SELECT 'Tennis',                     '🎾',          130
    UNION ALL SELECT 'Golf',                       '⛳',          140
    UNION ALL SELECT 'Cheer',                      '📣',          150
) d;

INSERT IGNORE INTO levels (school_id, name, sort_order)
SELECT s.id, d.name, d.sort_order
FROM schools s
CROSS JOIN (
              SELECT 'Varsity'       AS name, 10 AS sort_order
    UNION ALL SELECT 'JV',                    20
    UNION ALL SELECT 'Freshman',              30
    UNION ALL SELECT 'Middle School',         40
) d;

INSERT IGNORE INTO event_types (school_id, name, is_competition, sort_order)
SELECT s.id, d.name, d.is_competition, d.sort_order
FROM schools s
CROSS JOIN (
              SELECT 'Competition'   AS name, 1 AS is_competition, 10 AS sort_order
    UNION ALL SELECT 'Media Day',             0,                   20
    UNION ALL SELECT 'Banquet',               0,                   30
    UNION ALL SELECT 'Special Event',         0,                   40
    UNION ALL SELECT 'Practice',              0,                   50
) d;
