<?php
/**
 * RFC 8785 - JSON Canonicalization Scheme (JCS) Verification
 * 
 * This script verifies that the FixedJsonCanonicalizer correctly implements
 * RFC 8785 by testing against the examples and test vectors from the spec.
 */

require_once __DIR__ . '/FixedJsonCanonicalizer.php';

$GREEN = "\033[32m";
$RED = "\033[31m";
$YELLOW = "\033[33m";
$BLUE = "\033[34m";
$NC = "\033[0m";

$passed = 0;
$failed = 0;

$canonicalizer = new FixedJsonCanonicalizer();

function test($name, $input, $expectedCanonical, $expectedHex = null) {
    global $canonicalizer, $passed, $failed, $GREEN, $RED, $NC;
    
    $canonical = $canonicalizer->canonicalize($input);
    $actualHex = bin2hex($canonical);
    
    $success = ($canonical === $expectedCanonical);
    if ($expectedHex && $success) {
        $normalizedExpected = str_replace(' ', '', $expectedHex);
        $success = ($actualHex === $normalizedExpected);
    }
    
    if ($success) {
        echo "{$GREEN}✓ PASS{$NC} {$name}\n";
        $passed++;
    } else {
        echo "{$RED}✗ FAIL{$NC} {$name}\n";
        echo "  Expected: {$expectedCanonical}\n";
        echo "  Actual:   {$canonical}\n";
        if ($expectedHex) {
            $normalizedExpected = str_replace(' ', '', $expectedHex);
            echo "  Expected Hex: {$normalizedExpected}\n";
            echo "  Actual Hex:   {$actualHex}\n";
        }
        $failed++;
    }
}

echo "{$BLUE}╔════════════════════════════════════════════════════════════╗{$NC}\n";
echo "{$BLUE}║       RFC 8785 Compliance Verification (PHP)              ║{$NC}\n";
echo "{$BLUE}╚════════════════════════════════════════════════════════════╝{$NC}\n";
echo "\n";

// =============================================================================
// Section 3.2.2 - Main Example from RFC 8785
// =============================================================================
echo "{$YELLOW}=== Section 3.2.2: Main Serialization Example ==={$NC}\n";

$mainExample = (object)[
    'numbers' => [333333333.33333329, 1e30, 4.5, 2e-3, 0.000000000000000000000000001],
    'string' => "€$\x0f\nA'B\"\\\\\"/",
    'literals' => [null, true, false],
];

$mainExpected = '{"literals":[null,true,false],"numbers":[333333333.3333333,1e+30,4.5,0.002,1e-27],"string":"€$\u000f\nA\'B\"\\\\\\\\\"/"}';;

$mainExpectedHex = 
    "7b 22 6c 69 74 65 72 61 6c 73 22 3a 5b 6e 75 6c 6c 2c 74 72 " .
    "75 65 2c 66 61 6c 73 65 5d 2c 22 6e 75 6d 62 65 72 73 22 3a " .
    "5b 33 33 33 33 33 33 33 33 33 2e 33 33 33 33 33 33 33 2c 31 " .
    "65 2b 33 30 2c 34 2e 35 2c 30 2e 30 30 32 2c 31 65 2d 32 37 " .
    "5d 2c 22 73 74 72 69 6e 67 22 3a 22 e2 82 ac 24 5c 75 30 30 " .
    "30 66 5c 6e 41 27 42 5c 22 5c 5c 5c 5c 5c 22 2f 22 7d";

test("Main example with hex verification", $mainExample, $mainExpected, $mainExpectedHex);

echo "\n";

// =============================================================================
// Section 3.2.3 - Property Sorting (UTF-16 code unit comparison)
// =============================================================================
echo "{$YELLOW}=== Section 3.2.3: Property Sorting (UTF-16) ==={$NC}\n";

$sortingExample = (object)[
    "€" => "Euro Sign",
    "\r" => "Carriage Return",
    "\u{fb33}" => "Hebrew Letter Dalet With Dagesh",
    "1" => "One",
    "\u{1f600}" => "Emoji: Grinning Face",  // 😀
    "\u{0080}" => "Control",  // U+0080 in proper UTF-8 encoding
    "ö" => "Latin Small Letter O With Diaeresis",
];

// Expected order per RFC: \r (0x0D), 1 (0x31), U+0080, ö (0xF6), € (0x20AC), 😀, \ufb33
// We verify the ORDER is correct by checking key positions in the canonical STRING
$canonical = $canonicalizer->canonicalize($sortingExample);
$expectedKeyOrder = ["\r", "1", "\u{0080}", "ö", "€", "\u{1f600}", "\u{fb33}"];

// Extract keys from canonical output by finding "key": patterns
// Use a simpler approach: split on ":" and extract keys
preg_match_all('/"(?:[^"\\\\]|\\\\["\\\\\\/bfnrt]|\\\\u[0-9a-fA-F]{4})*"(?=:)/', $canonical, $matches);
$keysInOrder = array_map(function($m) {
    return json_decode($m);
}, $matches[0]);

