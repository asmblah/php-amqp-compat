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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Reference;

use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Generator;

/**
 * Class ReferenceImplementationTest.
 *
 * Runs the tests of the reference implementation php-amqp/ext-amqp
 * against this compatibility layer.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ReferenceImplementationTest extends AbstractTestCase
{
    /**
     * @dataProvider referenceImplementationTestProvider
     */
    public function testReferenceImplementationTest(string $path): void
    {
        $runnerArguments = [];
        $testArguments = [
            // Load our bootstrap script before executing .phpt tests.
            '-d auto_prepend_file=' . __DIR__ . '/Util/bootstrap.php',

            // Show diffs between the expected and actual output on failure.
            '--show-diff',

            // Don't output ANSI colour escapes.
            '--no-color'
        ];

        $runTestsPath = realpath(__DIR__ . '/../../../var') . '/run-tests.php';

        if (!file_exists($runTestsPath)) {
            $phpBasePath = dirname(dirname(PHP_BINARY));
            $runTestsPaths = glob($phpBasePath . '/lib/php{,/**}/build/run-tests.php', GLOB_BRACE);

            if (empty($runTestsPaths)) {
                $this->fail('Failed to find run-tests.php');
            }

            // Multiple scripts may be found, if so they should be sorted so take the most recent one.
            $originalRunTestsPath = end($runTestsPaths);

            // Copy run-tests.php to a temp dir as files will need to be created alongside it (see below).
            copy($originalRunTestsPath, $runTestsPath);
        }

        $command = sprintf(
            '%s %s %s %s %s',
            PHP_BINARY,
            implode(' ', $runnerArguments),
            escapeshellarg($runTestsPath),
            implode(' ', $testArguments),
            escapeshellarg($path)
        );

        exec($command, $stdoutLines, $exitCode);
        $stdout = implode("\n", $stdoutLines);

        if (preg_match('/^Tests skipped   :    1 \(100\.0%\)/m', $stdout)) {
            // run-tests.php reported a skipped test.
            $this->markTestSkipped('Test skipped, stdout was: ' . $stdout);
        }

        if (!preg_match('/^Tests passed    :    1 \(100\.0%\)/m', $stdout)) {
            // run-tests.php reported a failed test.
            $this->fail('Test failed or warned, stdout was: ' . $stdout);
        }

        static::assertSame(0, $exitCode, 'Expected exit code 0, stdout was: ' . $stdout);
    }

    public static function referenceImplementationTestProvider(): Generator
    {
        $basePath = realpath(__DIR__ . '/../../../var/ext/php-amqp/tests');

        // TODO: Have all possible tests passing with `glob($basePath . '/*.phpt')`.
        $phptFiles = glob(
            $basePath . '/{amqpconnection_construct_ini_read_timeout,amqpconnection_construct_with_limits,amqpconnection_connect_login_failure,amqpdecimal}.phpt',
            GLOB_BRACE
        );

        foreach ($phptFiles as $path) {
            yield basename($path) => [$path];
        }
    }
}
