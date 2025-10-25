# Prism Browser - Backend

PHP-based backend service providing custom search, wallet management, and engine support for Prism Browser.

## 🏗️ Architecture

The backend is built with:

- **Slim Framework** - Lightweight PHP routing
- **Composer** - Dependency management
- **RESTful API** - JSON-based endpoints
- **SQLite** - Local data storage (optional)

## 🚀 Getting Started

### Prerequisites

```bash
PHP >= 8.0
Composer >= 2.0
```

### Installation

```bash
# Install dependencies
composer install

# Copy environment configuration
cp env.example .env

# Start the development server
php -S localhost:8000 -t public
```

## 📡 API Endpoints

### Health Check
```http
GET /api/health
```

Returns backend status.

### Search
```http
GET /api/search?q=query
```

Custom Prism search engine.

**Response:**
```json
{
  "success": true,
  "results": {
    "direct_match": {...},
    "suggestions": [...],
    "indexed": [...],
    "web": [...]
  }
}
```

### Crypto Wallet

#### Create Wallet
```http
POST /api/wallet/create
Content-Type: application/json

{
  "name": "My Wallet",
  "password": "secure_password"
}
```

#### Import Wallet
```http
POST /api/wallet/import
Content-Type: application/json

{
  "private_key": "0x...",
  "name": "Imported Wallet",
  "password": "secure_password"
}
```

#### Get Wallet Balance
```http
GET /api/wallet?address=0x...
```

#### Send Transaction
```http
POST /api/wallet/send
Content-Type: application/json

{
  "from": "0x...",
  "to": "0x...",
  "amount": "0.1",
  "password": "secure_password"
}
```

### Engine Navigation (Prism Engine)
```http
POST /api/engine/navigate
Content-Type: application/json

{
  "url": "https://example.com",
  "engine": "prism"
}
```

Returns rendered HTML content.

## 📁 Project Structure

```
backend/
├── config/              # Configuration files
├── database/
│   └── migrations/      # Database migrations
├── public/
│   └── index.php        # Entry point
├── src/
│   ├── Application.php  # Main application
│   ├── Controllers/     # API controllers
│   │   ├── SearchController.php
│   │   ├── CryptoWalletController.php
│   │   └── EngineController.php
│   ├── Services/        # Business logic
│   │   ├── PrismSearchEngine.php
│   │   ├── CryptoWalletService.php
│   │   └── ... (30+ services)
│   └── Models/          # Data models
├── tests/               # PHPUnit tests
├── vendor/              # Composer dependencies
├── composer.json
├── phpunit.xml
└── README.md
```

## 🔧 Development

### Running Tests

```bash
# Run all tests
composer test

# Run specific test
./vendor/bin/phpunit tests/Services/AuthenticationServiceTest.php

# Run with coverage
composer test:coverage
```

### Code Quality

```bash
# Run linter
composer lint

# Fix code style
composer fix
```

### Database Migrations

```bash
# Run migrations
php scripts/migrate.php

# Create new migration
php scripts/create_migration.php create_new_table
```

## 🔐 Security

### Authentication

The backend uses JWT tokens for authentication:

```http
POST /api/auth/login
Content-Type: application/json

{
  "username": "user",
  "password": "password"
}
```

Returns a JWT token for subsequent requests.

### Middleware

- **JwtMiddleware** - Validates JWT tokens
- **CorsMiddleware** - Handles CORS headers
- **LoggingMiddleware** - Request/response logging

## 🗄️ Services

The backend includes 30+ services:

- **AI Assistant** - AI-powered assistance
- **Authentication** - User authentication
- **Budget Calculation** - Financial calculations
- **Cache** - Caching layer
- **Cloud Sync** - Cross-device synchronization
- **Cookie Jar** - Cookie management
- **CSS Parser** - CSS parsing and validation
- **CSS Renderer** - CSS rendering engine
- **Database** - Database abstraction
- **HTML5 Parser** - HTML5 parsing
- **HTTP Client** - HTTP request handling
- **JavaScript Engine** - JavaScript execution
- **Prism Search** - Custom search engine
- **Crypto Wallet** - Ethereum wallet management
- And many more...

## ⚙️ Configuration

Edit `.env` for configuration:

```env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=sqlite
DB_DATABASE=prism.db

# API Keys (optional)
OPENAI_API_KEY=
SUPABASE_URL=
SUPABASE_KEY=
```

## 📜 License

Proprietary - See [LICENSE.md](../LICENSE.md) for details.

- ✅ Non-commercial use permitted
- ❌ Modification prohibited
- ❌ Commercial use prohibited
- ❌ Redistribution prohibited

## 🔗 Links

- [Main Repository](../)
- [Frontend README](../frontend/README.md)
- [API Documentation](../docs/api/)
- [License](../LICENSE.md)
