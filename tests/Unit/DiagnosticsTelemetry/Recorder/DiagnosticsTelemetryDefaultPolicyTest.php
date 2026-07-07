<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DiagnosticsTelemetry\Recorder;

use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeEnum;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetryActorTypeInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityEnum;
use Maatify\EventLogging\DiagnosticsTelemetry\Enum\DiagnosticsTelemetrySeverityInterface;
use Maatify\EventLogging\DiagnosticsTelemetry\Recorder\DiagnosticsTelemetryDefaultPolicy;
use PHPUnit\Framework\TestCase;

class DiagnosticsTelemetryDefaultPolicyTest extends TestCase
{
    private DiagnosticsTelemetryDefaultPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new DiagnosticsTelemetryDefaultPolicy();
    }

    public function testNormalizeActorTypeEnum(): void
    {
        $this->assertSame(
            DiagnosticsTelemetryActorTypeEnum::SYSTEM,
            $this->policy->normalizeActorType(DiagnosticsTelemetryActorTypeEnum::SYSTEM)
        );
    }

    public function testNormalizeActorTypeStringValidEnum(): void
    {
        $this->assertSame(
            DiagnosticsTelemetryActorTypeEnum::SYSTEM,
            $this->policy->normalizeActorType('system')
        );
    }

    public function testNormalizeActorTypeStringAdHoc(): void
    {
        $result = $this->policy->normalizeActorType('custom_sys');

        $this->assertInstanceOf(DiagnosticsTelemetryActorTypeInterface::class, $result);
        $this->assertSame('CUSTOM_SYS', $result->value());
    }

    public function testNormalizeActorTypeEmptyReturnsAnonymous(): void
    {
        $this->assertSame(
            DiagnosticsTelemetryActorTypeEnum::ANONYMOUS,
            $this->policy->normalizeActorType('')
        );
    }

    public function testNormalizeSeverityEnum(): void
    {
        $this->assertSame(
            DiagnosticsTelemetrySeverityEnum::ERROR,
            $this->policy->normalizeSeverity(DiagnosticsTelemetrySeverityEnum::ERROR)
        );
    }

    public function testNormalizeSeverityStringValidEnum(): void
    {
        $this->assertSame(
            DiagnosticsTelemetrySeverityEnum::WARNING,
            $this->policy->normalizeSeverity('warning')
        );
    }

    public function testNormalizeSeverityStringAdHoc(): void
    {
        $result = $this->policy->normalizeSeverity('custom_sev');

        $this->assertInstanceOf(DiagnosticsTelemetrySeverityInterface::class, $result);
        $this->assertSame('CUSTOM_SEV', $result->value());
    }

    public function testNormalizeSeverityTruncatesToMax16(): void
    {
        $longSev = str_repeat('A', 20);
        $result = $this->policy->normalizeSeverity($longSev);

        $this->assertInstanceOf(DiagnosticsTelemetrySeverityInterface::class, $result);
        $this->assertSame(str_repeat('A', 16), $result->value());
    }

    public function testValidateMetadataSize(): void
    {
        $this->assertTrue($this->policy->validateMetadataSize('{"a":"b"}'));
        $this->assertTrue($this->policy->validateMetadataSize(str_repeat('a', 65536)));
        $this->assertFalse($this->policy->validateMetadataSize(str_repeat('a', 65537)));
    }
}
