# Phase 8: Migration & Rollout Guide

**Duration**: Reference document for entire project
**Priority**: Critical - Safe production deployment

---

## Objectives

1. Define safe migration strategy for production
2. Establish feature flag approach for gradual rollout
3. Create rollback procedures for each phase
4. Set up monitoring during deployment
5. Document communication plan

---

## 8.1 Rollout Philosophy

### Principles

1. **Never Break Production**: Old code paths remain functional until new code is proven
2. **Incremental Adoption**: Roll out to small user groups first
3. **Easy Rollback**: Every change can be reverted within minutes
4. **Observable**: Know immediately if something is wrong
5. **Communication**: Users know when changes are coming

### Risk Matrix

| Change Type | Risk Level | Rollout Strategy |
|-------------|------------|------------------|
| New directory structure | Low | Instant (no user impact) |
| Service layer extraction | Medium | Feature flag, gradual |
| State machine | Medium-High | Feature flag, shadow mode |
| Events/Listeners | Low-Medium | Canary deployment |
| Strategy patterns | Low | Feature flag |
| Database migrations | High | Blue-green, tested backups |

---

## 8.2 Feature Flags

### Feature Flag Configuration

```php
<?php
// config/features.php

return [

    /*
    |--------------------------------------------------------------------------
    | Architecture Refactoring Feature Flags
    |--------------------------------------------------------------------------
    |
    | These flags control the rollout of new architectural components.
    | Set to true to enable new implementation, false for legacy.
    |
    */

    'use_enrollment_service' => env('FEATURE_ENROLLMENT_SERVICE', false),
    'use_progress_service' => env('FEATURE_PROGRESS_SERVICE', false),
    'use_grading_service' => env('FEATURE_GRADING_SERVICE', false),
    'use_course_state_machine' => env('FEATURE_COURSE_STATE_MACHINE', false),
    'use_enrollment_state_machine' => env('FEATURE_ENROLLMENT_STATE_MACHINE', false),
    'use_attempt_state_machine' => env('FEATURE_ATTEMPT_STATE_MACHINE', false),
    'use_domain_events' => env('FEATURE_DOMAIN_EVENTS', false),
    'use_grading_strategies' => env('FEATURE_GRADING_STRATEGIES', false),

    /*
    |--------------------------------------------------------------------------
    | User-Based Rollout
    |--------------------------------------------------------------------------
    |
    | Enable features for specific users for testing.
    |
    */

    'pilot_user_ids' => explode(',', env('FEATURE_PILOT_USERS', '')),

    /*
    |--------------------------------------------------------------------------
    | Percentage Rollout
    |--------------------------------------------------------------------------
    |
    | Enable features for a percentage of users.
    |
    */

    'rollout_percentage' => [
        'enrollment_service' => (int) env('ROLLOUT_ENROLLMENT_SERVICE', 0),
        'progress_service' => (int) env('ROLLOUT_PROGRESS_SERVICE', 0),
        'grading_service' => (int) env('ROLLOUT_GRADING_SERVICE', 0),
    ],
];
```

### Feature Flag Service

```php
<?php
// app/Domain/Shared/Services/FeatureFlag.php

namespace App\Domain\Shared\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class FeatureFlag
{
    /**
     * Check if a feature is enabled for the current context.
     */
    public static function isEnabled(string $feature, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        // Check direct config flag
        if (config("features.{$feature}", false)) {
            return true;
        }

        // Check pilot users
        if ($user && self::isPilotUser($user)) {
            return true;
        }

        // Check percentage rollout
        if ($user && self::isInRolloutPercentage($feature, $user)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user is a pilot user.
     */
    public static function isPilotUser(User $user): bool
    {
        $pilotIds = config('features.pilot_user_ids', []);
        return in_array($user->id, $pilotIds);
    }

    /**
     * Check if user is in rollout percentage.
     */
    public static function isInRolloutPercentage(string $feature, User $user): bool
    {
        $percentage = config("features.rollout_percentage.{$feature}", 0);

        if ($percentage <= 0) {
            return false;
        }

        // Deterministic bucket based on user ID
        $bucket = $user->id % 100;

        return $bucket < $percentage;
    }

    /**
     * Get all enabled features for a user.
     */
    public static function getEnabledFeatures(?User $user = null): array
    {
        $features = [
            'enrollment_service',
            'progress_service',
            'grading_service',
            'course_state_machine',
            'enrollment_state_machine',
            'attempt_state_machine',
            'domain_events',
            'grading_strategies',
        ];

        return array_filter($features, fn($f) => self::isEnabled($f, $user));
    }
}
```

