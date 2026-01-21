#!/bin/bash

# Canonical JSON Comparison Script
# Runs both JavaScript and PHP canonicalization and compares results

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Check for input file argument
if [ -z "$1" ]; then
    echo -e "${RED}Error: No input file specified${NC}"
    echo "Usage: ./compare.sh <json-file>"
    exit 1
fi

INPUT_FILE="$1"

# Check if input file exists
if [ ! -f "$INPUT_FILE" ]; then
    echo -e "${RED}Error: File not found: $INPUT_FILE${NC}"
    exit 1
fi

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       Canonical JSON Comparison Tool                       ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}Input file:${NC} $INPUT_FILE"
echo -e "${YELLOW}Contents:${NC}"
cat "$INPUT_FILE"
echo ""
echo ""

# Run JavaScript canonicalization
echo -e "${BLUE}────────────────────────────────────────────────────────────${NC}"
JS_OUTPUT=$(node "$SCRIPT_DIR/canonicalize.js" "$INPUT_FILE" 2>&1)
JS_EXIT_CODE=$?

if [ $JS_EXIT_CODE -ne 0 ]; then
    echo -e "${RED}JavaScript Error:${NC}"
    echo "$JS_OUTPUT"
else
    echo "$JS_OUTPUT"
fi

echo ""

# Run PHP canonicalization
echo -e "${BLUE}────────────────────────────────────────────────────────────${NC}"
PHP_OUTPUT=$(php "$SCRIPT_DIR/canonicalize.php" "$INPUT_FILE" 2>&1)
PHP_EXIT_CODE=$?

if [ $PHP_EXIT_CODE -ne 0 ]; then
    echo -e "${RED}PHP Error:${NC}"
    echo "$PHP_OUTPUT"
else
    echo "$PHP_OUTPUT"
fi

echo ""

# Extract hashes for comparison
JS_HASH=$(echo "$JS_OUTPUT" | grep -A1 "SHA256 Hash:" | tail -1)
PHP_HASH=$(echo "$PHP_OUTPUT" | grep -A1 "SHA256 Hash:" | tail -1)

# Compare results
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo -e "${PURPLE}COMPARISON RESULTS${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
echo ""

if [ $JS_EXIT_CODE -ne 0 ] || [ $PHP_EXIT_CODE -ne 0 ]; then
    echo -e "${RED}✗ One or both scripts encountered errors${NC}"
    exit 1
fi

echo -e "JS Hash:  ${YELLOW}$JS_HASH${NC}"
echo -e "PHP Hash: ${YELLOW}$PHP_HASH${NC}"
echo ""

if [ "$JS_HASH" = "$PHP_HASH" ]; then
    echo -e "${GREEN}✓ MATCH: Both packages produce identical canonical JSON!${NC}"
else
    echo -e "${RED}✗ MISMATCH: The packages produce different canonical JSON!${NC}"
    echo ""
    echo -e "${YELLOW}Canonical outputs differ. Check the JSON strings above for differences.${NC}"
fi
