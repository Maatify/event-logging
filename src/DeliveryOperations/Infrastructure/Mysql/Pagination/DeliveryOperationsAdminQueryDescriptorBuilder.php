<?php

declare(strict_types=1);

namespace Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\Pagination;

use DateTimeZone;
use Maatify\EventLogging\DeliveryOperations\DTO\DeliveryOperationsAdminQueryRequestDTO;
use Maatify\Persistence\Pdo\Pagination\PdoPaginationQueryDescriptor;

/** @internal */
final class DeliveryOperationsAdminQueryDescriptorBuilder
{
    public function build(DeliveryOperationsAdminQueryRequestDTO $request): PdoPaginationQueryDescriptor
    {
        $filtered = $this->buildFilteredWhereAndParams($request);
        $whereSql = $filtered['whereSql'];
        $params = $filtered['params'];

        $totalSql = 'SELECT COUNT(*) FROM maa_event_logging_delivery_operations';
        $filteredCountSql = 'SELECT COUNT(*) FROM maa_event_logging_delivery_operations' . $whereSql;
        $dataSql = 'SELECT id, event_id, channel, operation_type, actor_type, actor_id, target_type, target_id, status, attempt_no, scheduled_at, completed_at, correlation_id, request_id, provider, provider_message_id, error_code, error_message, metadata, occurred_at FROM maa_event_logging_delivery_operations' . $whereSql;

        /** @var array<string, string|int|bool|null> $typedParams */
        $typedParams = $params;

        return new PdoPaginationQueryDescriptor(
            totalSql: $totalSql,
            totalParams: [],
            filteredCountSql: $filteredCountSql,
            filteredCountParams: $typedParams,
            dataSql: $dataSql,
            dataParams: $typedParams
        );
    }

    /** @return array{whereSql: string, params: array<string, string|int|float|bool|null>} */
    private function buildFilteredWhereAndParams(DeliveryOperationsAdminQueryRequestDTO $request): array
    {
        $conditions = [];
        $params = [];

        if ($request->id !== null) {
            $conditions[] = 'id = :id';
            $params['id'] = $request->id;
        }
        if ($request->eventId !== null) {
            $conditions[] = 'event_id = :event_id';
            $params['event_id'] = $request->eventId;
        }
        if ($request->channel !== null) {
            $conditions[] = 'channel = :channel';
            $params['channel'] = $request->channel;
        }
        if ($request->operationType !== null) {
            $conditions[] = 'operation_type = :operation_type';
            $params['operation_type'] = $request->operationType;
        }
        if ($request->actorType !== null) {
            $conditions[] = 'actor_type = :actor_type';
            $params['actor_type'] = $request->actorType;
        }
        if ($request->actorId !== null) {
            $conditions[] = 'actor_id = :actor_id';
            $params['actor_id'] = $request->actorId;
        }
        if ($request->targetType !== null) {
            $conditions[] = 'target_type = :target_type';
            $params['target_type'] = $request->targetType;
        }
        if ($request->targetId !== null) {
            $conditions[] = 'target_id = :target_id';
            $params['target_id'] = $request->targetId;
        }
        if ($request->status !== null) {
            $conditions[] = 'status = :status';
            $params['status'] = $request->status;
        }
        if ($request->attemptNoMin !== null) {
            $conditions[] = 'attempt_no >= :attempt_no_min';
            $params['attempt_no_min'] = $request->attemptNoMin;
        }
        if ($request->attemptNoMax !== null) {
            $conditions[] = 'attempt_no <= :attempt_no_max';
            $params['attempt_no_max'] = $request->attemptNoMax;
        }
        if ($request->correlationId !== null) {
            $conditions[] = 'correlation_id = :correlation_id';
            $params['correlation_id'] = $request->correlationId;
        }
        if ($request->requestId !== null) {
            $conditions[] = 'request_id = :request_id';
            $params['request_id'] = $request->requestId;
        }
        if ($request->provider !== null) {
            $conditions[] = 'provider = :provider';
            $params['provider'] = $request->provider;
        }
        if ($request->providerMessageId !== null) {
            $conditions[] = 'provider_message_id = :provider_message_id';
            $params['provider_message_id'] = $request->providerMessageId;
        }
        if ($request->errorCode !== null) {
            $conditions[] = 'error_code = :error_code';
            $params['error_code'] = $request->errorCode;
        }
        if ($request->errorMessageLike !== null) {
            $conditions[] = "error_message LIKE :error_message_like ESCAPE '\\\\'";
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $request->errorMessageLike);
            $params['error_message_like'] = '%' . $escaped . '%';
        }

        $utc = new DateTimeZone('UTC');

        if ($request->scheduledAfter !== null) {
            $conditions[] = 'scheduled_at >= :scheduled_after';
            $params['scheduled_after'] = $request->scheduledAfter->setTimezone($utc)->format('Y-m-d H:i:s.u');
        }
        if ($request->scheduledBefore !== null) {
            $conditions[] = 'scheduled_at <= :scheduled_before';
            $params['scheduled_before'] = $request->scheduledBefore->setTimezone($utc)->format('Y-m-d H:i:s.u');
        }

        if ($request->completedAfter !== null) {
            $conditions[] = 'completed_at >= :completed_after';
            $params['completed_after'] = $request->completedAfter->setTimezone($utc)->format('Y-m-d H:i:s.u');
        }
        if ($request->completedBefore !== null) {
            $conditions[] = 'completed_at <= :completed_before';
            $params['completed_before'] = $request->completedBefore->setTimezone($utc)->format('Y-m-d H:i:s.u');
        }

        if ($request->after !== null) {
            $conditions[] = 'occurred_at >= :after';
            $params['after'] = $request->after->setTimezone($utc)->format('Y-m-d H:i:s.u');
        }
        if ($request->before !== null) {
            $conditions[] = 'occurred_at <= :before';
            $params['before'] = $request->before->setTimezone($utc)->format('Y-m-d H:i:s.u');
        }

        if ($request->metadataFilters !== null) {
            $i = 1;
            foreach ($request->metadataFilters as $path => $value) {
                $pathExistsParam = 'meta_path_exists_' . $i;
                $pathValueParam = 'meta_path_value_' . $i;
                $valParam = 'meta_value_' . $i;

                $conditions[] = "JSON_CONTAINS_PATH(metadata, 'one', :{$pathExistsParam}) = 1 AND JSON_CONTAINS(metadata, :{$valParam}, :{$pathValueParam}) = 1";
                $params[$pathExistsParam] = $path;
                $params[$pathValueParam] = $path;
                $params[$valParam] = json_encode($value, \JSON_THROW_ON_ERROR);
                $i++;
            }
        }

        if ($request->nullStateFilters !== null) {
            $fieldMapping = [
                'actorType' => 'actor_type',
                'actorId' => 'actor_id',
                'targetType' => 'target_type',
                'targetId' => 'target_id',
                'scheduledAt' => 'scheduled_at',
                'completedAt' => 'completed_at',
                'correlationId' => 'correlation_id',
                'requestId' => 'request_id',
                'provider' => 'provider',
                'providerMessageId' => 'provider_message_id',
                'errorCode' => 'error_code',
                'errorMessage' => 'error_message'
            ];

            foreach ($request->nullStateFilters as $key => $isNull) {
                if (isset($fieldMapping[$key])) {
                    $column = $fieldMapping[$key];
                    if ($isNull) {
                        $conditions[] = "{$column} IS NULL";
                    } else {
                        $conditions[] = "{$column} IS NOT NULL";
                    }
                }
            }
        }

        $whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [
            'whereSql' => $whereSql,
            'params' => $params
        ];
    }
}
