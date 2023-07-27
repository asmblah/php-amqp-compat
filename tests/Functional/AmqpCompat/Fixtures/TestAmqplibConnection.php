<?php

declare(strict_types=1);

namespace Asmblah\PhpAmqpCompat\Tests\Functional\AmqpCompat\Fixtures;

use PhpAmqpLib\Connection\AbstractConnection;

class TestAmqplibConnection extends AbstractConnection
{
    public function __construct(TestAmqplibIo $io)
    {
        parent::__construct(
            'my_user',
            'mypass',
            '/my/vhost',
            false,
            'AMQPLAIN',
            null,
            'en_GB',
            $io/*,
            $heartbeat,
            $connection_timeout,
            $channel_rpc_timeout,
            $config*/
        );
    }
}
