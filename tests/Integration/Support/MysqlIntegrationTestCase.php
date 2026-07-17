<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\Support;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class MysqlIntegrationTestCase extends TestCase
{
    protected ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();

        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (!$dsn) {
            $this->markTestSkipped('Skipping MySQL integration test: EVENT_LOGGING_TEST_MYSQL_DSN is not set');
        }

        $user = getenv('EVENT_LOGGING_TEST_MYSQL_USER') ?: 'root';
        $pass = getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD') ?: '';

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Could not connect to MySQL using provided DSN: ' . $e->getMessage());
        }

        $this->setUpSchema();
        $this->cleanTables();
    }

    protected function tearDown(): void
    {
        if ($this->pdo) {
            $this->cleanTables();
        }
        $this->pdo = null;
        parent::tearDown();
    }

    abstract protected function getDomainSchemaFile(): string;
    /**
     * @return array<int, string>
     */
    abstract protected function getTableNames(): array;

    protected function setUpSchema(): void
    {
        if ($this->pdo === null) {
            return;
        }

        $file = __DIR__ . '/../../../' . $this->getDomainSchemaFile();
        if (!file_exists($file)) {
            throw new RuntimeException("Schema file not found: " . $file);
        }

        $sql = file_get_contents($file);

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        $tables = array_reverse($this->getTableNames());
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $statements = [];
        $current = '';
        /** @var string|false $sql */
        $lines = explode("\n", (string) $sql);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '--')) continue; // Skip full line comments
            $current .= $line . "\n";
            if (str_ends_with(trim($line), ';')) {
                $statements[] = trim($current);
                $current = '';
            }
        }

        foreach ($statements as $stmt) {
            if ($stmt !== '') {
                $this->pdo->exec($stmt);
            }
        }
    }

    protected function cleanTables(): void
    {
        if ($this->pdo === null) {
            return;
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->getTableNames() as $table) {
            $this->pdo->exec("TRUNCATE TABLE `$table`");
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
