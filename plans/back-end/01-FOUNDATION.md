# Phase 1: Foundation & Infrastructure

**Duration**: Week 1-2
**Priority**: Critical - All other phases depend on this

---

## Objectives

1. Establish directory structure for new architecture
2. Create base contracts (interfaces) for core patterns
3. Set up exception hierarchy for better error handling
4. Introduce DTOs and Value Objects
5. Configure service container bindings

---

## 1.1 Directory Structure

### Current Structure (Flat)
```
app/
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Models/
├── Policies/
├── Providers/
└── Services/  (only 2 files)
```

### Target Structure (Domain-Organized)
```
app/
├── Domain/
│   ├── Course/
│   │   ├── Actions/
│   │   ├── Contracts/
│   │   ├── DTOs/
│   │   ├── Events/
│   │   ├── Exceptions/
│   │   ├── Listeners/
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Services/
│   │   └── States/
│   ├── Enrollment/
│   │   ├── Actions/
│   │   ├── Contracts/
│   │   ├── DTOs/
│   │   ├── Events/
│   │   ├── Exceptions/
│   │   ├── Listeners/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── States/
│   ├── Assessment/
│   │   ├── Actions/
│   │   ├── Contracts/
│   │   ├── DTOs/
│   │   ├── Events/
│   │   ├── Exceptions/
│   │   ├── Listeners/
│   │   ├── Models/
│   │   ├── Policies/
│   │   ├── Services/
│   │   ├── States/
│   │   └── Strategies/
│   ├── Progress/
│   │   ├── Actions/
│   │   ├── Contracts/
│   │   ├── DTOs/
│   │   ├── Events/
│   │   ├── Listeners/
│   │   ├── Models/
│   │   └── Services/
│   └── Shared/
│       ├── Contracts/
│       ├── DTOs/
│       ├── Exceptions/
│       ├── Services/
│       └── ValueObjects/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   └── Web/
│   ├── Middleware/
│   └── Requests/
├── Providers/
└── Support/
    ├── Concerns/
    └── Helpers/
```

### Migration Strategy

**Step 1**: Create new directories (non-breaking)
```bash
# Create domain structure
mkdir -p app/Domain/{Course,Enrollment,Assessment,Progress,Shared}/{Actions,Contracts,DTOs,Events,Exceptions,Listeners,Services,States}
mkdir -p app/Domain/Assessment/Strategies
mkdir -p app/Domain/Shared/ValueObjects
mkdir -p app/Support/{Concerns,Helpers}
```

**Step 2**: Keep existing files working
- Don't move models yet
- Create contracts alongside existing code
- New code goes in new structure

**Step 3**: Gradual migration
- Move one domain at a time
- Update namespaces
- Keep backward compatibility aliases if needed

---

## 1.2 Base Contracts (Interfaces)

### Core Service Contract

```php
<?php
// app/Domain/Shared/Contracts/ServiceContract.php

namespace App\Domain\Shared\Contracts;

interface ServiceContract
{
    /**
     * Validate that the service can perform its action.
     *
     * @throws \App\Domain\Shared\Exceptions\ValidationException
     */
    public function validate(): void;
}
```

### State Machine Contract

```php
<?php
// app/Domain/Shared/Contracts/HasStates.php

namespace App\Domain\Shared\Contracts;

use Spatie\ModelStates\State;

interface HasStates
{
    public function getState(): State;

    public function canTransitionTo(string $state): bool;

    /**
     * @throws \App\Domain\Shared\Exceptions\InvalidStateTransitionException
     */
    public function transitionTo(string $state): void;
}
```

### Strategy Contract

```php
<?php
// app/Domain/Shared/Contracts/StrategyContract.php

namespace App\Domain\Shared\Contracts;

interface StrategyContract
{
    /**
     * Check if this strategy can handle the given context.
     */
    public function supports(mixed $context): bool;
}
```

### Grading Strategy Contract

```php
<?php
// app/Domain/Assessment/Contracts/GradingStrategyContract.php

namespace App\Domain\Assessment\Contracts;

use App\Domain\Assessment\DTOs\GradingResult;
use App\Models\Question;

interface GradingStrategyContract
{
    /**
     * Check if this strategy can grade the given question type.
     */
    public function supports(Question $question): bool;

    /**
     * Grade the answer and return a result.
     */
    public function grade(Question $question, mixed $answer): GradingResult;

    /**
     * Get the question types this strategy handles.
     *
     * @return array<string>
     */
    public function getHandledTypes(): array;
}
```

