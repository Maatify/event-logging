<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Regression;

use Maatify\EventLogging\BehaviorTrace\Contract\BehaviorTraceQueryInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Contract\DiagnosticsTelemetryQueryInterface;
use Maatify\EventLogging\Factory\AuthoritativeAuditFactory;
use Maatify\EventLogging\Provider\EventLoggingProvider;
use Maatify\EventLogging\Provider\EventLoggingProviderFactory;
use Maatify\Exceptions\Exception\System\SystemMaatifyException;
use Maatify\SharedCommon\Contracts\ClockInterface;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use ReflectionClass;
use ReflectionMethod;
use RegexIterator;

class ArchitectureTest extends TestCase
{
    private function getSrcFiles(): array
    {
        $directory = new RecursiveDirectoryIterator(__DIR__ . '/../../src');
        $iterator = new RecursiveIteratorIterator($directory);
        $regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

        $files = [];
        foreach ($regex as $file) {
            $files[] = $file[0];
        }
        return $files;
    }

    private function getSqlFiles(): array
    {
        $directory = new RecursiveDirectoryIterator(__DIR__ . '/../../');
        $iterator = new RecursiveIteratorIterator($directory);
        $regex = new RegexIterator($iterator, '/^.+\.sql$/i', RecursiveRegexIterator::GET_MATCH);

        $files = [];
        foreach ($regex as $file) {
            // Only care about schema directory or src/.../Database directory
            if (str_contains($file[0], '/schema/') || str_contains($file[0], '/Database/')) {
                $files[] = $file[0];
            }
        }
        return $files;
    }

    public function testGenericAbstractionPrevention(): void
    {
        $bannedClasses = [
            'GenericLogger',
            'GenericRecorder',
            'GenericLogDTO',
            'GenericReader',
            'GenericQueryRepository',
            'GenericRepository',
            'GenericLogRepository',
            'GenericLogFactory',
            'GenericLogProvider',
        ];

        foreach ($this->getSrcFiles() as $file) {
            $filename = basename($file);
            foreach ($bannedClasses as $bannedClass) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $bannedClass,
                    $filename,
                    "File $filename contains banned generic abstraction name $bannedClass"
                );
            }

