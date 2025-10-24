#!/bin/bash

# Prism Browser - Tor Setup Script
# This script installs and configures Tor for the Prism browser

set -e

echo "🔒 Setting up Tor for Prism Browser..."

# Check if Homebrew is installed
if ! command -v brew &> /dev/null; then
    echo "❌ Homebrew not found. Please install Homebrew first:"
    echo "   /bin/bash -c \"\$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)\""
    exit 1
fi

# Install Tor
echo "📦 Installing Tor..."
if brew list tor &> /dev/null; then
    echo "✓ Tor is already installed"
else
    brew install tor
fi

# Create Tor configuration directory
TOR_CONFIG_DIR="$HOME/.prism/tor"
mkdir -p "$TOR_CONFIG_DIR"

# Create Tor configuration file
cat > "$TOR_CONFIG_DIR/torrc" << EOF
# Prism Browser Tor Configuration

# SOCKS proxy on port 9050
SOCKSPort 9050

# Control port for advanced features (optional)
ControlPort 9051

# Data directory
DataDirectory $TOR_CONFIG_DIR/data

# Logs
Log notice file $TOR_CONFIG_DIR/tor.log

# DNS
DNSPort 5353

# Enhanced privacy settings
ExitRelay 0
ClientOnly 1
SafeSocks 1
TestSocks 1
WarnUnsafeSocks 1

# Circuit preferences
CircuitBuildTimeout 60
LearnCircuitBuildTimeout 0
MaxCircuitDirtiness 600

# Performance tuning
NumEntryGuards 8
NumDirectoryGuards 3

# Cookie authentication for control port
CookieAuthentication 1
CookieAuthFile $TOR_CONFIG_DIR/control_auth_cookie

# Prevent DNS leaks
VirtualAddrNetworkIPv4 10.192.0.0/10
AutomapHostsOnResolve 1
TransPort 9040
EOF

echo "✓ Tor configuration created at $TOR_CONFIG_DIR/torrc"

# Create Tor data directory
mkdir -p "$TOR_CONFIG_DIR/data"

# Start Tor service
echo "🚀 Starting Tor service..."
if pgrep -x "tor" > /dev/null; then
    echo "⚠️  Tor is already running. Restarting..."
    pkill -x "tor"
    sleep 2
fi

# Start Tor with custom config
tor -f "$TOR_CONFIG_DIR/torrc" &
TOR_PID=$!

echo "✓ Tor started (PID: $TOR_PID)"
echo ""
echo "⏳ Waiting for Tor to establish circuit..."
sleep 5

# Test Tor connection
echo "🔍 Testing Tor connection..."
if curl -s --socks5-hostname localhost:9050 https://check.torproject.org/ | grep -q "Congratulations"; then
    echo "✅ Tor is working correctly!"
else
    echo "⚠️  Tor connection test inconclusive. The browser will work but may not route through Tor."
fi

echo ""
echo "======================================"
echo "✅ Tor Setup Complete!"
echo "======================================"
echo ""
echo "Tor is now running on:"
echo "  SOCKS5 Proxy: localhost:9050"
echo "  Control Port: localhost:9051"
echo ""
echo "To manage Tor:"
echo "  Start:   tor -f $TOR_CONFIG_DIR/torrc &"
echo "  Stop:    pkill -x tor"
echo "  Logs:    tail -f $TOR_CONFIG_DIR/tor.log"
echo ""
echo "To start Tor automatically on login:"
echo "  brew services start tor"
echo ""
echo "🎉 You can now use Tor engine in Prism Browser!"

