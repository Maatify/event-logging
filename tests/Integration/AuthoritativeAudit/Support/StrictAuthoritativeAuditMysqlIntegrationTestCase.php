<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Integration\AuthoritativeAudit\Support;

use Maatify\EventLogging\Tests\Integration\Support\MysqlIntegrationTestCase;
use PDO;

abstract class StrictAuthoritativeAuditMysqlIntegrationTestCase extends MysqlIntegrationTestCase
{
    protected function setUp(): void
    {
        $dsn = getenv('EVENT_LOGGING_TEST_MYSQL_DSN');
        if (!$dsn) {
            $this->fail('Strict MySQL integration failed: EVENT_LOGGING_TEST_MYSQL_DSN is not set');
        }

        $user = getenv('EVENT_LOGGING_TEST_MYSQL_USER') ?: 'root';
        $pass = getenv('EVENT_LOGGING_TEST_MYSQL_PASSWORD') ?: '';

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $e) {
            $this->fail('Strict MySQL integration failed: Could not connect to MySQL using provided DSN: ' . $e->getMessage());
        }

        $this->setUpSchema();
        $this->cleanTables();
    }
}
