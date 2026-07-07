<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\Recorder;

use Maatify\EventLogging\DeliveryOperations\Enum\DeliveryActorTypeInterface;
use Maatify\EventLogging\DeliveryOperations\Recorder\DeliveryOperationsDefaultPolicy;
use PHPUnit\Framework\TestCase;

class DeliveryOperationsDefaultPolicyTest extends TestCase
{
    private DeliveryOperationsDefaultPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new DeliveryOperationsDefaultPolicy();
    }

    public function testNormalizeActorTypeStringKnown(): void
    {
        $this->assertSame('ADMIN', $this->policy->normalizeActorType('admin'));
    }

    public function testNormalizeActorTypeStringUnknownReturnsUppercase(): void
    {
        $this->assertSame('UNKNOWN_TYPE', $this->policy->normalizeActorType('unknown_type'));
    }

    public function testNormalizeActorTypeInterface(): void
    {
        $mock = $this->createMock(DeliveryActorTypeInterface::class);
        $mock->method('value')->willReturn('user');

        $this->assertSame('USER', $this->policy->normalizeActorType($mock));
    }

    public function testValidateMetadataSize(): void
    {
        $this->assertTrue($this->policy->validateMetadataSize('{"a":"b"}'));
        $this->assertTrue($this->policy->validateMetadataSize(str_repeat('a', 65536)));
        $this->assertFalse($this->policy->validateMetadataSize(str_repeat('a', 65537)));
    }
}
