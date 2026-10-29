<?php

use CodeIgniter\I18n\Time;
use Config\App;

if (! function_exists('generate_folio')) {
    /**
     * Generates a reusable folio with date and left-padded sequence.
     */
    function generate_folio(string $prefix = 'BOL', int $sequence = 1): string
    {
        $date = Time::now(config(App::class)->appTimezone)->format('Ymd');

        return sprintf('%s-%s-%06d', strtoupper(trim($prefix)), $date, $sequence);
    }
}

if (! function_exists('normalize_message')) {
    /**
     * Normalizes simple text messages without touching API response structure.
     */
    function normalize_message(?string $message): string
    {
        $message = trim((string) $message);
        $message = preg_replace('/\s+/', ' ', $message);

        return $message ?? '';
    }
}

if (! function_exists('client_ip_address')) {
    /**
     * Returns the client IP using CodeIgniter request handling.
     */
    function client_ip_address(): string
    {
        return service('request')->getIPAddress();
    }
}

if (! function_exists('client_user_agent')) {
    /**
     * Returns the client user agent string when available.
     */
    function client_user_agent(): ?string
    {
        $userAgent = service('request')->getUserAgent();

        return $userAgent ? (string) $userAgent->getAgentString() : null;
    }
}

if (! function_exists('sanitize_simple_string')) {
    /**
     * Sanitizes simple strings for basic reusable input cleanup.
     */
    function sanitize_simple_string(?string $value): string
    {
        $value = strip_tags((string) $value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}

if (! function_exists('app_timestamp')) {
    /**
     * Returns the current timestamp using the configured application timezone.
     */
    function app_timestamp(string $format = 'Y-m-d H:i:s'): string
    {
        return Time::now(config(App::class)->appTimezone)->format($format);
    }
}

if (! function_exists('set_authenticated_user')) {
    /**
     * Stores the authenticated user for the current request lifecycle.
     */
    function set_authenticated_user(?array $user): void
    {
        static $authenticatedUser;

        $authenticatedUser = $user;

        $GLOBALS['boletitos_authenticated_user'] = $authenticatedUser;
    }
}

if (! function_exists('get_authenticated_user')) {
    /**
     * Returns the authenticated user stored for the current request lifecycle.
     */
    function get_authenticated_user(): ?array
    {
        return $GLOBALS['boletitos_authenticated_user'] ?? null;
    }
}
