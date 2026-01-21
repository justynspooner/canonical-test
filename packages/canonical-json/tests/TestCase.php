<?php

declare(strict_types=1);

namespace DOVU\CanonicalJson\Tests;

use DOVU\CanonicalJson\CanonicalJsonServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CanonicalJsonServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'CanonicalJson' => \DOVU\CanonicalJson\Facades\CanonicalJson::class,
        ];
    }
}
