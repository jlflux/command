<h1><?= $opponent ? 'Edit opponent' : 'Add opponent' ?></h1>

<div class="card narrow">
    <form method="post" action="<?= e(BASE_URL) ?>/opponent_edit.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($opponent): ?>
            <input type="hidden" name="id" value="<?= (int)$opponent['id'] ?>">
        <?php endif; ?>

        <div class="field">
            <label for="name">School name</label>
            <input id="name" name="name" required maxlength="150" value="<?= e($old['name']) ?>" placeholder="Vestavia Hills High School">
        </div>
        <div class="field-row">
            <div class="field">
                <label for="mascot">Mascot</label>
                <input id="mascot" name="mascot" maxlength="80" value="<?= e((string)$old['mascot']) ?>" placeholder="Rebels">
            </div>
            <div class="field">
                <label for="city">City</label>
                <input id="city" name="city" maxlength="120" value="<?= e((string)$old['city']) ?>" placeholder="Vestavia Hills, AL">
            </div>
        </div>
        <div class="field">
            <label for="logo">Logo <span class="muted">(PNG, JPG, or WebP; max 2&nbsp;MB)</span></label>
            <?php if ($opponent && $opponent['logo_path']): ?>
                <p><img src="<?= e(BASE_URL . '/' . $opponent['logo_path']) ?>" alt="Current logo" class="opponent-logo"></p>
            <?php endif; ?>
            <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="field">
            <label for="website">Website</label>
            <input id="website" name="website" type="url" maxlength="255" value="<?= e((string)$old['website']) ?>" placeholder="https://...">
        </div>
        <div class="field-row">
            <div class="field">
                <label for="twitter">X / Twitter handle</label>
                <input id="twitter" name="twitter" maxlength="80" value="<?= e((string)$old['twitter']) ?>" placeholder="@vhhsathletics">
            </div>
            <div class="field">
                <label for="instagram">Instagram handle</label>
                <input id="instagram" name="instagram" maxlength="80" value="<?= e((string)$old['instagram']) ?>" placeholder="@vhhsathletics">
            </div>
        </div>
        <div class="field">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="3" placeholder="Parking, entry gate, AD contact..."><?= e((string)$old['notes']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $opponent ? 'Save changes' : 'Add opponent' ?></button>
            <a class="btn" href="<?= e(BASE_URL) ?>/opponents.php">Cancel</a>
        </div>
    </form>
</div>
