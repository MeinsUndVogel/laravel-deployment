<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once dirname(__DIR__).'/src/Deployer.php';

MUV\LaravelDeployment\Deployer::runBackgroundWorker();
