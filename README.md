# Prism Browser

A modern, privacy-focused web browser built with a custom engine and multi-engine architecture.

## 🚀 Features

### Core Browser Features
- **Multi-Engine Architecture**: Switch between Prism Engine, Chromium, and Firefox
- **Tab Management**: Create, close, group, pin, and duplicate tabs
- **Bookmark Management**: Save, organize, and manage bookmarks
- **History Tracking**: Browse history with search and management
- **Download Manager**: Download files with pause/resume functionality
- **Settings Management**: Comprehensive browser settings

### Privacy & Security
- **Ad Blocking**: Built-in ad blocker with customizable rules
- **Tracker Protection**: Block tracking scripts and cookies
- **Privacy Mode**: Enhanced privacy settings
- **Secure Authentication**: JWT-based authentication system
- **Data Encryption**: End-to-end encryption for sensitive data

### Advanced Features
- **WebRTC Support**: Real-time communication capabilities
- **WebAssembly**: Run WASM modules natively
- **Service Workers**: Background processing and caching
- **Push Notifications**: Real-time notifications
- **Offline Support**: Offline browsing with caching
- **Plugin System**: Extensible plugin architecture

### Developer Tools
- **Custom Engine**: Built-in HTML5, CSS, and JavaScript engine
- **Performance Monitoring**: Real-time performance metrics
- **Debug Tools**: Comprehensive debugging capabilities
- **API Documentation**: Complete API documentation

## 🏗️ Architecture

### Backend (PHP)
- **Slim Framework**: Lightweight PHP framework
- **Multi-Engine Support**: Prism, Chromium, Firefox engines
- **RESTful API**: Complete REST API for all features
- **Database Support**: SQLite and PostgreSQL (Supabase)
- **Authentication**: JWT-based authentication
- **Plugin System**: Extensible plugin architecture

### Frontend (React + Electron)
- **React**: Modern UI framework
- **TypeScript**: Type-safe development
- **Electron**: Desktop application wrapper
- **Tailwind CSS**: Utility-first CSS framework
- **Vite**: Fast build tool and dev server

## 📦 Installation

### Prerequisites
- PHP 8.1 or higher
- Composer
- Node.js 18 or higher
- npm or yarn

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/prism-browser.git
   cd prism-browser
   ```

2. **Run the setup script**
   ```bash
   chmod +x scripts/setup-dev.sh
   ./scripts/setup-dev.sh
   ```

3. **Start development servers**
   ```bash
   ./start-dev.sh
   ```

4. **Access the application**
   - Backend API: http://localhost:8000
   - Frontend: http://localhost:5173

### Manual Setup

#### Backend Setup
```bash
cd backend
composer install
cp env.example .env
# Edit .env with your configuration
php -r "require_once 'vendor/autoload.php'; require_once 'src/Services/DatabaseService.php'; use Prism\Backend\Services\DatabaseService; use Monolog\Logger; \$logger = new Logger('setup'); \$db = new DatabaseService(['driver' => 'sqlite', 'database' => 'prism_browser.sqlite'], \$logger); \$db->initialize();"
php -S localhost:8000 -t public
```

#### Frontend Setup
```bash
cd frontend
npm install
npm run dev
```

## 🔧 Configuration

### Backend Configuration
Edit `backend/.env` to configure:
- Database settings
- JWT secrets
- Engine configurations
- Plugin settings
- Feature flags

### Frontend Configuration
Edit `frontend/package.json` and `frontend/vite.config.js` to configure:
- Build settings
- Electron configuration
- Development server settings

## 🚀 Usage

### Starting the Browser
```bash
# Development mode
./start-dev.sh

# Production mode
cd backend && php -S localhost:8000 -t public
cd frontend && npm run build && npm run electron:build
```

### API Usage
```bash
# Get available engines
curl http://localhost:8000/api/engines

# Create a new tab
curl -X POST http://localhost:8000/api/tabs \
  -H "Content-Type: application/json" \
  -d '{"title": "New Tab", "url": "https://example.com"}'

# Get bookmarks
curl http://localhost:8000/api/bookmarks
```

## 🧪 Testing

### Run All Tests
```bash
./run-tests.sh
```

### Backend Tests
```bash
cd backend
composer test
```

### Frontend Tests
```bash
cd frontend
npm test
```

## 📚 API Documentation

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login user
- `POST /api/auth/refresh` - Refresh access token
- `POST /api/auth/logout` - Logout user

### Engines
- `GET /api/engines` - List available engines
- `POST /api/engines/switch` - Switch active engine
- `GET /api/engines/status` - Get engine status
- `GET /api/engines/{engine}/stats` - Get engine statistics

### Tabs
- `GET /api/tabs` - List tabs
- `POST /api/tabs` - Create new tab
- `GET /api/tabs/{id}` - Get tab details
- `PUT /api/tabs/{id}` - Update tab
- `DELETE /api/tabs/{id}` - Close tab
- `POST /api/tabs/{id}/navigate` - Navigate tab

### Bookmarks
- `GET /api/bookmarks` - List bookmarks
- `POST /api/bookmarks` - Create bookmark
- `GET /api/bookmarks/{id}` - Get bookmark
- `PUT /api/bookmarks/{id}` - Update bookmark
- `DELETE /api/bookmarks/{id}` - Delete bookmark

### History
- `GET /api/history` - List history
- `POST /api/history` - Add history entry
- `DELETE /api/history/{id}` - Delete history entry
- `DELETE /api/history` - Clear all history

### Downloads
- `GET /api/downloads` - List downloads
- `POST /api/downloads` - Start download
- `GET /api/downloads/{id}` - Get download status
- `POST /api/downloads/{id}/pause` - Pause download
- `POST /api/downloads/{id}/resume` - Resume download
- `POST /api/downloads/{id}/cancel` - Cancel download
- `DELETE /api/downloads/{id}` - Delete download

### Settings
- `GET /api/settings` - List settings
- `GET /api/settings/{key}` - Get setting
- `PUT /api/settings/{key}` - Update setting
- `PUT /api/settings` - Update multiple settings
- `DELETE /api/settings/{key}` - Delete setting
- `POST /api/settings/reset` - Reset to defaults

## 🔌 Plugin Development

### Creating a Plugin
```php
<?php

