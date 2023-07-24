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

use Asmblah\PhpAmqpCompat\AmqpFactoryInterface;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectionConfigInterface;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPExceptionInterface;

/**
 * Class AMQPConnection.
 *
 * Emulates AMQPConnection from pecl-amqp.
 *
 * @see {@link https://github.com/php-amqp/php-amqp/blob/v1.11.0/stubs/AMQPConnection.php}
 */
class AMQPConnection
{
    private AmqpFactoryInterface $amqpFactory;
    private ?AbstractConnection $amqplibConnection = null;
    private ?AmqpConnectionBridgeInterface $connectionBridge = null;
    private ConnectionConfigInterface $connectionConfig;

    /**
     * Represents a connection to an AMQP broker.
     * A connection will not be established until AMQPConnection::connect() is called.
     *
     *  $credentials = array(
     *      'host'  => amqp.host The host to connect to. Note: Max 1024 characters.
     *      'port'  => amqp.port Port on the host.
     *      'vhost' => amqp.vhost The virtual host on the host. Note: Max 128 characters.
     *      'login' => amqp.login The login name to use. Note: Max 128 characters.
     *      'password' => amqp.password Password. Note: Max 128 characters.
     *      'read_timeout'  => Timeout in for incoming activity. Note: 0 or greater seconds. May be fractional.
     *      'write_timeout' => Timeout in for outgoing activity. Note: 0 or greater seconds. May be fractional.
     *      'connect_timeout' => Connection timeout. Note: 0 or greater seconds. May be fractional.
     *      'rpc_timeout' => RPC timeout. Note: 0 or greater seconds. May be fractional.
     *
     *      Connection tuning options (see http://www.rabbitmq.com/amqp-0-9-1-reference.html#connection.tune for details):
     *      'channel_max' => Specifies the highest channel number that the server permits. 0 means standard extension limit
     *                       (see PHP_AMQP_MAX_CHANNELS constant).
     *      'frame_max'   => The largest frame size that the server proposes for the connection, including frame header
     *                       and end-byte. 0 means standard extension limit (depends on librabbitmq def.ault frame size limit)
     *      'heartbeat'   => The delay, in seconds, of the connection heartbeat that the server wants.
     *                       0 means the server does not want a heartbeat.
     *
     *      TLS support (see https://www.rabbitmq.com/ssl.html for details):
     *      'cacert' => Path to the CA cert file in PEM format.
     *      'cert'   => Path to the client certificate in PEM format.
     *      'key'    => Path to the client key in PEM format.
     *      'verify' => Enables or disables peer verification. If peer verification is enabled then the common name in the
     *                  server certificate must match the server name. Peer verification is enabled by default.
     *
     *      'connection_name' => A user-determined name for the connections
     * )
     *
     * @param array $credentials Optional array of credential information for
     *                           connecting to the AMQP broker.
     */
    public function __construct(array $credentials = [])
    {
        $this->amqpFactory = AmqpManager::getAmqpFactory();

        $this->connectionConfig = $this->amqpFactory->createConnectionConfig($credentials);
    }

    /**
     * Establishes a transient connection with the AMQP broker.
     *
     * @return boolean TRUE on success or throw an exception on failure.
     * @throws AMQPConnectionException
     */
    public function connect(): bool
    {
        if ($this->amqplibConnection !== null) {
            return true; // Already connected.
        }

        try {
            $this->connectionBridge = $this->amqpFactory->connect($this->connectionConfig);
        } catch (AMQPExceptionInterface $exception) {
            // TODO: Handle errors identically to php-amqp.
            throw new AMQPConnectionException(__METHOD__ . ' failed: ' . $exception->getMessage());
        }

        AmqpBridge::bridgeConnection($this, $this->connectionBridge);
        $this->amqplibConnection = $this->connectionBridge->getAmqplibConnection();

        return true;
    }

