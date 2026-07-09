<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Bootstrap;

use Maatify\EventLogging\AuditTrail\Contract\AuditTrailQueryInterface;
use Maatify\EventLogging\AuditTrail\Infrastructure\Mysql\AuditTrailQueryMysqlRepository;
use Maatify\EventLogging\AuditTrail\Recorder\AuditTrailRecorder;
use Maatify\EventLogging\AuthoritativeAudit\Contract\AuthoritativeAuditQueryInterface;
use Maatify\EventLogging\AuthoritativeAudit\Infrastructure\Mysql\AuthoritativeAuditQueryMysqlRepository;
use Maatify\EventLogging\AuthoritativeAudit\Recorder\AuthoritativeAuditRecorder;
use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceQueryInterface;
use Maatify\EventLogging\BehaviorTrace\Infrastructure\Mysql\BehaviorTraceQueryMysqlRepository;
use Maatify\EventLogging\BehaviorTrace\Recorder\BehaviorTraceRecorder;
use Maatify\EventLogging\DeliveryOperations\Contract\DeliveryOperationsQueryInterface;
use Maatify\EventLogging\DeliveryOperations\Infrastructure\Mysql\DeliveryOperationsQueryMysqlRepository;
use Maatify\EventLogging\DeliveryOperations\Recorder\DeliveryOperationsRecorder;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryQueryInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Infrastructure\Mysql\DiagnosticsTelemetryQueryMysqlRepository;
use Maatify\EventLogging\DiagnosticsTelemetry\Recorder\DiagnosticsTelemetryRecorder;
use Maatify\EventLogging\Provider\EventLoggingProvider;
use Maatify\EventLogging\Provider\EventLoggingProviderFactory;
use Maatify\EventLogging\SecuritySignals\Contract\SecuritySignalsQueryInterface;
use Maatify\EventLogging\SecuritySignals\Infrastructure\Mysql\SecuritySignalsQueryMysqlRepository;
use Maatify\EventLogging\SecuritySignals\Recorder\SecuritySignalsRecorder;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PDO;
use Psr\Log\LoggerInterface;
use Throwable;

final class EventLoggingBindings
{
    /**
     * Return optional container definitions for host applications that want to wire this package through DI.
     *
     * The returned array intentionally uses plain PHP callables instead of PHP-DI-specific definition helpers.
     * Host containers must provide a PDO instance and a ClockInterface binding. LoggerInterface is optional and
     * is passed only to fail-open domains by EventLoggingProviderFactory.
     *
     * @return array<class-string, callable(mixed): object>
     */
    public static function definitions(): array
    {
        return [
            EventLoggingProvider::class => static fn ($container): EventLoggingProvider => EventLoggingProviderFactory::createDefault(
                self::pdo($container),
                self::clock($container),
                self::optionalLogger($container)
            ),
            AuthoritativeAuditRecorder::class => static fn ($container): AuthoritativeAuditRecorder => self::provider($container)->authoritativeAudit(),
            AuditTrailRecorder::class => static fn ($container): AuditTrailRecorder => self::provider($container)->auditTrail(),
            SecuritySignalsRecorder::class => static fn ($container): SecuritySignalsRecorder => self::provider($container)->securitySignals(),
            BehaviorTraceRecorder::class => static fn ($container): BehaviorTraceRecorder => self::provider($container)->behaviorTrace(),
            DiagnosticsTelemetryRecorder::class => static fn ($container): DiagnosticsTelemetryRecorder => self::provider($container)->diagnosticsTelemetry(),
            DeliveryOperationsRecorder::class => static fn ($container): DeliveryOperationsRecorder => self::provider($container)->deliveryOperations(),
            AuthoritativeAuditQueryInterface::class => static fn ($container): AuthoritativeAuditQueryInterface => new AuthoritativeAuditQueryMysqlRepository(self::pdo($container)),
            AuditTrailQueryInterface::class => static fn ($container): AuditTrailQueryInterface => new AuditTrailQueryMysqlRepository(self::pdo($container)),
            SecuritySignalsQueryInterface::class => static fn ($container): SecuritySignalsQueryInterface => new SecuritySignalsQueryMysqlRepository(self::pdo($container)),
            BehaviorTraceQueryInterface::class => static fn ($container): BehaviorTraceQueryInterface => new BehaviorTraceQueryMysqlRepository(self::pdo($container)),
            DiagnosticsTelemetryQueryInterface::class => static fn ($container): DiagnosticsTelemetryQueryInterface => new DiagnosticsTelemetryQueryMysqlRepository(self::pdo($container)),
            DeliveryOperationsQueryInterface::class => static fn ($container): DeliveryOperationsQueryInterface => new DeliveryOperationsQueryMysqlRepository(self::pdo($container)),
        ];
    }

    private static function provider(mixed $container): EventLoggingProvider
    {
        $provider = self::get($container, EventLoggingProvider::class);
        assert($provider instanceof EventLoggingProvider);

        return $provider;
    }

    private static function pdo(mixed $container): PDO
    {
        $pdo = self::get($container, PDO::class);
        assert($pdo instanceof PDO);

        return $pdo;
    }

    private static function clock(mixed $container): ClockInterface
    {
        $clock = self::get($container, ClockInterface::class);
        assert($clock instanceof ClockInterface);

        return $clock;
    }

    private static function optionalLogger(mixed $container): ?LoggerInterface
    {
        if (is_object($container) && is_callable([$container, 'has']) && ! call_user_func([$container, 'has'], LoggerInterface::class)) {
            return null;
        }

        try {
            $logger = self::get($container, LoggerInterface::class);
        } catch (Throwable) {
            return null;
        }

        return $logger instanceof LoggerInterface ? $logger : null;
    }

    private static function get(mixed $container, string $id): mixed
    {
        if (! is_object($container) || ! is_callable([$container, 'get'])) {
            throw new \LogicException('Event logging DI bindings require a container object with a get(string $id) method.');
        }

        return call_user_func([$container, 'get'], $id);
    }
}
