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

namespace Asmblah\PhpAmqpCompat;

use Nytris\Core\Package\PackageInterface;

/**
 * Interface AmqpCompatPackageInterface.
 *
 * Configures the installation of PHP AMQP-Compat.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface AmqpCompatPackageInterface extends PackageInterface
{
}
