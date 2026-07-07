<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\Recorder;

use Maatify\EventLogging\AuditTrail\Enum\AuditTrailActorTypeEnum;
use Maatify\EventLogging\AuditTrail\Recorder\AuditTrailDefaultPolicy;
use PHPUnit\Framework\TestCase;

class AuditTrailDefaultPolicyTest extends TestCase
{
    private AuditTrailDefaultPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new AuditTrailDefaultPolicy();
    }

    public function testNormalizeActorTypeEnum(): void
    {
        $this->assertSame('ADMIN', $this->policy->normalizeActorType(AuditTrailActorTypeEnum::ADMIN));
    }

    public function testNormalizeActorTypeStringValid(): void
    {
        $this->assertSame('USER', $this->policy->normalizeActorType('user'));
    }

    public function testNormalizeActorTypeStringInvalid(): void
    {
        $this->assertSame('ANONYMOUS', $this->policy->normalizeActorType('UNKNOWN_TYPE'));
    }

    public function testValidateMetadataSize(): void
    {
        $this->assertTrue($this->policy->validateMetadataSize('{"a":"b"}'));
        $this->assertTrue($this->policy->validateMetadataSize(str_repeat('a', 65535)));
        $this->assertFalse($this->policy->validateMetadataSize(str_repeat('a', 65536)));
    }
}
