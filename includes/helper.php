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

if (!function_exists('is_valid_iso_date')) {
    function is_valid_iso_date(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }
}
