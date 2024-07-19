<?php

namespace muv\LaravelDeployment\Commands;

use Illuminate\Console\Command;

class LaravelDeploymentCommand extends Command
{
    public $signature = 'laravel-deployment:install';

    /**
     * @return int
     */
    public function handle(): int
    {
        echo shell_exec(command: 'php artisan vendor:publish --tag="deployment-config"');
        echo shell_exec(command: 'php artisan vendor:publish --tag="laravel-deployment-scripts"');

        if ($this->confirm(question: 'Publish deployment documentation?', default: "yes")) {
            if (!file_exists(base_path('deployment.md'))) {
                copy(from: __DIR__ . '/../../deployment.md', to: base_path(path: 'deployment.md'));
            } else {
                $this->info('Deployment documentation already exists.');
            }
        }

        chmod(base_path(path: 'git-deploy.sh'), permissions: 0755);

        $this->comment('All done');
        return self::SUCCESS;
    }
}
