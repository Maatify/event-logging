<?php

declare(strict_types=1);

namespace Maatify\EventLogging\AuthoritativeAudit\DTO;

use DateTimeImmutable;
use JsonSerializable;
use Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditAdminQueryInvalidArgumentException;

final readonly class AuthoritativeAuditAdminQueryRequestDTO implements JsonSerializable
{
    public ?string $eventId;
    public ?string $actorType;
    public ?int $actorId;
    public ?string $targetType;
    public ?int $targetId;
    public ?string $action;
    public ?string $correlationId;
    public ?DateTimeImmutable $after;
    public ?DateTimeImmutable $before;
    public int|string|null $page;
    public int|string|null $perPage;
    public ?string $sortBy;
    public ?string $sortDirection;

    public function __construct(
        ?string $eventId = null,
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $action = null,
        ?string $correlationId = null,
        ?DateTimeImmutable $after = null,
        ?DateTimeImmutable $before = null,
        int|string|null $page = null,
        int|string|null $perPage = null,
        ?string $sortBy = null,
        ?string $sortDirection = null
    ) {
        $this->eventId = self::normalizeString($eventId, 36, 'eventId');
        $this->actorType = self::normalizeString($actorType, 32, 'actorType');
        $this->targetType = self::normalizeString($targetType, 64, 'targetType');
        $this->action = self::normalizeString($action, 128, 'action');
        $this->correlationId = self::normalizeString($correlationId, 36, 'correlationId');

        $this->sortBy = self::normalizeSortBy($sortBy);
        $this->sortDirection = self::normalizeSortDirection($sortDirection);

        if ($actorId !== null && $actorId <= 0) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId('actorId');
        }
        $this->actorId = $actorId;

        if ($targetId !== null && $targetId <= 0) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidId('targetId');
        }
        $this->targetId = $targetId;

        if ($after !== null && $before !== null && $after > $before) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidDateRange();
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
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidEncoding($field);
        }

        if ($length > $maxLength) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidLength($field);
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
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidEncoding('sortBy');
        }

        if ($length > 64) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidLength('sortBy');
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
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidEncoding('sortDirection');
        }

        if ($length > 4) {
            throw AuthoritativeAuditAdminQueryInvalidArgumentException::invalidLength('sortDirection');
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
            'eventId' => $this->eventId,
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'action' => $this->action,
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
