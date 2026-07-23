<?php
/**
 * budget.php
 * Budget Tracking — lets a user set a monthly spending limit per expense
 * category, then shows how much of that budget has been used this month.
 */

declare(strict_types=1);
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/helper.php';
require_once __DIR__ . '/includes/categories.php';

$userId = (int)$_SESSION['user_id'];
$errors = [];
$flash  = null;
$loadError = null;

if (isset($_GET['updated'])) {
    $flash = 'Budgets updated.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    } else {
        try {
            $pdo = get_db_connection();
            $pdo->beginTransaction();

            foreach (EXPENSE_CATEGORIES as $cat) {
                $raw = trim((string)($_POST['budget'][$cat] ?? ''));

                if ($raw === '' || !is_numeric($raw) || (float)$raw <= 0) {
                    // Blank or 0/invalid input means "no budget" — remove any existing row.
                    $del = $pdo->prepare('DELETE FROM budgets WHERE user_id = :uid AND category = :cat');
                    $del->execute(['uid' => $userId, 'cat' => $cat]);
                    continue;
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO budgets (user_id, category, monthly_limit) VALUES (:uid, :cat, :amount)
                     ON DUPLICATE KEY UPDATE monthly_limit = VALUES(monthly_limit)'
                );
                $stmt->execute(['uid' => $userId, 'cat' => $cat, 'amount' => (float)$raw]);
            }

            $pdo->commit();
            header('Location: budget.php?updated=1');
            exit;
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Couldn't save budgets. Make sure the budgets table exists (see README), then try again.";
        }
    }
}

// ---- Load existing budgets + this month's spend per category ----
$budgets     = [];      // category => monthly_limit
$spent       = [];      // category => amount spent this month
$currentMonth = date('Y-m');
$currentMonthLabel = date('F Y');

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare('SELECT category, monthly_limit FROM budgets WHERE user_id = :uid');
    $stmt->execute(['uid' => $userId]);
    foreach ($stmt->fetchAll() as $row) {
        $budgets[$row['category']] = (float)$row['monthly_limit'];
    }

    $stmt = $pdo->prepare(
        "SELECT category, COALESCE(SUM(amount), 0) AS total FROM expenses
         WHERE user_id = :uid AND DATE_FORMAT(created_at, '%Y-%m') = :month
         GROUP BY category"
    );
    $stmt->execute(['uid' => $userId, 'month' => $currentMonth]);
    foreach ($stmt->fetchAll() as $row) {
        $spent[$row['category']] = (float)$row['total'];
    }
} catch (PDOException $e) {
    $loadError = "The budgets table hasn't been set up yet. Run the CREATE TABLE budgets statement from the README, then refresh this page.";
}

// ---- Derived data for the analysis dashboard (only if load succeeded) ----
$activeBudgets   = array_filter($budgets, static fn($limit) => $limit > 0);
$totalBudget     = array_sum($activeBudgets);
$totalSpent      = 0.0;
foreach ($activeBudgets as $cat => $limit) {
    $totalSpent += $spent[$cat] ?? 0.0;
}
$remainingBudget   = $totalBudget - $totalSpent;
$overBudgetCats    = [];
$budgetAlerts      = [];

foreach ($activeBudgets as $cat => $limit) {
    $used = $spent[$cat] ?? 0.0;
    $pct  = $limit > 0 ? ($used / $limit) * 100 : 0;

    if ($pct >= 100) {
        $overBudgetCats[] = $cat;
        $budgetAlerts[] = ['type' => 'over', 'category' => $cat, 'pct' => $pct];
    } elseif ($pct >= 80) {
        $budgetAlerts[] = ['type' => 'warn', 'category' => $cat, 'pct' => $pct];
    }
}
// If nothing needs attention, add one reassuring "healthy" alert.
if (empty($budgetAlerts) && !empty($activeBudgets)) {
    $budgetAlerts[] = ['type' => 'ok', 'category' => null, 'pct' => null];
}

/**
 * 4-tier bar/label state used for the progress bars.
 *   0-60%   -> ok     (green)
 *   61-80%  -> warn   (yellow)
 *   81-99%  -> high   (orange)
 *   100%+   -> over   (red)
 */
