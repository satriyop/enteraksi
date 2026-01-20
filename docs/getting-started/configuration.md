# Configuration Guide

This guide covers all configuration options for Enteraksi LMS.

## Environment Variables

All configuration is managed through the `.env` file. Never commit this file to version control.

### Application Settings

```env
APP_NAME="Enteraksi LMS"
APP_ENV=local              # local, staging, production
APP_KEY=                   # Auto-generated, don't change
APP_DEBUG=true             # false in production
APP_URL=http://localhost:8000
```

| Variable | Description | Values |
|----------|-------------|--------|
| APP_NAME | Displayed in UI and emails | Any string |
| APP_ENV | Environment mode | local, staging, production |
| APP_DEBUG | Show detailed errors | true/false |
| APP_URL | Base URL for links | Full URL with protocol |

### Database Configuration

**MySQL:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=enteraksi
DB_USERNAME=root
DB_PASSWORD=secret
```

**SQLite:**
```env
DB_CONNECTION=sqlite
```

**PostgreSQL:**
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=enteraksi
DB_USERNAME=postgres
DB_PASSWORD=secret
```

### Mail Configuration

Required for password reset and email verification.

**SMTP (Production):**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

**Mailtrap (Development):**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_user
MAIL_PASSWORD=your_mailtrap_pass
```

**Log (Testing - writes to log file):**
```env
MAIL_MAILER=log
```

### File Storage

```env
FILESYSTEM_DISK=public     # Where media files are stored
```

| Disk | Use Case |
|------|----------|
| local | Private files (not web accessible) |
| public | Media files (thumbnails, videos, documents) |
| s3 | AWS S3 for production |

**For AWS S3:**
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=enteraksi-media
```

### Session & Cache

```env
SESSION_DRIVER=database    # file, cookie, database, redis
SESSION_LIFETIME=120       # Minutes

CACHE_DRIVER=database      # file, database, redis
```

**For Redis (recommended for production):**
```env
SESSION_DRIVER=redis
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Queue Configuration

```env
QUEUE_CONNECTION=database  # sync, database, redis
```

| Driver | Use Case |
|--------|----------|
| sync | Development (immediate execution) |
| database | Simple production setup |
| redis | High-performance production |

---

## Laravel Configuration Files

### Authentication (`config/fortify.php`)

```php
'features' => [
    Features::registration(),           // Allow user registration
    Features::resetPasswords(),         // Password reset via email
    Features::emailVerification(),      // Require email verification
    Features::twoFactorAuthentication([
        'confirmPassword' => true,      // Require password to enable 2FA
        'confirm' => true,              // Require OTP confirmation
    ]),
],
```

**To disable registration:**
```php
'features' => [
    // Features::registration(),  // Comment out
    Features::resetPasswords(),
    // ... rest
],
```

### File Upload Limits (`config/filesystems.php`)

Defaults are set in `StoreMediaRequest`:
- Video: 512MB
- Audio: 100MB
- Document: 50MB
- Thumbnail: 5MB

To change, edit `app/Http/Requests/Media/StoreMediaRequest.php`.

### Inertia Settings (`config/inertia.php`)

```php
'ssr' => [
    'enabled' => false,  // Server-side rendering (requires Node.js)
],
```

---

## PHP Configuration

For large file uploads, update `php.ini`:

```ini
upload_max_filesize = 512M
post_max_size = 512M
max_execution_time = 300
memory_limit = 512M
```

Find your `php.ini`:
```bash
php --ini
```

---

## Web Server Configuration

### Nginx

```nginx
server {
    listen 80;
    server_name enteraksi.test;
    root /path/to/enteraksi/public;

    index index.php;

    client_max_body_size 512M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Apache

Ensure `mod_rewrite` is enabled. The `.htaccess` in `public/` handles routing.

```apache
<VirtualHost *:80>
    ServerName enteraksi.test
    DocumentRoot /path/to/enteraksi/public

    <Directory /path/to/enteraksi/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

---

## Storage Setup

### Create Symbolic Link

Required for serving uploaded files:

```bash
php artisan storage:link
```

This creates `public/storage` → `storage/app/public`.

### Directory Permissions

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache  # Linux with Apache/Nginx
```

---

## Production Checklist

Before deploying to production:

```bash
# Set environment
APP_ENV=production
APP_DEBUG=false

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Build frontend
npm run build

# Run migrations
php artisan migrate --force
```

### Security Headers

Add to web server config:
```
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

---

## Environment-Specific Settings

### Development
```env
APP_ENV=local
APP_DEBUG=true
LOG_LEVEL=debug
MAIL_MAILER=log
```

### Staging
```env
APP_ENV=staging
APP_DEBUG=true
LOG_LEVEL=debug
```

### Production
```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
SESSION_SECURE_COOKIE=true
```

---

## Next Steps

- [Your First Course](./first-course.md) - Create content
- [Understanding Roles](./roles.md) - User permissions
- [Deployment Guide](../architecture/deployment.md) - Production deployment
