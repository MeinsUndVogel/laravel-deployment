<?php

namespace muv\LaravelDeployment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GitDeployController
{
    /**
     * Überprüft, den Webhook von Github (überprüft den Hash) und ob es sich um den "richtigen" Branch handelt.
     * Wenn ja, wird eine Semaphor-Datei geschrieben, die dazu führt, dass der Cronjob (und damit das Deployment)
     * ausgeführt wird.
     *
     * @param Request $request
     * @return void
     */
    public function __invoke(Request $request)
    {
        if (!hash_equals(
            known_string: 'sha256=' . hash_hmac(algo: 'sha256', data: $request->getContent(), key: config(key: 'deployment.webhook-secret')),
            user_string: $request->header('X-Hub-Signature-256')
        )) {
            return;
        }

        if (!Str::endsWith($request->input('ref', ''), '/' . config('deployment.branch'))) {
            return;
        }

        /*
         * Semaphor in die Projekt-Root schreiben.
         * Dieser wird dann vom Shell-Script (dass jede Minute aufgerufen wird) gefunden.
         * Das Shell-Script führt dann die restliche Arbeit aus.
         */
        file_put_contents(base_path('/git-deploy.sem'), "-");
    }
}
