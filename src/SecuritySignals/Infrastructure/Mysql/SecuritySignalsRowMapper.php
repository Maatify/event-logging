<?php

declare(strict_types=1);

namespace Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsViewDTO;

/** @internal */
class SecuritySignalsRowMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public function map(array $row): SecuritySignalsViewDTO
    {
        return new SecuritySignalsViewDTO(
            id: self::intValue($row, 'id') ?? 0,
            eventId: self::stringValue($row, 'event_id') ?? '',
            actorType: self::stringValue($row, 'actor_type'),
            actorId: self::intValue($row, 'actor_id'),
            signalType: self::stringValue($row, 'signal_type') ?? '',
            severity: self::stringValue($row, 'severity') ?? '',
            correlationId: self::stringValue($row, 'correlation_id'),
            requestId: self::stringValue($row, 'request_id'),
            routeName: self::stringValue($row, 'route_name'),
            ipAddress: self::stringValue($row, 'ip_address'),
            userAgent: self::stringValue($row, 'user_agent'),
            metadata: self::jsonArray($row, 'metadata'),
            occurredAt: self::dateValue($row, 'occurred_at'),
        );
    }

    /** @param array<string, mixed> $row */
    private static function stringValue(array $row, string $key): ?string
    {
        return is_string($row[$key] ?? null) ? $row[$key] : null;
    }

    /** @param array<string, mixed> $row */
    private static function intValue(array $row, string $key): ?int
    {
        return isset($row[$key]) && is_numeric($row[$key]) ? (int) $row[$key] : null;
    }

    /** @param array<string, mixed> $row */
    private static function dateValue(array $row, string $key): DateTimeImmutable
    {
        return new DateTimeImmutable(
            is_string($row[$key] ?? null) ? $row[$key] : '1970-01-01 00:00:00',
            new DateTimeZone('UTC'),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private static function jsonArray(array $row, string $key): ?array
    {
        if (! isset($row[$key]) || ! is_string($row[$key]) || $row[$key] === '') {
            return null;
        }

        try {
            $decoded = json_decode($row[$key], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        foreach (array_keys($decoded) as $decodedKey) {
            if (! is_string($decodedKey)) {
                return null;
            }
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
