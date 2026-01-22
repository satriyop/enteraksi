---
name: enteraksi-phpstan-shapes
description: PHPDoc array shapes for static analysis with Larastan/PHPStan. Use when creating DTOs, writing fromArray methods, or adding type hints for better IDE support.
triggers:
  - array shape
  - phpstan
  - larastan
  - static analysis
  - fromArray
  - type hint array
  - phpDoc array
  - @param array
  - @return array
  - dto type
  - phpstan-type
---

# PHPStan Array Shapes for Enteraksi

## When to Use This Skill

- Creating DTOs with `fromArray()` methods
- Defining return types for methods that return associative arrays
- Adding type safety to service method parameters
- Documenting complex nested data structures
- Enabling IDE autocomplete for array keys

## Why Array Shapes Matter

| Without Shapes | With Shapes |
|----------------|-------------|
| `$data['progrss']` typo goes unnoticed | PHPStan catches typo at analysis time |
| IDE can't autocomplete array keys | Full autocomplete support |
| Runtime errors for missing keys | Compile-time error detection |
| Manual documentation maintenance | Self-documenting code |

## Running PHPStan

```bash
# Run analysis (should pass for new code)
./vendor/bin/phpstan analyse

# Check specific file
./vendor/bin/phpstan analyse app/Domain/Progress/DTOs/ProgressResult.php

# Generate baseline after fixing errors
./vendor/bin/phpstan analyse --generate-baseline
```

## Basic Array Shape Syntax

### Simple Shape

```php
/**
 * @param array{
 *     user_id: int,
 *     course_id: int,
 *     enrolled_at?: string|null
 * } $data
 */
public static function fromArray(array $data): static
```

**Key syntax:**
- `key: type` - Required key
- `key?: type` - Optional key (may not exist)
- `key: type|null` - Required key, can be null
- `key?: type|null` - Optional key, can be null when present

### Return Type Shape

```php
/**
 * @return array{
 *     id: int,
 *     title: string,
 *     status: string,
 *     created_at: string
 * }
 */
public function toResponse(): array
{
    return [
        'id' => $this->id,
        'title' => $this->title,
        'status' => (string) $this->status,
        'created_at' => $this->createdAt->toIso8601String(),
    ];
}
```

## Named Type Aliases (@phpstan-type)

For complex or reusable shapes, define type aliases at class level:

```php
/**
 * @phpstan-type ProgressDataArray array{
 *     id: int,
 *     enrollment_id: int,
 *     lesson_id: int,
 *     is_completed: bool,
 *     progress_percentage?: int,
 *     time_spent_seconds?: int,
 *     current_page?: int|null,
 *     total_pages?: int|null,
 *     completed_at?: string|null
 * }
 * @phpstan-type AssessmentStatsArray array{
 *     total: int,
 *     passed: int,
 *     pending: int,
 *     required_total: int,
 *     required_passed: int
 * }
 */
final readonly class ProgressResult
{
    /**
     * @param array{
     *     progress: ProgressDataArray,
     *     course_percentage: float|int,
     *     lesson_completed: bool,
     *     course_completed: bool,
     *     assessment_stats?: AssessmentStatsArray|null
     * } $data
     */
    public static function fromArray(array $data): static
    {
        // Implementation
    }
}
```

## Common Patterns

### 1. DTO fromArray with Validation

```php
/**
 * @param array{
 *     user_id: int,
 *     course_id: int,
 *     invited_by?: int|null,
 *     enrolled_at?: string|null
 * } $data
 */
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
```

### 2. Result DTO with Model

When a DTO contains an Eloquent model:

```php
/**
 * @param array{
 *     enrollment: \App\Models\Enrollment,
 *     is_new_enrollment: bool,
 *     message?: string|null
 * } $data
 */
public static function fromArray(array $data): static
{
    return new self(
        enrollment: $data['enrollment'],
        isNewEnrollment: $data['is_new_enrollment'],
        message: $data['message'] ?? null,
    );
}
```

### 3. Grading Result with Flexible Metadata

```php
/**
 * @param array{
 *     is_correct: bool,
 *     score: float|int,
 *     max_score: float|int,
 *     feedback?: string|null,
 *     metadata?: array<string, mixed>
 * } $data
 */
public static function fromArray(array $data): static
```

