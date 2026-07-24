<?php
/**
 * income/edit_income.php
 * Edit Income — updates an existing row in the `income` table.
 * Only lets a user edit their own entries (scoped by user_id).
 */

declare(strict_types=1);
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helper.php';

$userId = (int)$_SESSION['user_id'];
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errors = [];
$notFound = false;

if ($id <= 0) {
    header('Location: view_income.php');
    exit;
}

try {
    $pdo = get_db_connection();
} catch (PDOException $e) {
    $errors[] = "Couldn't connect to the database.";
    $pdo = null;
}

$old = ['source' => '', 'amount' => '', 'date' => date('Y-m-d')];

// Load the existing entry (GET) or use submitted values (POST, on validation failure)
if ($pdo && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $stmt = $pdo->prepare('SELECT source, amount, created_at FROM income WHERE id = :id AND user_id = :uid LIMIT 1');
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $entry = $stmt->fetch();

        if (!$entry) {
            $notFound = true;
        } else {
            $old = [
                'source' => $entry['source'],
                'amount' => rtrim(rtrim(number_format((float)$entry['amount'], 2, '.', ''), '0'), '.'),
                'date'   => date('Y-m-d', strtotime($entry['created_at'])),
            ];
        }
    } catch (PDOException $e) {
        $errors[] = "Couldn't load this entry. Make sure the income table exists.";
    }
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf()) {
        $errors[] = 'Your session has expired. Please refresh the page and try again.';
    }

    $source = trim((string)($_POST['source'] ?? ''));
    $amount = trim((string)($_POST['amount'] ?? ''));
    $date   = trim((string)($_POST['date'] ?? ''));

    $old = ['source' => $source, 'amount' => $amount, 'date' => $date !== '' ? $date : date('Y-m-d')];

    if ($source === '' || mb_strlen($source) > 120) {
        $errors[] = 'Please enter a source (e.g. Salary, Freelancing) under 120 characters.';
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
            $stmt = $pdo->prepare(
                'UPDATE income SET source = :source, amount = :amount, created_at = :created_at
                 WHERE id = :id AND user_id = :uid'
            );
            $stmt->execute([
                'source'     => $source,
                'amount'     => (float)$amount,
                'created_at' => $date . ' ' . date('H:i:s'),
                'id'         => $id,
                'uid'        => $userId,
            ]);

            if ($stmt->rowCount() === 0) {
                // Either nothing changed or the row doesn't belong to this user.
                $stmt2 = $pdo->prepare('SELECT id FROM income WHERE id = :id AND user_id = :uid LIMIT 1');
                $stmt2->execute(['id' => $id, 'uid' => $userId]);
                if (!$stmt2->fetch()) {
                    $notFound = true;
                }
            }

            if (!$notFound) {
                header('Location: view_income.php?updated=1');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "Couldn't update this entry. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Income — Finance Manager</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="icon" href="../assets/images/favicon.png">
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
      <a href="view_income.php" class="dash-nav__link is-active">Income</a>
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

      <?php if ($notFound): ?>

        <header class="form-card__header">
          <h2>Entry not found</h2>
          <p>This income entry doesn't exist or doesn't belong to your account.</p>
        </header>
        <a class="btn btn--primary btn--block" href="view_income.php">Back to income list</a>

      <?php else: ?>

        <header class="form-card__header">
          <h2>Edit income</h2>
          <p>Update this entry's details.</p>
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

        <form method="POST" action="edit_income.php?id=<?= $id ?>" class="reg-form" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="id" value="<?= $id ?>">

          <div class="field">
            <label for="source">Source</label>
            <input
              type="text"
              id="source"
              name="source"
              value="<?= htmlspecialchars($old['source'], ENT_QUOTES, 'UTF-8') ?>"
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

          <button type="submit" class="btn btn--primary btn--block">Save changes</button>
        </form>

        <p class="alt-action"><a href="view_income.php">← Back to income list</a></p>

      <?php endif; ?>

    </div>
  </main>

</div>

</body>
</html>