### Progress Calculator Contract

```php
<?php
// app/Domain/Progress/Contracts/ProgressCalculatorContract.php

namespace App\Domain\Progress\Contracts;

use App\Models\Enrollment;

interface ProgressCalculatorContract
{
    /**
     * Calculate progress for an enrollment.
     *
     * @return float Progress percentage (0-100)
     */
    public function calculate(Enrollment $enrollment): float;

    /**
     * Determine if the enrollment is complete.
     */
    public function isComplete(Enrollment $enrollment): bool;
}
```

### Event Contract

```php
<?php
// app/Domain/Shared/Contracts/DomainEvent.php

namespace App\Domain\Shared\Contracts;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class DomainEvent
{
    use Dispatchable, SerializesModels;

    public readonly \DateTimeImmutable $occurredAt;
    public readonly ?int $actorId;

    public function __construct(?int $actorId = null)
    {
        $this->occurredAt = new \DateTimeImmutable();
        $this->actorId = $actorId ?? auth()->id();
    }

    /**
     * Get the event name for logging/debugging.
     */
    abstract public function getEventName(): string;

    /**
     * Get event metadata for audit logging.
     *
     * @return array<string, mixed>
     */
    abstract public function getMetadata(): array;
}
```

---

## 1.3 Exception Hierarchy

### Base Exception Structure

```php
<?php
// app/Domain/Shared/Exceptions/DomainException.php

namespace App\Domain\Shared\Exceptions;

use Exception;

abstract class DomainException extends Exception
{
    protected array $context = [];

    public function __construct(
        string $message,
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->context = $context;
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Convert to array for logging/debugging.
     */
    public function toArray(): array
    {
        return [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'context' => $this->context,
            'code' => $this->getCode(),
        ];
    }
}
```

### Specific Exceptions

```php
<?php
// app/Domain/Shared/Exceptions/InvalidStateTransitionException.php

namespace App\Domain\Shared\Exceptions;

class InvalidStateTransitionException extends DomainException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $modelType,
        public readonly int|string $modelId,
        ?string $reason = null
    ) {
        $message = sprintf(
            'Cannot transition %s(%s) from "%s" to "%s"',
            $modelType,
            $modelId,
            $from,
            $to
        );

        if ($reason) {
            $message .= ": $reason";
        }

        parent::__construct($message, [
            'from' => $from,
            'to' => $to,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'reason' => $reason,
        ]);
    }
}
```

```php
<?php
// app/Domain/Shared/Exceptions/ValidationException.php

namespace App\Domain\Shared\Exceptions;

class ValidationException extends DomainException
{
    public function __construct(
        public readonly array $errors,
        string $message = 'The given data was invalid.'
    ) {
        parent::__construct($message, ['errors' => $errors]);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
```

```php
<?php
// app/Domain/Enrollment/Exceptions/AlreadyEnrolledException.php

namespace App\Domain\Enrollment\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class AlreadyEnrolledException extends DomainException
{
    public function __construct(int $userId, int $courseId)
    {
        parent::__construct(
            "User {$userId} is already enrolled in course {$courseId}",
            [
                'user_id' => $userId,
                'course_id' => $courseId,
            ]
        );
    }
}
```

```php
<?php
// app/Domain/Assessment/Exceptions/MaxAttemptsReachedException.php

namespace App\Domain\Assessment\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class MaxAttemptsReachedException extends DomainException
{
    public function __construct(
        int $userId,
        int $assessmentId,
        int $maxAttempts,
        int $currentAttempts
    ) {
        parent::__construct(
            "User has reached maximum attempts ({$maxAttempts}) for this assessment",
            [
                'user_id' => $userId,
                'assessment_id' => $assessmentId,
                'max_attempts' => $maxAttempts,
                'current_attempts' => $currentAttempts,
            ]
        );
    }
}
```

---

## 1.4 DTOs (Data Transfer Objects)

### Why DTOs?

1. **Type Safety**: Explicitly typed properties
2. **Immutability**: Data doesn't change unexpectedly
3. **Validation**: Validate at construction
4. **Documentation**: Self-documenting data structures
5. **Decoupling**: Controllers don't pass arrays around

### Base DTO

```php
<?php
// app/Domain/Shared/DTOs/DataTransferObject.php

namespace App\Domain\Shared\DTOs;

use Illuminate\Contracts\Support\Arrayable;

abstract class DataTransferObject implements Arrayable
{
    /**
     * Create from array of data.
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Create from request.
     */
    public static function fromRequest(\Illuminate\Http\Request $request): static
    {
        return static::fromArray($request->validated());
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
```

