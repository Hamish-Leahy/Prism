#!/bin/bash

# Prism Browser Setup Script
# This script sets up the development environment for Prism Browser

set -e

echo "🚀 Setting up Prism Browser development environment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running on macOS
if [[ "$OSTYPE" != "darwin"* ]]; then
    print_error "This setup script is designed for macOS. Please adapt it for your platform."
    exit 1
fi

# Check for Homebrew
if ! command -v brew &> /dev/null; then
    print_error "Homebrew is not installed. Please install it first: https://brew.sh"
    exit 1
fi

print_status "Installing system dependencies..."

# Install PHP
if ! command -v php &> /dev/null; then
    print_status "Installing PHP..."
    brew install php
else
    print_success "PHP is already installed"
fi

# Install Composer
if ! command -v composer &> /dev/null; then
    print_status "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
else
    print_success "Composer is already installed"
fi

# Install Node.js
if ! command -v node &> /dev/null; then
    print_status "Installing Node.js..."
    brew install node
else
    print_success "Node.js is already installed"
fi

# Install Chromium
if ! command -v chromium &> /dev/null; then
    print_status "Installing Chromium..."
    brew install chromium
else
    print_success "Chromium is already installed"
fi

# Install Firefox
if ! command -v firefox &> /dev/null; then
    print_status "Installing Firefox..."
    brew install firefox
else
    print_success "Firefox is already installed"
fi

# Install ChromeDriver
if ! command -v chromedriver &> /dev/null; then
    print_status "Installing ChromeDriver..."
    brew install chromedriver
else
    print_success "ChromeDriver is already installed"
fi

# Install GeckoDriver
if ! command -v geckodriver &> /dev/null; then
    print_status "Installing GeckoDriver..."
    brew install geckodriver
else
    print_success "GeckoDriver is already installed"
fi

print_status "Setting up backend..."

# Install PHP dependencies
cd backend
if [ ! -f "composer.lock" ]; then
    print_status "Installing PHP dependencies..."
    composer install
else
    print_success "PHP dependencies are already installed"
fi

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    print_status "Creating .env file..."
    cp env.example .env
    print_warning "Please review and update the .env file with your configuration"
else
    print_success ".env file already exists"
fi

cd ..

print_status "Setting up frontend..."

# Install Node.js dependencies
cd frontend
if [ ! -f "package-lock.json" ]; then
    print_status "Installing Node.js dependencies..."
    npm install
else
    print_success "Node.js dependencies are already installed"
fi

cd ..

print_status "Setting up shared utilities..."

# Create logs directory
mkdir -p backend/logs
chmod 755 backend/logs

# Create database directory
mkdir -p backend/data
chmod 755 backend/data

print_success "Setup completed successfully!"

echo ""
echo "🎉 Prism Browser is ready for development!"
echo ""
echo "To start the development servers:"
echo "  1. Backend:  cd backend && php -S localhost:8000"
echo "  2. Frontend: cd frontend && npm run electron:dev"
echo ""
echo "For more information, see the README files in each directory."
echo ""
