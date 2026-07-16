<?php
/**
 * expense/add_expense.php
 * Add Expense — inserts a new row into the `expenses` table for the logged-in user.
 */

declare(strict_types=1);
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helper.php';

$userId = (int)$_SESSION['user_id'];
$errors = [];
$old = ['category' => '', 'amount' => '', 'date' => date('Y-m-d')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    }

    $category = trim((string)($_POST['category'] ?? ''));
    $amount = trim((string)($_POST['amount'] ?? ''));
    $date   = trim((string)($_POST['date'] ?? ''));

    $old = ['category' => $category, 'amount' => $amount, 'date' => $date !== '' ? $date : date('Y-m-d')];

    if ($category === '' || mb_strlen($category) > 120) {
        $errors[] = 'Please enter a category (e.g. Food, Travel) under 120 characters.';
    }

    if (!is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = 'Please enter a valid amount greater than 0.';
    }

    $dateTime = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateTime) {
        $errors[] = 'Please enter a valid date.';
    }

    if (empty($errors)) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare(
                'INSERT INTO expenses (user_id, category, amount, created_at) VALUES (:uid, :category, :amount, :created_at)'
            );
            $stmt->execute([
                'uid'        => $userId,
                'category'   => $category,
                'amount'     => (float)$amount,
                'created_at' => $date . ' ' . date('H:i:s'),
            ]);

            header('Location: view_expense.php?added=1');
            exit;
        } catch (PDOException $e) {
            $errors[] = "Couldn't save this entry. Make sure the expenses table exists (see README), then try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Expense — Finance Manager</title>
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
    </nav>

    <form method="POST" action="../logout.php" class="dash-topbar__logout">
      <button type="submit" class="btn btn--ghost">
        <svg viewBox="0 0 24 24" class="icon-inline"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
        Log out
      </button>
    </form>
  </header>

  <main class="form-panel" style="width:100%;">
    <div class="form-card">

      <header class="form-card__header">
        <h2>Add expense</h2>
        <p>Record a new expense.</p>
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

      <form method="POST" action="add_expense.php" class="reg-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <div class="field">
          <label for="category">Category</label>
          <input
            type="text"
            id="category"
            name="category"
            placeholder="Food, Travel, Shopping..."
            value="<?= htmlspecialchars($old['category'], ENT_QUOTES, 'UTF-8') ?>"
            required
            autofocus
          >
        </div>

        <div class="field">
          <label for="amount">Amount (₹)</label>
          <input
            type="number"
            id="amount"
            name="amount"
            step="0.01"
            min="0.01"
            placeholder="30000"
            value="<?= htmlspecialchars($old['amount'], ENT_QUOTES, 'UTF-8') ?>"
            required
          >
        </div>

        <div class="field">
          <label for="date">Date received</label>
          <input
            type="date"
            id="date"
            name="date"
            value="<?= htmlspecialchars($old['date'], ENT_QUOTES, 'UTF-8') ?>"
            required
          >
        </div>

        <button type="submit" class="btn btn--primary btn--block">Save expense</button>
      </form>

      <p class="alt-action"><a href="view_expense.php">← Back to expense list</a></p>

    </div>
  </main>

</div>

</body>
</html>