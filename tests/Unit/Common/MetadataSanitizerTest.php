<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\Common;

use Maatify\EventLogging\Common\MetadataSanitizer;
use PHPUnit\Framework\TestCase;

class MetadataSanitizerTest extends TestCase
{
    public function testSanitizesSensitiveKeys(): void
    {
        $metadata = [
            'public_info' => 'visible',
            'password' => 'secret123',
            'api_token' => 'abc',
            'secret_key' => 'xyz',
            'authorization' => 'Bearer token',
            'session_cookie' => 'val'
        ];

        $sanitized = MetadataSanitizer::sanitize($metadata);

        $this->assertSame('visible', $sanitized['public_info']);
        $this->assertSame('[redacted]', $sanitized['password']);
        $this->assertSame('[redacted]', $sanitized['api_token']);
        $this->assertSame('[redacted]', $sanitized['secret_key']);
        $this->assertSame('[redacted]', $sanitized['authorization']);
        $this->assertSame('[redacted]', $sanitized['session_cookie']);
    }

    public function testSanitizesNestedArrays(): void
    {
        $metadata = [
            'user' => [
                'name' => 'John',
                'password' => 'secret123'
            ]
        ];

        $sanitized = MetadataSanitizer::sanitize($metadata);

        /** @var array<string, mixed> $user */
        $user = $sanitized['user'] ?? [];
        $this->assertSame('John', $user['name'] ?? null);
        $this->assertSame('[redacted]', $user['password'] ?? null);
    }

    public function testCustomSensitiveKeys(): void
    {
        $metadata = [
            'password' => 'secret123',
            'custom_secret' => 'hide_me'
        ];

        $sanitized = MetadataSanitizer::sanitize($metadata, ['custom_secret']);

        // 'password' is not in the custom list, so it shouldn't be redacted
        $this->assertSame('secret123', $sanitized['password']);
        $this->assertSame('[redacted]', $sanitized['custom_secret']);
    }
}
