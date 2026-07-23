<?php
/**
 * income/view_income.php
 * View Income — lists every income entry belonging to the logged-in user,
 * with links to Add / Edit / Delete. This is the "Income Management" home.
 */

declare(strict_types=1);
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helper.php';
require_once __DIR__ . '/../includes/categories.php';

$userId = (int)$_SESSION['user_id'];
$rows   = [];
$total  = 0.0;
$loadError = null;

// ---- Search / filter inputs (all optional, combine with AND) ----
$filters = [
    'q'         => trim((string)($_GET['q'] ?? '')),
    'category'  => trim((string)($_GET['category'] ?? '')),
    'month'     => trim((string)($_GET['month'] ?? '')),
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to'   => trim((string)($_GET['date_to'] ?? '')),
];
$hasFilters = array_filter($filters) !== [];

try {
    $pdo = get_db_connection();

    $where  = ['user_id = :uid'];
    $params = ['uid' => $userId];

    if ($filters['q'] !== '') {
        $where[] = 'source LIKE :q';
        $params['q'] = '%' . $filters['q'] . '%';
    }
    if ($filters['category'] !== '' && in_array($filters['category'], INCOME_CATEGORIES, true)) {
        $where[] = 'source = :category';
        $params['category'] = $filters['category'];
    }
    if ($filters['month'] !== '' && preg_match('/^\d{4}-\d{2}$/', $filters['month'])) {
        $where[] = "DATE_FORMAT(created_at, '%Y-%m') = :month";
        $params['month'] = $filters['month'];
    }
    if ($filters['date_from'] !== '') {
        $where[] = 'created_at >= :date_from';
        $params['date_from'] = $filters['date_from'] . ' 00:00:00';
    }
    if ($filters['date_to'] !== '') {
        $where[] = 'created_at <= :date_to';
        $params['date_to'] = $filters['date_to'] . ' 23:59:59';
    }

    $sql = 'SELECT id, source, amount, created_at FROM income WHERE '
        . implode(' AND ', $where) . ' ORDER BY created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $total += (float)$row['amount'];
    }
} catch (PDOException $e) {
    // Table probably doesn't exist yet — show a friendly message instead of a fatal error.
    $loadError = "The income table hasn't been set up yet. Run the CREATE TABLE income statement from the README, then refresh this page.";
}

// Flash messages passed via redirect query string
$flash = null;
if (isset($_GET['added']))   { $flash = 'Income entry added.'; }
if (isset($_GET['updated'])) { $flash = 'Income entry updated.'; }
if (isset($_GET['deleted'])) { $flash = 'Income entry deleted.'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="../assets/images/favicon.png">
<title>Income — Finance Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,500&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="dash-body">

<div class="dash-shell">

  <?php $basePath = '../'; $activeNav = 'income'; include __DIR__ . '/../includes/topbar.php'; ?>

  <main class="dash-main">

    <section class="dash-welcome dash-welcome--row">
      <div>
        <h1>Income</h1>
        <p>Every income entry you've logged, newest first.</p>
      </div>
      <a href="add_income.php" class="btn btn--primary">
        <svg viewBox="0 0 24 24" class="icon-inline"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        Add income
      </a>
    </section>

    <?php if ($flash): ?>
      <div class="alert alert--success" role="status"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($loadError): ?>
      <div class="alert alert--error" role="alert"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="dash-panel filter-panel">
      <form method="GET" action="view_income.php" class="filter-form">
        <div class="filter-field filter-field--grow">
          <label for="q">Search</label>
          <input type="text" id="q" name="q" placeholder="Search by keyword..." value="<?= htmlspecialchars($filters['q'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="filter-field">
          <label for="category">Category</label>
          <select id="category" name="category">
            <option value="">All categories</option>
            <?= category_options(INCOME_CATEGORIES, $filters['category']) ?>
          </select>
        </div>
        <div class="filter-field">
          <label for="month">Month</label>
          <input type="month" id="month" name="month" value="<?= htmlspecialchars($filters['month'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="filter-field">
          <label for="date_from">From</label>
          <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="filter-field">
          <label for="date_to">To</label>
          <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="filter-field filter-field--actions">
          <button type="submit" class="btn btn--primary">Filter</button>
          <?php if ($hasFilters): ?>
            <a href="view_income.php" class="btn btn--ghost">Reset</a>
          <?php endif; ?>
        </div>
      </form>
    </section>

    <section class="dash-panel">
      <header class="dash-panel__header">
        <h2>All entries</h2>
        <span class="panel-total">Total: <strong><?= rupees($total) ?></strong></span>
      </header>

      <?php if (empty($rows)): ?>
        <div class="empty-state">
          <?php if ($hasFilters): ?>
            <p>No income entries match these filters. Try widening your search or <a href="view_income.php">clear the filters</a>.</p>
          <?php else: ?>
            <p>No income logged yet. Click <strong>Add income</strong> above to record your first entry.</p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Source</th>
                <th>Date</th>
                <th class="align-right">Amount</th>
                <th class="align-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$row['source'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-muted"><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="align-right amount-income"><?= rupees((float)$row['amount']) ?></td>
                  <td class="align-right">
                    <div class="actions-cell">
                      <a href="edit_income.php?id=<?= (int)$row['id'] ?>" class="btn-icon" aria-label="Edit">
                        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                      </a>
                      <form method="POST" action="delete_income.php" onsubmit="return confirm('Delete this income entry? This cannot be undone.');">
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