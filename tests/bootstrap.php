<?php

/**
 * PHPUnit bootstrap.
 *
 * Normally `vendor/autoload.php` is enough, but the local toolchain (bougie)
 * re-dumps the *optimized* Composer autoloader with `--no-dev`, which strips the
 * test classes from the classmap and drops the `Tests\` PSR-4 mapping — leaving
 * the suite unable to resolve `Tests\TestCase`. Re-register the `Tests\`
 * namespace here so the suite runs regardless of how `vendor/` was last dumped.
 */

$autoload = require __DIR__.'/../vendor/autoload.php';

if ($autoload instanceof \Composer\Autoload\ClassLoader) {
    $autoload->addPsr4('Tests\\', __DIR__);
}

return $autoload;
