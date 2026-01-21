<?php

declare(strict_types=1);

namespace DOVU\CanonicalJson\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the JSON Canonicalizer.
 *
 * @method static string canonicalize(mixed $data) Canonicalize data to RFC 8785 JSON format
 * @method static string canonicalizeJson(string $json) Canonicalize a JSON string to RFC 8785 format
 *
 * @see \DOVU\CanonicalJson\JsonCanonicalizer
 */
class CanonicalJson extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \DOVU\CanonicalJson\JsonCanonicalizer::class;
    }
}
