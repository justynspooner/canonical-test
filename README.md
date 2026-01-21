# Canonical JSON Comparison Tool

A test suite for comparing canonical JSON (RFC 8785) implementations between JavaScript and PHP.

## Overview

This project tests canonical JSON serialization using:

- **JavaScript**: [canonicalize](https://www.npmjs.com/package/canonicalize) npm package
- **PHP**: [root23/php-json-canonicalization](https://packagist.org/packages/root23/php-json-canonicalization) with fixes

Both packages implement [RFC 8785 - JSON Canonicalization Scheme (JCS)](https://tools.ietf.org/html/rfc8785), which defines a deterministic way to serialize JSON so that the same data always produces the same byte-for-byte output. This is essential for signing JSON data or comparing JSON across different systems.

## Installation

```bash
# Install JavaScript dependencies
yarn install

# Install PHP dependencies
composer install
```

## Usage

### Compare a single JSON file

```bash
./compare.sh input.json
```

This will show:
- The input JSON
- Canonical output from JavaScript
- Canonical output from PHP
- SHA256 hashes of both outputs
- Whether they match

### Run the full test suite

```bash
./run-all-tests.sh
```

Runs all 20 test cases covering various JSON structures and edge cases.

### Run individual scripts

```bash
# JavaScript only
node canonicalize.js input.json

# PHP only
php canonicalize.php input.json
```

## The PHP Library Bug

The original `root23/php-json-canonicalization` library has bugs in its number formatting that cause mismatches with JavaScript:

### Bug 1: Trailing zeros stripped from integers

```php
// In Converter.php line 30
$formatted = rtrim($formatted, '.0');
```

This strips **all** trailing `0` and `.` characters, not just decimal trailing zeros:
- `25000000000` becomes `25` ❌

### Bug 2: Limited decimal precision

```php
// In Converter.php line 29  
$formatted = number_format($number, 7, '.', '');
```

Only 7 decimal places are preserved:
- `1.234567890123456` becomes `1.2345679` ❌

## Using the Fixed PHP Canonicalizer

This project includes fixed versions that produce output identical to JavaScript.

### Option 1: Copy the fixed files into your project

Copy these files to your project:
- `FixedConverter.php`
- `FixedJsonCanonicalizer.php`

Then use them:

```php
<?php

require_once __DIR__ . '/FixedJsonCanonicalizer.php';

$data = [
    'name' => 'Example',
    'values' => [1, 2, 3],
    'nested' => ['z' => 'last', 'a' => 'first']
];

$canonicalizer = new FixedJsonCanonicalizer();
$canonical = $canonicalizer->canonicalize($data);

// Create a hash for signing/verification
$hash = hash('sha256', $canonical);

echo "Canonical: $canonical\n";
echo "SHA256: $hash\n";
```

### Option 2: Integrate into an existing project

If you're already using `root23/php-json-canonicalization`, you can override just the converter:

```php
<?php

require_once 'vendor/autoload.php';
require_once __DIR__ . '/FixedConverter.php';

// Create a wrapper that uses the fixed converter
class MyCanonicalizer
{
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    public const ENCODING = 'UTF-16BE';

    public function canonicalize($data): string
    {
        ob_start();
        $this->encode($data);
        return ob_get_clean();
    }

    private function encode($item): void
    {
        if (is_float($item)) {
            // Use the fixed converter for floats
            echo FixedConverter::toEs6NumberFormat($item);
            return;
        }

        if (is_int($item)) {
            echo $item;
            return;
        }

        if (is_null($item) || is_scalar($item)) {
            echo json_encode($item, JSON_THROW_ON_ERROR | self::JSON_FLAGS);
            return;
        }

        if (is_array($item) && array_keys($item) === range(0, count($item) - 1)) {
            echo '[';
            $first = true;
            foreach ($item as $element) {
                if (!$first) echo ',';
                $first = false;
                $this->encode($element);
            }
            echo ']';
            return;
        }

        if (is_object($item)) {
            $item = (array)$item;
        }

        // Sort keys using UTF-16BE (RFC 8785 requirement)
        uksort($item, fn($a, $b) => strcmp(
            mb_convert_encoding($a, self::ENCODING),
            mb_convert_encoding($b, self::ENCODING)
        ));

        echo '{';
        $first = true;
        foreach ($item as $key => $value) {
            if (!$first) echo ',';
            $first = false;
            echo json_encode((string)$key, JSON_THROW_ON_ERROR | self::JSON_FLAGS) . ':';
            $this->encode($value);
        }
        echo '}';
    }
}
```

### Important: Preserve objects vs arrays

When decoding JSON in PHP, use `json_decode($json)` **without** the `true` parameter to preserve empty objects:

```php
// ❌ Bad - converts {} to []
$data = json_decode($json, true);

// ✅ Good - preserves {} as stdClass
$data = json_decode($json);
```

## How the Fix Works

The fix uses PHP's native `json_encode()` which respects the `serialize_precision = -1` INI setting to produce the **shortest decimal representation** that uniquely identifies each floating-point number (matching JavaScript's `Number.toString()` behavior):

```php
public static function toEs6NumberFormat(float $number): string
{
    // Handle special cases
    if ($number === 0.0) return '0';
    
    // Integers don't need decimal formatting
    if ($number == floor($number) && abs($number) < 9007199254740992) {
        return number_format($number, 0, '', '');
    }

    // Use json_encode for minimal representation
    $encoded = json_encode($number);
    
    // Normalize scientific notation (1.0e+30 → 1e+30)
    if (stripos($encoded, 'e') !== false) {
        return self::formatScientific($encoded);
    }
    
    return $encoded;
}
```

## Test Cases

The `tests/` directory contains 20 test cases:

| Test | Description |
|------|-------------|
| 01-simple.json | Basic object with string, number, boolean |
| 02-key-ordering.json | Verifies keys are sorted correctly |
| 03-nested-objects.json | Deeply nested object structures |
| 04-arrays.json | Various array types |
| 05-empty-structures.json | Empty objects `{}` and arrays `[]` |
| 06-unicode.json | Chinese, Japanese, Korean, Arabic, emoji |
| 07-special-chars.json | Quotes, backslashes, newlines, tabs |
| 08-control-chars.json | ASCII control characters |
| 09-numbers-integer.json | Integer edge cases |
| 10-numbers-float.json | Floating point precision |
| 11-numbers-scientific.json | Scientific notation |
| 12-boolean-null.json | Literals: true, false, null |
| 13-deeply-nested.json | 7 levels of nesting |
| 14-array-of-objects.json | Array containing objects |
| 15-numeric-strings.json | Strings that look like numbers |
| 16-unicode-keys.json | Non-ASCII object keys |
| 17-rfc8785-example.json | Example from RFC 8785 spec |
| 18-whitespace-strings.json | Strings with whitespace |
| 19-edge-case-keys.json | Empty string, space, numeric keys |
| 20-complex-mixed.json | Complex real-world-like structure |

## Creating Verifiable Hashes

To create a hash that can be verified across JavaScript and PHP:

**JavaScript:**
```javascript
import canonicalize from 'canonicalize';
import crypto from 'crypto';

const data = { /* your data */ };
const canonical = canonicalize(data);
const hash = crypto.createHash('sha256').update(canonical).digest('hex');
```

**PHP:**
```php
<?php
require_once 'FixedJsonCanonicalizer.php';

$data = /* your data */;
$canonicalizer = new FixedJsonCanonicalizer();
$canonical = $canonicalizer->canonicalize($data);
$hash = hash('sha256', $canonical);
```

Both will produce identical hashes for the same input data.

## License

MIT
