<?php
$content = file_get_contents('tests/Integration/Support/MysqlIntegrationTestCase.php');

$content = str_replace(
'        $dsn = getenv(\'EVENT_LOGGING_TEST_MYSQL_DSN\');
        if (!$dsn) {
            $this->markTestSkipped(\'Skipping MySQL integration test: EVENT_LOGGING_TEST_MYSQL_DSN is not set\');
        }

        $user = getenv(\'EVENT_LOGGING_TEST_MYSQL_USER\') ?: \'root\';
        $pass = getenv(\'EVENT_LOGGING_TEST_MYSQL_PASSWORD\') ?: \'\';

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $e) {
            $this->markTestSkipped(\'Could not connect to MySQL using provided DSN: \' . $e->getMessage());
        }',
'        $dsn = getenv(\'EVENT_LOGGING_TEST_MYSQL_DSN\');
        if (!$dsn) {
            if ($this->isStrictMysqlRequired()) {
                $this->fail(\'Strict MySQL integration failed: EVENT_LOGGING_TEST_MYSQL_DSN is not set\');
            } else {
                $this->markTestSkipped(\'Skipping MySQL integration test: EVENT_LOGGING_TEST_MYSQL_DSN is not set\');
            }
        }

        $user = getenv(\'EVENT_LOGGING_TEST_MYSQL_USER\') ?: \'root\';
        $pass = getenv(\'EVENT_LOGGING_TEST_MYSQL_PASSWORD\') ?: \'\';

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $e) {
            if ($this->isStrictMysqlRequired()) {
                $this->fail(\'Strict MySQL integration failed: Could not connect to MySQL using provided DSN: \' . $e->getMessage());
            } else {
                $this->markTestSkipped(\'Could not connect to MySQL using provided DSN: \' . $e->getMessage());
            }
        }', $content);

$content = str_replace(
'abstract class MysqlIntegrationTestCase extends TestCase
{',
'abstract class MysqlIntegrationTestCase extends TestCase
{
    protected function isStrictMysqlRequired(): bool
    {
        return false;
    }', $content);

file_put_contents('tests/Integration/Support/MysqlIntegrationTestCase.php', $content);
