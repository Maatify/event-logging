<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryInvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeliveryOperationsAdminQueryRequestDTOTest extends TestCase
{
    public function testItNormalizesAndValidatesAllProperties(): void
    {
        $dto = new DeliveryOperationsAdminQueryRequestDTO(
            page: '2',
            perPage: '10',
            sortBy: ' occurred_at ',
            sortDirection: ' asc ',
            id: 5,
            eventId: ' evt-123 ',
            channel: ' chan-1 ',
            operationType: ' op-1 ',
            actorType: ' act-1 ',
            actorId: 42,
            targetType: ' tar-1 ',
            targetId: 43,
            status: ' stat-1 ',
            attemptNoMin: 1,
            attemptNoMax: 3,
            correlationId: ' cor-1 ',
            requestId: ' req-1 ',
            provider: ' prov-1 ',
            providerMessageId: ' pmid-1 ',
            errorCode: ' err-1 ',
            errorMessageLike: ' err-msg ',
            metadataFilters: ['$.foo' => 'bar'],
            scheduledAfter: new DateTimeImmutable('2023-01-01', new DateTimeZone('UTC')),
            scheduledBefore: new DateTimeImmutable('2023-01-02', new DateTimeZone('UTC')),
            completedAfter: new DateTimeImmutable('2023-01-03', new DateTimeZone('UTC')),
            completedBefore: new DateTimeImmutable('2023-01-04', new DateTimeZone('UTC')),
            after: new DateTimeImmutable('2023-01-05', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2023-01-06', new DateTimeZone('UTC')),
            nullStateFilters: ['actorId' => true]
        );

        $this->assertEquals('2', $dto->page);
        $this->assertEquals('10', $dto->perPage);
        $this->assertEquals('occurred_at', $dto->sortBy);
        $this->assertEquals('ASC', $dto->sortDirection);
        $this->assertEquals(5, $dto->id);
        $this->assertEquals('evt-123', $dto->eventId);
        $this->assertEquals('chan-1', $dto->channel);
        $this->assertEquals('op-1', $dto->operationType);
        $this->assertEquals('act-1', $dto->actorType);
        $this->assertEquals(42, $dto->actorId);
        $this->assertEquals('tar-1', $dto->targetType);
        $this->assertEquals(43, $dto->targetId);
        $this->assertEquals('stat-1', $dto->status);
        $this->assertEquals(1, $dto->attemptNoMin);
        $this->assertEquals(3, $dto->attemptNoMax);
        $this->assertEquals('cor-1', $dto->correlationId);
        $this->assertEquals('req-1', $dto->requestId);
        $this->assertEquals('prov-1', $dto->provider);
        $this->assertEquals('pmid-1', $dto->providerMessageId);
        $this->assertEquals('err-1', $dto->errorCode);
        $this->assertEquals('err-msg', $dto->errorMessageLike);
        $this->assertEquals(['$.foo' => 'bar'], $dto->metadataFilters);
        $this->assertEquals(['actorId' => true], $dto->nullStateFilters);

        $json = $dto->jsonSerialize();
        $expectedKeys = [
            'page', 'perPage', 'sortBy', 'sortDirection', 'id', 'eventId',
            'channel', 'operationType', 'actorType', 'actorId', 'targetType',
            'targetId', 'status', 'attemptNoMin', 'attemptNoMax', 'correlationId',
            'requestId', 'provider', 'providerMessageId', 'errorCode', 'errorMessageLike',
            'metadataFilters', 'scheduledAfter', 'scheduledBefore', 'completedAfter',
            'completedBefore', 'after', 'before', 'nullStateFilters'
        ];
        $this->assertEquals($expectedKeys, array_keys($json));
    }

    public function testItThrowsOnInvalidSortBy(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query length: sortBy');
        new DeliveryOperationsAdminQueryRequestDTO(sortBy: str_repeat('a', 65));
    }

    public function testItThrowsOnInvalidSortDirection(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query length: sortDirection');
        new DeliveryOperationsAdminQueryRequestDTO(sortDirection: str_repeat('a', 5));
    }

    public function testItThrowsOnInvalidId(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query ID: id');
        new DeliveryOperationsAdminQueryRequestDTO(id: 0);
    }

    public function testItThrowsOnInvalidActorId(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query ID: actorId');
        new DeliveryOperationsAdminQueryRequestDTO(actorId: -1);
    }

    public function testItThrowsOnInvalidTargetId(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query ID: targetId');
        new DeliveryOperationsAdminQueryRequestDTO(targetId: 0);
    }

    public function testItThrowsOnInvalidEventIdEncoding(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query UTF-8 encoding: eventId');
        new DeliveryOperationsAdminQueryRequestDTO(eventId: "\xff");
    }

    public function testItThrowsOnInvalidEventIdLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query length: eventId');
        new DeliveryOperationsAdminQueryRequestDTO(eventId: str_repeat('a', 37));
    }

    public function testItThrowsOnInvalidChannelLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        new DeliveryOperationsAdminQueryRequestDTO(channel: str_repeat('a', 33));
    }

    public function testItThrowsOnInvalidOperationTypeLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        new DeliveryOperationsAdminQueryRequestDTO(operationType: str_repeat('a', 65));
    }

    public function testItThrowsOnInvalidActorTypeLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        new DeliveryOperationsAdminQueryRequestDTO(actorType: str_repeat('a', 33));
    }

    public function testItThrowsOnInvalidTargetTypeLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        new DeliveryOperationsAdminQueryRequestDTO(targetType: str_repeat('a', 65));
    }

    public function testItThrowsOnInvalidStatusLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        new DeliveryOperationsAdminQueryRequestDTO(status: str_repeat('a', 33));
    }

    public function testItThrowsOnInvalidCorrelationIdLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        new DeliveryOperationsAdminQueryRequestDTO(correlationId: str_repeat('a', 37));
    }

    public function testItThrowsOnInvalidRequestIdLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        new DeliveryOperationsAdminQueryRequestDTO(requestId: str_repeat('a', 65));
    }

    public function testItThrowsOnInvalidProviderLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        new DeliveryOperationsAdminQueryRequestDTO(provider: str_repeat('a', 65));
    }

    public function testItThrowsOnInvalidProviderMessageIdLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        new DeliveryOperationsAdminQueryRequestDTO(providerMessageId: str_repeat('a', 129));
    }

    public function testItThrowsOnInvalidErrorCodeLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        new DeliveryOperationsAdminQueryRequestDTO(errorCode: str_repeat('a', 65));
    }

    public function testItThrowsOnInvalidErrorMessageLikeLength(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        new DeliveryOperationsAdminQueryRequestDTO(errorMessageLike: str_repeat('a', 129));
    }

    public function testItThrowsOnInvalidRetryValueMin(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query retry value: attemptNoMin');
        new DeliveryOperationsAdminQueryRequestDTO(attemptNoMin: -1);
    }

    public function testItThrowsOnInvalidRetryValueMax(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query retry value: attemptNoMax');
        new DeliveryOperationsAdminQueryRequestDTO(attemptNoMax: -1);
    }

    public function testItThrowsOnInvalidRetryRange(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query retry range: attempt_no_min must be less than or equal to attempt_no_max');
        new DeliveryOperationsAdminQueryRequestDTO(attemptNoMin: 5, attemptNoMax: 4);
    }

    public function testItAllowsZeroRetryRange(): void
    {
        $dto = new DeliveryOperationsAdminQueryRequestDTO(attemptNoMin: 0, attemptNoMax: 0);
        $this->assertEquals(0, $dto->attemptNoMin);
        $this->assertEquals(0, $dto->attemptNoMax);
    }

    public function testItThrowsOnInvalidScheduledRange(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query date range: scheduled_at');
        new DeliveryOperationsAdminQueryRequestDTO(
            scheduledAfter: new DateTimeImmutable('2023-01-02'),
            scheduledBefore: new DateTimeImmutable('2023-01-01')
        );
    }

    public function testItThrowsOnInvalidCompletedRange(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query date range: completed_at');
        new DeliveryOperationsAdminQueryRequestDTO(
            completedAfter: new DateTimeImmutable('2023-01-02'),
            completedBefore: new DateTimeImmutable('2023-01-01')
        );
    }

    public function testItThrowsOnInvalidOccurredRange(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query date range: occurred_at');
        new DeliveryOperationsAdminQueryRequestDTO(
            after: new DateTimeImmutable('2023-01-02'),
            before: new DateTimeImmutable('2023-01-01')
        );
    }

    public function testItThrowsOnTooManyMetadataFilters(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query metadata filter count');
        new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: [
            '$.a' => 1, '$.b' => 2, '$.c' => 3, '$.d' => 4, '$.e' => 5, '$.f' => 6
        ]);
    }

    public function testItThrowsOnInvalidMetadataPath(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query metadata path or shape');
        new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: ['a' => 1]); // missing $.
    }

    public function testItThrowsOnInvalidMetadataValue(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query metadata value type');
        /** @phpstan-ignore argument.type */
        new DeliveryOperationsAdminQueryRequestDTO(metadataFilters: ['$.a' => ['array']]);
    }

    public function testItThrowsOnInvalidNullStateKey(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query null-state input: invalidKey');
        new DeliveryOperationsAdminQueryRequestDTO(nullStateFilters: ['invalidKey' => true]);
    }

    public function testItThrowsOnInvalidNullStateValue(): void
    {
        $this->expectException(DeliveryOperationsAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid DeliveryOperations Admin Query null-state input: actorId');
        /** @phpstan-ignore argument.type */
        new DeliveryOperationsAdminQueryRequestDTO(nullStateFilters: ['actorId' => 'yes']);
    }

    public function testItNormalizesInvalidSortToNull(): void
    {
        $dto = new DeliveryOperationsAdminQueryRequestDTO(sortBy: 'invalid');
        $this->assertNull($dto->sortBy);

        $dto2 = new DeliveryOperationsAdminQueryRequestDTO(sortDirection: 'foo');
        $this->assertNull($dto2->sortDirection);
    }
}
