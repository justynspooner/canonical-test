/**
 * RFC 8785 - JSON Canonicalization Scheme (JCS) Verification
 * 
 * This script verifies that the canonicalize package correctly implements
 * RFC 8785 by testing against the examples and test vectors from the spec.
 */

import canonicalize from "canonicalize";
import crypto from "crypto";

const GREEN = "\x1b[32m";
const RED = "\x1b[31m";
const YELLOW = "\x1b[33m";
const BLUE = "\x1b[34m";
const NC = "\x1b[0m";

let passed = 0;
let failed = 0;

function test(name, input, expectedCanonical, expectedHex = null) {
  const canonical = canonicalize(input);
  const actualHex = Buffer.from(canonical, "utf-8").toString("hex");

  let success = canonical === expectedCanonical;
  if (expectedHex && success) {
    // Normalize hex comparison (remove spaces)
    const normalizedExpected = expectedHex.replace(/\s+/g, "");
    success = actualHex === normalizedExpected;
  }

  if (success) {
    console.log(`${GREEN}✓ PASS${NC} ${name}`);
    passed++;
  } else {
    console.log(`${RED}✗ FAIL${NC} ${name}`);
    console.log(`  Expected: ${expectedCanonical}`);
    console.log(`  Actual:   ${canonical}`);
    if (expectedHex) {
      console.log(`  Expected Hex: ${expectedHex.replace(/\s+/g, "")}`);
      console.log(`  Actual Hex:   ${actualHex}`);
    }
    failed++;
  }
}

console.log(`${BLUE}╔════════════════════════════════════════════════════════════╗${NC}`);
console.log(`${BLUE}║       RFC 8785 Compliance Verification                     ║${NC}`);
console.log(`${BLUE}╚════════════════════════════════════════════════════════════╝${NC}`);
console.log("");

// =============================================================================
// Section 3.2.2 - Main Example from RFC 8785
// =============================================================================
console.log(`${YELLOW}=== Section 3.2.2: Main Serialization Example ===${NC}`);

const mainExample = {
  numbers: [333333333.33333329, 1e30, 4.5, 2e-3, 0.000000000000000000000000001],
  string: "€$\x0f\nA'B\"\\\\\"/",
  literals: [null, true, false],
};

// Expected output from RFC (line wrap removed)
const mainExpected =
  '{"literals":[null,true,false],"numbers":[333333333.3333333,1e+30,4.5,0.002,1e-27],"string":"€$\\u000f\\nA\'B\\"\\\\\\\\\\"/"}';

// Expected hex from RFC Section 3.2.4
const mainExpectedHex =
  "7b 22 6c 69 74 65 72 61 6c 73 22 3a 5b 6e 75 6c 6c 2c 74 72 " +
  "75 65 2c 66 61 6c 73 65 5d 2c 22 6e 75 6d 62 65 72 73 22 3a " +
  "5b 33 33 33 33 33 33 33 33 33 2e 33 33 33 33 33 33 33 2c 31 " +
  "65 2b 33 30 2c 34 2e 35 2c 30 2e 30 30 32 2c 31 65 2d 32 37 " +
  "5d 2c 22 73 74 72 69 6e 67 22 3a 22 e2 82 ac 24 5c 75 30 30 " +
  "30 66 5c 6e 41 27 42 5c 22 5c 5c 5c 5c 5c 22 2f 22 7d";

test("Main example with hex verification", mainExample, mainExpected, mainExpectedHex);

console.log("");

// =============================================================================
// Section 3.2.3 - Property Sorting (UTF-16 code unit comparison)
// =============================================================================
console.log(`${YELLOW}=== Section 3.2.3: Property Sorting (UTF-16) ===${NC}`);

const sortingExample = {
  "€": "Euro Sign",
  "\r": "Carriage Return",
  "\ufb33": "Hebrew Letter Dalet With Dagesh",
  "1": "One",
  "😀": "Emoji: Grinning Face",
  "\x80": "Control",
  "ö": "Latin Small Letter O With Diaeresis",
};

// Expected order per RFC: \r (0x0D), 1 (0x31), \x80, ö (0xF6), € (0x20AC), 😀 (surrogate), \ufb33
// We verify the ORDER is correct by checking key positions in the canonical STRING
// (JSON.parse reorders integer-like keys, so we can't use that)
const canonical = canonicalize(sortingExample);

