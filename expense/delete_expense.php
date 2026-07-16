<?php
/**
 * expense/delete_expense.php
 * Delete Expense — removes a single row from `expenses`.
 * POST-only, CSRF-protected, and scoped to the logged-in user's own rows.
 */

declare(strict_types=1);
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    header('Location: view_expense.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$id     = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $userId]);
    } catch (PDOException $e) {
        // Silently ignore — user just gets redirected back to an unchanged list.
    }
}

header('Location: view_expense.php?deleted=1');
exit;