<div class="page-head">
    <h1>Schedule</h1>
    <?php if ($canManage): ?>
        <div class="btn-row">
            <a class="btn" href="<?= e(BASE_URL) ?>/opponents.php">Opponents</a>
            <a class="btn" href="<?= e(BASE_URL) ?>/event_bulk.php">Bulk add</a>
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/event_edit.php">Add event</a>
        </div>
    <?php endif; ?>
</div>

<div class="card filter-bar">
    <div class="view-tabs">
        <?php
        $tabs = ['week' => 'This Week', 'upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'];
        $keep = http_build_query(array_filter(['sport' => $filters['sport'], 'level' => $filters['level'], 'ha' => $filters['ha']]));
        ?>
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= e(BASE_URL) ?>/schedule.php?view=<?= e($key) ?><?= $keep ? '&' . e($keep) : '' ?>"
               class="tab<?= $filters['view'] === $key ? ' active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(BASE_URL) ?>/calendar.php" class="tab">Calendar</a>
    </div>
    <form method="get" action="<?= e(BASE_URL) ?>/schedule.php" class="filter-form">
        <input type="hidden" name="view" value="<?= e($filters['view'] === 'range' ? 'range' : $filters['view']) ?>">
        <select name="sport" aria-label="Sport">
            <option value="">All sports</option>
            <?php foreach ($sports as $s): ?>
                <option value="<?= (int)$s['id'] ?>"<?= $filters['sport'] === (int)$s['id'] ? ' selected' : '' ?>><?= e($s['icon'] . ' ' . $s['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="level" aria-label="Level">
            <option value="">All levels</option>
            <?php foreach ($levels as $l): ?>
                <option value="<?= (int)$l['id'] ?>"<?= $filters['level'] === (int)$l['id'] ? ' selected' : '' ?>><?= e($l['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="ha" aria-label="Home or away">
            <option value="">Home &amp; away</option>
            <?php foreach (['home' => 'Home', 'away' => 'Away', 'neutral' => 'Neutral'] as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= $filters['ha'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" value="<?= e($filters['from']) ?>" aria-label="From date">
        <input type="date" name="to" value="<?= e($filters['to']) ?>" aria-label="To date">
        <button type="submit" class="btn btn-sm">Filter</button>
    </form>
</div>

<?php if (!$events): ?>
    <div class="card">
        <p class="muted">No events for this view.
        <?php if ($canManage): ?>
            <a href="<?= e(BASE_URL) ?>/event_edit.php">Add the first one</a> or use
            <a href="<?= e(BASE_URL) ?>/event_bulk.php">bulk add</a> to enter a whole season.
        <?php endif; ?></p>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-scroll">
            <table class="table schedule-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Event</th>
                        <th>H/A</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Readiness</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php $prevDate = null; foreach ($events as $ev): ?>
                    <tr class="<?= $ev['status'] !== 'scheduled' ? 'status-' . e($ev['status']) : '' ?>">
                        <td class="nowrap"><?= $ev['event_date'] !== $prevDate ? e(fmt_date($ev['event_date'])) : '' ?><?php $prevDate = $ev['event_date']; ?></td>
                        <td class="nowrap"><?= e(fmt_time($ev['start_time'])) ?></td>
                        <td>
                            <a href="<?= e(BASE_URL) ?>/event.php?id=<?= (int)$ev['id'] ?>" class="event-link">
                                <span class="sport-icon"><?= e($ev['sport_icon']) ?></span>
                                <?= e(event_title($ev)) ?>
                            </a>
                        </td>
                        <td class="nowrap"><?= e(ucfirst($ev['home_away'])) ?></td>
                        <td><?= e(event_location($ev)) ?></td>
                        <td class="nowrap">
                            <?php if ($result = event_result_label($ev)): ?>
                                <span class="badge badge-result badge-<?= e($ev['result']) ?>"><?= e($result) ?></span>
                            <?php elseif ($ev['status'] !== 'scheduled'): ?>
                                <span class="badge badge-status"><?= e(ucfirst($ev['status'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $alerts = $readiness[(int)$ev['id']] ?? []; ?>
                            <?php if ($alerts): ?>
                                <span class="badges">
                                <?php foreach ($alerts as $alert): ?>
                                    <span class="badge badge-<?= e($alert['severity']) ?>"><?= e($alert['label']) ?></span>
                                <?php endforeach; ?>
                                </span>
                            <?php elseif ($ev['status'] === 'scheduled' && $ev['event_date'] >= date('Y-m-d')): ?>
                                <span class="badge badge-ready">Ready</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <?php if ($canManage): ?>
                                <a class="btn btn-sm" href="<?= e(BASE_URL) ?>/event_edit.php?id=<?= (int)$ev['id'] ?>">Edit</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