if ($keysInOrder === $expectedKeyOrder) {
    echo "{$GREEN}✓ PASS{$NC} Property sorting (UTF-16 order)\n";
    $passed++;
} else {
    echo "{$RED}✗ FAIL{$NC} Property sorting (UTF-16 order)\n";
    echo "  Expected: " . implode(', ', array_map(function($k) { return sprintf('U+%04X', mb_ord($k)); }, $expectedKeyOrder)) . "\n";
    echo "  Actual:   " . implode(', ', array_map(function($k) { return sprintf('U+%04X', mb_ord($k)); }, $keysInOrder)) . "\n";
    $failed++;
}

echo "\n";

// =============================================================================
// Section 3.2.2.1 - Literals
// =============================================================================
echo "{$YELLOW}=== Section 3.2.2.1: Literals ==={$NC}\n";

test("null literal", null, "null");
test("true literal", true, "true");
test("false literal", false, "false");

echo "\n";

// =============================================================================
// Section 3.2.2.2 - String Serialization
// =============================================================================
echo "{$YELLOW}=== Section 3.2.2.2: String Serialization ==={$NC}\n";

test("Control char 0x00", "\x00", '"\u0000"');
test("Control char 0x01", "\x01", '"\u0001"');
test("Control char 0x1f", "\x1f", '"\u001f"');

test("Backspace (\\b)", "\x08", '"\b"');
test("Tab (\\t)", "\t", '"\t"');
test("Newline (\\n)", "\n", '"\n"');
test("Form feed (\\f)", "\f", '"\f"');
test("Carriage return (\\r)", "\r", '"\r"');

test("Quote", '"', '"\\""');
test("Backslash", "\\", '"\\\\"');

test("Regular ASCII", "Hello", '"Hello"');
test("Unicode (Euro sign)", "€", '"€"');
test("Unicode (Chinese)", "中文", '"中文"');

echo "\n";

// =============================================================================
// Section 3.2.2.3 / Appendix B - Number Serialization (IEEE 754)
// =============================================================================
echo "{$YELLOW}=== Section 3.2.2.3 / Appendix B: Number Serialization ==={$NC}\n";

test("Zero", 0, "0");
test("Zero (float)", 0.0, "0");
test("Min positive (5e-324)", 5e-324, "5e-324");
test("Min negative (-5e-324)", -5e-324, "-5e-324");
test("Max positive", 1.7976931348623157e+308, "1.7976931348623157e+308");
test("Max negative", -1.7976931348623157e+308, "-1.7976931348623157e+308");
test("Max safe integer", 9007199254740992, "9007199254740992");
test("Min safe integer", -9007199254740992, "-9007199254740992");

// Precision edge cases
test("1e+23", 1e+23, "1e+23");
test("1e+21", 1e+21, "1e+21");
test("0.000001", 0.000001, "0.000001");

// 333333333 series
test("333333333.3333333", 333333333.3333333, "333333333.3333333");

echo "\n";

// =============================================================================
// Section 3.2.1 - Whitespace
// =============================================================================
echo "{$YELLOW}=== Section 3.2.1: No Whitespace ==={$NC}\n";

test("Object without whitespace", (object)['a' => 1, 'b' => 2], '{"a":1,"b":2}');
test("Array without whitespace", [1, 2, 3], "[1,2,3]");
test("Nested without whitespace", (object)['a' => (object)['b' => [1]]], '{"a":{"b":[1]}}');

echo "\n";

// =============================================================================
// Section 3.2.3 - Recursive Sorting
// =============================================================================
echo "{$YELLOW}=== Section 3.2.3: Recursive Sorting ==={$NC}\n";

test(
    "Nested object sorting",
    (object)['z' => (object)['z' => 1, 'a' => 2], 'a' => (object)['z' => 3, 'a' => 4]],
    '{"a":{"a":4,"z":3},"z":{"a":2,"z":1}}'
);

test(
    "Array with objects (order preserved, properties sorted)",
    (object)['arr' => [(object)['z' => 1, 'a' => 2], (object)['y' => 3, 'b' => 4]]],
    '{"arr":[{"a":2,"z":1},{"b":4,"y":3}]}'
);

echo "\n";

// =============================================================================
// Summary
// =============================================================================
echo "{$BLUE}════════════════════════════════════════════════════════════{$NC}\n";
echo "{$YELLOW}SUMMARY{$NC}\n";
echo "{$BLUE}════════════════════════════════════════════════════════════{$NC}\n";
echo "\n";
echo "{$GREEN}Passed:{$NC} {$passed}\n";
echo "{$RED}Failed:{$NC} {$failed}\n";
echo "\n";

if ($failed === 0) {
    echo "{$GREEN}All RFC 8785 compliance tests passed!{$NC}\n";
    exit(0);
} else {
    echo "{$RED}Some RFC 8785 compliance tests failed.{$NC}\n";
    exit(1);
}