    /**
     * Closes the transient connection with the AMQP broker.
     *
     * @return boolean true if the connection was successfully closed, false otherwise.
     * @throws Exception
     */
    public function disconnect(): bool
    {
        if ($this->amqplibConnection === null || !$this->amqplibConnection->isConnected()) {
            return true; // Nothing to do; not connected anyway.
        }

        // NB: No persistent connection support.

        try {
            $this->amqplibConnection->close();
        } catch (AMQPExceptionInterface $exception) {
            // TODO: Handle errors identically to php-amqp.
            throw new AMQPConnectionException(__METHOD__ . ' failed: ' . $exception->getMessage());
        }

        return true;
    }

    /**
     * Get path to the CA cert file in PEM format
     *
     * @return string
     */
    public function getCACert(): string
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Get path to the client certificate in PEM format
     *
     * @return string
     */
    public function getCert(): string
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * @return string|null
     */
    public function getConnectionName(): ?string
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Get number of seconds between heartbeats of the connection in seconds.
     *
     * When connection is connected, effective connection value returned, which is normally the same as original
     * correspondent value passed to constructor, otherwise original value passed to constructor returned.
     *
     * @return int
     */
    public function getHeartbeatInterval(): int
    {
        return $this->connectionConfig->getHeartbeatInterval();
    }

    /**
     * Fetches the configured host.
     *
     * @return string The configured hostname of the broker.
     */
    public function getHost(): string
    {
        return $this->connectionConfig->getHost();
    }

    /**
     * Get path to the client key in PEM format
     *
     * @return string
     */
    public function getKey(): string
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Fetches the configured login username.
     *
     * @return string The configured login username as a string.
     */
    public function getLogin(): string
    {
        return $this->connectionConfig->getUser();
    }

    /**
     * Fetches the maximum number of channels the connection can handle.
     *
     * When connection is connected, effective connection value returned, which is normally the same as original
     * correspondent value passed to constructor, otherwise original value passed to constructor returned.
     *
     * @return int
     */
    public function getMaxChannels(): int
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Get max supported frame size per connection in bytes.
     *
     * When connection is connected, effective connection value returned, which is normally the same as original
     * correspondent value passed to constructor, otherwise original value passed to constructor returned.
     *
     * @return int
     */
    public function getMaxFrameSize(): int
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Fetches the configured password.
     *
     * @return string The configured password as a string.
     */
    public function getPassword(): string
    {
        return $this->connectionConfig->getPassword();
    }

    /**
     * Fetches the configured port.
     *
     * @return int The configured port as an integer.
     */
    public function getPort(): int
    {
        return $this->connectionConfig->getPort();
    }

    /**
     * Fetches the configured amount of time (in seconds) to wait for incoming activity
     * from the AMQP broker.
     *
     * @return float
     */
    public function getReadTimeout(): float
    {
        return $this->connectionConfig->getReadTimeout();
    }

    /**
     * Fetches the configured amount of time (in seconds) to wait for RPC activity
     * to the AMQP broker.
     *
     * @return float
     */
    public function getRpcTimeout(): float
    {
        return $this->connectionConfig->getRpcTimeout();
    }

    /**
     * @return int
     */
    public function getSaslMethod(): int
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Fetches the configured amount of time to wait for incoming activity
     * from the AMQP broker.
     *
     * @deprecated use AMQPConnection::getReadTimeout() instead
     *
     * @return float
     */
    public function getTimeout(): float
    {
        trigger_error(
            'AMQPConnection::getTimeout() method is deprecated; ' .
            'use AMQPConnection::getReadTimeout() instead',
            E_USER_DEPRECATED
        );

        return $this->connectionConfig->getReadTimeout();
    }

    /**
     * Return last used channel id during current connection session.
     *
     * @return int
     */
    public function getUsedChannels(): int
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Get whether peer verification enabled or disabled
     *
     * @return bool
     */
    public function getVerify(): bool
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Fetches the configured vhost.
     *
     * @return string The configured virtual host as a string.
     */
    public function getVhost(): string
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Fetches the configured interval of time (in seconds) to wait for outcome activity
     * to AMQP broker
     *
     * @return float
     */
    public function getWriteTimeout(): float
    {
        return $this->connectionConfig->getWriteTimeout();
    }

