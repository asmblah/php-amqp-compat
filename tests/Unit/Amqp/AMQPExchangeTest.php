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

use AMQPChannel;
use AMQPChannelException;
use AMQPConnection;
use AMQPExchange;
use AMQPExchangeException;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use stdClass;

/**
 * Class AMQPExchangeTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPExchangeTest extends AbstractTestCase
{
    private MockInterface&AMQPChannel $amqpChannel;
    private AMQPExchange $amqpExchange;
    private MockInterface&ChannelInterface $channel;
    private MockInterface&AmqpChannelBridgeInterface $channelBridge;
    private MockInterface&LoggerInterface $logger;

    public function setUp(): void
    {
        $this->amqpChannel = mock(AMQPChannel::class);
        $this->channel = mock(ChannelInterface::class);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
        ]);
        $this->channelBridge = mock(AmqpChannelBridgeInterface::class, [
            'acquireChannel' => $this->channel,
            'getLogger' => $this->logger,
        ]);
        AmqpBridge::bridgeChannel($this->amqpChannel, $this->channelBridge);

        $this->amqpExchange = new AMQPExchange($this->amqpChannel);
    }

    public function testConstructorNotBeingCalledIsHandledCorrectly(): void
    {
        $extendedAmqpExchange = new class extends AMQPExchange {
            public function __construct()
            {
                // Deliberately omit the call to the super constructor.
            }
        };

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('Could not declare exchange. Stale reference to the channel object.');

        $extendedAmqpExchange->declareExchange();
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider bindDataProvider
     */
    public function testBindLogsAttemptAsDebug(
        string $exchangeName,
        string $sourceExchangeName,
        string $routingKey,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->channel->allows('bindExchange');

        $this->logger->expects()
            ->debug('AMQPExchange::bind(): Exchange bind attempt', [
                'arguments' => $arguments,
                'exchange_name' => $exchangeName,
                'flags' => $flags,
                'routing_key' => $routingKey,
                'source_exchange_name' => $sourceExchangeName,
            ])
            ->once();

        $this->amqpExchange->bind($sourceExchangeName, $routingKey, $arguments);
    }

    public function testBindLogsSuccessAsDebug(): void
    {
        $this->amqpExchange->setFlags(AMQP_NOPARAM);
        $this->amqpExchange->setName('my_exchange');
        $this->channel->allows('bindExchange');

        $this->logger->expects()
            ->debug('AMQPExchange::bind(): Exchange bound')
            ->once();

        $this->amqpExchange->bind('your_exchange', 'my_routing_key', []);
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider bindDataProvider
     */
    public function testBindGoesViaChannel(
        string $exchangeName,
        string $sourceExchangeName,
        string $routingKey,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);

        $this->channel->expects()
            ->bindExchange(
                $exchangeName,
                $sourceExchangeName,
                $routingKey,
                (bool) ($flags & AMQP_NOWAIT),
                $arguments,
                AMQPExchangeException::class,
                'AMQPExchange::bind'
            )
            ->once();

        static::assertTrue($this->amqpExchange->bind($sourceExchangeName, $routingKey, $arguments));
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider bindDataProvider
     */
    public function testBindHandlesExceptionCorrectly(
        string $exchangeName,
        string $sourceExchangeName,
        string $routingKey,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->channel->allows('bindExchange')
            ->andThrow(new AMQPExchangeException('my text'));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpExchange->bind($sourceExchangeName, $routingKey, $arguments);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function bindDataProvider(): array
    {
        return [
            [
                'my_exchange',
                'your_exchange',
                'my_routing_key',
                AMQP_NOWAIT,
                ['x-first' => 'one', 'x-second' => 'two'],
            ],
            [
                'exchange_a',
                'exchange_b',
                'their_routing_key',
                AMQP_NOPARAM,
                ['x-first' => 'eins', 'x-second' => 'zwei'],
            ],
        ];
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider declareExchangeDataProvider
     */
    public function testDeclareExchangeLogsAttemptAsDebug(
        string $exchangeName,
        string $exchangeType,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setArguments($arguments);
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->amqpExchange->setType($exchangeType);
        $this->channel->allows('declareExchange');

        $this->logger->expects()
            ->debug('AMQPExchange::declareExchange(): Exchange declaration attempt', [
                'arguments' => $arguments,
                'exchange_name' => $exchangeName,
                'exchange_type' => $exchangeType,
                'flags' => $flags,
            ])
            ->once();

        $this->amqpExchange->declareExchange();
    }

    public function testDeclareExchangeLogsSuccessAsDebug(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->amqpExchange->setType(AMQP_EX_TYPE_FANOUT);
        $this->channel->allows('declareExchange');

        $this->logger->expects()
            ->debug('AMQPExchange::declareExchange(): Exchange declared')
            ->once();

        $this->amqpExchange->declareExchange();
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider declareExchangeDataProvider
     */
    public function testDeclareExchangeDeclaresViaChannel(
        string $exchangeName,
        string $exchangeType,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setArguments($arguments);
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->amqpExchange->setType($exchangeType);

        $this->channel->expects()
            ->declareExchange(
                $exchangeName,
                $exchangeType,
                (bool) ($flags & AMQP_PASSIVE),
                (bool) ($flags & AMQP_DURABLE),
                (bool) ($flags & AMQP_AUTODELETE),
                (bool) ($flags & AMQP_INTERNAL),
                (bool) ($flags & AMQP_NOWAIT),
                $arguments,
                AMQPExchangeException::class,
                'AMQPExchange::declareExchange'
            )
            ->once();

        $this->amqpExchange->declareExchange();
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider declareExchangeDataProvider
     */
    public function testDeclareExchangeHandlesExceptionCorrectly(
        string $exchangeName,
        string $exchangeType,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setArguments($arguments);
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->amqpExchange->setType($exchangeType);
        $this->channel->allows('declareExchange')
            ->andThrow(new AMQPExchangeException('my text'));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpExchange->declareExchange();
    }

    /**
     * @return array<array<mixed>>
     */
    public static function declareExchangeDataProvider(): array
    {
        return [
            [
                'my_exchange',
                AMQP_EX_TYPE_FANOUT,
                AMQP_PASSIVE & AMQP_DURABLE,
                ['x-first' => 'one', 'x-second' => 'two'],
            ],
            [
                'your_exchange',
                AMQP_EX_TYPE_TOPIC,
                AMQP_INTERNAL,
                ['x-first' => 'eins', 'x-second' => 'zwei'],
            ],
        ];
    }

    /**
     * @dataProvider deleteExchangeDataProvider
     */
    public function testDeleteLogsAttemptAsDebugWhenExchangeNameGivenAsArgument(
        string $exchangeName,
        int $flags
    ): void {
        $this->channel->allows('deleteExchange');

        $this->logger->expects()
            ->debug('AMQPExchange::delete(): Exchange deletion attempt', [
                'exchange_name' => $exchangeName,
                'flags' => $flags,
            ])
            ->once();

        $this->amqpExchange->delete($exchangeName, $flags);
    }

    /**
     * @dataProvider deleteExchangeDataProvider
     */
    public function testDeleteLogsAttemptAsDebugWhenExchangeNameSetOnInstanceAndArgIsNull(
        string $exchangeName,
        int $flags
    ): void {
        $this->channel->allows('deleteExchange');
        $this->amqpExchange->setName($exchangeName);

        $this->logger->expects()
            ->debug('AMQPExchange::delete(): Exchange deletion attempt', [
                'exchange_name' => $exchangeName,
                'flags' => $flags,
            ])
            ->once();

        $this->amqpExchange->delete(null, $flags);
    }

    /**
     * @dataProvider deleteExchangeDataProvider
     */
    public function testDeleteLogsAttemptAsDebugWhenExchangeNameSetOnInstanceAndArgIsEmptyString(
        string $exchangeName,
        int $flags
    ): void {
        $this->channel->allows('deleteExchange');
        $this->amqpExchange->setName($exchangeName);

        $this->logger->expects()
            ->debug('AMQPExchange::delete(): Exchange deletion attempt', [
                'exchange_name' => $exchangeName,
                'flags' => $flags,
            ])
            ->once();

        $this->amqpExchange->delete('', $flags);
    }

    public function testDeleteLogsSuccessAsDebug(): void
    {
        $this->channel->allows('deleteExchange');

        $this->logger->expects()
            ->debug('AMQPExchange::delete(): Exchange deleted')
            ->once();

        $this->amqpExchange->delete('my_exchange', AMQP_NOPARAM);
    }

    /**
     * @dataProvider deleteExchangeDataProvider
     */
    public function testDeleteDeletesViaChannel(
        string $exchangeName,
        int $flags
    ): void {
        $this->amqpExchange->setName($exchangeName);

        $this->channel->expects()
            ->deleteExchange(
                $exchangeName,
                (bool) ($flags & AMQP_IFUNUSED),
                (bool) ($flags & AMQP_NOWAIT),
                AMQPExchangeException::class,
                'AMQPExchange::delete'
            )
            ->once();

        $this->amqpExchange->delete($exchangeName, $flags);
    }

    /**
     * @dataProvider deleteExchangeDataProvider
     */
    public function testDeleteHandlesExceptionCorrectly(
        string $exchangeName,
        int $flags
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->channel->allows('deleteExchange')
            ->andThrow(new AMQPExchangeException('my text'));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpExchange->delete($exchangeName, $flags);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function deleteExchangeDataProvider(): array
    {
        return [
            'AMQP_NOPARAM' => [
                'my_exchange',
                AMQP_NOPARAM,
            ],
            'AMQP_IFUNUSED' => [
                'my_exchange',
                AMQP_IFUNUSED,
            ],
            'AMQP_NOWAIT' => [
                'my_exchange',
                AMQP_NOWAIT,
            ],
            'AMQP_IFUNUSED | AMQP_NOWAIT' => [
                'my_exchange',
                AMQP_IFUNUSED | AMQP_NOWAIT,
            ],
        ];
    }

    public function testGetArgumentReturnsFalseForMissingArgument(): void
    {
        static::assertFalse($this->amqpExchange->getArgument('non-existent'));
    }

    public function testGetArgumentReturnsArgumentValue(): void
    {
        $this->amqpExchange->setArgument('x-delayed-type', 'direct');

        static::assertSame('direct', $this->amqpExchange->getArgument('x-delayed-type'));
    }

    public function testGetArgumentsReturnsAllArguments(): void
    {
        $this->amqpExchange->setArguments(['x-delayed-type' => 'direct', 'x-max-priority' => 10]);

        static::assertEquals(
            ['x-delayed-type' => 'direct', 'x-max-priority' => 10],
            $this->amqpExchange->getArguments()
        );
    }

    public function testGetChannelReturnsChannel(): void
    {
        static::assertSame($this->amqpChannel, $this->amqpExchange->getChannel());
    }

    public function testGetConnectionReturnsConnection(): void
    {
        $amqpConnection = mock(AMQPConnection::class);
        $this->amqpChannel->allows()
            ->getConnection()
            ->andReturn($amqpConnection);

        static::assertSame($amqpConnection, $this->amqpExchange->getConnection());
    }

    public function testGetFlagsReturnsFlags(): void
    {
        $this->amqpExchange->setFlags(AMQP_DURABLE);

        static::assertSame(AMQP_DURABLE, $this->amqpExchange->getFlags());
    }

    public function testGetNameReturnsExchangeName(): void
    {
        $this->amqpExchange->setName('my-exchange');

        static::assertSame('my-exchange', $this->amqpExchange->getName());
    }

    public function testGetTypeReturnsExchangeType(): void
    {
        $this->amqpExchange->setType(AMQP_EX_TYPE_TOPIC);

        static::assertSame(AMQP_EX_TYPE_TOPIC, $this->amqpExchange->getType());
    }

    public function testHasArgumentReturnsTrueForExistingArgument(): void
    {
        $this->amqpExchange->setArgument('x-delayed-type', 'direct');

        static::assertTrue($this->amqpExchange->hasArgument('x-delayed-type'));
        static::assertFalse($this->amqpExchange->hasArgument('x-max-priority'));
    }

    /**
     * @param array<string, mixed> $attributes
     * @dataProvider publishDataProvider
     */
    public function testPublishLogsAttemptAsDebug(
        string $exchangeName,
        int $flags,
        string $routingKey,
        string $message,
        array $attributes
    ): void {
        $this->amqpExchange->setName($exchangeName);
        $this->channel->allows('basicPublish');

        $this->logger->expects()
            ->debug('AMQPExchange::publish(): Message publish attempt', [
                'attributes' => $attributes,
                'exchange_name' => $exchangeName,
                'flags' => $flags,
                'message' => $message,
                'routing_key' => $routingKey,
            ])
            ->once();

        $this->amqpExchange->publish($message, $routingKey, $flags, $attributes);
    }

    public function testPublishLogsSuccessAsDebug(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->channel->allows('basicPublish');

        $this->logger->expects()
            ->debug('AMQPExchange::publish(): Message published')
            ->once();

        $this->amqpExchange->publish('my message');
    }

    public function testPublishPublishesViaChannelWhenGivenMessageOnly(): void
    {
        $this->amqpExchange->setName('my_exchange');

        $this->channel->expects()
            ->basicPublish(
                'my message',
                [],
                'my_exchange',
                '',
                false,
                false,
                AMQPExchangeException::class,
                'AMQPExchange::publish'
            )
            ->once();

        $this->amqpExchange->publish('my message');
    }

    public function testPublishPassesRawMessageAndHeadersToChannel(): void
    {
        $this->amqpExchange->setName('my_exchange');

        $this->channel->expects()
            ->basicPublish(
                'my message',
                ['x-my-attribute' => 'my value'],
                'my_exchange',
                '',
                false,
                false,
                AMQPExchangeException::class,
                'AMQPExchange::publish'
            )
            ->once();

        $this->amqpExchange->publish('my message', headers: ['x-my-attribute' => 'my value']);
    }

    /**
     * @param array<string, mixed> $attributes
     * @dataProvider publishDataProvider
     */
    public function testPublishHandlesExceptionCorrectly(
        string $exchangeName,
        int $flags,
        string $routingKey,
        string $message,
        array $attributes
    ): void {
        $this->amqpExchange->setName($exchangeName);
        $this->channel->allows('basicPublish')
            ->andThrow(new AMQPExchangeException('your text'));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('your text');

        $this->amqpExchange->publish($message, $routingKey, $flags, $attributes);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function publishDataProvider(): array
    {
        return [
            [
                'my_exchange',
                AMQP_IMMEDIATE,
                'my_routing_key',
                'this is my first message',
                ['x-first' => 'one', 'x-second' => 'two'],
            ],
            [
                'my_exchange',
                AMQP_MANDATORY,
                'my_routing_key',
                'this is my second message',
                ['x-first' => 'I am 1', 'x-second' => 'I am 2'],
            ],
        ];
    }

    public function testSetArgumentThrowsWhenGivenInvalidValue(): void
    {
        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('The value parameter must be of type NULL, int, double or string.');

        $this->amqpExchange->setArgument('my_key', new stdClass);
    }

    public function testSetArgumentsSetsAllGivenArguments(): void
    {
        $this->amqpExchange->setArguments(['first_key' => 21, 'second_key' => 'my value']);

        static::assertEquals(
            ['first_key' => 21, 'second_key' => 'my value'],
            $this->amqpExchange->getArguments()
        );
    }

    public function testSetArgumentRemovesHeaderWhenGivenValueIsNull(): void
    {
        $this->amqpExchange->setArguments(['first_key' => 21, 'second_key' => 'my value']);

        $this->amqpExchange->setArgument('first_key', null);

        static::assertEquals(
            ['second_key' => 'my value'],
            $this->amqpExchange->getArguments()
        );
        static::assertFalse($this->amqpExchange->hasArgument('first_key'));
    }

    public function testSetNameRaisesExceptionWhenNameExceeds255Characters(): void
    {
        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Invalid exchange name given, must be less than 255 characters long.');

        $this->amqpExchange->setName(str_repeat('x', 256));
    }

    // Note that this is unlike the error message suggests.
    public function testSetNameDoesNotRaiseExceptionWhenNameIsExactly255Characters(): void
    {
        $validLongName = str_repeat('x', 255);

        $this->amqpExchange->setName($validLongName);

        static::assertSame($validLongName, $this->amqpExchange->getName());
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider unbindDataProvider
     */
    public function testUnbindLogsAttemptAsDebug(
        string $exchangeName,
        string $sourceExchangeName,
        string $routingKey,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->channel->allows('unbindExchange');

        $this->logger->expects()
            ->debug('AMQPExchange::unbind(): Exchange unbind attempt', [
                'arguments' => $arguments,
                'exchange_name' => $exchangeName,
                'flags' => $flags,
                'routing_key' => $routingKey,
                'source_exchange_name' => $sourceExchangeName,
            ])
            ->once();

        $this->amqpExchange->unbind($sourceExchangeName, $routingKey, $arguments);
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider unbindDataProvider
     */
    public function testUnbindGoesViaChannel(
        string $exchangeName,
        string $sourceExchangeName,
        string $routingKey,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);

        $this->channel->expects()
            ->unbindExchange(
                $exchangeName,
                $sourceExchangeName,
                $routingKey,
                (bool) ($flags & AMQP_NOWAIT),
                $arguments,
                AMQPExchangeException::class,
                'AMQPExchange::unbind'
            )
            ->once();

        static::assertTrue($this->amqpExchange->unbind($sourceExchangeName, $routingKey, $arguments));
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider unbindDataProvider
     */
    public function testUnbindHandlesExceptionCorrectly(
        string $exchangeName,
        string $sourceExchangeName,
        string $routingKey,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->channel->allows('unbindExchange')
            ->andThrow(new AMQPExchangeException('my text'));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpExchange->unbind($sourceExchangeName, $routingKey, $arguments);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function unbindDataProvider(): array
    {
        return [
            [
                'my_exchange',
                'your_exchange',
                'my_routing_key',
                AMQP_NOWAIT,
                ['x-first' => 'one', 'x-second' => 'two'],
            ],
            [
                'exchange_a',
                'exchange_b',
                'their_routing_key',
                AMQP_NOPARAM,
                ['x-first' => 'eins', 'x-second' => 'zwei'],
            ],
        ];
    }

    public function testUnbindLogsSuccessAsDebug(): void
    {
        $this->amqpExchange->setName('your_exchange');
        $this->channel->allows('unbindExchange');

        $this->logger->expects()
            ->debug('AMQPExchange::unbind(): Exchange unbound')
            ->once();

        $this->amqpExchange->unbind('my_exchange', 'my_routing_key');
    }
}
