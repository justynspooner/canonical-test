<?php

declare(strict_types=1);

use DOVU\CanonicalJson\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific
| PHPUnit test case class. By default, that class is "PHPUnit\Framework\TestCase".
| For Laravel package testing, we use Orchestra Testbench.
|
*/

uses(TestCase::class)->in('Feature');
uses()->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain
| conditions. Pest provides many "expectations" for that.
|
*/

expect()->extend('toBeCanonicalJson', function () {
    // Verify the value is a valid JSON string
    json_decode($this->value);

    return $this->and(json_last_error())->toBe(JSON_ERROR_NONE);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Global helper functions for tests.
|
*/

/**
 * Load a test file as JSON data (the input to canonicalize).
 *
 * @return mixed The parsed JSON data
 */
function loadTestInput(string $category, string $name): mixed
{
    $path = __DIR__."/datasets/{$category}/{$name}.json";
    $content = file_get_contents($path);

    return json_decode($content);
}

/**
 * Load all test inputs from a category.
 *
 * @return array<string, mixed> Map of test name to input data
 */
function loadAllTestInputs(string $category): array
{
    $path = __DIR__."/datasets/{$category}";
    $cases = [];

    foreach (glob("{$path}/*.json") as $file) {
        $name = basename($file, '.json');
        $content = file_get_contents($file);
        $cases[$name] = json_decode($content);
    }

    return $cases;
}
