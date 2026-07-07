<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuthoritativeAudit\Recorder;

use Maatify\EventLogging\AuthoritativeAudit\Enum\AuthoritativeAuditActorTypeInterface;
use Maatify\EventLogging\AuthoritativeAudit\Recorder\AuthoritativeAuditDefaultPolicy;
use PHPUnit\Framework\TestCase;

class AuthoritativeAuditDefaultPolicyTest extends TestCase
{
    private AuthoritativeAuditDefaultPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new AuthoritativeAuditDefaultPolicy();
    }

    public function testNormalizeActorTypeString(): void
    {
        $this->assertSame('ADMIN', $this->policy->normalizeActorType('admin'));
        $this->assertSame('UNKNOWN_TYPE', $this->policy->normalizeActorType('unknown_type'));
    }

    public function testNormalizeActorTypeInterface(): void
    {
        $mock = $this->createMock(AuthoritativeAuditActorTypeInterface::class);
        $mock->method('value')->willReturn('user');

        $this->assertSame('USER', $this->policy->normalizeActorType($mock));
    }

    public function testValidatePayloadWithoutSecrets(): void
    {
        $payload = [
            'key1' => 'value1',
            'nested' => [
                'key2' => 'value2'
            ]
        ];

        $this->assertTrue($this->policy->validatePayload($payload));
    }

    public function testValidatePayloadWithSecretsReturnsFalse(): void
    {
        $this->assertFalse($this->policy->validatePayload(['password' => '123']));
        $this->assertFalse($this->policy->validatePayload(['SECRET_KEY' => 'xyz']));
        $this->assertFalse($this->policy->validatePayload(['api_token' => 'abc']));
        $this->assertFalse($this->policy->validatePayload(['nested' => ['password' => '123']]));
    }
}
