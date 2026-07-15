<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Infrastructure\Mysql\Pagination;

use DateTimeImmutable;
use DateTimeZone;
use Maatify\EventLogging\SecuritySignals\DTO\SecuritySignalsAdminQueryRequestDTO;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\Pagination\SecuritySignalsAdminQueryDescriptorBuilder;
use Maatify\Persistence\Exception\InvalidPaginationQueryException;
use Maatify\Persistence\Pdo\Pagination\PaginationConfig;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;
use Maatify\Persistence\Pdo\Pagination\SortDirectionEnum;
use Maatify\Persistence\Pdo\Pagination\SortWhitelist;
use PHPUnit\Framework\TestCase;

final class SecuritySignalsAdminQueryDescriptorBuilderTest extends TestCase
{
    private const TOTAL_SQL = 'SELECT COUNT(*) FROM maa_event_logging_security_signals';
    private const DATA_SQL = 'SELECT id, event_id, actor_type, actor_id, signal_type, severity, correlation_id, request_id, route_name, ip_address, user_agent, metadata, occurred_at FROM maa_event_logging_security_signals';

    public function testNoFilterSqlUsesTableOnlyCountAndExplicitDataColumns(): void
    {
        $descriptor = $this->build(new SecuritySignalsAdminQueryRequestDTO());

        $this->assertSame(self::TOTAL_SQL, $descriptor->totalSql);
        $this->assertSame([], $descriptor->totalParams);
        $this->assertSame(self::TOTAL_SQL, $descriptor->filteredCountSql);
        $this->assertSame(self::DATA_SQL, $descriptor->dataSql);
        $this->assertSame([], $descriptor->filteredCountParams);
        $this->assertSame([], $descriptor->dataParams);
        $this->assertDescriptorHasNoGenericPaginationSql($descriptor);
    }

    public function testEveryFilterIndependently(): void
    {
        $this->assertSingleFilter(new SecuritySignalsAdminQueryRequestDTO(actorType: 'user'), 'actor_type = :actor_type', ['actor_type' => 'user']);
        $this->assertSingleFilter(new SecuritySignalsAdminQueryRequestDTO(actorId: 10), 'actor_id = :actor_id', ['actor_id' => 10]);
        $this->assertSingleFilter(new SecuritySignalsAdminQueryRequestDTO(signalType: 'login_failed'), 'signal_type = :signal_type', ['signal_type' => 'login_failed']);
        $this->assertSingleFilter(new SecuritySignalsAdminQueryRequestDTO(severity: 'HIGH'), 'severity = :severity', ['severity' => 'HIGH']);
        $this->assertSingleFilter(new SecuritySignalsAdminQueryRequestDTO(requestId: 'req'), 'request_id = :request_id', ['request_id' => 'req']);
        $this->assertSingleFilter(new SecuritySignalsAdminQueryRequestDTO(correlationId: 'corr'), 'correlation_id = :correlation_id', ['correlation_id' => 'corr']);
        $this->assertSingleFilter(
            new SecuritySignalsAdminQueryRequestDTO(after: new DateTimeImmutable('2024-01-01 12:00:00', new DateTimeZone('Africa/Cairo'))),
            'occurred_at >= :after',
            ['after' => '2024-01-01 10:00:00.000000'],
        );
        $this->assertSingleFilter(
            new SecuritySignalsAdminQueryRequestDTO(before: new DateTimeImmutable('2024-01-01 13:00:00', new DateTimeZone('Africa/Cairo'))),
            'occurred_at <= :before',
            ['before' => '2024-01-01 11:00:00.000000'],
        );
    }

    public function testActorFiltersAndCombinedFilters(): void
    {
        $this->assertSingleFilter(new SecuritySignalsAdminQueryRequestDTO(actorId: 10), 'actor_id = :actor_id', ['actor_id' => 10]);
        $this->assertSingleFilter(new SecuritySignalsAdminQueryRequestDTO(actorType: 'admin'), 'actor_type = :actor_type', ['actor_type' => 'admin']);

        $actorPair = $this->build(new SecuritySignalsAdminQueryRequestDTO(actorType: 'admin', actorId: 10));
        $this->assertWhereAndParamsIdentity($actorPair);
        $this->assertSame(['actor_type' => 'admin', 'actor_id' => 10], $actorPair->dataParams);
        $this->assertStringContainsString('WHERE actor_type = :actor_type AND actor_id = :actor_id', $actorPair->dataSql);

        $combined = $this->build(new SecuritySignalsAdminQueryRequestDTO(
            signalType: 'login_failed',
            severity: 'HIGH',
            requestId: 'req',
            correlationId: 'corr',
        ));
        $this->assertWhereAndParamsIdentity($combined);
        $this->assertSame([
            'signal_type' => 'login_failed',
            'severity' => 'HIGH',
            'request_id' => 'req',
            'correlation_id' => 'corr',
        ], $combined->dataParams);
    }

