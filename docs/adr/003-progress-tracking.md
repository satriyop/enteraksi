# ADR-003: Lesson Progress Tracking Approach

**Status:** Accepted (Updated)
**Date:** 2025-11-27
**Updated:** 2026-01-20
**Deciders:** Development Team

## Context

We need to track learner progress through course content. Requirements:
- Track completion of individual lessons
- Calculate overall course progress percentage
- Support different content types (text, video, audio, documents)
- Resume from where learner left off
- Auto-complete lessons when content is consumed
- Support courses with required assessments
- Allow different progress calculation strategies

## Decision

We will implement **service-based progress tracking** with:
1. **ProgressTrackingService** - Centralized API for all progress operations
2. **Strategy Pattern** - Swappable progress calculators
3. **Domain Events** - Event-driven notifications on progress changes

## Rationale

### Different Content Types Need Different Tracking

| Content Type | Completion Criteria | Resume Data |
|--------------|---------------------|-------------|
| Text (Rich Content) | Reached last page | Current page |
| Document (PDF) | Reached last page | Current page |
| Video | Watched 90%+ | Playback position |
| Audio | Listened 90%+ | Playback position |
| YouTube | Watched 90%+ | Playback position |
| Conference | Marked manually | N/A |

### Progress Model Design

```php
// lesson_progress table
Schema::create('lesson_progress', function (Blueprint $table) {
    // Page-based tracking
    $table->integer('current_page')->default(1);
    $table->integer('total_pages')->nullable();
    $table->integer('highest_page_reached')->default(1);

    // Media-based tracking
    $table->integer('media_position_seconds')->default(0);
    $table->integer('media_duration_seconds')->nullable();
    $table->decimal('media_progress_percentage', 5, 2)->default(0);

    // Completion
    $table->boolean('is_completed')->default(false);
    $table->timestamp('completed_at')->nullable();

    // Metadata
    $table->float('time_spent_seconds')->default(0);
    $table->json('pagination_metadata')->nullable();
});
```

### Auto-Completion Logic

```php
// Page-based: Complete when at last page
if ($currentPage >= $totalPages) {
    $this->markCompleted();
}

// Media-based: Complete at 90%
if ($mediaProgressPercentage >= 90) {
    $this->markCompleted();
}
```

**Why 90% for media?**
- Users often skip last few seconds
- Credits/outros not essential
- Industry standard threshold

### Course Progress Calculation (Strategy Pattern)

Progress calculation uses swappable strategies:

| Strategy | Formula | Use Case |
|----------|---------|----------|
| `LessonBasedProgressCalculator` | `(completed / total) * 100` | Simple courses |
| `AssessmentInclusiveProgressCalculator` | `(lessons * 0.7) + (assessments * 0.3)` | Courses with required assessments |
| `WeightedProgressCalculator` | Custom section weights | Complex curricula |

```php
// Using ProgressTrackingService
$service = app(ProgressTrackingServiceContract::class);
$progress = $service->calculateProgress($enrollment);

// The service uses ProgressCalculatorFactory to resolve the strategy
// based on course configuration or explicit selection
```

### Completion Requirements

For `AssessmentInclusiveProgressCalculator`:
- All lessons must be completed
- All **required** assessments must be passed (`is_required = true`)
- Optional assessments (`is_required = false`) don't block completion

## Implementation Details

### Service Layer Architecture

```
app/Domain/Progress/
├── Contracts/
│   ├── ProgressTrackingServiceContract.php
│   └── ProgressCalculatorContract.php
├── Services/
│   ├── ProgressTrackingService.php      # Main service
│   └── ProgressCalculatorFactory.php    # Strategy resolver
├── Strategies/
│   ├── LessonBasedProgressCalculator.php
│   ├── AssessmentInclusiveProgressCalculator.php
│   └── WeightedProgressCalculator.php
├── Events/
│   ├── LessonCompleted.php
│   └── ProgressUpdated.php
└── DTOs/
    ├── ProgressResult.php
    └── ProgressUpdateDTO.php
```

### Service API

```php
$service = app(ProgressTrackingServiceContract::class);

// Get or create progress record
$progress = $service->getOrCreateProgress($enrollment, $lesson);

// Update page-based progress (text/PDF)
$service->updatePageProgress($enrollment, $lesson, $currentPage, $totalPages);

// Update media progress (video/audio)
$service->updateMediaProgress($enrollment, $lesson, $positionSeconds, $durationSeconds);

// Mark lesson complete manually
$service->markLessonComplete($enrollment, $lesson);

// Calculate course progress using appropriate strategy
$percentage = $service->calculateProgress($enrollment);
```

### Frontend Tracking (Debounced)

```typescript
// Text content: Track page changes
watch(currentPage, useDebounceFn((page) => {
    updateProgress({ current_page: page, total_pages: totalPages });
}, 500));

// Video: Track periodically + on pause
onTimeUpdate(useDebounceFn((position) => {
    updateMediaProgress({ position_seconds: position });
}, 5000));
```

### Backend Endpoints

| Endpoint | Purpose | Service Method |
|----------|---------|----------------|
| `PATCH /progress` | Update page progress | `updatePageProgress()` |
| `PATCH /progress/media` | Update media progress | `updateMediaProgress()` |
| `POST /complete` | Manual completion | `markLessonComplete()` |

### Domain Events

| Event | Triggered When |
|-------|----------------|
| `ProgressUpdated` | Progress percentage changes |
| `LessonCompleted` | Lesson marked complete |
| `EnrollmentCompleted` | Course reaches 100% (via Enrollment domain) |

## Consequences

### Positive

- Accurate progress for all content types
- Resume capability for all content
- Automatic completion reduces manual effort
- Course percentage calculated correctly

### Negative

- More complex than simple boolean tracking
- Multiple API calls during lesson viewing
- Storage overhead for position data

### Risks

- **High API traffic** from progress updates
- **Mitigation**: Debounce updates (500ms-5s)

- **Progress loss on browser crash**
- **Mitigation**: Frequent saves, localStorage backup

## Alternatives Considered

### Simple Boolean Completion

Rejected because:
- No resume capability
- Manual completion only
- No partial progress visibility

### Time-Based Completion

Rejected because:
- Doesn't ensure content was consumed
- Can be gamed by leaving tab open
- Doesn't work for different content lengths

## References

- Industry research on video completion thresholds
- SCORM 2004 completion criteria
