<h1><?= $target ? 'Edit user' : 'Add user' ?></h1>

<div class="card narrow">
    <form method="post" action="<?= e(BASE_URL) ?>/user_edit.php">
        <?= csrf_field() ?>
        <?php if ($target): ?>
            <input type="hidden" name="id" value="<?= (int)$target['id'] ?>">
        <?php endif; ?>

        <div class="field">
            <label for="name">Full name</label>
            <input id="name" name="name" required maxlength="120" value="<?= e($old['name']) ?>">
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required maxlength="190" value="<?= e($old['email']) ?>">
        </div>

        <div class="field">
            <label for="phone">Phone <span class="muted">(for future shift reminders)</span></label>
            <input id="phone" name="phone" type="tel" maxlength="30" value="<?= e($old['phone']) ?>">
        </div>

        <div class="field">
            <label for="role">Role</label>
            <?php if ($isSelf): ?>
                <input type="hidden" name="role" value="<?= e($old['role']) ?>">
                <input id="role" value="<?= e($old['role']) ?> (you can't change your own role)" disabled>
            <?php else: ?>
                <select id="role" name="role" required>
                    <?php foreach (['admin' => 'Admin (athletic director)', 'manager' => 'Manager', 'staff' => 'Staff', 'viewer' => 'Viewer (read-only)'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $old['role'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="field">
            <label>Default job types <span class="muted">(used when assigning event workers)</span></label>
            <div class="checkgrid">
                <?php foreach ($jobTypes as $jt): ?>
                    <label class="check">
                        <input type="checkbox" name="job_type_ids[]" value="<?= (int)$jt['id'] ?>"
                            <?= in_array((int)$jt['id'], $selectedJobTypeIds, true) ? 'checked' : '' ?>>
                        <?= e($jt['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field">
            <label for="password"><?= $target ? 'New password' : 'Password' ?> <span class="muted">
                <?= $target ? '(leave blank to keep current)' : '(leave blank to auto-generate)' ?>, 10+ characters</span></label>
            <input id="password" name="password" type="password" minlength="10" autocomplete="new-password">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $target ? 'Save changes' : 'Create user' ?></button>
            <a class="btn" href="<?= e(BASE_URL) ?>/users.php">Cancel</a>
        </div>
    </form>
</div>
