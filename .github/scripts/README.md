# GitHub Actions Scripts

This directory contains scripts for managing version numbers, changelogs, and compatibility checks for WordPress/WooCommerce plugins.

## Quick Start

### Setting up for a New Plugin

1. Copy all files from this directory to your plugin's `.github/scripts/` directory
2. Edit `config.sh` to customize for your plugin:
   ```bash
   PLUGIN_FILE="my-plugin.php"                    # Your main plugin file
   VERSION_CONSTANT_NAME="MY_PLUGIN_VERSION"       # Your version constant name
   CHECK_WORDPRESS_COMPAT="true"                   # Enable/disable WP compat checks
   CHECK_WOOCOMMERCE_COMPAT="true"                 # Enable/disable WC compat checks
   ```
3. Test that everything works:
   ```bash
   bash .github/scripts/check-version-and-compat.sh
   ```

## Files Overview

### Configuration Files

#### `config.sh`
Plugin-specific configuration. **This is the only file you need to customize** for each plugin.

Key settings:
- `PLUGIN_FILE` - Main plugin file name
- `VERSION_CONSTANT_NAME` - Name of your version constant
- `CHANGELOG_FILE` - Standalone changelog file (optional, set to empty if not used)
- `CHECK_WORDPRESS_COMPAT` - Enable/disable WordPress compatibility checks
- `CHECK_WOOCOMMERCE_COMPAT` - Enable/disable WooCommerce compatibility checks
- `MONITORED_EXTENSIONS` - File extensions that trigger changelog requirements
- `VALID_CHANGELOG_TYPES` - Allowed changelog entry types

#### `lib.sh`
Shared utility functions. **Do not modify** unless adding custom functions.

Provides functions for:
- Version extraction and validation
- Version updates
- Changelog type mapping
- Compatibility checks
- GitHub Actions output formatting

## Scripts

### Developer Tools (Run Locally)

#### `new-changelog.sh`
Interactive tool for creating changelog entries.

**Usage:**
```bash
bash .github/scripts/new-changelog.sh
```

Or with arguments to skip prompts:
```bash
bash .github/scripts/new-changelog.sh "Feature" "add-dark-mode" "no" "Add dark mode support"
```

Creates a dated file in `.changelogs/` with the format:
```
Type: Feature
Needs Documentation: no

Add dark mode support
```

#### `new-version.sh`
Generates version bumps and consolidates changelogs.

**Usage (preview mode):**
```bash
bash .github/scripts/new-version.sh
```

**Usage (update mode):**
```bash
bash .github/scripts/new-version.sh update
```

What it does:
- Reads all changelog files from `.changelogs/`
- Determines semantic version bump based on change types:
  - Breaking/Major → Major version bump (1.0.0 → 2.0.0)
  - Feature → Minor version bump (1.0.0 → 1.1.0)
  - Others → Patch version bump (1.0.0 → 1.0.1)
- In update mode:
  - Updates version in `readme.txt` (Stable tag)
  - Updates version in plugin file header
  - Updates version constant (if exists)
  - Appends changelog to `readme.txt`
  - Updates `changelog.txt` with new version (if file exists)
  - Archives changelog files to backup directory

### CI/CD Scripts (Automated in GitHub Actions)

#### `check-version-and-compat.sh`
Validates version consistency and compatibility metadata.

**Usage:**
```bash
bash .github/scripts/check-version-and-compat.sh
```

Checks:
- Version format (X.Y.Z semantic versioning)
- Version consistency across readme, plugin header, constant, and changelog.txt (if exists)
- Version is greater than previous git tag
- Version is not already tagged
- WordPress "Tested up to" matches latest version (if enabled)
- WooCommerce "WC tested up to" matches latest version (if enabled)

#### `check-changelog-develop.sh`
Ensures changelog entries exist for code changes (develop branch PRs).

**Usage:**
```bash
bash .github/scripts/check-changelog-develop.sh <base-sha> <head-sha>
```

Fails if code changes (PHP, JS, CSS, etc.) are present without a changelog entry.

#### `check-changelog-master.sh`
Ensures changelogs have been consolidated (master branch PRs).

