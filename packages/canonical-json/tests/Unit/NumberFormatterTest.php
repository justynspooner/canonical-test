<?php

declare(strict_types=1);

use DOVU\CanonicalJson\Support\NumberFormatter;

describe('NumberFormatter', function () {
    describe('toEs6Format', function () {
        it('formats zero correctly', function () {
            expect(NumberFormatter::toEs6Format(0.0))->toBe('0');
            expect(NumberFormatter::toEs6Format(-0.0))->toBe('0');
        });

        it('formats integers within safe range', function () {
            expect(NumberFormatter::toEs6Format(1.0))->toBe('1');
            expect(NumberFormatter::toEs6Format(42.0))->toBe('42');
            expect(NumberFormatter::toEs6Format(-100.0))->toBe('-100');
            expect(NumberFormatter::toEs6Format(9007199254740991.0))->toBe('9007199254740991');
        });

        it('formats simple decimals', function () {
            expect(NumberFormatter::toEs6Format(1.5))->toBe('1.5');
            expect(NumberFormatter::toEs6Format(3.14159))->toBe('3.14159');
            expect(NumberFormatter::toEs6Format(-2.5))->toBe('-2.5');
        });

        it('formats boundary small numbers in decimal notation', function () {
            // Numbers >= 1e-6 should use decimal notation
            expect(NumberFormatter::toEs6Format(0.000001))->toBe('0.000001');
        });

        it('formats very small numbers in scientific notation', function () {
            // Numbers < 1e-6 should use scientific notation
            expect(NumberFormatter::toEs6Format(1e-7))->toBe('1e-7');
            expect(NumberFormatter::toEs6Format(1.5e-10))->toBe('1.5e-10');
        });

        it('formats large numbers in decimal notation', function () {
            // Numbers < 1e+21 should use decimal notation
            expect(NumberFormatter::toEs6Format(1e20))->toBe('100000000000000000000');
        });

        it('formats very large numbers in scientific notation', function () {
            // Numbers >= 1e+21 should use scientific notation
            expect(NumberFormatter::toEs6Format(1e21))->toBe('1e+21');
            expect(NumberFormatter::toEs6Format(1e30))->toBe('1e+30');
        });

        it('throws exception for NaN', function () {
            NumberFormatter::toEs6Format(NAN);
        })->throws(LogicException::class, 'Infinity or NaN cannot be represented in JSON');

        it('throws exception for positive infinity', function () {
            NumberFormatter::toEs6Format(INF);
        })->throws(LogicException::class, 'Infinity or NaN cannot be represented in JSON');

        it('throws exception for negative infinity', function () {
            NumberFormatter::toEs6Format(-INF);
        })->throws(LogicException::class, 'Infinity or NaN cannot be represented in JSON');

        it('preserves precision for IEEE 754 edge cases', function () {
            // These are specific values from RFC 8785
            expect(NumberFormatter::toEs6Format(0.000001))->toBe('0.000001');
            expect(NumberFormatter::toEs6Format(1e20))->toBe('100000000000000000000');
            expect(NumberFormatter::toEs6Format(1e21))->toBe('1e+21');
        });

        it('handles 333333333.3333333 precision test', function () {
            // From RFC 8785 - this is the canonical representation
            expect(NumberFormatter::toEs6Format(333333333.3333333))->toBe('333333333.3333333');
        });

        it('formats max/min doubles correctly', function () {
            expect(NumberFormatter::toEs6Format(1.7976931348623157e+308))->toBe('1.7976931348623157e+308');
            expect(NumberFormatter::toEs6Format(-1.7976931348623157e+308))->toBe('-1.7976931348623157e+308');
            expect(NumberFormatter::toEs6Format(5e-324))->toBe('5e-324');
        });
    });
});
