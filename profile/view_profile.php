<?php
/**
 * profile/profile.php
 * Account page — view profile, financial summary, account info, and security.
 *
 * ---------------------------------------------------------------------------
 * REQUIRED ONE-TIME SETUP — run this against your `users` table before use:
 *
 *   ALTER TABLE users
 *     ADD COLUMN phone VARCHAR(20) NULL,
 *     ADD COLUMN profile_photo VARCHAR(255) NULL,
 *     ADD COLUMN last_login DATETIME NULL,
 *     ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active',
 *     ADD COLUMN password_changed_at DATETIME NULL;
 *
 * Also, in login.php, right after a successful password check, add:
 *   $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :uid')
 *       ->execute(['uid' => $user['id']]);
 * so "Last Login" has real data to show.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helper.php';

$basePath  = '../';
$activeNav = 'profile';
$userId    = (int)$_SESSION['user_id'];

$flash = null;
if (isset($_GET['updated'])) {
    $flashMap = [
        'profile'  => 'Profile updated.',
        'password' => 'Password changed successfully.',
        'photo'    => 'Profile picture updated.',
    ];
    $flash = $flashMap[$_GET['updated']] ?? 'Saved.';
}

$user              = null;
$loadError         = null;
$totalIncome       = 0.0;
$totalExpense      = 0.0;
$totalTransactions = 0;

try {
    $pdo = get_db_connection();

    // ---- User record (new columns first, fall back if not migrated yet) ----
    try {
        $stmt = $pdo->prepare(
            'SELECT id, name, email, phone, profile_photo, created_at, last_login, status, password_changed_at
             FROM users WHERE id = :uid'
        );
        $stmt->execute(['uid' => $userId]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        $stmt = $pdo->prepare('SELECT id, name, email, created_at FROM users WHERE id = :uid');
        $stmt->execute(['uid' => $userId]);
        $user = $stmt->fetch();
        $loadError = "Some profile fields aren't set up yet. Run the ALTER TABLE statement at the top of this file, then refresh.";
    }

    // ---- Financial summary ----
    try {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM income WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $totalIncome = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM income WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $totalTransactions += (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // income table not ready yet — keep defaults.
    }

    try {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $totalExpense = (float)$stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM expenses WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $totalTransactions += (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // expenses table not ready yet — keep defaults.
    }
} catch (PDOException $e) {
    $loadError = "Couldn't load your profile right now. Please try again shortly.";
}

$balance = $totalIncome - $totalExpense;

// ---- Safe fallbacks for optional/new fields ----
$name        = $user['name'] ?? 'there';
$email       = $user['email'] ?? '';
$phone       = $user['phone'] ?? null;
$photo       = $user['profile_photo'] ?? null;
$memberSince = !empty($user['created_at']) ? date('d F Y', strtotime($user['created_at'])) : '—';
$lastLogin   = !empty($user['last_login']) ? date('d M Y, g:i A', strtotime($user['last_login'])) : 'First login';
$status      = $user['status'] ?? 'active';
$pwChanged   = !empty($user['password_changed_at']) ? date('d M Y', strtotime($user['password_changed_at'])) : 'Never changed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile — Finance Manager</title>
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
      <h1>Your profile</h1>
      <p>Manage your details, security, and see how your money is doing overall.</p>
    </section>

    <?php if ($flash): ?>
      <div class="alert alert--success" role="status"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($loadError): ?>
      <div class="alert alert--error" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Profile header: photo, name, contact details -->
    <section class="dash-panel profile-header">
      <div class="profile-avatar-wrap">
        <?php if ($photo): ?>
          <img src="uploads/<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>" alt="Your profile photo" class="profile-avatar">
        <?php else: ?>
          <div class="profile-avatar profile-avatar--placeholder" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="42" height="42">
              <circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6"/>
            </svg>
          </div>
        <?php endif; ?>
        <a href="upload_photo.php" class="profile-avatar__edit" aria-label="Change profile photo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="15" height="15">
            <path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.3"/>
          </svg>
        </a>
      </div>

      <div class="profile-info">
        <h2 class="profile-info__name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="profile-meta">
          <span class="profile-meta__row"><strong>Email:</strong> <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span>
          <?php if ($phone): ?>
            <span class="profile-meta__row"><strong>Phone:</strong> <?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <span class="profile-meta__row"><strong>Member since:</strong> <?= $memberSince ?></span>
        </div>
        <div class="profile-actions">
          <a href="edit_profile.php" class="btn btn--primary">Edit profile</a>
          <a href="change_password.php" class="btn btn--ghost">Change password</a>
        </div>
      </div>
    </section>

    <!-- Financial summary -->
    <section class="dash-panel">
      <header class="dash-panel__header">
        <h2>Financial summary</h2>
      </header>
      <div class="stat-grid stat-grid--compact">
        <article class="stat-card stat-card--income">
          <div class="stat-card__body">
            <span class="stat-card__label">Total Income</span>
            <span class="stat-card__value"><?= rupees($totalIncome) ?></span>
          </div>
        </article>
        <article class="stat-card stat-card--expense">
          <div class="stat-card__body">
            <span class="stat-card__label">Total Expense</span>
            <span class="stat-card__value"><?= rupees($totalExpense) ?></span>
          </div>
        </article>
        <article class="stat-card stat-card--balance">
          <div class="stat-card__body">
            <span class="stat-card__label">Current Balance</span>
            <span class="stat-card__value stat-card__value--balance <?= $balance < 0 ? 'is-negative' : '' ?>"><?= rupees($balance) ?></span>
          </div>
        </article>
      </div>
    </section>

    <!-- Account information -->
    <section class="dash-panel">
      <header class="dash-panel__header">
        <h2>Account information</h2>
      </header>
      <div class="info-grid">
        <div class="info-item">
          <span class="info-item__label">User ID</span>
          <span class="info-item__value">#<?= (int)($user['id'] ?? $userId) ?></span>
        </div>
        <div class="info-item">
          <span class="info-item__label">Member since</span>
          <span class="info-item__value"><?= $memberSince ?></span>
        </div>
        <div class="info-item">
          <span class="info-item__label">Last login</span>
          <span class="info-item__value"><?= $lastLogin ?></span>
        </div>
        <div class="info-item">
          <span class="info-item__label">Account status</span>
          <span class="status-badge status-badge--<?= $status === 'active' ? 'active' : 'inactive' ?>"><?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="info-item">
          <span class="info-item__label">Total transactions</span>
          <span class="info-item__value"><?= $totalTransactions ?></span>
        </div>
      </div>
    </section>

    <!-- Security -->
    <section class="dash-panel">
      <header class="dash-panel__header">
        <h2>Security</h2>
      </header>
      <div class="info-grid">
        <div class="info-item">
          <span class="info-item__label">Password last changed</span>
          <span class="info-item__value"><?= $pwChanged ?></span>
        </div>
        <div class="info-item">
          <span class="info-item__label">Active session</span>
          <span class="info-item__value">This device, signed in</span>
        </div>
      </div>
      <div class="profile-actions" style="margin-top: 16px;">
        <a href="change_password.php" class="btn btn--ghost">Change password</a>
      </div>
    </section>

    <!-- Danger zone -->
    <section class="dash-panel danger-zone">
      <header class="dash-panel__header">
        <h2>Delete account</h2>
      </header>
      <p class="danger-zone__copy">This permanently deletes your account and all income, expense, and budget data. This cannot be undone.</p>
      <a href="delete_account.php" class="btn btn--danger">Delete my account</a>
    </section>

  </main>

</div>

</body>
</html>