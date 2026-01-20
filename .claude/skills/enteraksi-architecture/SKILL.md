---
name: enteraksi-architecture
description: Domain-Driven Design patterns, service layer, contracts, DTOs, value objects for Enteraksi LMS. Use when creating services, DTOs, value objects, or working with domain layer code.
triggers:
  - create service
  - new service
  - domain service
  - create dto
  - data transfer object
  - value object
  - create value object
  - domain layer
  - bounded context
  - service contract
  - interface
  - DomainServiceProvider
---

# Enteraksi DDD Architecture

## When to Use This Skill

- Creating a new domain service with contract interface
- Creating Data Transfer Objects (DTOs) for data passing
- Creating Value Objects for domain concepts (scores, percentages)
- Registering services in DomainServiceProvider
- Understanding the bounded context structure

## Directory Structure

```
app/Domain/
├── {BoundedContext}/
│   ├── Contracts/         # Service interfaces
│   ├── DTOs/              # Data Transfer Objects
│   ├── Events/            # Domain events
│   ├── Exceptions/        # Domain-specific exceptions
│   ├── Listeners/         # Event listeners
│   ├── Notifications/     # Mail/notification classes
│   ├── Services/          # Service implementations
│   ├── States/            # State machine classes
│   ├── Strategies/        # Strategy pattern implementations
│   └── ValueObjects/      # Value objects
└── Shared/                # Cross-cutting concerns
    ├── Contracts/         # Base contracts (DomainEvent)
    ├── DTOs/              # Base DTO class
    ├── Exceptions/        # Shared exceptions
    ├── Listeners/         # Shared listeners
    ├── Services/          # Observability, logging
    └── ValueObjects/      # Shared value objects
```

## Bounded Contexts

| Context | Purpose |
|---------|---------|
| Assessment | Quizzes, grading, attempts |
| Course | Course content, states |
| Enrollment | User enrollments, lifecycle |
| LearningPath | Learning paths, prerequisites |
| Progress | Lesson progress, completion |
| Shared | Cross-cutting concerns |

## Key Patterns

### 1. Service Contract + Implementation

**Contract (Interface):**
```php
// app/Domain/Enrollment/Contracts/EnrollmentServiceContract.php
namespace App\Domain\Enrollment\Contracts;

use App\Domain\Enrollment\DTOs\CreateEnrollmentDTO;
use App\Domain\Enrollment\DTOs\EnrollmentResult;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

interface EnrollmentServiceContract
{
    public function enroll(CreateEnrollmentDTO $dto): EnrollmentResult;
    public function canEnroll(User $user, Course $course): bool;
    public function getActiveEnrollment(User $user, Course $course): ?Enrollment;
    public function drop(Enrollment $enrollment, ?string $reason = null): void;
    public function complete(Enrollment $enrollment): void;
}
```

**Implementation:**
```php
// app/Domain/Enrollment/Services/EnrollmentService.php
namespace App\Domain\Enrollment\Services;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Enrollment\DTOs\CreateEnrollmentDTO;
use App\Domain\Enrollment\DTOs\EnrollmentResult;
use App\Domain\Enrollment\Events\UserEnrolled;
use Illuminate\Support\Facades\DB;

class EnrollmentService implements EnrollmentServiceContract
{
    public function enroll(CreateEnrollmentDTO $dto): EnrollmentResult
    {
        return DB::transaction(function () use ($dto) {
            $enrollment = Enrollment::create([
                'user_id' => $dto->userId,
                'course_id' => $dto->courseId,
                'status' => ActiveState::$name,
                // ...
            ]);

            UserEnrolled::dispatch($enrollment);

            return new EnrollmentResult(
                enrollment: $enrollment,
                isNewEnrollment: true,
            );
        });
    }
}
```

### 2. Data Transfer Object (DTO)

**Base DTO Class:**
```php
// app/Domain/Shared/DTOs/DataTransferObject.php
namespace App\Domain\Shared\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;

abstract class DataTransferObject implements Arrayable
{
    abstract public static function fromArray(array $data): static;

    public static function fromRequest(Request $request): static
    {
        return static::fromArray($request->validated());
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
```

