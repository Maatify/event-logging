<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression\AuditTrail;

use Maatify\EventLogging\AuditTrail\Contract\AuditTrailQueryInterface;
use Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryDTO;
use Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailQueryMysqlRepository;
use Maatify\EventLogging\Tests\Support\FakePdo;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AuditTrailQueryMysqlRepositoryRegressionTest extends TestCase
{
    public function testPrimitiveInterfaceAndRepositoryConstructorRemainUnchanged(): void
    {
        $interface = new ReflectionMethod(AuditTrailQueryInterface::class, 'find');
        $this->assertSame('find', $interface->getName());
        $this->assertSame('array', (string) $interface->getReturnType());
        $this->assertSame(AuditTrailQueryDTO::class, (string) $interface->getParameters()[0]->getType());

        $constructor = new ReflectionMethod(AuditTrailQueryMysqlRepository::class, '__construct');
        $this->assertSame('pdo', $constructor->getParameters()[0]->getName());
        $this->assertSame('PDO', (string) $constructor->getParameters()[0]->getType());
    }

    public function testPrimitiveQueryDtoConstructorAndCursorFieldsRemainUnchanged(): void
    {
        $constructor = new ReflectionMethod(AuditTrailQueryDTO::class, '__construct');
        $this->assertSame([
            'actorType',
            'actorId',
            'eventKey',
            'entityType',
            'entityId',
            'subjectType',
            'subjectId',
            'requestId',
            'correlationId',
            'after',
            'before',
            'cursorOccurredAt',
            'cursorId',
            'limit',
        ], array_map(static fn (\ReflectionParameter $parameter): string => $parameter->getName(), $constructor->getParameters()));

        $query = new AuditTrailQueryDTO();
        $this->assertNull($query->cursorOccurredAt);
        $this->assertNull($query->cursorId);
        $this->assertSame(50, $query->limit);
    }

    public function testPrimitiveSqlOrderingFiltersLimitAndStorageExceptionRemainUnchanged(): void
    {
        $pdo = new FakePdo();
        $repository = new AuditTrailQueryMysqlRepository($pdo);
        $repository->find(new AuditTrailQueryDTO(limit: 25));

        $this->assertNotNull($pdo->lastStatement);
        $this->assertStringContainsString('SELECT *', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('ORDER BY occurred_at DESC, id DESC', $pdo->lastStatement->queryString);
        $this->assertStringContainsString('LIMIT 25', $pdo->lastStatement->queryString);
        $this->assertInstanceOf(\Throwable::class, new AuditTrailStorageException());
    }

    public function testSupersededAuditTrailWrapperArtifactsAreAbsent(): void
    {
        $this->assertFalse(class_exists('Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryCursorDTO'));
        $this->assertFalse(class_exists('Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryPageDTO'));
        $this->assertFalse(interface_exists('Maatify\EventLogging\AuditTrail\Contract\AuditTrailPaginatedQueryInterface'));
        $this->assertFalse(class_exists('Maatify\EventLogging\AuditTrail\Service\AuditTrailPaginatedQueryService'));
    }

    public function testNoOutOfScopeAdminQuerySurfaceWasAdded(): void
    {
        $this->assertFalse(interface_exists('Maatify\EventLogging\Contract\AdminQueryInterface'));
        $this->assertFalse(class_exists('Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceAdminQueryMysqlRepository'));
        $this->assertFalse(class_exists('Maatify\EventLogging\Http\AuditTrailAdminQueryController'));
        $this->assertFileExists(__DIR__ . '/../../../src/AuditTrail/Database/schema.maa_event_logging_audit_trail.sql');

        $schema = (string) file_get_contents(__DIR__ . '/../../../src/AuditTrail/Database/schema.maa_event_logging_audit_trail.sql');
        $this->assertStringContainsString('CREATE TABLE maa_event_logging_audit_trail', $schema);
    }
}
