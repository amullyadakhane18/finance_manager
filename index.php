<?php
/**
 * index.php
 * Entry point. Sends logged-in users to the dashboard and everyone
 * else to the login page.
 */

declare(strict_types=1);
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;