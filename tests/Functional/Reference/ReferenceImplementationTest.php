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
use Asmblah\PhpAmqpCompat\Tests\Functional\AbstractFunctionalTestCase;
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
    public const UNSUPPORTED_TESTS = [
        '003-channel-consumers.phpt',
        '004-queue-consume-nested.phpt',
        '004-queue-consume-orphaned.phpt',
        'amqpchannel_basicRecover.phpt',
        'amqpchannel_close.phpt',
        'amqpchannel_confirmSelect.phpt',
        'amqpchannel_construct_ini_global_prefetch_count.phpt',
        'amqpchannel_construct_ini_global_prefetch_size.phpt',
        'amqpchannel_construct_ini_prefetch_count.phpt',
        'amqpchannel_construct_ini_prefetch_size.phpt',
        'amqpchannel_getChannelId.phpt',
        'amqpchannel_multi_channel_connection.phpt',
        'amqpchannel_set_global_prefetch_count.phpt',
        'amqpchannel_set_global_prefetch_size.phpt',
        'amqpchannel_set_prefetch_and_global_prefetch_count.phpt',
        'amqpchannel_set_prefetch_and_global_prefetch_size.phpt',
        'amqpchannel_set_prefetch_count.phpt',
        'amqpchannel_set_prefetch_size.phpt',
        'amqpchannel_var_dump.phpt',
        'amqpconnection_heartbeat.phpt',
        'amqpconnection_heartbeat_with_consumer.phpt',
        'amqpconnection_heartbeat_with_persistent.phpt',
        'amqpconnection_persistent_construct_basic.phpt',
        'amqpconnection_persistent_in_use.phpt',
        'amqpconnection_persistent_reusable.phpt',
        'amqpconnection_setSaslMethod.phpt',
        'amqpconnection_setSaslMethod_invalid.phpt',
        'amqpconnection_setTimeout_deprecated.phpt',
        'amqpconnection_setTimeout_float.phpt',
        'amqpconnection_setTimeout_int.phpt',
        'amqpconnection_setTimeout_out_of_range.phpt',
        'amqpconnection_setTimeout_string.phpt',
        'amqpconnection_toomanychannels.phpt',
        'amqpconnection_var_dump.phpt',
        'amqpenvelope_get_accessors.phpt',
        'amqpexchange_get_channel.phpt',
        'amqpexchange_publish_confirms.phpt',
        'amqpexchange_publish_confirms_consume.phpt',
        'amqpexchange_publish_mandatory.phpt',
        'amqpexchange_publish_mandatory_consume.phpt',
        'amqpexchange_publish_mandatory_multiple_channels_pitfal.phpt',
        'amqpexchange_publish_with_decimal_header.phpt', // TODO: Needs ValueProcessor for AMQPDecimal support.
        'amqpexchange_publish_with_null.phpt',
        'amqpexchange_publish_with_properties.phpt',
        'amqpexchange_publish_with_properties_ignore_num_header.phpt',
        'amqpexchange_publish_with_properties_ignore_unsupported_header_values.phpt',
        'amqpexchange_publish_with_properties_nested_header.phpt',
        'amqpexchange_publish_with_properties_user_id_failure.phpt',
        'amqpexchange_publish_with_timestamp_header.phpt', // TODO: Needs ValueProcessor for AMQPExchange support.
        'amqpexchange_publish_xdeath.phpt',
        'amqpexchange_setArgument.phpt',
        'amqpexchange_set_flags.phpt',
        'amqpexchange_var_dump.phpt',
        'amqpqueue-cancel-no-consumers.phpt',
        'amqpqueue_bind_basic_headers_arguments.phpt',
        'amqpqueue_cancel.phpt',
        'amqpqueue_consume_basic.phpt',
        'amqpqueue_consume_multiple.phpt',
        'amqpqueue_consume_multiple_no_doubles.phpt',
        'amqpqueue_consume_nonexistent.phpt',
        'amqpqueue_consume_timeout.phpt',
        'amqpqueue_empty_name.phpt',
        'amqpqueue_get_basic.phpt',
        'amqpqueue_get_channel.phpt',
        'amqpqueue_get_empty_body.phpt',
        'amqpqueue_get_headers.phpt',
        'amqpqueue_get_nonexistent.phpt',
        'amqpqueue_headers_with_bool.phpt',
        'amqpqueue_headers_with_float.phpt',
        'amqpqueue_headers_with_null.phpt',
        'amqpqueue_nack.phpt',
        'amqpqueue_nested_arrays.phpt',
        'amqpqueue_nested_headers.phpt',
        'amqpqueue_setArgument.phpt',
        'amqpqueue_unbind_basic_empty_routing_key.phpt',
        'amqpqueue_unbind_basic_headers_arguments.phpt',
        'amqpqueue_var_dump.phpt',
        'amqptimestamp_php8.phpt',
        'bug_19707.phpt',
        'bug_62354.phpt',
        'bug_gh147.phpt',
        'bug_gh155_direct_reply_to.phpt',
        'bug_gh53-2.phpt',
        'bug_gh53.phpt',
        'bug_gh72-2.phpt',
        'package-version.phpt',
    ];
    /**
     * When the reference tests call var_dump(...), an exact object ID is expected.
     *
     * This ensures that the tests see the following:
     * `object(AMQPBasicProperties)#2 (14) {`
     *
     * rather than something like:
     * `object(AMQPBasicProperties)#1333 (14) {`
     */
    public const VAR_DUMP_OBJECT_IDS = [
        'amqpbasicproperties.php' => [1, 2],
        'amqpconnection_construct_with_limits.php' => [1],
        'amqpenvelope_construct.php' => [1],
        'amqpenvelope_var_dump.php' => [5, 5],
    ];

    /**
     * @dataProvider referenceImplementationTestProvider
     */
    public function testReferenceImplementationTest(string $path): void
    {
        if (in_array(basename($path), self::UNSUPPORTED_TESTS, true)) {
            $this->markTestSkipped('Not yet supported');
        }

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

        $phptFiles = glob($basePath . '/*.phpt', GLOB_BRACE);

        foreach ($phptFiles as $path) {
            yield basename($path) => [$path];
        }
    }
}
