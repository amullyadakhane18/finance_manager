<?php
/**
 * expense/view_expense.php
 * View Expense — lists every expense belonging to the logged-in user,
 * with links to Add / Edit / Delete. This is the "Expense Management" home.
 */

declare(strict_types=1);
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helper.php';

$userId = (int)$_SESSION['user_id'];
$rows   = [];
$total  = 0.0;
$loadError = null;

try {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare(
        'SELECT id, category, amount, created_at FROM expenses WHERE user_id = :uid ORDER BY created_at DESC'
    );
    $stmt->execute(['uid' => $userId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $total += (float)$row['amount'];
    }
} catch (PDOException $e) {
    // Table probably doesn't exist yet — show a friendly message instead of a fatal error.
    $loadError = "The expenses table hasn't been set up yet. Run the CREATE TABLE expenses statement from the README, then refresh this page.";
}

// Flash messages passed via redirect query string
$flash = null;
if (isset($_GET['added']))   { $flash = 'Expense added.'; }
if (isset($_GET['updated'])) { $flash = 'Expense updated.'; }
if (isset($_GET['deleted'])) { $flash = 'Expense deleted.'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="../assets/images/favicon.png">
<title>Expense — Finance Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dash-body">

<div class="dash-shell">

  <header class="dash-topbar">
    <a href="../dashboard.php" class="brand-mark brand-mark--dark">
      <span class="brand-mark__glyph">₹</span>
      <span class="brand-mark__word">Finance Manager</span>
    </a>

    <nav class="dash-nav">
      <a href="../dashboard.php" class="dash-nav__link">Dashboard</a>
      <a href="../income/view_income.php" class="dash-nav__link">Income</a>
      <a href="view_expense.php" class="dash-nav__link is-active">Expense</a>
      <a href="../reports.php" class="dash-nav__link ">Reports</a>
      <a href="../budget.php" class="dash-nav__link ">Budget</a>
    </nav>

    <form method="POST" action="../logout.php" class="dash-topbar__logout">
      <button type="submit" class="btn btn--ghost">
        <svg viewBox="0 0 24 24" class="icon-inline"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
        Log out
      </button>
    </form>
  </header>

  <main class="dash-main">

    <section class="dash-welcome dash-welcome--row">
      <div>
        <h1>Expenses</h1>
        <p>Every expense you've logged, newest first.</p>
      </div>
      <a href="add_expense.php" class="btn btn--primary">
        <svg viewBox="0 0 24 24" class="icon-inline"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        Add expense
      </a>
    </section>

    <?php if ($flash): ?>
      <div class="alert alert--success" role="status"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($loadError): ?>
      <div class="alert alert--error" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="dash-panel">
      <header class="dash-panel__header">
        <h2>All entries</h2>
        <span class="panel-total">Total: <strong><?= rupees($total) ?></strong></span>
      </header>

      <?php if (empty($rows)): ?>
        <div class="empty-state">
          <p>No expenses logged yet. Click <strong>Add expense</strong> above to record your first expense.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Date</th>
                <th class="align-right">Amount</th>
                <th class="align-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$row['category'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-muted"><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="align-right amount-expense"><?= rupees((float)$row['amount']) ?></td>
                  <td class="align-right">
                    <div class="actions-cell">
                      <a href="edit_expense.php?id=<?= (int)$row['id'] ?>" class="btn-icon" aria-label="Edit">
                        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                      </a>
                      <form method="POST" action="delete_expense.php" onsubmit="return confirm('Delete this expense? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                        <button type="submit" class="btn-icon btn-icon--danger" aria-label="Delete">
                          <svg viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

  </main>

</div>

</body>
</html>