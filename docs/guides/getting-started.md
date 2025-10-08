# Getting Started with Prism Browser

This guide will help you get up and running with Prism Browser quickly.

## Prerequisites

Before you begin, ensure you have the following installed on your macOS system:

- **Homebrew** - Package manager for macOS
- **PHP 8.1+** - Backend runtime
- **Node.js 18+** - Frontend runtime
- **Composer** - PHP dependency manager

## Quick Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/prism-browser/prism.git
   cd prism
   ```

2. **Run the setup script**:
   ```bash
   ./scripts/setup.sh
   ```

3. **Start the development environment**:
   ```bash
   ./scripts/start-dev.sh
   ```

That's it! The Prism Browser should now be running.

## Manual Installation

If you prefer to set up manually or the script doesn't work:

### Backend Setup

1. **Install PHP dependencies**:
   ```bash
   cd backend
   composer install
   ```

2. **Configure environment**:
   ```bash
   cp env.example .env
   # Edit .env with your configuration
   ```

3. **Start the backend server**:
   ```bash
   php -S localhost:8000 -t public/
   ```

### Frontend Setup

1. **Install Node.js dependencies**:
   ```bash
   cd frontend
   npm install
   ```

2. **Start the development server**:
   ```bash
   npm run electron:dev
   ```

## First Steps

### 1. Choose Your Engine

When you first open Prism, you'll see the engine selection screen. Choose from:

- **Chromium**: Full web compatibility, Chrome extensions
- **Firefox**: Privacy-focused, Firefox extensions  
- **Prism**: Lightweight, custom engine

### 2. Navigate to a Website

- Click in the address bar
- Type a URL or search term
- Press Enter to navigate

### 3. Manage Tabs

- **New Tab**: Cmd+T or click the + button
- **Close Tab**: Cmd+W or click the X on a tab
- **Switch Tabs**: Click on tab or use Cmd+1, Cmd+2, etc.

### 4. Access Settings

- Click the settings icon in the title bar
- Configure your preferences
- Switch between engines

## Basic Usage

### Navigation

- **Address Bar**: Type URLs or search terms
- **Back/Forward**: Use arrow buttons or Cmd+Left/Right
- **Refresh**: Click refresh button or Cmd+R
- **Home**: Click home button or Cmd+H

### Tab Management

- **Sidebar**: Shows all open tabs
- **Active Tab**: Highlighted in the sidebar
- **Tab Actions**: Right-click for context menu

### Bookmarks

- **Add Bookmark**: Click star icon in address bar
- **Bookmark Bar**: Shows below address bar
- **Manage**: Access through settings

## Engine Switching

You can switch between engines at any time:

1. Click the engine selector in the title bar
2. Choose your preferred engine
3. The change takes effect immediately

### When to Switch Engines

- **Chromium**: For maximum compatibility
- **Firefox**: For privacy and Firefox extensions
- **Prism**: For speed and minimal resource usage

## Troubleshooting

### Common Issues

**Backend won't start**:
- Check if port 8000 is available
- Ensure PHP 8.1+ is installed
- Run `composer install` in backend directory

**Frontend won't start**:
- Check if port 5173 is available
- Ensure Node.js 18+ is installed
- Run `npm install` in frontend directory

**Engine not working**:
- Check if the engine binary is installed
- Verify the path in backend configuration
- Check the logs for error messages

### Getting Help

- Check the [FAQ](faq.md)
- Look at [Known Issues](known-issues.md)
- Join our [Community](community.md)
- Open an issue on GitHub

## Next Steps

Now that you have Prism running, explore:

- [User Manual](user-manual.md) - Complete user guide
- [Developer Guide](developer-guide.md) - For contributors
- [API Documentation](api/) - For integrations
- [Examples](examples/) - Code samples

## Configuration

### Backend Configuration

Edit `backend/config/app.php` to configure:
- Database settings
- Engine preferences
- API settings
- Security options

### Frontend Configuration

Edit `frontend/src/hooks/useSettings.ts` to configure:
- Default settings
- Theme preferences
- Engine selection
- UI options

## Development

If you want to contribute or customize Prism:

1. Read the [Development Setup](development-setup.md) guide
2. Check the [Architecture Overview](architecture.md)
3. Follow the [Contributing Guidelines](contributing.md)

Welcome to Prism Browser! 🎉
