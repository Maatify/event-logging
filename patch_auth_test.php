<?php
$content = file_get_contents('tests/Integration/AuthoritativeAudit/AuthoritativeAuditRepositoryTest.php');
$content = str_replace(
'final class AuthoritativeAuditRepositoryTest extends MysqlIntegrationTestCase
{',
'final class AuthoritativeAuditRepositoryTest extends MysqlIntegrationTestCase
{
    protected function isStrictMysqlRequired(): bool
    {
        return true;
    }', $content);
file_put_contents('tests/Integration/AuthoritativeAudit/AuthoritativeAuditRepositoryTest.php', $content);

$content = file_get_contents('tests/Integration/AuthoritativeAudit/AuthoritativeAuditAdminQueryMysqlRepositoryTest.php');
$content = str_replace(
'final class AuthoritativeAuditAdminQueryMysqlRepositoryTest extends MysqlIntegrationTestCase
{',
'final class AuthoritativeAuditAdminQueryMysqlRepositoryTest extends MysqlIntegrationTestCase
{
    protected function isStrictMysqlRequired(): bool
    {
        return true;
    }', $content);
file_put_contents('tests/Integration/AuthoritativeAudit/AuthoritativeAuditAdminQueryMysqlRepositoryTest.php', $content);
