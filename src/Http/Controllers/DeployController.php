<?php

namespace muv\LaravelDeployment\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeployController
{
    /**
     * Verifies the GitHub webhook (checks the hash) and ensures it targets the correct branch.
     * If valid, it writes a semaphore file to trigger the cron job (and thus the deployment).
     *
     * @param Request $request
     * @return Response
     */
    public function __invoke(Request $request): Response
    {
        // Verify the hash
        if (!hash_equals(
            known_string: 'sha256=' . hash_hmac(algo: 'sha256', data: $request->getContent(), key: config(key: 'deployment.webhook-secret')),
            user_string: $request->header('X-Hub-Signature-256')
        )) {
            return response('Invalid Signature', 403);
        }

        // Ignore if the branch is not the expected one
        if (!Str::endsWith($request->input('ref', ''), '/' . config('deployment.branch'))) {
            return response('Request received for a non-deployment branch. No action taken.', 200);
        }

        /*
         * Write a semaphore to the project root.
         * This will then be detected by a shell script (which is called every minute with cron)
         * and then performs the deployment tasks.
         */
        $semaphoreFilePath = base_path('/git-deploy.sem');
        try {
            file_put_contents($semaphoreFilePath, "-");

            return response('Deployment initiated.', 200);
        } catch (Exception $e) {
            Log::error('Error writing the semaphore file.', [
                'error' => $e->getMessage(),
                'file_path' => $semaphoreFilePath,
            ]);

            return response('Error writing the semaphore file.', 500);
        }
    }
}
