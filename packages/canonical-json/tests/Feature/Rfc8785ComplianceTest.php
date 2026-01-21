<?php

declare(strict_types=1);

use DOVU\CanonicalJson\Facades\CanonicalJson;
use DOVU\CanonicalJson\JsonCanonicalizer;

describe('RFC 8785 Compliance', function () {
    beforeEach(function () {
        $this->canonicalizer = new JsonCanonicalizer;
    });

    describe('Section 3.2.1 - Whitespace', function () {
        it('produces no whitespace between tokens', function () {
            $input = [
                'array' => [1, 2, 3],
                'object' => ['nested' => 'value'],
                'string' => 'hello world',
            ];

            $result = $this->canonicalizer->canonicalize($input);

            // Only the string value should contain a space
            $withoutStringValue = str_replace('hello world', 'x', $result);
            expect($withoutStringValue)->not->toContain(' ');
            expect($result)->not->toContain("\n");
            expect($result)->not->toContain("\t");
            expect($result)->not->toContain("\r");
        });
    });

    describe('Section 3.2.2.3 - Number Serialization', function () {
        it('formats integers without decimal point', function () {
            expect($this->canonicalizer->canonicalize(42))->toBe('42');
            expect($this->canonicalizer->canonicalize(-100))->toBe('-100');
            expect($this->canonicalizer->canonicalize(0))->toBe('0');
        });

        it('uses decimal notation for numbers in [1e-6, 1e+21)', function () {
            // Lower boundary
            expect($this->canonicalizer->canonicalize(0.000001))->toBe('0.000001');
            // Upper boundary (just below)
            expect($this->canonicalizer->canonicalize(1e20))->toBe('100000000000000000000');
        });

        it('uses scientific notation for numbers outside [1e-6, 1e+21)', function () {
            // Below lower boundary
            expect($this->canonicalizer->canonicalize(1e-7))->toBe('1e-7');
            // At/above upper boundary
            expect($this->canonicalizer->canonicalize(1e21))->toBe('1e+21');
        });

        it('uses lowercase e in scientific notation', function () {
            $result = $this->canonicalizer->canonicalize(1e30);
            expect($result)->toContain('e');
            expect($result)->not->toContain('E');
        });

        it('includes + sign for positive exponents', function () {
            expect($this->canonicalizer->canonicalize(1e30))->toBe('1e+30');
        });

        it('formats 333333333.3333333 correctly', function () {
            // Key test from RFC 8785
            expect($this->canonicalizer->canonicalize(333333333.3333333))->toBe('333333333.3333333');
        });
    });

    describe('Section 3.2.3 - Object Key Sorting', function () {
        it('sorts keys lexicographically', function () {
            $input = ['z' => 1, 'a' => 2, 'm' => 3];
            expect($this->canonicalizer->canonicalize($input))->toBe('{"a":2,"m":3,"z":1}');
        });

        it('sorts using UTF-16BE code unit comparison', function () {
            // From RFC 8785 example: keys sorted by UTF-16BE
            $input = [
                "\u{20AC}" => 'Euro Sign',
                "\r" => 'Carriage Return',
                '1' => 'One',
            ];

            $result = $this->canonicalizer->canonicalize($input);

            // In UTF-16BE order:
            // \r (0x000D) < '1' (0x0031) < Euro (0x20AC)
            expect($result)->toStartWith('{"\r"');
        });

        it('handles numeric string keys', function () {
            $input = ['10' => 'ten', '2' => 'two', '1' => 'one'];
            // String comparison: "1" < "10" < "2"
            expect($this->canonicalizer->canonicalize($input))->toBe('{"1":"one","10":"ten","2":"two"}');
        });
    });

    describe('Section 3.2.4 - String Escaping', function () {
        it('escapes control characters', function () {
            expect($this->canonicalizer->canonicalize("\n"))->toBe('"\n"');
            expect($this->canonicalizer->canonicalize("\t"))->toBe('"\t"');
            expect($this->canonicalizer->canonicalize("\r"))->toBe('"\r"');
        });

        it('escapes backslash and quote', function () {
            expect($this->canonicalizer->canonicalize('\\'))->toBe('"\\\\"');
            expect($this->canonicalizer->canonicalize('"'))->toBe('"\\""');
        });

        it('preserves Unicode characters unescaped', function () {
            // Using actual Unicode characters, not escape sequences
            expect($this->canonicalizer->canonicalize('café'))->toBe('"café"');
            expect($this->canonicalizer->canonicalize('€'))->toBe('"€"');
        });
    });

    describe('Facade integration', function () {
        it('works via the facade', function () {
            $result = CanonicalJson::canonicalize(['b' => 2, 'a' => 1]);
            expect($result)->toBe('{"a":1,"b":2}');
        });
    });

    describe('Main RFC 8785 Example', function () {
        it('produces the expected output for the main RFC example', function () {
            // The main example from RFC 8785 Appendix B
            // Note: Using actual characters, not PHP \u{} escape sequences in strings
            $input = (object) [
                'numbers' => [333333333.33333329, 1e30, 4.5, 2e-3, 0.000000000000000000000000001],
                'string' => "€$\x0f\nA'B\"\\\\\"/",
                'literals' => [null, true, false],
            ];

            $result = $this->canonicalizer->canonicalize($input);

            // Expected output from RFC 8785 (keys sorted, numbers formatted)
            $expected = '{"literals":[null,true,false],"numbers":[333333333.3333333,1e+30,4.5,0.002,1e-27],"string":"€$\u000f\nA\'B\"\\\\\\\\\"/"}';;

            expect($result)->toBe($expected);
        });

        it('produces a consistent hash', function () {
            $input = (object) [
                'numbers' => [333333333.33333329, 1e30, 4.5, 2e-3, 0.000000000000000000000000001],
                'string' => "€$\x0f\nA'B\"\\\\\"/",
                'literals' => [null, true, false],
            ];

            $canonical = $this->canonicalizer->canonicalize($input);
            $hash = hash('sha256', $canonical);

            // This tests determinism - same input always produces same hash
            $hash2 = hash('sha256', $this->canonicalizer->canonicalize($input));
            expect($hash)->toBe($hash2);
        });
    });

    describe('Recursive sorting', function () {
        it('sorts nested object keys', function () {
            $input = [
                'z' => ['z' => 1, 'a' => 2],
                'a' => ['z' => 3, 'a' => 4],
            ];
            $result = $this->canonicalizer->canonicalize($input);
            expect($result)->toBe('{"a":{"a":4,"z":3},"z":{"a":2,"z":1}}');
        });

        it('preserves array order while sorting object keys', function () {
            $input = [
                'arr' => [
                    ['z' => 1, 'a' => 2],
                    ['y' => 3, 'b' => 4],
                ],
            ];
            $result = $this->canonicalizer->canonicalize($input);
            expect($result)->toBe('{"arr":[{"a":2,"z":1},{"b":4,"y":3}]}');
        });
    });
});