function budget_tier(float $pct): string
{
    if ($pct >= 100) return 'over';
    if ($pct > 80)   return 'high';
    if ($pct > 60)   return 'warn';
    return 'ok';
}

// ---- Budget recommendations: avg spend per category over the last 3 full months ----
$recommendations = [];
if (!$loadError) {
    try {
        $windowStart = date('Y-m-01', strtotime('-3 months'));
        $windowEnd   = date('Y-m-01'); // exclusive — start of current month

        $stmt = $pdo->prepare(
            "SELECT category, SUM(amount) AS total FROM expenses
             WHERE user_id = :uid AND created_at >= :start AND created_at < :end
             GROUP BY category"
        );
        $stmt->execute(['uid' => $userId, 'start' => $windowStart, 'end' => $windowEnd]);

        foreach ($stmt->fetchAll() as $row) {
            $avg = (float)$row['total'] / 3;
            if ($avg <= 0) continue;
            $suggested = (float)(ceil($avg / 100) * 100); // round up to nearest ₹100
            $recommendations[$row['category']] = [
                'average'   => $avg,
                'suggested' => $suggested,
            ];
        }
    } catch (PDOException $e) {
        // Silently skip recommendations if the query fails — non-critical feature.
    }
}

// ---- Monthly spending trend: last 6 months, all categories combined ----
$trendLabels = [];
$trendTotals = [];
if (!$loadError) {
    try {
        $trendStart = date('Y-m-01', strtotime('-5 months'));

        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(amount) AS total FROM expenses
             WHERE user_id = :uid AND created_at >= :start
             GROUP BY ym ORDER BY ym ASC"
        );
        $stmt->execute(['uid' => $userId, 'start' => $trendStart]);
        $byMonth = [];
        foreach ($stmt->fetchAll() as $row) {
            $byMonth[$row['ym']] = (float)$row['total'];
        }

        for ($i = 5; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-$i months"));
            $trendLabels[] = date('M', strtotime($ym . '-01'));
            $trendTotals[] = $byMonth[$ym] ?? 0.0;
        }
    } catch (PDOException $e) {
        // Silently skip the trend chart if the query fails — non-critical feature.
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Budget — Finance Manager</title>
<link rel="icon" href="assets/images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dash-body">

<div class="dash-shell">

  <?php $basePath = ''; $activeNav = 'budget'; include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="dash-main">

    <section class="dash-welcome">
      <h1>Budget</h1>
      <p>Set a monthly limit per category and track how much you've spent in <?= htmlspecialchars($currentMonthLabel, ENT_QUOTES, 'UTF-8') ?>.</p>
    </section>

    <?php if ($flash): ?>
      <div class="alert alert--success" role="status"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert--error" role="alert">
        <ul>
          <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($loadError): ?>
      <div class="alert alert--error" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!$loadError): ?>

    <!-- Summary cards -->
    <section class="summary-grid" aria-label="Budget summary">
      <div class="summary-card">
        <span class="summary-card__label">Total Budget</span>
        <span class="summary-card__value"><?= rupees($totalBudget) ?></span>
      </div>
      <div class="summary-card">
        <span class="summary-card__label">Total Spent</span>
        <span class="summary-card__value"><?= rupees($totalSpent) ?></span>
      </div>
      <div class="summary-card <?= $remainingBudget < 0 ? 'summary-card--danger' : '' ?>">
        <span class="summary-card__label">Remaining Budget</span>
        <span class="summary-card__value"><?= rupees(abs($remainingBudget)) ?><?= $remainingBudget < 0 ? ' over' : '' ?></span>
      </div>
      <div class="summary-card <?= count($overBudgetCats) > 0 ? 'summary-card--danger' : '' ?>">
        <span class="summary-card__label">Over-Budget Categories</span>
        <span class="summary-card__value"><?= count($overBudgetCats) ?></span>
      </div>
    </section>

    <!-- Budget alerts -->
    <?php if (!empty($budgetAlerts)): ?>
      <section class="alert-stack" aria-label="Budget alerts">
        <?php foreach ($budgetAlerts as $a): ?>
          <?php if ($a['type'] === 'over'): ?>
            <div class="budget-alert budget-alert--over">
              <span class="budget-alert__icon">🔴</span>
              <span><strong><?= htmlspecialchars($a['category'], ENT_QUOTES, 'UTF-8') ?></strong> is <?= number_format($a['pct'], 0) ?>% used — you've gone over budget.</span>
            </div>
          <?php elseif ($a['type'] === 'warn'): ?>
            <div class="budget-alert budget-alert--warn">
              <span class="budget-alert__icon">🟡</span>
              <span><strong><?= htmlspecialchars($a['category'], ENT_QUOTES, 'UTF-8') ?></strong> has reached <?= number_format($a['pct'], 0) ?>% of its budget — keep an eye on it.</span>
            </div>
          <?php else: ?>
            <div class="budget-alert budget-alert--ok">
              <span class="budget-alert__icon">🟢</span>
              <span>All budgets are healthy this month. Nice work!</span>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php endif; // !$loadError ?>

    <!-- Progress overview -->
    <section class="dash-panel">
      <header class="dash-panel__header">
        <h2>This month's utilization</h2>
      </header>

      <?php if (empty($activeBudgets)): ?>
        <div class="empty-state">
          <p>No budgets set yet. Enter a monthly limit for any category below to start tracking it.</p>
        </div>
      <?php else: ?>
        <div class="budget-list">
          <?php foreach ($activeBudgets as $cat => $limit): ?>
            <?php
              $used = $spent[$cat] ?? 0.0;
              $remaining = $limit - $used;
              $pct = $limit > 0 ? ($used / $limit) * 100 : 0;
              $barPct = min(100, max(0, $pct));
              $state = budget_tier($pct);
            ?>
            <div class="budget-item">
              <div class="budget-item__top">
                <span class="budget-item__category"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="budget-item__figures">
                  <?= rupees($used) ?> of <?= rupees($limit) ?>
                  <span class="budget-item__pct budget-item__pct--<?= $state ?>"><?= number_format($pct, 0) ?>%</span>
                </span>
              </div>
              <div class="budget-bar">
                <div class="budget-bar__fill budget-bar__fill--<?= $state ?>" style="width: <?= $barPct ?>%;"></div>
              </div>
              <div class="budget-item__bottom">
                <?php if ($remaining >= 0): ?>
                  <span class="text-muted"><?= rupees($remaining) ?> remaining</span>
                <?php else: ?>
                  <span class="budget-item__over"><?= rupees(abs($remaining)) ?> over budget</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if (!$loadError): ?>
    <div class="dash-grid-2">

      <!-- Budget utilization doughnut -->
      <section class="dash-panel">
        <header class="dash-panel__header">
          <h2>Utilization at a glance</h2>
        </header>
        <?php if ($totalBudget <= 0): ?>
          <div class="empty-state">
            <p>Set at least one budget above to see this chart.</p>
          </div>
        <?php else: ?>
          <div class="chart-box chart-box--doughnut">
            <canvas id="utilizationDoughnut" aria-label="Budget used vs remaining" role="img"></canvas>
          </div>
          <div class="chart-legend">
            <span class="chart-legend__item"><i class="chart-legend__dot" style="background: var(--mint)"></i>Used — <?= rupees(min($totalSpent, $totalBudget)) ?></span>
            <span class="chart-legend__item"><i class="chart-legend__dot" style="background: var(--paper); border: 1px solid var(--border)"></i>Remaining — <?= rupees(max($totalBudget - $totalSpent, 0)) ?></span>
            <?php if ($totalSpent > $totalBudget): ?>
              <span class="chart-legend__item"><i class="chart-legend__dot" style="background: var(--danger)"></i>Over — <?= rupees($totalSpent - $totalBudget) ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- Budget recommendations -->
      <section class="dash-panel">
        <header class="dash-panel__header">
          <h2>Recommended budgets</h2>
        </header>
        <?php if (empty($recommendations)): ?>
          <div class="empty-state">
            <p>Not enough spending history yet — recommendations appear once you have a few months of expenses.</p>
          </div>
        <?php else: ?>
          <div class="recommend-list">
            <?php foreach ($recommendations as $cat => $rec): ?>
              <div class="recommend-item">
                <div class="recommend-item__info">
                  <span class="recommend-item__category"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="recommend-item__figures">Average spending: <?= rupees($rec['average']) ?></span>
                </div>
                <div class="recommend-item__action">
                  <span class="recommend-item__suggested"><?= rupees($rec['suggested']) ?></span>
                  <button type="button" class="btn-suggest" data-category="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" data-amount="<?= $rec['suggested'] ?>">Use</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

    </div>

    <!-- Monthly spending trend -->
    <section class="dash-panel">
      <header class="dash-panel__header">
        <h2>Monthly spending trend</h2>
      </header>
      <?php if (array_sum($trendTotals) <= 0): ?>
        <div class="empty-state">
          <p>No expense history in the last 6 months yet.</p>
        </div>
      <?php else: ?>
        <div class="chart-box chart-box--trend">
          <canvas id="trendChart" aria-label="Monthly spending trend, last 6 months" role="img"></canvas>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; // !$loadError ?>

    <!-- Set budgets -->
    <section class="dash-panel">
      <header class="dash-panel__header">
        <h2>Set monthly budgets</h2>
      </header>

      <form method="POST" action="budget.php" class="budget-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="budget-form__grid">
          <?php foreach (EXPENSE_CATEGORIES as $cat): ?>
            <div class="field">
              <label for="budget-<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></label>
              <input
                type="number"
                id="budget-<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"
                name="budget[<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>]"
                step="0.01"
                min="0"
                placeholder="No limit"
                value="<?= isset($budgets[$cat]) && $budgets[$cat] > 0 ? htmlspecialchars((string)$budgets[$cat], ENT_QUOTES, 'UTF-8') : '' ?>"
              >
            </div>
          <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn--primary">Save budgets</button>
        <p class="field-hint">Leave a field blank or 0 to remove the budget for that category.</p>
      </form>
    </section>

  </main>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // ---- "Use" button on recommendations: fills the matching budget input ----
    document.querySelectorAll('.btn-suggest').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var cat = btn.dataset.category;
            var amount = btn.dataset.amount;
            var input = document.getElementById('budget-' + cat);
            if (input) {
                input.value = amount;
                input.focus();
            }
        });
    });

    if (typeof Chart === 'undefined') return;

    var rootStyles = getComputedStyle(document.documentElement);
    var colorMint    = rootStyles.getPropertyValue('--mint').trim()    || '#4C8C6B';
    var colorPaper   = rootStyles.getPropertyValue('--paper').trim()   || '#EEF0E9';
    var colorDanger  = rootStyles.getPropertyValue('--danger').trim()  || '#B3462C';
    var colorBorder  = rootStyles.getPropertyValue('--border').trim()  || '#DADFD7';
    var colorGold    = rootStyles.getPropertyValue('--gold').trim()    || '#C9A23D';
    var colorText    = rootStyles.getPropertyValue('--text').trim()    || '#16241F';

    // ---- Doughnut: used vs remaining (vs over) ----
    var doughnutEl = document.getElementById('utilizationDoughnut');
    if (doughnutEl) {
        var totalBudget = <?= json_encode($totalBudget) ?>;
        var totalSpent  = <?= json_encode($totalSpent) ?>;
        var used        = Math.min(totalSpent, totalBudget);
        var remaining   = Math.max(totalBudget - totalSpent, 0);
        var over        = Math.max(totalSpent - totalBudget, 0);

        var data   = [used, remaining];
        var labels = ['Used', 'Remaining'];
        var colors = [colorMint, colorPaper];
        var borderColors = [colorMint, colorBorder];

        if (over > 0) {
            data.push(over);
            labels.push('Over budget');
            colors.push(colorDanger);
            borderColors.push(colorDanger);
        }

        new Chart(doughnutEl, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: colors, borderColor: borderColors, borderWidth: 1 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { display: false } }
            }
        });
    }

    // ---- Monthly spending trend ----
    var trendEl = document.getElementById('trendChart');
    if (trendEl) {
        var trendLabels = <?= json_encode($trendLabels) ?>;
        var trendTotals = <?= json_encode($trendTotals) ?>;

        new Chart(trendEl, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [{
                    label: 'Spending',
                    data: trendTotals,
                    backgroundColor: colorGold,
                    borderRadius: 6,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: colorBorder },
                        ticks: { color: colorText, callback: function (v) { return '₹' + v; } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: colorText }
                    }
                }
            }
        });
    }
});
</script>

</body>
</html>