<h1><?= $event ? 'Edit event' : 'Add event' ?></h1>

<form method="post" action="<?= e(BASE_URL) ?>/event_edit.php">
    <?= csrf_field() ?>
    <?php if ($event): ?>
        <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
    <?php endif; ?>

    <div class="card">
        <h2>What &amp; when</h2>
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
        <div class="field-row">
            <div class="field">
                <label for="event_date">Date</label>
                <input id="event_date" name="event_date" type="date" required value="<?= e($old['event_date']) ?>">
            </div>
            <div class="field">
                <label for="start_time">Start time</label>
                <input id="start_time" name="start_time" type="time" value="<?= e((string)$old['start_time']) ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Matchup &amp; place</h2>
        <div class="field-row">
            <div class="field">
                <label for="opponent_id">Opponent</label>
                <select id="opponent_id" name="opponent_id">
                    <option value="">— none —</option>
                    <?php foreach ($opponents as $o): ?>
                        <option value="<?= (int)$o['id'] ?>"<?= $old['opponent_id'] === (int)$o['id'] ? ' selected' : '' ?>><?= e($o['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="hint"><a href="<?= e(BASE_URL) ?>/opponent_edit.php">New opponent</a></p>
            </div>
            <div class="field">
                <label for="home_away">Home / Away</label>
                <select id="home_away" name="home_away">
                    <?php foreach (['home' => 'Home', 'away' => 'Away', 'neutral' => 'Neutral'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $old['home_away'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label for="venue_id">Venue <span class="muted">(from your venue list)</span></label>
                <select id="venue_id" name="venue_id">
                    <option value="">—</option>
                    <?php foreach ($venues as $v): ?>
                        <option value="<?= (int)$v['id'] ?>"<?= $old['venue_id'] === (int)$v['id'] ? ' selected' : '' ?>><?= e($v['name']) ?><?= $v['is_home'] ? ' (home)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="location_text">Or location text</label>
                <input id="location_text" name="location_text" maxlength="255" value="<?= e((string)$old['location_text']) ?>" placeholder="Central HS Stadium, Tuscaloosa">
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Links &amp; status</h2>
        <div class="field">
            <label for="ticket_link">Ticket link</label>
            <input id="ticket_link" name="ticket_link" type="url" maxlength="255" value="<?= e((string)$old['ticket_link']) ?>" placeholder="https://gofan.co/...">
        </div>
        <div class="field">
            <label class="check"><input type="checkbox" name="broadcast_planned" value="1"<?= $old['broadcast_planned'] ? ' checked' : '' ?>> Broadcast / stream planned</label>
            <input name="broadcast_link" type="url" maxlength="255" value="<?= e((string)$old['broadcast_link']) ?>" placeholder="https://youtube.com/... (broadcast link)" aria-label="Broadcast link">
        </div>
        <div class="field-row">
            <div class="field">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach (['scheduled' => 'Scheduled', 'completed' => 'Completed', 'postponed' => 'Postponed', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $old['status'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="final_score_us">Score (us / them)</label>
                <div class="score-inputs">
                    <input id="final_score_us" name="final_score_us" type="number" min="0" value="<?= e((string)$old['final_score_us']) ?>" placeholder="Us">
                    <input name="final_score_them" type="number" min="0" value="<?= e((string)$old['final_score_them']) ?>" placeholder="Them" aria-label="Their score">
                </div>
            </div>
            <div class="field">
                <label for="result">Result</label>
                <select id="result" name="result">
                    <option value="">Auto from score</option>
                    <?php foreach (['W' => 'Win', 'L' => 'Loss', 'T' => 'Tie'] as $value => $label): ?>
                        <option value="<?= e($value) ?>"<?= $old['result'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="2"><?= e((string)$old['notes']) ?></textarea>
        </div>
    </div>

    <div class="card">
        <h2>Operational needs</h2>
        <div class="field">
            <label>Workers needed <span class="muted">(by job type — assignments come in the Event Ops module)</span></label>
            <div class="worker-grid">
                <?php foreach ($jobTypes as $jt): ?>
                    <label class="worker-cell">
                        <span><?= e($jt['name']) ?></span>
                        <input type="number" name="workers[<?= (int)$jt['id'] ?>]" min="0" max="999"
                               value="<?= isset($oldWorkers[(int)$jt['id']]) ? (int)$oldWorkers[(int)$jt['id']] : '' ?>" placeholder="0">
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="field-row">
            <div class="field">
                <label for="equipment_needed">Equipment needed</label>
                <textarea id="equipment_needed" name="equipment_needed" rows="2" placeholder="Chains, headsets, shot clock..."><?= e((string)$oldNeeds['equipment_needed']) ?></textarea>
            </div>
            <div class="field">
                <label for="social_content_needed">Social content needed</label>
                <textarea id="social_content_needed" name="social_content_needed" rows="2" placeholder="Starting lineup graphic, score updates..."><?= e((string)$oldNeeds['social_content_needed']) ?></textarea>
            </div>
        </div>
        <div class="field">
            <label class="check"><input type="checkbox" name="transportation_needed" value="1"<?= $oldNeeds['transportation_needed'] ? ' checked' : '' ?>> Transportation needed</label>
            <input name="transportation_details" maxlength="255" value="<?= e((string)$oldNeeds['transportation_details']) ?>" placeholder="2 buses, depart 3:30 PM" aria-label="Transportation details">
            <label class="check"><input type="checkbox" name="transportation_arranged" value="1"<?= $oldNeeds['transportation_arranged'] ? ' checked' : '' ?>> Transportation arranged</label>
        </div>
    </div>

    <div class="form-actions sticky-actions">
        <button type="submit" class="btn btn-primary"><?= $event ? 'Save changes' : 'Add event' ?></button>
        <?php if (!$event): ?>
            <button type="submit" name="save_add_another" value="1" class="btn">Save &amp; add another</button>
        <?php endif; ?>
        <a class="btn" href="<?= e(BASE_URL) ?>/schedule.php">Cancel</a>
    </div>
</form>

<?php if ($event): ?>
    <div class="card danger-zone">
        <form method="post" action="<?= e(BASE_URL) ?>/event_edit.php" data-confirm="Delete this event? This cannot be undone.">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-danger">Delete event</button>
        </form>
    </div>
<?php endif; ?>
