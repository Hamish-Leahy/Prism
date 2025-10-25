#!/bin/bash

# Prism Browser - Build and Install Script
# Compiles the app and installs it to Applications

set -e

echo "🚀 Building Prism Browser..."
cd "$(dirname "$0")/../frontend"

# Build the app
npx electron-builder --mac --dir

echo "📦 Installing to Applications..."

# Remove old version if exists
if [ -d "/Applications/Prism Browser.app" ]; then
    rm -rf "/Applications/Prism Browser.app"
    echo "   Removed old version"
fi

# Copy new version
cp -R "dist-electron/mac-arm64/Prism Browser.app" /Applications/
echo "   ✅ Copied to Applications"

# Install custom icon
cp electron/icon.icns "/Applications/Prism Browser.app/Contents/Resources/electron.icns"
echo "   ✅ Icon installed"

# Refresh Dock
touch "/Applications/Prism Browser.app"
killall Dock 2>/dev/null || true

echo ""
echo "✨ Prism Browser v$(node -p "require('./package.json').version") is now installed!"
echo "   Location: /Applications/Prism Browser.app"
echo ""

