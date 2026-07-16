<?php
declare(strict_types=1);

/**
 * Simple SQL migration runner, shared by public/install.php (first install)
 * and the admin settings page (applying later migrations to a live site).
 *
 * Migration files are /migrations/NNN_description.sql, applied in filename
 * order and recorded in schema_migrations. Statements end with ";" at end
 * of line; "--" full-line comments are stripped. No stored procedures or
 * triggers in migration files (the splitter is deliberately simple).
 */

require_once __DIR__ . '/db.php';

function ensure_migrations_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            filename   VARCHAR(120) NOT NULL,
            applied_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (filename)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** Filenames already applied (empty if the tracking table doesn't exist yet). */
function migrations_applied(): array
{
    try {
        return db()->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException) {
        return [];
    }
}

/** Migration filenames not yet applied, in run order. */
function migrations_pending(): array
{
    $files = glob(__DIR__ . '/../migrations/*.sql') ?: [];
    sort($files);
    $applied = migrations_applied();

    return array_values(array_filter(
        array_map('basename', $files),
        fn (string $name) => !in_array($name, $applied, true)
    ));
}

/** Apply all pending migrations. Returns the filenames that were run. */
function run_pending_migrations(): array
{
    ensure_migrations_table();

    $ran = [];
    foreach (migrations_pending() as $name) {
        $sql = file_get_contents(__DIR__ . '/../migrations/' . $name);
        if ($sql === false) {
            throw new RuntimeException("Could not read migration $name");
        }

        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
            $statement = trim(preg_replace('/^\s*--.*$/m', '', $statement) ?? '');
            if ($statement !== '') {
                db()->exec($statement);
            }
        }

        db()->prepare('INSERT INTO schema_migrations (filename) VALUES (?)')->execute([$name]);
        $ran[] = $name;
    }

    return $ran;
}
