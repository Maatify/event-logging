<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DiagnosticsTelemetry\DTO;

use DateTimeImmutable;
use JsonSerializable;
use Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryAdminQueryInvalidArgumentException;

final readonly class DiagnosticsTelemetryAdminQueryRequestDTO implements JsonSerializable
{
    public ?string $actorType;
    public ?int $actorId;
    public ?string $eventKey;
    public ?string $severity;
    public ?string $requestId;
    public ?string $correlationId;
    public ?DateTimeImmutable $after;
    public ?DateTimeImmutable $before;
    public int|string|null $page;
    public int|string|null $perPage;
    public ?string $sortBy;
    public ?string $sortDirection;

    public function __construct(
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $eventKey = null,
        ?string $severity = null,
        ?string $requestId = null,
        ?string $correlationId = null,
        ?DateTimeImmutable $after = null,
        ?DateTimeImmutable $before = null,
        int|string|null $page = null,
        int|string|null $perPage = null,
        ?string $sortBy = null,
        ?string $sortDirection = null
    ) {
        $this->actorType = self::normalizeString($actorType, 32, 'actorType');
        $this->eventKey = self::normalizeString($eventKey, 255, 'eventKey');
        $this->severity = self::normalizeString($severity, 16, 'severity');
        $this->requestId = self::normalizeString($requestId, 64, 'requestId');
        $this->correlationId = self::normalizeString($correlationId, 36, 'correlationId');

        $this->sortBy = self::normalizeSortBy($sortBy);
        $this->sortDirection = self::normalizeSortDirection($sortDirection);

        if ($actorId !== null && $actorId <= 0) {
            throw DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidId('actorId');
        }
        $this->actorId = $actorId;

        if ($after !== null && $before !== null && $after > $before) {
            throw DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidDateRange();
        }
        $this->after = $after;
        $this->before = $before;

        $this->page = $page;
        $this->perPage = $perPage;
    }

    private static function normalizeString(?string $value, int $maxLength, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $length = preg_match_all('/./us', $trimmed);
        if ($length === false || $length === 0) {
            throw DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidEncoding($field);
        }

        if ($length > $maxLength) {
            throw DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidLength($field);
        }

        return $trimmed;
    }

    private static function normalizeSortBy(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $length = preg_match_all('/./us', $trimmed);
        if ($length === false || $length === 0) {
            throw DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidEncoding('sortBy');
        }

        if ($length > 64) {
            throw DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidLength('sortBy');
        }

        if ($trimmed === 'occurred_at') {
            return $trimmed;
        }

        return null;
    }

    private static function normalizeSortDirection(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = strtoupper(trim($value));
        if ($trimmed === '') {
            return null;
        }

        $length = preg_match_all('/./us', $trimmed);
        if ($length === false || $length === 0) {
            throw DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidEncoding('sortDirection');
        }

        if ($length > 4) {
            throw DiagnosticsTelemetryAdminQueryInvalidArgumentException::invalidLength('sortDirection');
        }

        if ($trimmed === 'ASC' || $trimmed === 'DESC') {
            return $trimmed;
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'eventKey' => $this->eventKey,
            'severity' => $this->severity,
            'requestId' => $this->requestId,
            'correlationId' => $this->correlationId,
            'after' => $this->after?->format(DATE_ATOM),
            'before' => $this->before?->format(DATE_ATOM),
            'page' => $this->page,
            'perPage' => $this->perPage,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
        ];
    }
}
