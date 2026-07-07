<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\Common;

use Maatify\EventLogging\Common\UrlSanitizer;
use PHPUnit\Framework\TestCase;

class UrlSanitizerTest extends TestCase
{
    public function testSanitizesSensitiveQueryString(): void
    {
        $url = 'https://example.com/api?user=john&token=secret123&signature=abc';
        $sanitized = UrlSanitizer::sanitize($url);

        $this->assertStringContainsString('user=john', $sanitized);
        $this->assertStringContainsString('token=%5Bredacted%5D', $sanitized);
        $this->assertStringContainsString('signature=%5Bredacted%5D', $sanitized);
    }

    public function testNoQueryString(): void
    {
        $url = 'https://example.com/api';
        $sanitized = UrlSanitizer::sanitize($url);

        $this->assertSame($url, $sanitized);
    }

    public function testCustomSensitiveKeys(): void
    {
        $url = 'https://example.com/api?token=secret123&custom_secret=hide_me';
        $sanitized = UrlSanitizer::sanitize($url, ['custom_secret']);

        $this->assertStringContainsString('token=secret123', $sanitized);
        $this->assertStringContainsString('custom_secret=%5Bredacted%5D', $sanitized);
    }
}
