<?php

require_once __DIR__ . '/FixedConverter.php';

/**
 * Fixed JSON Canonicalizer for RFC 8785.
 * 
 * Uses FixedConverter to properly handle floating point numbers
 * matching JavaScript's Number.toString() behavior.
 */
class FixedJsonCanonicalizer
{
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    public const ENCODING = 'UTF-16BE';

    /**
     * Canonicalize data to RFC 8785 JSON format.
     */
    public function canonicalize($data): string
    {
        ob_start();
        $this->encode($data);
        return ob_get_clean();
    }

    /**
     * Encode a value to canonical JSON format.
     */
    private function encode($item): void
    {
        // Handle floats with our fixed converter
        if (is_float($item)) {
            echo FixedConverter::toEs6NumberFormat($item);
            return;
        }

        // Handle integers
        if (is_int($item)) {
            echo $item;
            return;
        }

        // Handle null and other scalars (bool, string)
        if (is_null($item) || is_scalar($item)) {
            echo json_encode($item, JSON_THROW_ON_ERROR | self::JSON_FLAGS);
            return;
        }

        // Handle arrays (non-associative)
        if (is_array($item) && !$this->isArrayAssoc($item)) {
            echo '[';
            $next = false;
            foreach ($item as $element) {
                if ($next) {
                    echo ',';
                }
                $next = true;
                $this->encode($element);
            }
            echo ']';
            return;
        }

        // Handle objects and associative arrays
        if (is_object($item)) {
            $item = (array)$item;
        }

        // Sort keys using UTF-16BE comparison (RFC 8785 requirement)
        uksort($item, static function (string $a, string $b) {
            $a = mb_convert_encoding($a, self::ENCODING);
            $b = mb_convert_encoding($b, self::ENCODING);
            return strcmp($a, $b);
        });

        echo '{';
        $next = false;
        foreach ($item as $key => $value) {
            if ($next) {
                echo ',';
            }
            $next = true;
            $outKey = json_encode((string)$key, JSON_THROW_ON_ERROR | self::JSON_FLAGS);
            echo $outKey . ':';
            $this->encode($value);
        }
        echo '}';
    }

    /**
     * Check if an array is associative.
     */
    private function isArrayAssoc(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
