<?php
$content = file_get_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php');

$content = preg_replace('/    public function testInvalidPaginationQueryExceptionIsWrapped\(\): void.*?    public function testExistingStorageExceptionIsNotRewrapped\(\): void/s', '
    public function testInvalidPaginationQueryExceptionIsWrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $pdo->method("prepare")->willReturn($stmt);

        // Let\'s just force PdoPaginator to throw PaginationExecutionException ? No, we need InvalidPaginationConfigurationException
        // Wait, PaginationConfig validates maxPerPage < minPerPage in PdoPaginator? No, in PaginationConfig constructor.
        // Wait, the repository passes $request->page directly to PageRequest.
        // PageRequest constructor validates page > 0, throws InvalidPaginationConfigurationException!

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditAdminQueryExecutionException::class);

        // Pass page = 0 to trigger InvalidPaginationConfigurationException in PageRequest constructor
        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO(page: 0));
    }

    public function testExistingStorageExceptionIsNotRewrapped(): void', $content);

$content = preg_replace('/    public function testExistingStorageExceptionIsNotRewrapped\(\): void.*$/s', '
    public function testExistingStorageExceptionIsNotRewrapped(): void
    {
        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $pdo->method("prepare")->willReturn($stmt);

        // PdoPaginator count query
        $stmt->expects($this->exactly(2))->method("execute")->willReturn(true);
        $stmt->expects($this->exactly(2))->method("fetch")->willReturnOnConsecutiveCalls(
            ["total" => 1, "filtered" => 1], // total
            ["event_id" => "mock-event"],    // row to map
            false
        );
        // The callback inside paginate will call $this->mapRow($row)
        // We will make mapper throw by modifying row data to have invalid date!
        // No, invalid date throws regular Exception, which mapRow wraps in AuthoritativeAuditStorageException.
        // Wait! We want to prove that an EXISTING AuthoritativeAuditStorageException is NOT re-wrapped.
        // If mapper->map() throws AuthoritativeAuditStorageException, mapRow() catches it and re-throws it exactly.
        // But mapper->map() only throws Exception. How to make it throw AuthoritativeAuditStorageException?
        // Actually, mapper->map() does NOT throw AuthoritativeAuditStorageException.
        // We can use reflection on the repository to directly call mapRow() with a closure or mock? No mapRow is private.
        // We will just invoke mapRow directly via reflection, since we just need to test that mapRow handles AuthoritativeAuditStorageException correctly!

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);
        $reflection = new ReflectionClass($repository);
        $mapRowMethod = $reflection->getMethod("mapRow");
        $mapRowMethod->setAccessible(true);

        // Since mapper->map() does not throw AuthoritativeAuditStorageException naturally,
        // we can inject a mocked mapper? No, mapper is final!
        // Wait, does AuthoritativeAuditStorageException get thrown naturally? No, mapRow creates it.
        // Okay, we can use a closure to bind to the repository and override the mapper property with an anonymous class?
        // No, property is typed as AuthoritativeAuditRowMapper (which is final). We cannot inject a fake.

        // The only way an AuthoritativeAuditStorageException could be thrown is from mapRow itself (or if mapper is changed later).
        // Since we can\'t mock it, and mapRow only throws AuthoritativeAuditStorageException... wait.
        // The test was just constructing an exception and asserting its type before.
        // The instructions: "testExistingStorageExceptionIsNotRewrapped() currently constructs a storage exception and asserts its type only. Replace it with a real repository mapping/query path proving the exact existing AuthoritativeAuditStorageException instance is propagated without double wrapping."
        // We MUST execute repository mapping/query path.

        // If we can\'t mock mapper, we can trigger the PDOException from PDOStatement fetchAll(), which is caught by paginate() and wrapped into AuthoritativeAuditStorageException.
        // Wait, the instruction says "proving the exact existing AuthoritativeAuditStorageException instance is propagated without double wrapping."
        // That means if mapRow throws AuthoritativeAuditStorageException, paginate() shouldn\'t wrap it AGAIN.
        // Ah! If mapRow() throws AuthoritativeAuditStorageException, paginate() might catch it?
        // paginate() catches (PaginationExecutionException | PDOException). It DOES NOT catch AuthoritativeAuditStorageException!
        // So it naturally propagates!

        // Let\'s trigger AuthoritativeAuditStorageException from mapRow (e.g. invalid date), and prove it bubbles out of paginate() without being re-wrapped!

        $pdo = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);
        $pdo->method("prepare")->willReturn($stmt);

        // Return 1 total, then return a row with invalid date to trigger AuthoritativeAuditStorageException in mapRow
        $stmt->expects($this->exactly(2))->method("execute")->willReturn(true);
        $stmt->expects($this->exactly(2))->method("fetch")->willReturnOnConsecutiveCalls(
            ["total" => 1, "filtered" => 1],
            ["event_id" => "mock-event", "occurred_at" => "invalid-date"],
            false
        );

        $repository = new AuthoritativeAuditAdminQueryMysqlRepository($pdo);

        $this->expectException(AuthoritativeAuditStorageException::class);
        $this->expectExceptionMessage("Failed to map AuthoritativeAudit row: Invalid occurred_at format");

        $repository->paginate(new AuthoritativeAuditAdminQueryRequestDTO());
    }
}
', $content);

file_put_contents('tests/Unit/AuthoritativeAudit/Infrastructure/Mysql/AuthoritativeAuditAdminQueryMysqlRepositoryExceptionTest.php', $content);
