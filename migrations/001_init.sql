-- 001_init.sql — Athletics Command Center: multi-tenant core
--
-- Conventions: InnoDB, utf8mb4/utf8mb4_unicode_ci, plural snake_case table
-- names, `id` PK. Every tenant-scoped table carries school_id (FK -> schools)
-- and an index leading with it. Statements end with ";" at end of line —
-- the simple migration runner in install.php splits on that.
--
-- (The schema_migrations tracking table is created by the runner itself,
-- not by a migration, so it exists before any migration runs.)

-- Tenants. One row per customer school.
CREATE TABLE schools (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150)    NOT NULL,
    slug        VARCHAR(80)     NOT NULL,
    is_active   TINYINT(1)      NOT NULL DEFAULT 1,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schools_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users belong to exactly one school. Email is globally unique so login
-- (an explicit, intentional non-tenant-scoped query) can resolve by email.
-- Roles: viewer < staff < manager < admin (see ROLE_LEVELS in helpers.php).
CREATE TABLE users (
    id             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    school_id      INT UNSIGNED   NOT NULL,
    name           VARCHAR(120)   NOT NULL,
    email          VARCHAR(190)   NOT NULL,
    password_hash  VARCHAR(255)   NOT NULL,
    role           ENUM('admin','manager','staff','viewer') NOT NULL DEFAULT 'staff',
    phone          VARCHAR(30)    NULL,
    is_active      TINYINT(1)     NOT NULL DEFAULT 1,
    last_login_at  DATETIME       NULL,
    created_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_school_role (school_id, role),
    CONSTRAINT fk_users_school FOREIGN KEY (school_id) REFERENCES schools (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- "Remember me" tokens (selector/validator pattern: selector is looked up,
-- validator is compared against its SHA-256 hash — raw validator never stored).
CREATE TABLE remember_tokens (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED  NOT NULL,
    selector        CHAR(24)      NOT NULL,
    validator_hash  CHAR(64)      NOT NULL,
    expires_at      DATETIME      NOT NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_selector (selector),
    KEY idx_remember_user (user_id),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-school customization, key/value.
-- Known keys: color_primary, color_secondary, logo_path,
--             sponsorship_levels (JSON array of names, e.g. ["Platinum","Gold","Silver","Bronze"]).
CREATE TABLE school_settings (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    school_id      INT UNSIGNED  NOT NULL,
    setting_key    VARCHAR(80)   NOT NULL,
    setting_value  TEXT          NULL,
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_school_settings (school_id, setting_key),
    CONSTRAINT fk_settings_school FOREIGN KEY (school_id) REFERENCES schools (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
