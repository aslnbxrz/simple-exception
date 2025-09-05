#!/bin/bash

# Quick release script - just bump patch version
# Usage: ./scripts/quick-release.sh

echo "🚀 Quick Release Script"
echo "======================"

# Check if there are uncommitted changes
if [ -n "$(git status --porcelain)" ]; then
    echo "📝 Uncommitted changes detected. Committing them first..."
    git add .
    git commit -m "WIP: Preparing for release"
fi

# Run the bump script
./scripts/bump-version.sh patch

echo ""
echo "✅ Quick release completed!"
echo "📦 Package is now available on Packagist"
