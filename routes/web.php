<?php
// Die Route, die durch GitGub immer dann aufgerufen wird, wenn etwas ins Repository auf dem Deployment-Branch gepushed wird.
use App\Http\Controllers\GitDeployController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

Route::post('/git-deploy', [GitDeployController::class])->withoutMiddleware(ValidateCsrfToken::class);
