<?php

declare(strict_types=1);

namespace DOVU\CanonicalJson;

use DOVU\CanonicalJson\Contracts\CanonicalizerInterface;
use DOVU\CanonicalJson\Support\NumberFormatter;
use JsonException;

/**
 * RFC 8785 JSON Canonicalization Scheme (JCS) implementation.
 *
 * This class provides deterministic JSON serialization following the
 * RFC 8785 specification, suitable for use in cryptographic hashing
 * and signing operations.
 *
 * @see https://www.rfc-editor.org/rfc/rfc8785
 */
class JsonCanonicalizer implements CanonicalizerInterface
{
    /**
     * JSON encoding flags for string values.
     * - JSON_UNESCAPED_UNICODE: Keep Unicode characters as-is
     * - JSON_UNESCAPED_SLASHES: Don't escape forward slashes
     */
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Encoding used for key comparison per RFC 8785 Section 3.2.3.
     * Keys are sorted by UTF-16BE code unit comparison.
     */
    private const KEY_SORT_ENCODING = 'UTF-16BE';

    /**
     * Output buffer for building the canonical JSON.
     */
    private string $output = '';

    /**
     * Canonicalize data to RFC 8785 JSON format.
     *
     * @param  mixed  $data  The data to canonicalize
     * @return string The canonical JSON representation
     *
     * @throws JsonException If the data cannot be encoded to JSON
     */
    public function canonicalize(mixed $data): string
    {
        $this->output = '';
        $this->encode($data);

        return $this->output;
    }

    /**
     * Canonicalize a JSON string to RFC 8785 JSON format.
     *
     * Parses the JSON string and re-serializes it in canonical form.
     * This is useful for canonicalizing raw request bodies.
     *
     * @param  string  $json  The JSON string to canonicalize
     * @return string The canonical JSON representation
     *
     * @throws JsonException If the JSON string is invalid
     */
    public function canonicalizeJson(string $json): string
    {
        // Decode with JSON_BIGINT_AS_STRING to preserve large integer precision
        // Use objects for JSON objects to preserve the distinction from arrays
        $data = json_decode($json, false, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);

        return $this->canonicalize($data);
    }

    /**
     * Recursively encode a value to canonical JSON format.
     *
     * @param  mixed  $item  The value to encode
     *
     * @throws JsonException If a string value cannot be encoded
     */
    private function encode(mixed $item): void
    {
        // Handle floats with ES6-compatible formatting
        if (is_float($item)) {
            $this->output .= NumberFormatter::toEs6Format($item);

            return;
        }

        // Handle integers directly
        if (is_int($item)) {
            $this->output .= (string) $item;

            return;
        }

        // Handle null and other scalars (bool, string)
        if (is_null($item) || is_scalar($item)) {
            $this->output .= json_encode($item, JSON_THROW_ON_ERROR | self::JSON_FLAGS);

            return;
        }

        // Handle arrays (non-associative)
        if (is_array($item) && ! $this->isAssociativeArray($item)) {
            $this->output .= '[';
            $first = true;
            foreach ($item as $element) {
                if (! $first) {
                    $this->output .= ',';
                }
                $first = false;
                $this->encode($element);
            }
            $this->output .= ']';

            return;
        }

        // Handle objects and associative arrays
        if (is_object($item)) {
            $item = (array) $item;
        }

        // Sort keys using UTF-16BE comparison (RFC 8785 Section 3.2.3)
        uksort($item, static function (string $a, string $b): int {
            $a = mb_convert_encoding($a, self::KEY_SORT_ENCODING);
            $b = mb_convert_encoding($b, self::KEY_SORT_ENCODING);

            return strcmp($a, $b);
        });

        $this->output .= '{';
        $first = true;
        foreach ($item as $key => $value) {
            if (! $first) {
                $this->output .= ',';
            }
            $first = false;

            // Encode key as JSON string
            $encodedKey = json_encode((string) $key, JSON_THROW_ON_ERROR | self::JSON_FLAGS);
            $this->output .= $encodedKey.':';
            $this->encode($value);
        }
        $this->output .= '}';
    }

    /**
     * Determine if an array is associative (has string keys or non-sequential numeric keys).
     *
     * @param  array<mixed>  $arr  The array to check
     * @return bool True if associative, false if sequential
     */
    private function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
