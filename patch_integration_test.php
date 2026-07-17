<?php
$content = file_get_contents('tests/Integration/AuthoritativeAudit/AuthoritativeAuditRepositoryTest.php');

$content = preg_replace(
'/    public function testCorruptJsonMapsToNullSafely\(\): void\s+\{\s+\$now = new DateTimeImmutable\(\'2024-01-01 10:00:00\', new DateTimeZone\(\'UTC\'\)\);\s+if \(\$this->pdo === null\) \{\s+\$this->fail\(\'PDO not initialized\.\'\);\s+\}\s+\$serverVersionAttr = \$this->pdo->getAttribute\(PDO::ATTR_SERVER_VERSION\);\s+\$serverVersion = is_scalar\(\$serverVersionAttr\) \? \(string\) \$serverVersionAttr : \'\';\s+\$isMariaDb = str_contains\(\$serverVersion, \'MariaDB\'\);\s+if \(\$isMariaDb\) \{\s+\$this->pdo->exec\("ALTER TABLE maa_event_logging_authoritative_audit_log DROP CONSTRAINT IF EXISTS `changes`"\);\s+\}\s+\/\/ Insert corrupt JSON directly into log table\s+\$stmt = \$this->pdo->prepare\("\s+INSERT INTO maa_event_logging_authoritative_audit_log\s+\(event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at\)\s+VALUES \(\?, \?, \?, \?, \?, \?, \'invalid-json\', \?, \?\)\s+"\);\s+\$this->expectException\(\\\\PDOException::class\);\s+\$stmt->execute\(\[\s+\'event-corrupt\',\s+\'system\',\s+1,\s+\'test_action\',\s+\'target\',\s+2,\s+\'corr-1\',\s+\$now->format\(\'Y-m-d H:i:s.u\'\)\s+\]\);\s+\}/',
'    public function testStrictJsonDatabaseRejectsCorruptJson(): void
    {
        $now = new DateTimeImmutable(\'2024-01-01 10:00:00\', new DateTimeZone(\'UTC\'));

        if ($this->pdo === null) {
            $this->fail(\'PDO not initialized.\');
        }

        // Insert corrupt JSON directly into log table.
        // We expect the strict JSON constraint to reject this insertion at the database level.
        $stmt = $this->pdo->prepare("
            INSERT INTO maa_event_logging_authoritative_audit_log
            (event_id, actor_type, actor_id, action, target_type, target_id, changes, correlation_id, occurred_at)
            VALUES (?, ?, ?, ?, ?, ?, \'invalid-json\', ?, ?)
        ");

        $this->expectException(\PDOException::class);
        $stmt->execute([
            \'event-corrupt\',
            \'system\',
            1,
            \'test_action\',
            \'target\',
            2,
            \'corr-1\',
            $now->format(\'Y-m-d H:i:s.u\')
        ]);
    }', $content);

file_put_contents('tests/Integration/AuthoritativeAudit/AuthoritativeAuditRepositoryTest.php', $content);
