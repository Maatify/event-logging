<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\DTO;

use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryInvalidArgumentException;

final class DeliveryOperationsAdminQueryRequestDTO implements \JsonSerializable
{
    public readonly int|string|null $page;
    public readonly int|string|null $perPage;
    public readonly ?string $sortBy;
    public readonly ?string $sortDirection;
    public readonly ?int $id;
    public readonly ?string $eventId;
    public readonly ?string $channel;
    public readonly ?string $operationType;
    public readonly ?string $actorType;
    public readonly ?int $actorId;
    public readonly ?string $targetType;
    public readonly ?int $targetId;
    public readonly ?string $status;
    public readonly ?int $attemptNoMin;
    public readonly ?int $attemptNoMax;
    public readonly ?string $correlationId;
    public readonly ?string $requestId;
    public readonly ?string $provider;
    public readonly ?string $providerMessageId;
    public readonly ?string $errorCode;
    public readonly ?string $errorMessageLike;
    /** @var array<string, string|int|float|bool|null>|null */
    public readonly ?array $metadataFilters;
    public readonly ?\DateTimeImmutable $scheduledAfter;
    public readonly ?\DateTimeImmutable $scheduledBefore;
    public readonly ?\DateTimeImmutable $completedAfter;
    public readonly ?\DateTimeImmutable $completedBefore;
    public readonly ?\DateTimeImmutable $after;
    public readonly ?\DateTimeImmutable $before;
    /** @var array<string, bool>|null */
    public readonly ?array $nullStateFilters;

