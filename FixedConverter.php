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

        $absNumber = abs($number);
        $sign = $number < 0 ? '-' : '';
        
        // Check if it's effectively an integer (no fractional part)
        // and within safe integer range
        if ($number == floor($number) && $absNumber < 9007199254740992) {
            return number_format($number, 0, '', '');
        }

        // Use json_encode which respects serialize_precision=-1 for minimal representation
        $encoded = json_encode($absNumber);
        
        // ECMAScript rule: numbers in range [1e-6, 1e+21) use decimal notation
        // PHP's json_encode sometimes uses scientific notation at boundaries
        if (stripos($encoded, 'e') !== false) {
            // Check if the number is in the decimal range
            if ($absNumber >= 1e-6 && $absNumber < 1e+21) {
                // Force decimal notation
                $encoded = self::toDecimalNotation($absNumber);
            } else {
                // Use scientific notation, but normalize it
                $encoded = self::formatScientific($encoded);
            }
        }
        
        return $sign . $encoded;
    }
    
    /**
     * Convert a number to decimal notation (no scientific notation).
     * Uses the "shortest decimal representation" approach like ECMAScript.
     */
    private static function toDecimalNotation(float $number): string
    {
        // Try progressively shorter representations until we find the shortest
        // that still parses back to the same float
        
        // Calculate the magnitude to determine decimal places needed
        $magnitude = $number > 0 ? floor(log10($number)) : 0;
        
        // For small numbers (< 1), we need decimals after leading zeros
        // For numbers >= 1, we need fewer decimal places
        if ($number >= 1) {
            // Start with many digits and reduce
            for ($decimals = 17; $decimals >= 0; $decimals--) {
                $str = sprintf('%.' . $decimals . 'f', $number);
                // Clean up trailing zeros
                if (strpos($str, '.') !== false) {
                    $str = rtrim($str, '0');
                    $str = rtrim($str, '.');
                }
                // Check if it parses back correctly
                if ((float)$str === $number) {
                    return $str;
                }
            }
        } else {
            // For small numbers, calculate leading zeros needed
            $leadingZeros = abs((int)$magnitude) - 1;
            
            // Start with many significant digits and reduce
            for ($sigDigits = 17; $sigDigits >= 1; $sigDigits--) {
                $decimals = $leadingZeros + $sigDigits;
                $str = sprintf('%.' . $decimals . 'f', $number);
                // Clean up trailing zeros
                if (strpos($str, '.') !== false) {
                    $str = rtrim($str, '0');
                    $str = rtrim($str, '.');
                }
                // Check if it parses back correctly
                if ((float)$str === $number) {
                    return $str;
                }
            }
        }
        
        // Fallback: use sprintf with full precision
        $str = sprintf('%.17g', $number);
        if (stripos($str, 'e') !== false) {
            // If still in scientific notation, we have a problem
            // This shouldn't happen for numbers in [1e-6, 1e+21)
            return json_encode($number);
        }
        return $str;
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