// Extract key positions from the canonical string using regex
const keyPattern = /"([^"\\]|\\["\\/bfnrt]|\\u[0-9a-fA-F]{4})*":/g;
const matches = canonical.match(keyPattern);
const keysInOrder = matches.map((m) => JSON.parse(m.slice(0, -1))); // Remove trailing ':'

// Expected order: \r < 1 < \x80 < ö < € < 😀 < \ufb33
const expectedOrder = ["\r", "1", "\x80", "ö", "€", "😀", "\ufb33"];

if (JSON.stringify(keysInOrder) === JSON.stringify(expectedOrder)) {
  console.log(`${GREEN}✓ PASS${NC} Property sorting (UTF-16 order)`);
  passed++;
} else {
  console.log(`${RED}✗ FAIL${NC} Property sorting (UTF-16 order)`);
  console.log(`  Expected order: ${expectedOrder.map((k) => k.codePointAt(0).toString(16)).join(", ")}`);
  console.log(`  Actual order:   ${keysInOrder.map((k) => k.codePointAt(0).toString(16)).join(", ")}`);
  failed++;
}

console.log("");

// =============================================================================
// Section 3.2.2.1 - Literals
// =============================================================================
console.log(`${YELLOW}=== Section 3.2.2.1: Literals ===${NC}`);

test("null literal", null, "null");
test("true literal", true, "true");
test("false literal", false, "false");

console.log("");

// =============================================================================
// Section 3.2.2.2 - String Serialization
// =============================================================================
console.log(`${YELLOW}=== Section 3.2.2.2: String Serialization ===${NC}`);

// Control characters that MUST use \uXXXX notation
test("Control char 0x00", "\x00", '"\\u0000"');
test("Control char 0x01", "\x01", '"\\u0001"');
test("Control char 0x1f", "\x1f", '"\\u001f"');

// Predefined escape sequences
test("Backspace (\\b)", "\b", '"\\b"');
test("Tab (\\t)", "\t", '"\\t"');
test("Newline (\\n)", "\n", '"\\n"');
test("Form feed (\\f)", "\f", '"\\f"');
test("Carriage return (\\r)", "\r", '"\\r"');

// Characters that MUST be escaped
test("Quote (\\')", '"', '"\\""');
test("Backslash (\\\\)", "\\", '"\\\\"');

// Characters outside ASCII control range serialized as-is
test("Regular ASCII", "Hello", '"Hello"');
test("Unicode (Euro sign)", "€", '"€"');
test("Unicode (Chinese)", "中文", '"中文"');

console.log("");

// =============================================================================
// Section 3.2.2.3 / Appendix B - Number Serialization (IEEE 754)
// =============================================================================
console.log(`${YELLOW}=== Section 3.2.2.3 / Appendix B: Number Serialization ===${NC}`);

// From the table in Appendix B
test("Zero", 0, "0");
test("Minus zero", -0, "0"); // -0 serializes as 0
test("Min positive (5e-324)", 5e-324, "5e-324");
test("Min negative (-5e-324)", -5e-324, "-5e-324");
test("Max positive", 1.7976931348623157e308, "1.7976931348623157e+308");
test("Max negative", -1.7976931348623157e308, "-1.7976931348623157e+308");
test("Max safe integer", 9007199254740992, "9007199254740992");
test("Min safe integer", -9007199254740992, "-9007199254740992");
test("~2^68", 295147905179352830000, "295147905179352830000");

// Precision edge cases from the table
test("9.999999999999997e+22", 9.999999999999997e22, "9.999999999999997e+22");
test("1e+23", 1e23, "1e+23");
test("1.0000000000000001e+23", 1.0000000000000001e23, "1.0000000000000001e+23");
test("999999999999999700000", 999999999999999700000, "999999999999999700000");
test("999999999999999900000", 999999999999999900000, "999999999999999900000");
test("1e+21", 1e21, "1e+21");
test("9.999999999999997e-7", 9.999999999999997e-7, "9.999999999999997e-7");
test("0.000001", 0.000001, "0.000001");

// 333333333 series from the table
test("333333333.3333332", 333333333.3333332, "333333333.3333332");
test("333333333.33333325", 333333333.33333325, "333333333.33333325");
test("333333333.3333333", 333333333.3333333, "333333333.3333333");
test("333333333.3333334", 333333333.3333334, "333333333.3333334");
test("333333333.33333343", 333333333.33333343, "333333333.33333343");

// Other edge cases
test("-0.0000033333333333333333", -0.0000033333333333333333, "-0.0000033333333333333333");
test("Round to even (1424953923781206.2)", 1424953923781206.2, "1424953923781206.2");

console.log("");

// =============================================================================
// Section 3.2.1 - Whitespace
// =============================================================================
console.log(`${YELLOW}=== Section 3.2.1: No Whitespace ===${NC}`);

test("Object without whitespace", { a: 1, b: 2 }, '{"a":1,"b":2}');
test("Array without whitespace", [1, 2, 3], "[1,2,3]");
test("Nested without whitespace", { a: { b: [1] } }, '{"a":{"b":[1]}}');

console.log("");

// =============================================================================
// Section 3.2.3 - Recursive Sorting
// =============================================================================
console.log(`${YELLOW}=== Section 3.2.3: Recursive Sorting ===${NC}`);

test(
  "Nested object sorting",
  { z: { z: 1, a: 2 }, a: { z: 3, a: 4 } },
  '{"a":{"a":4,"z":3},"z":{"a":2,"z":1}}'
);

test(
  "Array with objects (order preserved, properties sorted)",
  { arr: [{ z: 1, a: 2 }, { y: 3, b: 4 }] },
  '{"arr":[{"a":2,"z":1},{"b":4,"y":3}]}'
);

console.log("");

// =============================================================================
// Summary
// =============================================================================
console.log(`${BLUE}════════════════════════════════════════════════════════════${NC}`);
console.log(`${YELLOW}SUMMARY${NC}`);
console.log(`${BLUE}════════════════════════════════════════════════════════════${NC}`);
console.log("");
console.log(`${GREEN}Passed:${NC} ${passed}`);
console.log(`${RED}Failed:${NC} ${failed}`);
console.log("");

if (failed === 0) {
  console.log(`${GREEN}All RFC 8785 compliance tests passed!${NC}`);
  process.exit(0);
} else {
  console.log(`${RED}Some RFC 8785 compliance tests failed.${NC}`);
  process.exit(1);
}
