#!/bin/bash

# Prism Browser Build Script
# This script builds the production version of Prism Browser

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_status "Building Prism Browser for production..."

# Build frontend
print_status "Building frontend..."
cd frontend

if [ ! -f "package-lock.json" ]; then
    print_status "Installing dependencies..."
    npm install
fi

print_status "Building React app..."
npm run build

print_status "Building Electron app..."
npm run electron:build

cd ..

print_success "Build completed successfully!"

echo ""
echo "🎉 Prism Browser has been built for production!"
echo ""
echo "Build artifacts:"
echo "  - Frontend: frontend/dist/"
echo "  - Electron: frontend/dist-electron/"
echo ""
echo "To create a distributable package, run:"
echo "  cd frontend && npm run dist"
echo ""