### Usage in Controllers

```php
<?php
// Example: EnrollmentController with feature flag

use App\Domain\Shared\Services\FeatureFlag;
use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;

class EnrollmentController extends Controller
{
    public function store(Request $request, Course $course)
    {
        if (FeatureFlag::isEnabled('enrollment_service')) {
            // New architecture
            return $this->enrollWithService($request, $course);
        }

        // Legacy implementation
        return $this->enrollLegacy($request, $course);
    }

    protected function enrollWithService(Request $request, Course $course)
    {
        $service = app(EnrollmentServiceContract::class);

        $dto = new CreateEnrollmentDTO(
            userId: $request->user()->id,
            courseId: $course->id,
        );

        $result = $service->enroll($dto);

        return redirect()->route('learn.course', $course)
            ->with('success', 'Berhasil mendaftar ke kursus!');
    }

    protected function enrollLegacy(Request $request, Course $course)
    {
        // Existing implementation
        Enrollment::create([...]);

        return redirect()->route('learn.course', $course)
            ->with('success', 'Berhasil mendaftar ke kursus!');
    }
}
```

---

## 8.3 Database Migration Safety

### Pre-Migration Checklist

```markdown
## Before Running Migrations

- [ ] Database backup completed
- [ ] Backup verified (can restore)
- [ ] Maintenance window scheduled
- [ ] Team notified
- [ ] Rollback script prepared
- [ ] Monitoring dashboard open
```

### Safe Migration Practices

```php
<?php
// Example: Adding new column with default value

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add column as nullable (non-blocking)
        Schema::table('courses', function (Blueprint $table) {
            $table->string('status_new')->nullable()->after('status');
        });

        // Step 2: Backfill data (can be done separately for large tables)
        // DB::statement('UPDATE courses SET status_new = status');

        // Step 3: Make non-nullable (separate migration after verification)
        // $table->string('status_new')->nullable(false)->change();

        // Step 4: Drop old column (separate migration after verification)
        // $table->dropColumn('status');
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('status_new');
        });
    }
};
```

### Large Table Migrations

```php
<?php
// For large tables, use batch processing

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected int $batchSize = 1000;

    public function up(): void
    {
        $total = DB::table('enrollments')->count();
        $batches = ceil($total / $this->batchSize);

        for ($i = 0; $i < $batches; $i++) {
            DB::table('enrollments')
                ->orderBy('id')
                ->offset($i * $this->batchSize)
                ->limit($this->batchSize)
                ->update([
                    'status_new' => DB::raw('status'),
                ]);

            // Allow database to breathe
            usleep(100000); // 100ms
        }
    }
};
```

---

## 8.4 Phase-by-Phase Rollout

### Phase 1: Foundation (Week 1-2)

**Deployment Risk**: Low
**User Impact**: None
**Rollout**: Immediate

```bash
# No feature flags needed
# Just deploy new directory structure and base classes
php artisan migrate
```

**Validation**:
```bash
php artisan test --testsuite=Unit
```

### Phase 2: Service Layer (Week 3-5)

**Deployment Risk**: Medium
**User Impact**: Potential behavior differences
**Rollout**: Gradual with feature flags

**Week 3: Deploy services (disabled)**
```env
FEATURE_ENROLLMENT_SERVICE=false
FEATURE_PROGRESS_SERVICE=false
```

**Week 4: Enable for pilot users**
```env
FEATURE_PILOT_USERS=1,2,3,4,5
```

**Week 5: Gradual rollout**
```env
ROLLOUT_ENROLLMENT_SERVICE=10  # 10% of users
ROLLOUT_PROGRESS_SERVICE=10
```

**Week 5+: Full rollout (if no issues)**
```env
FEATURE_ENROLLMENT_SERVICE=true
FEATURE_PROGRESS_SERVICE=true
```

### Phase 3: State Machines (Week 6-7)

**Deployment Risk**: Medium-High
**User Impact**: State transition validation
**Rollout**: Shadow mode first

**Week 6: Shadow mode**
```php
// Run both old and new, compare results, log differences
if (FeatureFlag::isEnabled('course_state_machine')) {
    try {
        $newResult = $this->publishWithStateMachine($course);
    } catch (\Exception $e) {
        Log::error('State machine error', ['error' => $e->getMessage()]);
    }
}

// Always use old implementation
$result = $this->publishLegacy($course);

// Compare in shadow mode
if (isset($newResult)) {
    $this->compareResults($result, $newResult);
}
```

