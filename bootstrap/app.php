<?php

use LaravelZero\Framework\Application;

// Loaded here rather than through Composer's "files" autoload, which would also load the
// helpers into every project that installs the CLI with `composer require`.
require_once dirname(__DIR__).'/app/helpers.php';

return Application::configure(basePath: dirname(__DIR__))->create();
