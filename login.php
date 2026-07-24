<?php
/**
 * login.php
 * User login page for Finance Manager.
 * Verifies credentials and starts a session on success.
 */

declare(strict_types=1);
session_start();


require_once __DIR__ . '/includes/db.php';

// Already logged in? Skip straight to the dashboard.
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// ---- CSRF token ----
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$old = ['email' => ''];
$justRegistered = isset($_GET['registered']);
$justLoggedOut  = isset($_GET['loggedout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- CSRF check ----
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    }

    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $old['email'] = $email;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        try {
            $pdo = get_db_connection();

            $stmt = $pdo->prepare('SELECT id, full_name, email, password FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            // Generic message either way — never reveal whether the email exists.
            if (!$user || !password_verify($password, $user['password'])) {
                $errors[] = 'Incorrect email or password.';
            }  
            else {
                // ---- Start authenticated session ----
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];

                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
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
<title>Sign in — Finance Manager</title>
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
        <h1>Welcome back.<br>Let's check the numbers.</h1>
        <p>Sign in to see where your money went, what's left to spend, and how close you are to your goals.</p>
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
        <li>Your data stays in your own database</li>
        <li>Session-protected dashboard</li>
        <li>Pick up right where you left off</li>
      </ul>
    </div>
  </aside>

  <!-- Form panel -->
  <main class="form-panel">
    <div class="form-card">

      <header class="form-card__header">
        <h2>Sign in</h2>
        <p>Enter your details to access your dashboard.</p>
      </header>

      <?php if ($justRegistered): ?>
        <div class="alert alert--success" role="status">
          Account created — please sign in to continue.
        </div>
      <?php endif; ?>

      <?php if ($justLoggedOut): ?>
        <div class="alert alert--success" role="status">
          You've been signed out.
        </div>
      <?php endif; ?>

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

      <form method="POST" action="login.php" class="reg-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

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
            autofocus
          >
        </div>

        <div class="field">
          <div class="field-label-row">
            <label for="password">Password</label>
            <a class="field-link" href="forgot-password.php">Forgot password?</a>
          </div>
          <div class="input-wrap">
            <input
              type="password"
              id="password"
              name="password"
              placeholder="Enter your password"
              autocomplete="current-password"
              required
            >
            <button type="button" class="toggle-visibility" data-target="password" aria-label="Show password">
              <svg viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>

        <label class="checkbox">
          <input type="checkbox" name="remember">
          <span>Keep me signed in on this device</span>
        </label>

        <button type="submit" class="btn btn--primary btn--block">Sign in</button>
      </form>

      <p class="alt-action">Don't have an account? <a href="register.php">Create one</a></p>

    </div>
  </main>

</div>

<script src="assets/js/main.js"></script>
</body>
</html>