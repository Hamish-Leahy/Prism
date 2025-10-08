# Prism Backend

PHP-based backend service for the Prism browser, handling engine management, API endpoints, and core browser functionality.

## Structure

```
backend/
├── src/               # PHP source code
│   ├── Controllers/   # API controllers
│   ├── Services/      # Business logic services
│   ├── Models/        # Data models
│   └── Middleware/    # Request/response middleware
├── config/            # Configuration files
├── public/            # Web-accessible files
├── tests/             # Unit and integration tests
├── vendor/            # Composer dependencies
└── composer.json      # PHP dependencies
```

## Features

- **Engine Management**: Switch between Chromium, Firefox, and Prism engines
- **REST API**: Clean API for frontend communication
- **Session Management**: Handle browser sessions and tabs
- **Bookmark Management**: Store and manage bookmarks
- **History Tracking**: Track browsing history
- **Settings Management**: User preferences and configuration

## Setup

1. **Install PHP 8.1+**:
   ```bash
   brew install php
   ```

2. **Install Composer**:
   ```bash
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   ```

3. **Install Dependencies**:
   ```bash
   composer install
   ```

4. **Start Development Server**:
   ```bash
   php -S localhost:8000 -t public/
   ```

## API Endpoints

- `GET /api/engines` - List available engines
- `POST /api/engines/switch` - Switch active engine
- `GET /api/tabs` - List open tabs
- `POST /api/tabs` - Create new tab
- `DELETE /api/tabs/{id}` - Close tab
- `GET /api/bookmarks` - List bookmarks
- `POST /api/bookmarks` - Add bookmark
- `GET /api/history` - Get browsing history

## Configuration

Edit `config/app.php` to configure:
- Database settings
- Engine preferences
- API settings
- Security options

## Testing

```bash
composer test
```

## Contributing

1. Follow PSR-12 coding standards
2. Write tests for new features
3. Update documentation
4. Submit pull requests
