<h1>Bulk add events</h1>
<p class="muted">Enter a whole slate for one team at once. Opponents that don't exist yet are
created automatically — add logos and details later in the
<a href="<?= e(BASE_URL) ?>/opponents.php">opponent manager</a>. Blank rows are skipped.</p>

<form method="post" action="<?= e(BASE_URL) ?>/event_bulk.php">
    <?= csrf_field() ?>

    <div class="card">
        <h2>This batch is for</h2>
        <div class="field-row">
            <div class="field">
                <label for="sport_id">Sport</label>
                <select id="sport_id" name="sport_id" required>
                    <option value="">Pick a sport…</option>
                    <?php foreach ($sports as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"<?= $old['sport_id'] === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['icon'] . ' ' . $s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="level_id">Level</label>
                <select id="level_id" name="level_id">
                    <option value="">—</option>
                    <?php foreach ($levels as $l): ?>
                        <option value="<?= (int)$l['id'] ?>"<?= $old['level_id'] === (int)$l['id'] ? ' selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="event_type_id">Event type</label>
                <select id="event_type_id" name="event_type_id" required>
                    <?php foreach ($eventTypes as $et): ?>
                        <option value="<?= (int)$et['id'] ?>"<?= $old['event_type_id'] === (int)$et['id'] ? ' selected' : '' ?>><?= e($et['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Games</h2>
        <div class="table-scroll">
            <table class="table bulk-table">
                <thead>
                    <tr><th>#</th><th>Date</th><th>Time</th><th>Opponent</th><th>H/A</th><th>Location (away/neutral)</th></tr>
                </thead>
                <tbody>
                <?php for ($i = 0; $i < $rowCount; $i++): $row = $old['rows'][$i] ?? []; ?>
                    <tr>
                        <td class="muted"><?= $i + 1 ?></td>
                        <td><input type="date" name="rows[<?= $i ?>][date]" value="<?= e($row['date'] ?? '') ?>"></td>
                        <td><input type="time" name="rows[<?= $i ?>][time]" value="<?= e($row['time'] ?? '') ?>"></td>
                        <td>
                            <input name="rows[<?= $i ?>][opponent]" list="opponent-names" maxlength="150"
                                   value="<?= e($row['opponent'] ?? '') ?>" placeholder="Opponent name">
                        </td>
                        <td>
                            <select name="rows[<?= $i ?>][ha]" aria-label="Home or away">
                                <?php foreach (['home' => 'Home', 'away' => 'Away', 'neutral' => 'Neutral'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>"<?= ($row['ha'] ?? 'home') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input name="rows[<?= $i ?>][location]" maxlength="255" value="<?= e($row['location'] ?? '') ?>"></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <datalist id="opponent-names">
            <?php foreach ($opponents as $o): ?>
                <option value="<?= e($o['name']) ?>"></option>
            <?php endforeach; ?>
        </datalist>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Add all filled rows</button>
        <a class="btn" href="<?= e(BASE_URL) ?>/schedule.php">Cancel</a>
    </div>
</form>
