<?php

it('keeps env() out of config/app.php', function () {
    // `app:build` evaluates config/app.php and bakes the resulting array into the phar, so any
    // env() call there is frozen at build time and ignored by the binary. Put it elsewhere.
    expect(file_get_contents(base_path('config/app.php')))->not->toContain('env(');
});
