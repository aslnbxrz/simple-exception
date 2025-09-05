# Scripts

This directory contains automation scripts for the Simple Exception package.

## Available Scripts

### 1. `bump-version.sh`
Main version bumping script.

**Usage:**
```bash
./scripts/bump-version.sh [patch|minor|major]
```

**Examples:**
- `./scripts/bump-version.sh patch` - Bump patch version (1.0.1 → 1.0.2)
- `./scripts/bump-version.sh minor` - Bump minor version (1.0.1 → 1.1.0)
- `./scripts/bump-version.sh major` - Bump major version (1.0.1 → 2.0.0)

### 2. `quick-release.sh`
Quick release script that automatically commits changes and bumps patch version.

**Usage:**
```bash
./scripts/quick-release.sh
```

## Composer Scripts

You can also use composer scripts:

```bash
composer run bump-patch   # Bump patch version
composer run bump-minor   # Bump minor version
composer run bump-major   # Bump major version
composer run release      # Quick release (patch)
```

## Make Commands

```bash
make test        # Run tests
make bump-patch  # Bump patch version
make bump-minor  # Bump minor version
make bump-major  # Bump major version
make release     # Run tests and bump patch version
make help        # Show help
```

## Workflow

1. Make your changes
2. Run `./scripts/quick-release.sh` or `make release`
3. The script will:
   - Commit your changes
   - Run tests
   - Bump version
   - Create git tag
   - Push to GitHub
   - Update Packagist

## Requirements

- Git repository with remote origin
- Composer installed
- PHPUnit for testing
- GitHub access (SSH or HTTPS with token)
