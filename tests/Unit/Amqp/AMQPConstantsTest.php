<?php

/*
 * PHP AMQP-Compat - php-amqp/ext-amqp compatibility.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/asmblah/php-amqp-compat/
 *
 * Released under the MIT license.
 * https://github.com/asmblah/php-amqp-compat/raw/main/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Asmblah\PhpAmqpCompat\Tests\Unit\Amqp;

use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Asmblah\PhpCodeShift\CodeShift;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Asmblah\PhpCodeShift\Shifter\Shift\Shift\FunctionHook\FunctionHookShiftSpec;
use Generator;
use RuntimeException;

/**
 * Class AMQPConstantsTest.
 *
 * Validates AMQP constants against the reference implementation php-amqp/ext-amqp.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPConstantsTest extends AbstractTestCase
{
    /**
     * @dataProvider deliveryModeConstantsProvider
     */
    public function testDeliveryModeConstantIsCorrectlyDefined(string $constantName, int $expectedValue): void
    {
        static::assertTrue(
            defined($constantName),
            "{$constantName} should be defined."
        );
        static::assertEquals(
            $expectedValue,
            constant($constantName),
            "{$constantName} should be {$expectedValue}."
        );
    }

    /**
     * Provides delivery mode constants with their expected AMQP standard values.
     *
     * These constants are not defined in the reference implementation
     * but are standard AMQP protocol values.
     */
    public static function deliveryModeConstantsProvider(): Generator
    {
        yield 'AMQP_DELIVERY_MODE_TRANSIENT' => ['AMQP_DELIVERY_MODE_TRANSIENT', 1];
        yield 'AMQP_DELIVERY_MODE_PERSISTENT' => ['AMQP_DELIVERY_MODE_PERSISTENT', 2];
    }

    /**
     * Tests that the expected constants match those actually defined by the stubs file.
     *
     * @dataProvider constantNameProvider
     */
    public function testAllConstantsAreDefinedMatchingReferenceImplementation(string $constantName): void
    {
        $referenceImplementationConstants = self::extractConstantsFromCImplementation();

        static::assertArrayHasKey(
            $constantName,
            $referenceImplementationConstants,
            "Constant {$constantName} is not defined in reference implementation."
        );
        static::assertTrue(
            defined($constantName),
            "Constant {$constantName} is not defined in userland PHP implementation."
        );
        static::assertSame($referenceImplementationConstants[$constantName], constant($constantName));
    }

    public static function constantNameProvider(): Generator
    {
        foreach ([
            // Basic AMQP flags.
            'AMQP_NOPARAM',
            'AMQP_JUST_CONSUME',
            'AMQP_DURABLE',
            'AMQP_PASSIVE',
            'AMQP_EXCLUSIVE',
            'AMQP_AUTODELETE',
            'AMQP_INTERNAL',
            'AMQP_NOLOCAL',
            'AMQP_AUTOACK',
            'AMQP_IFEMPTY',
            'AMQP_IFUNUSED',
            'AMQP_MANDATORY',
            'AMQP_IMMEDIATE',
            'AMQP_MULTIPLE',
            'AMQP_NOWAIT',
            'AMQP_REQUEUE',

            // Exchange types.
            'AMQP_EX_TYPE_DIRECT',
            'AMQP_EX_TYPE_FANOUT',
            'AMQP_EX_TYPE_TOPIC',
            'AMQP_EX_TYPE_HEADERS',

            // Other constants.
            'AMQP_OS_SOCKET_TIMEOUT_ERRNO',
            'PHP_AMQP_MAX_CHANNELS',
            'AMQP_SASL_METHOD_PLAIN',
            'AMQP_SASL_METHOD_EXTERNAL',
        ] as $constantName) {
            yield $constantName => [$constantName];
        }
    }

    /**
     * Extracts constants from the reference implementation stubs file using PHP Code Shift.
     *
     * @return array<string, mixed>
     */
    private static function extractConstantsFromCImplementation(): array
    {
        $constants = [];
        $stubsFile = dirname(__DIR__, 3) . '/var/ext/php-amqp/stubs/AMQP.php';

        if (!file_exists($stubsFile)) {
            throw new RuntimeException('Reference implementation stubs file not found.');
        }

        // Hook the define() function.
        $codeShift = new CodeShift();
        $codeShift->shift(
            new FunctionHookShiftSpec(
                'define',
                function () use (&$constants) {
                    return function (string $name, mixed $value) use (&$constants): bool {
                        $constants[$name] = $value;

                        return true;
                    };
                }
            ),
            new FileFilter($stubsFile)
        );

        // Include the stubs file, triggering hooked define() calls.
        include $stubsFile;

        $codeShift->uninstall();

        return $constants;
    }
}
