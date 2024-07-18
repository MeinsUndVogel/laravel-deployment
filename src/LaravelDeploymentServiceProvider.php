<?php

namespace muv\LaravelDeployment;

use Illuminate\Filesystem\Filesystem;
use muv\LaravelDeployment\Commands\LaravelDeploymentCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelDeploymentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-deployment')
            ->hasConfigFile()
            ->hasRoute('web')
            ->hasCommand(LaravelDeploymentCommand::class);
    }

    /**
     * @return void
     */
    public function packageBooted(): void
    {
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../scripts/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("{$file->getFilename()}"),
                ], 'laravel-deployment-scripts');
            }
        }
    }
}
