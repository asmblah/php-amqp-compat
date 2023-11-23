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

/**
 * Class AmqpCompatPackage.
 *
 * Configures the installation of PHP AMQP-Compat.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpCompatPackage implements AmqpCompatPackageInterface
{
    /**
     * @inheritDoc
     */
    public function getPackageFacadeFqcn(): string
    {
        return AmqpCompat::class;
    }
}
