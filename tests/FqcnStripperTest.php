<?php

declare(strict_types=1);

namespace LHcze\FqcnStripper\Tests;

use InvalidArgumentException;
use LHcze\FqcnStripper\FqcnStripper;
use LHcze\FqcnStripper\StringOperation;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionException;

final class FqcnStripperTest extends TestCase
{
    public function testBasicStripping(): void
    {
        $this->assertSame('User', FqcnStripper::strip('App\\Entity\\User'));
    }

    public function testLower(): void
    {
        $this->assertSame('user', FqcnStripper::strip('App\\Entity\\User', FqcnStripper::LOWER));
    }

    public function testLowUc(): void
    {
        $this->assertSame('User', FqcnStripper::strip('App\\Entity\\User', FqcnStripper::LOW_UC));
    }

    public function testUpper(): void
    {
        $this->assertSame('USER', FqcnStripper::strip('App\\Entity\\User', FqcnStripper::UPPER));
    }

    public function testNormalizeModifier(): void
    {
        $this->assertSame(
            FqcnStripper::LOW_UC,
            FqcnStripper::normalizeModifier(FqcnStripper::LOWER | FqcnStripper::UC),
        );
    }

    public function testObjectInput(): void
    {
        $object = new TestStubClassForFqcnStripper();
        $this->assertSame('TestStubClassForFqcnStripper', FqcnStripper::strip($object));
    }

    public function testMultibyteTransformation(): void
    {
        if (!extension_loaded('mbstring')) {
            $this->markTestSkipped('mbstring extension not available');
        }

        $this->assertSame(
            'Üser',
            FqcnStripper::strip('App\\Entity\\Üser', FqcnStripper::LOW_UC | FqcnStripper::MULTIBYTE),
        );
    }

    public function testStripThemAll(): void
    {
        $fqcnList = [
            'App\\Model\\Customer',
            'App\\Entity\\Order',
            'App\\Controller\\Admin\\DashboardController',
        ];

        $expected = ['customer', 'order', 'dashboardcontroller'];

        $this->assertSame(
            $expected,
            FqcnStripper::stripThemAll($fqcnList, FqcnStripper::LOWER),
        );
    }

    public function testEmptyInputThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FqcnStripper::strip('');
    }

    public function testInvalidModifierThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FqcnStripper::strip('App\\Entity\\User', 999);
    }

    public function testModifierConflictThrowsException(): void
    {
        $this->expectException(LogicException::class);
        FqcnStripper::strip('App\\Entity\\User', FqcnStripper::UPPER | FqcnStripper::LOWER);
    }

    public function testMultibyteWithoutExtensionThrows(): void
    {
        if (extension_loaded('mbstring')) {
            // Test using a subprocess
            $output = shell_exec('php -n tests/fqcn-stripper-test-no-mb.php');
            $this->assertStringContainsString('PASS', (string) $output);
        } else {
            $this->expectException(LogicException::class);
            FqcnStripper::strip('App\\Entity\\Üser', FqcnStripper::MULTIBYTE);
        }
    }

    public function testIsValidModifier(): void
    {
        $this->assertTrue(FqcnStripper::isValidModifier(FqcnStripper::LOWER));
        $this->assertTrue(FqcnStripper::isValidModifier(FqcnStripper::LOW_UC));
        $this->assertFalse(FqcnStripper::isValidModifier(1024));
        $this->assertFalse(FqcnStripper::isValidModifier(FqcnStripper::UPPER | FqcnStripper::UC));
    }

    public function testTrimsSinglePostfix(): void
    {
        $this->assertSame(
            'User',
            FqcnStripper::strip('App\\Entity\\UserDto', FqcnStripper::TRIM_POSTFIX)
        );
    }


    public function testTrimsMultipleStackedPostfixes(): void
    {
        $this->assertSame(
            'User',
            FqcnStripper::strip('App\\Entity\\UserHandlerDtoEvent', FqcnStripper::TRIM_POSTFIX)
        );
    }


    public function testTrimsPreservingCasing(): void
    {
        $this->assertSame(
            'MyÜser',
            FqcnStripper::strip('App\\Entity\\MyÜserFactoryEnum', FqcnStripper::TRIM_POSTFIX | FqcnStripper::MULTIBYTE)
        );
    }


    public function testTrimsAndLowercases(): void
    {
        $modifier = FqcnStripper::TRIM_POSTFIX | FqcnStripper::LOWER;

        $this->assertSame(
            'user',
            FqcnStripper::strip('App\\Entity\\UserFactory', $modifier)
        );
    }

    /**
     * @param array<int, mixed> $args
     * @throws ReflectionException
     */
    private function callPrivateStatic(string $method, array $args = []): mixed
    {
        $ref = new \ReflectionClass(FqcnStripper::class);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs(null, $args); // static = null
    }

    public function testTrimPostfixReflection(): void
    {
        $result = $this->callPrivateStatic('trimPostfix', ['OrderEventFactory', false]);

        $this->assertSame('Order', $result);
    }

    public function testStrOpReflectionLower(): void
    {
        $result = $this->callPrivateStatic('strOp', ['FOO', StringOperation::LOWER, false]);
        $this->assertSame('foo', $result);
    }

    public function testStrOpReflectionMbSub(): void
    {
        $result = $this->callPrivateStatic('strOp', ['Čokoláda', StringOperation::SUB, true, [4]]);
        $this->assertSame('láda', $result);
    }
}
