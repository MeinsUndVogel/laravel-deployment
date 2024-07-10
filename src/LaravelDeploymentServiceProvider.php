<?php

namespace muv\LaravelDeployment;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use muv\LaravelDeployment\Commands\LaravelDeploymentCommand;

class LaravelDeploymentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-deployment')
            ->hasConfigFile()
            ->hasCommand(LaravelDeploymentCommand::class);
    }
}
