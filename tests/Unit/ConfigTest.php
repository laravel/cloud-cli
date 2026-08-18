<?php

use Humbug\SelfUpdate\Strategy\GithubStrategy;

it('keeps env() out of config/app.php', function () {
    // `app:build` evaluates config/app.php and bakes the resulting array into the phar, so any
    // env() call there is frozen at build time and ignored by the binary. Put it elsewhere.
    expect(file_get_contents(base_path('config/app.php')))->not->toContain('env(');
});

it('points self-update at a strategy that names the file to download', function () {
    // GithubReleasesStrategy builds `releases/download/{version}/{pharName}`, and nothing ever
    // calls setPharName(), so the URL loses its filename and 404s. GithubStrategy appends the
    // running binary's own name to the tagged `builds/` path instead.
    $strategy = app(config('updater.strategy'));

    (new ReflectionProperty(GithubStrategy::class, 'remoteVersion'))
        ->setValue($strategy, 'v9.9.9');

    $url = (new ReflectionMethod($strategy, 'getDownloadUrl'))
        ->invoke($strategy, ['source' => ['url' => 'https://github.com/laravel/cloud-cli.git']]);

    expect($url)->toMatch('#^https://github\\.com/laravel/cloud-cli/raw/v9\\.9\\.9/+builds/#');
});
