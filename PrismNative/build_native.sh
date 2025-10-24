#!/bin/bash

# Prism Browser - Native macOS App Builder
# No Xcode required - builds directly with swiftc

set -e

echo "🔮 Building Prism Browser (Native macOS)"
echo ""

cd "/Users/hamishleahy/Desktop/Software Projects/Prism/PrismNative"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

# Clean previous builds
rm -rf "Prism Browser.app"
rm -f PrismBrowser

echo -e "${BLUE}📦 Creating app bundle structure...${NC}"

# Create proper macOS app bundle
mkdir -p "Prism Browser.app/Contents/MacOS"
mkdir -p "Prism Browser.app/Contents/Resources"
mkdir -p "Prism Browser.app/Contents/Frameworks"

# Create Info.plist
cat > "Prism Browser.app/Contents/Info.plist" << 'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleExecutable</key>
    <string>PrismBrowser</string>
    <key>CFBundleIdentifier</key>
    <string>com.prism.browser</string>
    <key>CFBundleName</key>
    <string>Prism Browser</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>CFBundleShortVersionString</key>
    <string>1.0.0</string>
    <key>CFBundleVersion</key>
    <string>1</string>
    <key>LSMinimumSystemVersion</key>
    <string>13.0</string>
    <key>NSHighResolutionCapable</key>
    <true/>
    <key>NSSupportsAutomaticGraphicsSwitching</key>
    <true/>
    <key>NSHumanReadableCopyright</key>
    <string>© 2024 Prism Browser</string>
</dict>
</plist>
PLIST

echo -e "${GREEN}✅ App bundle created${NC}"
echo -e "${BLUE}🔨 Compiling Swift code...${NC}"

# Compile with all necessary frameworks
swiftc \
    -o "Prism Browser.app/Contents/MacOS/PrismBrowser" \
    -target arm64-apple-macos13.0 \
    -framework SwiftUI \
    -framework WebKit \
    -framework Foundation \
    -framework AppKit \
    -framework Cocoa \
    -sdk "$(xcrun --show-sdk-path)" \
    -Xlinker -rpath -Xlinker @executable_path/../Frameworks \
    -Xlinker -rpath -Xlinker @loader_path/../Frameworks \
    PrismBrowser/PrismBrowserApp.swift \
    PrismBrowser/Models/BrowserState.swift \
    PrismBrowser/Views/ContentView.swift \
    PrismBrowser/Engines/SafariWebView.swift \
    PrismBrowser/Engines/ChromiumWebView.swift \
    PrismBrowser/Engines/FirefoxWebView.swift \
    PrismBrowser/Engines/TorWebView.swift

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Build successful!${NC}"
    echo ""
    echo -e "${BLUE}🚀 Launching Prism Browser...${NC}"
    echo ""
    
    # Launch the app
    open "Prism Browser.app"
    
    echo -e "${GREEN}✅ Prism Browser is running!${NC}"
else
    echo -e "${RED}❌ Build failed${NC}"
    exit 1
fi

