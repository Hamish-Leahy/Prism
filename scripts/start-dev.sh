#!/bin/bash

# Prism Browser Development Startup Script
# This script starts both the backend and frontend development servers

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

# Function to cleanup background processes
cleanup() {
    print_status "Shutting down development servers..."
    kill $BACKEND_PID $FRONTEND_PID 2>/dev/null || true
    exit 0
}

# Set up signal handlers
trap cleanup SIGINT SIGTERM

print_status "Starting Prism Browser development environment..."

# Check if backend is already running
if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null ; then
    print_error "Port 8000 is already in use. Please stop the existing backend server."
    exit 1
fi

# Check if frontend is already running
if lsof -Pi :5173 -sTCP:LISTEN -t >/dev/null ; then
    print_error "Port 5173 is already in use. Please stop the existing frontend server."
    exit 1
fi

# Start backend server
print_status "Starting PHP backend server on port 8000..."
cd backend
php -S localhost:8000 -t public/ &
BACKEND_PID=$!
cd ..

# Wait a moment for backend to start
sleep 2

# Check if backend started successfully
if ! kill -0 $BACKEND_PID 2>/dev/null; then
    print_error "Failed to start backend server"
    exit 1
fi

print_success "Backend server started (PID: $BACKEND_PID)"

# Start frontend development server
print_status "Starting Electron frontend on port 5173..."
cd frontend
npm run electron:dev &
FRONTEND_PID=$!
cd ..

# Wait a moment for frontend to start
sleep 3

# Check if frontend started successfully
if ! kill -0 $FRONTEND_PID 2>/dev/null; then
    print_error "Failed to start frontend server"
    kill $BACKEND_PID 2>/dev/null || true
    exit 1
fi

print_success "Frontend server started (PID: $FRONTEND_PID)"

echo ""
echo "🎉 Prism Browser development environment is running!"
echo ""
echo "Backend API:  http://localhost:8000"
echo "Frontend:     http://localhost:5173"
echo "Electron app should open automatically"
echo ""
echo "Press Ctrl+C to stop all servers"
echo ""

# Wait for user to stop
wait
