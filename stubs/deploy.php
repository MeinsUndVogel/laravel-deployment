<?php

declare(strict_types=1);

(function (): void {
    $currentDir = __DIR__;
    $found = false;

    $path = '';

    // Traverse upwards to circumvent hardcoded paths, ensuring the vendor directory
    // is located regardless of the project's public folder structure.
    while (true) {
        $path = $currentDir.'/vendor/muv/laravel-deployment/src/Deployer.php';

        if (file_exists($path)) {
            $found = true;
            break;
        }

        $parentDir = dirname($currentDir);
        if ($parentDir === $currentDir) {
            break;
        }
        $currentDir = $parentDir;
    }

    if (! $found) {
        http_response_code(500);
        echo 'Deployer class file not found.';
        exit(1);
    }

    require_once $path;

    MUV\LaravelDeployment\Deployer::handleWebhook();
})();
