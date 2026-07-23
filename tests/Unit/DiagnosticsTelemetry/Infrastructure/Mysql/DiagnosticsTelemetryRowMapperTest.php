<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\Infrastructure\Mysql;

use DateTimeZone;
use Exception;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryPolicyInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryRowMapper;
use Maatify\EventLogging\DiagnosticsTelemetry\Recorder\DiagnosticsTelemetryDefaultPolicy;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryRowMapper
 */
final class DiagnosticsTelemetryRowMapperTest extends TestCase
{
    private DiagnosticsTelemetryRowMapper $mapper;
    private DiagnosticsTelemetryPolicyInterface $policy;

    protected function setUp(): void
    {
        $this->policy = new DiagnosticsTelemetryDefaultPolicy();
        $this->mapper = new DiagnosticsTelemetryRowMapper($this->policy);
    }

    public function testItIsInternalAndFinal(): void
    {
        $reflector = new ReflectionClass(DiagnosticsTelemetryRowMapper::class);

        $this->assertTrue($reflector->isFinal());
        $this->assertStringContainsString('@internal', (string) $reflector->getDocComment());
    }

    public function testItMapsFullValidRow(): void
    {
        $row = [
            'id' => '123',
            'event_id' => 'uuid-1',
            'event_key' => 'system_start',
            'severity' => 'WARNING',
            'actor_type' => 'sys',
            'actor_id' => '42',
            'correlation_id' => 'corr-1',
            'request_id' => 'req-1',
            'route_name' => 'api.start',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test-agent',
            'duration_ms' => '500',
            'metadata' => '{"foo": "bar", "num": 1}',
            'occurred_at' => '2023-01-01 10:00:00.123456',
        ];

        $dto = $this->mapper->map($row);

        $this->assertSame(123, $dto->id);
        $this->assertSame('uuid-1', $dto->eventId);
        $this->assertSame('system_start', $dto->eventKey);
        $this->assertSame('WARNING', $dto->severity->value());
        $this->assertSame('SYS', $dto->context->actorType->value()); // Default policy upcases
        $this->assertSame(42, $dto->context->actorId);
        $this->assertSame('corr-1', $dto->context->correlationId);
        $this->assertSame('req-1', $dto->context->requestId);
        $this->assertSame('api.start', $dto->context->routeName);
        $this->assertSame('127.0.0.1', $dto->context->ipAddress);
        $this->assertSame('test-agent', $dto->context->userAgent);
        $this->assertSame(500, $dto->durationMs);
        $this->assertSame(['foo' => 'bar', 'num' => 1], $dto->metadata);

        $this->assertSame('2023-01-01 10:00:00.123456', $dto->context->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $dto->context->occurredAt->getTimezone()->getName());
    }

    public function testItHandlesNumericConversionsFromBothIntegersAndStrings(): void
    {
        $row = [
            'id' => 123,
            'actor_id' => '42',
            'duration_ms' => 500,
        ];

        $dto = $this->mapper->map($row);

        $this->assertSame(123, $dto->id);
        $this->assertSame(42, $dto->context->actorId);
        $this->assertSame(500, $dto->durationMs);

        $row2 = [
            'id' => '123',
            'actor_id' => 42,
            'duration_ms' => '500',
        ];
        $dto2 = $this->mapper->map($row2);
        $this->assertSame(123, $dto2->id);
        $this->assertSame(42, $dto2->context->actorId);
        $this->assertSame(500, $dto2->durationMs);
    }

    public function testItHandlesEmptyRowWithMissingFieldsAndReturnsFallbacks(): void
    {
        $dto = $this->mapper->map([]);

        $this->assertSame(0, $dto->id);
        $this->assertSame('', $dto->eventId);
        $this->assertSame('unknown', $dto->eventKey);
        $this->assertSame('INFO', $dto->severity->value());
        $this->assertSame('ANONYMOUS', $dto->context->actorType->value());
        $this->assertNull($dto->context->actorId);
        $this->assertNull($dto->context->correlationId);
        $this->assertNull($dto->context->requestId);
        $this->assertNull($dto->context->routeName);
        $this->assertNull($dto->context->ipAddress);
        $this->assertNull($dto->context->userAgent);
        $this->assertNull($dto->durationMs);
        $this->assertNull($dto->metadata);
        $this->assertSame('1970-01-01 00:00:00.000000', $dto->context->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $dto->context->occurredAt->getTimezone()->getName());
    }

    public function testItHandlesMissingAndWrongTypeFallbacks(): void
    {
        $row = [
            'id' => 'not-numeric',
            'event_id' => 123, // not string
            'event_key' => 123,
            'severity' => 123,
            'actor_type' => 123,
            'actor_id' => 'not-numeric',
            'correlation_id' => 123,
            'request_id' => 123,
            'route_name' => 123,
            'ip_address' => 123,
            'user_agent' => 123,
            'duration_ms' => 'not-numeric',
            'metadata' => 123,
            'occurred_at' => null,
        ];

        $dto = $this->mapper->map($row);

        $this->assertSame(0, $dto->id);
        $this->assertSame('', $dto->eventId);
        $this->assertSame('unknown', $dto->eventKey);
        $this->assertSame('INFO', $dto->severity->value());
        $this->assertSame('ANONYMOUS', $dto->context->actorType->value());
        $this->assertNull($dto->context->actorId);
        $this->assertNull($dto->context->correlationId);
        $this->assertNull($dto->context->requestId);
        $this->assertNull($dto->context->routeName);
        $this->assertNull($dto->context->ipAddress);
        $this->assertNull($dto->context->userAgent);
        $this->assertNull($dto->durationMs);
        $this->assertNull($dto->metadata);

        $this->assertSame('1970-01-01 00:00:00.000000', $dto->context->occurredAt->format('Y-m-d H:i:s.u'));
    }

    /**
     * @dataProvider invalidMetadataProvider
     */
    public function testItFallsBackToNullForInvalidMetadata(mixed $metadataInput): void
    {
        $row = ['metadata' => $metadataInput];
        $dto = $this->mapper->map($row);
        $this->assertNull($dto->metadata);
    }

    /**
     * @return array<int, array{mixed}>
     */
    public static function invalidMetadataProvider(): array
    {
        return [
            [''],
            ['not-json'],
            ['"scalar"'],
            ['123'],
            [123],
        ];
    }

    public function testItAcceptsNumericKeyArraysAsValidMetadata(): void
    {
        // Primitive find logic accepts numeric arrays; we must preserve it
        $row = ['metadata' => '[1, 2, 3]'];
        $dto = $this->mapper->map($row);
        $this->assertSame([1, 2, 3], $dto->metadata);
    }

    public function testItAllowsEmptyAssociativeArrayMetadata(): void
    {
        $row = ['metadata' => '{}'];
        $dto = $this->mapper->map($row);
        $this->assertSame([], $dto->metadata);
    }

    public function testItThrowsOnInvalidPersistedDateText(): void
    {
        $row = ['occurred_at' => 'invalid-date-text'];

        $this->expectException(Exception::class);
        $this->mapper->map($row);
    }
}
