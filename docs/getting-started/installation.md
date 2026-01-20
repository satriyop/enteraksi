# Installation Guide

This guide walks you through setting up Enteraksi LMS for local development.

## Prerequisites

Before starting, ensure you have:

| Requirement | Version | Check Command |
|-------------|---------|---------------|
| PHP | 8.4+ | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | 20+ | `node -v` |
| npm | 10+ | `npm -v` |
| MySQL | 8.0+ | `mysql --version` |

> **Note**: SQLite can be used instead of MySQL for development.

## Step 1: Clone the Repository

```bash
git clone <repository-url>
cd enteraksi
```

## Step 2: Install PHP Dependencies

```bash
composer install
```

This installs Laravel and all PHP packages defined in `composer.json`.

**Common Issues:**
- If you get memory errors, run: `COMPOSER_MEMORY_LIMIT=-1 composer install`
- If extensions are missing, install them via your PHP package manager

## Step 3: Install JavaScript Dependencies

```bash
npm install
```

This installs Vue.js, Tailwind CSS, and all frontend packages.

## Step 4: Environment Configuration

```bash
# Copy the example environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### Configure Database

Edit `.env` and set your database connection:

**For MySQL:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=enteraksi
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

**For SQLite (simpler for development):**
```env
DB_CONNECTION=sqlite
# Comment out or remove DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
```

Then create the SQLite file:
```bash
touch database/database.sqlite
```

### Configure Mail (Optional)

For password reset and email verification:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_FROM_ADDRESS=noreply@enteraksi.test
MAIL_FROM_NAME="Enteraksi LMS"
```

> **Tip**: Use [Mailtrap](https://mailtrap.io) for development email testing.

## Step 5: Run Database Migrations

```bash
php artisan migrate
```

This creates all database tables.

## Step 6: Seed Test Data (Recommended)

```bash
php artisan db:seed
```

This creates:
- 4 test users (learner, content_manager, trainer, lms_admin)
- 6 course categories
- 37 tags
- 5 sample courses with sections, lessons, and media

See [Test Accounts](#test-accounts) for login credentials.

## Step 7: Build Frontend Assets

**For development (with hot reload):**
```bash
npm run dev
```

**For production:**
```bash
npm run build
```

## Step 8: Start the Application

**Option 1: Separate terminals**
```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite dev server (for hot reload)
npm run dev
```

**Option 2: Combined command**
```bash
composer run dev
```

Visit **http://localhost:8000** in your browser.

---

## Test Accounts

After seeding, use these accounts:

| Role | Email | Password | Can Do |
|------|-------|----------|--------|
| Learner | test@example.com | password | Enroll, learn, take assessments |
| Content Manager | content@example.com | password | Create/edit own courses |
| Trainer | trainer@example.com | password | Create courses, invite learners |
| LMS Admin | admin@example.com | password | Full access, publish courses |

---

## Verifying Installation

### Check Laravel
```bash
php artisan about
```

Should display Laravel version and environment info.

### Check Database Connection
```bash
php artisan migrate:status
```

Should list all migrations as "Ran".

### Check Frontend Build
```bash
npm run build
```

Should complete without errors.

### Run Tests
```bash
php artisan test
```

All tests should pass.

---

## Common Issues

### "Class not found" errors
```bash
composer dump-autoload
php artisan optimize:clear
```

### "Vite manifest not found" error
```bash
npm run build
```

### Permission denied on storage
```bash
chmod -R 775 storage bootstrap/cache
```

### Database connection refused
- Verify MySQL is running
- Check `.env` database credentials
- Try: `php artisan config:clear`

---

## Next Steps

- [Configuration Guide](./configuration.md) - Configure all options
- [Your First Course](./first-course.md) - Create a course tutorial
- [Understanding Roles](./roles.md) - Learn about user permissions

---

## Using Docker (Laravel Sail)

If you prefer Docker:

```bash
# Install dependencies with Sail
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# Start Sail
./vendor/bin/sail up -d

# Run migrations
./vendor/bin/sail artisan migrate

# Install npm packages and build
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Visit **http://localhost** (port 80 with Sail).
