<?php
/**
 * logout.php
 * Destroys the session and sends the user back to the login page.
 */

declare(strict_types=1);
session_start();

// Clear session data
$_SESSION = [];

// Remove the session cookie itself
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: login.php?loggedout=1');
exit;