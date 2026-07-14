<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailAdminQueryRequestDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailAdminQueryInvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AuditTrailAdminQueryRequestDTOTest extends TestCase
{
    public function testDefaultsNormalizeToNullAndRawPageValuesRemainRaw(): void
    {
        $dto = new AuditTrailAdminQueryRequestDTO(page: ' 02 ', perPage: '300');

        $this->assertNull($dto->actorType);
        $this->assertSame(' 02 ', $dto->page);
        $this->assertSame('300', $dto->perPage);
        $this->assertNull($dto->sortBy);
        $this->assertNull($dto->sortDirection);
    }

    public function testTrimsNullableStringsAndConvertsEmptyStringsToNull(): void
    {
        $dto = new AuditTrailAdminQueryRequestDTO(
            actorType: ' user ',
            eventKey: " \t",
            entityType: ' document ',
            subjectType: '',
            requestId: ' req-1 ',
            correlationId: ' corr-1 '
        );

        $this->assertSame('user', $dto->actorType);
        $this->assertNull($dto->eventKey);
        $this->assertSame('document', $dto->entityType);
        $this->assertNull($dto->subjectType);
        $this->assertSame('req-1', $dto->requestId);
        $this->assertSame('corr-1', $dto->correlationId);
    }

    public function testTypeOnlyFiltersAreValid(): void
    {
        $dto = new AuditTrailAdminQueryRequestDTO(
            actorType: 'user',
            entityType: 'invoice',
            subjectType: 'account'
        );

        $this->assertSame('user', $dto->actorType);
        $this->assertSame('invoice', $dto->entityType);
        $this->assertSame('account', $dto->subjectType);
        $this->assertNull($dto->actorId);
        $this->assertNull($dto->entityId);
        $this->assertNull($dto->subjectId);
    }

    public function testRejectsZeroIds(): void
    {
        $this->expectException(AuditTrailAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuditTrail Admin Query ID: actorId');

        new AuditTrailAdminQueryRequestDTO(actorType: 'user', actorId: 0);
    }

    public function testRejectsActorIdWithoutActorType(): void
    {
        $this->expectException(AuditTrailAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuditTrail Admin Query ID: actorId without actorType');

        new AuditTrailAdminQueryRequestDTO(actorId: 1);
    }

    public function testRejectsEntityIdWithoutEntityType(): void
    {
        $this->expectException(AuditTrailAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuditTrail Admin Query ID: entityId without entityType');

        new AuditTrailAdminQueryRequestDTO(entityId: 1);
    }

    public function testRejectsSubjectIdWithoutSubjectType(): void
    {
        $this->expectException(AuditTrailAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuditTrail Admin Query ID: subjectId without subjectType');

        new AuditTrailAdminQueryRequestDTO(subjectId: 1);
    }

    public function testRejectsOverlongUtf8StringsByCharacterCount(): void
    {
        $this->expectException(AuditTrailAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuditTrail Admin Query length: actorType');

        new AuditTrailAdminQueryRequestDTO(actorType: str_repeat('س', 33));
    }

    public function testRejectsInvalidUtf8Encoding(): void
    {
        $this->expectException(AuditTrailAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuditTrail Admin Query UTF-8 encoding: actorType');

        new AuditTrailAdminQueryRequestDTO(actorType: "\xB1\x31");
    }

    public function testRejectsAfterGreaterThanBeforeButAcceptsEqualBoundaries(): void
    {
        $equal = new DateTimeImmutable('2024-01-01 10:00:00', new DateTimeZone('UTC'));
        $dto = new AuditTrailAdminQueryRequestDTO(after: $equal, before: $equal);
        $this->assertSame($equal, $dto->after);
        $this->assertSame($equal, $dto->before);

        $this->expectException(AuditTrailAdminQueryInvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid AuditTrail Admin Query date range: after must be before or equal to before');
        new AuditTrailAdminQueryRequestDTO(
            after: new DateTimeImmutable('2024-01-02 00:00:00', new DateTimeZone('UTC')),
            before: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC'))
        );
    }

    public function testSortNormalizationAllowsOnlyOccurredAtAndAscDesc(): void
    {
        $valid = new AuditTrailAdminQueryRequestDTO(sortBy: ' occurred_at ', sortDirection: ' asc ');
        $this->assertSame('occurred_at', $valid->sortBy);
        $this->assertSame('ASC', $valid->sortDirection);

        $invalid = new AuditTrailAdminQueryRequestDTO(sortBy: 'id', sortDirection: 'sideways');
        $this->assertNull($invalid->sortBy);
        $this->assertNull($invalid->sortDirection);
    }

    public function testJsonSerializesDatesWithDateAtomAndDoesNotMutateDateObjects(): void
    {
        $after = new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('Africa/Cairo'));
        $before = new DateTimeImmutable('2024-01-01 13:00:00', new DateTimeZone('Africa/Cairo'));

        $dto = new AuditTrailAdminQueryRequestDTO(after: $after, before: $before);

        $this->assertSame($after, $dto->after);
        $this->assertSame('Africa/Cairo', $after->getTimezone()->getName());
        $this->assertSame($after->format(DATE_ATOM), $dto->jsonSerialize()['after']);
        $this->assertSame($before->format(DATE_ATOM), $dto->jsonSerialize()['before']);
    }
}
