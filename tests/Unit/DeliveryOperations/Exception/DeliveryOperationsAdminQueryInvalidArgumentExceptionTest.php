<?php

declare(strict_types=1);

namespace Maatify\EventLogging\Tests\Unit\DeliveryOperations\Exception;

use Maatify\EventLogging\DeliveryOperations\Exception\DeliveryOperationsAdminQueryInvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeliveryOperationsAdminQueryInvalidArgumentExceptionTest extends TestCase
{
    public function testFactories(): void
    {
        $this->assertEquals(
            'Invalid DeliveryOperations Admin Query ID: foo',
            DeliveryOperationsAdminQueryInvalidArgumentException::invalidId('foo')->getMessage()
        );
        $this->assertEquals(
            'Invalid DeliveryOperations Admin Query retry range: attempt_no_min must be less than or equal to attempt_no_max',
            DeliveryOperationsAdminQueryInvalidArgumentException::invalidRetryRange()->getMessage()
        );
        $this->assertEquals(
            'Invalid DeliveryOperations Admin Query retry value: foo',
            DeliveryOperationsAdminQueryInvalidArgumentException::invalidRetryValue('foo')->getMessage()
        );
        $this->assertEquals(
            'Invalid DeliveryOperations Admin Query length: foo',
            DeliveryOperationsAdminQueryInvalidArgumentException::invalidLength('foo')->getMessage()
        );
        $this->assertEquals(
            'Invalid DeliveryOperations Admin Query UTF-8 encoding: foo',
            DeliveryOperationsAdminQueryInvalidArgumentException::invalidEncoding('foo')->getMessage()
        );
        $this->assertEquals(
            'Invalid DeliveryOperations Admin Query date range: foo after must be before or equal to before',
            DeliveryOperationsAdminQueryInvalidArgumentException::invalidDateRange('foo')->getMessage()
        );
        $this->assertEquals(
            'Invalid DeliveryOperations Admin Query null-state input: foo',
            DeliveryOperationsAdminQueryInvalidArgumentException::invalidNullState('foo')->getMessage()
        );
        $this->assertEquals(
            'Invalid DeliveryOperations Admin Query metadata filter count',
            DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataCount()->getMessage()
        );
        $this->assertEquals(
            'Invalid DeliveryOperations Admin Query metadata path or shape',
            DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataPath()->getMessage()
        );
        $this->assertEquals(
            'Invalid DeliveryOperations Admin Query metadata value type',
            DeliveryOperationsAdminQueryInvalidArgumentException::invalidMetadataValue()->getMessage()
        );
    }
}
