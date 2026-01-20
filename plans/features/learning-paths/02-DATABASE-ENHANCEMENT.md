# Phase 2: Database Enhancement

> **Phase**: 2 of 6
> **Estimated Effort**: Low-Medium
> **Prerequisites**: Phase 1 (Domain Layer)

---

## Objectives

- Create migration for `learning_path_enrollments` table
- Create migration for `learning_path_course_progress` table
- Implement new Eloquent models
- Add relationships to existing models
- Create model factories
- Create database seeders

---

## 2.1 Migration: Learning Path Enrollments

### File: `database/migrations/2026_01_20_000001_create_learning_path_enrollments_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_path_enrollments', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('learning_path_id')
                ->constrained('learning_paths')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Status tracking
            $table->enum('status', ['active', 'completed', 'dropped'])
                ->default('active');

            // Timestamps
            $table->timestamp('enrolled_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dropped_at')->nullable();

            // Progress
            $table->unsignedTinyInteger('progress_percentage')->default(0);

            // Additional data
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Constraints
            $table->unique(['learning_path_id', 'user_id']);

            // Indexes for common queries
            $table->index(['user_id', 'status']);
            $table->index(['learning_path_id', 'status']);
            $table->index('enrolled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_path_enrollments');
    }
};
```

---

## 2.2 Migration: Learning Path Course Progress

### File: `database/migrations/2026_01_20_000002_create_learning_path_course_progress_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_path_course_progress', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->foreignId('learning_path_enrollment_id')
                ->constrained('learning_path_enrollments')
                ->cascadeOnDelete();

            $table->foreignId('course_id')
                ->constrained('courses')
                ->cascadeOnDelete();

            // Link to actual course enrollment (when enrolled)
            $table->foreignId('enrollment_id')
                ->nullable()
                ->constrained('enrollments')
                ->nullOnDelete();

            // Status: locked, available, in_progress, completed
            $table->enum('status', ['locked', 'available', 'in_progress', 'completed'])
                ->default('locked');

            // Timestamps for status transitions
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Progress within this course
            $table->unsignedTinyInteger('completion_percentage')->default(0);

            $table->timestamps();

            // Constraints
            $table->unique(['learning_path_enrollment_id', 'course_id'], 'path_enrollment_course_unique');

            // Indexes
            $table->index('status');
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_path_course_progress');
    }
};
```

---

## 2.3 Model: LearningPathEnrollment

### File: `app/Models/LearningPathEnrollment.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningPathEnrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_path_id',
        'user_id',
        'status',
        'enrolled_at',
        'completed_at',
        'dropped_at',
        'progress_percentage',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'dropped_at' => 'datetime',
            'progress_percentage' => 'integer',
            'metadata' => 'array',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courseProgress(): HasMany
    {
        return $this->hasMany(LearningPathCourseProgress::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    /**
     * Only active enrollments.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Only completed enrollments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Enrollments for a specific user.
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Enrollments for a specific path.
     */
    public function scopeForPath($query, LearningPath $path)
    {
        return $query->where('learning_path_id', $path->id);
    }

    // ─────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────

    /**
     * Check if enrollment is active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if enrollment is completed.
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Get formatted enrolled date in Indonesian.
     */
    public function getFormattedEnrolledAtAttribute(): string
    {
        return $this->enrolled_at->translatedFormat('d F Y');
    }

    /**
     * Get completed courses count.
     */
    public function getCompletedCoursesCountAttribute(): int
    {
        return $this->courseProgress()
            ->where('status', 'completed')
            ->count();
    }

    /**
     * Get total courses count.
     */
    public function getTotalCoursesCountAttribute(): int
    {
        return $this->courseProgress()->count();
    }

    // ─────────────────────────────────────────────────────────────
    // Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Check if a specific course is completed in this enrollment.
     */
    public function isCourseCompleted(Course $course): bool
    {
        return $this->courseProgress()
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Check if a specific course is unlocked in this enrollment.
     */
    public function isCourseUnlocked(Course $course): bool
    {
        return $this->courseProgress()
            ->where('course_id', $course->id)
            ->whereIn('status', ['available', 'in_progress', 'completed'])
            ->exists();
    }

    /**
     * Get progress for a specific course.
     */
    public function getCourseProgress(Course $course): ?LearningPathCourseProgress
    {
        return $this->courseProgress()
            ->where('course_id', $course->id)
            ->first();
    }
}
```

---

## 2.4 Model: LearningPathCourseProgress

### File: `app/Models/LearningPathCourseProgress.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningPathCourseProgress extends Model
{
    use HasFactory;

    protected $table = 'learning_path_course_progress';

    protected $fillable = [
        'learning_path_enrollment_id',
        'course_id',
        'enrollment_id',
        'status',
        'unlocked_at',
        'started_at',
        'completed_at',
        'completion_percentage',
    ];

    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'completion_percentage' => 'integer',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function pathEnrollment(): BelongsTo
    {
        return $this->belongsTo(LearningPathEnrollment::class, 'learning_path_enrollment_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    /**
     * Only locked courses.
     */
    public function scopeLocked($query)
    {
        return $query->where('status', 'locked');
    }

    /**
     * Only available (unlocked but not started) courses.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Only in-progress courses.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Only completed courses.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Unlocked courses (available, in_progress, or completed).
     */
    public function scopeUnlocked($query)
    {
        return $query->whereIn('status', ['available', 'in_progress', 'completed']);
    }

    // ─────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────

    /**
     * Check if course is locked.
     */
    public function getIsLockedAttribute(): bool
    {
        return $this->status === 'locked';
    }

    /**
     * Check if course is available.
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Check if course is in progress.
     */
    public function getIsInProgressAttribute(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if course is completed.
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if course is unlocked (not locked).
     */
    public function getIsUnlockedAttribute(): bool
    {
        return in_array($this->status, ['available', 'in_progress', 'completed']);
    }

    /**
     * Get status label in Indonesian.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'locked' => 'Terkunci',
            'available' => 'Tersedia',
            'in_progress' => 'Sedang Berlangsung',
            'completed' => 'Selesai',
            default => $this->status,
        };
    }
}
```

---

## 2.5 Update Existing Models

### Add to `LearningPath` Model

```php
// Add to app/Models/LearningPath.php

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Learning path enrollments.
 */
public function pathEnrollments(): HasMany
{
    return $this->hasMany(LearningPathEnrollment::class);
}

/**
 * Active enrollments count.
 */
public function getActiveEnrollmentsCountAttribute(): int
{
    return $this->pathEnrollments()->active()->count();
}

/**
 * Completed enrollments count.
 */
public function getCompletedEnrollmentsCountAttribute(): int
{
    return $this->pathEnrollments()->completed()->count();
}

/**
 * Check if a user is enrolled in this path.
 */
public function isUserEnrolled(User $user): bool
{
    return $this->pathEnrollments()
        ->forUser($user)
        ->whereIn('status', ['active', 'completed'])
        ->exists();
}

/**
 * Get user's enrollment for this path.
 */
public function getUserEnrollment(User $user): ?LearningPathEnrollment
{
    return $this->pathEnrollments()
        ->forUser($user)
        ->first();
}
```

### Add to `User` Model

```php
// Add to app/Models/User.php

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Learning path enrollments for this user.
 */
public function learningPathEnrollments(): HasMany
{
    return $this->hasMany(LearningPathEnrollment::class);
}

/**
 * Active learning path enrollments.
 */
public function activeLearningPathEnrollments(): HasMany
{
    return $this->learningPathEnrollments()->active();
}

/**
 * Completed learning path enrollments.
 */
public function completedLearningPathEnrollments(): HasMany
{
    return $this->learningPathEnrollments()->completed();
}

/**
 * Check if user is enrolled in a learning path.
 */
public function isEnrolledInPath(LearningPath $path): bool
{
    return $this->learningPathEnrollments()
        ->forPath($path)
        ->whereIn('status', ['active', 'completed'])
        ->exists();
}
```

### Add to `Course` Model

```php
// Add to app/Models/Course.php

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Learning path course progress records for this course.
 */
public function learningPathProgress(): HasMany
{
    return $this->hasMany(LearningPathCourseProgress::class);
}
```

---

## 2.6 Model Factory: LearningPathEnrollmentFactory

### File: `database/factories/LearningPathEnrollmentFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LearningPathEnrollment>
 */
class LearningPathEnrollmentFactory extends Factory
{
    protected $model = LearningPathEnrollment::class;

    public function definition(): array
    {
        return [
            'learning_path_id' => LearningPath::factory(),
            'user_id' => User::factory(),
            'status' => 'active',
            'enrolled_at' => now(),
            'completed_at' => null,
            'dropped_at' => null,
            'progress_percentage' => 0,
            'metadata' => null,
        ];
    }

    /**
     * Active enrollment with some progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'progress_percentage' => fake()->numberBetween(10, 80),
        ]);
    }

    /**
     * Completed enrollment.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'progress_percentage' => 100,
            'completed_at' => now(),
        ]);
    }

    /**
     * Dropped enrollment.
     */
    public function dropped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'dropped',
            'dropped_at' => now(),
            'metadata' => [
                'drop_reason' => 'Test drop reason',
            ],
        ]);
    }

    /**
     * Enrollment started some time ago.
     */
    public function startedDaysAgo(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'enrolled_at' => now()->subDays($days),
        ]);
    }
}
```

---

## 2.7 Model Factory: LearningPathCourseProgressFactory

### File: `database/factories/LearningPathCourseProgressFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LearningPathCourseProgress>
 */
class LearningPathCourseProgressFactory extends Factory
{
    protected $model = LearningPathCourseProgress::class;

    public function definition(): array
    {
        return [
            'learning_path_enrollment_id' => LearningPathEnrollment::factory(),
            'course_id' => Course::factory(),
            'enrollment_id' => null,
            'status' => 'locked',
            'unlocked_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'completion_percentage' => 0,
        ];
    }

    /**
     * Course is unlocked and available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'available',
            'unlocked_at' => now(),
        ]);
    }

    /**
     * Course is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'unlocked_at' => now()->subDays(2),
            'started_at' => now()->subDay(),
            'completion_percentage' => fake()->numberBetween(10, 80),
        ]);
    }

    /**
     * Course is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'unlocked_at' => now()->subDays(7),
            'started_at' => now()->subDays(5),
            'completed_at' => now(),
            'completion_percentage' => 100,
        ]);
    }

    /**
     * Course is locked.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'locked',
            'unlocked_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'completion_percentage' => 0,
        ]);
    }
}
```

---

## 2.8 Database Seeder

### File: `database/seeders/LearningPathEnrollmentSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\LearningPath;
use App\Models\LearningPathCourseProgress;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Database\Seeder;

class LearningPathEnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        // Get published learning paths with courses
        $paths = LearningPath::published()
            ->has('courses')
            ->with('courses')
            ->get();

        if ($paths->isEmpty()) {
            $this->command->info('No published learning paths with courses found. Skipping seeder.');
            return;
        }

        // Get learners
        $learners = User::where('role', 'learner')
            ->inRandomOrder()
            ->limit(10)
            ->get();

        if ($learners->isEmpty()) {
            $this->command->info('No learners found. Skipping seeder.');
            return;
        }

        foreach ($learners as $learner) {
            // Enroll each learner in 1-3 random paths
            $selectedPaths = $paths->random(min(rand(1, 3), $paths->count()));

            foreach ($selectedPaths as $path) {
                // Check if already enrolled
                if (LearningPathEnrollment::where('user_id', $learner->id)
                    ->where('learning_path_id', $path->id)
                    ->exists()) {
                    continue;
                }

                // Create enrollment with random progress
                $enrollment = LearningPathEnrollment::factory()
                    ->create([
                        'learning_path_id' => $path->id,
                        'user_id' => $learner->id,
                        'enrolled_at' => now()->subDays(rand(1, 30)),
                    ]);

                // Create course progress for each course
                $this->createCourseProgress($enrollment, $path);
            }
        }

        $this->command->info('Learning path enrollments seeded successfully.');
    }

    private function createCourseProgress(LearningPathEnrollment $enrollment, LearningPath $path): void
    {
        $courses = $path->courses()->orderBy('pivot_position')->get();
        $progressStage = rand(0, $courses->count()); // How far the learner has progressed

        foreach ($courses as $index => $course) {
            if ($index < $progressStage - 1) {
                // Completed courses
                LearningPathCourseProgress::factory()
                    ->completed()
                    ->create([
                        'learning_path_enrollment_id' => $enrollment->id,
                        'course_id' => $course->id,
                    ]);
            } elseif ($index === $progressStage - 1) {
                // Current course (in progress)
                LearningPathCourseProgress::factory()
                    ->inProgress()
                    ->create([
                        'learning_path_enrollment_id' => $enrollment->id,
                        'course_id' => $course->id,
                    ]);
            } elseif ($index === $progressStage) {
                // Next course (available)
                LearningPathCourseProgress::factory()
                    ->available()
                    ->create([
                        'learning_path_enrollment_id' => $enrollment->id,
                        'course_id' => $course->id,
                    ]);
            } else {
                // Future courses (locked)
                LearningPathCourseProgress::factory()
                    ->locked()
                    ->create([
                        'learning_path_enrollment_id' => $enrollment->id,
                        'course_id' => $course->id,
                    ]);
            }
        }

        // Update enrollment progress percentage
        $completedCount = $enrollment->courseProgress()->completed()->count();
        $totalCount = $courses->count();
        $percentage = $totalCount > 0 ? (int) round(($completedCount / $totalCount) * 100) : 0;

        $enrollment->update(['progress_percentage' => $percentage]);
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
        LearningPathEnrollmentSeeder::class, // Add this after LearningPathSeeder
    ]);
}
```

---

## 2.9 Indexes Rationale

| Table | Index | Columns | Purpose |
|-------|-------|---------|---------|
| learning_path_enrollments | Primary | `id` | Default |
| learning_path_enrollments | Unique | `learning_path_id, user_id` | One enrollment per user per path |
| learning_path_enrollments | Composite | `user_id, status` | User's active/completed paths |
| learning_path_enrollments | Composite | `learning_path_id, status` | Path's enrollments by status |
| learning_path_enrollments | Index | `enrolled_at` | Sort by enrollment date |
| learning_path_course_progress | Primary | `id` | Default |
| learning_path_course_progress | Unique | `learning_path_enrollment_id, course_id` | One record per course per enrollment |
| learning_path_course_progress | Index | `status` | Filter by status |
| learning_path_course_progress | Index | `course_id` | Find enrollments for a course |

---

## Implementation Checklist

- [ ] Create migration for `learning_path_enrollments`
- [ ] Create migration for `learning_path_course_progress`
- [ ] Run migrations
- [ ] Create `LearningPathEnrollment` model
- [ ] Create `LearningPathCourseProgress` model
- [ ] Add relationships to `LearningPath` model
- [ ] Add relationships to `User` model
- [ ] Add relationships to `Course` model
- [ ] Create `LearningPathEnrollmentFactory`
- [ ] Create `LearningPathCourseProgressFactory`
- [ ] Create `LearningPathEnrollmentSeeder`
- [ ] Update `DatabaseSeeder`
- [ ] Test factories in tinker
- [ ] Write model unit tests

---

## Verification Commands

```bash
# Create migrations
php artisan make:migration create_learning_path_enrollments_table
php artisan make:migration create_learning_path_course_progress_table

# Run migrations
php artisan migrate

# Create models
php artisan make:model LearningPathEnrollment -f
php artisan make:model LearningPathCourseProgress -f

# Create seeder
php artisan make:seeder LearningPathEnrollmentSeeder

# Test in tinker
php artisan tinker
>>> LearningPathEnrollment::factory()->create()
>>> LearningPathEnrollment::factory()->completed()->create()
>>> LearningPathCourseProgress::factory()->inProgress()->create()

# Verify relationships
>>> LearningPath::first()->pathEnrollments
>>> User::first()->learningPathEnrollments
>>> LearningPathEnrollment::first()->courseProgress
```

---

## Next Phase

Continue to [Phase 3: Prerequisites and Branching](./03-PREREQUISITES-AND-BRANCHING.md)
