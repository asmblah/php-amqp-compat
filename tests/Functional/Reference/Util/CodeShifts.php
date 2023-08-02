<?php

/*
 * PHP AMQP-Compat - php-amqp/ext-amqp compatibility.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/asmblah/php-amqp-compat/
 *
 * Released under the MIT license.
 * https://github.com/asmblah/php-amqp-compat/raw/master/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util;

use AMQPConnection;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpCodeShift\CodeShift;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Asmblah\PhpCodeShift\Shifter\Shift\Spec\FunctionHookShiftSpec;

/**
 * Class CodeShifts.
 *
 * Applies code shifts via PHP Code Shift to allow the reference implementation php-amqp/ext-amqp's
 * own test suite to be run against this library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class CodeShifts
{
    public static function install(): void
    {
        $codeShift = new CodeShift();

        // Pretend that ext-amqp, that we are emulating, is installed.
        $codeShift->shift(
            new FunctionHookShiftSpec(
                'extension_loaded',
                function ($original) {
                    return function (string $name) use ($original): bool {
                        return $name === 'amqp' || $original($name);
                    };
                }
            ),
            new FileFilter('*.php')
        );

        $codeShift->shift(
            new FunctionHookShiftSpec(
                'var_dump',
                function ($original) {
                    return function (...$vars) use ($original) {
                        foreach ($vars as $var) {
                            if ($var instanceof AMQPConnection) {
                                $connectionConfig = AmqpBridge::getConnectionConfig($var);

                                print <<<OUT
object(AMQPConnection)#1 (18) {
  ["login":"AMQPConnection":private]=>
  string(5) "{$connectionConfig->getUser()}"
  ["password":"AMQPConnection":private]=>
  string(5) "{$connectionConfig->getPassword()}"
  ["host":"AMQPConnection":private]=>
  string(9) "{$connectionConfig->getHost()}"
  ["vhost":"AMQPConnection":private]=>
  string(1) "{$connectionConfig->getVirtualHost()}"
  ["port":"AMQPConnection":private]=>
  int({$connectionConfig->getPort()})
  ["read_timeout":"AMQPConnection":private]=>
  float({$connectionConfig->getReadTimeout()})
  ["write_timeout":"AMQPConnection":private]=>
  float({$connectionConfig->getWriteTimeout()})
  ["connect_timeout":"AMQPConnection":private]=>
  float({$connectionConfig->getConnectionTimeout()})
  ["rpc_timeout":"AMQPConnection":private]=>
  float({$connectionConfig->getRpcTimeout()})
  ["channel_max":"AMQPConnection":private]=>
  int(10)
  ["frame_max":"AMQPConnection":private]=>
  int(10240)
  ["heartbeat":"AMQPConnection":private]=>
  int(5)
  ["cacert":"AMQPConnection":private]=>
  string(0) ""
  ["key":"AMQPConnection":private]=>
  string(0) ""
  ["cert":"AMQPConnection":private]=>
  string(0) ""
  ["verify":"AMQPConnection":private]=>
  bool(true)
  ["sasl_method":"AMQPConnection":private]=>
  int(0)
  ["connection_name":"AMQPConnection":private]=>
  NULL
}

OUT;

                                return;
                            }

                            $original($var);
                        }
                    };
                }
            ),
            new FileFilter('*.php')
        );
    }
}
