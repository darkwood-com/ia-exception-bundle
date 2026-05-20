<?php

declare(strict_types=1);

namespace Darkwood\IaExceptionBundle\Tests;

use Darkwood\IaExceptionBundle\DarkwoodIaExceptionBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class DarkwoodIaExceptionBundleTest extends TestCase
{
    public function testBundleIsSymfonyBundle(): void
    {
        self::assertInstanceOf(Bundle::class, new DarkwoodIaExceptionBundle());
    }
}
