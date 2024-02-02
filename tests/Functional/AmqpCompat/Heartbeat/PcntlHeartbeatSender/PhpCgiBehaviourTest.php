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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\AmqpCompat\Heartbeat\PcntlHeartbeatSender;

use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use hollodotme\FastCGI\Client;
use hollodotme\FastCGI\Exceptions\FastCGIClientException;
use hollodotme\FastCGI\Requests\GetRequest;
use hollodotme\FastCGI\Requests\PostRequest;
use hollodotme\FastCGI\SocketConnections\NetworkSocket;

/**
 * Class PhpCgiBehaviourTest.
 *
 * Checks the behaviour of PcntlHeartbeatSender with php-cgi as the host process.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class PhpCgiBehaviourTest extends AbstractTestCase
{
    private const SERVER_HOST = '127.0.0.1';
    private const SERVER_PORT = 9877;

    private string $baseDir;
    private Client $fastCgiClient;
    private NetworkSocket $fastCgiConnection;
    /** @var array<int, resource> */
    private array $pipes = [];
    /**
     * @var resource
     */
    private $process;
    private string $wwwDir;

    public function setUp(): void
    {
        $this->baseDir = dirname(__DIR__, 5);
        $this->wwwDir = $this->baseDir . '/tests/Functional/Fixtures/PhpCgi/www';

        // Start long-running php-cgi process.
        $descriptorSpec = array(
            0 => array('pipe', 'r'), // Stdin.
            1 => array('pipe', 'w'), // Stdout.
            2 => array('pipe', 'w'), // Stderr.
        );

        // Spawn a long-running php-cgi process for handling FastCGI requests.
        $process = proc_open(
            sprintf(
                'PHP_FCGI_CHILDREN=0 PHP_FCGI_MAX_REQUESTS=1000 exec php-cgi -d open_basedir=%s -b %s:%s',
                $this->baseDir,
                self::SERVER_HOST,
                self::SERVER_PORT
            ),
            $descriptorSpec,
            $this->pipes
        );

        if ($process === false) {
            $this->fail('Failed to start php-cgi process.');
        }

        $this->process = $process;

        $this->fastCgiClient = new Client();
        $this->fastCgiConnection = new NetworkSocket(self::SERVER_HOST, self::SERVER_PORT);

        // Wait for php-cgi to be ready to receive FastCGI requests.
        for (;;) {
            try {
                $response = $this->fastCgiClient->sendRequest(
                    $this->fastCgiConnection,
                    new GetRequest('/', '')
                );
            } catch (FastCGIClientException) {
                $response = null;
            }

            if ($response && $response->getHeaderLine('Status') === '404 Not Found') {
                break;
            }

            $status = proc_get_status($this->process);

            if (!$status['running']) {
                $this->fail(
                    sprintf(
                        'php-cgi process exited unexpectedly with signal %d, stdout: "%s", stderr: "%s"',
                        $status['termsig'],
                        stream_get_contents($this->pipes[1]),
                        stream_get_contents($this->pipes[2])
                    )
                );
            }

            usleep(100000);
        }
    }

    public function tearDown(): void
    {
        fclose($this->pipes[0]);
        fclose($this->pipes[1]);
        fclose($this->pipes[2]);

        $status = proc_get_status($this->process);

        if (!$status['running']) {
            proc_close($this->process);

            $this->fail('php-cgi process had stopped unexpectedly, exit code was ' . $status['exitcode']);
        }

        proc_terminate($this->process, SIGTERM);
        usleep(100 * 1000);
        proc_terminate($this->process, SIGKILL);

        proc_close($this->process);
    }

    public function testPhpCgiProcessDoesNotReceiveLingeringSigalrmsWhileIdleAfterRequestEnds(): void
    {
        /*
         * Send first request, which will set SIGALRM to be triggered
         * for the php-cgi process while it waits to process the next request.
         */
        $response1 = $this->fastCgiClient->sendRequest(
            $this->fastCgiConnection,
            new PostRequest(
                $this->wwwDir . '/maybe_install_heartbeat_sender.php',
                'heartbeat_interval=2'
            )
        );

        /*
         * Sleep for slightly longer than the interval given for SIGALRM to ensure it is triggered.
         * If it has been left pending, then this will cause the php-cgi process to unexpectedly exit
         * with an exit code of SIGALRM (14).
         */
        sleep(3);

        $status = proc_get_status($this->process);

        static::assertFalse(
            $status['signaled'],
            'php-cgi process was terminated with signal ' . $status['termsig']
        );

        $response2 = $this->fastCgiClient->sendRequest(
            $this->fastCgiConnection,
            new PostRequest(
                $this->wwwDir . '/maybe_install_heartbeat_sender.php',
                ''
            )
        );

        static::assertSame(
            'Installed heartbeat sender with 2 second interval' . PHP_EOL .
            'Did not sleep' . PHP_EOL,
            $response1->getBody()
        );
        static::assertSame(
            'Did not install heartbeat sender' . PHP_EOL .
            'Did not sleep' . PHP_EOL,
            $response2->getBody()
        );
    }

    public function testPhpCgiProcessDoesNotReceiveLingeringSigalrmsDuringSubsequentRequests(): void
    {
        /*
         * Send first request, which will set SIGALRM to be triggered
         * for the php-cgi process while it waits to process the next request.
         */
        $response1 = $this->fastCgiClient->sendRequest(
            $this->fastCgiConnection,
            new PostRequest(
                $this->wwwDir . '/maybe_install_heartbeat_sender.php',
                'heartbeat_interval=2'
            )
        );

        /*
         * In a second request, sleep for slightly longer than the interval given for SIGALRM to ensure it is triggered.
         * If it has been left pending, then this will cause the php-cgi process to unexpectedly exit
         * with an exit code of SIGALRM (14).
         */
        $response2 = $this->fastCgiClient->sendRequest(
            $this->fastCgiConnection,
            new PostRequest(
                $this->wwwDir . '/maybe_install_heartbeat_sender.php',
                'sleep_duration=3'
            )
        );

        $status = proc_get_status($this->process);

        static::assertFalse(
            $status['signaled'],
            'php-cgi process was terminated with signal ' . $status['termsig']
        );
        static::assertSame(
            'Installed heartbeat sender with 2 second interval' . PHP_EOL .
            'Did not sleep' . PHP_EOL,
            $response1->getBody()
        );
        static::assertSame(
            'Did not install heartbeat sender' . PHP_EOL .
            'Slept for 3 seconds' . PHP_EOL,
            $response2->getBody()
        );
    }
}
