<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\Infrastructure\Mysql;

use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailRowMapper;
use PHPUnit\Framework\TestCase;

final class AuditTrailRowMapperTest extends TestCase
{
    public function testMapsFullRowAndCastsNumericIds(): void
    {
        $dto = (new AuditTrailRowMapper())->map([
            'id' => '5',
            'event_id' => 'evt',
            'actor_type' => 'user',
            'actor_id' => '10',
            'event_key' => 'view',
            'entity_type' => 'doc',
            'entity_id' => '20',
            'subject_type' => 'account',
            'subject_id' => '30',
            'referrer_route_name' => 'ref',
            'referrer_path' => '/from',
            'referrer_host' => 'example.test',
            'correlation_id' => 'corr',
            'request_id' => 'req',
            'route_name' => 'route',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'agent',
            'metadata' => '{"ok":true}',
            'occurred_at' => '2024-01-01 12:00:00.000000',
        ]);

        $this->assertSame(5, $dto->id);
        $this->assertSame(10, $dto->actorId);
        $this->assertSame(20, $dto->entityId);
        $this->assertSame(30, $dto->subjectId);
        $this->assertSame(['ok' => true], $dto->metadata);
        $this->assertSame('2024-01-01 12:00:00.000000', $dto->occurredAt->format('Y-m-d H:i:s.u'));
    }

    public function testPreservesPrimitiveFallbacksForInvalidValues(): void
    {
        $dto = (new AuditTrailRowMapper())->map([
            'id' => 'bad',
            'actor_id' => 'bad',
            'entity_id' => null,
            'subject_id' => 'bad',
            'metadata' => '{bad',
            'occurred_at' => [],
        ]);

        $this->assertSame(0, $dto->id);
        $this->assertSame('', $dto->eventId);
        $this->assertSame('', $dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertNull($dto->entityId);
        $this->assertNull($dto->subjectId);
        $this->assertNull($dto->metadata);
    }
}
