<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config.php';

// ---- CSRF token ----
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$old = ['full_name' => '', 'email' => ''];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- CSRF check ----
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    }

    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');
    $terms    = isset($_POST['terms']);

    $old['full_name'] = $fullName;
    $old['email']     = $email;

    // ---- Validation ----
    if ($fullName === '' || mb_strlen($fullName) < 2) {
        $errors[] = 'Please enter your full name.';
    } elseif (mb_strlen($fullName) > 120) {
        $errors[] = 'Full name is too long.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must include at least one letter and one number.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$terms) {
        $errors[] = 'You must accept the Terms of Service and Privacy Policy.';
    }

    // ---- Attempt insert if no errors so far ----
    if (empty($errors)) {
        try {
            $pdo = get_db_connection();

            $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $check->execute(['email' => $email]);

            if ($check->fetch()) {
                $errors[] = 'An account with that email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $insert = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password) VALUES (:full_name, :email, :password)'
                );
                $insert->execute([
                    'full_name' => $fullName,
                    'email'     => $email,
                    'password'  => $hash,
                ]);

                $success = true;
                $old = ['full_name' => '', 'email' => ''];
                // Refresh CSRF token after a successful submit
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Uncomment to redirect straight to the login page instead of
                // showing the inline success state below:
                // header('Location: login.php?registered=1');
                // exit;
            }
        } catch (PDOException $e) {
            // Don't leak DB internals to the user.
            $errors[] = 'Something went wrong on our end. Please try again shortly.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create your account — Finance Manager</title>
<link rel="icon" href="assets/images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="page">

  <!-- Brand / signature panel -->
  <aside class="brand-panel">
    <div class="brand-panel__inner">
      <a href="index.php" class="brand-mark">
        <span class="brand-mark__glyph">₹</span>
        <span class="brand-mark__word">Finance Manager</span>
      </a>

      <div class="brand-copy">
        <h1>Every rupee,<br>accounted for.</h1>
        <p>Track spending, plan budgets, and watch your savings climb — all from one calm, uncluttered dashboard.</p>
      </div>

      <div class="ledger-chart" aria-hidden="true">
        <svg viewBox="0 0 460 200" class="ledger-chart__svg">
          <line x1="0" y1="164" x2="460" y2="164" class="ledger-chart__base"/>
          <polyline
            class="ledger-chart__line"
            fill="none"
            points="0,150 55,140 100,148 150,110 200,120 250,78 300,92 350,54 400,60 460,26"
          />
          <circle class="ledger-chart__dot" cx="460" cy="26" r="5"/>
        </svg>
        <div class="ledger-chart__legend">
          <span><i class="dot dot--income"></i>Income</span>
          <span><i class="dot dot--savings"></i>Savings</span>
          <span><i class="dot dot--expense"></i>Expenses</span>
        </div>
      </div>

      <ul class="brand-points">
        <li>Bank-grade password encryption</li>
        <li>Custom budgets by category</li>
        <li>Clear monthly summaries</li>
      </ul>
    </div>
  </aside>

  <!-- Form panel -->
  <main class="form-panel">
    <div class="form-card">

      <?php if ($success): ?>

        <div class="success-state" role="status">
          <svg class="success-state__icon" viewBox="0 0 52 52" aria-hidden="true">
            <circle cx="26" cy="26" r="25" fill="none"/>
            <path fill="none" d="M14 27l7 7 17-17"/>
          </svg>
          <h2>Account created</h2>
          <p>Welcome aboard — your Finance Manager account is ready. You can sign in now.</p>
          <a class="btn btn--primary" href="login.php">Go to sign in</a>
        </div>

      <?php else: ?>

        <header class="form-card__header">
          <h2>Create your account</h2>
          <p>Start managing your money in a few minutes.</p>
        </header>

        <?php if (!empty($errors)): ?>
          <div class="alert alert--error" role="alert">
            <strong>Please fix the following:</strong>
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="reg-form" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <div class="field">
            <label for="full_name">Full name</label>
            <input
              type="text"
              id="full_name"
              name="full_name"
              placeholder="Asha Verma"
              value="<?= htmlspecialchars($old['full_name'], ENT_QUOTES, 'UTF-8') ?>"
              autocomplete="name"
              required
            >
          </div>

          <div class="field">
            <label for="email">Email address</label>
            <input
              type="email"
              id="email"
              name="email"
              placeholder="you@example.com"
              value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8') ?>"
              autocomplete="email"
              required
            >
          </div>

          <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
              <input
                type="password"
                id="password"
                name="password"
                placeholder="At least 8 characters"
                autocomplete="new-password"
                minlength="8"
                required
              >
              <button type="button" class="toggle-visibility" data-target="password" aria-label="Show password">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="strength-meter" aria-hidden="true">
              <span></span><span></span><span></span><span></span>
            </div>
            <p class="field-hint" id="strength-label">Use 8+ characters with a mix of letters and numbers.</p>
          </div>

          <div class="field">
            <label for="confirm_password">Confirm password</label>
            <div class="input-wrap">
              <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                placeholder="Re-enter your password"
                autocomplete="new-password"
                minlength="8"
                required
              >
              <button type="button" class="toggle-visibility" data-target="confirm_password" aria-label="Show password">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <p class="field-hint field-hint--error" id="match-label"></p>
          </div>

          <label class="checkbox">
            <input type="checkbox" name="terms" required>
            <span>I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a></span>
          </label>

          <button type="submit" class="btn btn--primary btn--block">Create account</button>
        </form>

        <p class="alt-action">Already have an account? <a href="login.php">Sign in</a></p>

      <?php endif; ?>

    </div>
  </main>

</div>

<script src="assets/js/main.js"></script>
</body>
</html>