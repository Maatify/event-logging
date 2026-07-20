<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuthoritativeAudit\Support;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class StrictAuthoritativeAuditMysqlIntegrationTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (!is_string($dsn) || $dsn === '') {
            throw new RuntimeException(
                'EVENT_LOGGING_TEST_MYSQL_DSN is required for strict AuthoritativeAudit integration tests.'
            );
        }

        $user = getenv('EVENT_LOGGING_TEST_MYSQL_USER');
        $password = getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD');

        try {
            $this->pdo = new PDO(
                $dsn,
                is_string($user) ? $user : 'root',
                is_string($password) ? $password : '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Could not connect to MySQL for strict AuthoritativeAudit integration tests: '
                    . $exception->getMessage(),
                previous: $exception,
            );
        }

        $this->resetSchema();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $this->cleanTables();
        }

        parent::tearDown();
    }

    abstract protected function getDomainSchemaFile(): string;

    /** @return list<string> */
    abstract protected function getTableNames(): array;

    private function resetSchema(): void
    {
        $schemaPath = __DIR__ . '/../../../../' . $this->getDomainSchemaFile();
        $schema = file_get_contents($schemaPath);
        if (!is_string($schema)) {
            throw new RuntimeException('AuthoritativeAudit schema file not found: ' . $schemaPath);
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (array_reverse($this->getTableNames()) as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $statements = preg_split('/;\s*(?:\r?\n|$)/', $schema);
        if (!is_array($statements)) {
            throw new RuntimeException('Could not parse AuthoritativeAudit schema statements.');
        }

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $this->pdo->exec($statement);
            }
        }

        $this->cleanTables();
    }

    private function cleanTables(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->getTableNames() as $table) {
            $this->pdo->exec("TRUNCATE TABLE `{$table}`");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
