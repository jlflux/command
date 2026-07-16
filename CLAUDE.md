# Athletics Command Center

Multi-tenant SaaS operations platform for high school athletic departments.
Pilot school: **Homewood High School** — but every design decision must support
selling to other schools from day one. There is one shared database; every
tenant-scoped table carries a `school_id` column and **every query is scoped
by it**.

## Tech stack (non-negotiable)

- **PHP 8.x** — no framework, no Composer, no build step. Plain `.php` files.
- **MySQL / MariaDB** — single shared database, InnoDB, utf8mb4.
- **Hostinger shared hosting** — assume no shell daemons, no cron guarantees,
  no long-running processes. File-based PHP sessions are fine.
- **Vanilla JavaScript and CSS** — no npm, no bundlers, no CDN dependencies.
- **PDO with prepared statements everywhere.** Never interpolate values into SQL.
- **Sessions for auth**; `password_hash()` / `password_verify()` for passwords.

## Project structure

```
/public              Web root (only this directory is web-accessible)
  index.php          Front controller / landing
  install.php        One-time installer (disable via INSTALL_ENABLED after use)
  assets/css/        Stylesheets (vanilla CSS)
  assets/js/         Scripts (vanilla JS)
  uploads/           User-uploaded files (PHP execution blocked via .htaccess)
/app                 Application code (NOT web-accessible)
  config.php         DB credentials + app constants (placeholders in git)
  db.php             db() — PDO singleton
  helpers.php        Session bootstrap, auth, tenant scoping, CSRF, misc helpers
  views/             Page templates (server-rendered PHP)
  api/               JSON endpoints for AJAX (routed through /public)
/migrations          Numbered SQL files: 001_init.sql, 002_*.sql, ...
```

`app/helpers.php` requires `config.php` and `db.php` and starts the session —
every entry-point script only needs `require __DIR__ . '/../app/helpers.php';`.

## Multi-tenancy rules (read this twice)

1. Every tenant-scoped table has a `school_id INT UNSIGNED NOT NULL` column
   with a foreign key to `schools(id)` and an index (usually the leading
   column of a composite index).
2. **Every** SELECT/UPDATE/DELETE against a tenant-scoped table filters by
   `school_id = :school_id`, bound from `current_school_id()` — never from
   request input. Every INSERT sets it the same way.
3. Use the `tenant_query()` / `tenant_fetch()` / `tenant_fetch_all()` helpers
   in `app/helpers.php`; they refuse to run SQL that doesn't reference
   `:school_id` and bind it automatically.
4. When loading a record by id (e.g. `/event.php?id=42`), the query must
   include `AND school_id = :school_id`. A missing row and a cross-tenant row
   must be indistinguishable (both → 404).
5. Uploaded files are stored under `public/uploads/{school_id}/...`.
6. No cross-tenant data leaks, ever. If a query cannot be tenant-scoped
   (e.g. login by email, super-admin tooling), that must be an explicit,
   commented exception.

## Roles (per school)

| Role      | Capabilities |
|-----------|--------------|
| `admin`   | Athletic director. Full control incl. users and settings. |
| `manager` | Manage schedule, sponsors, events, assets. |
| `staff`   | Sees own assignments; accept/decline; check in at events. |
| `viewer`  | Read-only. |

Hierarchy: `viewer < staff < manager < admin`. Use `require_role('manager')`
(meaning "manager or above") via the helpers; don't hand-roll role checks.

## Core modules (built in phases — check what exists before assuming)

1. **Auth & multi-tenancy** — schools, users, roles. *(skeleton done)*
2. **Master athletic schedule** — the backbone; everything links to events.
3. **Sponsorship management** — sponsors, contracts, invoices, payments,
   fulfillment obligations, assets.
4. **Event operations** — per-event command sheet: staffing assignments,
   run of show, templates, check-ins.
5. **Digital asset library** — logos, photos, graphics, videos; tagged and
   linked to sponsors/teams/events.
6. **Team/season workspaces** — rosters, coaches, results, documents per
   sport per season.
7. **Incident tracking** — operational issues logged per event.
8. **Dashboard & reporting** — Monday-morning readiness view, sponsorship
   reports, event reports.

## Key design principles

- **The master schedule is the spine.** Sponsors, staffing, assets, incidents,
  and reports all attach to events. New tables should reference `events`
  where it makes sense.
- **Readiness is a first-class concept.** The system should always know what's
  missing for each upcoming event (workers, ticket link, opponent logo,
  sponsor obligations) and surface it — design tables so "is this complete?"
  is cheap to compute.
- **Mobile-friendly is mandatory.** Game-night users are on phones at a
  stadium: big touch targets, minimal typing, fast pages, works on spotty
  connections. Design mobile-first, enhance for desktop.
- **Per-school customization** lives in `school_settings` (key/value).
  Known keys so far: `color_primary`, `color_secondary`, `logo_path`,
  `sponsorship_levels` (JSON array of level names, e.g.
  `["Platinum","Gold","Silver","Bronze"]`).

## Coding conventions

### PHP
- `declare(strict_types=1);` at the top of every PHP file.
- Procedural + small functions; no framework patterns for their own sake.
  Shared logic goes in `app/helpers.php` or a focused `app/*.php` include.
- Escape all output with `e()` (wrapper for `htmlspecialchars`). Raw echo of
  user data is a bug.
- All state-changing requests are POST and must pass `require_csrf()`.
  Render the hidden input with `csrf_field()`.
- JSON endpoints live in `app/api/`, are served through thin wrappers in
  `/public`, respond via `json_response()`, and enforce login + tenant scope
  + CSRF exactly like pages do.
- Redirect-after-POST for all form handling.
- `password_hash($pw, PASSWORD_DEFAULT)`; never store or log plaintext.
- Errors: exceptions bubble to a generic 500 in production (`APP_ENV`);
  never echo SQL or stack traces to users.

### SQL / migrations
- Migrations are plain SQL, numbered `NNN_description.sql`, append-only —
  never edit an already-applied migration; add a new one.
- Applied migrations are tracked in `schema_migrations` (created by the
  installer / migration runner).
- Tables: InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`, singular `id` PK,
  `created_at` timestamps; `snake_case` names, plural table names.
- Statements in migration files end with `;` at end-of-line (the simple
  runner splits on that — avoid stored procedures/triggers in migrations).

### JavaScript / CSS
- Vanilla ES6+ in `public/assets/js/`, one file per page/feature, loaded with
  `defer`. Use `fetch()` against the JSON endpoints; always send the CSRF
  token (from a `<meta name="csrf-token">` tag) on mutating requests.
- No inline `onclick=`; use `addEventListener`.
- Vanilla CSS in `public/assets/css/`, mobile-first with `min-width` media
  queries. Use CSS custom properties for theming (school colors are injected
  as `--color-primary` / `--color-secondary` from `school_settings`).

## Setup / install

1. Copy real DB credentials into `app/config.php` (placeholders in git —
   never commit real credentials).
2. Point the web root at `/public`.
3. Visit `/install.php` — it runs pending migrations and creates the first
   school + admin user.
4. Set `INSTALL_ENABLED` to `false` in `app/config.php` (and ideally delete
   `public/install.php`).
