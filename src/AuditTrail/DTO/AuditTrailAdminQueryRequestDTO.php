<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuditTrail\DTO;

use DateTimeImmutable;
use JsonSerializable;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailAdminQueryInvalidArgumentException;

final readonly class AuditTrailAdminQueryRequestDTO implements JsonSerializable
{
    public ?string $actorType;
    public ?int $actorId;
    public ?string $eventKey;
    public ?string $entityType;
    public ?int $entityId;
    public ?string $subjectType;
    public ?int $subjectId;
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
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?string $requestId = null,
        ?string $correlationId = null,
        ?DateTimeImmutable $after = null,
        ?DateTimeImmutable $before = null,
        int|string|null $page = null,
        int|string|null $perPage = null,
        ?string $sortBy = null,
        ?string $sortDirection = null
    ) {
        $this->actorType = self::normalizeNullableString($actorType, 'actorType', 32);
        $this->actorId = self::validatePositiveNullableId($actorId, 'actorId');
        if ($this->actorId !== null && $this->actorType === null) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidId('actorId without actorType');
        }

        $this->eventKey = self::normalizeNullableString($eventKey, 'eventKey', 255);
        $this->entityType = self::normalizeNullableString($entityType, 'entityType', 64);
        $this->entityId = self::validatePositiveNullableId($entityId, 'entityId');
        if ($this->entityId !== null && $this->entityType === null) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidId('entityId without entityType');
        }

        $this->subjectType = self::normalizeNullableString($subjectType, 'subjectType', 64);
        $this->subjectId = self::validatePositiveNullableId($subjectId, 'subjectId');
        if ($this->subjectId !== null && $this->subjectType === null) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidId('subjectId without subjectType');
        }

        $this->requestId = self::normalizeNullableString($requestId, 'requestId', 64);
        $this->correlationId = self::normalizeNullableString($correlationId, 'correlationId', 36);

        $this->after = $after;
        $this->before = $before;
        if ($this->after !== null && $this->before !== null && $this->after > $this->before) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidDateRange();
        }

        $this->page = $page;
        $this->perPage = $perPage;

        $normalizedSortBy = self::normalizeNullableString($sortBy, 'sortBy', 64);
        $this->sortBy = $normalizedSortBy === 'occurred_at' ? 'occurred_at' : null;

        $normalizedSortDirection = self::normalizeNullableString(
            $sortDirection,
            'sortDirection',
            4
        );
        $this->sortDirection = $normalizedSortDirection !== null
            && in_array(strtoupper($normalizedSortDirection), ['ASC', 'DESC'], true)
            ? strtoupper($normalizedSortDirection)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'eventKey' => $this->eventKey,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'subjectType' => $this->subjectType,
            'subjectId' => $this->subjectId,
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

    private static function utf8Length(string $value, string $field): int
    {
        $length = preg_match_all('/./us', $value);

        if ($length === false) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidEncoding($field);
        }

        return $length;
    }

    private static function normalizeNullableString(
        ?string $value,
        string $field,
        int $maxLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (self::utf8Length($trimmed, $field) > $maxLength) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidLength($field);
        }

        return $trimmed;
    }

    private static function validatePositiveNullableId(?int $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value <= 0) {
            throw AuditTrailAdminQueryInvalidArgumentException::invalidId($field);
        }

        return $value;
    }

}
