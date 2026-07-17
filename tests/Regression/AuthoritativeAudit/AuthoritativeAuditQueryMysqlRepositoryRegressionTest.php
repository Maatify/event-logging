<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression\AuthoritativeAudit;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;

final class AuthoritativeAuditQueryMysqlRepositoryRegressionTest extends TestCase
{
    public function testFindWithCursorGeneratesCorrectSql(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $stmt = $this->createMock(\PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql) {
                $this->assertStringContainsString('SELECT * FROM maa_event_logging_authoritative_audit_log', $sql);
                $this->assertStringContainsString('ORDER BY occurred_at DESC, id DESC', $sql);
                $this->assertStringContainsString('(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))', $sql);
                return true;
            }))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with([
                'cursor_at_before' => '2024-01-01 00:00:00.000000',
                'cursor_at_equal' => '2024-01-01 00:00:00.000000',
                'cursor_id' => 100,
            ])
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $repository = new \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository($pdo);
        $query = new \Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO(
            cursorOccurredAt: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC')),
            cursorId: 100
        );

        $repository->find($query);
    }


    public function testPrimitiveInterfaceSignatureAndRepositoryConstructor(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $repository = new \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository($pdo);
        $this->assertInstanceOf(\Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository::class, $repository);

        $reflection = new \ReflectionClass(\Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        /** @var \ReflectionNamedType $type */
        $type = $params[0]->getType();
        $this->assertSame(\PDO::class, $type->getName());
    }

    public function testExactAuthoritativeAuditQueryDTOConstructorParametersOrderAndDefaults(): void
    {
        $reflection = new \ReflectionClass(\Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();

        $this->assertCount(11, $params);

        $expectedParams = [
            'after' => ['type' => \DateTimeImmutable::class, 'null' => true, 'default' => null],
            'before' => ['type' => \DateTimeImmutable::class, 'null' => true, 'default' => null],
            'actorType' => ['type' => 'string', 'null' => true, 'default' => null],
            'actorId' => ['type' => 'int', 'null' => true, 'default' => null],
            'targetType' => ['type' => 'string', 'null' => true, 'default' => null],
            'targetId' => ['type' => 'int', 'null' => true, 'default' => null],
            'action' => ['type' => 'string', 'null' => true, 'default' => null],
            'correlationId' => ['type' => 'string', 'null' => true, 'default' => null],
            'cursorOccurredAt' => ['type' => \DateTimeImmutable::class, 'null' => true, 'default' => null],
            'cursorId' => ['type' => 'int', 'null' => true, 'default' => null],
            'limit' => ['type' => 'int', 'null' => false, 'default' => 50],
        ];

        $i = 0;
        foreach ($expectedParams as $name => $expected) {
            $this->assertSame($name, $params[$i]->getName());
            /** @var \ReflectionNamedType $type */
            $type = $params[$i]->getType();
            $this->assertSame($expected['type'], $type->getName());
            $this->assertSame($expected['null'], $params[$i]->allowsNull());
            $this->assertTrue($params[$i]->isDefaultValueAvailable());
            $this->assertSame($expected['default'], $params[$i]->getDefaultValue());
            $i++;
        }
    }

    public function testProtectedSerializationBehavior(): void
    {
        $dto = new \Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO(
            cursorOccurredAt: new \DateTimeImmutable('2024-01-01 00:00:00', new \DateTimeZone('UTC')),
            cursorId: 100,
            limit: 50
        );

        $reflection = new \ReflectionMethod(\Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO::class, 'jsonSerialize');
        $this->assertTrue($reflection->isPublic());

        $serialized = $dto->jsonSerialize();
        $this->assertSame('2024-01-01T00:00:00+00:00', $serialized['cursorOccurredAt']);
        $this->assertSame(100, $serialized['cursorId']);
        $this->assertSame(50, $serialized['limit']);
        $this->assertArrayHasKey('after', $serialized);
        $this->assertArrayHasKey('before', $serialized);
        $this->assertArrayHasKey('actorType', $serialized);
        $this->assertArrayHasKey('actorId', $serialized);
        $this->assertArrayHasKey('targetType', $serialized);
        $this->assertArrayHasKey('targetId', $serialized);
        $this->assertArrayHasKey('action', $serialized);
        $this->assertArrayHasKey('correlationId', $serialized);
    }

    public function testSupersededFilesAreAbsent(): void
    {
        $files = [
            'Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryCursorDTO',
            'Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryPageDTO',
            'Maatify\EventLogging\AuthoritativeAudit\Query\AuthoritativeAuditPaginatedQueryInterface',
            'Maatify\EventLogging\AuthoritativeAudit\Query\AuthoritativeAuditPaginatedQueryService',
            'Maatify\EventLogging\AuthoritativeAudit\Query\AuthoritativeAuditCursorQueryInterface',
            'Maatify\EventLogging\AuthoritativeAudit\Query\AuthoritativeAuditCursorQueryService',
            'Maatify\EventLogging\AuthoritativeAudit\Query\AuthoritativeAuditPageQueryInterface',
        ];

        foreach ($files as $file) {

            $this->assertFalse(class_exists(str_replace('__XXX__', '', $file . '__XXX__')), "Class $file should not exist");

            $this->assertFalse(interface_exists(str_replace('__XXX__', '', $file . '__XXX__')), "Interface $file should not exist");
        }
    }

    public function testFindWithZeroOrNegativeLimitClampsToOne(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $stmt = $this->createMock(\PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $repository = new \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository($pdo);
        $query = new \Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO(
            limit: -5
        );

        $repository->find($query);
    }

    public function testExceptionMappingAndMessages(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willThrowException(new \PDOException('Connection failed'));

        $repository = new \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository($pdo);
        $query = new \Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO();

        try {
            $repository->find($query);
            $this->fail('Expected exception was not thrown');
        } catch (\Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException $e) {
            $this->assertSame('Failed to query AuthoritativeAudit records: Connection failed', $e->getMessage());
            $this->assertInstanceOf(\PDOException::class, $e->getPrevious());
        }
    }

    public function testCorruptJsonMapsToNull(): void
    {
        $pdo = $this->createMock(\PDO::class);
        $stmt = $this->createMock(\PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                [
                    'id' => '1',
                    'event_id' => 'event-1',
                    'action' => 'test_action',
                    'changes' => 'invalid-json',
                    'occurred_at' => '2024-01-01 00:00:00.123456'
                ]
            ]);

        $repository = new \Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository($pdo);
        $query = new \Maatify\EventLogging\AuthoritativeAudit\DTO\AuthoritativeAuditQueryDTO();

        $result = $repository->find($query);
        $this->assertCount(1, $result);
        $this->assertNull($result[0]->changes);
        $this->assertSame('2024-01-01 00:00:00.123456', $result[0]->occurredAt->format('Y-m-d H:i:s.u'));
        $this->assertSame('UTC', $result[0]->occurredAt->getTimezone()->getName());
    }
}
