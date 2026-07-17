<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

$content = preg_replace(
'/    public function testExistingStorageExceptionIsNotRewrapped\(\): void\s+\{\s+\$pdo = \$this->createMock\(PDO::class\);\s+\$repository = new AuthoritativeAuditAdminQueryMysqlRepository\(\$pdo\);\s+\$reflection = new ReflectionClass\(\$repository\);\s+\$mapRowMethod = \$reflection->getMethod\("mapRow"\);\s+\$mapRowMethod->setAccessible\(true\);\s+\$originalException = new AuthoritativeAuditStorageException\("Original storage exception"\);\s+\$this->expectException\(AuthoritativeAuditStorageException::class\);\s+\$this->expectExceptionMessage\("Original storage exception"\);\s+\$this->markTestIncomplete\("Need to see RowMapper and AdminQueryRepository code to properly throw this exception."\);\s+\}/',
'    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $pdo->method("prepare")->willReturn($stmt);

        // Paginator calls fetch for totals, return 1
        $stmt->method("fetch")->willReturn(["total" => 1, "filtered" => 1]);

        // Paginator calls fetchAll for data
        $stmt->method("fetchAll")->willReturn([
            ["event_id" => "mock-event"] // We need a way for the mapper to throw AuthoritativeAuditStorageException. We mock RowMapper via reflection or inject it? It\'s private. Let\'s see RowMapper.
        ]);

        // Let us mock the PDO Paginator to not deal with the mapping, no we must deal with mapping.
        // Or we use reflection to replace the private $mapper with a mock mapper that throws AuthoritativeAuditStorageException.
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);
        $reflection = new ReflectionClass($repository);
        $mapperProp = $reflection->getProperty("mapper");
        $mapperProp->setAccessible(true);

        $mockMapper = $this->createMock(\Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditRowMapper::class);
        $originalException = new AuthoritativeAuditStorageException("Original storage exception");
        $mockMapper->method("map")->willThrowException($originalException);

        $mapperProp->setValue($repository, $mockMapper);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage("Original storage exception");

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
        } catch (AuthoritativeAuditStorageException $e) {
            $this->assertSame($originalException, $e);
            throw $e;
        }
    }', $content);

file_put_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php', $content);
