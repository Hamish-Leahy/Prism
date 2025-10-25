# Prism Browser - Frontend

The Electron-based desktop application for Prism Browser with native multi-engine support.

## 🏗️ Architecture

The frontend consists of:

- **Electron Main Process** (`electron/main.js`) - Application lifecycle and window management
- **Renderer Process** (`electron/renderer.js`) - UI logic and IPC communication  
- **Engine Manager** (`electron/engines/`) - Native engine implementations
- **UI Components** (`electron/index.html`) - Clean Apple-inspired interface

## 🚀 Getting Started

### Prerequisites

```bash
# Required
node >= 18.0.0
npm >= 9.0.0

# Optional (for full functionality)
Firefox (latest version)
Tor (brew install tor)
```

### Installation

```bash
# Install dependencies
npm install

# Run in development mode
npm run electron

# Build for production
npm run build
```

## 🎨 Features

### Multi-Engine Support

- **Firefox Engine** - Real Firefox with Gecko rendering
- **Chromium Engine** - Native Electron BrowserView
- **Tor Engine** - Anonymous browsing with SOCKS5 proxy
- **Prism Engine** - Custom backend-powered rendering

### UI Features

- Drag-and-drop tab reordering
- Per-tab engine selection
- Engine-specific color coding
- Native macOS traffic lights
- Clean, minimalist design

## 📁 Project Structure

```
frontend/
├── electron/
│   ├── main.js              # Electron main process
│   ├── renderer.js          # Renderer process logic
│   ├── index.html           # Main UI
│   ├── icon.svg             # Application icon
│   └── engines/             # Native engine implementations
│       ├── EngineManager.js
│       ├── EngineInterface.js
│       ├── ChromiumEngine.js
│       ├── FirefoxEngine.js
│       ├── TorEngine.js
│       └── PrismEngine.js
├── package.json
└── README.md
```

## 🔧 Development

### Running Tests

```bash
npm test
```

### Debugging

```bash
# Run with DevTools open
npm run electron
# Then press Cmd/Ctrl + Shift + I
```

### Building

```bash
# Build for current platform
npm run dist

# Build for specific platform
npm run dist -- --mac
npm run dist -- --linux
npm run dist -- --win
```

## 📚 Engine Details

### Chromium Engine
- Uses Electron's native BrowserView
- Blink rendering engine
- Google search by default

### Firefox Engine  
- Spawns headless Firefox process
- Gecko rendering engine
- DuckDuckGo search by default
- Privacy enhancements injected

### Tor Engine
- SOCKS5 proxy configuration
- Per-tab circuit isolation
- DuckDuckGo onion service
- Maximum privacy protections

### Prism Engine
- Custom backend rendering
- PHP-powered search
- Fallback to Google when offline

## 🎯 IPC Communication

The frontend communicates with engines via IPC:

```javascript
// Create a new tab
await ipcRenderer.invoke('engine:createTab', tabId, engineName, options);

// Navigate
await ipcRenderer.invoke('engine:navigate', tabId, url);

// Switch engines
await ipcRenderer.invoke('engine:switchTabEngine', tabId, newEngine);
```

## 📜 License

Proprietary - See [LICENSE.md](../LICENSE.md) for details.

- ✅ Non-commercial use permitted
- ❌ Modification prohibited
- ❌ Commercial use prohibited
- ❌ Redistribution prohibited

## 🔗 Links

- [Main Repository](../)
- [Backend README](../backend/README.md)
- [Documentation](../docs/)
- [License](../LICENSE.md)