### Enrollment DTO

```php
<?php
// app/Domain/Enrollment/DTOs/CreateEnrollmentDTO.php

namespace App\Domain\Enrollment\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;

final readonly class CreateEnrollmentDTO extends DataTransferObject
{
    public function __construct(
        public int $userId,
        public int $courseId,
        public ?int $invitedBy = null,
        public ?\DateTimeInterface $enrolledAt = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            userId: $data['user_id'],
            courseId: $data['course_id'],
            invitedBy: $data['invited_by'] ?? null,
            enrolledAt: isset($data['enrolled_at'])
                ? new \DateTimeImmutable($data['enrolled_at'])
                : null,
        );
    }
}
```

### Grading Result DTO

```php
<?php
// app/Domain/Assessment/DTOs/GradingResult.php

namespace App\Domain\Assessment\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;

final readonly class GradingResult extends DataTransferObject
{
    public function __construct(
        public bool $isCorrect,
        public float $score,
        public float $maxScore,
        public ?string $feedback = null,
        public array $metadata = [],
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            isCorrect: $data['is_correct'],
            score: $data['score'],
            maxScore: $data['max_score'],
            feedback: $data['feedback'] ?? null,
            metadata: $data['metadata'] ?? [],
        );
    }

    public static function correct(float $points, ?string $feedback = null): static
    {
        return new static(
            isCorrect: true,
            score: $points,
            maxScore: $points,
            feedback: $feedback,
        );
    }

    public static function incorrect(float $maxPoints, ?string $feedback = null): static
    {
        return new static(
            isCorrect: false,
            score: 0,
            maxScore: $maxPoints,
            feedback: $feedback,
        );
    }

    public static function partial(float $score, float $maxScore, ?string $feedback = null): static
    {
        return new static(
            isCorrect: $score > 0,
            score: $score,
            maxScore: $maxScore,
            feedback: $feedback,
        );
    }

    public function getPercentage(): float
    {
        return $this->maxScore > 0
            ? round(($this->score / $this->maxScore) * 100, 2)
            : 0;
    }
}
```

### Progress Update DTO

```php
<?php
// app/Domain/Progress/DTOs/ProgressUpdateDTO.php

namespace App\Domain\Progress\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;

final readonly class ProgressUpdateDTO extends DataTransferObject
{
    public function __construct(
        public int $enrollmentId,
        public int $lessonId,
        public ?int $currentPage = null,
        public ?int $totalPages = null,
        public ?float $timeSpentSeconds = null,
        public ?int $mediaPositionSeconds = null,
        public ?int $mediaDurationSeconds = null,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): static
    {
        return new static(
            enrollmentId: $data['enrollment_id'],
            lessonId: $data['lesson_id'],
            currentPage: $data['current_page'] ?? null,
            totalPages: $data['total_pages'] ?? null,
            timeSpentSeconds: $data['time_spent_seconds'] ?? null,
            mediaPositionSeconds: $data['media_position_seconds'] ?? null,
            mediaDurationSeconds: $data['media_duration_seconds'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    public function isPageProgress(): bool
    {
        return $this->currentPage !== null;
    }

    public function isMediaProgress(): bool
    {
        return $this->mediaPositionSeconds !== null && $this->mediaDurationSeconds !== null;
    }
}
```

---

## 1.5 Value Objects

### Why Value Objects?

1. **Domain Modeling**: Express domain concepts explicitly
2. **Validation**: Invalid states are impossible
3. **Type Safety**: Compiler/IDE catches errors
4. **Self-documenting**: Code tells you what data means

### Percentage Value Object

