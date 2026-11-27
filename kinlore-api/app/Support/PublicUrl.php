<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The base address that phones and invitees use to reach this server (invite and setup links).
 */
final class PublicUrl
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];

    /**
     * False when APP_URL is empty, a local address, or under a reserved example domain (RFC 2606).
     */
    public static function isConfigured(): bool
    {
        $host = strtolower(trim((string) parse_url((string) config('app.url'), PHP_URL_HOST), '[].'));

        return $host !== ''
            && ! in_array($host, self::LOCAL_HOSTS, true)
            && preg_match('/(^|\.)example\.(com|org|net)$/', $host) !== 1;
    }

    public static function base(): string
    {
        // APP_URL stays authoritative; the request root (Host header, via the trusted proxies) is only a fallback for a placeholder.
        return rtrim(self::isConfigured() ? (string) config('app.url') : url('/'), '/');
    }
}
