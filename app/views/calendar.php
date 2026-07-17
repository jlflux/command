<div class="page-head">
    <h1>Calendar</h1>
    <div class="btn-row">
        <a class="btn" href="<?= e(BASE_URL) ?>/schedule.php">List view</a>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/event_edit.php">Add event</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="cal-nav">
        <a class="btn btn-sm" href="<?= e(BASE_URL) ?>/calendar.php?month=<?= e($prevMonth) ?>">&larr; Prev</a>
        <strong class="cal-title"><?= e($monthLabel) ?></strong>
        <a class="btn btn-sm" href="<?= e(BASE_URL) ?>/calendar.php?month=<?= e($nextMonth) ?>">Next &rarr;</a>
    </div>

    <div class="table-scroll">
        <table class="cal-grid">
            <thead>
                <tr>
                    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow): ?>
                        <th><?= e($dow) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($weeks as $week): ?>
                <tr>
                    <?php foreach ($week as $day): ?>
                        <?php
                        $inMonth = str_starts_with($day, $month);
                        $isToday = $day === date('Y-m-d');
                        ?>
                        <td class="cal-cell<?= $inMonth ? '' : ' cal-out' ?><?= $isToday ? ' cal-today' : '' ?>">
                            <div class="cal-daynum"><?= (int)substr($day, 8, 2) ?></div>
                            <?php foreach ($byDay[$day] ?? [] as $ev): ?>
                                <a class="cal-event<?= isset($readiness[(int)$ev['id']]) ? ' has-alerts' : '' ?><?= $ev['status'] !== 'scheduled' ? ' status-' . e($ev['status']) : '' ?>"
                                   href="<?= e(BASE_URL) ?>/event.php?id=<?= (int)$ev['id'] ?>"
                                   title="<?= e(event_title($ev)) ?>">
                                    <?= e($ev['sport_icon']) ?>
                                    <?= $ev['start_time'] ? e(fmt_time($ev['start_time'])) : '' ?>
                                    <?= e($ev['opponent_name'] ?? $ev['event_type_name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted cal-legend">Outlined events have readiness alerts — open them to see what's missing.</p>
</div>
