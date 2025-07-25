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

use Nytris\Boot\BootConfig;
use Nytris\Boot\PlatformConfig;
use Nytris\Nytris;
use Tasque\Core\Scheduler\ContextSwitch\TimeSliceStrategy;
use Tasque\EventLoop\TasqueEventLoopPackage;
use Tasque\TasquePackage;

require_once __DIR__ . '/../vendor/autoload.php';

Mockery::getConfiguration()->allowMockingNonExistentMethods(false);
Mockery::globalHelpers();

$bootConfig = new BootConfig(new PlatformConfig(dirname(__DIR__) . '/var/nytris/'));
$bootConfig->installPackage(new TasquePackage(new TimeSliceStrategy()));
$bootConfig->installPackage(new TasqueEventLoopPackage());

Nytris::boot($bootConfig);

// Check out the php-amqp/ext-amqp project in order to run its tests against this library in ReferenceImplementationTest.
$repo = 'https://github.com/php-amqp/php-amqp.git';
$checkoutPath = realpath(__DIR__ . '/..') . '/var/ext';
$ref = 'v1.11.0';
$dir = 'php-amqp';

exec(
    sprintf(
        'mkdir -p %s && cd %s && rm -rf %s && git clone %s --branch %s --single-branch %s 2>&1',
        $checkoutPath,
        $checkoutPath,
        $dir,
        $repo,
        $ref,
        $dir
    ),
    $stdoutLines,
    $exitCode
);

$stdout = implode("\n", $stdoutLines);

if ($exitCode !== 0) {
    throw new RuntimeException(
        sprintf(
            'git clone :: Non-zero exit code returned: %d, stdout: %s',
            $exitCode,
            $stdout
        )
    );
}
