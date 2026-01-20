# Phase 5: Auto-Enrollment

> **Phase**: 5 of 6
> **Estimated Effort**: Medium
> **Prerequisites**: Phase 4 (Learner Experience)
> **Priority**: Low (Enhancement)

---

## Objectives

- Create auto-enrollment rules engine
- Support role-based enrollment rules
- Support department-based enrollment rules
- Implement scheduled enrollment checks
- Create admin UI for managing rules

---

## 5.1 Database Migration

### File: `database/migrations/2026_01_20_000003_create_learning_path_auto_enrollment_rules_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_path_auto_enrollment_rules', function (Blueprint $table) {
            $table->id();

            // Which learning path this rule applies to
            $table->foreignId('learning_path_id')
                ->constrained('learning_paths')
                ->cascadeOnDelete();

            // Rule name and description
            $table->string('name');
            $table->text('description')->nullable();

            // Rule type: role, department, all_new_users, custom
            $table->string('rule_type');

            // Rule conditions (JSON)
            // Examples:
            // { "roles": ["teller", "customer_service"] }
            // { "departments": ["compliance", "operations"] }
            // { "roles": ["learner"], "departments": ["branch_a"] }
            $table->json('conditions');

            // When to apply: on_create, on_update, scheduled
            $table->string('trigger_type')->default('on_create');

            // Is the rule active?
            $table->boolean('is_active')->default(true);

            // Priority (lower number = higher priority)
            $table->unsignedSmallInteger('priority')->default(100);

            // Who created this rule
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            // Indexes
            $table->index(['learning_path_id', 'is_active']);
            $table->index('rule_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_path_auto_enrollment_rules');
    }
};
```

---

## 5.2 Model: AutoEnrollmentRule

### File: `app/Models/LearningPathAutoEnrollmentRule.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningPathAutoEnrollmentRule extends Model
{
    use HasFactory;

    protected $table = 'learning_path_auto_enrollment_rules';

    protected $fillable = [
        'learning_path_id',
        'name',
        'description',
        'rule_type',
        'conditions',
        'trigger_type',
        'is_active',
        'priority',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTrigger($query, string $trigger)
    {
        return $query->where('trigger_type', $trigger);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority');
    }

    // ─────────────────────────────────────────────────────────────
    // Methods
    // ─────────────────────────────────────────────────────────────

    /**
     * Check if a user matches this rule's conditions.
     */
    public function matchesUser(User $user): bool
    {
        $conditions = $this->conditions;

        // Check roles
        if (isset($conditions['roles']) && !empty($conditions['roles'])) {
            if (!in_array($user->role, $conditions['roles'])) {
                return false;
            }
        }

        // Check departments (if user has department)
        if (isset($conditions['departments']) && !empty($conditions['departments'])) {
            $userDepartment = $user->department ?? $user->profile?->department ?? null;
            if (!$userDepartment || !in_array($userDepartment, $conditions['departments'])) {
                return false;
            }
        }

        // Check custom conditions
        if (isset($conditions['custom'])) {
            return $this->evaluateCustomConditions($user, $conditions['custom']);
        }

        return true;
    }

    /**
     * Evaluate custom conditions (extensible).
     */
    private function evaluateCustomConditions(User $user, array $custom): bool
    {
        // Example custom conditions:
        // { "joined_after": "2024-01-01" }
        // { "has_completed_course": 5 }

        foreach ($custom as $condition => $value) {
            switch ($condition) {
                case 'joined_after':
                    if ($user->created_at < $value) {
                        return false;
                    }
                    break;

                case 'has_completed_course':
                    $completed = $user->enrollments()
                        ->where('course_id', $value)
                        ->where('status', 'completed')
                        ->exists();
                    if (!$completed) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }
}
```

---

## 5.3 Auto-Enrollment Service

### File: `app/Domain/LearningPath/Services/AutoEnrollmentService.php`

```php
<?php

namespace App\Domain\LearningPath\Services;

use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Models\LearningPathAutoEnrollmentRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AutoEnrollmentService
{
    public function __construct(
        private PathEnrollmentServiceContract $enrollmentService,
    ) {}

    /**
     * Process auto-enrollment for a user.
     * Called when user is created or updated.
     */
    public function processForUser(User $user, string $trigger = 'on_create'): array
    {
        $enrolledPaths = [];

        // Get all active rules for this trigger
        $rules = LearningPathAutoEnrollmentRule::active()
            ->forTrigger($trigger)
            ->byPriority()
            ->with('learningPath')
            ->get();

        foreach ($rules as $rule) {
            // Skip if path is not published
            if (!$rule->learningPath->is_published) {
                continue;
            }

            // Skip if user is already enrolled
            if ($this->enrollmentService->isEnrolled($user, $rule->learningPath)) {
                continue;
            }

            // Check if user matches the rule
            if ($rule->matchesUser($user)) {
                try {
                    $this->enrollmentService->enroll($user, $rule->learningPath);
                    $enrolledPaths[] = $rule->learningPath;

                    Log::info('Auto-enrolled user in learning path', [
                        'user_id' => $user->id,
                        'learning_path_id' => $rule->learningPath->id,
                        'rule_id' => $rule->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to auto-enroll user', [
                        'user_id' => $user->id,
                        'learning_path_id' => $rule->learningPath->id,
                        'rule_id' => $rule->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $enrolledPaths;
    }

    /**
     * Process all users for a specific rule.
     * Used for batch enrollment when a new rule is created.
     */
    public function processRuleForAllUsers(LearningPathAutoEnrollmentRule $rule): int
    {
        $enrolledCount = 0;

        if (!$rule->is_active || !$rule->learningPath->is_published) {
            return 0;
        }

        // Get users who match the rule and are not enrolled
        $users = $this->getUsersMatchingRule($rule);

        foreach ($users as $user) {
            if ($this->enrollmentService->isEnrolled($user, $rule->learningPath)) {
                continue;
            }

            try {
                $this->enrollmentService->enroll($user, $rule->learningPath);
                $enrolledCount++;

                Log::info('Batch auto-enrolled user in learning path', [
                    'user_id' => $user->id,
                    'learning_path_id' => $rule->learningPath->id,
                    'rule_id' => $rule->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to batch auto-enroll user', [
                    'user_id' => $user->id,
                    'learning_path_id' => $rule->learningPath->id,
                    'rule_id' => $rule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $enrolledCount;
    }

    /**
     * Get users matching a rule's conditions.
     */
    private function getUsersMatchingRule(LearningPathAutoEnrollmentRule $rule): Collection
    {
        $query = User::query();
        $conditions = $rule->conditions;

        // Filter by roles
        if (isset($conditions['roles']) && !empty($conditions['roles'])) {
            $query->whereIn('role', $conditions['roles']);
        }

        // Filter by departments (if applicable)
        if (isset($conditions['departments']) && !empty($conditions['departments'])) {
            // Adjust this based on your user model structure
            $query->where(function ($q) use ($conditions) {
                $q->whereIn('department', $conditions['departments'])
                    ->orWhereHas('profile', function ($pq) use ($conditions) {
                        $pq->whereIn('department', $conditions['departments']);
                    });
            });
        }

        return $query->get();
    }

    /**
     * Get matching rules for a user (for preview/testing).
     */
    public function getMatchingRules(User $user): Collection
    {
        return LearningPathAutoEnrollmentRule::active()
            ->byPriority()
            ->with('learningPath')
            ->get()
            ->filter(fn ($rule) => $rule->matchesUser($user));
    }
}
```

---

## 5.4 Event Listener for User Creation

### File: `app/Domain/LearningPath/Listeners/AutoEnrollNewUser.php`

```php
<?php

namespace App\Domain\LearningPath\Listeners;

use App\Domain\LearningPath\Services\AutoEnrollmentService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;

class AutoEnrollNewUser implements ShouldQueue
{
    public function __construct(
        private AutoEnrollmentService $autoEnrollmentService,
    ) {}

    public function handle(Registered $event): void
    {
        $this->autoEnrollmentService->processForUser($event->user, 'on_create');
    }
}
```

### Register in EventServiceProvider

```php
// In app/Providers/EventServiceProvider.php

use Illuminate\Auth\Events\Registered;
use App\Domain\LearningPath\Listeners\AutoEnrollNewUser;

protected $listen = [
    Registered::class => [
        // ... existing listeners
        AutoEnrollNewUser::class,
    ],
];
```

---

## 5.5 Scheduled Command for Periodic Enrollment

### File: `app/Console/Commands/ProcessAutoEnrollmentRules.php`

```php
<?php

namespace App\Console\Commands;

use App\Domain\LearningPath\Services\AutoEnrollmentService;
use App\Models\LearningPathAutoEnrollmentRule;
use App\Models\User;
use Illuminate\Console\Command;

class ProcessAutoEnrollmentRules extends Command
{
    protected $signature = 'learning-paths:process-auto-enrollment
                            {--rule= : Process specific rule ID}
                            {--dry-run : Preview without enrolling}';

    protected $description = 'Process scheduled auto-enrollment rules';

    public function __construct(
        private AutoEnrollmentService $autoEnrollmentService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $ruleId = $this->option('rule');
        $dryRun = $this->option('dry-run');

        if ($ruleId) {
            $rules = LearningPathAutoEnrollmentRule::where('id', $ruleId)->get();
        } else {
            $rules = LearningPathAutoEnrollmentRule::active()
                ->forTrigger('scheduled')
                ->byPriority()
                ->get();
        }

        if ($rules->isEmpty()) {
            $this->info('No rules to process.');
            return Command::SUCCESS;
        }

        $this->info("Processing {$rules->count()} auto-enrollment rule(s)...");

        foreach ($rules as $rule) {
            $this->line("Processing rule: {$rule->name}");

            if ($dryRun) {
                $matchingUsers = User::all()->filter(fn ($u) => $rule->matchesUser($u));
                $this->line("  Would enroll {$matchingUsers->count()} users (dry run)");
            } else {
                $enrolled = $this->autoEnrollmentService->processRuleForAllUsers($rule);
                $this->line("  Enrolled {$enrolled} users");
            }
        }

        $this->info('Done.');
        return Command::SUCCESS;
    }
}
```

### Schedule in `routes/console.php`

```php
// In routes/console.php

use Illuminate\Support\Facades\Schedule;

Schedule::command('learning-paths:process-auto-enrollment')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping();
```

---

## 5.6 Admin Controller for Rules

### File: `app/Http/Controllers/Admin/AutoEnrollmentRuleController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domain\LearningPath\Services\AutoEnrollmentService;
use App\Http\Controllers\Controller;
use App\Models\LearningPath;
use App\Models\LearningPathAutoEnrollmentRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AutoEnrollmentRuleController extends Controller
{
    public function __construct(
        private AutoEnrollmentService $autoEnrollmentService,
    ) {}

    /**
     * List all auto-enrollment rules.
     */
    public function index(Request $request): Response
    {
        $rules = LearningPathAutoEnrollmentRule::with(['learningPath', 'creator'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->byPriority()
            ->paginate(15);

        return Inertia::render('admin/auto-enrollment/Index', [
            'rules' => $rules,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show create form.
     */
    public function create(): Response
    {
        $learningPaths = LearningPath::published()
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('admin/auto-enrollment/Create', [
            'learningPaths' => $learningPaths,
            'ruleTypes' => $this->getRuleTypes(),
            'triggerTypes' => $this->getTriggerTypes(),
        ]);
    }

    /**
     * Store new rule.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'learning_path_id' => 'required|exists:learning_paths,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rule_type' => 'required|in:role,department,combined,all_new_users',
            'conditions' => 'required|array',
            'conditions.roles' => 'nullable|array',
            'conditions.departments' => 'nullable|array',
            'trigger_type' => 'required|in:on_create,on_update,scheduled',
            'is_active' => 'boolean',
            'priority' => 'integer|min:1|max:1000',
        ]);

        $rule = LearningPathAutoEnrollmentRule::create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.auto-enrollment.index')
            ->with('success', "Aturan \"{$rule->name}\" berhasil dibuat.");
    }

    /**
     * Show edit form.
     */
    public function edit(LearningPathAutoEnrollmentRule $rule): Response
    {
        $learningPaths = LearningPath::published()
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('admin/auto-enrollment/Edit', [
            'rule' => $rule->load('learningPath'),
            'learningPaths' => $learningPaths,
            'ruleTypes' => $this->getRuleTypes(),
            'triggerTypes' => $this->getTriggerTypes(),
        ]);
    }

    /**
     * Update rule.
     */
    public function update(Request $request, LearningPathAutoEnrollmentRule $rule): RedirectResponse
    {
        $validated = $request->validate([
            'learning_path_id' => 'required|exists:learning_paths,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rule_type' => 'required|in:role,department,combined,all_new_users',
            'conditions' => 'required|array',
            'trigger_type' => 'required|in:on_create,on_update,scheduled',
            'is_active' => 'boolean',
            'priority' => 'integer|min:1|max:1000',
        ]);

        $rule->update($validated);

        return redirect()
            ->route('admin.auto-enrollment.index')
            ->with('success', "Aturan \"{$rule->name}\" berhasil diperbarui.");
    }

    /**
     * Delete rule.
     */
    public function destroy(LearningPathAutoEnrollmentRule $rule): RedirectResponse
    {
        $name = $rule->name;
        $rule->delete();

        return redirect()
            ->route('admin.auto-enrollment.index')
            ->with('success', "Aturan \"{$name}\" berhasil dihapus.");
    }

    /**
     * Toggle rule active status.
     */
    public function toggle(LearningPathAutoEnrollmentRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => !$rule->is_active]);

        $status = $rule->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->back()
            ->with('success', "Aturan \"{$rule->name}\" berhasil {$status}.");
    }

    /**
     * Execute rule immediately (batch enrollment).
     */
    public function execute(LearningPathAutoEnrollmentRule $rule): RedirectResponse
    {
        $enrolled = $this->autoEnrollmentService->processRuleForAllUsers($rule);

        return redirect()
            ->back()
            ->with('success', "{$enrolled} pengguna berhasil didaftarkan ke \"{$rule->learningPath->title}\".");
    }

    private function getRuleTypes(): array
    {
        return [
            ['value' => 'role', 'label' => 'Berdasarkan Role'],
            ['value' => 'department', 'label' => 'Berdasarkan Department'],
            ['value' => 'combined', 'label' => 'Role + Department'],
            ['value' => 'all_new_users', 'label' => 'Semua Pengguna Baru'],
        ];
    }

    private function getTriggerTypes(): array
    {
        return [
            ['value' => 'on_create', 'label' => 'Saat Pengguna Dibuat'],
            ['value' => 'on_update', 'label' => 'Saat Pengguna Diperbarui'],
            ['value' => 'scheduled', 'label' => 'Terjadwal (Harian)'],
        ];
    }
}
```

---

## 5.7 Routes for Admin

### File: `routes/admin_auto_enrollment.php`

```php
<?php

use App\Http\Controllers\Admin\AutoEnrollmentRuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:lms_admin'])
    ->prefix('admin/auto-enrollment')
    ->name('admin.auto-enrollment.')
    ->group(function () {
        Route::get('/', [AutoEnrollmentRuleController::class, 'index'])->name('index');
        Route::get('/create', [AutoEnrollmentRuleController::class, 'create'])->name('create');
        Route::post('/', [AutoEnrollmentRuleController::class, 'store'])->name('store');
        Route::get('/{rule}/edit', [AutoEnrollmentRuleController::class, 'edit'])->name('edit');
        Route::put('/{rule}', [AutoEnrollmentRuleController::class, 'update'])->name('update');
        Route::delete('/{rule}', [AutoEnrollmentRuleController::class, 'destroy'])->name('destroy');
        Route::put('/{rule}/toggle', [AutoEnrollmentRuleController::class, 'toggle'])->name('toggle');
        Route::post('/{rule}/execute', [AutoEnrollmentRuleController::class, 'execute'])->name('execute');
    });
```

---

## 5.8 Example Auto-Enrollment Rules

### Rule 1: All Tellers Must Take Compliance Training

```json
{
    "name": "Teller Compliance Training",
    "learning_path_id": 1,
    "rule_type": "role",
    "conditions": {
        "roles": ["teller"]
    },
    "trigger_type": "on_create",
    "is_active": true,
    "priority": 10
}
```

### Rule 2: All New Employees Take Onboarding

```json
{
    "name": "New Employee Onboarding",
    "learning_path_id": 2,
    "rule_type": "all_new_users",
    "conditions": {},
    "trigger_type": "on_create",
    "is_active": true,
    "priority": 1
}
```

### Rule 3: Branch A Compliance (Scheduled)

```json
{
    "name": "Branch A AML Training",
    "learning_path_id": 3,
    "rule_type": "combined",
    "conditions": {
        "roles": ["learner", "teller", "customer_service"],
        "departments": ["branch_a"]
    },
    "trigger_type": "scheduled",
    "is_active": true,
    "priority": 50
}
```

---

## Implementation Checklist

- [ ] Create migration for `learning_path_auto_enrollment_rules`
- [ ] Run migration
- [ ] Create `LearningPathAutoEnrollmentRule` model
- [ ] Create `AutoEnrollmentService`
- [ ] Create `AutoEnrollNewUser` listener
- [ ] Register listener in EventServiceProvider
- [ ] Create `ProcessAutoEnrollmentRules` command
- [ ] Schedule command in `routes/console.php`
- [ ] Create `AutoEnrollmentRuleController`
- [ ] Create admin routes
- [ ] Create admin UI pages (Index, Create, Edit)
- [ ] Write tests for auto-enrollment logic

---

## Next Phase

Continue to [Phase 6: Test Plan](./06-TEST-PLAN.md)
