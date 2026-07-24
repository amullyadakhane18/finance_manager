<?php
/**
 * index.php
 * Entry point. Sends logged-in users to the dashboard and everyone
 * else to the login page.
 */

declare(strict_types=1);
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: register.php');
} else {
    header('Location: login.php');
}
exit;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
    <title>Document</title>
</head>
<body>
    
</body>
</html>