    public function testAllFiltersTogetherParameterNamesValuesAndDateFormatting(): void
    {
        $descriptor = $this->build(new SecuritySignalsAdminQueryRequestDTO(
            actorType: 'user',
            actorId: 10,
            signalType: 'login_failed',
            severity: 'HIGH',
            requestId: 'req',
            correlationId: 'corr',
            after: new DateTimeImmutable('2024-01-01 12:00:00.123456', new DateTimeZone('Africa/Cairo')),
            before: new DateTimeImmutable('2024-01-01 12:00:00.123456', new DateTimeZone('Africa/Cairo')),
        ));

        $expectedParams = [
            'actor_type' => 'user',
            'actor_id' => 10,
            'signal_type' => 'login_failed',
            'severity' => 'HIGH',
            'request_id' => 'req',
            'correlation_id' => 'corr',
            'after' => '2024-01-01 10:00:00.123456',
            'before' => '2024-01-01 10:00:00.123456',
        ];

        $this->assertSame(self::TOTAL_SQL, $descriptor->totalSql);
        $this->assertSame(
            self::TOTAL_SQL . ' WHERE actor_type = :actor_type AND actor_id = :actor_id AND signal_type = :signal_type AND severity = :severity AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before',
            $descriptor->filteredCountSql,
        );
        $this->assertSame(
            self::DATA_SQL . ' WHERE actor_type = :actor_type AND actor_id = :actor_id AND signal_type = :signal_type AND severity = :severity AND request_id = :request_id AND correlation_id = :correlation_id AND occurred_at >= :after AND occurred_at <= :before',
            $descriptor->dataSql,
        );
        $this->assertSame($expectedParams, $descriptor->filteredCountParams);
        $this->assertSame($expectedParams, $descriptor->dataParams);
        $this->assertWhereAndParamsIdentity($descriptor);

        foreach (array_keys($descriptor->dataParams) as $key) {
            $this->assertStringStartsNotWith(':', $key);
        }
    }

    public function testCanonicalPaginationConfigurationAndPublicSortBoundary(): void
    {
        $config = new PaginationConfig(
            sortWhitelist: new SortWhitelist([
                'occurred_at' => 'occurred_at',
                'id' => 'id',
            ]),
            defaultSortBy: 'occurred_at',
            defaultSortDirection: SortDirectionEnum::DESC,
            tieBreakerSortBy: 'id',
            tieBreakerDirection: SortDirectionEnum::DESC,
            defaultPerPage: 20,
            minPerPage: 1,
            maxPerPage: 200,
        );

        $this->assertSame('`occurred_at`', $config->sortWhitelist->quotedIdentifierFor('occurred_at'));
        $this->assertSame('`id`', $config->sortWhitelist->quotedIdentifierFor('id'));
        $this->assertSame('occurred_at', $config->defaultSortBy);
        $this->assertSame(SortDirectionEnum::DESC, $config->defaultSortDirection);
        $this->assertSame('id', $config->tieBreakerSortBy);
        $this->assertSame(SortDirectionEnum::DESC, $config->tieBreakerDirection);
        $this->assertSame(20, $config->defaultPerPage);
        $this->assertSame(1, $config->minPerPage);
        $this->assertSame(200, $config->maxPerPage);
        $this->assertSame('occurred_at', (new SecuritySignalsAdminQueryRequestDTO(sortBy: 'occurred_at'))->sortBy);
        $this->assertNull((new SecuritySignalsAdminQueryRequestDTO(sortBy: 'id'))->sortBy);
    }

    public function testInvalidPersistenceDescriptorContractsAreRejectedAtBoundary(): void
    {
        $this->expectException(InvalidPaginationQueryException::class);
        $this->expectExceptionMessage('Invalid pagination parameter key.');

        new PdoPaginationQueryDescriptor(
            totalSql: self::TOTAL_SQL,
            totalParams: [':bad' => 'value'],
            filteredCountSql: self::TOTAL_SQL,
            filteredCountParams: [],
            dataSql: 'SELECT id FROM maa_event_logging_security_signals',
            dataParams: [],
        );
    }

    private function build(SecuritySignalsAdminQueryRequestDTO $request): PdoPaginationQueryDescriptor
    {
        return (new SecuritySignalsAdminQueryDescriptorBuilder())->build($request);
    }

    /** @param array<string, string|int|bool|null> $params */
    private function assertSingleFilter(
        SecuritySignalsAdminQueryRequestDTO $request,
        string $condition,
        array $params,
    ): void {
        $descriptor = $this->build($request);

        $this->assertSame(self::TOTAL_SQL . ' WHERE ' . $condition, $descriptor->filteredCountSql);
        $this->assertSame(self::DATA_SQL . ' WHERE ' . $condition, $descriptor->dataSql);
        $this->assertSame($params, $descriptor->filteredCountParams);
        $this->assertSame($params, $descriptor->dataParams);
        $this->assertWhereAndParamsIdentity($descriptor);
        $this->assertDescriptorHasNoGenericPaginationSql($descriptor);
    }

    private function assertWhereAndParamsIdentity(PdoPaginationQueryDescriptor $descriptor): void
    {
        $filteredWhere = str_replace(self::TOTAL_SQL, '', $descriptor->filteredCountSql);
        $dataWhere = str_replace(self::DATA_SQL, '', $descriptor->dataSql);

        $this->assertSame($filteredWhere, $dataWhere);
        $this->assertSame($descriptor->filteredCountParams, $descriptor->dataParams);
    }

    private function assertDescriptorHasNoGenericPaginationSql(PdoPaginationQueryDescriptor $descriptor): void
    {
        $this->assertStringNotContainsString('SELECT *', $descriptor->dataSql);
        $this->assertStringNotContainsString('ORDER BY', $descriptor->dataSql);
        $this->assertStringNotContainsString('LIMIT', $descriptor->dataSql);
        $this->assertStringNotContainsString('OFFSET', $descriptor->dataSql);
    }
}
