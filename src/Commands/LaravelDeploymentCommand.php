<?php

namespace muv\LaravelDeployment\Commands;

use Illuminate\Console\Command;

class LaravelDeploymentCommand extends Command
{
    public $signature = 'laravel-deployment:install';

    public function handle(): int
    {
        echo shell_exec('php artisan vendor:publish --tag="deployment-config"');
        echo shell_exec('php artisan vendor:publish --tag="laravel-deployment-scripts"');
        $this->comment('All done');
        return self::SUCCESS;
    }
}
