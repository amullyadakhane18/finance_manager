<?php
/**
 * reports.php
 * Monthly Financial Reports + Charts and Analytics.
 *
 * - Total Income / Total Expense / Savings for the selected month
 * - Expense by Category (Pie)
 * - Monthly Income vs Expense Comparison, last 6 months (Bar)
 * - Spending Trend Over Time within the selected month (Line)
 */

declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helper.php';

$userId = (int)$_SESSION['user_id'];

$selectedMonth = trim((string)($_GET['month'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$monthLabel = date('F Y', strtotime($selectedMonth . '-01'));
$prevMonth  = date('Y-m', strtotime($selectedMonth . '-01 -1 month'));
$nextMonth  = date('Y-m', strtotime($selectedMonth . '-01 +1 month'));
$isCurrentMonth = $selectedMonth >= date('Y-m');

$monthIncome  = 0.0;
$monthExpense = 0.0;
$categoryTotals = [];   // category => total (this month)
$trendLabels = [];      // day numbers for the line chart
$trendData   = [];      // daily expense totals
$compareLabels = [];    // last 6 months, short labels
$compareIncome = [];
$compareExpense = [];
$loadError = null;

try {
    $pdo = get_db_connection();

    // ---- This month's totals ----
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM income WHERE user_id = :uid AND DATE_FORMAT(created_at, '%Y-%m') = :month");
    $stmt->execute(['uid' => $userId, 'month' => $selectedMonth]);
    $monthIncome = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE user_id = :uid AND DATE_FORMAT(created_at, '%Y-%m') = :month");
    $stmt->execute(['uid' => $userId, 'month' => $selectedMonth]);
    $monthExpense = (float)$stmt->fetchColumn();

    // ---- Expense by category (this month) ----
    $stmt = $pdo->prepare(
        "SELECT category, SUM(amount) AS total FROM expenses
         WHERE user_id = :uid AND DATE_FORMAT(created_at, '%Y-%m') = :month
         GROUP BY category ORDER BY total DESC"
    );
    $stmt->execute(['uid' => $userId, 'month' => $selectedMonth]);
    foreach ($stmt->fetchAll() as $row) {
        $categoryTotals[$row['category']] = (float)$row['total'];
    }

    // ---- Spending trend: daily expense totals within the selected month ----
    $daysInMonth = (int)date('t', strtotime($selectedMonth . '-01'));
    $dailyTotals = array_fill(1, $daysInMonth, 0.0);

    $stmt = $pdo->prepare(
        "SELECT DAY(created_at) AS d, SUM(amount) AS total FROM expenses
         WHERE user_id = :uid AND DATE_FORMAT(created_at, '%Y-%m') = :month
         GROUP BY d"
    );
    $stmt->execute(['uid' => $userId, 'month' => $selectedMonth]);
    foreach ($stmt->fetchAll() as $row) {
        $dailyTotals[(int)$row['d']] = (float)$row['total'];
    }
    $trendLabels = array_map(static fn($d) => (string)$d, array_keys($dailyTotals));
    $trendData   = array_values($dailyTotals);

    // ---- Last 6 months income vs expense comparison ----
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[] = date('Y-m', strtotime($selectedMonth . '-01 -' . $i . ' months'));
    }
    $placeholders = implode(',', array_fill(0, count($months), '?'));

    $incomeByMonth = array_fill_keys($months, 0.0);
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(amount) AS total FROM income
         WHERE user_id = ? AND DATE_FORMAT(created_at, '%Y-%m') IN ($placeholders) GROUP BY ym"
    );
    $stmt->execute(array_merge([$userId], $months));
    foreach ($stmt->fetchAll() as $row) {
        $incomeByMonth[$row['ym']] = (float)$row['total'];
    }

    $expenseByMonth = array_fill_keys($months, 0.0);
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(amount) AS total FROM expenses
         WHERE user_id = ? AND DATE_FORMAT(created_at, '%Y-%m') IN ($placeholders) GROUP BY ym"
    );
    $stmt->execute(array_merge([$userId], $months));
    foreach ($stmt->fetchAll() as $row) {
        $expenseByMonth[$row['ym']] = (float)$row['total'];
    }

    foreach ($months as $m) {
        $compareLabels[]  = date('M', strtotime($m . '-01'));
        $compareIncome[]  = $incomeByMonth[$m];
        $compareExpense[] = $expenseByMonth[$m];
    }
} catch (PDOException $e) {
    $loadError = "Couldn't load report data. Make sure the income/expenses tables exist (see README), then refresh this page.";
}

$savings = $monthIncome - $monthExpense;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="assets/images/favicon.png">
<title>Reports — Finance Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dash-body">

