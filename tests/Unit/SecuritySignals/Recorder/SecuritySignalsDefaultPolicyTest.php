<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\SecuritySignals\Recorder;

use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalActorTypeEnum;
use Maatify\EventLogging\SecuritySignals\Enum\SecuritySignalSeverityEnum;
use Maatify\EventLogging\SecuritySignals\Recorder\SecuritySignalsDefaultPolicy;
use PHPUnit\Framework\TestCase;

class SecuritySignalsDefaultPolicyTest extends TestCase
{
    private SecuritySignalsDefaultPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new SecuritySignalsDefaultPolicy();
    }

    public function testNormalizeActorTypeEnum(): void
    {
        $this->assertSame('ADMIN', $this->policy->normalizeActorType(SecuritySignalActorTypeEnum::ADMIN));
    }

    public function testNormalizeActorTypeStringValid(): void
    {
        $this->assertSame('USER', $this->policy->normalizeActorType('user'));
    }

    public function testNormalizeActorTypeStringInvalid(): void
    {
        $this->assertSame('ANONYMOUS', $this->policy->normalizeActorType('UNKNOWN_TYPE'));
    }

    public function testNormalizeSeverityEnum(): void
    {
        $this->assertSame('CRITICAL', $this->policy->normalizeSeverity(SecuritySignalSeverityEnum::CRITICAL));
    }

    public function testNormalizeSeverityStringValid(): void
    {
        $this->assertSame('WARNING', $this->policy->normalizeSeverity('warning'));
    }

    public function testNormalizeSeverityStringInvalid(): void
    {
        $this->assertSame('INFO', $this->policy->normalizeSeverity('UNKNOWN_SEVERITY'));
    }

    public function testValidateMetadataSize(): void
    {
        $this->assertTrue($this->policy->validateMetadataSize('{"a":"b"}'));
        $this->assertTrue($this->policy->validateMetadataSize(str_repeat('a', 65535)));
        $this->assertFalse($this->policy->validateMetadataSize(str_repeat('a', 65536)));
    }
}
