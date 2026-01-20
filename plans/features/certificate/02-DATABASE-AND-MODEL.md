# Phase 2: Database and Model

> **Phase**: 2 of 5
> **Estimated Effort**: Low
> **Prerequisites**: Phase 1 (Domain Layer)

---

## Objectives

- Create database migration for certificates table
- Implement Certificate Eloquent model
- Create model factory for testing
- Add relationships to existing models
- Create database seeder

---

## 2.1 Migration

### File: `database/migrations/2026_01_20_000001_create_certificates_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('course_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('enrollment_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Certificate identification
            $table->string('certificate_number', 50)->unique();

            // Dates
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->nullable();

            // Revocation
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('revocation_reason')->nullable();

            // Additional data
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['user_id', 'issued_at']);
            $table->index(['course_id', 'issued_at']);
            $table->index('certificate_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
```

---

## 2.2 Certificate Model

### File: `app/Models/Certificate.php`

```php
<?php

namespace App\Models;

use App\Domain\Certificate\ValueObjects\CertificateNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'enrollment_id',
        'certificate_number',
        'issued_at',
        'expires_at',
        'revoked_at',
        'revoked_by',
        'revocation_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected $appends = [
        'is_valid',
        'is_expired',
        'verification_url',
    ];

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function revokedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    /**
     * Only valid (non-revoked, non-expired) certificates.
     */
    public function scopeValid($query)
    {
        return $query->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Only revoked certificates.
     */
    public function scopeRevoked($query)
    {
        return $query->whereNotNull('revoked_at');
    }

    /**
     * Only expired certificates.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Certificates for a specific user.
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Certificates for a specific course.
     */
    public function scopeForCourse($query, Course $course)
    {
        return $query->where('course_id', $course->id);
    }

    // ─────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────

    /**
     * Check if certificate is valid (not revoked and not expired).
     */
    public function getIsValidAttribute(): bool
    {
        return $this->isValid();
    }

    /**
     * Check if certificate is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->isExpired();
    }

    /**
     * Get the public verification URL.
     */
    public function getVerificationUrlAttribute(): string
    {
        return route('certificates.verify', $this->certificate_number);
    }

    /**
     * Get formatted issue date in Indonesian.
     */
    public function getFormattedIssueDateAttribute(): string
    {
        return $this->issued_at->translatedFormat('d F Y');
    }

    /**
     * Get formatted expiry date in Indonesian.
     */
    public function getFormattedExpiryDateAttribute(): ?string
    {
        return $this->expires_at?->translatedFormat('d F Y');
    }

    // ─────────────────────────────────────────────────────────────
    // Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Check if certificate is valid.
     */
    public function isValid(): bool
    {
        if ($this->isRevoked()) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        return true;
    }

    /**
     * Check if certificate is revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Check if certificate is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Get certificate number as value object.
     */
    public function getCertificateNumberObject(): CertificateNumber
    {
        return CertificateNumber::fromString($this->certificate_number);
    }
}
```

---

## 2.3 Model Factory

### File: `database/factories/CertificateFactory.php`

```php
<?php

namespace Database\Factories;

use App\Domain\Certificate\ValueObjects\CertificateNumber;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Certificate>
 */
class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'enrollment_id' => null,
            'certificate_number' => CertificateNumber::generate()->toString(),
            'issued_at' => now(),
            'expires_at' => null,
            'revoked_at' => null,
            'revoked_by' => null,
            'revocation_reason' => null,
            'metadata' => [
                'completion_date' => now()->toISOString(),
                'progress_percentage' => 100,
            ],
        ];
    }

    /**
     * Certificate with enrollment.
     */
    public function withEnrollment(): static
    {
        return $this->state(function (array $attributes) {
            $enrollment = Enrollment::factory()->completed()->create([
                'user_id' => $attributes['user_id'],
                'course_id' => $attributes['course_id'],
            ]);

            return [
                'enrollment_id' => $enrollment->id,
            ];
        });
    }

    /**
     * Certificate that is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
            'revoked_by' => User::factory(),
            'revocation_reason' => 'Certificate revoked for testing',
        ]);
    }

    /**
     * Certificate that is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'issued_at' => now()->subYear(),
            'expires_at' => now()->subMonth(),
        ]);
    }

    /**
     * Certificate that expires soon (within 30 days).
     */
    public function expiresSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'issued_at' => now()->subMonths(11),
            'expires_at' => now()->addDays(15),
        ]);
    }

    /**
     * Certificate with 1 year validity.
     */
    public function withOneYearValidity(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addYear(),
        ]);
    }

    /**
     * Certificate issued in specific year.
     */
    public function issuedInYear(int $year): static
    {
        $date = now()->setYear($year);

        return $this->state(fn (array $attributes) => [
            'issued_at' => $date,
            'certificate_number' => "CERT-{$year}-" . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)),
        ]);
    }
}
```

---

## 2.4 Add Relationships to Existing Models

### User Model Addition

```php
// Add to app/Models/User.php

