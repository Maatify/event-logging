<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Infrastructure\Mysql\Pagination;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\Pagination\SecuritySignalsAdminQueryDescriptorBuilder;
use Maatify\Persistence\Exception\InvalidPaginationQueryException;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use PHPUnit\Framework\TestCase;

final class SecuritySignalsAdminQueryDescriptorBuilderTest extends TestCase
{
    public function testBuildsExpectedSqlAndParams(): void
    {
        $descriptor = (new SecuritySignalsAdminQueryDescriptorBuilder())->build(new SecuritySignalsAdminQueryRequestDTO(
            actorType: 'user',
            actorId: 10,
            signalType: 'login_failed',
            severity: 'HIGH',
            requestId: 'req',
            correlationId: 'corr',
            after: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('Africa/Cairo')),
            before: new DateTimeImmutable('2024-01-01 13:00:00', new DateTimeZone('Africa/Cairo')),
        ));

        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_security_signals', $descriptor->totalSql);
        $this->assertSame([], $descriptor->totalParams);
        $this->assertSame(
            'SELECT COUNT(*) FROM maa_event_logging_security_signals WHERE actor_type = :actor_type AND actor_id = :actor_id AND signal_type = :signal_type AND severity = :severity AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before',
            $descriptor->filteredCountSql,
        );
        $this->assertSame(
            'SELECT id, event_id, actor_type, actor_id, signal_type, severity, correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at FROM maa_event_logging_security_signals WHERE actor_type = :actor_type AND actor_id = :actor_id AND signal_type = :signal_type AND severity = :severity AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before',
            $descriptor->dataSql,
        );
        $this->assertStringNotContainsString('SELECT *', $descriptor->dataSql);
        $this->assertStringNotContainsString('ORDER BY', $descriptor->dataSql);
        $this->assertStringNotContainsString('LIMIT', $descriptor->dataSql);
        $this->assertStringNotContainsString('OFFSET', $descriptor->dataSql);

        $expectedParams = [
            'actor_type' => 'user',
            'actor_id' => 10,
            'signal_type' => 'login_failed',
            'severity' => 'HIGH',
            'request_id' => 'req',
            'correlation_id' => 'corr',
            'after' => '2024-01-01 10:00:00.000000',
            'before' => '2024-01-01 11:00:00.000000',
        ];
        $this->assertSame($expectedParams, $descriptor->filteredCountParams);
        $this->assertSame($expectedParams, $descriptor->dataParams);

        foreach (array_keys($descriptor->dataParams) as $key) {
            $this->assertStringStartsNotWith(':', $key);
        }
    }

    public function testActorFiltersAreIndependentAndNoFilterUsesNoWhereClause(): void
    {
        $builder = new SecuritySignalsAdminQueryDescriptorBuilder();

        $actorIdOnly = $builder->build(new SecuritySignalsAdminQueryRequestDTO(actorId: 10));
        $this->assertStringContainsString('WHERE actor_id = :actor_id', $actorIdOnly->dataSql);
        $this->assertSame(['actor_id' => 10], $actorIdOnly->dataParams);

        $actorTypeOnly = $builder->build(new SecuritySignalsAdminQueryRequestDTO(actorType: 'admin'));
        $this->assertStringContainsString('WHERE actor_type = :actor_type', $actorTypeOnly->filteredCountSql);
        $this->assertSame(['actor_type' => 'admin'], $actorTypeOnly->filteredCountParams);

        $noFilter = $builder->build(new SecuritySignalsAdminQueryRequestDTO());
        $this->assertSame('SELECT COUNT(*) FROM maa_event_logging_security_signals', $noFilter->filteredCountSql);
        $this->assertSame(
            'SELECT id, event_id, actor_type, actor_id, signal_type, severity, correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at FROM maa_event_logging_security_signals',
            $noFilter->dataSql,
        );
        $this->assertSame([], $noFilter->dataParams);
    }

    public function testInvalidPersistenceDescriptorContractsAreRejectedAtBoundary(): void
    {
        $this->expectException(InvalidPaginationQueryException::class);
        $this->expectExceptionMessage('Invalid pagination parameter key.');

        new PdoPaginationQueryDescriptor(
            totalSql: 'SELECT COUNT(*) FROM maa_event_logging_security_signals',
            totalParams: [':bad' => 'value'],
            filteredCountSql: 'SELECT COUNT(*) FROM maa_event_logging_security_signals',
            filteredCountParams: [],
            dataSql: 'SELECT id FROM maa_event_logging_security_signals',
            dataParams: [],
        );
    }
}
