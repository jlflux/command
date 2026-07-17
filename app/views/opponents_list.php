<div class="page-head">
    <h1>Opponents</h1>
    <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/opponent_edit.php">Add opponent</a>
</div>

<?php if (!$opponents): ?>
    <div class="card"><p class="muted">No opponents yet. They're also created automatically when you
    type a new name in <a href="<?= e(BASE_URL) ?>/event_bulk.php">bulk add</a>.</p></div>
<?php else: ?>
    <div class="card">
        <div class="table-scroll">
            <table class="table">
                <thead>
                    <tr><th>Logo</th><th>Name</th><th>Mascot</th><th>City</th><th>Links</th><th>Events</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($opponents as $o): ?>
                    <tr>
                        <td>
                            <?php if ($o['logo_path']): ?>
                                <img src="<?= e(BASE_URL . '/' . $o['logo_path']) ?>" alt="" class="opponent-thumb">
                            <?php else: ?>
                                <span class="badge badge-warn">No logo</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= e($o['name']) ?></strong></td>
                        <td><?= e($o['mascot'] ?? '') ?></td>
                        <td><?= e($o['city'] ?? '') ?></td>
                        <td class="nowrap">
                            <?php if ($o['website']): ?><a href="<?= e($o['website']) ?>" target="_blank" rel="noopener">Web</a><?php endif; ?>
                            <?php if ($o['twitter']): ?><a href="https://x.com/<?= e($o['twitter']) ?>" target="_blank" rel="noopener">X</a><?php endif; ?>
                            <?php if ($o['instagram']): ?><a href="https://instagram.com/<?= e($o['instagram']) ?>" target="_blank" rel="noopener">IG</a><?php endif; ?>
                        </td>
                        <td><?= (int)$o['event_count'] ?></td>
                        <td class="actions">
                            <a class="btn btn-sm" href="<?= e(BASE_URL) ?>/opponent_edit.php?id=<?= (int)$o['id'] ?>">Edit</a>
                            <?php if (!(int)$o['event_count']): ?>
                                <form method="post" action="<?= e(BASE_URL) ?>/opponents.php" data-confirm="Delete <?= e($o['name']) ?>?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
