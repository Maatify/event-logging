<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Common;

final class MetadataSanitizer
{
    /**
     * @param array<string, mixed> $metadata
     * @param string[] $sensitiveKeys
     * @return array<string, mixed>
     */
    public static function sanitize(array $metadata, array $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization', 'cookie']): array
    {
        foreach ($metadata as $key => $value) {
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos((string) $key, (string) $sensitiveKey) !== false) {
                    $metadata[$key] = '[redacted]';
                    continue 2;
                }
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $metadata[$key] = self::sanitize($value, $sensitiveKeys);
            }
        }

        return $metadata;
    }
}
