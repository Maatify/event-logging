<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

$content = preg_replace(
'/    public function testExistingStorageExceptionIsNotRewrapped\(\): void.*?\}\s*\} catch.*?\}/s',
'    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $reflection = new ReflectionClass($repository);
        $paginatorProp = $reflection->getProperty("paginator");
        $paginatorProp->setAccessible(true);

        $mockPaginator = $this->createMock(\Maatify\Persistence\Pdo\Pagination\PdoPaginator::class);

        $originalException = new AuthoritativeAuditStorageException("Original storage exception");

        // When paginator is called, we will execute the callback to simulate throwing the exception
        $mockPaginator->method("paginate")->willThrowException($originalException);

        $paginatorProp->setValue($repository, $mockPaginator);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage("Original storage exception");

        try {
            $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
        } catch (AuthoritativeAuditStorageException $e) {
            $this->assertSame($originalException, $e);
            throw $e;
        }
    }
}', $content);

file_put_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php', $content);