<div class="dash-shell">

  <?php $basePath = ''; $activeNav = 'reports'; include __DIR__ . '/includes/topbar.php'; ?>

  <main class="dash-main">

    <section class="dash-welcome dash-welcome--row">
      <div>
        <h1>Reports</h1>
        <p>Monthly summary and visual insights into your finances.</p>
      </div>
      <form method="GET" action="reports.php" class="month-nav">
        <a href="reports.php?month=<?= htmlspecialchars($prevMonth, ENT_QUOTES, 'UTF-8') ?>" class="btn-icon" aria-label="Previous month">
          <svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
        <?php if (!$isCurrentMonth): ?>
          <a href="reports.php?month=<?= htmlspecialchars($nextMonth, ENT_QUOTES, 'UTF-8') ?>" class="btn-icon" aria-label="Next month">
            <svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
          </a>
        <?php endif; ?>
      </form>
    </section>

    <?php if ($loadError): ?>
      <div class="alert alert--error" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Monthly summary cards -->
    <section class="stat-grid">
      <article class="stat-card stat-card--income">
        <div class="stat-card__icon">
          <svg viewBox="0 0 24 24"><path d="M12 19V5"/><path d="M5 12l7-7 7 7"/></svg>
        </div>
        <div class="stat-card__body">
          <span class="stat-card__label">Total Income — <?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="stat-card__value"><?= rupees($monthIncome) ?></span>
        </div>
      </article>

      <article class="stat-card stat-card--expense">
        <div class="stat-card__icon">
          <svg viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M19 12l-7 7-7-7"/></svg>
        </div>
        <div class="stat-card__body">
          <span class="stat-card__label">Total Expenses — <?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="stat-card__value"><?= rupees($monthExpense) ?></span>
        </div>
      </article>

      <article class="stat-card stat-card--balance">
        <div class="stat-card__icon">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9 12h6"/><path d="M12 9v6"/></svg>
        </div>
        <div class="stat-card__body">
          <span class="stat-card__label">Savings / Remaining Balance</span>
          <span class="stat-card__value stat-card__value--balance <?= $savings < 0 ? 'is-negative' : '' ?>"><?= rupees($savings) ?></span>
        </div>
      </article>
    </section>

    <!-- Charts -->
    <section class="reports-grid">

      <div class="dash-panel chart-panel">
        <header class="dash-panel__header"><h2>Expense by Category</h2></header>
        <?php if (empty($categoryTotals)): ?>
          <div class="empty-state"><p>No expenses logged in <?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?> yet.</p></div>
        <?php else: ?>
          <div class="chart-wrap"><canvas id="categoryPieChart"></canvas></div>
        <?php endif; ?>
      </div>

      <div class="dash-panel chart-panel">
        <header class="dash-panel__header"><h2>Income vs Expense (last 6 months)</h2></header>
        <div class="chart-wrap"><canvas id="compareBarChart"></canvas></div>
      </div>

      <div class="dash-panel chart-panel chart-panel--wide">
        <header class="dash-panel__header"><h2>Spending Trend — <?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?></h2></header>
        <?php if (empty(array_filter($trendData))): ?>
          <div class="empty-state"><p>No daily spending to chart yet for this month.</p></div>
        <?php else: ?>
          <div class="chart-wrap"><canvas id="trendLineChart"></canvas></div>
        <?php endif; ?>
      </div>

    </section>

    <!-- Category breakdown table -->
    <?php if (!empty($categoryTotals)): ?>
    <section class="dash-panel">
      <header class="dash-panel__header"><h2>Category breakdown — <?= htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8') ?></h2></header>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Category</th><th class="align-right">Amount</th><th class="align-right">% of expenses</th></tr>
          </thead>
          <tbody>
            <?php foreach ($categoryTotals as $cat => $amt): ?>
              <tr>
                <td><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></td>
                <td class="align-right amount-expense"><?= rupees($amt) ?></td>
                <td class="align-right text-muted"><?= $monthExpense > 0 ? number_format($amt / $monthExpense * 100, 1) : '0.0' ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

  </main>

</div>

<script>
const brandColors = ['#C9A23D', '#4C8C6B', '#B3462C', '#0E2A24', '#E4C878', '#7BA98F', '#8A6A1E', '#5B6B63'];

<?php if (!empty($categoryTotals)): ?>
new Chart(document.getElementById('categoryPieChart'), {
  type: 'pie',
  data: {
    labels: <?= json_encode(array_keys($categoryTotals)) ?>,
    datasets: [{
      data: <?= json_encode(array_values($categoryTotals)) ?>,
      backgroundColor: brandColors,
      borderColor: '#ffffff',
      borderWidth: 2
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { font: { family: 'Inter' } } } }
  }
});
<?php endif; ?>

new Chart(document.getElementById('compareBarChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($compareLabels) ?>,
    datasets: [
      { label: 'Income', data: <?= json_encode($compareIncome) ?>, backgroundColor: '#4C8C6B', borderRadius: 4 },
      { label: 'Expense', data: <?= json_encode($compareExpense) ?>, backgroundColor: '#B3462C', borderRadius: 4 }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { font: { family: 'Inter' } } } },
    scales: { y: { beginAtZero: true } }
  }
});

<?php if (!empty(array_filter($trendData))): ?>
new Chart(document.getElementById('trendLineChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($trendLabels) ?>,
    datasets: [{
      label: 'Daily spending',
      data: <?= json_encode($trendData) ?>,
      borderColor: '#C9A23D',
      backgroundColor: 'rgba(201, 162, 61, 0.15)',
      tension: 0.3,
      fill: true,
      pointRadius: 2
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true }, x: { title: { display: true, text: 'Day of month' } } }
  }
});
<?php endif; ?>
</script>

</body>
</html>