**Concrete DTO:**
```php
// app/Domain/Enrollment/DTOs/CreateEnrollmentDTO.php
namespace App\Domain\Enrollment\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use DateTimeImmutable;
use DateTimeInterface;

final class CreateEnrollmentDTO extends DataTransferObject
{
    public function __construct(
        public int $userId,
        public int $courseId,
        public ?int $invitedBy = null,
        public ?DateTimeInterface $enrolledAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new self(
            userId: $data['user_id'],
            courseId: $data['course_id'],
            invitedBy: $data['invited_by'] ?? null,
            enrolledAt: isset($data['enrolled_at'])
                ? new DateTimeImmutable($data['enrolled_at'])
                : null,
        );
    }
}
```

### 3. Result DTO

```php
// app/Domain/Enrollment/DTOs/EnrollmentResult.php
namespace App\Domain\Enrollment\DTOs;

use App\Models\Enrollment;

final readonly class EnrollmentResult
{
    public function __construct(
        public Enrollment $enrollment,
        public bool $isNewEnrollment,
        public ?string $message = null,
    ) {}
}
```

### 4. Value Object

**With Validation in Constructor:**
```php
// app/Domain/Shared/ValueObjects/Percentage.php
namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class Percentage implements JsonSerializable, Stringable
{
    public function __construct(public float $value)
    {
        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException(
                "Percentage must be between 0 and 100, got: {$value}"
            );
        }
    }

    public static function fromFraction(float $numerator, float $denominator): self
    {
        if ($denominator === 0.0) {
            return new self(0);
        }
        return new self(round(($numerator / $denominator) * 100, 2));
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function full(): self
    {
        return new self(100);
    }

    public function isComplete(): bool
    {
        return $this->value >= 100;
    }

    public function toFraction(): float
    {
        return $this->value / 100;
    }

    public function format(int $decimals = 1): string
    {
        return number_format($this->value, $decimals) . '%';
    }

    public function jsonSerialize(): float
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
```

### 5. DomainServiceProvider Registration

```php
// app/Providers/DomainServiceProvider.php
namespace App\Providers;

use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Enrollment\Services\EnrollmentService;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Contract bindings (interface => implementation).
     */
    public array $bindings = [
        EnrollmentServiceContract::class => EnrollmentService::class,
    ];

    public function register(): void
    {
        $this->registerGradingStrategies();
        $this->registerProgressCalculators();
    }

    protected function registerGradingStrategies(): void
    {
        // Tag strategies for resolver
        $this->app->tag([
            MultipleChoiceGradingStrategy::class,
            TrueFalseGradingStrategy::class,
        ], 'grading.strategies');

        // Singleton resolver with tagged strategies
        $this->app->singleton(GradingStrategyResolverContract::class, function ($app) {
            return new GradingStrategyResolver($app->tagged('grading.strategies'));
        });
    }
}
```

## Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Contract | `{Name}Contract` | `EnrollmentServiceContract` |
| Service | `{Name}Service` | `EnrollmentService` |
| DTO | `{Action}{Entity}DTO` or `{Name}Result` | `CreateEnrollmentDTO`, `EnrollmentResult` |
| Value Object | `{Concept}` | `Percentage`, `Score`, `Duration` |
| Exception | `{Description}Exception` | `AlreadyEnrolledException` |

## Gotchas & Best Practices

1. **Always use DB::transaction()** for operations that dispatch events
2. **DTOs should be final** - no inheritance, simple data containers
3. **Value objects should be readonly** - immutable after construction
4. **Validate in value object constructor** - fail fast
5. **Return Result DTOs** from service methods, not void when data is needed
6. **Use static factory methods** - `fromArray()`, `fromRequest()`, `zero()`, `full()`

## Quick Reference

```bash
# Create new bounded context structure
mkdir -p app/Domain/{Context}/{Contracts,DTOs,Events,Exceptions,Services,States,Strategies}

# Files to reference
app/Providers/DomainServiceProvider.php           # Registration patterns
app/Domain/Shared/DTOs/DataTransferObject.php     # Base DTO class
app/Domain/Shared/ValueObjects/Percentage.php     # Value object example
app/Domain/Enrollment/Services/EnrollmentService.php  # Service example
```
