<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use muv\LaravelDeployment\Http\Controllers\DeployController;

// Call this route with a GitHub webhook for push events
Route::post('/git-deploy', DeployController::class)->withoutMiddleware(ValidateCsrfToken::class);
