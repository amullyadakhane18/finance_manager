<?php
/**
 * includes/helpers.php
 * Small shared helper functions used across protected pages.
 */

declare(strict_types=1);

if (!function_exists('rupees')) {
    function rupees(float $amount): string
    {
        return '₹' . number_format($amount, 2);
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): bool
    {
        return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
}