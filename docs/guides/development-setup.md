# Development Setup Guide

This guide will help you set up a complete development environment for Prism Browser.

## Prerequisites

### System Requirements

- **macOS 10.15+** (Catalina or later)
- **8GB RAM** minimum (16GB recommended)
- **2GB free disk space**
- **Internet connection** for downloading dependencies

### Required Software

- **Homebrew** - Package manager
- **Git** - Version control
- **PHP 8.1+** - Backend runtime
- **Node.js 18+** - Frontend runtime
- **Composer** - PHP dependency manager
- **ChromeDriver** - For Chromium engine
- **GeckoDriver** - For Firefox engine

## Installation Steps

### 1. Install Homebrew

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

### 2. Install System Dependencies

```bash
# Install PHP
brew install php

# Install Node.js
brew install node

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install browser engines
brew install chromium firefox

# Install WebDriver binaries
brew install chromedriver geckodriver
```

### 3. Clone the Repository

```bash
git clone https://github.com/prism-browser/prism.git
cd prism
```

### 4. Run Setup Script

```bash
chmod +x scripts/setup.sh
./scripts/setup.sh
```

## Manual Setup

If the setup script doesn't work, follow these manual steps:

### Backend Setup

1. **Navigate to backend directory**:
   ```bash
   cd backend
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Create environment file**:
   ```bash
   cp env.example .env
   ```

4. **Edit configuration**:
   ```bash
   nano .env
   ```

   Update the following values:
   ```env
   APP_DEBUG=true
   DB_DATABASE=prism.db
   CHROMIUM_BINARY=/opt/homebrew/bin/chromium
   FIREFOX_BINARY=/opt/homebrew/bin/firefox
   ```

5. **Create necessary directories**:
   ```bash
   mkdir -p logs data
   chmod 755 logs data
   ```

### Frontend Setup

1. **Navigate to frontend directory**:
   ```bash
   cd frontend
   ```

2. **Install Node.js dependencies**:
   ```bash
   npm install
   ```

3. **Create environment file** (optional):
   ```bash
   cp .env.example .env.local
   ```

## Development Workflow

### Starting Development Servers

**Option 1: Use the start script**:
```bash
./scripts/start-dev.sh
```

**Option 2: Start manually**:

Terminal 1 (Backend):
```bash
cd backend
php -S localhost:8000 -t public/
```

Terminal 2 (Frontend):
```bash
cd frontend
npm run electron:dev
```

### Development URLs

- **Backend API**: http://localhost:8000
- **Frontend Dev Server**: http://localhost:5173
- **Electron App**: Opens automatically

### Hot Reload

- **Backend**: Restart PHP server for changes
- **Frontend**: Automatic hot reload in development
- **Electron**: Automatic restart on changes

## Project Structure

```
prism/
├── backend/                 # PHP backend
│   ├── src/                # Source code
│   ├── config/             # Configuration
│   ├── public/             # Web root
│   └── tests/              # Tests
├── frontend/               # Electron frontend
│   ├── src/                # React source
│   ├── electron/           # Electron main process
│   └── public/             # Static assets
├── engines/                # Engine implementations
│   ├── chromium/           # Chromium engine
│   ├── firefox/            # Firefox engine
│   └── prism/              # Custom Prism engine
├── shared/                 # Shared utilities
├── docs/                   # Documentation
└── scripts/                # Build scripts
```

## Code Organization

### Backend (PHP)

- **Controllers**: API endpoints
- **Services**: Business logic
- **Models**: Data structures
- **Middleware**: Request/response processing

### Frontend (React + Electron)

- **Components**: UI components
- **Hooks**: Custom React hooks
- **Services**: API communication
- **Types**: TypeScript definitions

### Engines

- **Interface**: Common engine interface
- **Implementations**: Engine-specific code
- **Configuration**: Engine settings

## Testing

### Backend Testing

```bash
cd backend
composer test
```

### Frontend Testing

```bash
cd frontend
npm test
```

### Integration Testing

```bash
# Start both servers
./scripts/start-dev.sh

# Run integration tests
npm run test:integration
```

## Debugging

### Backend Debugging

1. **Enable debug mode** in `.env`:
   ```env
   APP_DEBUG=true
   LOG_LEVEL=debug
   ```

2. **Check logs**:
   ```bash
   tail -f backend/logs/app.log
   ```

3. **Use Xdebug** (optional):
   ```bash
   brew install php-xdebug
   ```

### Frontend Debugging

1. **React DevTools**: Available in development
2. **Electron DevTools**: Press Cmd+Option+I
3. **Console Logs**: Check browser console

### Engine Debugging

1. **Check engine status**:
   ```bash
   curl http://localhost:8000/api/engines/current
   ```

2. **View engine logs**:
   ```bash
   tail -f backend/logs/engine.log
   ```

## Building for Production

### Build All Components

```bash
./scripts/build.sh
```

### Build Individual Components

**Backend**:
```bash
cd backend
composer install --no-dev --optimize-autoloader
```

**Frontend**:
```bash
cd frontend
npm run build
npm run electron:build
```

### Create Distribution

```bash
cd frontend
npm run dist
```

## Common Issues

### Port Conflicts

**Port 8000 in use**:
```bash
lsof -ti:8000 | xargs kill -9
```

**Port 5173 in use**:
```bash
lsof -ti:5173 | xargs kill -9
```

### Permission Issues

**Composer permissions**:
```bash
sudo chown -R $(whoami) ~/.composer
```

**Node modules permissions**:
```bash
sudo chown -R $(whoami) node_modules
```

### Engine Issues

**ChromeDriver not found**:
```bash
brew install chromedriver
```

**GeckoDriver not found**:
```bash
brew install geckodriver
```

## IDE Setup

### VS Code

Recommended extensions:
- PHP Intelephense
- TypeScript and JavaScript Language Features
- Tailwind CSS IntelliSense
- GitLens

### PhpStorm

- Enable PHP 8.1+ support
- Configure Composer autoloader
- Set up Node.js interpreter

## Performance Optimization

### Backend

- Use OPcache in production
- Enable gzip compression
- Use Redis for caching

### Frontend

- Enable production builds
- Use code splitting
- Optimize bundle size

### Engines

- Configure memory limits
- Use connection pooling
- Enable caching

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

See [Contributing Guidelines](contributing.md) for details.

## Getting Help

- Check the [FAQ](faq.md)
- Look at [Known Issues](known-issues.md)
- Join our [Community](community.md)
- Open an issue on GitHub

Happy coding! 🚀
