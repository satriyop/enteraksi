# Troubleshooting Guide

Common issues and their solutions when working with Enteraksi LMS.

---

## Installation Issues

### "Class not found" errors after installation

**Symptom:** `Class 'App\...' not found` errors

**Solution:**
```bash
composer dump-autoload
php artisan optimize:clear
```

---

### "Vite manifest not found" error

**Symptom:** `Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest`

**Solution:**
```bash
# Build frontend assets
npm run build

# Or run development server
npm run dev
```

---

### Database connection refused

**Symptom:** `SQLSTATE[HY000] [2002] Connection refused`

**Solutions:**

1. **Check MySQL is running:**
   ```bash
   # macOS
   brew services start mysql

   # Linux
   sudo systemctl start mysql
   ```

2. **Verify credentials in `.env`:**
   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=enteraksi
   DB_USERNAME=root
   DB_PASSWORD=your_password
   ```

3. **Clear config cache:**
   ```bash
   php artisan config:clear
   ```

---

### Permission denied on storage

**Symptom:** `file_put_contents(): failed to open stream: Permission denied`

**Solution:**
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache  # Linux with web server
```

---

### npm install fails with node-gyp errors

**Symptom:** `gyp ERR! build error` during `npm install`

**Solution:**
```bash
# Install build tools
# macOS
xcode-select --install

# Linux (Ubuntu/Debian)
sudo apt-get install build-essential

# Then retry
rm -rf node_modules package-lock.json
npm install
```

---

## Runtime Issues

### Changes not reflecting in browser

**Symptom:** Code changes don't appear after refresh

**Solutions:**

1. **Clear all caches:**
   ```bash
   php artisan optimize:clear
   ```

2. **Rebuild frontend:**
   ```bash
   npm run build
   ```

3. **Hard refresh browser:** `Ctrl+Shift+R` (Windows/Linux) or `Cmd+Shift+R` (macOS)

---

### 419 Page Expired error

**Symptom:** `419 | Page Expired` on form submission

**Causes:**
- CSRF token mismatch
- Session expired

**Solutions:**

1. **Refresh the page** to get a new CSRF token

2. **Check session configuration:**
   ```env
   SESSION_DRIVER=database  # or file, redis
   SESSION_LIFETIME=120
   ```

3. **Ensure `@csrf` directive** in Blade forms (Inertia handles this automatically)

---

### 403 Forbidden on action

**Symptom:** `403 | This action is unauthorized`

**Possible causes:**

1. **User lacks permission** - Check user role:
   ```bash
   php artisan tinker
   >>> User::find(1)->role
   ```

2. **Policy denies action** - Review the relevant policy in `app/Policies/`

3. **Course is published** - Published courses can only be edited by LMS Admin

---

### Media upload fails

**Symptom:** File uploads timeout or fail

**Solutions:**

1. **Check PHP limits in `php.ini`:**
   ```ini
   upload_max_filesize = 512M
   post_max_size = 512M
   max_execution_time = 300
   ```

2. **Check Nginx/Apache limits:**
   ```nginx
   # Nginx
   client_max_body_size 512M;
   ```

3. **Verify storage permissions:**
   ```bash
   chmod -R 775 storage/app/public
   ```

4. **Check disk space:**
   ```bash
   df -h
   ```

---

### Video won't play

**Symptom:** Video shows but doesn't play

**Solutions:**

1. **Check file format** - Supported: MP4, WebM, MOV

2. **Check storage link:**
   ```bash
   php artisan storage:link
   ```

3. **Check file permissions:**
   ```bash
   ls -la storage/app/public/lessons/
   ```

4. **Check browser console** for specific errors

---

### Progress not saving

**Symptom:** Lesson progress resets after leaving

**Solutions:**

1. **Check enrollment exists:**
   ```bash
   php artisan tinker
   >>> Enrollment::where('user_id', 1)->where('course_id', 1)->first()
   ```

2. **Check browser network tab** for failed PATCH requests

3. **Verify route exists:**
   ```bash
   php artisan route:list --path=progress
   ```

---

## Testing Issues

### Tests failing with "table not found"

**Symptom:** `SQLSTATE[HY000]: General error: 1 no such table`

**Solution:**
```bash
# Refresh test database
php artisan migrate:fresh --env=testing
```

---

### Tests failing randomly

**Symptom:** Tests pass individually but fail together

**Solutions:**

1. **Add `RefreshDatabase` trait:**
   ```php
   use Illuminate\Foundation\Testing\RefreshDatabase;

   class MyTest extends TestCase
   {
       use RefreshDatabase;
   }
   ```

2. **Isolate database transactions:**
   ```php
   use Illuminate\Foundation\Testing\DatabaseTransactions;
   ```

---

### Factory errors

**Symptom:** `Call to undefined method App\Models\X::factory()`

**Solution:** Ensure model uses `HasFactory` trait:
```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Course extends Model
{
    use HasFactory;
}
```

---

## Performance Issues

### Slow page loads

**Solutions:**

1. **Enable caching in production:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

2. **Check for N+1 queries** using Laravel Telescope or Debugbar

3. **Eager load relationships:**
   ```php
   // Bad
   $courses = Course::all();
   foreach ($courses as $course) {
       echo $course->user->name;  // N+1!
   }

   // Good
   $courses = Course::with('user')->get();
   ```

---

### High memory usage

**Solutions:**

1. **Use chunking for large datasets:**
   ```php
   Course::chunk(100, function ($courses) {
       // Process in batches
   });
   ```

2. **Use cursor for reading:**
   ```php
   foreach (Course::cursor() as $course) {
       // Memory efficient
   }
   ```

---

## Common Error Messages

| Error | Meaning | Solution |
|-------|---------|----------|
| `CSRF token mismatch` | Session expired or token invalid | Refresh page |
| `Unauthenticated` | Not logged in | Log in |
| `This action is unauthorized` | Policy denied access | Check user role/permissions |
| `The given data was invalid` | Validation failed | Check form inputs |
| `Model not found` | Record doesn't exist | Verify ID is correct |
| `Too Many Attempts` | Rate limited | Wait and retry |

---

## Getting Help

If you can't resolve an issue:

1. **Check the logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Enable debug mode** (development only):
   ```env
   APP_DEBUG=true
   ```

3. **Use Laravel Telescope** for detailed debugging

4. **Search existing issues** in the repository

5. **Contact the development team** with:
   - Error message
   - Steps to reproduce
   - Log entries
   - Environment details
