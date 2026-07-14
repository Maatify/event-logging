<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\DTO;

use PHPUnit\Framework\TestCase;

class AuditTrailQueryCursorDTOTest extends TestCase
{
    public function testSupersededCursorDtoIsRetired(): void
    {
        $this->assertFalse(class_exists('Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryCursorDTO'));
    }
}