```php
<?php
// app/Domain/Shared/ValueObjects/Percentage.php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class Percentage implements JsonSerializable, Stringable
{
    public function __construct(
        public float $value
    ) {
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

### Duration Value Object

```php
<?php
// app/Domain/Shared/ValueObjects/Duration.php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class Duration implements JsonSerializable, Stringable
{
    private const SECONDS_PER_MINUTE = 60;
    private const SECONDS_PER_HOUR = 3600;

    public function __construct(
        public int $seconds
    ) {
        if ($seconds < 0) {
            throw new InvalidArgumentException(
                "Duration cannot be negative, got: {$seconds}"
            );
        }
    }

    public static function fromMinutes(int $minutes): self
    {
        return new self($minutes * self::SECONDS_PER_MINUTE);
    }

    public static function fromHours(int $hours): self
    {
        return new self($hours * self::SECONDS_PER_HOUR);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function toMinutes(): int
    {
        return (int) floor($this->seconds / self::SECONDS_PER_MINUTE);
    }

    public function toHours(): float
    {
        return $this->seconds / self::SECONDS_PER_HOUR;
    }

    public function add(Duration $other): self
    {
        return new self($this->seconds + $other->seconds);
    }

    public function format(): string
    {
        if ($this->seconds < self::SECONDS_PER_MINUTE) {
            return "{$this->seconds} detik";
        }

        $minutes = $this->toMinutes();

        if ($minutes < 60) {
            return "{$minutes} menit";
        }

        $hours = (int) floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return "{$hours} jam";
        }

        return "{$hours} jam {$remainingMinutes} menit";
    }

    public function formatShort(): string
    {
        $hours = (int) floor($this->seconds / self::SECONDS_PER_HOUR);
        $minutes = (int) floor(($this->seconds % self::SECONDS_PER_HOUR) / self::SECONDS_PER_MINUTE);
        $seconds = $this->seconds % self::SECONDS_PER_MINUTE;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function jsonSerialize(): int
    {
        return $this->seconds;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
```

### Score Value Object

```php
<?php
// app/Domain/Assessment/ValueObjects/Score.php

namespace App\Domain\Assessment\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class Score implements JsonSerializable, Stringable
{
    public function __construct(
        public float $earned,
        public float $maximum
    ) {
        if ($earned < 0) {
            throw new InvalidArgumentException("Earned score cannot be negative");
        }
        if ($maximum <= 0) {
            throw new InvalidArgumentException("Maximum score must be positive");
        }
        if ($earned > $maximum) {
            throw new InvalidArgumentException("Earned score cannot exceed maximum");
        }
    }

    public static function zero(float $maximum): self
    {
        return new self(0, $maximum);
    }

    public static function perfect(float $maximum): self
    {
        return new self($maximum, $maximum);
    }

    public function getPercentage(): float
    {
        return round(($this->earned / $this->maximum) * 100, 2);
    }

    public function isPassing(float $passingPercentage): bool
    {
        return $this->getPercentage() >= $passingPercentage;
    }

    public function isPerfect(): bool
    {
        return $this->earned === $this->maximum;
    }

    public function add(Score $other): self
    {
        return new self(
            $this->earned + $other->earned,
            $this->maximum + $other->maximum
        );
    }

    public function format(): string
    {
        return sprintf('%.1f/%.1f (%.1f%%)',
            $this->earned,
            $this->maximum,
            $this->getPercentage()
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'earned' => $this->earned,
            'maximum' => $this->maximum,
            'percentage' => $this->getPercentage(),
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
```

---

## 1.6 Service Provider Setup

### Domain Service Provider

```php
<?php
// app/Providers/DomainServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Contracts
use App\Domain\Assessment\Contracts\GradingStrategyContract;
use App\Domain\Progress\Contracts\ProgressCalculatorContract;

// Services (to be created in Phase 2)
// use App\Domain\Enrollment\Services\EnrollmentService;
// use App\Domain\Progress\Services\ProgressTrackingService;
// use App\Domain\Assessment\Services\GradingService;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * All of the container singletons that should be registered.
     */
    public array $singletons = [
        // Services will be registered here in Phase 2
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register strategy resolver
        $this->registerGradingStrategies();

        // Register progress calculator
        $this->registerProgressCalculator();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register event listeners (Phase 4)
        // Register model observers if needed
    }

    protected function registerGradingStrategies(): void
    {
        // Will be implemented in Phase 5
        // $this->app->tag([
        //     MultipleChoiceGradingStrategy::class,
        //     TrueFalseGradingStrategy::class,
        //     ShortAnswerGradingStrategy::class,
        // ], 'grading.strategies');

        // $this->app->singleton(GradingStrategyResolver::class, function ($app) {
        //     return new GradingStrategyResolver(
        //         $app->tagged('grading.strategies')
        //     );
        // });
    }

    protected function registerProgressCalculator(): void
    {
        // Will be implemented in Phase 5
        // $this->app->bind(
        //     ProgressCalculatorContract::class,
        //     LessonBasedProgressCalculator::class
        // );
    }
}
```

### Register in bootstrap/providers.php

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\DomainServiceProvider::class, // Add this
];
```

---

## 1.7 Implementation Checklist

### Week 1 Tasks

- [ ] Create directory structure
  - [ ] `app/Domain/Course/` with subdirectories
  - [ ] `app/Domain/Enrollment/` with subdirectories
  - [ ] `app/Domain/Assessment/` with subdirectories
  - [ ] `app/Domain/Progress/` with subdirectories
  - [ ] `app/Domain/Shared/` with subdirectories
  - [ ] `app/Support/` with subdirectories

- [ ] Create base contracts
  - [ ] `ServiceContract.php`
  - [ ] `HasStates.php`
  - [ ] `StrategyContract.php`
  - [ ] `DomainEvent.php`
  - [ ] `GradingStrategyContract.php`
  - [ ] `ProgressCalculatorContract.php`

- [ ] Create exception hierarchy
  - [ ] `DomainException.php`
  - [ ] `InvalidStateTransitionException.php`
  - [ ] `ValidationException.php`
  - [ ] Domain-specific exceptions

### Week 2 Tasks

- [ ] Create base DTOs
  - [ ] `DataTransferObject.php`
  - [ ] `CreateEnrollmentDTO.php`
  - [ ] `GradingResult.php`
  - [ ] `ProgressUpdateDTO.php`

- [ ] Create Value Objects
  - [ ] `Percentage.php`
  - [ ] `Duration.php`
  - [ ] `Score.php`

- [ ] Configure Service Provider
  - [ ] Create `DomainServiceProvider.php`
  - [ ] Register in `bootstrap/providers.php`
  - [ ] Test that application boots correctly

- [ ] Write tests for Value Objects
  - [ ] Test Percentage validation and methods
  - [ ] Test Duration calculations
  - [ ] Test Score operations

---

## 1.8 Testing Foundation

### Value Object Tests

```php
<?php
// tests/Unit/Domain/Shared/ValueObjects/PercentageTest.php

use App\Domain\Shared\ValueObjects\Percentage;

describe('Percentage', function () {
    it('creates valid percentage', function () {
        $percentage = new Percentage(75.5);
        expect($percentage->value)->toBe(75.5);
    });

    it('rejects negative values', function () {
        new Percentage(-1);
    })->throws(InvalidArgumentException::class);

    it('rejects values over 100', function () {
        new Percentage(101);
    })->throws(InvalidArgumentException::class);

    it('calculates from fraction', function () {
        $percentage = Percentage::fromFraction(3, 4);
        expect($percentage->value)->toBe(75.0);
    });

    it('handles zero denominator', function () {
        $percentage = Percentage::fromFraction(5, 0);
        expect($percentage->value)->toBe(0.0);
    });

    it('formats correctly', function () {
        $percentage = new Percentage(75.55);
        expect($percentage->format())->toBe('75.6%');
        expect($percentage->format(2))->toBe('75.55%');
    });

    it('detects completion', function () {
        expect((new Percentage(100))->isComplete())->toBeTrue();
        expect((new Percentage(99.9))->isComplete())->toBeFalse();
    });
});
```

```php
<?php
// tests/Unit/Domain/Assessment/ValueObjects/ScoreTest.php

use App\Domain\Assessment\ValueObjects\Score;

describe('Score', function () {
    it('creates valid score', function () {
        $score = new Score(80, 100);
        expect($score->earned)->toBe(80.0);
        expect($score->maximum)->toBe(100.0);
    });

    it('calculates percentage', function () {
        $score = new Score(75, 100);
        expect($score->getPercentage())->toBe(75.0);
    });

    it('determines passing status', function () {
        $score = new Score(70, 100);
        expect($score->isPassing(70))->toBeTrue();
        expect($score->isPassing(71))->toBeFalse();
    });

    it('adds scores together', function () {
        $score1 = new Score(10, 20);
        $score2 = new Score(15, 30);
        $total = $score1->add($score2);

        expect($total->earned)->toBe(25.0);
        expect($total->maximum)->toBe(50.0);
    });

    it('rejects earned greater than maximum', function () {
        new Score(101, 100);
    })->throws(InvalidArgumentException::class);
});
```

---

## 1.9 Migration Notes

### Backward Compatibility

During Phase 1, the existing code continues to work:
- Models stay in `app/Models/`
- Controllers stay in `app/Http/Controllers/`
- No existing namespaces change

### Forward References

Phase 2 will begin using:
- DTOs for data transfer
- Exceptions for error handling
- Value Objects where appropriate

### Dependencies

Phase 1 has no external package dependencies. All code is pure PHP/Laravel.

---

## Next Phase

Once Phase 1 is complete, proceed to [Phase 2: Service Layer Extraction](./02-SERVICE-LAYER.md).

The foundation established here enables clean service extraction without breaking existing functionality.
