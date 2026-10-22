<?php
declare(strict_types=1);

/**
 * ZARO WEAR shared helper functions.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Safely escape values for HTML output.
 */
if (!function_exists('h')) {
    function h(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

/**
 * Format a price for display.
 * This function does not calculate or verify payment amounts.
 */
if (!function_exists('money')) {
    function money(int|float|string $amount): string
    {
        if (!is_numeric($amount)) {
            $amount = 0;
        }

        return 'Rs. ' . number_format((float) $amount, 2);
    }
}

/**
 * Create a session-based CSRF token.
 */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(
                random_bytes(32)
            );
        }

        return $_SESSION['csrf_token'];
    }
}

/**
 * Validate a submitted CSRF token.
 */
if (!function_exists('verify_csrf')) {
    function verify_csrf(): bool
    {
        $submitted = $_POST['csrf_token'] ?? '';
        $stored = $_SESSION['csrf_token'] ?? '';

        return is_string($submitted)
            && is_string($stored)
            && $submitted !== ''
            && $stored !== ''
            && hash_equals($stored, $submitted);
    }
}
