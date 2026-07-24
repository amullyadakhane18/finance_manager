<?php
/**
 * profile/edit_profile.php
 * Update Name, Email, and (optional) Phone.
 */

declare(strict_types=1);
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helper.php';

$basePath  = '../';
$activeNav = 'profile';
$userId    = (int)$_SESSION['user_id'];

$errors = [];
$name  = '';
$email = '';
$phone = '';

try {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT full_name, email, phone FROM users WHERE id = :uid');
    $stmt->execute(['uid' => $userId]);
    $current = $stmt->fetch();
} catch (PDOException $e) {
    // Fallback if `phone` column hasn't been added yet.
    $stmt = $pdo->prepare('SELECT full_name, email FROM users WHERE id = :uid');
    $stmt->execute(['uid' => $userId]);
    $current = $stmt->fetch();
    $current['phone'] = null;
}

$name  = $current['full_name'] ?? '';
$email = $current['email'] ?? '';
$phone = $current['phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));

        if ($name === '') {
            $errors[] = 'Full name is required.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
            $errors[] = 'Enter a valid phone number, or leave it blank.';
        }

        if (empty($errors)) {
            try {
                // Email must stay unique across other accounts.
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id != :uid');
                $stmt->execute(['email' => $email, 'uid' => $userId]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $errors[] = 'That email is already in use by another account.';
                }
            } catch (PDOException $e) {
                $errors[] = "Couldn't validate your email right now. Please try again.";
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, email = :email, phone = :phone WHERE id = :uid');
                $stmt->execute([
                    'full_name'  => $name,
                    'email' => $email,
                    'phone' => $phone !== '' ? $phone : null,
                    'uid'   => $userId,
                ]);
                $_SESSION['user_name'] = $name;
                header('Location: view_profile.php?updated=profile');
                exit;
            } catch (PDOException $e) {
                // Most likely the `phone` column isn't there yet — retry without it.
                try {
                    $stmt = $pdo->prepare('UPDATE users SET full_name = :full_name, email = :email WHERE id = :uid');
                    $stmt->execute(['full_name' => $name, 'email' => $email, 'uid' => $userId]);
                    $_SESSION['user_name'] = $name;
                    header('Location: view_profile.php?updated=profile');
                    exit;
                } catch (PDOException $e2) {
                    $errors[] = "Couldn't save your profile right now. Please try again.";
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
<title>Edit Profile — Finance Manager</title>
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
      <h1>Edit profile</h1>
      <p>Update your name, email, and phone number.</p>
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
      <form method="POST" action="edit_profile.php" class="stacked-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="field">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="field">
          <label for="phone">Phone <span class="text-muted">(optional)</span></label>
          <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars((string)$phone, ENT_QUOTES, 'UTF-8') ?>" placeholder=" ">
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Save changes</button>
          <a href="view_profile.php" class="btn btn--ghost">Cancel</a>
        </div>
      </form>
    </section>

  </main>

</div>

</body>
</html>
