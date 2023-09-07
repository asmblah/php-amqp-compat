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

namespace Asmblah\PhpAmqpCompat\Tests;

use Asmblah\PhpAmqpCompat\AmqpManager;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Class AbstractTestCase.
 *
 * Base class for all test cases.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
abstract class AbstractTestCase extends PhpUnitTestCase
{
    use MockeryPHPUnitIntegration;

    public function resetAmqpManager(): void
    {
        AmqpManager::setAmqpIntegration(null);
        AmqpManager::setConfiguration(null);
    }
}
