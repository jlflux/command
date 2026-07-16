<div class="page-head">
    <h1>Users</h1>
    <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/user_edit.php">Add user</a>
</div>

<div class="card">
    <div class="table-scroll">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Default jobs</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <tr<?= $row['is_active'] ? '' : ' class="inactive-row"' ?>>
                    <td><?= e($row['name']) ?><?= (int)$row['id'] === (int)$user['id'] ? ' <span class="muted">(you)</span>' : '' ?></td>
                    <td><?= e($row['email']) ?></td>
                    <td><?= e($row['phone']) ?></td>
                    <td><span class="badge badge-role"><?= e($row['role']) ?></span></td>
                    <td class="muted"><?= e($row['job_types'] ?? '') ?></td>
                    <td><?= $row['is_active'] ? 'Active' : '<span class="muted">Deactivated</span>' ?></td>
                    <td class="actions">
                        <a class="btn btn-sm" href="<?= e(BASE_URL) ?>/user_edit.php?id=<?= (int)$row['id'] ?>">Edit</a>
                        <?php if ((int)$row['id'] !== (int)$user['id']): ?>
                            <form method="post" action="<?= e(BASE_URL) ?>/users.php"
                                  <?= $row['is_active'] ? 'data-confirm="Deactivate ' . e($row['name']) . '? They will no longer be able to log in."' : '' ?>>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $row['is_active'] ? 'btn-danger' : '' ?>">
                                    <?= $row['is_active'] ? 'Deactivate' : 'Reactivate' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