    /**
     * Checks whether the connection to the AMQP broker is still valid.
     *
     * It does so by checking the return status of the last connect-command.
     *
     * @return boolean True if connected, false otherwise.
     */
    public function isConnected(): bool
    {
        return $this->amqplibConnection !== null;
    }

    /**
     * Determines whether the connection is persistent.
     *
     * Returns false when not connected.
     *
     * @return bool
     */
    public function isPersistent(): bool
    {
        // TODO: Not sure persistent connections are possible from userland?
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Establishes a persistent connection with the AMQP broker.
     *
     * This method will initiate a connection with the AMQP broker
     * or reuse an existing one if present.
     *
     * @return boolean TRUE on success or throws an exception on failure.
     * @throws AMQPConnectionException
     */
    public function pconnect(): bool
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Closes a persistent connection with the AMQP broker.
     *
     * @return boolean true if a connection was found and closed,
     *                 false if no persistent connection with this host,
     *                 port, vhost and login could be found.
     */
    public function pdisconnect(): bool
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Closes any open persistent connections and initiates a new one with the AMQP broker.
     *
     * Note this means "p(ersistent)-reconnect" and not "pre-connect".
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function preconnect(): bool
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Closes any open transient connections and initiates a new one with the AMQP broker.
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function reconnect(): bool
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Set path to the CA cert file in PEM format
     *
     * @param string $cacert
     */
    public function setCACert(string $cacert): void
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Set path to the client certificate in PEM format
     *
     * @param string $cert
     */
    public function setCert(string $cert): void
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * @param string|null $connection_name
     */
    public function setConnectionName(?string $connection_name): void
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Sets the hostname used to connect to the AMQP broker.
     *
     * @param string $host The hostname of the AMQP broker.
     *
     * @throws AMQPConnectionException If host is longer than 1024 characters.
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function setHost(string $host): bool
    {
        if (strlen($host) > 1024) {
            throw new AMQPConnectionException('Invalid \'host\' given, exceeds 1024 character limit.');
        }

        $this->connectionConfig->setHost($host);

        return true;
    }

    /**
     * Set path to the client key in PEM format
     *
     * @param string $key
     */
    public function setKey(string $key): void
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Sets the login username used to connect to the AMQP broker.
     *
     * @param string $login The login username used to authenticate
     *                      with the AMQP broker.
     *
     * @throws AMQPConnectionException If login username is longer than 128 characters.
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function setLogin(string $login): bool
    {
        if (strlen($login) > 128) {
            throw new AMQPConnectionException('Invalid \'login\' given, exceeds 128 characters limit.');
        }

        $this->connectionConfig->setUser($login);

        return true;
    }

    /**
     * Sets the password string used to connect to the AMQP broker.
     *
     * @param string $password The password string used to authenticate
     *                         with the AMQP broker.
     *
     * @throws AMQPConnectionException If password is longer than 128 characters.
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function setPassword(string $password): bool
    {
        if (strlen($password) > 128) {
            throw new AMQPConnectionException('Invalid \'password\' given, exceeds 128 characters limit.');
        }

        $this->connectionConfig->setPassword($password);

        return true;
    }

    /**
     * Sets the port used to connect to the AMQP broker.
     *
     * @param integer $port The port used to connect to the AMQP broker.
     *
     * @throws AMQPConnectionException If port is not between 1 and 65535.
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function setPort(int $port): bool
    {
        if ($port < 1 || $port > 65535) {
            throw new AMQPConnectionException('Invalid port given. Value must be between 1 and 65535.');
        }

        $this->connectionConfig->setPort($port);

        return true;
    }

    /**
     * Sets the interval of time (in seconds) to wait for incoming activity
     * from the AMQP broker.
     *
     * @param float $timeout
     *
     * @throws AMQPConnectionException If timeout is less than 0.
     *
     * @return bool
     */
    public function setReadTimeout(float $timeout): bool
    {
        if ($timeout < 0) {
            throw new AMQPConnectionException('Parameter \'read_timeout\' must be greater than or equal to zero.');
        }

        if ($this->amqplibConnection !== null && $this->amqplibConnection->isConnected()) {
            // Close the connection if already open.
            $this->amqplibConnection->close();

            return false;
        }

        $this->connectionConfig->setReadTimeout($timeout);

        return true;
    }

    /**
     * Sets the interval of time to wait (in seconds) for RPC activity to the AMQP broker.
     *
     * @param float $timeout
     * @return bool
     * @throws AMQPConnectionException If timeout is less than 0.
     */
    public function setRpcTimeout(float $timeout): bool
    {
        if ($timeout < 0) {
            throw new AMQPConnectionException(
                'Parameter \'rpc_timeout\' must be greater than or equal to zero.'
            );
        }

        if ($this->amqplibConnection !== null && $this->amqplibConnection->isConnected()) {
            // Close the connection if already open.
            $this->amqplibConnection->close();

            return false;
        }

        $this->connectionConfig->setRpcTimeout($timeout);

        return true;
    }

    /**
     * Sets the authentication method for the connection.
     *
     * @param int $method AMQP_SASL_METHOD_PLAIN | AMQP_SASL_METHOD_EXTERNAL
     */
    public function setSaslMethod(int $method): void
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Sets the interval of time to wait for incoming activity from the AMQP broker.
     *
     * @deprecated use AMQPConnection::setReadTimeout($timeout) instead.
     *
     * @param float $timeout
     *
     * @throws AMQPConnectionException If timeout is less than 0.
     *
     * @return bool
     */
    public function setTimeout(float $timeout): bool
    {
        trigger_error(
            'AMQPConnection::setTimeout($timeout) method is deprecated; ' .
            'use AMQPConnection::setReadTimeout($timeout) instead',
            E_USER_DEPRECATED
        );

        if ($timeout < 0) {
            throw new AMQPConnectionException('Parameter \'timeout\' must be greater than or equal to zero.');
        }

        if ($this->amqplibConnection !== null && $this->amqplibConnection->isConnected()) {
            // Close the connection if already open.
            $this->amqplibConnection->close();

            return false;
        }

        $this->connectionConfig->setReadTimeout($timeout);

        return true;
    }

    /**
     * Enables or disables peer verification.
     *
     * @param bool $verify
     */
    public function setVerify(bool $verify): void
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Sets the virtual host to connect to on the AMQP broker.
     *
     * @param string $vhost The virtual host to use on the AMQP
     *                      broker.
     *
     * @throws AMQPConnectionException If host is longer than 32 characters.
     *
     * @return boolean true on success or false on failure.
     */
    public function setVhost(string $vhost): bool
    {
        if (strlen($vhost) > 128) {
            throw new AMQPConnectionException('Parameter \'vhost\' exceeds 128 characters limit.');
        }

        $this->connectionConfig->setVirtualHost($vhost);

        return true;
    }

    /**
     * Sets the interval of time (in seconds) to wait for outgoing activity to the AMQP broker.
     *
     * @param float $timeout
     *
     * @throws AMQPConnectionException If timeout is less than 0.
     *
     * @return bool
     */
    public function setWriteTimeout(float $timeout): bool
    {
        if ($timeout < 0) {
            throw new AMQPConnectionException(
                'Parameter \'write_timeout\' must be greater than or equal to zero.'
            );
        }

        if ($this->amqplibConnection !== null && $this->amqplibConnection->isConnected()) {
            // Close the connection if already open.
            $this->amqplibConnection->close();

            return false;
        }

        $this->connectionConfig->setWriteTimeout($timeout);

        return true;
    }
}
