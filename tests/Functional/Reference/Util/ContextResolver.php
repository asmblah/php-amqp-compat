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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util;

/**
 * Class ContextResolver.
 *
 * Resolves the applicable reference implementation test file & line context.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ContextResolver
{
    /**
     * Fetches the userland file & line context of the actual test file
     * as would be reported by php-amqp/ext-amqp.
     *
     * @returns array{file: ?string, line: ?int}
     */
    public function getContext(): array
    {
        $file = null;
        $line = null;
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Look for a frame that is inside a script run by the reference implementation tests.
        $prefix = realpath(__DIR__ . '/../../../../var/ext/php-amqp/tests/');

        foreach ($backtrace as $frame) {
            $frameFile = $frame['file'] ?? null;

            if ($frameFile !== null && str_starts_with($frameFile, $prefix) && !str_ends_with($frameFile, '.inc')) {
                $frameLine = (int) $frame['line'];

                $file = $frameFile;
                $line = $frameLine;
                break;
            }
        }

        return ['file' => $file, 'line' => $line];
    }
}