**Week 7: Enable after validation**
```env
FEATURE_COURSE_STATE_MACHINE=true
FEATURE_ENROLLMENT_STATE_MACHINE=true
```

### Phase 4: Events (Week 8-9)

**Deployment Risk**: Low-Medium
**User Impact**: None (side effects only)
**Rollout**: Canary

**Week 8: Deploy with events disabled**
```env
FEATURE_DOMAIN_EVENTS=false
```

**Week 8: Enable events (listeners may fail safely)**
```env
FEATURE_DOMAIN_EVENTS=true
```

**Monitor**: Check queue for failed jobs, event log for errors

### Phase 5: Strategy Patterns (Week 10-11)

**Deployment Risk**: Low
**User Impact**: Grading results may differ
**Rollout**: A/B test

```env
FEATURE_GRADING_STRATEGIES=false
ROLLOUT_GRADING_SERVICE=10
```

**Validation**: Compare grading results for same inputs

### Phase 6: Observability (Week 12)

**Deployment Risk**: Low
**User Impact**: None
**Rollout**: Immediate

```bash
php artisan migrate  # Event log tables
```

---

## 8.5 Rollback Procedures

### Service Layer Rollback

```bash
# Disable feature flags
sed -i 's/FEATURE_ENROLLMENT_SERVICE=true/FEATURE_ENROLLMENT_SERVICE=false/' .env
sed -i 's/FEATURE_PROGRESS_SERVICE=true/FEATURE_PROGRESS_SERVICE=false/' .env

# Clear cache
php artisan config:clear
php artisan cache:clear

# Verify
php artisan tinker --execute="echo config('features.use_enrollment_service') ? 'ENABLED' : 'DISABLED';"
```

### State Machine Rollback

```bash
# Disable state machines
sed -i 's/FEATURE_COURSE_STATE_MACHINE=true/FEATURE_COURSE_STATE_MACHINE=false/' .env

# Clear cache
php artisan config:clear

# Verify status column still works
php artisan tinker --execute="echo App\Models\Course::first()->status;"
```

### Database Rollback

```bash
# Rollback last migration
php artisan migrate:rollback --step=1

# If multiple migrations
php artisan migrate:rollback --step=5

# If disaster
php artisan migrate:reset
mysql -u root -p database < backup.sql
php artisan migrate
```

### Emergency Rollback Script

```bash
#!/bin/bash
# emergency-rollback.sh

echo "EMERGENCY ROLLBACK INITIATED"
echo "================================"

# Step 1: Disable all feature flags
echo "Disabling feature flags..."
cat > .env.rollback << 'EOF'
FEATURE_ENROLLMENT_SERVICE=false
FEATURE_PROGRESS_SERVICE=false
FEATURE_GRADING_SERVICE=false
FEATURE_COURSE_STATE_MACHINE=false
FEATURE_ENROLLMENT_STATE_MACHINE=false
FEATURE_ATTEMPT_STATE_MACHINE=false
FEATURE_DOMAIN_EVENTS=false
FEATURE_GRADING_STRATEGIES=false
EOF

# Merge rollback env
cat .env.rollback >> .env

# Step 2: Clear all caches
echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Step 3: Restart queues
echo "Restarting queue workers..."
php artisan queue:restart

# Step 4: Verify
echo "Verifying rollback..."
php artisan about

echo "================================"
echo "ROLLBACK COMPLETE"
echo "Check application health: curl -s http://localhost/api/health/check"
```

---

## 8.6 Monitoring During Rollout

### Key Metrics to Watch

