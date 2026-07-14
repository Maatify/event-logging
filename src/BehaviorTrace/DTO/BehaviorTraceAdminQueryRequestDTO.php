<?php

declare(strict_types=1);

namespace Maatify\EventLogging\BehaviorTrace\DTO;

use DateTimeImmutable;
use JsonSerializable;
use Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceAdminQueryInvalidArgumentException;

final readonly class BehaviorTraceAdminQueryRequestDTO implements JsonSerializable
{
    public ?string $actorType;
    public ?int $actorId;
    public ?string $action;
    public ?string $entityType;
    public ?int $entityId;
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
        ?string $action = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $requestId = null,
        ?string $correlationId = null,
        ?DateTimeImmutable $after = null,
        ?DateTimeImmutable $before = null,
        int|string|null $page = null,
        int|string|null $perPage = null,
        ?string $sortBy = null,
        ?string $sortDirection = null,
    ) {
        $this->actorType = self::normalizeNullableString($actorType, 'actorType', 32);
        $this->actorId = self::validatePositiveNullableId($actorId, 'actorId');

        $this->action = self::normalizeNullableString($action, 'action', 128);

        $this->entityType = self::normalizeNullableString($entityType, 'entityType', 64);
        $this->entityId = self::validatePositiveNullableId($entityId, 'entityId');

        $this->requestId = self::normalizeNullableString($requestId, 'requestId', 64);
        $this->correlationId = self::normalizeNullableString($correlationId, 'correlationId', 36);

        if ($after !== null && $before !== null && $after > $before) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidDateRange();
        }
        $this->after = $after;
        $this->before = $before;

        $this->page = $page;
        $this->perPage = $perPage;

        $normalizedSortBy = self::normalizeNullableString($sortBy, 'sortBy', 64);
        $this->sortBy = $normalizedSortBy === 'occurred_at'
            ? 'occurred_at'
            : null;

        $normalizedSortDirection = self::normalizeNullableString(
            $sortDirection,
            'sortDirection',
            4,
        );

        $this->sortDirection = $normalizedSortDirection !== null
            && in_array(strtoupper($normalizedSortDirection), ['ASC', 'DESC'], true)
                ? strtoupper($normalizedSortDirection)
                : null;

        if ($this->actorId !== null && $this->actorType === null) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidId('actorId without actorType');
        }

        if ($this->entityId !== null && $this->entityType === null) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidId('entityId without entityType');
        }
    }

    private static function utf8Length(string $value, string $field): int
    {
        $length = preg_match_all('/./us', $value);

        if ($length === false) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidEncoding($field);
        }

        return $length;
    }

    private static function normalizeNullableString(
        ?string $value,
        string $field,
        int $maxLength,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (self::utf8Length($trimmed, $field) > $maxLength) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidLength($field);
        }

        return $trimmed;
    }

    private static function validatePositiveNullableId(
        ?int $value,
        string $field,
    ): ?int {
        if ($value === null) {
            return null;
        }

        if ($value <= 0) {
            throw BehaviorTraceAdminQueryInvalidArgumentException::invalidId($field);
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'action' => $this->action,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
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
