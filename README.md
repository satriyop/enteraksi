# Enteraksi LMS

A Learning Management System built for Indonesian banking and financial compliance training.

[![PHP Version](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3-4FC08D?logo=vue.js)](https://vuejs.org)
[![License](https://img.shields.io/badge/License-Proprietary-blue)]()

## Overview

Enteraksi is an enterprise LMS designed for:
- **Banking/Financial Compliance Training** - OJK regulation compliance, AML, cyber security
- **Indonesian Context** - Bahasa Indonesia UI, local date formats, Indonesian content
- **Industry Standards** - SCORM, xAPI, LTI compatible architecture

## Quick Start

### Prerequisites

- PHP 8.4+
- Composer 2.x
- Node.js 20+
- MySQL 8.0+ or SQLite

### Installation

```bash
# Clone the repository
git clone <repository-url>
cd enteraksi

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Seed the database (optional, creates test data)
php artisan db:seed

# Build frontend assets
npm run build
```

### Running the Application

```bash
# Option 1: Using Laravel's built-in server
php artisan serve
npm run dev  # In another terminal

# Option 2: Using the combined command
composer run dev
```

Visit `http://localhost:8000` in your browser.

### Test Accounts

After seeding, these accounts are available:

| Role | Email | Password |
|------|-------|----------|
| Learner | test@example.com | password |
| Content Manager | content@example.com | password |
| Trainer | trainer@example.com | password |
| LMS Admin | admin@example.com | password |

## Documentation

Comprehensive documentation is available in the [`docs/`](./docs/) directory:

| Document | Description |
|----------|-------------|
| [Documentation Index](./docs/index.md) | Start here - documentation overview |
| [Getting Started](./docs/getting-started/installation.md) | Detailed setup instructions |
| [Architecture](./docs/architecture/overview.md) | System architecture (Arc42) |
| [Guides](./docs/guides/) | How-to guides for common tasks |
| [Reference](./docs/reference/) | API and model reference |
| [ADRs](./docs/adr/) | Architecture Decision Records |

### Quick Links

- [Architecture Overview](./docs/ARCHITECTURE.md)
- [Data Model Reference](./docs/DATA-MODEL.md)
- [Feature Flows](./docs/FEATURES.md)

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12, PHP 8.4 |
| Frontend | Vue 3, TypeScript, Inertia.js v2 |
| Styling | Tailwind CSS v4 |
| Database | MySQL / SQLite |
| Authentication | Laravel Fortify |
| Testing | PHPUnit 11 |

## Project Structure

```
enteraksi/
├── app/                    # Laravel application code
│   ├── Http/Controllers/   # Request handlers
│   ├── Models/             # Eloquent models
│   ├── Policies/           # Authorization policies
│   └── Services/           # Business logic services
├── database/
│   ├── migrations/         # Database schema
│   ├── factories/          # Test data factories
│   └── seeders/            # Database seeders
├── resources/js/           # Vue.js frontend
│   ├── components/         # Reusable components
│   ├── layouts/            # Page layouts
│   └── pages/              # Page components
├── routes/                 # Route definitions
├── tests/                  # Test suites
└── docs/                   # Documentation
```

## Development

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/CourseTest.php

# Run with coverage
php artisan test --coverage
```

### Code Style

```bash
# Fix PHP code style
vendor/bin/pint

# Fix JavaScript/TypeScript code style
npm run lint
```

### Useful Commands

```bash
# Generate Wayfinder routes (after route changes)
php artisan wayfinder:generate

# Clear all caches
php artisan optimize:clear

# Run database fresh with seeds
php artisan migrate:fresh --seed
```

## Contributing

Please read our [Contributing Guide](./docs/contributing.md) before submitting changes.

## License

Proprietary - All rights reserved.

## Support

For issues and questions:
- Check the [Troubleshooting Guide](./docs/troubleshooting.md)
- Review [existing documentation](./docs/)
- Contact the development team
