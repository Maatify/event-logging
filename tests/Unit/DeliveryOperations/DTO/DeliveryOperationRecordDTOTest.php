<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\DTO;

use DateTimeImmutable;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationRecordDTO;
use PHPUnit\Framework\TestCase;

class DeliveryOperationRecordDTOTest extends TestCase
{
    public function testSerialization(): void
    {
        $sched = new DateTimeImmutable('2023-01-01T10:00:00+00:00');
        $comp = new DateTimeImmutable('2023-01-01T10:01:00+00:00');
        $occ = new DateTimeImmutable('2023-01-01T10:01:01+00:00');

        $dto = new DeliveryOperationRecordDTO(
            'evt-1',
            'EMAIL',
            'SEND',
            'SYSTEM',
            123,
            'USER',
            456,
            'SUCCESS',
            1,
            $sched,
            $comp,
            'corr',
            'req',
            'smtp',
            'msg',
            'err',
            'msg_err',
            ['foo' => 'bar'],
            $occ
        );

        $expected = [
            'eventId' => 'evt-1',
            'channel' => 'EMAIL',
            'operationType' => 'SEND',
            'actorType' => 'SYSTEM',
            'actorId' => 123,
            'targetType' => 'USER',
            'targetId' => 456,
            'status' => 'SUCCESS',
            'attemptNo' => 1,
            'scheduledAt' => $sched->format(DATE_ATOM),
            'completedAt' => $comp->format(DATE_ATOM),
            'correlationId' => 'corr',
            'requestId' => 'req',
            'provider' => 'smtp',
            'providerMessageId' => 'msg',
            'errorCode' => 'err',
            'errorMessage' => 'msg_err',
            'metadata' => ['foo' => 'bar'],
            'occurredAt' => $occ->format(DATE_ATOM),
        ];

        $this->assertSame($expected, $dto->jsonSerialize());
    }

    public function testNullOptionals(): void
    {
        $occ = new DateTimeImmutable('2023-01-01T10:01:01+00:00');

        $dto = new DeliveryOperationRecordDTO(
            'evt-1',
            'SMS',
            'SEND',
            null,
            null,
            null,
            null,
            'PENDING',
            0,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $occ
        );

        $this->assertNull($dto->actorType);
        $this->assertNull($dto->actorId);
        $this->assertNull($dto->targetType);
        $this->assertNull($dto->targetId);
        $this->assertNull($dto->scheduledAt);
        $this->assertNull($dto->completedAt);
    }
}
