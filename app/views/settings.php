<h1>School settings</h1>

<div class="card">
    <h2>Identity</h2>
    <form method="post" action="<?= e(BASE_URL) ?>/settings.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="identity">
        <div class="field">
            <label for="school_name">School name</label>
            <input id="school_name" name="school_name" required maxlength="150" value="<?= e($school['name']) ?>">
        </div>
        <div class="field">
            <label for="mascot">Mascot</label>
            <input id="mascot" name="mascot" maxlength="80" value="<?= e(school_setting('mascot', '')) ?>" placeholder="Patriots">
        </div>
        <div class="field">
            <label for="timezone">Timezone</label>
            <select id="timezone" name="timezone">
                <?php $currentTz = school_setting('timezone', 'America/Chicago'); ?>
                <?php foreach ($timezones as $tz): ?>
                    <option value="<?= e($tz) ?>"<?= $tz === $currentTz ? ' selected' : '' ?>><?= e($tz) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save identity</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Colors</h2>
    <p class="muted">Used to theme the whole app for your school.</p>
    <form method="post" action="<?= e(BASE_URL) ?>/settings.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="colors">
        <div class="field-row">
            <div class="field">
                <label for="color_primary">Primary color</label>
                <input id="color_primary" name="color_primary" type="color" value="<?= e(theme_color('primary')) ?>">
            </div>
            <div class="field">
                <label for="color_secondary">Secondary color</label>
                <input id="color_secondary" name="color_secondary" type="color" value="<?= e(theme_color('secondary')) ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save colors</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Logo</h2>
    <?php if ($logo = school_logo_url()): ?>
        <p><img src="<?= e($logo) ?>" alt="Current school logo" class="logo-preview"></p>
    <?php else: ?>
        <p class="muted">No logo uploaded yet.</p>
    <?php endif; ?>
    <form method="post" action="<?= e(BASE_URL) ?>/settings.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="logo">
        <div class="field">
            <label for="logo">Upload logo <span class="muted">(PNG, JPG, or WebP; max 2&nbsp;MB)</span></label>
            <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" required>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Upload logo</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Sponsorship levels</h2>
    <p class="muted">Your school's sponsor tiers — name, badge color, and display order.</p>
    <?php foreach ($levels as $level): ?>
        <form method="post" action="<?= e(BASE_URL) ?>/settings.php" class="row-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="level_row">
            <input type="hidden" name="id" value="<?= (int)$level['id'] ?>">
            <span class="badge" style="background: <?= e($level['color']) ?>; color: <?= e(contrast_color($level['color'])) ?>;"><?= e($level['name']) ?></span>
            <input name="name" required maxlength="80" value="<?= e($level['name']) ?>" aria-label="Level name">
            <input name="color" type="color" value="<?= e($level['color']) ?>" aria-label="Level color">
            <input name="sort_order" type="number" value="<?= (int)$level['sort_order'] ?>" class="input-sm" aria-label="Sort order">
            <button type="submit" name="do" value="save" class="btn btn-sm">Save</button>
            <button type="submit" name="do" value="delete" class="btn btn-sm btn-danger"
                    formnovalidate data-confirm="Delete the '<?= e($level['name']) ?>' level?">Delete</button>
        </form>
    <?php endforeach; ?>
    <form method="post" action="<?= e(BASE_URL) ?>/settings.php" class="row-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="level_add">
        <input name="name" required maxlength="80" placeholder="New level name" aria-label="New level name">
        <input name="color" type="color" value="#1d4ed8" aria-label="New level color">
        <input name="sort_order" type="number" value="<?= (int)$nextLevelSort ?>" class="input-sm" aria-label="Sort order">
        <button type="submit" class="btn btn-sm btn-primary">Add level</button>
    </form>
</div>

<div class="card">
    <h2>Job types</h2>
    <p class="muted">Roles staff can work at events. Deactivated types are kept for history but can't be assigned.</p>
    <?php foreach ($jobTypes as $jt): ?>
        <form method="post" action="<?= e(BASE_URL) ?>/settings.php" class="row-form<?= $jt['is_active'] ? '' : ' inactive-row' ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="jobtype_row">
            <input type="hidden" name="id" value="<?= (int)$jt['id'] ?>">
            <input name="name" required maxlength="80" value="<?= e($jt['name']) ?>" aria-label="Job type name">
            <input name="sort_order" type="number" value="<?= (int)$jt['sort_order'] ?>" class="input-sm" aria-label="Sort order">
            <button type="submit" name="do" value="save" class="btn btn-sm">Save</button>
            <button type="submit" name="do" value="toggle" class="btn btn-sm<?= $jt['is_active'] ? ' btn-danger' : '' ?>" formnovalidate>
                <?= $jt['is_active'] ? 'Deactivate' : 'Reactivate' ?>
            </button>
        </form>
    <?php endforeach; ?>
    <form method="post" action="<?= e(BASE_URL) ?>/settings.php" class="row-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="jobtype_add">
        <input name="name" required maxlength="80" placeholder="New job type" aria-label="New job type name">
        <button type="submit" class="btn btn-sm btn-primary">Add job type</button>
    </form>
</div>

<div class="card">
    <h2>Database</h2>
    <?php if ($pendingMigrations): ?>
        <p>Pending migrations: <strong><?= e(implode(', ', $pendingMigrations)) ?></strong></p>
        <form method="post" action="<?= e(BASE_URL) ?>/settings.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="migrate">
            <button type="submit" class="btn btn-primary">Apply pending migrations</button>
        </form>
    <?php else: ?>
        <p class="muted">Database is up to date.</p>
    <?php endif; ?>
</div>
