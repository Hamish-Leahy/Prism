# Prism Frontend

Electron-based desktop application for macOS, providing a modern browser interface with Arc-inspired design.

## Features

- **Modern UI**: Clean, minimalist interface inspired by Arc
- **Multi-Engine Support**: Seamless switching between rendering engines
- **Tab Management**: Advanced tab organization and grouping
- **Bookmark System**: Intuitive bookmark management
- **Settings Panel**: Comprehensive configuration options
- **Dark/Light Themes**: Multiple theme options

## Technology Stack

- **Electron**: Cross-platform desktop app framework
- **React**: UI component library
- **TypeScript**: Type-safe JavaScript
- **Tailwind CSS**: Utility-first CSS framework
- **Vite**: Fast build tool and dev server

## Project Structure

```
frontend/
├── src/               # Source code
│   ├── components/    # React components
│   ├── pages/         # Page components
│   ├── hooks/         # Custom React hooks
│   ├── services/      # API services
│   ├── utils/         # Utility functions
│   └── types/         # TypeScript type definitions
├── public/            # Static assets
├── dist/              # Build output
└── electron/          # Electron main process
```

## Setup

1. **Install Node.js 18+**:
   ```bash
   brew install node
   ```

2. **Install Dependencies**:
   ```bash
   npm install
   ```

3. **Start Development**:
   ```bash
   npm run dev
   ```

4. **Build for Production**:
   ```bash
   npm run build
   ```

## Development Scripts

- `npm run dev` - Start development server
- `npm run build` - Build for production
- `npm run electron:dev` - Start Electron in development
- `npm run electron:build` - Build Electron app
- `npm run test` - Run tests
- `npm run lint` - Run linter

## UI Components

### Core Components
- `BrowserWindow` - Main browser window
- `TabBar` - Tab management interface
- `AddressBar` - URL input and navigation
- `BookmarkBar` - Bookmark shortcuts
- `SettingsPanel` - Configuration interface

### Engine Components
- `EngineSelector` - Engine switching dropdown
- `EngineStatus` - Current engine indicator
- `EngineSettings` - Engine-specific settings

## API Integration

The frontend communicates with the PHP backend through:
- REST API calls for data
- WebSocket connections for real-time updates
- File system access for local storage

## Theming

The app supports multiple themes:
- **Light**: Clean, bright interface
- **Dark**: Easy on the eyes
- **Arc**: Arc-inspired design
- **Custom**: User-defined themes

## Building for Distribution

1. **Build the app**:
   ```bash
   npm run build
   npm run electron:build
   ```

2. **Create installer**:
   ```bash
   npm run dist
   ```

## Contributing

1. Follow React and TypeScript best practices
2. Use Tailwind CSS for styling
3. Write tests for components
4. Update documentation
5. Submit pull requests

## Troubleshooting

### Common Issues

1. **Electron won't start**: Check Node.js version
2. **Build fails**: Clear node_modules and reinstall
3. **API connection**: Ensure backend is running

### Debug Mode

```bash
npm run dev:debug
```

This enables:
- React DevTools
- Electron DevTools
- Console logging
- Hot reload
