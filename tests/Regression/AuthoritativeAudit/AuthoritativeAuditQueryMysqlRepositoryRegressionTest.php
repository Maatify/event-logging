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
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('(occurred_at < :cursor_at_before OR (occurred_at = :cursor_at_equal AND id < :cursor_id))'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $repository = new AuthoritativeAuditQueryMysqlRepository($pdo);
        $query = new AuthoritativeAuditQueryDTO(
            cursorOccurredAt: new DateTimeImmutable('2024-01-01 00:00:00', new DateTimeZone('UTC')),
            cursorId: 100
        );

        $repository->find($query);
    }
}
