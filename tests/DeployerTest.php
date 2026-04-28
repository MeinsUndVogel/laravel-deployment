<?php

declare(strict_types=1);

use MUV\LaravelDeployment\Deployer;

/**
 * Invoke a private static method via reflection
 *
 * @throws ReflectionException
 */
function callPrivate(string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod(Deployer::class, $method))->invoke(null, ...$args);
}

/**
 * Run a PHP snippet in a child process; returns [exitCode, stdout+stderr]
 */
function runInSubprocess(string $phpCode): array
{
    $autoload = dirname(__DIR__).'/vendor/autoload.php';
    $tmp = tempnam(sys_get_temp_dir(), 'pest_');
    file_put_contents($tmp, '<?php require '.var_export($autoload, true).";\n".$phpCode);
    exec(PHP_BINARY.' '.escapeshellarg($tmp).' 2>&1', $lines, $code);
    unlink($tmp);

    return [$code, implode("\n", $lines)];
}

function makeTempDir(): string
{
    $dir = sys_get_temp_dir().'/deployer_test_'.uniqid();
    mkdir($dir, 0777, true);

    return $dir;
}

function removeTempDir(string $dir): void
{
    foreach (array_merge(glob($dir.'/*') ?: [], glob($dir.'/.*') ?: []) as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    @rmdir($dir);
}

describe('getEnvVariable', function () {
    afterEach(function () {
        unset($_ENV['DEPLOY_TEST'], $_SERVER['DEPLOY_TEST']);
    });

    it('reads from $_ENV', function () {
        $_ENV['DEPLOY_TEST'] = 'env_value';
        expect(callPrivate('getEnvVariable', 'DEPLOY_TEST'))->toBe('env_value');
    });

    it('reads from $_SERVER when not in $_ENV', function () {
        unset($_ENV['DEPLOY_TEST']);
        $_SERVER['DEPLOY_TEST'] = 'server_value';
        expect(callPrivate('getEnvVariable', 'DEPLOY_TEST'))->toBe('server_value');
    });

    it('returns the default when variable is missing', function () {
        expect(callPrivate('getEnvVariable', 'DEPLOY_TEST', 'fallback'))->toBe('fallback');
    });

    it('aborts with exit code 1 when variable is missing and no default is given', function () {
        [$code, $output] = runInSubprocess(<<<'PHP'
            unset($_ENV['DEPLOY_TEST'], $_SERVER['DEPLOY_TEST']);
            (new ReflectionMethod(\MUV\LaravelDeployment\Deployer::class, 'getEnvVariable'))
                ->invoke(null, 'DEPLOY_TEST');
        PHP);

        expect($code)->toBe(1)
            ->and($output)->toContain('Missing environment variable: DEPLOY_TEST');
    });
});

describe('loadEnvironment', function () {
    beforeEach(function () {
        $this->dir = makeTempDir();
    });

    afterEach(function () {
        removeTempDir($this->dir);
        unset(
            $_ENV['LDE_KEY'], $_SERVER['LDE_KEY'],
            $_ENV['LDE_QUOTED'], $_SERVER['LDE_QUOTED'],
            $_ENV['LDE_SKIP'], $_SERVER['LDE_SKIP'],
        );
    });

    it('does nothing when the .env file does not exist', function () {
        $before = $_ENV;
        callPrivate('loadEnvironment', $this->dir);
        expect($_ENV)->toBe($before);
    });

    it('parses key=value pairs into $_ENV and $_SERVER', function () {
        file_put_contents($this->dir.'/.env', "LDE_KEY=hello\n");
        callPrivate('loadEnvironment', $this->dir);
        expect($_ENV['LDE_KEY'])->toBe('hello')
            ->and($_SERVER['LDE_KEY'])->toBe('hello');
    });

    it('strips surrounding double quotes from values', function () {
        file_put_contents($this->dir.'/.env', 'LDE_QUOTED="quoted value"'."\n");
        callPrivate('loadEnvironment', $this->dir);
        expect($_ENV['LDE_QUOTED'])->toBe('quoted value');
    });

    it('strips surrounding single quotes from values', function () {
        file_put_contents($this->dir.'/.env', "LDE_QUOTED='single quoted'\n");
        callPrivate('loadEnvironment', $this->dir);
        expect($_ENV['LDE_QUOTED'])->toBe('single quoted');
    });

    it('skips comment lines', function () {
        file_put_contents($this->dir.'/.env', "# comment\nLDE_KEY=real\n");
        callPrivate('loadEnvironment', $this->dir);
        expect($_ENV)->toHaveKey('LDE_KEY')
            ->and(array_keys($_ENV))->not->toContain('# comment');
    });

    it('does not override an existing $_ENV variable', function () {
        $_ENV['LDE_SKIP'] = 'original';
        file_put_contents($this->dir.'/.env', "LDE_SKIP=overwritten\n");
        callPrivate('loadEnvironment', $this->dir);
        expect($_ENV['LDE_SKIP'])->toBe('original');
    });

    it('does not override an existing $_SERVER variable', function () {
        $_SERVER['LDE_SKIP'] = 'original';
        file_put_contents($this->dir.'/.env', "LDE_SKIP=overwritten\n");
        callPrivate('loadEnvironment', $this->dir);
        expect($_SERVER['LDE_SKIP'])->toBe('original');
    });
});

describe('runCommand', function () {
    beforeEach(function () {
        $this->log = tempnam(sys_get_temp_dir(), 'log_');
        $this->handle = fopen($this->log, 'wb');
    });

    afterEach(function () {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
        @unlink($this->log);
    });

    it('returns true when the command exits with code 0', function () {
        expect(callPrivate('runCommand', $this->handle, sys_get_temp_dir(), 'echo ok'))->toBeTrue();
    });

    it('returns false when the command exits with a non-zero code', function () {
        expect(callPrivate('runCommand', $this->handle, sys_get_temp_dir(), 'false'))->toBeFalse();
    });

    it('writes the command output to the log handle', function () {
        callPrivate('runCommand', $this->handle, sys_get_temp_dir(), 'echo deployment_output');
        fclose($this->handle);
        $this->handle = null;
        expect(file_get_contents($this->log))->toContain('deployment_output');
    });

    it('logs NO_OUTPUT when the command produces no output', function () {
        callPrivate('runCommand', $this->handle, sys_get_temp_dir(), 'true');
        fclose($this->handle);
        $this->handle = null;
        expect(file_get_contents($this->log))->toContain('NO_OUTPUT');
    });

    it('returns false when the handle is not a resource', function () {
        expect(callPrivate('runCommand', 'not-a-resource', sys_get_temp_dir(), 'echo ok'))->toBeFalse();
    });
});

describe('verifyBranch', function () {
    afterEach(function () {
        unset($_ENV['DEPLOYMENT_BRANCH'], $_SERVER['DEPLOYMENT_BRANCH']);
    });

    it('passes silently when the branch matches', function () {
        $_ENV['DEPLOYMENT_BRANCH'] = 'main';
        callPrivate('verifyBranch', json_encode(['ref' => 'refs/heads/main']));
        expect(true)->toBeTrue();
    });

    it('defaults to "deployment" branch when DEPLOYMENT_BRANCH is not set', function () {
        unset($_ENV['DEPLOYMENT_BRANCH'], $_SERVER['DEPLOYMENT_BRANCH']);
        callPrivate('verifyBranch', json_encode(['ref' => 'refs/heads/deployment']));
        expect(true)->toBeTrue();
    });

    it('aborts with exit code 1 on branch mismatch', function () {
        [$code, $output] = runInSubprocess(<<<'PHP'
            $_ENV['DEPLOYMENT_BRANCH'] = 'main';
            (new ReflectionMethod(\MUV\LaravelDeployment\Deployer::class, 'verifyBranch'))
                ->invoke(null, json_encode(['ref' => 'refs/heads/other']));
        PHP);

        expect($code)->toBe(1)
            ->and($output)->toContain('Ignored: Branch mismatch.');
    });

    it('aborts with exit code 1 on invalid JSON payload', function () {
        [$code, $output] = runInSubprocess(<<<'PHP'
            (new ReflectionMethod(\MUV\LaravelDeployment\Deployer::class, 'verifyBranch'))
                ->invoke(null, 'not-json');
        PHP);

        expect($code)->toBe(1)
            ->and($output)->toContain('Invalid JSON payload.');
    });
});

describe('verifyWebhook', function () {
    afterEach(function () {
        unset(
            $_ENV['GITHUB_WEBHOOK_SECRET'],
            $_ENV['GITHUB_WEBHOOK_CONTENT_TYPE'],
            $_SERVER['CONTENT_TYPE'],
            $_SERVER['HTTP_X_HUB_SIGNATURE_256'],
        );
    });

    it('passes silently with a valid HMAC signature', function () {
        $secret = 'test-secret';
        $payload = '{"ref":"refs/heads/main"}';

        $_ENV['GITHUB_WEBHOOK_SECRET'] = $secret;
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'sha256='.hash_hmac('sha256', $payload, $secret);

        callPrivate('verifyWebhook', $payload);

        expect(true)->toBeTrue();
    });

    it('aborts with exit code 1 on wrong Content-Type', function () {
        [$code, $output] = runInSubprocess(<<<'PHP'
            $_ENV['GITHUB_WEBHOOK_CONTENT_TYPE'] = 'application/json';
            $_SERVER['CONTENT_TYPE'] = 'text/plain';
            $_ENV['GITHUB_WEBHOOK_SECRET'] = 'secret';
            (new ReflectionMethod(\MUV\LaravelDeployment\Deployer::class, 'verifyWebhook'))
                ->invoke(null, 'payload');
        PHP);

        expect($code)->toBe(1)
            ->and($output)->toContain('Invalid Content-Type.');
    });

    it('aborts with exit code 1 when the signature header is absent', function () {
        [$code, $output] = runInSubprocess(<<<'PHP'
            $_SERVER['CONTENT_TYPE'] = 'application/json';
            $_ENV['GITHUB_WEBHOOK_SECRET'] = 'secret';
            unset($_SERVER['HTTP_X_HUB_SIGNATURE_256']);
            (new ReflectionMethod(\MUV\LaravelDeployment\Deployer::class, 'verifyWebhook'))
                ->invoke(null, 'payload');
        PHP);

        expect($code)->toBe(1)
            ->and($output)->toContain('Missing signature header.');
    });

    it('aborts with exit code 1 when the signature does not match', function () {
        [$code, $output] = runInSubprocess(<<<'PHP'
            $_SERVER['CONTENT_TYPE'] = 'application/json';
            $_ENV['GITHUB_WEBHOOK_SECRET'] = 'secret';
            $_SERVER['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=invalidsignature';
            (new ReflectionMethod(\MUV\LaravelDeployment\Deployer::class, 'verifyWebhook'))
                ->invoke(null, 'payload');
        PHP);

        expect($code)->toBe(1)
            ->and($output)->toContain('Invalid signature.');
    });
});

describe('executeDeployment', function () {
    beforeEach(function () {
        $this->dir = makeTempDir();
        // Snapshot the static $commands so each test starts from the same state
        $this->originalCommands = (new ReflectionProperty(Deployer::class, 'commands'))->getValue();
    });

    afterEach(function () {
        // Restore $commands modified by deploy_pre.php overrides
        (new ReflectionProperty(Deployer::class, 'commands'))->setValue(null, $this->originalCommands);
        removeTempDir($this->dir);
    });

    it('creates a deployment.log file', function () {
        file_put_contents($this->dir.'/deploy_pre.php', '<?php return ["echo ok"];');
        callPrivate('executeDeployment', $this->dir);
        expect(file_exists($this->dir.'/deployment.log'))->toBeTrue();
    });

    it('runs commands overridden by deploy_pre.php', function () {
        file_put_contents($this->dir.'/deploy_pre.php', '<?php return ["echo custom_command_ran"];');
        callPrivate('executeDeployment', $this->dir);
        expect(file_get_contents($this->dir.'/deployment.log'))->toContain('custom_command_ran');
    });

    it('stops execution after the first failing command', function () {
        file_put_contents($this->dir.'/deploy_pre.php', '<?php return ["false", "echo should_not_run"];');
        callPrivate('executeDeployment', $this->dir);
        expect(file_get_contents($this->dir.'/deployment.log'))->not->toContain('should_not_run');
    });

    it('runs deploy_pre.sh before the main commands', function () {
        file_put_contents($this->dir.'/deploy_pre.sh', "echo pre_hook_ran\n");
        file_put_contents($this->dir.'/deploy_pre.php', '<?php return ["echo main_command"];');
        callPrivate('executeDeployment', $this->dir);
        $log = file_get_contents($this->dir.'/deployment.log');
        expect($log)->toContain('pre_hook_ran')
            ->and($log)->toContain('main_command');
    });

    it('aborts early when deploy_pre.sh fails', function () {
        file_put_contents($this->dir.'/deploy_pre.sh', "exit 1\n");
        file_put_contents($this->dir.'/deploy_pre.php', '<?php return ["echo should_not_run"];');
        callPrivate('executeDeployment', $this->dir);
        expect(file_get_contents($this->dir.'/deployment.log'))->not->toContain('should_not_run');
    });

    it('runs deploy_post.sh after the main commands', function () {
        file_put_contents($this->dir.'/deploy_pre.php', '<?php return ["echo ok"];');
        file_put_contents($this->dir.'/deploy_post.sh', "echo post_hook_ran\n");
        callPrivate('executeDeployment', $this->dir);
        expect(file_get_contents($this->dir.'/deployment.log'))->toContain('post_hook_ran');
    });

    it('runs deploy_post.php after the main commands', function () {
        file_put_contents($this->dir.'/deploy_pre.php', '<?php return ["echo ok"];');
        file_put_contents($this->dir.'/deploy_post.php', '<?php echo "post_php_ran";');
        callPrivate('executeDeployment', $this->dir);
        expect(file_get_contents($this->dir.'/deployment.log'))->toContain('post_php_ran');
    });

    it('ignores non-string entries returned by deploy_pre.php', function () {
        file_put_contents($this->dir.'/deploy_pre.php', '<?php return ["echo valid", 42, null, "echo also_valid"];');
        callPrivate('executeDeployment', $this->dir);
        $log = file_get_contents($this->dir.'/deployment.log');
        expect($log)->toContain('valid')
            ->and($log)->toContain('also_valid');
    });
});
