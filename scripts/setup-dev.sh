#!/bin/bash

# Prism Browser Development Setup Script

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

# Check if we're in the right directory
if [ ! -f "README.md" ] || [ ! -d "backend" ] || [ ! -d "frontend" ]; then
    print_error "Please run this script from the Prism Browser root directory"
    exit 1
fi

# Check for required tools
print_status "Checking for required tools..."

# Check PHP
if ! command -v php &> /dev/null; then
    print_error "PHP is not installed. Please install PHP 8.1 or higher."
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
print_success "PHP $PHP_VERSION found"

# Check Composer
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed. Please install Composer."
    exit 1
fi

print_success "Composer found"

# Check Node.js
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed. Please install Node.js 18 or higher."
    exit 1
fi

NODE_VERSION=$(node --version)
print_success "Node.js $NODE_VERSION found"

# Check npm
if ! command -v npm &> /dev/null; then
    print_error "npm is not installed. Please install npm."
    exit 1
fi

print_success "npm found"

# Setup Backend
print_status "Setting up backend..."

cd backend

# Install PHP dependencies
print_status "Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Create necessary directories
print_status "Creating necessary directories..."
mkdir -p logs
mkdir -p cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/cache
mkdir -p storage/framework/views
mkdir -p storage/app/public
mkdir -p storage/logs

# Set permissions
chmod -R 755 storage
chmod -R 755 cache
chmod -R 755 logs

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    print_status "Creating .env file..."
    cp env.example .env
    
    # Generate app key
    APP_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    sed -i.bak "s/your-app-key-here/$APP_KEY/" .env
    rm .env.bak
    
    print_success ".env file created with generated app key"
else
    print_warning ".env file already exists, skipping creation"
fi

# Create database
print_status "Creating database..."
if [ -f "prism_browser.sqlite" ]; then
    print_warning "Database already exists, skipping creation"
else
    touch prism_browser.sqlite
    chmod 664 prism_browser.sqlite
    print_success "SQLite database created"
fi

# Run database migrations
print_status "Running database migrations..."
php -r "
require_once 'vendor/autoload.php';
require_once 'src/Services/DatabaseService.php';

use Prism\Backend\Services\DatabaseService;
use Monolog\Logger;

\$logger = new Logger('setup');
\$db = new DatabaseService([
    'driver' => 'sqlite',
    'database' => 'prism_browser.sqlite'
], \$logger);

try {
    \$db->initialize();
    echo 'Database initialized successfully' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Database initialization failed: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

cd ..

# Setup Frontend
print_status "Setting up frontend..."

cd frontend

# Install Node.js dependencies
print_status "Installing Node.js dependencies..."
npm install

# Build frontend
print_status "Building frontend..."
npm run build

cd ..

# Create startup scripts
print_status "Creating startup scripts..."

# Backend startup script
cat > start-backend.sh << 'EOF'
#!/bin/bash
cd backend
php -S localhost:8000 -t public
EOF

chmod +x start-backend.sh

# Frontend startup script
cat > start-frontend.sh << 'EOF'
#!/bin/bash
cd frontend
npm run dev
EOF

chmod +x start-frontend.sh

# Development startup script
cat > start-dev.sh << 'EOF'
#!/bin/bash
# Start both backend and frontend in development mode

# Function to kill background processes on exit
cleanup() {
    echo "Stopping development servers..."
    kill $BACKEND_PID $FRONTEND_PID 2>/dev/null
    exit
}

# Set up signal handlers
trap cleanup SIGINT SIGTERM

echo "Starting Prism Browser development servers..."

# Start backend
cd backend
php -S localhost:8000 -t public &
BACKEND_PID=$!

# Start frontend
cd ../frontend
npm run dev &
FRONTEND_PID=$!

echo "Backend running at http://localhost:8000"
echo "Frontend running at http://localhost:5173"
echo "Press Ctrl+C to stop all servers"

# Wait for processes
wait
EOF

chmod +x start-dev.sh

# Create test script
cat > run-tests.sh << 'EOF'
#!/bin/bash
echo "Running Prism Browser tests..."

# Run backend tests
echo "Running backend tests..."
cd backend
composer test

# Run frontend tests
echo "Running frontend tests..."
cd ../frontend
npm test

echo "All tests completed!"
EOF

chmod +x run-tests.sh

print_success "Development environment setup complete!"

echo ""
echo "🎉 Setup Summary:"
echo "=================="
echo "✅ Backend dependencies installed"
echo "✅ Frontend dependencies installed"
echo "✅ Database initialized"
echo "✅ Configuration files created"
echo "✅ Startup scripts created"
echo ""
echo "🚀 Quick Start:"
echo "==============="
echo "1. Start development servers: ./start-dev.sh"
echo "2. Backend will be available at: http://localhost:8000"
echo "3. Frontend will be available at: http://localhost:5173"
echo "4. Run tests: ./run-tests.sh"
echo ""
echo "📁 Project Structure:"
echo "===================="
echo "backend/     - PHP backend API"
echo "frontend/    - React frontend"
echo "scripts/     - Development scripts"
echo "docs/        - Documentation"
echo ""
echo "🔧 Configuration:"
echo "================="
echo "Backend config: backend/.env"
echo "Frontend config: frontend/package.json"
echo ""
echo "Happy coding! 🎯"
