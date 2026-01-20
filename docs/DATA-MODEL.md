# Enteraksi LMS - Data Model Documentation

> **Purpose**: Complete reference for all database entities, their relationships, attributes, and behaviors. Use this document to understand the data structure before writing queries, migrations, or business logic.

---

## Table of Contents
1. [Entity Relationship Overview](#entity-relationship-overview)
2. [Models Reference](#models-reference)
3. [Database Schema](#database-schema)
4. [Factories & Seeders](#factories--seeders)

---

## Entity Relationship Overview

### Visual Diagram (Text)

```
                                    ┌─────────────────┐
                                    │      User       │
                                    │   (4 roles)     │
                                    └────────┬────────┘
                                             │
              ┌──────────────────────────────┼──────────────────────────────┐
              │                              │                              │
              ▼                              ▼                              ▼
    ┌─────────────────┐           ┌─────────────────┐           ┌─────────────────┐
    │     Course      │◄──────────│   Enrollment    │           │  LearningPath   │
    │                 │           │                 │           │                 │
    └────────┬────────┘           └─────────────────┘           └────────┬────────┘
             │                                                           │
    ┌────────┼────────┬─────────────────┐               ┌───────────────┘
    │        │        │                 │               │
    ▼        ▼        ▼                 ▼               ▼
┌────────┐ ┌────────┐ ┌──────────┐ ┌──────────┐  ┌─────────────┐
│Category│ │  Tag   │ │CourseInv.│ │  Rating  │  │LP ◄─► Course│
└────────┘ └────────┘ └──────────┘ └──────────┘  │   (pivot)   │
                                                 └─────────────┘
             │
             ▼
    ┌─────────────────┐
    │  CourseSection  │
    └────────┬────────┘
             │
             ▼
    ┌─────────────────┐
    │     Lesson      │◄───────────┐
    └────────┬────────┘            │
             │                     │
    ┌────────┼────────┐            │
    │        │        │            │
    ▼        ▼        ▼            │
┌────────┐ ┌────────┐ ┌────────────┴────┐
│ Media  │ │Progress│ │   Assessment    │
│(morph) │ │        │ └────────┬────────┘
└────────┘ └────────┘          │
                               ▼
                      ┌─────────────────┐
                      │    Question     │
                      └────────┬────────┘
                               │
                      ┌────────┼────────┐
                      │                 │
                      ▼                 ▼
             ┌─────────────┐   ┌─────────────┐
             │   Option    │   │   Attempt   │
             └─────────────┘   └──────┬──────┘
                                      │
                                      ▼
                              ┌─────────────┐
                              │   Answer    │
                              └─────────────┘
```

### Relationship Summary

| Parent | Relationship | Child | Type |
|--------|--------------|-------|------|
| User | creates | Course | 1:N |
| User | enrolls in | Course | N:M (via Enrollment) |
| User | is invited to | Course | N:M (via CourseInvitation) |
| User | rates | Course | 1:N (CourseRating) |
| User | creates | Assessment | 1:N |
| User | attempts | Assessment | N:M (via AssessmentAttempt) |
| User | creates | LearningPath | 1:N |
| Course | belongs to | Category | N:1 |
| Course | has | Tag | N:M (pivot) |
| Course | contains | CourseSection | 1:N |
| Course | has | Assessment | 1:N |
| Course | has | Media | 1:N (morph) |
| CourseSection | contains | Lesson | 1:N |
| Lesson | has | Media | 1:N (morph) |
| Lesson | tracks | LessonProgress | 1:N |
| Enrollment | tracks | LessonProgress | 1:N |
| Assessment | contains | Question | 1:N |
| Question | has | QuestionOption | 1:N |
| Assessment | has | AssessmentAttempt | 1:N |
| AssessmentAttempt | contains | AttemptAnswer | 1:N |
| LearningPath | contains | Course | N:M (pivot with position) |

---

## Models Reference

### 1. User

**File:** `app/Models/User.php`
**Table:** `users`

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Full name |
| email | string | Unique email |
| email_verified_at | timestamp | Verification timestamp |
| password | string | Hashed password |
| role | enum | `learner`, `content_manager`, `trainer`, `lms_admin` |
| two_factor_secret | text | 2FA TOTP secret |
| two_factor_recovery_codes | text | 2FA recovery codes |
| two_factor_confirmed_at | timestamp | 2FA confirmation |
| remember_token | string | Session token |

#### Relationships
```php
courses()           → HasMany(Course)           // Courses created by user
enrollments()       → HasMany(Enrollment)       // Enrollment records
enrolledCourses()   → BelongsToMany(Course)     // via Enrollment pivot
courseInvitations() → HasMany(CourseInvitation)
pendingInvitations()→ HasMany(CourseInvitation) // Filtered: pending + not expired
courseRatings()     → HasMany(CourseRating)
```

#### Role Helper Methods
```php
isLearner(): bool
isContentManager(): bool
isTrainer(): bool
isLmsAdmin(): bool
canManageCourses(): bool  // content_manager, trainer, or lms_admin
```

---

### 2. Course

**File:** `app/Models/Course.php`
**Table:** `courses`
**Traits:** HasFactory, SoftDeletes

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Creator (FK → users) |
| title | string(255) | Course title |
| slug | string | URL-friendly identifier |
| short_description | text | Brief description (max 500) |
| long_description | longtext | Detailed description |
| objectives | json | Array of learning objectives |
| prerequisites | json | Array of prerequisites |
| category_id | bigint | FK → categories |
| thumbnail_path | string | Storage path |
| status | enum | `draft`, `published`, `archived` |
| visibility | enum | `public`, `restricted`, `hidden` |
| difficulty_level | enum | `beginner`, `intermediate`, `advanced` |
| estimated_duration_minutes | int | Auto-calculated |
| manual_duration_minutes | int | Manual override |
| published_at | timestamp | Publication date |
| published_by | bigint | FK → users |

#### Relationships
```php
user()          → BelongsTo(User)           // Creator
category()      → BelongsTo(Category)
publishedBy()   → BelongsTo(User)
sections()      → HasMany(CourseSection)     // Ordered by `order`
lessons()       → HasManyThrough(Lesson, CourseSection)
tags()          → BelongsToMany(Tag)
enrollments()   → HasMany(Enrollment)
enrolledUsers() → BelongsToMany(User)        // via Enrollment
ratings()       → HasMany(CourseRating)
invitations()   → HasMany(CourseInvitation)
media()         → MorphMany(Media)
```

#### Scopes
```php
published()      // status = 'published'
draft()          // status = 'draft'
archived()       // status = 'archived'
visible()        // visibility = 'public'
forUser($user)   // user_id = $user->id
```

#### Accessors (Appended)
```php
duration         // manual_duration_minutes ?? estimated_duration_minutes ?? 0
totalLessons     // Count of lessons
isEditable       // status !== 'published'
thumbnailUrl     // Full storage URL
averageRating    // Rounded average of ratings
ratingsCount     // Count of ratings
```

#### Methods
```php
calculateEstimatedDuration(): int  // Sum of lesson durations
updateEstimatedDuration(): void    // Recalculate and save
```

---

### 3. CourseSection

**File:** `app/Models/CourseSection.php`
**Table:** `course_sections`

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| course_id | bigint | FK → courses |
| title | string(255) | Section title |
| description | text | Optional description |
| order | int | Display order |
| estimated_duration_minutes | int | Auto-calculated |

#### Relationships
```php
course()  → BelongsTo(Course)
lessons() → HasMany(Lesson)  // Ordered by `order`
```

#### Accessors
```php
totalLessons  // Count of lessons
duration      // estimated_duration_minutes or sum of lessons
```

---

### 4. Lesson

**File:** `app/Models/Lesson.php`
**Table:** `lessons`

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| course_section_id | bigint | FK → course_sections |
| title | string(255) | Lesson title |
| description | text | Optional description |
| order | int | Display order |
| content_type | enum | `text`, `video`, `youtube`, `audio`, `document`, `conference` |
| rich_content | json | TipTap editor content |
| youtube_url | string | YouTube video URL |
| conference_url | string | Video conference URL |
| conference_type | enum | `zoom`, `google_meet`, `other` |
| estimated_duration_minutes | int | Duration in minutes |
| is_free_preview | boolean | Allow preview without enrollment |

#### Relationships
```php
section()  → BelongsTo(CourseSection)
course()   → HasOneThrough(Course, CourseSection)
media()    → MorphMany(Media)
progress() → HasMany(LessonProgress)
```

#### Accessors
```php
youtubeVideoId    // Extracted from youtube_url
hasVideo          // content_type in ['video', 'youtube']
hasAudio          // content_type === 'audio'
hasDocument       // content_type === 'document'
hasConference     // content_type === 'conference'
richContentHtml   // Rendered via TipTapRenderer
```

---

### 5. Media

**File:** `app/Models/Media.php`
**Table:** `media`

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| mediable_type | string | Polymorphic type (Course, Lesson) |
| mediable_id | bigint | Polymorphic ID |
| collection_name | string | `default`, `video`, `audio`, `document`, `thumbnail` |
| name | string | Display name |
| file_name | string | Original filename |
| mime_type | string | MIME type |
| disk | string | Storage disk |
| path | string | Storage path |
| size | bigint | File size in bytes |
| duration_seconds | int | For video/audio |
| custom_properties | json | Additional metadata |
| order_column | int | Display order |

#### Relationships
```php
mediable() → MorphTo  // Course or Lesson
```

#### Accessors
```php
url                // Full storage URL
fullPath           // Absolute path
humanReadableSize  // Formatted (KB, MB, GB)
isImage            // mime_type starts with 'image/'
isVideo            // mime_type starts with 'video/'
isAudio            // mime_type starts with 'audio/'
isDocument         // PDF, DOC, PPT, XLS types
durationFormatted  // HH:MM:SS or MM:SS
```

---

### 6. Enrollment

**File:** `app/Models/Enrollment.php`
**Table:** `enrollments`

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | FK → users |
| course_id | bigint | FK → courses |
| status | enum | `active`, `inactive`, `completed`, `dropped` |
| progress_percentage | decimal | 0-100 |
| enrolled_at | timestamp | Enrollment date |
| started_at | timestamp | First lesson accessed |
| completed_at | timestamp | Course completion |
| invited_by | bigint | FK → users (if invited) |
| last_lesson_id | bigint | FK → lessons |

#### Relationships
```php
user()           → BelongsTo(User)
course()         → BelongsTo(Course)
invitedBy()      → BelongsTo(User)
lastLesson()     → BelongsTo(Lesson)
lessonProgress() → HasMany(LessonProgress)
```

#### Scopes
```php
active()        // status = 'active'
completed()     // status = 'completed'
forUser($user)  // user_id = $user->id
```

#### Accessors
```php
isCompleted  // status === 'completed'
isActive     // status === 'active'
```

#### Methods
```php
getProgressForLesson(Lesson $lesson): ?LessonProgress
```

> **Note:** Progress tracking is now handled by `ProgressTrackingService`. Use:
> ```php
> $service = app(ProgressTrackingServiceContract::class);
> $service->getOrCreateProgress($enrollment, $lesson);
> $service->calculateProgress($enrollment);
> ```

---

### 7. LessonProgress

**File:** `app/Models/LessonProgress.php`
**Table:** `lesson_progress`

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| enrollment_id | bigint | FK → enrollments |
| lesson_id | bigint | FK → lessons |
| current_page | int | Current page (for text/PDF) |
| total_pages | int | Total pages |
| highest_page_reached | int | Furthest page viewed |
| time_spent_seconds | float | Total time on lesson |
| media_position_seconds | int | Video/audio position |
| media_duration_seconds | int | Total media duration |
| media_progress_percentage | decimal | 0-100 |
| is_completed | boolean | Completion flag |
| last_viewed_at | timestamp | Last access |
| completed_at | timestamp | Completion date |
| pagination_metadata | json | Additional tracking data |

#### Relationships
```php
enrollment() → BelongsTo(Enrollment)
lesson()     → BelongsTo(Lesson)
```

#### Accessors
```php
resumePosition       // media_position_seconds
progressPercentage   // (highest_page_reached / total_pages) * 100
timeSpentFormatted   // Indonesian format (detik, menit, jam)
```

#### Methods
```php
isMediaBased(): bool
```

> **Note:** Progress updates are now handled by `ProgressTrackingService`. Use:
> ```php
> $service = app(ProgressTrackingServiceContract::class);
> $service->updatePageProgress($enrollment, $lesson, $page, $totalPages);
> $service->updateMediaProgress($enrollment, $lesson, $position, $duration);
> $service->markLessonComplete($enrollment, $lesson);
> ```

---

### 8. CourseInvitation

**File:** `app/Models/CourseInvitation.php`
**Table:** `course_invitations`

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | FK → users (invitee) |
| course_id | bigint | FK → courses |
| invited_by | bigint | FK → users (inviter) |
| status | enum | `pending`, `accepted`, `declined`, `expired` |
| message | text | Optional personal message |
| expires_at | timestamp | Expiration date |
| responded_at | timestamp | Response date |

#### Relationships
```php
user()    → BelongsTo(User)   // Invitee
course()  → BelongsTo(Course)
inviter() → BelongsTo(User)   // Who sent invitation
```

#### Scopes
```php
pending()      // status = 'pending'
forUser($user) // user_id = $user->id
notExpired()   // expires_at is null OR expires_at > now()
```

#### Accessors
```php
isExpired  // expires_at && expires_at.isPast()
isPending  // status === 'pending' && !is_expired
```

---

### 9. CourseRating

**File:** `app/Models/CourseRating.php`
**Table:** `course_ratings`

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | FK → users |
| course_id | bigint | FK → courses |
| rating | int | 1-5 stars |
| review | text | Optional review text |

#### Relationships
```php
user()   → BelongsTo(User)
course() → BelongsTo(Course)
```

---

### 10. Category

**File:** `app/Models/Category.php`
**Table:** `categories`

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Category name |
| slug | string | URL-friendly identifier |
| description | text | Optional description |
| parent_id | bigint | FK → categories (self-referencing) |
| order | int | Display order |

#### Relationships
```php
parent()   → BelongsTo(Category)
children() → HasMany(Category)
courses()  → HasMany(Course)
```

---

### 11. Tag

**File:** `app/Models/Tag.php`
**Table:** `tags`

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | string | Tag name |
| slug | string | URL-friendly identifier |

#### Relationships
```php
courses() → BelongsToMany(Course)
```

---

### 12. Assessment

**File:** `app/Models/Assessment.php`
**Table:** `assessments`
**Traits:** HasFactory, SoftDeletes

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| course_id | bigint | FK → courses |
| user_id | bigint | FK → users (creator) |
| title | string(255) | Assessment title |
| slug | string | URL-friendly identifier |
| description | text | Description |
| instructions | text | Instructions for learners |
| time_limit_minutes | int | Time limit (null = unlimited) |
| passing_score | int | Passing percentage (0-100) |
| max_attempts | int | Maximum attempts allowed |
| shuffle_questions | boolean | Randomize question order |
| show_correct_answers | boolean | Show answers after submission |
| allow_review | boolean | Allow review of attempt |
| is_required | boolean | Required for course completion (default: true) |
| status | enum | `draft`, `published`, `archived` |
| visibility | enum | `public`, `restricted`, `hidden` |
| published_at | timestamp | Publication date |
| published_by | bigint | FK → users |

#### Relationships
```php
course()      → BelongsTo(Course)
user()        → BelongsTo(User)
publishedBy() → BelongsTo(User)
questions()   → HasMany(Question)  // Ordered by `order`
attempts()    → HasMany(AssessmentAttempt)
```

#### Scopes
```php
published()
draft()
archived()
visible()
```

#### Accessors
```php
totalQuestions  // Count of questions
totalPoints     // Sum of question points
isEditable      // status !== 'published'
```

#### Methods
```php
generateSlug(): string
canBeAttemptedBy(User $user): bool  // Checks published, enrollment, attempt limits
```

---

### 13. Question

**File:** `app/Models/Question.php`
**Table:** `questions`
**Traits:** HasFactory, SoftDeletes

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| assessment_id | bigint | FK → assessments |
| question_text | text | Question content |
| question_type | enum | `multiple_choice`, `true_false`, `matching`, `short_answer`, `essay`, `file_upload` |
| points | int | Points for correct answer |
| feedback | text | General feedback |
| order | int | Display order |

#### Relationships
```php
assessment()        → BelongsTo(Assessment)
options()           → HasMany(QuestionOption)  // Ordered by `order`
answers()           → HasMany(AttemptAnswer)
getCorrectOptions() → HasMany(QuestionOption)  // Filtered by is_correct
```

#### Methods
```php
getQuestionTypeLabel(): string  // Indonesian labels
isMultipleChoice(): bool
isTrueFalse(): bool
isMatching(): bool
isShortAnswer(): bool
isEssay(): bool
isFileUpload(): bool
requiresManualGrading(): bool  // essay, short_answer, file_upload, matching
```

---

### 14. QuestionOption

**File:** `app/Models/QuestionOption.php`
**Table:** `question_options`
**Traits:** HasFactory, SoftDeletes

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| question_id | bigint | FK → questions |
| option_text | text | Option content |
| is_correct | boolean | Correct answer flag |
| feedback | text | Option-specific feedback |
| order | int | Display order |

#### Relationships
```php
question() → BelongsTo(Question)
```

---

### 15. AssessmentAttempt

**File:** `app/Models/AssessmentAttempt.php`
**Table:** `assessment_attempts`
**Traits:** HasFactory, SoftDeletes

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| assessment_id | bigint | FK → assessments |
| user_id | bigint | FK → users |
| attempt_number | int | Attempt count for this user |
| status | enum | `in_progress`, `submitted`, `graded`, `completed` |
| score | decimal | Points earned |
| max_score | decimal | Maximum possible points |
| percentage | decimal | Score percentage |
| passed | boolean | Met passing score |
| started_at | timestamp | Start time |
| submitted_at | timestamp | Submission time |
| graded_at | timestamp | Grading completion |
| graded_by | bigint | FK → users (grader) |
| feedback | text | Overall feedback |

#### Relationships
```php
assessment() → BelongsTo(Assessment)
user()       → BelongsTo(User)
gradedBy()   → BelongsTo(User)
answers()    → HasMany(AttemptAnswer)
```

#### Methods
```php
isInProgress(): bool
isSubmitted(): bool
isGraded(): bool
isCompleted(): bool
requiresGrading(): bool
calculateScore(): void  // Auto-grade attempt
completeAttempt(): void
```

---

### 16. AttemptAnswer

**File:** `app/Models/AttemptAnswer.php`
**Table:** `attempt_answers`
**Traits:** HasFactory, SoftDeletes

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| attempt_id | bigint | FK → assessment_attempts |
| question_id | bigint | FK → questions |
| answer_text | text | Learner's answer |
| file_path | string | For file upload questions |
| is_correct | boolean | Correctness flag |
| score | decimal | Points awarded |
| feedback | text | Per-answer feedback |
| graded_by | bigint | FK → users |
| graded_at | timestamp | Grading time |

#### Relationships
```php
attempt()  → BelongsTo(AssessmentAttempt)
question() → BelongsTo(Question)
gradedBy() → BelongsTo(User)
```

#### Methods
```php
isGraded(): bool
isCorrect(): bool
getFileUrl(): ?string
```

---

### 17. LearningPath

**File:** `app/Models/LearningPath.php`
**Table:** `learning_paths`
**Traits:** HasFactory, SoftDeletes

#### Attributes
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| title | string(255) | Learning path title |
| description | text | Description |
| objectives | json | Array of objectives |
| slug | string | URL-friendly identifier |
| created_by | bigint | FK → users |
| updated_by | bigint | FK → users |
| is_published | boolean | Publication status |
| published_at | timestamp | Publication date |
| estimated_duration | int | Total duration in minutes |
| difficulty_level | enum | `beginner`, `intermediate`, `advanced`, `expert` |
| thumbnail_url | string | Cover image URL |

#### Relationships
```php
creator()     → BelongsTo(User)
updater()     → BelongsTo(User)
courses()     → BelongsToMany(Course)  // Pivot: position, is_required, prerequisites, min_completion_percentage
enrollments() → HasManyThrough(Enrollment, Course)
```

#### Scopes
```php
published()  // is_published = true
```

---

## Database Schema

### Migration Files (Chronological)

| Migration | Table | Description |
|-----------|-------|-------------|
| `0001_01_01_000000` | `users` | Core users table |
| `0001_01_01_000001` | `cache` | Framework cache |
| `0001_01_01_000002` | `jobs` | Queue jobs |
| `2025_08_14_170933` | `users` | Add 2FA columns |
| `2025_11_26_175215` | `users` | Add role column |
| `2025_11_26_175446` | `categories` | Course categories |
| `2025_11_26_175509` | `tags` | Content tags |
| `2025_11_26_175534` | `courses` | Main courses |
| `2025_11_26_175603` | `course_sections` | Course sections |
| `2025_11_26_175627` | `lessons` | Lesson content |
| `2025_11_26_175653` | `media` | Media attachments |
| `2025_11_26_175719` | `course_tag` | Course-tag pivot |
| `2025_11_26_193402` | `enrollments` | User enrollments |
| `2025_11_26_193535` | `course_invitations` | Course invitations |
| `2025_11_27_113317` | `lesson_progress` | Progress tracking |
| `2025_11_27_122424` | `lesson_progress` | Add media tracking |
| `2025_11_27_181219` | `course_ratings` | Ratings and reviews |
| `2025_12_22_000001` | `assessments` | Quiz/assessment definitions |
| `2025_12_22_000002` | `questions` | Quiz questions |
| `2025_12_22_000003` | `question_options` | Answer options |
| `2025_12_22_000004` | `assessment_attempts` | Attempt records |
| `2025_12_22_000005` | `attempt_answers` | Learner answers |
| `2025_12_23_115003` | `learning_paths` | Learning paths |
| `2025_12_23_115004` | `learning_path_course` | LP-course pivot |

---

## Factories & Seeders

### Seeders

| Seeder | Purpose | Data Created |
|--------|---------|--------------|
| `DatabaseSeeder` | Main orchestrator | 4 test users (learner, content_manager, trainer, lms_admin) |
| `CategorySeeder` | Course categories | 6 Indonesian categories (IT, Business, Language, Design, Finance, Soft Skills) |
| `TagSeeder` | Content tags | 37 tags covering tech, business, design |
| `CourseSeeder` | Full course structure | 5 courses with sections, lessons, media |

### Test Users

| Role | Email | Name |
|------|-------|------|
| learner | `test@example.com` | Test User |
| content_manager | `content@example.com` | Content Manager |
| trainer | `trainer@example.com` | Trainer |
| lms_admin | `admin@example.com` | LMS Admin |

**Default Password:** `password`

### Factory States

#### CourseFactory
```php
Course::factory()->draft()->create();
Course::factory()->published()->create();
Course::factory()->archived()->create();
Course::factory()->public()->create();
Course::factory()->restricted()->create();
Course::factory()->beginner()->create();
Course::factory()->intermediate()->create();
Course::factory()->advanced()->create();
```

#### EnrollmentFactory
```php
Enrollment::factory()->active()->create();
Enrollment::factory()->completed()->create();
Enrollment::factory()->dropped()->create();
```

#### AssessmentFactory
```php
Assessment::factory()->published()->create();
Assessment::factory()->draft()->create();
Assessment::factory()->archived()->create();
```

#### QuestionFactory
```php
Question::factory()->multipleChoice()->create();
Question::factory()->trueFalse()->create();
Question::factory()->shortAnswer()->create();
Question::factory()->essay()->create();
Question::factory()->matching()->create();
Question::factory()->fileUpload()->create();
```

#### CourseInvitationFactory
```php
CourseInvitation::factory()->pending()->create();
CourseInvitation::factory()->accepted()->create();
CourseInvitation::factory()->declined()->create();
CourseInvitation::factory()->expired()->create();
CourseInvitation::factory()->expiringSoon()->create();
```

#### LearningPathFactory
```php
LearningPath::factory()->published()->create();
LearningPath::factory()->unpublished()->create();
LearningPath::factory()->beginner()->create();
LearningPath::factory()->expert()->create();
```

---

## Quick Reference: Common Queries

### Get user's enrolled courses with progress
```php
$user->enrolledCourses()
    ->withPivot('status', 'progress_percentage', 'last_lesson_id')
    ->get();
```

### Get course with all content
```php
Course::with(['sections.lessons.media', 'category', 'tags'])
    ->find($courseId);
```

### Track lesson progress (using service)
```php
$service = app(ProgressTrackingServiceContract::class);

// Get or create progress
$progress = $service->getOrCreateProgress($enrollment, $lesson);

// Update page progress
$service->updatePageProgress($enrollment, $lesson, $currentPage, $totalPages);

// Update media progress
$service->updateMediaProgress($enrollment, $lesson, $positionSeconds, $durationSeconds);

// Mark lesson complete
$service->markLessonComplete($enrollment, $lesson);

// Calculate course progress
$percentage = $service->calculateProgress($enrollment);
```

### Enroll user (using service)
```php
$service = app(EnrollmentServiceContract::class);
$result = $service->enroll($user, $course);

if ($result->success) {
    $enrollment = $result->enrollment;
}
```

### Check if user can attempt assessment
```php
$assessment->canBeAttemptedBy($user);
```

### Get pending invitations for user
```php
$user->pendingInvitations()
    ->with('course', 'inviter')
    ->get();
```
