<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\Infrastructure\Mysql;

use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsRowMapper;
use PHPUnit\Framework\TestCase;

final class DeliveryOperationsRowMapperTest extends TestCase
{
    private DeliveryOperationsRowMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DeliveryOperationsRowMapper();
    }

    public function testItMapsCompleteRow(): void
    {
        $row = [
            'id' => '123',
            'event_id' => 'evt-1',
            'channel' => 'chan-1',
            'operation_type' => 'op-1',
            'actor_type' => 'act-1',
            'actor_id' => '42',
            'target_type' => 'tar-1',
            'target_id' => '43',
            'status' => 'stat-1',
            'attempt_no' => '2',
            'scheduled_at' => '2023-01-01 10:00:00.000000',
            'completed_at' => '2023-01-02 10:00:00.000000',
            'correlation_id' => 'cor-1',
            'request_id' => 'req-1',
            'provider' => 'prov-1',
            'provider_message_id' => 'pmid-1',
            'error_code' => 'err-1',
            'error_message' => 'err-msg',
            'metadata' => '{"foo": "bar"}',
            'occurred_at' => '2023-01-03 10:00:00.000000',
        ];

        $dto = $this->mapper->map($row);

        $this->assertEquals(123, $dto->id);
        $this->assertEquals('evt-1', $dto->eventId);
        $this->assertEquals('chan-1', $dto->channel);
        $this->assertEquals('op-1', $dto->operationType);
        $this->assertEquals('act-1', $dto->actorType);
        $this->assertEquals(42, $dto->actorId);
        $this->assertEquals('tar-1', $dto->targetType);
        $this->assertEquals(43, $dto->targetId);
        $this->assertEquals('stat-1', $dto->status);
        $this->assertEquals(2, $dto->attemptNo);
        $this->assertEquals('2023-01-01T10:00:00+00:00', $dto->scheduledAt?->format(\DATE_ATOM));
        $this->assertEquals('2023-01-02T10:00:00+00:00', $dto->completedAt?->format(\DATE_ATOM));
        $this->assertEquals('cor-1', $dto->correlationId);
        $this->assertEquals('req-1', $dto->requestId);
        $this->assertEquals('prov-1', $dto->provider);
        $this->assertEquals('pmid-1', $dto->providerMessageId);
        $this->assertEquals('err-1', $dto->errorCode);
        $this->assertEquals('err-msg', $dto->errorMessage);
        $this->assertEquals(['foo' => 'bar'], $dto->metadata);
        $this->assertEquals('2023-01-03T10:00:00+00:00', $dto->occurredAt->format(\DATE_ATOM));
    }

    public function testItMapsMissingOrInvalidValuesToFallbacks(): void
    {
        $dto = $this->mapper->map([]);

        $this->assertEquals(0, $dto->id);
        $this->assertEquals('', $dto->eventId);
        $this->assertEquals('', $dto->channel);
        $this->assertEquals('', $dto->operationType);
        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->targetId);
        $this->assertEquals('', $dto->status);
        $this->assertEquals(0, $dto->attemptNo);
        $this->assertNull($dto->scheduledAt);
        $this->assertNull($dto->completedAt);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->requestId);
        $this->assertNull($dto->provider);
        $this->assertNull($dto->providerMessageId);
        $this->assertNull($dto->errorCode);
        $this->assertNull($dto->errorMessage);
        $this->assertNull($dto->metadata);
        $this->assertEquals('1970-01-01T00:00:00+00:00', $dto->occurredAt->format(\DATE_ATOM));
    }

    public function testItMapsInvalidJsonToNull(): void
    {
        $dto = $this->mapper->map([
            'metadata' => 'invalid-json'
        ]);
        $this->assertNull($dto->metadata);
    }

    public function testItMapsEmptyJsonToNull(): void
    {
        $dto = $this->mapper->map([
            'metadata' => ''
        ]);
        $this->assertNull($dto->metadata);
    }

    public function testItMapsScalarJsonToNull(): void
    {
        $dto = $this->mapper->map([
            'metadata' => '"string"'
        ]);
        $this->assertNull($dto->metadata);
    }

    public function testItMapsListJsonToNull(): void
    {
        $dto = $this->mapper->map([
            'metadata' => '[1, 2, 3]'
        ]);
        $this->assertNull($dto->metadata);
    }

    public function testItThrowsOnInvalidOccurredAtDateString(): void
    {
        $this->expectException(\Exception::class);
        $this->mapper->map(['occurred_at' => 'invalid-date']);
    }

    public function testItThrowsOnInvalidScheduledAtDateString(): void
    {
        $this->expectException(\Exception::class);
        $this->mapper->map(['scheduled_at' => 'invalid-date']);
    }

    public function testItThrowsOnInvalidCompletedAtDateString(): void
    {
        $this->expectException(\Exception::class);
        $this->mapper->map(['completed_at' => 'invalid-date']);
    }
}
