<?php
/**
 * profile/upload_photo.php
 * Upload/replace the profile picture. Accepts JPG, JPEG, PNG up to 2MB.
 * Stores files in profile/uploads/ and saves only the bare filename in
 * users.profile_photo — profile.php then renders it as "uploads/<filename>".
 *
 * IMPORTANT — one-time setup on your server:
 *   1. Make sure profile/uploads/ exists and is writable by PHP
 *      (e.g. chmod 755 profile/uploads).
 *   2. Add a profile/uploads/.htaccess containing:
 *        php_flag engine off
 *      so an uploaded file can never be executed as a script.
 */

declare(strict_types=1);
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helper.php';

$basePath  = '../';
$activeNav = 'profile';
$userId    = (int)$_SESSION['user_id'];

$errors = [];
$uploadDir = __DIR__ . '/uploads/';
$allowedExt = ['jpg', 'jpeg', 'png'];
$allowedMime = ['image/jpeg', 'image/png'];
$maxBytes = 2 * 1024 * 1024; // 2MB

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT profile_photo FROM users WHERE id = :uid');
    $stmt->execute(['uid' => $userId]);
    $currentPhoto = $stmt->fetchColumn() ?: null;
} catch (PDOException $e) {
     die("Database Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    } elseif (empty($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Choose a photo to upload.';
    } else {
        $file = $_FILES['photo'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed. Please try again.';
        } elseif ($file['size'] > $maxBytes) {
            $errors[] = 'That file is too large — please choose one under 2MB.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mime = mime_content_type($file['tmp_name']) ?: '';

            if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true)) {
                $errors[] = 'Only JPG, JPEG, and PNG files are allowed.';
            } else {
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }

                $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
                $destination = $uploadDir . $filename;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = "Couldn't save the uploaded file. Check that profile/uploads/ is writable.";
                } else {
                    try {
                        $stmt = $pdo->prepare('UPDATE users SET profile_photo = :photo WHERE id = :uid');
                        $stmt->execute(['photo' => $filename, 'uid' => $userId]);

                        // Clean up the old photo file, if any.
                        if ($currentPhoto && is_file($uploadDir . $currentPhoto)) {
                            @unlink($uploadDir . $currentPhoto);
                        }

                        header('Location: view_profile.php?updated=photo');
                        exit;
                    } catch (PDOException $e) {
                        @unlink($destination);
                        $errors[] = "Couldn't save your photo. Run the ALTER TABLE statement at the top of profile.php, then try again.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Upload Profile Picture — Finance Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="icon" href="../assets/images/favicon.png">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css">
</head>
<body class="dash-body">

<div class="dash-shell">

  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <main class="dash-main">

    <section class="dash-welcome">
      <h1>Profile picture</h1>
      <p>Upload a JPG or PNG, up to 2MB.</p>
    </section>

    <?php if (!empty($errors)): ?>
      <div class="alert alert--error" role="alert">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <section class="dash-panel form-panel">
      <div class="upload-preview">
        <?php if ($currentPhoto): ?>
          <img src="uploads/<?= htmlspecialchars($currentPhoto, ENT_QUOTES, 'UTF-8') ?>" alt="Current profile photo" id="avatarPreview" class="profile-avatar profile-avatar--large">
        <?php else: ?>
          <div class="profile-avatar profile-avatar--large profile-avatar--placeholder" id="avatarPreview" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="52" height="52">
              <circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/>
            </svg>
          </div>
        <?php endif; ?>
      </div>

      <form method="POST" action="upload_photo.php" enctype="multipart/form-data" class="stacked-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="field">
          <label for="photo">Choose a photo</label>
          <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Upload photo</button>
          <a href="view_profile.php" class="btn btn--ghost">Cancel</a>
        </div>
      </form>
    </section>

  </main>

</div>

<script>
document.getElementById('photo').addEventListener('change', function (e) {
    var file = e.target.files[0];
    if (!file) return;
    var preview = document.getElementById('avatarPreview');
    var reader = new FileReader();
    reader.onload = function (ev) {
        var img = document.createElement('img');
        img.src = ev.target.result;
        img.alt = 'Selected profile photo';
        img.id = 'avatarPreview';
        img.className = 'profile-avatar profile-avatar--large';
        preview.replaceWith(img);
    };
    reader.readAsDataURL(file);
});
</script>

</body>
</html>