<?php

declare(strict_types=1);

namespace MUV\LaravelDeployment;

final class Deployer
{
    /** @var array<int, string> */
    private static array $commands = [
        'php artisan down',
        'git reset --hard',
        'git pull',
        'composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress --quiet',
        'npm ci --omit=dev --ignore-scripts',
        'npm run build',
        'php artisan optimize',
        'php artisan migrate --force',
        'php artisan up',
    ];

    /**
     * Entry point for the GitHub webhook.
     *
     * @internal Do not call this method manually in your application. It relies on the raw PHP input stream
     * and specific HTTP headers sent by GitHub to verify the payload signature.
     */
    public static function handleWebhook(): void
    {
        $projectRoot = self::getProjectRoot();
        self::loadEnvironment($projectRoot);

        $payload = file_get_contents('php://input');
        if ($payload === false) {
            self::abort(400, 'Cannot read payload.');
        }

        self::verifyWebhook($payload);
        self::verifyBranch($payload);

        self::spawnBackgroundProcess();

        http_response_code(200);
        echo 'Accepted';
    }

    /**
     * Internal entry point for the CLI background worker.
     *
     * @internal Must strictly be executed via the CLI SAPI. Calling this from a web request
     * will block the PHP process until the entire deployment finishes.
     */
    public static function runBackgroundWorker(): void
    {
        $projectRoot = self::getProjectRoot();
        self::loadEnvironment($projectRoot);
        self::executeDeployment($projectRoot);
    }

    /**
     * Entry point for manual deployment triggers (e.g., via a backend UI button).
     * Bypasses webhook validation and directly spawns the background worker.
     */
    public static function triggerAsync(): void
    {
        self::spawnBackgroundProcess();
    }

    /**
     * Spawns a background process to run the deployment.
     */
    private static function spawnBackgroundProcess(): void
    {
        $workerPath = dirname(__DIR__).'/bin/run-deploy.php';

        // Redirecting output and appending '&' is required to detach the child process,
        // allowing the current PHP script to terminate and respond to the HTTP request immediately.
        $command = sprintf(
            'php %s > /dev/null 2>&1 &',
            escapeshellarg($workerPath)
        );
        exec($command);
    }

    /**
     * Runs the deployment process.
     *
     * 1. Executes pre-deployment scripts (if any).
     * 2. Executes the deployment commands.
     * 3. Executes post-deployment scripts (if any).
     */
    private static function executeDeployment(string $projectRoot): void
    {
        $logFile = $projectRoot.'/storage/logs/deployment.log';

        // Using wb mode to truncate the file on start, ensuring we only keep logs of the latest run.
        $handle = fopen($logFile, 'wb');
        if ($handle === false) {
            // Last-resort fallback so a permissions issue never causes a completely silent failure.
            $handle = fopen(sys_get_temp_dir().'/deployment.log', 'wb');
            if ($handle === false) {
                return;
            }
        }

        $preSh = $projectRoot.'/deploy_pre.sh';
        if (file_exists($preSh)) {
            if (! self::runCommand($handle, $projectRoot, 'sh deploy_pre.sh')) {
                fclose($handle);

                return;
            }
        }

        $prePhp = $projectRoot.'/deploy_pre.php';
        if (file_exists($prePhp)) {
            $override = require $prePhp;

            if (is_array($override)) {
                self::$commands = [];
                foreach ($override as $cmd) {
                    if (is_string($cmd)) {
                        self::$commands[] = $cmd;
                    }
                }
            }
        }

        foreach (self::$commands as $command) {
            if (! self::runCommand($handle, $projectRoot, $command)) {
                fclose($handle);

                return;
            }
        }

        $postSh = $projectRoot.'/deploy_post.sh';
        if (file_exists($postSh)) {
            self::runCommand($handle, $projectRoot, 'sh deploy_post.sh');
        }

        $postPhp = $projectRoot.'/deploy_post.php';
        if (file_exists($postPhp)) {
            self::runCommand($handle, $projectRoot, 'php deploy_post.php');
        }

        fclose($handle);
    }

