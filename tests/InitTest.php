<?php

declare(strict_types=1);

/**
 * Tests for `todo-md init` — project scaffolding.
 */

test('init: scaffolds .todo-md.php', function (): void {
    require_once Fixture::pkgRoot() . '/src/bootstrap.php';

    $target = sys_get_temp_dir() . '/todomd-init-' . bin2hex(random_bytes(6));
    mkdir($target, 0755, true);

    [$code, $out, $err] = Fixture::runBin('todo-md', ['init', $target]);
    expectEquals(0, $code, "init should succeed\n$err");

    $configFile = $target . '/.todo-md.php';
    expectFileExists($configFile, '.todo-md.php scaffolded');
    expectContains('Copied   .todo-md.php', $out, 'init reports the config file');

    // The scaffolded config must load as a safe default (no-op until filled in).
    $config = TodoMd\Validator::loadConfigFile($configFile);
    expectEquals(false, $config['strict'], 'default strict is false');
    expectEquals([], $config['roles'], 'default roles empty');
    expectEquals([], $config['agents'], 'default agents empty');
});

test('init: --no-config skips .todo-md.php scaffolding', function (): void {
    require_once Fixture::pkgRoot() . '/src/bootstrap.php';

    $target = sys_get_temp_dir() . '/todomd-init-' . bin2hex(random_bytes(6));
    mkdir($target, 0755, true);

    [$code, $out, $err] = Fixture::runBin('todo-md', ['init', $target, '--no-config']);
    expectEquals(0, $code, "init --no-config should succeed\n$err");

    expectFileMissing($target . '/.todo-md.php', '--no-config must not scaffold .todo-md.php');
    expectContains('Skip     .todo-md.php (--no-config)', $out, 'init reports the skipped config');
});

test('init: --force --no-config keeps user .todo-md.php intact', function (): void {
    require_once Fixture::pkgRoot() . '/src/bootstrap.php';

    $target = sys_get_temp_dir() . '/todomd-init-' . bin2hex(random_bytes(6));
    mkdir($target, 0755, true);

    $userConfig = <<<'PHP'
<?php

declare(strict_types=1);

return [
    'roles' => ['Тестировщик', 'Девопс'],
    'agents' => ['pi'],
    'strict' => true,
];
PHP;
    file_put_contents($target . '/.todo-md.php', $userConfig);

    [$code, $out, $err] = Fixture::runBin('todo-md', ['init', $target, '--force', '--no-config']);
    expectEquals(0, $code, "init --force --no-config should succeed\n$err");

    expectEquals($userConfig, file_get_contents($target . '/.todo-md.php'), 'user config must stay byte-identical');
    expectContains('Skip     .todo-md.php (--no-config)', $out, 'init reports the skipped config');
});

test('init: --force overwrites .todo-md.php (regression)', function (): void {
    require_once Fixture::pkgRoot() . '/src/bootstrap.php';

    $target = sys_get_temp_dir() . '/todomd-init-' . bin2hex(random_bytes(6));
    mkdir($target, 0755, true);

    file_put_contents($target . '/.todo-md.php', "<?php\n\nreturn ['roles' => ['custom'], 'strict' => true];\n");

    [$code, $out, $err] = Fixture::runBin('todo-md', ['init', $target, '--force']);
    expectEquals(0, $code, "init --force should succeed\n$err");

    expectContains('Updated  .todo-md.php', $out, 'init reports the updated config');

    // Package default replaces the user config — documented --force behaviour.
    $config = TodoMd\Validator::loadConfigFile($target . '/.todo-md.php');
    expectEquals(false, $config['strict'], 'config reset to package default (strict false)');
    expectEquals([], $config['roles'], 'config reset to package default (roles empty)');
});
