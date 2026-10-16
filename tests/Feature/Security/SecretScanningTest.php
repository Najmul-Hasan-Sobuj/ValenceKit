<?php

test('ensures .env and sensitive credential files are never tracked in git', function () {
    $basePath = base_path();

    // Verify .env is not tracked
    $trackedEnv = trim(shell_exec("git -C {$basePath} ls-files .env") ?? '');
    expect($trackedEnv)->toBeEmpty();

    // Verify no private keys or certificates are tracked
    $trackedKeys = trim(shell_exec("git -C {$basePath} ls-files '*.key' '*.pem' '*.p12'") ?? '');
    expect($trackedKeys)->toBeEmpty();

    // Verify that the only tracked .env file is .env.example
    $trackedEnvFiles = array_filter(
        explode("\n", trim(shell_exec("git -C {$basePath} ls-files '.env*'") ?? '')),
        fn (string $file) => $file !== ''
    );
    expect($trackedEnvFiles)->toEqual(['.env.example']);
});

test('ensures .env.example contains only blank placeholders for sensitive secrets', function () {
    $examplePath = base_path('.env.example');
    expect(file_exists($examplePath))->toBeTrue();

    $content = file_get_contents($examplePath);

    // APP_KEY must be blank
    expect($content)->toMatch('/^APP_KEY=$/m');

    // AWS credentials must be blank
    expect($content)->toMatch('/^AWS_ACCESS_KEY_ID=$/m');
    expect($content)->toMatch('/^AWS_SECRET_ACCESS_KEY=$/m');

    // Admin bootstrap password must be blank
    expect($content)->toMatch('/^ADMIN_BOOTSTRAP_PASSWORD=$/m');
});

test('ensures gitleaks configuration exists and defines necessary allowlists', function () {
    $configPath = base_path('.gitleaks.toml');
    expect(file_exists($configPath))->toBeTrue();

    $config = file_get_contents($configPath);
    expect($config)
        ->toContain('[allowlist]')
        ->toContain('\.env\.example')
        ->toContain('AdminSeeder');
});

test('ensures ci workflow includes gitleaks secret scanning before ci execution', function () {
    $workflowPath = base_path('.github/workflows/tests.yml');
    expect(file_exists($workflowPath))->toBeTrue();

    $workflow = file_get_contents($workflowPath);
    expect($workflow)
        ->toContain('gitleaks/gitleaks-action')
        ->toContain('needs: [gitleaks]');
});

test('scans all git-tracked source files for high-confidence leaked credentials', function () {
    $basePath = base_path();
    $trackedFiles = array_filter(
        explode("\n", trim(shell_exec("git -C {$basePath} ls-files") ?? '')),
        fn (string $file) => $file !== '' && ! in_array($file, [
            '.gitleaks.toml',
            'composer.lock',
            'package-lock.json',
            'tests/Feature/Security/SecretScanningTest.php',
            'database/seeders/AdminSeeder.php',
        ], true)
    );

    $highConfidenceSignatures = [
        'AWS Access Key ID' => '/AKIA[0-9A-Z]{16}/',
        'GitHub Personal Access Token' => '/ghp_[0-9a-zA-Z]{36}/',
        'Private Key Header' => '/-----BEGIN (?:RSA |OPENSSH |EC |DSA |PGP )?PRIVATE KEY-----/',
        'Stripe Live Secret Key' => '/sk_live_[0-9a-zA-Z]{24}/',
        'Slack Bot Token' => '/xoxb-[0-9]{11,13}-[0-9]{11,13}-[a-zA-Z0-9]{24}/',
    ];

    $violations = [];

    foreach ($trackedFiles as $file) {
        $fullPath = $basePath.'/'.$file;
        if (! file_exists($fullPath) || is_dir($fullPath)) {
            continue;
        }

        $content = file_get_contents($fullPath);
        foreach ($highConfidenceSignatures as $name => $pattern) {
            if (preg_match($pattern, $content)) {
                $violations[] = "{$file} matched signature '{$name}'";
            }
        }
    }

    expect($violations)->toBeEmpty();
});
