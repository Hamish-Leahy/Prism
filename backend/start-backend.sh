#!/bin/bash

# Prism Backend Server Startup Script

cd "$(dirname "$0")"

echo "🚀 Starting Prism Backend Server..."
echo "   Port: 8000"
echo "   URL: http://localhost:8000"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

php -S localhost:8000 -t public

