<?php

declare(strict_types=1);

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Heartbeat\PcntlHeartbeatSender;
use Asmblah\PhpAmqpCompat\Misc\Clock;

require_once dirname(__DIR__, 5) . '/vendor/autoload.php';

$heartbeatInterval = $_POST['heartbeat_interval'] ?? null;

if ($heartbeatInterval !== null) {
    $pcntlHeartbeatSender = new PcntlHeartbeatSender(new Clock());

    $pcntlHeartbeatSender->register(mock(AmqpConnectionBridgeInterface::class, [
        'getHeartbeatInterval' => $heartbeatInterval,
    ]));

    print 'Installed heartbeat sender with ' . $heartbeatInterval . ' second interval' . PHP_EOL;
} else {
    print 'Did not install heartbeat sender' . PHP_EOL;
}
