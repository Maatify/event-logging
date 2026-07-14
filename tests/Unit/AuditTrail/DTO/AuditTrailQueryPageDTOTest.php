<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\AuditTrail\DTO;

use PHPUnit\Framework\TestCase;

class AuditTrailQueryPageDTOTest extends TestCase
{
    public function testSupersededPageDtoIsRetired(): void
    {
        $this->assertFalse(class_exists('Maatify\EventLogging\AuditTrail\DTO\AuditTrailQueryPageDTO'));
    }
}
