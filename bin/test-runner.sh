#!/bin/bash
# WordPress Test Suite Setup and Test Runner
# This script helps install WordPress Test Suite and run tests

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}WP GitHub Updater Test Runner${NC}"
echo -e "${BLUE}================================${NC}"
echo ""

# Function to check if WordPress Test Suite is installed
check_wp_tests() {
    local tests_dir="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
    
    if [ -d "$tests_dir" ] && [ -f "$tests_dir/includes/functions.php" ]; then
        return 0
    else
        return 1
    fi
}

# Function to install WordPress Test Suite
install_wp_tests() {
    echo -e "${YELLOW}WordPress Test Suite not found${NC}"
    echo ""
    echo "To run WordPress integration tests, you need to install WordPress Test Suite."
    echo ""
    echo -e "${GREEN}Installation command:${NC}"
    echo "  ./bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]"
    echo ""
    echo -e "${GREEN}Example:${NC}"
    echo "  ./bin/install-wp-tests.sh wordpress_test root '' localhost 6.7.1"
    echo ""
    read -p "Do you want to install now? (y/N) " -n 1 -r
    echo
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo ""
        echo "Please provide database credentials:"
        read -p "Database name (default: wordpress_test): " db_name
        db_name=${db_name:-wordpress_test}
        
        read -p "Database user (default: root): " db_user
        db_user=${db_user:-root}
        
        read -sp "Database password (default: empty): " db_pass
        echo
        
        read -p "Database host (default: localhost): " db_host
        db_host=${db_host:-localhost}
        
        read -p "WordPress version (default: 6.7.1): " wp_version
        wp_version=${wp_version:-6.7.1}
        
        echo ""
        echo -e "${BLUE}Installing WordPress Test Suite...${NC}"
        "$PROJECT_ROOT/bin/install-wp-tests.sh" "$db_name" "$db_user" "$db_pass" "$db_host" "$wp_version"
        
        if [ $? -eq 0 ]; then
            echo ""
            echo -e "${GREEN}✓ WordPress Test Suite installed successfully!${NC}"
            return 0
        else
            echo ""
            echo -e "${RED}✗ Failed to install WordPress Test Suite${NC}"
            return 1
        fi
    else
        echo ""
        echo "Skipping WordPress Test Suite installation."
        echo "You can install it later using the command above."
        return 1
    fi
}

# Function to run specific test suite
run_tests() {
    local suite=$1
    local phpunit="$PROJECT_ROOT/vendor/bin/phpunit"
    
    if [ ! -f "$phpunit" ]; then
        echo -e "${RED}✗ PHPUnit not found. Please run: composer install${NC}"
        exit 1
    fi
    
    cd "$PROJECT_ROOT"
    
    case $suite in
        unit)
            echo -e "${BLUE}Running Unit Tests...${NC}"
            "$phpunit" --testsuite=unit
            ;;
        integration)
            echo -e "${BLUE}Running Integration Tests...${NC}"
            "$phpunit" --testsuite=integration
            ;;
        wordpress)
            echo -e "${BLUE}Running WordPress Tests...${NC}"
            if check_wp_tests; then
                echo -e "${GREEN}✓ WordPress Test Suite found${NC}"
            else
                echo -e "${YELLOW}⚠️  WordPress Test Suite not found${NC}"
                echo "WordPress tests will run with mocked functions."
                echo ""
            fi
            "$phpunit" --testsuite=wordpress
            ;;
        all)
            echo -e "${BLUE}Running All Tests...${NC}"
            "$phpunit"
            ;;
        coverage)
            echo -e "${BLUE}Running Tests with Coverage...${NC}"
            if command -v php -m | grep -q xdebug; then
                "$phpunit" --coverage-html build/coverage --coverage-text
                echo ""
                echo -e "${GREEN}✓ Coverage report generated in: build/coverage/index.html${NC}"
            else
                echo -e "${YELLOW}⚠️  Xdebug not found. Installing PCOV...${NC}"
                echo "Please install Xdebug or PCOV for code coverage:"
                echo "  - Xdebug: pecl install xdebug"
                echo "  - PCOV: pecl install pcov"
                exit 1
            fi
            ;;
        *)
            echo -e "${RED}Unknown test suite: $suite${NC}"
            show_usage
            exit 1
            ;;
    esac
}

# Function to show test status
show_status() {
    echo -e "${BLUE}Test Environment Status:${NC}"
    echo ""
    
    # PHPUnit
    if [ -f "$PROJECT_ROOT/vendor/bin/phpunit" ]; then
        echo -e "PHPUnit:     ${GREEN}✓ Installed${NC}"
        phpunit_version=$("$PROJECT_ROOT/vendor/bin/phpunit" --version | head -n 1)
        echo "             $phpunit_version"
    else
        echo -e "PHPUnit:     ${RED}✗ Not installed${NC}"
        echo "             Run: composer install"
    fi
    
    echo ""
    
    # WordPress Test Suite
    if check_wp_tests; then
        echo -e "WP Tests:    ${GREEN}✓ Installed${NC}"
        tests_dir="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
        echo "             Location: $tests_dir"
    else
        echo -e "WP Tests:    ${YELLOW}⚠️  Not installed${NC}"
        echo "             Run: $0 install"
    fi
    
    echo ""
    
    # Mock Plugin
    mock_plugin="$PROJECT_ROOT/tests/fixtures/mock-plugin/mock-plugin.php"
    if [ -f "$mock_plugin" ]; then
        echo -e "Mock Plugin: ${GREEN}✓ Available${NC}"
        echo "             Location: tests/fixtures/mock-plugin/"
    else
        echo -e "Mock Plugin: ${RED}✗ Not found${NC}"
    fi
    
    echo ""
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [command]"
    echo ""
    echo "Commands:"
    echo "  status                 Show test environment status"
    echo "  install                Install WordPress Test Suite"
    echo "  unit                   Run unit tests only"
    echo "  integration            Run integration tests only"
    echo "  wordpress              Run WordPress tests only"
    echo "  all                    Run all tests (default)"
    echo "  coverage               Run tests with code coverage"
    echo "  help                   Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 status              # Check environment"
    echo "  $0 install             # Install WP Test Suite"
    echo "  $0 unit                # Run unit tests"
    echo "  $0                     # Run all tests"
    echo ""
}

# Main script logic
case ${1:-all} in
    status)
        show_status
        ;;
    install)
        install_wp_tests
        ;;
    unit|integration|wordpress|all|coverage)
        run_tests "$1"
        ;;
    help|-h|--help)
        show_usage
        ;;
    *)
        echo -e "${RED}Unknown command: $1${NC}"
        echo ""
        show_usage
        exit 1
        ;;
esac

exit 0