            $content = file_get_contents($file);
            foreach ($bannedClasses as $bannedClass) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\bclass\s+' . preg_quote($bannedClass, '/') . '\b/i',
                    $content,
                    "File $file declares banned class $bannedClass"
                );
                $this->assertDoesNotMatchRegularExpression(
                    '/\binterface\s+' . preg_quote($bannedClass, '/') . '\b/i',
                    $content,
                    "File $file declares banned interface $bannedClass"
                );
            }
        }

        $bannedTables = [
            'generic_log',
            'generic_logs',
        ];

        foreach ($this->getSqlFiles() as $file) {
            $content = file_get_contents($file);
            foreach ($bannedTables as $bannedTable) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\bTABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($bannedTable, '/') . '`?\b/i',
                    $content,
                    "SQL file $file contains banned generic table name $bannedTable"
                );
            }
            $this->assertDoesNotMatchRegularExpression(
                '/\bTABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?event_logs`?\b/i',
                $content,
                "SQL file $file contains banned catch-all event_logs table"
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\bTABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?logs`?\b/i',
                $content,
                "SQL file $file contains banned catch-all logs table"
            );
        }
    }

    public function testNoHostAppNamespaceLeakage(): void
    {
        $bannedNamespaces = [
            'App\\\\',
            'Athar\\\\',
            'AtharAdmin\\\\',
            'EP4N\\\\',
            'Maatify\\\\Admin',
            'Maatify\\\\Athar',
        ];

        foreach ($this->getSrcFiles() as $file) {
            $content = file_get_contents($file);
            $code = $this->stripComments($content);
            foreach ($bannedNamespaces as $bannedNamespace) {
                $this->assertStringNotContainsString(
                    stripslashes($bannedNamespace),
                    $code,
                    "File $file leaks host application namespace: " . stripslashes($bannedNamespace)
                );
            }
        }
    }

    public function testNoFrameworkBindingsInSrc(): void
    {
        $bannedFrameworks = [
            'Slim\\',
            'Laravel\\',
            'Illuminate\\',
            'Symfony\\',
            'DI\\', // PHP-DI
            'Psr\\Http\\Message',
        ];

        $bannedTerms = [
            'route',
            'controller',
            'middleware',
        ];

        foreach ($this->getSrcFiles() as $file) {
            $content = file_get_contents($file);
            $code = $this->stripComments($content);

            // Remove 'routeName' to avoid false positives
            $code = str_ireplace('routeName', '', $code);

            foreach ($bannedFrameworks as $framework) {
                $this->assertStringNotContainsString(
                    $framework,
                    $code,
                    "File $file contains framework binding: " . $framework
                );
            }

            foreach ($bannedTerms as $term) {
                $this->assertDoesNotMatchRegularExpression(
                    '/\b' . preg_quote($term, '/') . '\b/i',
                    $code,
                    "File $file contains banned framework term: $term"
                );
            }
        }
    }

    public function testNoRoutesControllersMiddlewareAdminUI(): void
    {
        $bannedTerms = [
            'Controller',
            'Middleware',
            'Route',
            'Routes',
            'AdminController',
            'AdminDashboard',
            'View',
            'Template',
            'Blade',
            'Twig',
        ];

        foreach ($this->getSrcFiles() as $file) {
            $filename = basename($file, '.php');
            $filename = str_ireplace('ViewDTO', '', $filename);

            foreach ($bannedTerms as $term) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $term,
                    $filename,
                    "Filename $file contains banned UI/routing term: $term"
                );
            }

            $content = file_get_contents($file);
            $code = $this->stripComments($content);

            $code = str_ireplace('routeName', '', $code);
            $code = str_ireplace('route_name', '', $code);
            $code = str_ireplace('ViewDTO', '', $code);

            foreach ($bannedTerms as $term) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![a-zA-Z])' . preg_quote($term, '/') . '(?![a-zA-Z])/i',
                    $code,
                    "File $file contains banned UI/routing term in code: $term"
                );
            }
        }
    }

    public function testComposerDependencyBoundaries(): void
    {
        $composerJsonPath = __DIR__ . '/../../composer.json';
        $this->assertFileExists($composerJsonPath);

        $composerData = json_decode(file_get_contents($composerJsonPath), true);
        $this->assertNotNull($composerData, "composer.json must be valid JSON");

        $this->assertEquals('maatify/event-logging', $composerData['name'] ?? null);
        $this->assertEquals('library', $composerData['type'] ?? null);
        $this->assertEquals('MIT', $composerData['license'] ?? null);

        $this->assertArrayHasKey('psr-4', $composerData['autoload'] ?? []);
        $this->assertArrayHasKey('Maatify\\EventLogging\\', $composerData['autoload']['psr-4']);
        $this->assertEquals('src/', $composerData['autoload']['psr-4']['Maatify\\EventLogging\\']);

        $require = $composerData['require'] ?? [];
        $this->assertSame('^1.1', $require['maatify/exceptions'] ?? null);
        $this->assertSame('^1.0', $require['maatify/shared-common'] ?? null);

        $bannedRequires = ['slim/', 'laravel/', 'illuminate/', 'symfony/', 'php-di/'];

        foreach (array_keys($require) as $package) {
            foreach ($bannedRequires as $banned) {
                $this->assertStringNotContainsString(
                    $banned,
                    $package,
                    "composer.json requires banned framework package: $package"
                );
            }
        }
    }

    public function testProviderApiBoundaries(): void
    {
        $this->assertTrue(class_exists(EventLoggingProvider::class));

        $providerRef = new ReflectionClass(EventLoggingProvider::class);
        $bannedMethods = [
            'log', 'record', 'dispatch', 'route', 'logger', 'recorder', 'repository', 'get', 'make', 'resolve'
        ];

        foreach ($providerRef->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) continue;

            $methodName = $method->getName();
            $this->assertNotContains(
                strtolower($methodName),
                $bannedMethods,
                "EventLoggingProvider introduces banned generic method: $methodName"
            );
        }

        $expectedMethods = [
            'authoritativeAudit',
            'auditTrail',
            'securitySignals',
            'behaviorTrace',
            'diagnosticsTelemetry',
            'deliveryOperations'
        ];
        foreach ($expectedMethods as $expectedMethod) {
            $this->assertTrue(
                $providerRef->hasMethod($expectedMethod),
                "EventLoggingProvider must retain explicit typed accessor: $expectedMethod()"
            );
        }

        $this->assertTrue(class_exists(EventLoggingProviderFactory::class));
        $factoryRef = new ReflectionClass(EventLoggingProviderFactory::class);
        foreach ($factoryRef->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) continue;
            $methodName = $method->getName();
            $this->assertNotContains(
                strtolower($methodName),
                $bannedMethods,
                "EventLoggingProviderFactory introduces banned generic method: $methodName"
            );
        }

        $authAuditFactoryRef = new ReflectionClass(AuthoritativeAuditFactory::class);
        $createMethod = $authAuditFactoryRef->getMethod('create');
        foreach ($createMethod->getParameters() as $param) {
            if ($param->getType() !== null) {
                $typeName = $param->getType()->getName();
                $this->assertStringNotContainsString(
                    'LoggerInterface',
                    $typeName,
                    "AuthoritativeAuditFactory::create must not accept a PSR Logger"
                );
            }
        }
    }

    public function testBackwardCompatibilityGuard(): void
    {
        $this->assertTrue(interface_exists(BehaviorTraceQueryInterface::class));
        $btRef = new ReflectionClass(BehaviorTraceQueryInterface::class);
        $this->assertTrue($btRef->hasMethod('read'), "BehaviorTraceQueryInterface must retain legacy read() method");

        $this->assertTrue(interface_exists(DiagnosticsTelemetryQueryInterface::class));
        $dtRef = new ReflectionClass(DiagnosticsTelemetryQueryInterface::class);
        $this->assertTrue($dtRef->hasMethod('read'), "DiagnosticsTelemetryQueryInterface must retain legacy read() method");
    }

    public function testClockContractUsesSharedCommonAsSourceOfTruth(): void
    {
        $this->assertFileDoesNotExist(__DIR__ . '/../../src/Common/ClockInterface.php');

        foreach ($this->getSrcFiles() as $file) {
            $content = file_get_contents($file);

            $this->assertStringNotContainsString(
                'Maatify\\EventLogging\\Common\\ClockInterface',
                $content,
                "File $file must not use the removed internal ClockInterface"
            );
        }

        $factoryRef = new ReflectionClass(EventLoggingProviderFactory::class);
        $clockParameter = $factoryRef->getMethod('createDefault')->getParameters()[1] ?? null;
        $this->assertNotNull($clockParameter);
        $this->assertSame(ClockInterface::class, $clockParameter->getType()?->getName());
    }

    public function testStorageExceptionsUseSystemMaatifyException(): void
    {
        $exceptionClasses = [
            \Maatify\EventLogging\AuthoritativeAudit\Exception\AuthoritativeAuditStorageException::class,
            \Maatify\EventLogging\AuditTrail\Exception\AuditTrailStorageException::class,
            \Maatify\EventLogging\SecuritySignals\Exception\SecuritySignalsStorageException::class,
            \Maatify\EventLogging\BehaviorTrace\Exception\BehaviorTraceStorageException::class,
            \Maatify\EventLogging\DiagnosticsTelemetry\Exception\DiagnosticsTelemetryStorageException::class,
            \Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsStorageException::class,
        ];

        foreach ($exceptionClasses as $exceptionClass) {
            $ref = new ReflectionClass($exceptionClass);
            $content = file_get_contents($ref->getFileName());

            $this->assertTrue($ref->isSubclassOf(SystemMaatifyException::class));
            $this->assertStringContainsString('extends SystemMaatifyException', $content);
            $this->assertStringNotContainsString('extends RuntimeException', $content);
            $this->assertStringNotContainsString('DatabaseConnectionMaatifyException', $content);
            $this->assertTrue($ref->hasMethod('defaultErrorCode'));
        }
    }

    public function testDomainIsolation(): void
    {
        $expectedDomains = [
            'AuthoritativeAudit',
            'AuditTrail',
            'SecuritySignals',
            'BehaviorTrace',
            'DiagnosticsTelemetry',
            'DeliveryOperations'
        ];

        foreach ($expectedDomains as $domain) {
            $this->assertDirectoryExists(__DIR__ . '/../../src/' . $domain);
        }

        $commonDir = __DIR__ . '/../../src/Common';
        if (is_dir($commonDir)) {
            $directory = new RecursiveDirectoryIterator($commonDir);
            $iterator = new RecursiveIteratorIterator($directory);
            $regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

            $bannedSuffixes = ['DTO', 'Recorder', 'Repository'];

            foreach ($regex as $file) {
                $filename = basename($file[0], '.php');
                foreach ($bannedSuffixes as $suffix) {
                    $this->assertStringEndsNotWith(
                        $suffix,
                        $filename,
                        "src/Common must not contain shared cross-domain classes: $filename"
                    );
                }
            }
        }
    }

    private function stripComments(string $source): string
    {
        $tokens = token_get_all($source);
        $code = '';
        foreach ($tokens as $token) {
            if (is_string($token)) {
                $code .= $token;
            } else {
                list($id, $text) = $token;
                if ($id !== T_COMMENT && $id !== T_DOC_COMMENT) {
                    $code .= $text;
                }
            }
        }
        return $code;
    }
}
