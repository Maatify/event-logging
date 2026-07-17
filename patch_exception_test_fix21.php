<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

$content = preg_replace('/    public function testInvalidPaginationQueryExceptionIsWrapped\(\): void.*?    public function testExistingStorageExceptionIsNotRewrapped\(\): void/s', '
    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmtTotal = $this->createMock(PDOStatement::class);

        $pdo->method("prepare")->willReturn($stmtTotal);

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // PdoPaginator is final, we cannot mock it.
        // How to trigger InvalidPaginationQueryException natively?
        // Let\'s trigger it by modifying descriptor builder to return invalid sql or params?
        // We can mock DescriptorBuilder! It is instantiated internally though.
        // What about just replacing the Paginate Request with invalid sorts? We saw it did not throw.
        // Wait, PaginationConfig validates maxPerPage < minPerPage, maybe we can reflection inject a bad config?
        // createPaginationConfig is private.

        // Wait! The repository catches (InvalidPaginationConfigurationException | InvalidPaginationQueryException)
        // If we inject a DescriptorBuilder that throws it!
        $reflection = new ReflectionClass($repository);
        $descProp = $reflection->getProperty("descriptorBuilder");
        $descProp->setAccessible(true);

        $mockDesc = $this->createMock(\Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\Pagination\AuthoritativeAuditAdminQueryDescriptorBuilder::class);
        $mockDesc->method("build")->willThrowException(new \Maatify\Persistence\Exception\InvalidPaginationQueryException("Test desc builder throw"));

        $descProp->setValue($repository, $mockDesc);

        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
    }

    public function testExistingStorageExceptionIsNotRewrapped(): void', $content);

file_put_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php', $content);
