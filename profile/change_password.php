<?php
/**
 * profile/change_password.php
 * Verify current password, then hash and store a new one.
 *
 * Field IDs (#password, #confirm_password, .strength-meter, #strength-label,
 * #match-label, .toggle-visibility) intentionally match main.js's existing
 * selectors, so the strength meter and live match-check work for free.
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
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            $errors[] = 'Fill in all three fields.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password and confirmation do not match.';
        } else {
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :uid');
                $stmt->execute(['uid' => $userId]);
                $row = $stmt->fetch();

                if (!$row || !password_verify($current, $row['password'])) {
                    $errors[] = 'Current password is incorrect.';
                } else {
                    $hash = password_hash($new, PASSWORD_DEFAULT);

                    try {
                        $stmt = $pdo->prepare('UPDATE users SET password = :hash, password_changed_at = NOW() WHERE id = :uid');
                        $stmt->execute(['hash' => $hash, 'uid' => $userId]);
                    } catch (PDOException $e) {
                        // `password_changed_at` column not added yet — update password only.
                        $stmt = $pdo->prepare('UPDATE users SET password = :hash WHERE id = :uid');
                        $stmt->execute(['hash' => $hash, 'uid' => $userId]);
                    }

                    header('Location: profile.php?updated=password');
                    exit;
                }
            } catch (PDOException $e) {
                $errors[] = "Couldn't update your password right now. Please try again.";
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
<title>Change Password — Finance Manager</title>
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
      <h1>Change password</h1>
      <p>Choose a strong password you don't use anywhere else.</p>
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
      <form method="POST" action="change_password.php" class="stacked-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <div class="field">
          <label for="current_password">Current Password</label>
          <div class="input-wrap">
            <input type="password" id="current_password" name="current_password" required>
            <button type="button" class="toggle-visibility" data-target="current_password" aria-label="Show password">
              <svg viewBox="0 0 24 24" class="icon-inline"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <div class="field">
          <label for="password">New Password</label>
          <div class="input-wrap">
            <input type="password" id="password" name="password" required minlength="8">
            <button type="button" class="toggle-visibility" data-target="password" aria-label="Show password">
              <svg viewBox="0 0 24 24" class="icon-inline"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="strength-meter"><span></span><span></span><span></span><span></span></div>
          <p class="field-hint" id="strength-label">Use 8+ characters with a mix of letters and numbers.</p>
        </div>

        <div class="field">
          <label for="confirm_password">Confirm New Password</label>
          <div class="input-wrap">
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            <button type="button" class="toggle-visibility" data-target="confirm_password" aria-label="Show password">
              <svg viewBox="0 0 24 24" class="icon-inline"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <p class="field-hint" id="match-label"></p>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn--primary">Update password</button>
          <a href="profile.php" class="btn btn--ghost">Cancel</a>
        </div>
      </form>
    </section>

  </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ---- Show / hide password (self-contained — doesn't depend on main.js) ----
    document.querySelectorAll('.toggle-visibility').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            if (!input) return;
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            btn.classList.toggle('is-visible', isHidden);
        });
    });

    // ---- Password strength meter ----
    var passwordInput = document.getElementById('password');
    var meter = document.querySelector('.strength-meter');
    var strengthLabel = document.getElementById('strength-label');

    function scorePassword(value) {
        var score = 0;
        if (value.length >= 8) score++;
        if (value.length >= 12) score++;
        if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
        if (/[0-9]/.test(value) && /[^A-Za-z0-9]/.test(value)) score++;
        return Math.min(score, 4);
    }

    var labels = [
        'Use 8+ characters with a mix of letters and numbers.',
        'Weak — try adding a number or symbol.',
        'Okay — a little longer would help.',
        'Good password.',
        'Strong password.'
    ];

    if (passwordInput && meter) {
        passwordInput.addEventListener('input', function () {
            var score = passwordInput.value.length ? Math.max(scorePassword(passwordInput.value), 1) : 0;
            meter.className = 'strength-meter' + (score ? ' level-' + score : '');
            if (strengthLabel) strengthLabel.textContent = labels[score];
        });
    }

    // ---- Live "passwords match" check ----
    var confirmInput = document.getElementById('confirm_password');
    var matchLabel = document.getElementById('match-label');

    function checkMatch() {
        if (!confirmInput.value) {
            matchLabel.textContent = '';
            return;
        }
        if (passwordInput.value === confirmInput.value) {
            matchLabel.textContent = 'Passwords match.';
            matchLabel.classList.remove('field-hint--error');
        } else {
            matchLabel.textContent = 'Passwords do not match.';
            matchLabel.classList.add('field-hint--error');
        }
    }

    if (passwordInput && confirmInput && matchLabel) {
        confirmInput.addEventListener('input', checkMatch);
        passwordInput.addEventListener('input', checkMatch);
    }
});
</script>
</body>
</html>