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
