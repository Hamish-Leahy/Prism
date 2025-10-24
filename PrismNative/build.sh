#!/bin/bash

# Prism Browser - Native macOS Builder
# Compiles Swift directly without Xcode

set -e

echo "🔮 Prism Browser - Native macOS Build"
echo ""

cd "/Users/hamishleahy/Desktop/Software Projects/Prism/PrismNative"

# Clean
rm -rf "Prism Browser.app"
rm -rf build

echo "📦 Creating app bundle..."

# Create app structure
mkdir -p "Prism Browser.app/Contents/MacOS"
mkdir -p "Prism Browser.app/Contents/Resources"

# Create Info.plist
cat > "Prism Browser.app/Contents/Info.plist" << 'EOF'
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
EOF

echo "✅ App bundle structure created"
echo "🔨 Compiling Swift..."

# Compile all Swift files
swiftc \
    -parse-as-library \
    -o "Prism Browser.app/Contents/MacOS/PrismBrowser" \
    -target arm64-apple-macos13.0 \
    -framework SwiftUI \
    -framework WebKit \
    -framework Foundation \
    -framework AppKit \
    -framework Cocoa \
    -sdk "$(xcrun --show-sdk-path)" \
    -Xlinker -rpath \
    -Xlinker @executable_path/../Frameworks \
    -Xlinker -rpath \
    -Xlinker @loader_path/../Frameworks \
    Sources/main.swift \
    Sources/Models/BrowserState.swift \
    Sources/Views/ContentView.swift \
    Sources/Engines/SafariWebView.swift \
    Sources/Engines/ChromiumWebView.swift \
    Sources/Engines/FirefoxWebView.swift \
    Sources/Engines/TorWebView.swift \
    Sources/Engines/PrismWebView.swift

if [ $? -eq 0 ]; then
    echo "✅ Build successful!"
    echo ""
    echo "🚀 Launching Prism Browser..."
    open "Prism Browser.app"
    echo ""
    echo "✅ Prism Browser is running!"
    echo ""
    echo "📍 Location: $(pwd)/Prism Browser.app"
else
    echo "❌ Build failed"
    exit 1
fi