```php
<?php
// app/Console/Commands/MonitorRollout.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MonitorRollout extends Command
{
    protected $signature = 'rollout:monitor';
    protected $description = 'Monitor rollout health metrics';

    public function handle(): void
    {
        $this->info('Rollout Health Monitor');
        $this->info('======================');

        // Error rates
        $errorCount = DB::table('domain_event_log')
            ->where('event_name', 'like', '%.failed')
            ->where('occurred_at', '>=', now()->subHour())
            ->count();

        $this->line("Errors (last hour): {$errorCount}");

        // State transitions
        $transitions = DB::table('state_transitions')
            ->where('transitioned_at', '>=', now()->subHour())
            ->count();

        $this->line("State transitions (last hour): {$transitions}");

        // Failed jobs
        $failedJobs = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subHour())
            ->count();

        $this->line("Failed jobs (last hour): {$failedJobs}");

        // Feature flag status
        $this->newLine();
        $this->info('Feature Flags:');
        foreach (config('features') as $key => $value) {
            if (is_bool($value)) {
                $status = $value ? '✓ ON' : '✗ OFF';
                $this->line("  {$key}: {$status}");
            }
        }

        // Recommendations
        if ($errorCount > 10 || $failedJobs > 5) {
            $this->newLine();
            $this->error('⚠ HIGH ERROR RATE DETECTED');
            $this->error('Consider rolling back: ./emergency-rollback.sh');
        } else {
            $this->newLine();
            $this->info('✓ All metrics within normal range');
        }
    }
}
```

### Health Check During Rollout

```bash
# Run every 5 minutes during rollout
watch -n 300 "curl -s http://localhost/api/health/check | jq ."
```

### Log Monitoring

```bash
# Watch for errors in real-time
tail -f storage/logs/laravel.log | grep -E "(error|exception|failed)"

# Watch domain events
tail -f storage/logs/events.log

# Watch state transitions
tail -f storage/logs/domain.log | grep "state"
```

---

## 8.7 Communication Plan

### Pre-Rollout Communication

```markdown
# Email to Team

Subject: LMS Backend Architecture Upgrade - Phase X Starting

Team,

We're beginning Phase X of the backend architecture upgrade.

## What's Changing
- [Brief description of changes]

## Timeline
- Start: [Date/Time]
- Expected completion: [Date/Time]

## User Impact
- [None / Minimal / Moderate]

## If You Notice Issues
1. Check #incidents channel
2. Run: php artisan rollout:monitor
3. If critical: ./emergency-rollback.sh

Questions? Reach out to [contact].
```

### User Communication (if needed)

```markdown
# In-App Notification

We're making improvements to our learning platform.

You may notice:
- Faster page loads
- Improved progress tracking

No action required on your part.
```

---

## 8.8 Post-Rollout Cleanup

### Remove Legacy Code

After successful rollout (2+ weeks stable):

```bash
# 1. Remove feature flag checks
grep -r "FeatureFlag::isEnabled" app/ --include="*.php"

# 2. Remove legacy methods
# Review each file, remove methods like:
# - enrollLegacy()
# - publishLegacy()
# - Old inline logic in controllers

# 3. Remove feature flag configuration
# config/features.php - simplify or remove

# 4. Update tests
# Remove tests for legacy paths
```

### Documentation Update

- [ ] Update README with new architecture
- [ ] Update API documentation
- [ ] Update deployment guides
- [ ] Archive old documentation

---

## 8.9 Implementation Checklist

### Pre-Rollout

- [ ] All phases implemented in staging
- [ ] Full test suite passing
- [ ] Performance benchmarks captured
- [ ] Rollback procedures tested
- [ ] Team briefed
- [ ] Monitoring configured
- [ ] Backup verified

### During Rollout

- [ ] Feature flags configured
- [ ] Pilot users identified
- [ ] Monitoring active
- [ ] Communication sent
- [ ] Support team on standby

### Post-Rollout

- [ ] Metrics validated
- [ ] No increase in errors
- [ ] Performance maintained
- [ ] User feedback collected
- [ ] Legacy code scheduled for removal
- [ ] Documentation updated

---

## 8.10 Success Criteria

### Technical Success

| Metric | Target | Measured |
|--------|--------|----------|
| Test coverage | 80%+ | |
| Error rate | < 0.1% | |
| Response time | No regression | |
| Failed jobs | < 1/hour | |
| State transition errors | 0 | |

### Business Success

| Metric | Target | Measured |
|--------|--------|----------|
| User complaints | 0 increase | |
| Support tickets | No increase | |
| Feature development speed | 20% faster | |
| Bug fix time | 30% faster | |

---

## Conclusion

This migration guide ensures the refactored architecture is deployed safely to production. By using feature flags, gradual rollout, and comprehensive monitoring, we can achieve the benefits of the new architecture while minimizing risk to users.

The key to success:
1. **Test thoroughly** before production
2. **Roll out gradually** with feature flags
3. **Monitor constantly** during deployment
4. **Rollback quickly** if issues arise
5. **Clean up** legacy code after stabilization

Good luck with your refactoring journey!
