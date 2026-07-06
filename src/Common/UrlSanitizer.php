<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Common;

final class UrlSanitizer
{
    /** @param list<string> $sensitiveKeys */
    public static function sanitize(string $url, array $sensitiveKeys = ['token', 'secret', 'password', 'key', 'signature']): string
    {
        $parts = parse_url($url);
        if (! isset($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);
        foreach ($query as $key => $_) {
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos((string) $key, $sensitiveKey) !== false) {
                    $query[$key] = '[redacted]';
                    break;
                }
            }
        }

        $sanitizedQuery = http_build_query($query);
        $base = strtok($url, '?');

        return $sanitizedQuery === '' ? (string) $base : (string) $base . '?' . $sanitizedQuery;
    }
}
