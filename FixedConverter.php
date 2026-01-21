<?php

/**
 * Fixed ES6 number formatting for RFC 8785 canonical JSON.
 * 
 * Fixes issues in root23/php-json-canonicalization:
 * 1. rtrim('.0') incorrectly strips trailing zeros from integers (25000000000 → 25)
 * 2. number_format with 7 decimals loses precision (1.234567890123456 → 1.2345679)
 * 
 * Uses PHP's json_encode with serialize_precision=-1 for minimal representation,
 * then normalizes scientific notation to match JavaScript's format.
 */
class FixedConverter
{
    /**
     * Convert a float to ES6 number format per RFC 8785.
     * 
     * This implementation matches JavaScript's Number.toString() behavior
     * which is what the canonicalize npm package uses.
     */
    public static function toEs6NumberFormat(float $number): string
    {
        if (is_nan($number) || is_infinite($number)) {
            throw new \LogicException("Infinity or NaN can't be used in JSON");
        }

        // Handle zero specially
        if ($number === 0.0) {
            return '0';
        }

        // Check if it's effectively an integer (no fractional part)
        // and within safe integer range
        if ($number == floor($number) && abs($number) < 9007199254740992) {
            return number_format($number, 0, '', '');
        }

        // Use json_encode which respects serialize_precision=-1 for minimal representation
        // This gives us the shortest decimal that uniquely identifies the float
        $encoded = json_encode($number);
        
        // Normalize scientific notation to match ES6 format
        if (stripos($encoded, 'e') !== false) {
            return self::formatScientific($encoded);
        }
        
        return $encoded;
    }

    /**
     * Format a number in scientific notation to match ES6 style.
     * Converts "1.0e+30" or "1.0E+30" to "1e+30"
     */
    private static function formatScientific(string $formatted): string
    {
        // Normalize to lowercase 'e'
        $formatted = strtolower($formatted);
        $parts = explode('e', $formatted);
        
        if (count($parts) !== 2) {
            return $formatted;
        }

        $mantissa = $parts[0];
        $exponent = (int)$parts[1];

        // Remove trailing zeros from mantissa (after decimal point only)
        if (strpos($mantissa, '.') !== false) {
            $mantissa = rtrim($mantissa, '0');
            $mantissa = rtrim($mantissa, '.');
        }

        // Format exponent with sign (ES6 always uses + for positive exponents)
        $expSign = $exponent >= 0 ? '+' : '';
        
        return $mantissa . 'e' . $expSign . $exponent;
    }
}