    /**
     * @param array<string, string|int|float|bool|null>|null $metadataFilters
     * @param array<string, bool>|null $nullStateFilters
     */
    public function __construct(
        int|string|null $page = null,
        int|string|null $perPage = null,
        ?string $sortBy = null,
        ?string $sortDirection = null,
        ?int $id = null,
        ?string $eventId = null,
        ?string $channel = null,
        ?string $operationType = null,
        ?string $actorType = null,
        ?int $actorId = null,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $status = null,
        ?int $attemptNoMin = null,
        ?int $attemptNoMax = null,
        ?string $correlationId = null,
        ?string $requestId = null,
        ?string $provider = null,
        ?string $providerMessageId = null,
        ?string $errorCode = null,
        ?string $errorMessageLike = null,
        ?array $metadataFilters = null,
        ?\DateTimeImmutable $scheduledAfter = null,
        ?\DateTimeImmutable $scheduledBefore = null,
        ?\DateTimeImmutable $completedAfter = null,
        ?\DateTimeImmutable $completedBefore = null,
        ?\DateTimeImmutable $after = null,
        ?\DateTimeImmutable $before = null,
        ?array $nullStateFilters = null
    ) {
        $this->page = $page;
        $this->perPage = $perPage;

        $normSortBy = is_string($sortBy) ? trim($sortBy) : null;
        if ($normSortBy === '') $normSortBy = null;
        if ($normSortBy !== null && !preg_match('/./us', $normSortBy)) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidEncoding('sortBy');
        }
        if ($normSortBy !== null && preg_match_all('/./us', $normSortBy) > 64) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidLength('sortBy');
        }
        $this->sortBy = $normSortBy === 'occurred_at' ? 'occurred_at' : null;

        $normSortDir = is_string($sortDirection) ? trim($sortDirection) : null;
        if ($normSortDir === '') $normSortDir = null;
        if ($normSortDir !== null && !preg_match('/./us', $normSortDir)) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidEncoding('sortDirection');
        }
        if ($normSortDir !== null && preg_match_all('/./us', $normSortDir) > 4) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidLength('sortDirection');
        }
        if ($normSortDir !== null) {
            $normSortDir = strtoupper($normSortDir);
            $normSortDir = in_array($normSortDir, ['ASC', 'DESC'], true) ? $normSortDir : null;
        }
        $this->sortDirection = $normSortDir;

        if ($id !== null && $id <= 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidId('id');
        }
        $this->id = $id;

        $this->eventId = self::normalizeString($eventId, 'eventId', 36);
        $this->channel = self::normalizeString($channel, 'channel', 32);
        $this->operationType = self::normalizeString($operationType, 'operationType', 64);
        $this->actorType = self::normalizeString($actorType, 'actorType', 32);

        if ($actorId !== null && $actorId <= 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidId('actorId');
        }
        $this->actorId = $actorId;

        $this->targetType = self::normalizeString($targetType, 'targetType', 64);

        if ($targetId !== null && $targetId <= 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidId('targetId');
        }
        $this->targetId = $targetId;

        $this->status = self::normalizeString($status, 'status', 32);

        if ($attemptNoMin !== null && $attemptNoMin < 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidRetryValue('attemptNoMin');
        }
        if ($attemptNoMax !== null && $attemptNoMax < 0) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidRetryValue('attemptNoMax');
        }
        if ($attemptNoMin !== null && $attemptNoMax !== null && $attemptNoMin > $attemptNoMax) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidRetryRange();
        }
        $this->attemptNoMin = $attemptNoMin;
        $this->attemptNoMax = $attemptNoMax;

        $this->correlationId = self::normalizeString($correlationId, 'correlationId', 36);
        $this->requestId = self::normalizeString($requestId, 'requestId', 64);
        $this->provider = self::normalizeString($provider, 'provider', 64);
        $this->providerMessageId = self::normalizeString($providerMessageId, 'providerMessageId', 128);
        $this->errorCode = self::normalizeString($errorCode, 'errorCode', 64);
        $this->errorMessageLike = self::normalizeString($errorMessageLike, 'errorMessageLike', 128);

        if ($metadataFilters !== null) {
            if (count($metadataFilters) > 5) {
                throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataCount();
            }
            /** @var mixed $value */
            foreach ($metadataFilters as $path => $value) {
                /** @phpstan-ignore function.alreadyNarrowedType */
                if (!is_string($path)) {
                    throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataPath();
                }
                $pathStr = (string)$path;
                if (strlen($pathStr) > 64 || !preg_match('/^\$\.[A-Za-z0-9_]+(\.[A-Za-z0-9_]+){0,4}$/', $pathStr)) {
                    throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataPath();
                }
                $isScalarVal = is_scalar($value);
                if ($value !== null && !$isScalarVal) {
                    throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataValue();
                }
                try {
                    json_encode($value, \JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataValue();
                }
            }
        }
        $this->metadataFilters = $metadataFilters;

        if ($scheduledAfter && $scheduledBefore && $scheduledAfter > $scheduledBefore) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidDateRange('scheduled_at');
        }
        $this->scheduledAfter = $scheduledAfter;
        $this->scheduledBefore = $scheduledBefore;

        if ($completedAfter && $completedBefore && $completedAfter > $completedBefore) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidDateRange('completed_at');
        }
        $this->completedAfter = $completedAfter;
        $this->completedBefore = $completedBefore;

        if ($after && $before && $after > $before) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidDateRange('occurred_at');
        }
        $this->after = $after;
        $this->before = $before;

        if ($nullStateFilters !== null) {
            $allowed = [
                'actorType', 'actorId', 'targetType', 'targetId',
                'scheduledAt', 'completedAt', 'correlationId', 'requestId',
                'provider', 'providerMessageId', 'errorCode', 'errorMessage'
            ];
            /** @var mixed $val */
            foreach ($nullStateFilters as $key => $val) {
                /** @phpstan-ignore function.alreadyNarrowedType */
                if (!is_string($key)) {
                    /** @phpstan-ignore function.alreadyNarrowedType */
                    $keyString = is_scalar($key) ? (string) $key : 'unknown';
                    throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidNullState($keyString);
                }
                $keyStr = (string)$key;
                $isBoolVal = is_bool($val);
                if (!in_array($keyStr, $allowed, true) || !$isBoolVal) {
                    throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidNullState($keyStr);
                }
            }
        }
        $this->nullStateFilters = $nullStateFilters;
    }

    private static function normalizeString(?string $value, string $field, int $maxLength): ?string
    {
        $val = is_string($value) ? trim($value) : null;
        if ($val === '') return null;
        if ($val !== null && !preg_match('/./us', $val)) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidEncoding($field);
        }
        if ($val !== null && preg_match_all('/./us', $val) > $maxLength) {
            throw DeliveryOperationsAdminQueryInvalidArgumentException::invalidLength($field);
        }
        return $val;
    }

    /**
     * @return array{
     *     page: int|string|null,
     *     perPage: int|string|null,
     *     sortBy: ?string,
     *     sortDirection: ?string,
     *     id: ?int,
     *     eventId: ?string,
     *     channel: ?string,
     *     operationType: ?string,
     *     actorType: ?string,
     *     actorId: ?int,
     *     targetType: ?string,
     *     targetId: ?int,
     *     status: ?string,
     *     attemptNoMin: ?int,
     *     attemptNoMax: ?int,
     *     correlationId: ?string,
     *     requestId: ?string,
     *     provider: ?string,
     *     providerMessageId: ?string,
     *     errorCode: ?string,
     *     errorMessageLike: ?string,
     *     metadataFilters: array<string, string|int|float|bool|null>|null,
     *     scheduledAfter: ?string,
     *     scheduledBefore: ?string,
     *     completedAfter: ?string,
     *     completedBefore: ?string,
     *     after: ?string,
     *     before: ?string,
     *     nullStateFilters: array<string, bool>|null
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'page' => $this->page,
            'perPage' => $this->perPage,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
            'id' => $this->id,
            'eventId' => $this->eventId,
            'channel' => $this->channel,
            'operationType' => $this->operationType,
            'actorType' => $this->actorType,
            'actorId' => $this->actorId,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
            'status' => $this->status,
            'attemptNoMin' => $this->attemptNoMin,
            'attemptNoMax' => $this->attemptNoMax,
            'correlationId' => $this->correlationId,
            'requestId' => $this->requestId,
            'provider' => $this->provider,
            'providerMessageId' => $this->providerMessageId,
            'errorCode' => $this->errorCode,
            'errorMessageLike' => $this->errorMessageLike,
            'metadataFilters' => $this->metadataFilters,
            'scheduledAfter' => $this->scheduledAfter?->format(\DATE_ATOM),
            'scheduledBefore' => $this->scheduledBefore?->format(\DATE_ATOM),
            'completedAfter' => $this->completedAfter?->format(\DATE_ATOM),
            'completedBefore' => $this->completedBefore?->format(\DATE_ATOM),
            'after' => $this->after?->format(\DATE_ATOM),
            'before' => $this->before?->format(\DATE_ATOM),
            'nullStateFilters' => $this->nullStateFilters,
        ];
    }
}
