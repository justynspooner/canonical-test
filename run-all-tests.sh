#!/bin/bash

# Run all canonical JSON comparison tests

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TESTS_DIR="$SCRIPT_DIR/tests"

# Counters
PASSED=0
FAILED=0
ERRORS=0

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       Canonical JSON Test Suite                            ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Check if tests directory exists
if [ ! -d "$TESTS_DIR" ]; then
    echo -e "${RED}Error: tests directory not found${NC}"
    exit 1
fi

# Run each test
for test_file in "$TESTS_DIR"/*.json; do
    if [ ! -f "$test_file" ]; then
        continue
    fi
    
    test_name=$(basename "$test_file")
    
    # Run JS canonicalization
    JS_OUTPUT=$(node "$SCRIPT_DIR/canonicalize.js" "$test_file" 2>&1)
    JS_EXIT=$?
    
    # Run PHP canonicalization
    PHP_OUTPUT=$(php "$SCRIPT_DIR/canonicalize.php" "$test_file" 2>&1)
    PHP_EXIT=$?
    
    # Extract hashes
    JS_HASH=$(echo "$JS_OUTPUT" | grep -A1 "SHA256 Hash:" | tail -1)
    PHP_HASH=$(echo "$PHP_OUTPUT" | grep -A1 "SHA256 Hash:" | tail -1)
    
    # Extract canonical JSON for diff display
    JS_CANONICAL=$(echo "$JS_OUTPUT" | grep -A1 "Canonical JSON:" | tail -1)
    PHP_CANONICAL=$(echo "$PHP_OUTPUT" | grep -A1 "Canonical JSON:" | tail -1)
    
    # Check results
    if [ $JS_EXIT -ne 0 ] || [ $PHP_EXIT -ne 0 ]; then
        echo -e "${RED}✗ ERROR${NC}  $test_name"
        if [ $JS_EXIT -ne 0 ]; then
            echo -e "  ${RED}JS Error:${NC} $(echo "$JS_OUTPUT" | head -1)"
        fi
        if [ $PHP_EXIT -ne 0 ]; then
            echo -e "  ${RED}PHP Error:${NC} $(echo "$PHP_OUTPUT" | head -1)"
        fi
        ((ERRORS++))
    elif [ "$JS_HASH" = "$PHP_HASH" ]; then
        echo -e "${GREEN}✓ PASS${NC}   $test_name"
        ((PASSED++))
    else
        echo -e "${RED}✗ FAIL${NC}   $test_name"
        echo -e "  ${YELLOW}JS:${NC}  $JS_CANONICAL"
        echo -e "  ${YELLOW}PHP:${NC} $PHP_CANONICAL"
        ((FAILED++))
    fi
done

echo ""
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${PURPLE}TEST SUMMARY${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${GREEN}Passed:${NC}  $PASSED"
echo -e "${RED}Failed:${NC}  $FAILED"
echo -e "${YELLOW}Errors:${NC}  $ERRORS"
echo ""

TOTAL=$((PASSED + FAILED + ERRORS))
if [ $FAILED -eq 0 ] && [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}All $TOTAL tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed or had errors.${NC}"
    exit 1
fi
