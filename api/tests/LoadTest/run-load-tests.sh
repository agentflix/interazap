#!/bin/bash
#
# Load Test Runner Script
# Runs k6 load tests and generates reports
#
# Usage:
#   ./run-load-tests.sh [scenario] [options]
#
# Scenarios:
#   baseline  - Basic performance measurement
#   stress    - 100 req/s sustained load
#   spike     - 500 req/s burst test
#   soak      - 50 req/s for 10 minutes
#   all       - Run baseline, stress, and spike
#
# Options:
#   --url URL     Base URL (default: http://localhost:8000)
#   --token TOKEN Auth token for API
#   --output DIR  Output directory for results
#

set -e

# Default configuration
BASE_URL="${BASE_URL:-http://localhost:8000}"
AUTH_TOKEN="${AUTH_TOKEN:-test-token}"
OUTPUT_DIR="results"
SCENARIO="${1:-baseline}"

# Parse arguments
shift || true
while [[ $# -gt 0 ]]; do
    case $1 in
        --url)
            BASE_URL="$2"
            shift 2
            ;;
        --token)
            AUTH_TOKEN="$2"
            shift 2
            ;;
        --output)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check k6 installation
check_k6() {
    if ! command -v k6 &> /dev/null; then
        echo -e "${RED}Error: k6 is not installed${NC}"
        echo ""
        echo "Install k6:"
        echo "  macOS:   brew install k6"
        echo "  Linux:   sudo apt-get install k6"
        echo "  Docker:  docker run -i grafana/k6 run -"
        echo ""
        exit 1
    fi
    echo -e "${GREEN}✓ k6 found: $(k6 version)${NC}"
}

# Run a single test
run_test() {
    local test_name=$1
    local test_file="${test_name}.js"
    
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Running: ${test_name}${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    if [[ ! -f "$test_file" ]]; then
        echo -e "${RED}Error: Test file not found: ${test_file}${NC}"
        return 1
    fi
    
    mkdir -p "$OUTPUT_DIR"
    
    k6 run \
        -e BASE_URL="$BASE_URL" \
        -e AUTH_TOKEN="$AUTH_TOKEN" \
        --out json="${OUTPUT_DIR}/${test_name}-detailed.json" \
        "$test_file"
    
    local exit_code=$?
    
    if [[ $exit_code -eq 0 ]]; then
        echo -e "${GREEN}✓ ${test_name} passed${NC}"
    else
        echo -e "${RED}✗ ${test_name} failed${NC}"
    fi
    
    return $exit_code
}

# Print summary
print_summary() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Load Test Summary${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo "Results saved in: ${OUTPUT_DIR}/"
    echo ""
    
    for result_file in "${OUTPUT_DIR}"/*.json; do
        if [[ -f "$result_file" && ! "$result_file" == *"-detailed"* ]]; then
            local test_name=$(basename "$result_file" .json)
            echo -e "${YELLOW}${test_name}:${NC}"
            cat "$result_file" | head -20
            echo ""
        fi
    done
}

# Main execution
main() {
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  AgentFlix Load Test Suite${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo ""
    echo "Configuration:"
    echo "  URL:      $BASE_URL"
    echo "  Scenario: $SCENARIO"
    echo "  Output:   $OUTPUT_DIR"
    echo ""
    
    check_k6
    
    case $SCENARIO in
        baseline)
            run_test "baseline"
            ;;
        stress)
            run_test "stress"
            ;;
        spike)
            run_test "spike"
            ;;
        soak)
            run_test "soak"
            ;;
        all)
            run_test "baseline" || true
            run_test "stress" || true
            run_test "spike" || true
            ;;
        full)
            run_test "full-suite"
            ;;
        *)
            echo -e "${RED}Unknown scenario: ${SCENARIO}${NC}"
            echo ""
            echo "Available scenarios: baseline, stress, spike, soak, all, full"
            exit 1
            ;;
    esac
    
    print_summary
    
    echo -e "${GREEN}Load tests completed!${NC}"
}

main
