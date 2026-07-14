<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\Infrastructure\Mysql;

use Exception;
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTracePolicyInterface;
use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeEnum;
use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeInterface;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceRowMapper;
use Maatify\EventLogging\BehaviorTrace\Recorder\BehaviorTraceDefaultPolicy;
use PHPUnit\Framework\TestCase;

final class BehaviorTraceRowMapperTest extends TestCase
{
    public function testMapsFullRowAndCastsNumericIds(): void
    {
        $dto = (new BehaviorTraceRowMapper(new BehaviorTraceDefaultPolicy()))->map([
            'id' => '5',
            'event_id' => 'evt',
            'actor_type' => 'user',
            'actor_id' => '10',
            'action' => 'view',
            'entity_type' => 'doc',
            'entity_id' => '20',
            'correlation_id' => 'corr',
            'request_id' => 'req',
            'route_name' => 'route',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'agent',
            'metadata' => '{"ok":true}',
            'occurred_at' => '2024-01-01 12:00:00.000000',
        ]);

        $this->assertSame(5, $dto->id);
        $this->assertSame('evt', $dto->eventId);
        $this->assertSame('view', $dto->action);
        $this->assertSame(10, $dto->context->actorId);
        $this->assertSame(20, $dto->entityId);
        $this->assertSame('USER', $dto->context->actorType->value());
        $this->assertSame(['ok' => true], $dto->metadata);
        $this->assertSame('2024-01-01 12:00:00.000000', $dto->context->occurredAt->format('Y-m-d H:i:s.u'));
    }

    public function testPreservesPrimitiveFallbacksForInvalidValues(): void
    {
        $dto = (new BehaviorTraceRowMapper(new BehaviorTraceDefaultPolicy()))->map([
            'id' => 'bad',
            'actor_type' => [],
            'actor_id' => 'bad',
            'entity_id' => 'bad',
            'metadata' => '{bad',
            'occurred_at' => [],
        ]);

        $this->assertSame(0, $dto->id);
        $this->assertSame('', $dto->eventId);
        $this->assertSame('unknown', $dto->action);
        $this->assertSame('ANONYMOUS', $dto->context->actorType->value());
        $this->assertNull($dto->context->actorId);
        $this->assertNull($dto->entityType);
        $this->assertNull($dto->entityId);
        $this->assertNull($dto->metadata);
        $this->assertSame('1970-01-01 00:00:00.000000', $dto->context->occurredAt->format('Y-m-d H:i:s.u'));
    }

    public function testCustomPolicyIsUsedAndPolicyExceptionsBubbleRaw(): void
    {
        $policy = new class implements BehaviorTracePolicyInterface {
            public function normalizeActorType(string|BehaviorTraceActorTypeInterface $actorType): BehaviorTraceActorTypeInterface
            {
                return new class implements BehaviorTraceActorTypeInterface {
                    public function value(): string
                    {
                        return 'CUSTOM';
                    }
                };
            }

            public function validateMetadataSize(string $json): bool
            {
                return true;
            }
        };

        $dto = (new BehaviorTraceRowMapper($policy))->map(['occurred_at' => '2024-01-01 00:00:00']);
        $this->assertSame('CUSTOM', $dto->context->actorType->value());

        $throwingPolicy = new class implements BehaviorTracePolicyInterface {
            public function normalizeActorType(string|BehaviorTraceActorTypeInterface $actorType): BehaviorTraceActorTypeInterface
            {
                throw new Exception('policy failed');
            }

            public function validateMetadataSize(string $json): bool
            {
                return true;
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('policy failed');
        (new BehaviorTraceRowMapper($throwingPolicy))->map([]);
    }
}
