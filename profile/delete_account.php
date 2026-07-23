<?php
/**
 * profile/delete_account.php
 * Permanently deletes the account after password confirmation.
 * Removes income, expenses, and budgets rows for this user, then the
 * user row itself, in a single transaction.
 */

declare(strict_types=1);
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helper.php';

$basePath  = '../';
$activeNav = 'profile';
$userId    = (int)$_SESSION['user_id'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    } else {
        $password = (string)($_POST['password'] ?? '');

        if ($password === '') {
            $errors[] = 'Enter your password to confirm.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare('SELECT password, profile_photo FROM users WHERE id = :uid');
                $stmt->execute(['uid' => $userId]);
                $row = $stmt->fetch();

                if (!$row || !password_verify($password, $row['password'])) {
                    $errors[] = 'Incorrect password.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        foreach (['budgets', 'expenses', 'income'] as $table) {
                            try {
                                $pdo->prepare("DELETE FROM {$table} WHERE user_id = :uid")->execute(['uid' => $userId]);
                            } catch (PDOException $e) {
                                // Table may not exist yet on this install — nothing to clean up.
                            }
                        }
                        $pdo->prepare('DELETE FROM users WHERE id = :uid')->execute(['uid' => $userId]);
                        $pdo->commit();

                        // Remove the profile photo file, if any, now that the DB row is gone.
                        if (!empty($row['profile_photo'])) {
                            $path = __DIR__ . '/uploads/' . $row['profile_photo'];
                            if (is_file($path)) {
                                @unlink($path);
                            }
                        }

                        session_unset();
                        session_destroy();
                        header('Location: ' . $basePath . 'login.php?deleted=1');
                        exit;
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $errors[] = "Couldn't delete your account right now. Please try again.";
                    }
                }
            } catch (PDOException $e) {
                $errors[] = "Couldn't delete your account right now. Please try again.";
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
<title>Delete Account — Finance Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css">
</head>
<body class="dash-body">

<div class="dash-shell">

  <?php require __DIR__ . '/../includes/sidebar.php'; ?>

  <main class="dash-main">

    <section class="dash-welcome">
      <h1>Delete account</h1>
      <p>This is permanent and cannot be undone.</p>
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

    <section class="dash-panel danger-zone">
      <header class="dash-panel__header">
        <h2>This will permanently delete:</h2>
      </header>
      <ul class="danger-zone__list">
        <li>Your profile and login details</li>
        <li>All income entries</li>
        <li>All expense entries</li>
        <li>All budgets</li>
      </ul>

      <form method="POST" action="delete_account.php" class="stacked-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="field">
          <label for="password">Confirm your password</label>
          <input type="password" id="password" name="password" required>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn--danger">Yes, permanently delete my account</button>
          <a href="profile.php" class="btn btn--ghost">Cancel</a>
        </div>
      </form>
    </section>

  </main>

</div>

</body>
</html>