<?php

declare(strict_types=1);

namespace Asmblah\PhpAmqpCompat\Tests\Functional\AmqpCompat\Fixtures;

use PhpAmqpLib\Wire\IO\AbstractIO;

class TestAmqplibIo extends AbstractIO
{
    /**
     * @var string[]
     */
    private $log = [];

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        $this->log[] = '->close()';
    }

    /**
     * @inheritDoc
     */
    public function connect(): void
    {
        $this->log[] = '->connect()';
    }

    /**
     * @inheritDoc
     */
    protected function do_select(?int $sec, int $usec)
    {
        $this->log[] = sprintf('->do_select(%d, %d)', $sec, $usec);

        return 21; // FIXME: What should result be?
    }

    /**
     * Fetches the log of I/O operations for test verification.
     *
     * @return string[]
     */
    public function getLog(): array
    {
        return $this->log;
    }

    /**
     * @inheritDoc
     */
    public function read($len): string
    {
        $this->log[] = sprintf('->read(%d)', $len);

        return '';
    }

    /**
     * @inheritDoc
     */
    public function write($data): void
    {
        $this->log[] = sprintf('->write(%s)', $data);
    }
}
