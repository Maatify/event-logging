<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Infrastructure\Mysql;

use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsRowMapper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SecuritySignalsRowMapperTest extends TestCase
{
    public function testEveryFieldMapsNormallyAndNumericIdsAreCasted(): void
    {
        $dto = (new SecuritySignalsRowMapper())->map($this->fullRow());

        $this->assertSame(5, $dto->id);
        $this->assertSame('evt', $dto->eventId);
        $this->assertSame('raw actor', $dto->actorType);
        $this->assertSame(10, $dto->actorId);
        $this->assertSame('login_failed', $dto->signalType);
        $this->assertSame('raw severity', $dto->severity);
        $this->assertSame('corr', $dto->correlationId);
        $this->assertSame('req', $dto->requestId);
        $this->assertSame('route', $dto->routeName);
        $this->assertSame('127.0.0.1', $dto->ipAddress);
        $this->assertSame('agent', $dto->userAgent);
        $this->assertSame(['ok' => true], $dto->metadata);
        $this->assertSame('2024-01-01 12:00:00.000000', $dto->occurredAt->format('Y-m-d H:i:s.u'));
    }

    public function testScalarFieldFallbacks(): void
    {
        $dto = (new SecuritySignalsRowMapper())->map([
            'id' => 'bad',
            'event_id' => [],
            'actor_type' => [],
            'actor_id' => 'bad',
            'signal_type' => [],
            'severity' => [],
            'correlation_id' => [],
            'request_id' => [],
            'route_name' => [],
            'ip_address' => [],
            'user_agent' => [],
            'occurred_at' => '2024-01-01',
        ]);

        $this->assertSame(0, $dto->id);
        $this->assertSame('', $dto->eventId);
        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertSame('', $dto->signalType);
        $this->assertSame('', $dto->severity);
        $this->assertNull($dto->correlationId);
        $this->assertNull($dto->requestId);
        $this->assertNull($dto->routeName);
        $this->assertNull($dto->ipAddress);
        $this->assertNull($dto->userAgent);
    }

    public function testMissingAndNonStringDateFallbackToUnixEpochText(): void
    {
        $mapper = new SecuritySignalsRowMapper();

        $this->assertSame('1970-01-01 00:00:00.000000', $mapper->map([])->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('1970-01-01 00:00:00.000000', $mapper->map(['occurred_at' => []])->occurredAt->format('Y-m-d H:i:s.u'));
    }

    public function testInvalidPersistedDateTextThrows(): void
    {
        $this->expectException(\Exception::class);
        (new SecuritySignalsRowMapper())->map(['occurred_at' => 'not a date']);
    }

    public function testAssociativeMetadataMapsAndFallbacksReturnNull(): void
    {
        $mapper = new SecuritySignalsRowMapper();

        $this->assertSame(['key' => ['nested' => true]], $mapper->map([
            'metadata' => '{"key":{"nested":true}}',
            'occurred_at' => '2024-01-01',
        ])->metadata);
        $this->assertNull($mapper->map(['occurred_at' => '2024-01-01'])->metadata);
        $this->assertNull($mapper->map(['metadata' => 123, 'occurred_at' => '2024-01-01'])->metadata);
        $this->assertNull($mapper->map(['metadata' => '', 'occurred_at' => '2024-01-01'])->metadata);
        $this->assertNull($mapper->map(['metadata' => '{bad', 'occurred_at' => '2024-01-01'])->metadata);
        $this->assertNull($mapper->map(['metadata' => '"scalar"', 'occurred_at' => '2024-01-01'])->metadata);
        $this->assertNull($mapper->map(['metadata' => '["list"]', 'occurred_at' => '2024-01-01'])->metadata);
    }

    public function testMapperHasNoConstructorOrPolicyDependency(): void
    {
        $reflection = new ReflectionClass(SecuritySignalsRowMapper::class);
        $this->assertFalse($reflection->hasMethod('__construct'));
        $this->assertSame([], $reflection->getProperties());
    }

    /** @return array<string, mixed> */
    private function fullRow(): array
    {
        return [
            'id' => '5',
            'event_id' => 'evt',
            'actor_type' => 'raw actor',
            'actor_id' => '10',
            'signal_type' => 'login_failed',
            'severity' => 'raw severity',
            'correlation_id' => 'corr',
            'request_id' => 'req',
            'route_name' => 'route',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'agent',
            'metadata' => '{"ok":true}',
            'occurred_at' => '2024-01-01 12:00:00.000000',
        ];
    }
}