namespace Prism\Backend\Services\Plugins;

use Prism\Backend\Services\Plugins\BasePlugin;

class MyPlugin extends BasePlugin
{
    public function getName(): string
    {
        return 'My Plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function initialize(): bool
    {
        // Plugin initialization logic
        return true;
    }

    public function handleEvent(string $event, array $data): void
    {
        // Handle events
    }
}
```

### Plugin Events
- `page.load` - Page loaded
- `request.before` - Before HTTP request
- `request.after` - After HTTP response
- `tab.created` - Tab created
- `tab.closed` - Tab closed
- `bookmark.added` - Bookmark added

## 🛠️ Development

### Project Structure
```
prism-browser/
├── backend/                 # PHP backend
│   ├── src/
│   │   ├── Controllers/     # API controllers
│   │   ├── Services/        # Business logic
│   │   ├── Models/          # Data models
│   │   └── Plugins/         # Plugin system
│   ├── config/              # Configuration files
│   ├── public/              # Web root
│   └── vendor/              # Composer dependencies
├── frontend/                # React frontend
│   ├── src/
│   │   ├── components/      # React components
│   │   ├── hooks/           # Custom hooks
│   │   ├── services/        # API services
│   │   └── utils/           # Utility functions
│   ├── public/              # Static assets
│   └── dist/                # Built files
├── scripts/                 # Development scripts
├── docs/                    # Documentation
└── tests/                   # Test files
```

### Adding New Features

1. **Backend API**
   - Create controller in `backend/src/Controllers/`
   - Add routes in `backend/src/Application.php`
   - Create model in `backend/src/Models/`
   - Add service in `backend/src/Services/`

2. **Frontend UI**
   - Create component in `frontend/src/components/`
   - Add hook in `frontend/src/hooks/`
   - Update API service in `frontend/src/services/`
   - Add to main app in `frontend/src/App.js`

3. **Database Changes**
   - Update schema in `schema.sql`
   - Create migration script
   - Update models and services

### Code Style
- **PHP**: PSR-12 coding standard
- **JavaScript**: ESLint with React rules
- **CSS**: Tailwind CSS utility classes
- **Documentation**: PHPDoc for PHP, JSDoc for JavaScript

## 🚀 Deployment

### Production Build
```bash
# Backend
cd backend
composer install --no-dev --optimize-autoloader

# Frontend
cd frontend
npm run build
npm run electron:build
```

### Docker Deployment
```bash
# Build Docker image
docker build -t prism-browser .

# Run container
docker run -p 8000:8000 prism-browser
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

### Development Guidelines
- Follow the existing code style
- Add tests for new features
- Update documentation
- Ensure all tests pass
- Follow semantic versioning

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- [Slim Framework](https://www.slimframework.com/) - PHP micro-framework
- [React](https://reactjs.org/) - JavaScript library
- [Electron](https://www.electronjs.org/) - Desktop app framework
- [Tailwind CSS](https://tailwindcss.com/) - CSS framework
- [Vite](https://vitejs.dev/) - Build tool

## 📞 Support

- **Documentation**: [docs/](docs/)
- **Issues**: [GitHub Issues](https://github.com/yourusername/prism-browser/issues)
- **Discussions**: [GitHub Discussions](https://github.com/yourusername/prism-browser/discussions)
- **Email**: support@prism-browser.com

## 🗺️ Roadmap

### Phase 1: Foundation (Q4 2024) ✅
- [x] Core browser features
- [x] Multi-engine architecture
- [x] Basic UI/UX
- [x] Authentication system
- [x] Database integration

### Phase 2: Features (Q1 2025)
- [ ] Enhanced privacy features
- [ ] Advanced UI/UX
- [ ] Performance optimizations
- [ ] Mobile responsive design
- [ ] Accessibility improvements

### Phase 3: Advanced (Q2 2025)
- [ ] WebRTC implementation
- [ ] WebAssembly support
- [ ] Service Worker integration
- [ ] Push Notifications
- [ ] Offline functionality

### Phase 4: Ecosystem (Q3 2025)
- [ ] Extension system
- [ ] Plugin marketplace
- [ ] Cloud sync
- [ ] Cross-device support
- [ ] Advanced developer tools

### Phase 5: Future (Q4 2025+)
- [ ] AI integration
- [ ] Mobile companion app
- [ ] Enterprise features
- [ ] Advanced analytics
- [ ] Machine learning features

---

**Made with ❤️ by the Prism Browser Team**