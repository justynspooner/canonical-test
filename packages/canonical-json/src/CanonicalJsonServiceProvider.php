<?php

declare(strict_types=1);

namespace DOVU\CanonicalJson;

use DOVU\CanonicalJson\Contracts\CanonicalizerInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CanonicalJsonServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('canonical-json')
            ->hasConfigFile();
    }

    /**
     * Register package services.
     */
    public function packageRegistered(): void
    {
        // Register the canonicalizer as a singleton
        $this->app->singleton(JsonCanonicalizer::class, function () {
            return new JsonCanonicalizer;
        });

        // Bind the interface to the implementation
        $this->app->bind(CanonicalizerInterface::class, JsonCanonicalizer::class);

        // Register an alias for convenience
        $this->app->alias(JsonCanonicalizer::class, 'canonical-json');
    }
}
