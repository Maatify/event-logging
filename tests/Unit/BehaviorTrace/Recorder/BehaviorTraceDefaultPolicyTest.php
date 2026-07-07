<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\BehaviorTrace\Recorder;

use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeEnum;
use Maatify\EventLogging\BehaviorTrace\Enum\BehaviorTraceActorTypeInterface;
use Maatify\EventLogging\BehaviorTrace\Recorder\BehaviorTraceDefaultPolicy;
use PHPUnit\Framework\TestCase;

class BehaviorTraceDefaultPolicyTest extends TestCase
{
    private BehaviorTraceDefaultPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new BehaviorTraceDefaultPolicy();
    }

    public function testNormalizeActorTypeEnum(): void
    {
        $this->assertSame(
            BehaviorTraceActorTypeEnum::USER,
            $this->policy->normalizeActorType(BehaviorTraceActorTypeEnum::USER)
        );
    }

    public function testNormalizeActorTypeStringValidEnum(): void
    {
        $this->assertSame(
            BehaviorTraceActorTypeEnum::SYSTEM,
            $this->policy->normalizeActorType('system')
        );
    }

    public function testNormalizeActorTypeStringAdHoc(): void
    {
        $result = $this->policy->normalizeActorType('custom_user');

        $this->assertInstanceOf(BehaviorTraceActorTypeInterface::class, $result);
        $this->assertSame('CUSTOM_USER', $result->value());
    }

    public function testNormalizeActorTypeEmptyReturnsAnonymous(): void
    {
        $this->assertSame(
            BehaviorTraceActorTypeEnum::ANONYMOUS,
            $this->policy->normalizeActorType('')
        );
    }

    public function testNormalizeActorTypeSanitizesCharacters(): void
    {
        $result = $this->policy->normalizeActorType('Bad@User#Name!');

        $this->assertInstanceOf(BehaviorTraceActorTypeInterface::class, $result);
        $this->assertSame('BAD_USER_NAME_', $result->value());
    }

    public function testNormalizeActorTypeTruncatesToMax32(): void
    {
        $longName = str_repeat('A', 40);
        $result = $this->policy->normalizeActorType($longName);

        $this->assertInstanceOf(BehaviorTraceActorTypeInterface::class, $result);
        $this->assertSame(str_repeat('A', 32), $result->value());
    }

    public function testValidateMetadataSize(): void
    {
        $this->assertTrue($this->policy->validateMetadataSize('{"a":"b"}'));
        $this->assertTrue($this->policy->validateMetadataSize(str_repeat('a', 65536)));
        $this->assertFalse($this->policy->validateMetadataSize(str_repeat('a', 65537)));
    }
}
