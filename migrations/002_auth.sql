-- 002_auth.sql — auth & tenant layer: job types, user default jobs, sponsorship levels
--
-- job_types and sponsorship_levels are tenant-scoped lookup tables so each
-- school can customize its own lists. user_job_types is a pure junction
-- table: it carries no school_id — tenant scoping is enforced through both
-- parents (user and job type are each validated against the current school
-- before rows are written).
--
-- New schools get their default rows from app/seed.php; the INSERT ... SELECT
-- statements at the bottom backfill schools that already exist. Keep the two
-- lists in sync.

CREATE TABLE job_types (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    school_id   INT UNSIGNED  NOT NULL,
    name        VARCHAR(80)   NOT NULL,
    sort_order  INT           NOT NULL DEFAULT 0,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_job_types_school_name (school_id, name),
    CONSTRAINT fk_job_types_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_job_types (
    user_id      INT UNSIGNED NOT NULL,
    job_type_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, job_type_id),
    CONSTRAINT fk_ujt_user     FOREIGN KEY (user_id)     REFERENCES users (id)     ON DELETE CASCADE,
    CONSTRAINT fk_ujt_job_type FOREIGN KEY (job_type_id) REFERENCES job_types (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sponsorship level definitions (replaces the sponsorship_levels JSON key in
-- school_settings from 001).
CREATE TABLE sponsorship_levels (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    school_id   INT UNSIGNED  NOT NULL,
    name        VARCHAR(80)   NOT NULL,
    color       CHAR(7)       NOT NULL DEFAULT '#1d4ed8',
    sort_order  INT           NOT NULL DEFAULT 0,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_levels_school_name (school_id, name),
    CONSTRAINT fk_levels_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Backfill defaults for schools created before this migration
-- (keep in sync with app/seed.php)
-- ---------------------------------------------------------------------------

INSERT IGNORE INTO job_types (school_id, name, sort_order)
SELECT s.id, d.name, d.sort_order
FROM schools s
CROSS JOIN (
              SELECT 'Ticket Worker'        AS name, 10  AS sort_order
    UNION ALL SELECT 'Gate',                         20
    UNION ALL SELECT 'PA Announcer',                 30
    UNION ALL SELECT 'Scoreboard Operator',          40
    UNION ALL SELECT 'Video Board Operator',         50
    UNION ALL SELECT 'Broadcast',                    60
    UNION ALL SELECT 'Security',                     70
    UNION ALL SELECT 'Athletic Trainer',             80
    UNION ALL SELECT 'Concessions',                  90
    UNION ALL SELECT 'Intern',                       100
    UNION ALL SELECT 'Bus Driver',                   110
    UNION ALL SELECT 'Game Administrator',           120
) d;

INSERT IGNORE INTO sponsorship_levels (school_id, name, color, sort_order)
SELECT s.id, d.name, d.color, d.sort_order
FROM schools s
CROSS JOIN (
              SELECT 'Red'             AS name, '#dc2626' AS color, 10 AS sort_order
    UNION ALL SELECT 'White',                   '#ffffff',          20
    UNION ALL SELECT 'Blue',                    '#2563eb',          30
    UNION ALL SELECT 'Patriot Partner',         '#1e3a8a',          40
) d;

-- New settings keys for existing schools (INSERT IGNORE: keep any existing value).
INSERT IGNORE INTO school_settings (school_id, setting_key, setting_value)
SELECT id, 'mascot', '' FROM schools;

INSERT IGNORE INTO school_settings (school_id, setting_key, setting_value)
SELECT id, 'timezone', 'America/Chicago' FROM schools;

-- Sponsorship levels are a real table now; drop the legacy JSON setting.
DELETE FROM school_settings WHERE setting_key = 'sponsorship_levels';
