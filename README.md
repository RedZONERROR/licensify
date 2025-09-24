# Laravel License Management System

A comprehensive license management system built with Laravel, featuring secure license validation, device binding, user management, and API access.

## Features

### Core Functionality
- **License Management**: Create, manage, and validate software licenses
- **Device Binding**: Automatic device registration and validation
- **User Management**: Role-based access control with admin and user roles
- **Product Management**: Organize licenses by products
- **Audit Logging**: Complete audit trail of all system activities

### Security Features
- **Two-Factor Authentication**: TOTP-based 2FA for enhanced security
- **OAuth Integration**: Support for external OAuth providers
- **Session Security**: IP and user agent validation
- **API Authentication**: JWT and HMAC-based API access
- **Rate Limiting**: Configurable rate limits for API endpoints

### API Features
- **License Validation API**: RESTful API for license validation
- **Device Management**: Automatic device binding and tracking
- **Request Logging**: Comprehensive API request logging
- **Standardized Responses**: Consistent JSON response format

### Administrative Features
- **Admin Dashboard**: Web-based administration interface
- **Backup Management**: Automated backup creation and management
- **Settings Management**: Configurable system settings
- **Chat System**: Internal communication system

## Requirements

- PHP 8.1 or higher
- Laravel 10.x
- MySQL 8.0 or PostgreSQL 13+
- Composer
- Node.js 16+ (for frontend assets)

## Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd licensify
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure database**
   Edit `.env` file with your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=licensify
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

6. **Run migrations**
   ```bash
   php artisan migrate
   ```

7. **Seed the database** (optional)
   ```bash
   php artisan db:seed
   ```

8. **Build frontend assets**
   ```bash
   npm run build
   ```

9. **Start the development server**
   ```bash
   php artisan serve
   ```

## Configuration

### Two-Factor Authentication
Configure 2FA settings in `.env`:
```env
TOTP_ISSUER="License Management System"
TOTP_DIGITS=6
TOTP_PERIOD=30
```

### OAuth Integration
Add OAuth provider credentials:
```env
GITHUB_CLIENT_ID=your_github_client_id
GITHUB_CLIENT_SECRET=your_github_client_secret
GITHUB_REDIRECT_URI=http://localhost:8000/auth/github/callback

GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

### API Configuration
Configure API settings:
```env
API_RATE_LIMIT=1000
API_TIMESTAMP_TOLERANCE=300
```

## Usage

### Web Interface
Access the web interface at `http://localhost:8000`

**Default Admin Account:**
- Email: admin@example.com
- Password: password

### API Usage
The system provides RESTful APIs for license validation. See [API Documentation](docs/api/license-validation.md) for detailed usage instructions.

**Example API Request:**
```bash
curl -X POST http://localhost:8000/api/license/validate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-jwt-token" \
  -d '{"license_key":"your-license-key","device_hash":"device-identifier"}'
```

## Testing

Run the test suite:
```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test tests/Feature/
php artisan test tests/Unit/

# Run with coverage
php artisan test --coverage
```

## Development Status

### Completed Tasks (1-10)
- ✅ **Task 1**: Project Setup and Database Schema
- ✅ **Task 2**: User Authentication and Authorization
- ✅ **Task 3**: License Model and Management
- ✅ **Task 4**: Device Binding and Validation
- ✅ **Task 5**: Admin Dashboard and License Management
- ✅ **Task 6**: Two-Factor Authentication
- ✅ **Task 7**: OAuth Integration
- ✅ **Task 8**: Audit Logging and Security
- ✅ **Task 9**: Backup and Settings Management
- ✅ **Task 10**: License Validation API

## Architecture

### Models
- **User**: User accounts with role-based permissions
- **License**: Software licenses with device binding
- **Product**: Product catalog for license organization
- **ApiClient**: API client management for external access
- **AuditLog**: Comprehensive audit logging
- **Backup**: Automated backup management

### Security Layers
- Authentication (Session + JWT)
- Authorization (Role-based + Policies)
- Rate Limiting (Per-client + IP-based)
- Request Validation (HMAC + Timestamp + Nonce)
- Audit Logging (All actions tracked)

### API Endpoints
- `POST /api/license/validate` - Validate license and bind device
- `GET /api/license/{key}` - Retrieve license information

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support and questions:
- Create an issue in the repository
- Check the [API Documentation](docs/api/license-validation.md)
- Review the test files for usage examples