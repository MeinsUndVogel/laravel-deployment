<?php

namespace muv\LaravelDeployment\Commands;

use Illuminate\Console\Command;

class LaravelDeploymentCommand extends Command
{
    public $signature = 'laravel-deployment';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