### 4. Service Method Parameters

```php
/**
 * @param array{
 *     filters?: array{status?: string, search?: string},
 *     pagination?: array{page: int, per_page: int},
 *     includes?: array<string>
 * } $options
 * @return array{data: array<Course>, meta: array{total: int, page: int}}
 */
public function listCourses(array $options = []): array
```

### 5. Spatie Data Class with fromModel

```php
use App\Models\LessonProgress;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LessonProgressData extends Data
{
    public function __construct(
        public int $id,
        public int $enrollment_id,
        public int $lesson_id,
        public bool $is_completed,
        public int $progress_percentage,
        public int $time_spent_seconds,
        // ... more properties
    ) {}

    public static function fromModel(LessonProgress $progress): self
    {
        return new self(
            id: $progress->id,
            enrollment_id: $progress->enrollment_id,
            // ...
        );
    }
}
```

## Generic Array Types

For collections and lists:

```php
// List of integers
/** @param array<int> $ids */

// List of strings
/** @param array<string> $names */

// Associative with string keys, mixed values
/** @param array<string, mixed> $metadata */

// List of typed objects
/** @param array<Course> $courses */

// List of shaped arrays
/** @param array<array{id: int, title: string}> $items */
```

## Importing Types from Other Classes

```php
use App\Domain\Assessment\DTOs\GradingResult;

/**
 * @phpstan-import-type QuestionResultArray from GradingResult
 */
final class AssessmentResultDTO
{
    /**
     * @param array{questions: array<QuestionResultArray>} $data
     */
    public static function fromArray(array $data): static
```

## Eloquent Model Property Hints

Add `@property` annotations to models for PHPStan:

```php
/**
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property string $status
 * @property int $progress_percentage
 * @property \Carbon\Carbon|null $enrolled_at
 * @property \Carbon\Carbon|null $completed_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Course $course
 */
class Enrollment extends Model
```

## Gotchas & Best Practices

### 1. Optional vs Nullable

```php
// Optional: key may not exist in array
'enrolled_at?: string'

// Nullable: key exists but value can be null
'enrolled_at: string|null'

// Both: key may not exist, and if exists can be null
'enrolled_at?: string|null'
```

### 2. Snake Case in Shapes

Array shapes should match the actual array keys (usually snake_case from database):

```php
// CORRECT - matches actual array keys
/** @param array{user_id: int, course_id: int} $data */

// WRONG - camelCase doesn't match array keys
/** @param array{userId: int, courseId: int} $data */
```

### 3. Float vs Int

Be explicit about numeric types:

```php
// Accept both float and int
'score: float|int'

// Only float
'percentage: float'

// Only int
'count: int'
```

### 4. Consistency with toArray()

If you define a `@return` shape for `toArray()`, ensure the implementation matches:

```php
/**
 * @return array{id: int, name: string}  // Shape says 'name'
 */
public function toArray(): array
{
    return [
        'id' => $this->id,
        'title' => $this->title,  // BUG: 'title' != 'name'
    ];
}
```

### 5. Baseline for Existing Errors

New code must pass PHPStan. Existing errors are in baseline:

```bash
# After fixing a batch of errors
./vendor/bin/phpstan analyse --generate-baseline
```

## Quick Reference

| Pattern | Syntax |
|---------|--------|
| Required key | `key: type` |
| Optional key | `key?: type` |
| Nullable value | `key: type\|null` |
| Mixed array | `array<string, mixed>` |
| Typed list | `array<int>` |
| Type alias | `@phpstan-type Name array{...}` |
| Import type | `@phpstan-import-type Name from Class` |
| Use alias | `@param Name $var` |

## Files to Reference

```
phpstan.neon                                          # Configuration
phpstan-baseline.neon                                 # Existing errors
app/Domain/Progress/DTOs/ProgressResult.php           # Complex nested example
app/Data/Progress/LessonProgressData.php              # Spatie Data class example
app/Domain/Enrollment/DTOs/CreateEnrollmentDTO.php    # Simple DTO example
app/Domain/Assessment/DTOs/GradingResult.php          # Mixed types example
```
