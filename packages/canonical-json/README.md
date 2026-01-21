# DOVU Canonical JSON

A Laravel package providing RFC 8785 JSON Canonicalization Scheme (JCS) implementation.

This package produces deterministic JSON serialization suitable for cryptographic hashing and signing operations. It ensures that semantically equivalent JSON documents produce identical byte representations.

## Installation

```bash
composer require dovu/canonical-json
```

The package uses Laravel's auto-discovery, so the service provider and facade are registered automatically.

### PHP Configuration

For proper float serialization, ensure your `php.ini` has:

```ini
serialize_precision = -1
```

Or set it at runtime before using the canonicalizer:

```php
ini_set('serialize_precision', '-1');
```

## Usage

### Using the Facade

```php
use DOVU\CanonicalJson\Facades\CanonicalJson;

$data = [
    'name' => 'John Doe',
    'age' => 30,
    'active' => true,
];

$canonical = CanonicalJson::canonicalize($data);
// Output: {"active":true,"age":30,"name":"John Doe"}

// Then hash separately
$hash = hash('sha256', $canonical);
```

### Canonicalizing Raw JSON Strings (Request Bodies)

If you have a raw JSON string (e.g., from `$request->getContent()`), use `canonicalizeJson()`:

```php
use DOVU\CanonicalJson\Facades\CanonicalJson;

// Raw JSON from request body
$rawJson = $request->getContent();
// e.g., '{"z":1,"a":2,"nested":{"b":3,"a":4}}'

$canonical = CanonicalJson::canonicalizeJson($rawJson);
// Output: {"a":2,"nested":{"a":4,"b":3},"z":1}
```

### Using Dependency Injection

```php
use DOVU\CanonicalJson\Contracts\CanonicalizerInterface;

class WebhookController
{
    public function __construct(
        private CanonicalizerInterface $canonicalizer
    ) {}

    public function verify(Request $request): bool
    {
        // Option 1: From parsed JSON array
        $canonical = $this->canonicalizer->canonicalize($request->json()->all());

        // Option 2: From raw request body (preserves original number precision)
        $canonical = $this->canonicalizer->canonicalizeJson($request->getContent());

        $hash = hash('sha256', $canonical);
        return hash_equals($expectedHash, $hash);
    }
}
```

### Direct Instantiation (Non-Laravel)

```php
use DOVU\CanonicalJson\JsonCanonicalizer;

$canonicalizer = new JsonCanonicalizer();
$canonical = $canonicalizer->canonicalize($data);
```

## RFC 8785 Compliance

This implementation follows RFC 8785 (JSON Canonicalization Scheme) specifications:

### Key Sorting (Section 3.2.3)
Object keys are sorted using UTF-16BE code unit comparison:

```php
$data = ['z' => 1, 'a' => 2, 'm' => 3];
CanonicalJson::canonicalize($data);
// Output: {"a":2,"m":3,"z":1}
```

### Number Formatting (Section 3.2.2.3)
Numbers are formatted using ECMAScript's `Number.toString()` rules:

```php
// Integers without decimal point
CanonicalJson::canonicalize(42);           // "42"

// Decimal notation for [1e-6, 1e+21)
CanonicalJson::canonicalize(0.000001);     // "0.000001"
CanonicalJson::canonicalize(1e20);         // "100000000000000000000"

// Scientific notation outside that range
CanonicalJson::canonicalize(1e-7);         // "1e-7"
CanonicalJson::canonicalize(1e21);         // "1e+21"
```

### No Whitespace (Section 3.2.1)
Output contains no whitespace between elements:

```php
$data = ['key' => [1, 2, 3]];
CanonicalJson::canonicalize($data);
// Output: {"key":[1,2,3]}
```

### Unicode Preservation (Section 3.2.4)
Unicode characters are preserved (not escaped):

```php
CanonicalJson::canonicalize(['currency' => '€']);
// Output: {"currency":"€"}
```

## Example: Verifying the RFC 8785 Test Vector

```php
use DOVU\CanonicalJson\Facades\CanonicalJson;

$input = [
    'numbers' => [333333333.33333329, 1e30, 4.5, 2e-3, 0.000000000000000000000000001],
    'string' => "\u{20AC}\$\u{000F}\u{000A}A'\u{0042}\u{0022}\u{005C}\\\"//",
    'literals' => [null, true, false],
];

$canonical = CanonicalJson::canonicalize($input);
$hash = hash('sha256', $canonical);

// Expected hash from RFC 8785
assert($hash === 'f12e9e34f4ff5a94e058c5b77e2da072bac2bdd07e55b11c73c61f0c0bb10e16');
```

## Testing

```bash
cd packages/canonical-json
composer install
composer test
```

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, or 12.x
- `mbstring` extension
- `serialize_precision = -1` in php.ini

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.
