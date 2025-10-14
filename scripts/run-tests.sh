#!/bin/bash

# Prism Browser Test Runner
# This script runs all tests for the Prism Browser project

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to run backend tests
run_backend_tests() {
    print_status "Running backend tests..."
    
    if [ ! -d "backend" ]; then
        print_error "Backend directory not found!"
        return 1
    fi
    
    cd backend
    
    # Check if PHPUnit is available
    if ! command_exists phpunit && [ ! -f "vendor/bin/phpunit" ]; then
        print_error "PHPUnit not found! Please run 'composer install' first."
        return 1
    fi
    
    # Run PHPUnit tests
    if [ -f "vendor/bin/phpunit" ]; then
        ./vendor/bin/phpunit --configuration phpunit.xml
    else
        phpunit --configuration phpunit.xml
    fi
    
    cd ..
    print_success "Backend tests completed!"
}

# Function to run frontend tests
run_frontend_tests() {
    print_status "Running frontend tests..."
    
    if [ ! -d "frontend" ]; then
        print_error "Frontend directory not found!"
        return 1
    fi
    
    cd frontend
    
    # Check if npm is available
    if ! command_exists npm; then
        print_error "npm not found! Please install Node.js and npm first."
        return 1
    fi
    
    # Check if node_modules exists
    if [ ! -d "node_modules" ]; then
        print_warning "node_modules not found. Installing dependencies..."
        npm install
    fi
    
    # Run Vitest tests
    if [ -f "package.json" ] && grep -q "vitest" package.json; then
        npm run test
    else
        print_warning "Vitest not configured in package.json"
    fi
    
    cd ..
    print_success "Frontend tests completed!"
}

# Function to run integration tests
run_integration_tests() {
    print_status "Running integration tests..."
    
    # Check if backend is running
    if ! curl -s http://localhost:8000/health >/dev/null 2>&1; then
        print_warning "Backend not running. Starting backend server..."
        cd backend
        php -S localhost:8000 -t public &
        BACKEND_PID=$!
        sleep 3
        cd ..
    fi
    
    # Run integration tests if they exist
    if [ -d "tests/integration" ]; then
        cd tests/integration
        if [ -f "run.sh" ]; then
            chmod +x run.sh
            ./run.sh
        else
            print_warning "No integration test runner found"
        fi
        cd ../..
    else
        print_warning "No integration tests found"
    fi
    
    # Clean up backend process if we started it
    if [ ! -z "$BACKEND_PID" ]; then
        kill $BACKEND_PID 2>/dev/null || true
    fi
    
    print_success "Integration tests completed!"
}

# Function to run all tests
run_all_tests() {
    print_status "Running all tests..."
    
    local exit_code=0
    
    # Run backend tests
    if ! run_backend_tests; then
        exit_code=1
    fi
    
    # Run frontend tests
    if ! run_frontend_tests; then
        exit_code=1
    fi
    
    # Run integration tests
    if ! run_integration_tests; then
        exit_code=1
    fi
    
    if [ $exit_code -eq 0 ]; then
        print_success "All tests passed!"
    else
        print_error "Some tests failed!"
    fi
    
    return $exit_code
}

# Function to show help
show_help() {
    echo "Prism Browser Test Runner"
    echo ""
    echo "Usage: $0 [OPTIONS] [TEST_TYPE]"
    echo ""
    echo "OPTIONS:"
    echo "  -h, --help     Show this help message"
    echo "  -v, --verbose  Enable verbose output"
    echo "  -c, --coverage Generate coverage reports"
    echo ""
    echo "TEST_TYPE:"
    echo "  backend       Run only backend tests"
    echo "  frontend      Run only frontend tests"
    echo "  integration   Run only integration tests"
    echo "  all           Run all tests (default)"
    echo ""
    echo "Examples:"
    echo "  $0                    # Run all tests"
    echo "  $0 backend            # Run only backend tests"
    echo "  $0 --coverage all     # Run all tests with coverage"
}

# Main script logic
main() {
    local test_type="all"
    local verbose=false
    local coverage=false
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -h|--help)
                show_help
                exit 0
                ;;
            -v|--verbose)
                verbose=true
                shift
                ;;
            -c|--coverage)
                coverage=true
                shift
                ;;
            backend|frontend|integration|all)
                test_type="$1"
                shift
                ;;
            *)
                print_error "Unknown option: $1"
                show_help
                exit 1
                ;;
        esac
    done
    
    # Set verbose mode if requested
    if [ "$verbose" = true ]; then
        set -x
    fi
    
    # Set coverage mode if requested
    if [ "$coverage" = true ]; then
        export COVERAGE=true
    fi
    
    # Run tests based on type
    case $test_type in
        backend)
            run_backend_tests
            ;;
        frontend)
            run_frontend_tests
            ;;
        integration)
            run_integration_tests
            ;;
        all)
            run_all_tests
            ;;
        *)
            print_error "Invalid test type: $test_type"
            show_help
            exit 1
            ;;
    esac
}

# Run main function with all arguments
main "$@"
