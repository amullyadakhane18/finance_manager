<?php
/**
 * dashboard.php
 * Home page after successful login.
 * Shows: welcome message, Total Income, Total Expense, Current Balance, Logout.
 *
 * Growth stages (per project plan):
 *   Stage 1 — Income/Expense modules don't exist yet -> everything shows ₹0.
 *   Stage 2 — income table exists  -> Total Income is real.
 *   Stage 3 — expenses table exists -> Total Expense + Balance are real.
 *   Stage 4 (optional) -> Recent Transactions list below the summary cards.
 *
 * The queries below are wrapped in try/catch so the dashboard still works
 * even before the `income` / `expenses` tables have been created.
 */

declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helper.php';

$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'there';


$totalIncome  = 0.0;
$totalExpense = 0.0;
$recent       = [];

try {
    $pdo = get_db_connection();

    // ---- Total Income ----
    try {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM income WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $totalIncome = (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // `income` table doesn't exist yet (Stage 1) — keep default 0.
    }

    // ---- Total Expense ----
    try {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE user_id = :uid');
        $stmt->execute(['uid' => $userId]);
        $totalExpense = (float)$stmt->fetchColumn();
    } catch (PDOException $e) {
        // `expenses` table doesn't exist yet (Stage 1/2) — keep default 0.
    }

    // ---- Recent transactions (optional Stage 4) ----
    try {
        $stmt = $pdo->prepare(
            "(SELECT 'income' AS type, id, source AS label, amount, created_at FROM income WHERE user_id = :uid1)
             UNION ALL
             (SELECT 'expense' AS type, id, category AS label, amount, created_at FROM expenses WHERE user_id = :uid2)
             ORDER BY created_at DESC
             LIMIT 8"
        );
        $stmt->execute(['uid1' => $userId, 'uid2' => $userId]);
        $recent = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Income/expense tables not ready yet — recent list stays empty.
    }

} catch (PDOException $e) {
    // Database connection itself failed — summary values stay at 0.
}

$balance = $totalIncome - $totalExpense;

// A lightweight, local-only money health indicator. It rewards keeping
// expenses below income and remains useful even before budgets are set up.
$healthScore = null;
$healthLabel = 'Ready when you are';
$healthMessage = 'Add an income or expense to unlock your personalised money snapshot.';
$healthTone = 'starting';

if ($totalIncome > 0 || $totalExpense > 0) {
    $healthScore = $totalIncome > 0
        ? (int) max(0, min(100, round(100 - (($totalExpense / $totalIncome) * 70))))
        : 0;

    if ($healthScore >= 80) {
        $healthLabel = 'Excellent shape';
        $healthMessage = 'You are keeping a strong share of your income. Keep building that cushion.';
        $healthTone = 'excellent';
    } elseif ($healthScore >= 60) {
        $healthLabel = 'Looking healthy';
        $healthMessage = 'Your spending is in a comfortable range. A small saving habit can lift this further.';
        $healthTone = 'good';
    } elseif ($healthScore >= 40) {
        $healthLabel = 'Worth watching';
        $healthMessage = 'Spending is taking up a large part of your income. Review your recent expenses.';
        $healthTone = 'watch';
    } else {
        $healthLabel = 'Needs attention';
        $healthMessage = 'Your expenses are close to or above your income. Start with one expense to reduce.';
        $healthTone = 'attention';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="assets/images/favicon.png">
<title>Dashboard — Finance Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dash-body">

<div class="dash-shell">

  <!-- Top bar -->
  <?php $basePath = ''; $activeNav = 'dashboard'; include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="dash-main">

    <!-- Welcome -->
    <section class="dash-welcome">
      <h1>Welcome, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?> 👋</h1>
      <p>Here's where your money stands right now.</p>
    </section>

    <!-- Summary cards -->
    <section class="stat-grid">

      <article class="stat-card stat-card--income">
        <div class="stat-card__icon">
          <svg viewBox="0 0 24 24"><path d="M12 19V5"/><path d="M5 12l7-7 7 7"/></svg>
        </div>
        <div class="stat-card__body">
          <span class="stat-card__label">Total Income</span>
          <span class="stat-card__value"><?= rupees($totalIncome) ?></span>
        </div>
        <a href="income/add_income.php" class="stat-card__action" aria-label="Add income">+</a>
      </article>

      <article class="stat-card stat-card--expense">
        <div class="stat-card__icon">
          <svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M19 12l-7 7-7-7"/></svg>
        </div>
        <div class="stat-card__body">
          <span class="stat-card__label">Total Expense</span>
          <span class="stat-card__value"><?= rupees($totalExpense) ?></span>
        </div>
        <a href="expense/add_expense.php" class="stat-card__action stat-card__action--expense" aria-label="Add expense">+</a>
      </article>

      <article class="stat-card stat-card--balance">
        <div class="stat-card__icon">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 12h6"/><path d="M12 9v6"/></svg>
        </div>
        <div class="stat-card__body">
          <span class="stat-card__label">Current Balance</span>
          <span class="stat-card__value stat-card__value--balance <?= $balance < 0 ? 'is-negative' : '' ?>">
            <?= rupees($balance) ?>
          </span>
        </div>
      </article>

    </section>

    <!-- Financial health snapshot -->
    <section class="health-snapshot health-snapshot--<?= $healthTone ?>" aria-labelledby="health-title">
      <div class="health-snapshot__score"<?= $healthScore !== null ? ' style="--health-score: ' . $healthScore . '"' : '' ?> aria-label="<?= $healthScore !== null ? 'Financial health score: ' . $healthScore . ' out of 100' : 'Financial health score unavailable until you add a transaction' ?>">
        <div class="health-snapshot__score-inner">
          <strong><?= $healthScore !== null ? $healthScore : '—' ?></strong>
          <span><?= $healthScore !== null ? '/ 100' : 'start' ?></span>
        </div>
      </div>
      <div class="health-snapshot__content">
        <span class="health-snapshot__eyebrow">Financial health snapshot</span>
        <h2 id="health-title"><?= $healthLabel ?></h2>
        <p><?= $healthMessage ?></p>
      </div>
      <a class="health-snapshot__link" href="<?= $healthScore !== null && $healthScore < 60 ? 'expense/view_expense.php' : 'income/add_income.php' ?>">
        <?= $healthScore !== null && $healthScore < 60 ? 'Review spending' : 'Add income' ?>
        <span aria-hidden="true">→</span>
      </a>
    </section>

    <!-- Recent transactions (Stage 4, optional) -->
    <section class="dash-panel">
      <header class="dash-panel__header">
        <h2>Recent transactions</h2>
        <span class="panel-header__links">
          <a href="income/view_income.php" class="field-link">View all income</a>
          <a href="expense/view_expense.php" class="field-link">View all expenses</a>
        </span>
      </header>

      <?php if (empty($recent)): ?>
        <div class="empty-state">
          <p>No transactions yet. Once the Income and Expense modules are added, your latest entries will show up here.</p>
        </div>
      <?php else: ?>
        <ul class="tx-list">
          <?php foreach ($recent as $row): ?>
            <?php $editHref = $row['type'] === 'income'
                ? 'income/edit_income.php?id=' . (int)$row['id']
                : 'expense/edit_expense.php?id=' . (int)$row['id']; ?>
            <li class="tx-row">
              <span class="tx-row__dot tx-row__dot--<?= $row['type'] === 'income' ? 'income' : 'expense' ?>"></span>
              <span class="tx-row__label"><?= htmlspecialchars((string)$row['label'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="tx-row__date"><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="tx-row__amount tx-row__amount--<?= $row['type'] === 'income' ? 'income' : 'expense' ?>">
                <?= $row['type'] === 'income' ? '+' : '-' ?><?= rupees((float)$row['amount']) ?>
              </span>
              <a href="<?= htmlspecialchars($editHref, ENT_QUOTES, 'UTF-8') ?>" class="tx-row__edit" aria-label="Edit">
                <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

  </main>

</div>

</body>
</html>
