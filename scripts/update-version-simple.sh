#!/bin/bash

# WP GitHub Updater - Version Update Script
# Updates version numbers across all project files consistently

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_NAME="WP GitHub Updater"
COMPOSER_FILE="composer.json"
README_FILE="README.md"
CHANGELOG_FILE="CHANGELOG.md"
PHP_SOURCE_DIR="src"

# Function to display usage
usage() {
    echo -e "${BLUE}Usage: $0 <version> [--no-confirm]${NC}"
    echo ""
    echo "Examples:"
    echo "  $0 1.1.1                 # Update to version 1.1.1 with confirmation"
    echo "  $0 1.2.0 --no-confirm   # Update to version 1.2.0 without confirmation (for CI)"
    echo ""
    echo "This script will update version numbers in:"
    echo "  - composer.json"
    echo "  - README.md (if version references exist)"
    echo "  - CHANGELOG.md (if unreleased section exists)"
    exit 1
}

# Function to validate version format
validate_version() {
    local version=$1
    if [[ ! $version =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        echo -e "${RED}Error: Version must be in format X.Y.Z (e.g., 1.2.3)${NC}"
        exit 1
    fi
}

# Function to get current version from composer.json
get_current_version() {
    if [[ -f "$COMPOSER_FILE" ]]; then
        grep '"version"' "$COMPOSER_FILE" | head -n1 | sed 's/.*"version": *"\([^"]*\)".*/\1/'
    else
        echo "unknown"
    fi
}

# Function to update composer.json
update_composer_version() {
    local new_version=$1
    
    if [[ -f "$COMPOSER_FILE" ]]; then
        echo -e "${YELLOW}Updating $COMPOSER_FILE...${NC}"
        
        # Create backup
        cp "$COMPOSER_FILE" "${COMPOSER_FILE}.backup"
        
        # Update version using sed
        sed -i.tmp "s/\"version\": *\"[^\"]*\"/\"version\": \"$new_version\"/" "$COMPOSER_FILE"
        rm "${COMPOSER_FILE}.tmp"
        
        echo -e "${GREEN}✅ Updated composer.json version to $new_version${NC}"
    else
        echo -e "${RED}❌ composer.json not found${NC}"
        exit 1
    fi
}

# Function to update CHANGELOG.md if it has unreleased section
update_changelog_if_unreleased() {
    local new_version=$1
    local current_date=$(date +"%Y-%m-%d")
    
    if [[ -f "$CHANGELOG_FILE" ]]; then
        # Check if there's an [Unreleased] section
        if grep -q "## \[Unreleased\]" "$CHANGELOG_FILE"; then
            echo -e "${YELLOW}Updating CHANGELOG.md [Unreleased] section...${NC}"
            
            # Create backup
            cp "$CHANGELOG_FILE" "${CHANGELOG_FILE}.backup"
            
            # Replace [Unreleased] with the new version and date
            sed -i.tmp "s/## \[Unreleased\]/## [$new_version] - $current_date/" "$CHANGELOG_FILE"
            rm "${CHANGELOG_FILE}.tmp"
            
            echo -e "${GREEN}✅ Updated CHANGELOG.md [Unreleased] to [$new_version] - $current_date${NC}"
        else
            echo -e "${BLUE}ℹ️  No [Unreleased] section found in CHANGELOG.md, skipping${NC}"
        fi
    else
        echo -e "${BLUE}ℹ️  CHANGELOG.md not found, skipping${NC}"
    fi
}

# Function to update PHP source files
update_php_files() {
    local new_version=$1
    
    if [[ -d "$PHP_SOURCE_DIR" ]]; then
        echo -e "${YELLOW}Updating PHP source files in $PHP_SOURCE_DIR/...${NC}"
        
        # Find all PHP files in the source directory
        local php_files
        php_files=$(find "$PHP_SOURCE_DIR" -name "*.php" -type f)
        
        if [[ -n "$php_files" ]]; then
            while IFS= read -r file; do
                if [[ -f "$file" ]]; then
                    # Create backup
                    cp "$file" "${file}.backup"
                    
                    # Update @version tags in PHP files
                    if grep -q "@version" "$file"; then
                        sed -i.tmp "s/@version [0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*/@version $new_version/" "$file"
                        rm "${file}.tmp"
                        echo -e "${GREEN}  ✅ Updated @version in $(basename "$file")${NC}"
                    fi
                    
                    # Update @since tags for the current version (if they exist)
                    if grep -q "@since $new_version" "$file"; then
                        echo -e "${BLUE}  ℹ️  @since $new_version already present in $(basename "$file")${NC}"
                    fi
                    
                    # Update version constants (if they exist)
                    if grep -q "VERSION.*=" "$file"; then
                        sed -i.tmp "s/VERSION.*=.*['\"][0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*['\"];/VERSION = \"$new_version\";/" "$file"
                        rm "${file}.tmp"
                        echo -e "${GREEN}  ✅ Updated VERSION constant in $(basename "$file")${NC}"
                    fi
                fi
            done <<< "$php_files"
        else
            echo -e "${BLUE}  ℹ️  No PHP files found in $PHP_SOURCE_DIR${NC}"
        fi
    else
        echo -e "${BLUE}ℹ️  Directory $PHP_SOURCE_DIR not found, skipping PHP files update${NC}"
    fi
}

# Function to show what will be updated
show_changes_preview() {
    local current_version=$1
    local new_version=$2
    
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$PROJECT_NAME - Version Update Preview${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo ""
    echo -e "Current version: ${YELLOW}$current_version${NC}"
    echo -e "New version:     ${GREEN}$new_version${NC}"
    echo ""
    echo "Files that will be updated:"
    
    if [[ -f "$COMPOSER_FILE" ]]; then
        echo -e "  ${GREEN}✓${NC} $COMPOSER_FILE"
    fi
    
    if [[ -f "$CHANGELOG_FILE" ]] && grep -q "## \[Unreleased\]" "$CHANGELOG_FILE"; then
        echo -e "  ${GREEN}✓${NC} $CHANGELOG_FILE (convert [Unreleased] to [$new_version])"
    else
        echo -e "  ${YELLOW}⚠${NC} $CHANGELOG_FILE (no [Unreleased] section found)"
    fi
    
    # Show PHP files that will be updated
    if [[ -d "$PHP_SOURCE_DIR" ]]; then
        local php_files
        php_files=$(find "$PHP_SOURCE_DIR" -name "*.php" -type f)
        if [[ -n "$php_files" ]]; then
            echo -e "  ${GREEN}✓${NC} PHP files in $PHP_SOURCE_DIR/:"
            while IFS= read -r file; do
                echo -e "    - $(basename "$file")"
            done <<< "$php_files"
        fi
    fi
    
    echo ""
}

# Function to confirm changes
confirm_changes() {
    local skip_confirm=$1
    
    if [[ "$skip_confirm" != "true" ]]; then
        echo -e "${YELLOW}Do you want to proceed with these changes? (y/N): ${NC}"
        read -r response
        if [[ ! "$response" =~ ^[Yy]$ ]]; then
            echo -e "${RED}Update cancelled.${NC}"
            exit 0
        fi
    fi
}

# Function to restore backups on error
cleanup_on_error() {
    echo -e "${RED}An error occurred. Restoring backups...${NC}"
    
    if [[ -f "${COMPOSER_FILE}.backup" ]]; then
        mv "${COMPOSER_FILE}.backup" "$COMPOSER_FILE"
        echo -e "${GREEN}Restored composer.json${NC}"
    fi
    
    if [[ -f "${CHANGELOG_FILE}.backup" ]]; then
        mv "${CHANGELOG_FILE}.backup" "$CHANGELOG_FILE"
        echo -e "${GREEN}Restored CHANGELOG.md${NC}"
    fi
    
    # Restore PHP file backups
    if [[ -d "$PHP_SOURCE_DIR" ]]; then
        local php_backups
        php_backups=$(find "$PHP_SOURCE_DIR" -name "*.php.backup" -type f 2>/dev/null || true)
        if [[ -n "$php_backups" ]]; then
            while IFS= read -r backup_file; do
                if [[ -f "$backup_file" ]]; then
                    local original_file="${backup_file%.backup}"
                    mv "$backup_file" "$original_file"
                    echo -e "${GREEN}Restored $(basename "$original_file")${NC}"
                fi
            done <<< "$php_backups"
        fi
    fi
    
    exit 1
}

# Function to clean up successful backups
cleanup_backups() {
    rm -f "${COMPOSER_FILE}.backup"
    rm -f "${CHANGELOG_FILE}.backup"
    
    # Clean up PHP file backups
    if [[ -d "$PHP_SOURCE_DIR" ]]; then
        find "$PHP_SOURCE_DIR" -name "*.php.backup" -type f -delete 2>/dev/null || true
    fi
}

# Main script
main() {
    local new_version=$1
    local no_confirm=$2
    
    # Check if version is provided
    if [[ -z "$new_version" ]]; then
        usage
    fi
    
    # Validate version format
    validate_version "$new_version"
    
    # Get current version
    local current_version
    current_version=$(get_current_version)
    
    # Set up error handler
    trap cleanup_on_error ERR
    
    # Show preview
    show_changes_preview "$current_version" "$new_version"
    
    # Confirm changes
    confirm_changes "$no_confirm"
    
    # Update files
    echo -e "${BLUE}Updating version to $new_version...${NC}"
    echo ""
    
    update_composer_version "$new_version"
    update_changelog_if_unreleased "$new_version"
    update_php_files "$new_version"
    
    # Clean up backups on success
    cleanup_backups
    
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}✅ Version update completed successfully!${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""
    echo -e "${BLUE}Next steps:${NC}"
    echo "1. Review the changes: git diff"
    echo "2. Commit the changes: git add . && git commit -m \"Bump version to $new_version\""
    echo "3. Create a tag: git tag -a v$new_version -m \"Release v$new_version\""
    echo "4. Push changes: git push origin main --tags"
    echo ""
}

# Parse arguments
if [[ "$2" == "--no-confirm" ]]; then
    main "$1" "true"
else
    main "$1" "false"
fi
