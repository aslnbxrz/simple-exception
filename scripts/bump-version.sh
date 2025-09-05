#!/bin/bash

# Version bump script for Simple Exception package
# Usage: ./scripts/bump-version.sh [patch|minor|major]

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

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    print_error "composer.json not found. Please run this script from the package root directory."
    exit 1
fi

# Get current version
CURRENT_VERSION=$(grep '"version"' composer.json | sed 's/.*"version": *"\([^"]*\)".*/\1/')
print_status "Current version: $CURRENT_VERSION"

# Determine version bump type
BUMP_TYPE=${1:-patch}

# Validate bump type
if [[ ! "$BUMP_TYPE" =~ ^(patch|minor|major)$ ]]; then
    print_error "Invalid bump type. Use: patch, minor, or major"
    echo "Usage: $0 [patch|minor|major]"
    exit 1
fi

# Parse current version
IFS='.' read -ra VERSION_PARTS <<< "$CURRENT_VERSION"
MAJOR=${VERSION_PARTS[0]}
MINOR=${VERSION_PARTS[1]}
PATCH=${VERSION_PARTS[2]}

# Calculate new version
case $BUMP_TYPE in
    patch)
        PATCH=$((PATCH + 1))
        ;;
    minor)
        MINOR=$((MINOR + 1))
        PATCH=0
        ;;
    major)
        MAJOR=$((MAJOR + 1))
        MINOR=0
        PATCH=0
        ;;
esac

NEW_VERSION="$MAJOR.$MINOR.$PATCH"
print_status "New version: $NEW_VERSION"

# Update composer.json
print_status "Updating composer.json..."
sed -i.bak "s/\"version\": \"$CURRENT_VERSION\"/\"version\": \"$NEW_VERSION\"/" composer.json
rm composer.json.bak

# Run tests
print_status "Running tests..."
if composer test; then
    print_success "All tests passed!"
else
    print_error "Tests failed! Reverting version change..."
    git checkout composer.json
    exit 1
fi

# Git operations
print_status "Committing changes..."
git add .
git commit -m "v$NEW_VERSION: Version bump ($BUMP_TYPE)"

print_status "Creating tag v$NEW_VERSION..."
git tag -a "v$NEW_VERSION" -m "Release v$NEW_VERSION"

print_status "Pushing to GitHub..."
git push origin main --tags

print_success "Version $NEW_VERSION successfully released! 🎉"
print_status "Package is now available at: https://packagist.org/packages/aslnbxrz/simple-exception"

# Show usage
echo ""
print_status "To install the new version:"
echo "composer require aslnbxrz/simple-exception:^$NEW_VERSION"