    /**
     * Executes a command and logs its output to the specified file handle.
     *
     * @param  resource  $handle  The file handle to write the log to.
     * @return bool true if the command was successful, false otherwise.
     */
    private static function runCommand(mixed $handle, string $cwd, string $command): bool
    {
        if (! is_resource($handle)) {
            return false;
        }

        $outputLines = [];
        $resultCode = 0;

        exec('cd '.escapeshellarg($cwd).' && '.$command.' 2>&1', $outputLines, $resultCode);

        $date = date('Y-m-d H:i:s');
        $outputStr = implode("\n", $outputLines);
        if ($outputStr === '') {
            $outputStr = 'NO_OUTPUT';
        }

        $logEntry = sprintf(
            "[%s] Command: %s\n[%s] Output: %s\n[%s] Return code: %d\n",
            $date, $command,
            $date, $outputStr,
            $date, $resultCode
        );

        fwrite($handle, $logEntry);

        return $resultCode === 0;
    }

    private static function getProjectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * Parse the .env file and load environment variables into $_ENV and $_SERVER.
     *
     * @param  string  $root  The root directory of the project.
     */
    private static function loadEnvironment(string $root): void
    {
        $envFile = $root.'/.env';
        if (! file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            if (str_starts_with(mb_trim($line), '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = mb_trim($key);
                $value = mb_trim(mb_trim($value), '"\'');

                // Prevents overriding actual server-level environment variables
                // injected by container orchestrators or web server configurations.
                if (! array_key_exists($key, $_SERVER) && ! array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }

    /**
     * Verifies the authenticity of incoming webhooks by comparing
     * the payload signature with the expected secret.
     */
    private static function verifyWebhook(string $payload): void
    {
        $expectedContentType = self::getEnvVariable('GITHUB_WEBHOOK_CONTENT_TYPE', 'application/json');
        $actualContentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if ($actualContentType !== $expectedContentType) {
            self::abort(400, 'Invalid Content-Type.');
        }

        $secret = self::getEnvVariable('GITHUB_WEBHOOK_SECRET');
        $signatureHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

        if (! is_string($signatureHeader) || $signatureHeader === '') {
            self::abort(401, 'Missing signature header.');
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        // hash_equals is required to mitigate timing attacks during signature comparison.
        if (! hash_equals($expectedSignature, $signatureHeader)) {
            self::abort(403, 'Invalid signature.');
        }
    }

    /**
     * Verifies the branch of the incoming webhook payload
     * against the expected branch specified in the environment.
     *
     * @see https://docs.github.com/en/webhooks/webhook-events-and-payloads#push
     */
    private static function verifyBranch(string $payload): void
    {
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            self::abort(400, 'Invalid JSON payload.');
        }

        $expectedBranch = self::getEnvVariable('DEPLOYMENT_BRANCH', 'deployment');

        $ref = $data['ref'] ?? '';
        if (! is_string($ref)) {
            self::abort(400, 'Missing ref in payload.');
        }

        $branch = basename($ref);
        if ($branch !== $expectedBranch) {
            // Returning 200 instead of an error code prevents the webhook provider from repeatedly retrying deliveries for ignored branches.
            self::abort(200, 'Ignored: Branch mismatch.');
        }
    }

    /**
     * Retrieves environment variables from the $_ENV, $_SERVER, and getenv() arrays.
     */
    private static function getEnvVariable(string $key, ?string $default = null): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if ($default !== null) {
            return $default;
        }

        self::abort(500, "Missing environment variable: $key");
    }

    /**
     * Aborts the HTTP request with the specified status code and message.
     */
    private static function abort(int $code, string $message): never
    {
        http_response_code($code);
        echo $message;
        exit(1);
    }
}
