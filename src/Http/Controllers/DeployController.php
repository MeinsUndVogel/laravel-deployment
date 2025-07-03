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

		// Verify the Content-Type (wrong content-type = no data)
		$contentType = $request->header('Content-Type');
		if (Str::lower($contentType) !== 'application/json') {
            return response('Invalid Content-Type. (application/json needed)', 403);
        }
			

        // Ignore if the branch is not the expected one
        if (!Str::endsWith($request->input('ref', ''), '/' . config('deployment.branch'))) {
            return response('Request received for a non-deployment branch(' .
				$request->input('ref', '').
				'/' .
				config('deployment.branch').		
				') No action taken.', 200);
        }

        /*
         * Starting the deployment script.
         * The script needs to be "outside" the Laravel-Project to not destroy a file which already executes.
         */
        $scriptFilePath = base_path('/git-deploy.php');
        try {
            // We run asynchronous
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows
                exec("start php {$scriptFilePath}");
            } else {
                // Linux/Unix/macOS
                exec("php {$scriptFilePath} > /dev/null 2>&1 &");
            }

            return response('Deployment started.', 200);
        } catch (Exception $e) {
            Log::error('Error starting Deployment.');

            return response('Error starting Deployment.', 500);
        }
    }
}
