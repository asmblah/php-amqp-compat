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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Connection\Config;

use Asmblah\PhpAmqpCompat\Connection\Config\DefaultConnectionConfig;
use Asmblah\PhpAmqpCompat\Connection\Config\DefaultConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\TimeoutDeprecationUsageEnum;
use Asmblah\PhpAmqpCompat\Misc\IniInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;

/**
 * Class DefaultConnectionConfigTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class DefaultConnectionConfigTest extends AbstractTestCase
{
    private ?DefaultConnectionConfig $config;
    /**
     * @var (MockInterface&IniInterface)|null
     */
    private $ini;

    public function setUp(): void
    {
        $this->ini = mock(IniInterface::class, [
            'getRawIniSetting' => false,
        ]);

        $this->config = new DefaultConnectionConfig($this->ini);
    }

    public function testGetConnectionNameReturnsNull(): void
    {
        // Cannot be configured by INI setting.
        static::assertNull($this->config->getConnectionName());
    }

    public function testGetConnectionTimeoutReturnsTimeoutWhenSetInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.connect_timeout')
            ->andReturn(123.4);

        static::assertSame(123.4, $this->config->getConnectionTimeout());
    }

    public function testGetConnectionTimeoutReturnsDefaultTimeoutWhenNotSetInIni(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_CONNECTION_TIMEOUT,
            $this->config->getConnectionTimeout()
        );
    }

    public function testGetHeartbeatIntervalReturnsIntervalWhenSetInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.heartbeat')
            ->andReturn(21);

        static::assertSame(21, $this->config->getHeartbeatInterval());
    }

    public function testGetHeartbeatIntervalReturnsDefaultIntervalWhenNotSetInIni(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_HEARTBEAT_INTERVAL,
            $this->config->getHeartbeatInterval()
        );
    }

    public function testGetHostReturnsHostWhenSetInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.host')
            ->andReturn('myhost');

        static::assertSame('myhost', $this->config->getHost());
    }

    public function testGetHostReturnsDefaultHostWhenNotSetInIni(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_HOST,
            $this->config->getHost()
        );
    }

    public function testGetPasswordReturnsPasswordWhenSetInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.password')
            ->andReturn('mypassword');

        static::assertSame('mypassword', $this->config->getPassword());
    }

    public function testGetPasswordReturnsDefaultPasswordWhenNotSetInIni(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_PASSWORD,
            $this->config->getPassword()
        );
    }

    public function testGetPortReturnsPortWhenSetInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.port')
            ->andReturn(5555);

        static::assertSame(5555, $this->config->getPort());
    }

    public function testGetPortReturnsDefaultPortWhenNotSetInIni(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_PORT,
            $this->config->getPort()
        );
    }

    public function testGetReadTimeoutCorrectlyHandlesOnlyNewCredentialBeingSetInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.read_timeout')
            ->andReturn(67.89);

        static::assertSame(67.89, $this->config->getReadTimeout());
        static::assertSame(
            TimeoutDeprecationUsageEnum::NOT_USED,
            $this->config->getDeprecatedTimeoutIniSettingUsage()
        );
    }

    public function testGetReadTimeoutCorrectlyHandlesOnlyDeprecatedCredentialBeingSetInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.timeout')
            ->andReturn(67.89);

        static::assertSame(67.89, $this->config->getReadTimeout());
        static::assertSame(
            TimeoutDeprecationUsageEnum::USED_ALONE,
            $this->config->getDeprecatedTimeoutIniSettingUsage()
        );
    }

    public function testGetReadTimeoutCorrectlyHandlesDeprecatedCredentialBeingShadowedInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.read_timeout')
            ->andReturn(12.34);
        $this->ini->allows()
            ->getRawIniSetting('amqp.timeout')
            ->andReturn(56.78);

        static::assertSame(12.34, $this->config->getReadTimeout());
        static::assertSame(
            TimeoutDeprecationUsageEnum::SHADOWED,
            $this->config->getDeprecatedTimeoutIniSettingUsage()
        );
    }

    public function testGetReadTimeoutReturnsDefaultReadTimeoutWhenNotSetInIni(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_READ_TIMEOUT,
            $this->config->getReadTimeout()
        );
    }

    public function testGetRpcTimeoutReturnsTimeoutWhenSetInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.rpc_timeout')
            ->andReturn(12.34);

        static::assertSame(12.34, $this->config->getRpcTimeout());
    }

    public function testGetRpcTimeoutReturnsDefaultTimeoutWhenNotSetInIni(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_RPC_TIMEOUT,
            $this->config->getRpcTimeout()
        );
    }

    public function testGetUserReturnsUserWhenSetInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.login')
            ->andReturn('myuser');

        static::assertSame('myuser', $this->config->getUser());
    }

    public function testGetUserReturnsDefaultUserWhenNotSetInIni(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_USER,
            $this->config->getUser()
        );
    }

    public function testGetVirtualHostReturnsVirtualHostWhenSetInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.vhost')
            ->andReturn('/my/vhost');

        static::assertSame('/my/vhost', $this->config->getVirtualHost());
    }

    public function testGetVirtualHostReturnsDefaultVirtualHostWhenNotSetInIni(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_VIRTUAL_HOST,
            $this->config->getVirtualHost()
        );
    }

    public function testGetWriteTimeoutReturnsTimeoutWhenSetInIni(): void
    {
        $this->ini->allows()
            ->getRawIniSetting('amqp.write_timeout')
            ->andReturn(45.67);

        static::assertSame(45.67, $this->config->getWriteTimeout());
    }

    public function testGetWriteTimeoutReturnsDefaultTimeoutWhenNotSetInIni(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_WRITE_TIMEOUT,
            $this->config->getWriteTimeout()
        );
    }
}
