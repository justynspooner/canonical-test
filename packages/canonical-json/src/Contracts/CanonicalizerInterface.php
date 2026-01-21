<?php

declare(strict_types=1);

namespace DOVU\CanonicalJson\Contracts;

/**
 * Interface for JSON canonicalization per RFC 8785.
 *
 * Implementations of this interface provide deterministic JSON serialization
 * suitable for use in cryptographic hashing and signing operations.
 */
interface CanonicalizerInterface
{
    /**
     * Canonicalize data to RFC 8785 JSON format.
     *
     * Takes any PHP data structure (arrays, objects, scalars) and returns
     * a deterministic JSON string following the RFC 8785 JSON Canonicalization
     * Scheme (JCS) specification.
     *
     * Key properties of the output:
     * - Object keys are sorted using UTF-16BE code unit comparison
     * - Numbers use ECMAScript Number.toString() format
     * - No whitespace between elements
     * - Unicode characters preserved (UTF-8 output)
     *
     * @param  mixed  $data  The data to canonicalize (array, object, or scalar)
     * @return string The canonical JSON representation
     *
     * @throws \JsonException If the data cannot be encoded to JSON
     * @throws \LogicException If the data contains NaN or Infinity values
     */
    public function canonicalize(mixed $data): string;

    /**
     * Canonicalize a JSON string to RFC 8785 JSON format.
     *
     * Parses the JSON string and re-serializes it in canonical form.
     * This is useful for canonicalizing raw request bodies.
     *
     * @param  string  $json  The JSON string to canonicalize
     * @return string The canonical JSON representation
     *
     * @throws \JsonException If the JSON string is invalid
     * @throws \LogicException If the data contains NaN or Infinity values
     */
    public function canonicalizeJson(string $json): string;
}
