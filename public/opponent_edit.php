<?php
declare(strict_types=1);

require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/schedule.php';

$user = require_role('manager');
$schoolId = current_school_id();

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$opponent = null;
if ($id > 0) {
    $opponent = tenant_fetch(
        'SELECT * FROM opponents WHERE id = :id AND school_id = :school_id',
        ['id' => $id]
    ) ?? not_found();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $in = [
        'name'      => trim($_POST['name'] ?? ''),
        'mascot'    => trim($_POST['mascot'] ?? '') ?: null,
        'city'      => trim($_POST['city'] ?? '') ?: null,
        'website'   => trim($_POST['website'] ?? '') ?: null,
        'twitter'   => ltrim(trim($_POST['twitter'] ?? ''), '@') ?: null,
        'instagram' => ltrim(trim($_POST['instagram'] ?? ''), '@') ?: null,
        'notes'     => trim($_POST['notes'] ?? '') ?: null,
    ];

    if ($in['name'] === '' || mb_strlen($in['name']) > 150) {
        $errors[] = 'Opponent name is required (max 150 characters).';
    }
    if ($in['website'] !== null && !filter_var($in['website'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Website must be a full URL (https://...).';
    }
    foreach (['mascot' => 80, 'city' => 120, 'twitter' => 80, 'instagram' => 80] as $field => $max) {
        if ($in[$field] !== null && mb_strlen($in[$field]) > $max) {
            $errors[] = ucfirst($field) . " must be $max characters or fewer.";
        }
    }

    // Optional logo upload (validated like the school logo)
    $logoTmp = null;
    $logoExt = null;
    $file = $_FILES['logo'] ?? null;
    if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Logo upload failed — try again.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Logo must be 2 MB or smaller.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            if (!isset($extensions[$mime])) {
                $errors[] = 'Logo must be a PNG, JPG, or WebP image.';
            } else {
                $logoTmp = $file['tmp_name'];
                $logoExt = $extensions[$mime];
            }
        }
    }

    if (!$errors) {
        try {
            if ($opponent === null) {
                tenant_query(
                    'INSERT INTO opponents (school_id, name, mascot, city, website, twitter, instagram, notes)
                     VALUES (:school_id, :name, :mascot, :city, :website, :twitter, :instagram, :notes)',
                    $in
                );
                $id = (int)db()->lastInsertId();
            } else {
                tenant_query(
                    'UPDATE opponents SET name = :name, mascot = :mascot, city = :city, website = :website,
                            twitter = :twitter, instagram = :instagram, notes = :notes
                     WHERE id = :id AND school_id = :school_id',
                    $in + ['id' => $id]
                );
            }

            if ($logoTmp !== null) {
                $dir = __DIR__ . '/uploads/' . $schoolId . '/opponents';
                if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                    throw new RuntimeException('mkdir failed for ' . $dir);
                }
                foreach (glob($dir . '/opp_' . $id . '.*') ?: [] as $oldLogo) {
                    unlink($oldLogo);
                }
                if (!move_uploaded_file($logoTmp, $dir . '/opp_' . $id . '.' . $logoExt)) {
                    throw new RuntimeException('move_uploaded_file failed');
                }
                tenant_query(
                    'UPDATE opponents SET logo_path = :path WHERE id = :id AND school_id = :school_id',
                    ['path' => 'uploads/' . $schoolId . '/opponents/opp_' . $id . '.' . $logoExt, 'id' => $id]
                );
            }

            flash($opponent === null ? "{$in['name']} added." : "{$in['name']} updated.");
            redirect('/opponents.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $errors[] = 'An opponent with that name already exists.';
            } else {
                error_log('[opponent_edit] ' . $ex->getMessage());
                $errors[] = 'Could not save the opponent. Try again.';
            }
        } catch (RuntimeException $ex) {
            error_log('[opponent_edit] ' . $ex->getMessage());
            $errors[] = 'Saved, but the logo could not be stored. Try uploading it again.';
        }
    }

    foreach ($errors as $error) {
        flash($error, 'error');
    }
    $old = $in;
} else {
    $old = [
        'name'      => $opponent['name'] ?? '',
        'mascot'    => $opponent['mascot'] ?? '',
        'city'      => $opponent['city'] ?? '',
        'website'   => $opponent['website'] ?? '',
        'twitter'   => $opponent['twitter'] ?? '',
        'instagram' => $opponent['instagram'] ?? '',
        'notes'     => $opponent['notes'] ?? '',
    ];
}

view('opponent_form', [
    'title'    => $opponent ? 'Edit opponent' : 'Add opponent',
    'active'   => 'schedule',
    'user'     => $user,
    'opponent' => $opponent,
    'old'      => $old,
]);
