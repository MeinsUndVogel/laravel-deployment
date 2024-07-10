<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GitDeployController
{
    /**
     * Überprüft, ob der Webhook von Github kommt (ob der Hash stimmt) und ob es sich um den "richtigen" Branch handelt.
     * Wenn ja, wird die Semaphor-Datei geschrieben, die dazu führt, dass der cron-Job (und damit das Deployment)
     * ausgeführt wird.
     */
    public function __invoke(Request $request)
    {
        if (!hash_equals(
            'sha256=' . hash_hmac('sha256', $request->getContent(), config('github.webhook-secret')),
            $request->header('X-Hub-Signature-256')
        )) {
            return;
        }
        if (!Str::endsWith($request->input('ref', ''), '/' . config('github.branch'))) {
            return;
        }

        /*
         * Semaphor in die Projekt-Root schreiben.
         * Dieser wird dann vom Shell-Script - dass jede Minute aufgerufen wird - gefunden.
         * Das Shell Script führt dann die Restliche arbeit aus.
         */
        file_put_contents(base_path('/git-deploy.sem'), "-");
    }
}