**Usage:**
```bash
bash .github/scripts/check-changelog-master.sh
```

Fails if any changelog files exist in `.changelogs/` (except `.gitkeep`).

## Workflow Examples

### Development Workflow

1. Make code changes
2. Create changelog entry:
   ```bash
   bash .github/scripts/new-changelog.sh
   ```
3. Commit and push to develop branch
4. Create PR → `check-changelog-develop.sh` runs automatically

### Release Workflow

1. All changes merged to develop
2. Run version script:
   ```bash
   bash .github/scripts/new-version.sh
   ```
3. Review the suggested version and changelog
4. Apply updates:
   ```bash
   bash .github/scripts/new-version.sh update
   ```
5. Commit changes and create PR to master
6. PRs to master trigger `check-version-and-compat.sh` and `check-changelog-master.sh`

## Customization Guide

### Adding New File Types to Monitor

Edit `config.sh`:
```bash
MONITORED_EXTENSIONS=(php js css ts jsx tsx scss)  # Added scss
```

### Disabling Compatibility Checks

For non-WooCommerce plugins, edit `config.sh`:
```bash
CHECK_WOOCOMMERCE_COMPAT="false"
```

### Using/Disabling changelog.txt

Some plugins maintain a separate `changelog.txt` file. To enable:
```bash
CHANGELOG_FILE="changelog.txt"
```

To disable (if your plugin doesn't use this file):
```bash
CHANGELOG_FILE=""
```

The scripts will automatically:
- Check version consistency if the file exists
- Update it with new versions when running `new-version.sh update`
- Skip it entirely if not configured or file doesn't exist

### Adding Custom Changelog Types

Edit `config.sh`:
```bash
VALID_CHANGELOG_TYPES=(breaking major feature enhancement misc change tweak fix security)
ACCEPTED_CHANGELOG_TYPES_DISPLAY=(Breaking Major Feature Enhancement Misc Change Tweak Fix Security)
```

Update the bump mapping in `lib.sh`:
```bash
map_type_to_bump() {
  local t="$1"; t=$(echo "$t" | tr 'A-Z' 'a-z')
  case "$t" in
    breaking|major) echo 2 ;;
    feature|feat|minor) echo 1 ;;
    security) echo 1 ;;  # Added security as minor bump
    *) echo 0 ;;
  esac
}
```

### Using Auto-Detection

If you want the scripts to auto-detect your plugin file, edit `config.sh`:
```bash
PLUGIN_FILE=""  # Leave empty to enable auto-detection
```

The scripts will search for PHP files with "Plugin Name:" header.

For version constants, you can also use auto-detection:
```bash
VERSION_CONSTANT_NAME=""  # Auto-detect any version constant
```

## Troubleshooting

### "Could not parse Version from plugin file"
- Check that `PLUGIN_FILE` in `config.sh` matches your actual plugin file name
- Ensure your plugin file has a valid "Version:" header

### "Version mismatch" errors
- Ensure version numbers are consistent across:
  - `readme.txt` (Stable tag)
  - Plugin file header (Version)
  - Version constant (if used)

### Compatibility check failures
- Ensure WordPress/WooCommerce "tested up to" versions are current
- Or disable checks in `config.sh` if not needed

### Script not finding files
- Ensure you're running scripts from the repository root
- Check file paths in `config.sh` are correct

## Advanced Features

### Extending lib.sh

You can add custom functions to `lib.sh`. For example:

```bash
# Custom function to check for deprecated functions
check_deprecated_functions() {
  local plugin_file="$(get_plugin_file)"
  if grep -q "deprecated_function" "$plugin_file"; then
    warn "Plugin uses deprecated functions"
  fi
}
```

Then call it from your scripts.

### Integration with Other Tools

These scripts can be integrated with:
- GitHub Actions workflows (see `.github/workflows/`)
- Pre-commit hooks
- Release automation tools
- CI/CD pipelines

## Support

For issues or questions:
- Check the plan file at `/home/michael/.claude/plans/jolly-giggling-piglet.md`
- Review the implementation in this directory
- Test changes locally before committing
