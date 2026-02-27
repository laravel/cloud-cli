<?php

require_once __DIR__.'/Helpers.php';

use App\Prompts\Renderer as PromptRenderer;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');

afterEach(function () {
    PromptRenderer::$suppressOutput = false;
})->in('Feature');
