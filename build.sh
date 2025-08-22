#!/bin/bash

# Data Machine WordPress Plugin Build Script
# Creates production-ready zip file for WordPress installation

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_FILE="data-machine.php"
BUILD_DIR="build"
TEMP_DIR="$BUILD_DIR/temp"

echo -e "${GREEN}🔨 Starting Data Machine build process...${NC}"

# Check if we're in the right directory
if [[ ! -f "$PLUGIN_FILE" ]]; then
    echo -e "${RED}❌ Error: $PLUGIN_FILE not found. Make sure you're in the plugin root directory.${NC}"
    exit 1
fi

# Extract version from plugin file
VERSION=$(grep "Version:" $PLUGIN_FILE | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
if [[ -z "$VERSION" ]]; then
    echo -e "${RED}❌ Error: Could not extract version from $PLUGIN_FILE${NC}"
    exit 1
fi

echo -e "${YELLOW}📦 Building version: $VERSION${NC}"

# Check if composer is available
if ! command -v composer &> /dev/null; then
    echo -e "${RED}❌ Error: Composer is not installed or not in PATH${NC}"
    exit 1
fi

# Clean build directory
echo -e "${YELLOW}🧹 Cleaning build directory...${NC}"
rm -rf "$BUILD_DIR"
mkdir -p "$TEMP_DIR/data-machine"

# Install production dependencies
echo -e "${YELLOW}📥 Installing production dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

# Copy plugin files (excluding development files)
echo -e "${YELLOW}📋 Copying plugin files...${NC}"

# Copy main plugin files
cp "$PLUGIN_FILE" "$TEMP_DIR/data-machine/"
cp "uninstall.php" "$TEMP_DIR/data-machine/"

# Copy directories
echo -e "${YELLOW}   📂 Copying inc/ directory...${NC}"
cp -r inc/ "$TEMP_DIR/data-machine/"

echo -e "${YELLOW}   📂 Copying lib/ directory...${NC}"
cp -r lib/ "$TEMP_DIR/data-machine/"

echo -e "${YELLOW}   📂 Copying vendor/ directory...${NC}"
cp -r vendor/ "$TEMP_DIR/data-machine/"

# Validate essential files exist
echo -e "${YELLOW}✅ Validating build...${NC}"
REQUIRED_FILES=("$TEMP_DIR/data-machine/data-machine.php" "$TEMP_DIR/data-machine/vendor/autoload.php")
for file in "${REQUIRED_FILES[@]}"; do
    if [[ ! -f "$file" ]]; then
        echo -e "${RED}❌ Error: Required file missing: $file${NC}"
        exit 1
    fi
done

# Create zip file
ZIP_NAME="data-machine-v$VERSION.zip"
echo -e "${YELLOW}📦 Creating zip file: $ZIP_NAME${NC}"

cd "$TEMP_DIR"
zip -r "../$ZIP_NAME" "data-machine/" -q

cd ../../

# Clean up temp directory
rm -rf "$TEMP_DIR"

# Restore dev dependencies
echo -e "${YELLOW}🔄 Restoring development dependencies...${NC}"
composer install --no-interaction

# Final output
BUILD_PATH="$BUILD_DIR/$ZIP_NAME"
FILE_SIZE=$(du -h "$BUILD_PATH" | cut -f1)

echo -e "${GREEN}✅ Build complete!${NC}"
echo -e "${GREEN}📦 Output: $BUILD_PATH ($FILE_SIZE)${NC}"
echo -e "${GREEN}🚀 Ready for WordPress installation${NC}"

# Optional: Show zip contents
if command -v unzip &> /dev/null; then
    echo -e "\n${YELLOW}📋 Zip contents:${NC}"
    unzip -l "$BUILD_PATH" | head -20
    if [[ $(unzip -l "$BUILD_PATH" | wc -l) -gt 25 ]]; then
        echo "... (truncated)"
    fi
fi