/**
 * Certificates earned by this user.
 */
public function certificates(): HasMany
{
    return $this->hasMany(Certificate::class);
}

/**
 * Valid certificates only.
 */
public function validCertificates(): HasMany
{
    return $this->certificates()->valid();
}
```

### Course Model Addition

```php
// Add to app/Models/Course.php

/**
 * Certificates issued for this course.
 */
public function certificates(): HasMany
{
    return $this->hasMany(Certificate::class);
}

/**
 * Count of certificates issued.
 */
public function getCertificatesCountAttribute(): int
{
    return $this->certificates()->count();
}
```

### Enrollment Model Addition

```php
// Add to app/Models/Enrollment.php

/**
 * Certificate for this enrollment.
 */
public function certificate(): HasOne
{
    return $this->hasOne(Certificate::class);
}

/**
 * Check if enrollment has a certificate.
 */
public function hasCertificate(): bool
{
    return $this->certificate()->exists();
}
```

---

## 2.5 Database Seeder

### File: `database/seeders/CertificateSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

class CertificateSeeder extends Seeder
{
    public function run(): void
    {
        // Get completed enrollments that don't have certificates
        $completedEnrollments = Enrollment::where('status', 'completed')
            ->whereDoesntHave('certificate')
            ->with(['user', 'course'])
            ->get();

        foreach ($completedEnrollments as $enrollment) {
            Certificate::factory()->create([
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
                'enrollment_id' => $enrollment->id,
                'issued_at' => $enrollment->completed_at ?? now(),
                'metadata' => [
                    'completion_date' => $enrollment->completed_at?->toISOString(),
                    'progress_percentage' => $enrollment->progress_percentage,
                ],
            ]);
        }

        // Create some sample certificates for test user
        $testUser = User::where('email', 'test@example.com')->first();

        if ($testUser && $testUser->validCertificates()->count() === 0) {
            // Create a few certificates for demo
            Certificate::factory()
                ->count(2)
                ->sequence(
                    ['issued_at' => now()->subMonths(3)],
                    ['issued_at' => now()->subMonth()],
                )
                ->create([
                    'user_id' => $testUser->id,
                ]);
        }
    }
}
```

### Update DatabaseSeeder

```php
// In database/seeders/DatabaseSeeder.php, add:

public function run(): void
{
    // ... existing seeders

    $this->call([
        // ... existing
        CertificateSeeder::class, // Add this
    ]);
}
```

---

## 2.6 Database Indexes Rationale

| Index | Columns | Purpose |
|-------|---------|---------|
| Primary | `id` | Default primary key |
| Unique | `certificate_number` | Fast verification lookup |
| Composite | `user_id, issued_at` | User's certificates sorted by date |
| Composite | `course_id, issued_at` | Course's certificates sorted by date |
| FK Index | `enrollment_id` | Find certificate by enrollment |

---

## Implementation Checklist

- [ ] Create migration file
- [ ] Run migration
- [ ] Create Certificate model
- [ ] Add casts and accessors
- [ ] Add scopes
- [ ] Create factory with states
- [ ] Add relationship to User model
- [ ] Add relationship to Course model
- [ ] Add relationship to Enrollment model
- [ ] Create CertificateSeeder
- [ ] Update DatabaseSeeder
- [ ] Write model unit tests

---

## Verification Commands

```bash
# Create migration
php artisan make:migration create_certificates_table

# Run migration
php artisan migrate

# Create model with factory
php artisan make:model Certificate -f

# Create seeder
php artisan make:seeder CertificateSeeder

# Test factory
php artisan tinker
>>> Certificate::factory()->create()
>>> Certificate::factory()->revoked()->create()
>>> Certificate::factory()->expired()->create()

# Verify relationships
>>> User::first()->certificates
>>> Course::first()->certificates
```

---

## Next Phase

Continue to [Phase 3: PDF Generation](./03-PDF-GENERATION.md)
