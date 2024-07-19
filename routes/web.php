<?php
// Die Route, die durch GitHub bei Push-Events im Repository aufgerufen wird
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use muv\LaravelDeployment\Http\Controllers\GitDeployController;

Route::post('/git-deploy', GitDeployController::class)->withoutMiddleware(ValidateCsrfToken::class);
