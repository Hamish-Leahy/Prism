# Prism Browser

A modern, open-source browser successor to Arc, built with PHP backend and multiple rendering engine support.

## Features

- **Multi-Engine Support**: Choose between Chromium, Firefox-based, or custom Prism engine
- **Modern UI**: Clean, Arc-inspired interface
- **Cross-Platform**: Desktop app for macOS (with plans for Windows/Linux)
- **Extensible**: Plugin system for custom functionality
- **Privacy-Focused**: Built with user privacy in mind

## Architecture

```
Prism/
├── backend/           # PHP backend services
├── engines/           # Rendering engine implementations
│   ├── chromium/      # Chromium-based engine
│   ├── firefox/       # Firefox-based engine
│   └── prism/         # Custom Prism engine
├── frontend/          # Electron desktop app
├── shared/            # Shared utilities and types
└── docs/             # Documentation
```

## Quick Start

1. **Backend Setup**:
   ```bash
   cd backend
   composer install
   php -S localhost:8000
   ```

2. **Frontend Setup**:
   ```bash
   cd frontend
   npm install
   npm start
   ```

3. **Engine Configuration**:
   - Edit `backend/config/engines.php` to configure your preferred engine
   - Each engine has its own configuration file

## Engine Options

### Chromium Engine
- Based on Chromium/Blink
- Full web compatibility
- Chrome extension support

### Firefox Engine
- Based on Gecko
- Privacy-focused
- Firefox extension support

### Prism Engine
- Custom lightweight engine
- Optimized for performance
- Minimal resource usage

## Development

See individual README files in each directory for detailed setup instructions.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

MIT License - see LICENSE file for details
