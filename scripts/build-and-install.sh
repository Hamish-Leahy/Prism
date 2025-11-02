#!/bin/bash

# Prism Browser - Build and Install Script
# Compiles the app and installs it to Applications

set -e

echo "🚀 Building Prism Browser..."
cd "$(dirname "$0")/../frontend"

# Build the app
npx electron-builder --mac --dir

echo "📦 Installing to Applications..."

# Remove old versions if they exist
if [ -d "/Applications/Prism Browser.app" ]; then
    rm -rf "/Applications/Prism Browser.app"
    echo "   Removed old Prism Browser.app"
fi

if [ -d "/Applications/Prism.app" ]; then
    rm -rf "/Applications/Prism.app"
    echo "   Removed old Prism.app"
fi

# Copy new version and rename to Prism.app
cp -R "dist-electron/mac-arm64/Prism Browser.app" /Applications/Prism.app
echo "   ✅ Copied to Applications as Prism.app"

# Install custom icon
cp electron/icon.icns "/Applications/Prism.app/Contents/Resources/electron.icns"
echo "   ✅ Icon installed"

# Refresh Dock
touch "/Applications/Prism.app"
killall Dock 2>/dev/null || true

echo ""
echo "✨ Prism v$(node -p "require('./package.json').version") is now installed!"
echo "   Location: /Applications/Prism.app"
echo ""

