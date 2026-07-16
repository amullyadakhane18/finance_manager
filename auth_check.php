<?php
/**
 * auth_check.php
 * Include this at the very top of every protected page
 * (dashboard.php, add-income.php, add-expense.php, profile.php, ...).
 *
 * Usage:
 *   require_once __DIR__ . '/auth_check.php';
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
 
// Make sure every protected page that renders a form has a CSRF token ready.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
 