<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

// Fix testInvalidPaginationQueryExceptionIsWrapped
$content = preg_replace(
'/    public function testInvalidPaginationQueryExceptionIsWrapped\(\): void.*?    \}/s',
'    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // Passing an invalid page number (e.g. 0 or less) to trigger InvalidPaginationConfigurationException / InvalidPaginationQueryException
        // from inside PageRequest or PdoPaginator itself
        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(page: 0));
    }', $content);

// Fix testExistingStorageExceptionIsNotRewrapped
$content = preg_replace(
'/    public function testExistingStorageExceptionIsNotRewrapped\(\): void.*?    \}/s',
'    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        // PdoPaginator executes two queries: count and data
        $pdo->method("prepare")->willReturn($stmt);

        // total count query mock
        $stmt->expects($this->exactly(2))->method("execute")->willReturn(true);
        $stmt->expects($this->exactly(2))->method("fetch")->willReturnOnConsecutiveCalls(
            ["total" => 1, "filtered" => 1],
            ["event_id" => "mock-event"], // the row data which mapper will fail on
            false
        );

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        // Inject a mocked RowMapper that throws an existing AuthoritativeAuditStorageException
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
