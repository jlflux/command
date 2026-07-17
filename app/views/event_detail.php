<div class="page-head">
    <h1><span class="sport-icon"><?= e($event['sport_icon']) ?></span> <?= e(event_title($event)) ?></h1>
    <?php if ($canManage): ?>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/event_edit.php?id=<?= (int)$event['id'] ?>">Edit</a>
    <?php endif; ?>
</div>

<?php if ($alerts): ?>
    <div class="card readiness-card">
        <h2>Readiness</h2>
        <span class="badges">
            <?php foreach ($alerts as $alert): ?>
                <span class="badge badge-<?= e($alert['severity']) ?>"><?= e($alert['label']) ?></span>
            <?php endforeach; ?>
        </span>
    </div>
<?php elseif ($event['status'] === 'scheduled' && $event['event_date'] >= date('Y-m-d')): ?>
    <div class="card readiness-card">
        <h2>Readiness</h2>
        <span class="badge badge-ready">Ready — nothing missing</span>
    </div>
<?php endif; ?>

<div class="cards">
    <div class="card">
        <h2>Details</h2>
        <dl class="detail-list">
            <dt>Date</dt><dd><?= e(fmt_date($event['event_date'])) ?><?= $event['start_time'] ? ' · ' . e(fmt_time($event['start_time'])) : '' ?></dd>
            <dt>Type</dt><dd><?= e($event['event_type_name']) ?></dd>
            <dt>Home/Away</dt><dd><?= e(ucfirst($event['home_away'])) ?></dd>
            <dt>Location</dt><dd><?= e(event_location($event)) ?: '<span class="muted">—</span>' ?></dd>
            <dt>Status</dt><dd><?= e(ucfirst($event['status'])) ?>
                <?php if ($result = event_result_label($event)): ?>
                    <span class="badge badge-result badge-<?= e($event['result']) ?>"><?= e($result) ?></span>
                <?php endif; ?>
            </dd>
            <dt>Tickets</dt><dd><?php if ($event['ticket_link']): ?><a href="<?= e($event['ticket_link']) ?>" target="_blank" rel="noopener"><?= e($event['ticket_link']) ?></a><?php else: ?><span class="muted">none</span><?php endif; ?></dd>
            <dt>Broadcast</dt><dd>
                <?php if (!$event['broadcast_planned']): ?><span class="muted">not planned</span>
                <?php elseif ($event['broadcast_link']): ?><a href="<?= e($event['broadcast_link']) ?>" target="_blank" rel="noopener"><?= e($event['broadcast_link']) ?></a>
                <?php else: ?><span class="muted">planned — no link yet</span><?php endif; ?>
            </dd>
            <?php if ($event['notes']): ?><dt>Notes</dt><dd><?= nl2br(e($event['notes'])) ?></dd><?php endif; ?>
        </dl>
    </div>

    <?php if ($event['opponent_id']): ?>
    <div class="card">
        <h2>Opponent</h2>
        <div class="opponent-summary">
            <?php if ($event['opponent_logo']): ?>
                <img src="<?= e(BASE_URL . '/' . $event['opponent_logo']) ?>" alt="" class="opponent-logo">
            <?php endif; ?>
            <div>
                <strong><?= e($event['opponent_name']) ?></strong>
                <?php if ($event['opponent_mascot']): ?><div class="muted"><?= e($event['opponent_mascot']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Operational needs</h2>
        <?php if ($workerNeeds): ?>
            <h3 class="sub-h">Workers</h3>
            <ul class="plain-list">
                <?php foreach ($workerNeeds as $wn): ?>
                    <li><?= e($wn['job_type_name']) ?>: <?= (int)$wn['workers_needed'] ?> needed <span class="muted">(0 assigned — staffing comes in Event Ops)</span></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($needs && $needs['equipment_needed']): ?>
            <h3 class="sub-h">Equipment</h3><p><?= nl2br(e($needs['equipment_needed'])) ?></p>
        <?php endif; ?>
        <?php if ($needs && $needs['social_content_needed']): ?>
            <h3 class="sub-h">Social content</h3><p><?= nl2br(e($needs['social_content_needed'])) ?></p>
        <?php endif; ?>
        <?php if ($needs && $needs['transportation_needed']): ?>
            <h3 class="sub-h">Transportation</h3>
            <p><?= $needs['transportation_arranged'] ? 'Arranged' : '<strong>Not arranged</strong>' ?>
               <?= $needs['transportation_details'] ? '— ' . e($needs['transportation_details']) : '' ?></p>
        <?php endif; ?>
        <?php if (!$workerNeeds && (!$needs || (!$needs['equipment_needed'] && !$needs['social_content_needed'] && !$needs['transportation_needed']))): ?>
            <p class="muted">No operational needs recorded.</p>
        <?php endif; ?>
    </div>
</div>

<p><a href="<?= e(BASE_URL) ?>/schedule.php">&larr; Back to schedule</a></p>
