# Course Management Implementation Plan

**Date:** 2025-11-27
**Project:** LMS E-Learning Application
**Feature:** Course Management (based on course-story.md)

---

## Phase Summary

| Phase | Description | Status |
|-------|-------------|--------|
| Day 1 | Core Course Management | ✅ ~90% Complete |
| Day 2 | Progress, Dashboard, Rating, Assessment Foundation | 🔄 In Progress |
| Day 3 | Assessment Taking, Learning Paths | ⏳ Pending |
| Day 4 | Competencies & Certificates | ⏳ Pending |
| Day 5 | Gamification (XP, Badges) | ⏳ Pending |

---

# Day 1: Core Course Management (COMPLETED)

## Completed Features

| Feature | Status | Notes |
|---------|--------|-------|
| Course CRUD | ✅ Complete | Full CRUD with thumbnail, tags, categories |
| Course Sections | ✅ Complete | Drag-drop ordering, duration calculation |
| Lessons | ✅ Complete | All 6 content types (text, video, youtube, audio, document, conference) |
| Enrollment System | ✅ Complete | Enroll/unenroll, invitation workflow |
| Course Publishing | ✅ Complete | Draft → Published → Archived workflow |
| Course Visibility | ✅ Complete | Public, Restricted, Hidden |
| Authorization | ✅ Complete | Role-based policies (learner, content_manager, lms_admin) |
| Learner Course Browse | ✅ Complete | Search, filter by category/difficulty |
| Lesson Viewing | ✅ Complete | Udemy-style immersive layout with sidebar |
| Free Preview | ✅ Complete | is_free_preview flag working |

## Existing Database Schema

### Core Tables
- `users` (with role: learner, content_manager, trainer, lms_admin)
- `categories`
- `tags`
- `courses` (status, visibility, difficulty, objectives, prerequisites)
- `course_sections` (order, duration)
- `lessons` (content_type, rich_content, order)
- `course_tag` (pivot)
- `media` (polymorphic)
- `enrollments` (status, progress_percentage, last_lesson_id)
- `course_invitations`
- `lesson_progress` (migration exists, not fully implemented)

### Existing Controllers
- CourseController (CRUD)
- CourseSectionController
- LessonController
- CourseReorderController
- CoursePublishController
- EnrollmentController
- LessonPreviewController

---

# Day 2: Progress, Dashboard, Rating, Assessment (IN PROGRESS)

## Partially Implemented Features

| Feature | Status | What's Missing |
|---------|--------|----------------|
| Lesson Progress | 🟡 60% | Migration exists, but progress not updating |
| Learner Dashboard | 🟡 40% | Missing "My Learning", "Invited Courses" sections |
| Course Rating | 🟡 0% | Not started |
| Duration Re-estimation | 🟡 80% | Re-estimate button not implemented |

---

## Priority 1: Lesson Progress Tracking

**Problem**: `lesson_progress` migration exists but progress is not being tracked when learners view lessons.

### Tasks
1. Create `LessonProgress` model with relationships
2. Update `LessonController@show` to record/update lesson progress
3. Calculate progress based on content type:
   - Text: Mark complete on view
   - Video/Audio: Track watch time, complete at 90%+
   - Document: Mark complete on view
   - YouTube: Mark complete on view
4. Update enrollment `progress_percentage` based on completed lessons
5. Add "Tandai Selesai" (Mark as Complete) button
6. Update sidebar to show completed lessons with checkmark

### Files to Create/Modify
```
app/Models/LessonProgress.php                    (create)
app/Http/Controllers/LessonProgressController.php (create)
app/Http/Controllers/LessonController.php        (modify show)
resources/js/Pages/lessons/Show.vue              (add complete button)
routes/courses.php                               (add routes)
tests/Feature/LessonProgressTest.php             (create)
```

### Database Schema (Already Exists)
```sql
-- lesson_progress table
id, user_id, lesson_id, enrollment_id, progress_percentage,
is_completed, completed_at, time_spent_seconds, last_position, timestamps
```

### API Endpoints
```
POST   /lessons/{lesson}/progress          # Update progress
POST   /lessons/{lesson}/complete          # Mark as complete
GET    /courses/{course}/my-progress       # Get user's progress for course
```

---

## Priority 2: Enhance Learner Dashboard

**Problem**: Learner landing page missing "My Learning" and "Invited Courses" sections.

### Tasks
1. Enhance `LearnerDashboardController`
2. Add "My Learning" section:
   - Enrolled courses with progress bars
   - "Lanjutkan Belajar" (Continue Learning) button
   - Filter: In Progress / Completed
3. Add "Undangan Kursus" (Invited Courses) section
4. Add carousel of featured courses (top 5)

### Files to Create/Modify
```
app/Http/Controllers/LearnerDashboardController.php  (modify)
resources/js/Pages/learner/Dashboard.vue             (modify/create)
resources/js/Pages/courses/Browse.vue                (add carousel)
resources/js/components/courses/CourseCarousel.vue   (create)
resources/js/components/courses/MyLearningCard.vue   (create)
```

### Data Structure
```typescript
interface MyLearningCourse {
  id: number;
  title: string;
  thumbnail_url: string;
  progress_percentage: number;
  last_lesson: { id: number; title: string } | null;
  total_lessons: number;
  completed_lessons: number;
}

interface InvitedCourse {
  id: number;
  title: string;
  thumbnail_url: string;
  invited_by: string;
  invited_at: string;
  invitation_id: number;
}
```

---

## Priority 3: Course Rating System

**Problem**: Learners cannot rate courses.

### Tasks
1. Create `course_ratings` migration
2. Create `CourseRating` model
3. Create `CourseRatingController`
4. Update `Course` model (add average rating accessor)
5. Add rating UI in course detail page
6. Show ratings in course browse/listing

### Files to Create
```
database/migrations/xxxx_create_course_ratings_table.php
app/Models/CourseRating.php
app/Http/Controllers/CourseRatingController.php
app/Http/Requests/StoreCourseRatingRequest.php
resources/js/components/courses/CourseRating.vue
resources/js/components/courses/StarRating.vue
tests/Feature/CourseRatingTest.php
```

### Database Schema
```sql
CREATE TABLE course_ratings (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    course_id BIGINT REFERENCES courses(id),
    rating TINYINT CHECK (rating >= 1 AND rating <= 5),
    review TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(user_id, course_id)
);
```

### API Endpoints
```
GET    /courses/{course}/ratings          # List ratings
POST   /courses/{course}/ratings          # Create rating
PATCH  /courses/{course}/ratings/{rating} # Update rating
DELETE /courses/{course}/ratings/{rating} # Delete rating
```

---

## Priority 4: Assessment Module (Foundation)

**Problem**: No quiz/assessment functionality exists.

### Tasks - Phase 1 (Foundation)
1. Create database schema (5 tables)
2. Create Models (5 models)
3. Create Assessment CRUD for Content Managers
4. Question management within assessment

### Files to Create
```
# Migrations
database/migrations/xxxx_create_assessments_table.php
database/migrations/xxxx_create_questions_table.php
database/migrations/xxxx_create_question_options_table.php
database/migrations/xxxx_create_assessment_attempts_table.php
database/migrations/xxxx_create_attempt_answers_table.php

# Models
app/Models/Assessment.php
app/Models/Question.php
app/Models/QuestionOption.php
app/Models/AssessmentAttempt.php
app/Models/AttemptAnswer.php

# Controllers
app/Http/Controllers/AssessmentController.php
app/Http/Controllers/QuestionController.php

# Form Requests
app/Http/Requests/Assessment/StoreAssessmentRequest.php
app/Http/Requests/Assessment/UpdateAssessmentRequest.php
app/Http/Requests/Question/StoreQuestionRequest.php

# Policies
app/Policies/AssessmentPolicy.php

# Vue Pages
resources/js/Pages/assessments/Index.vue
resources/js/Pages/assessments/Create.vue
resources/js/Pages/assessments/Edit.vue
resources/js/Pages/assessments/Show.vue

# Tests
tests/Feature/AssessmentCrudTest.php
tests/Feature/QuestionCrudTest.php
```

### Database Schema
```sql
-- assessments
CREATE TABLE assessments (
    id BIGINT PRIMARY KEY,
    course_id BIGINT NULL REFERENCES courses(id),
    section_id BIGINT NULL REFERENCES course_sections(id),
    lesson_id BIGINT NULL REFERENCES lessons(id),
    title VARCHAR(255),
    description TEXT NULL,
    type ENUM('quiz', 'exam', 'assignment'),
    passing_score INT DEFAULT 70,
    time_limit_minutes INT NULL,
    max_attempts INT NULL,
    shuffle_questions BOOLEAN DEFAULT FALSE,
    shuffle_options BOOLEAN DEFAULT FALSE,
    show_results BOOLEAN DEFAULT TRUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- questions
CREATE TABLE questions (
    id BIGINT PRIMARY KEY,
    assessment_id BIGINT REFERENCES assessments(id) ON DELETE CASCADE,
    type ENUM('single_choice', 'multiple_choice', 'true_false'),
    content TEXT,
    explanation TEXT NULL,
    points INT DEFAULT 1,
    order INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- question_options
CREATE TABLE question_options (
    id BIGINT PRIMARY KEY,
    question_id BIGINT REFERENCES questions(id) ON DELETE CASCADE,
    content TEXT,
    is_correct BOOLEAN DEFAULT FALSE,
    order INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- assessment_attempts
CREATE TABLE assessment_attempts (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    assessment_id BIGINT REFERENCES assessments(id),
    score DECIMAL(5,2) NULL,
    total_points INT,
    earned_points INT,
    status ENUM('in_progress', 'completed', 'abandoned'),
    started_at TIMESTAMP,
    completed_at TIMESTAMP NULL,
    time_spent_seconds INT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- attempt_answers
CREATE TABLE attempt_answers (
    id BIGINT PRIMARY KEY,
    attempt_id BIGINT REFERENCES assessment_attempts(id) ON DELETE CASCADE,
    question_id BIGINT REFERENCES questions(id),
    selected_options JSON,
    is_correct BOOLEAN,
    points_earned INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Question Types
1. **Single Choice** - Select one correct answer
2. **Multiple Choice** - Select multiple correct answers
3. **True/False** - Binary choice

---

## Implementation Order

```
Day 2 Sprint:
├── 1. Lesson Progress Tracking (2-3 hours)
│   ├── LessonProgress model
│   ├── Progress controller & routes
│   ├── Update enrollment progress calculation
│   ├── UI: Complete button, sidebar checkmarks
│   └── Tests
│
├── 2. Learner Dashboard Enhancement (2-3 hours)
│   ├── My Learning section with progress
│   ├── Invited courses section
│   ├── Continue learning feature
│   └── Featured courses carousel
│
├── 3. Course Rating (1-2 hours)
│   ├── Migration & Model
│   ├── Controller & Routes
│   ├── StarRating & CourseRating components
│   └── Tests
│
└── 4. Assessment Foundation (4-6 hours)
    ├── All 5 migrations
    ├── All 5 models with relationships
    ├── AssessmentController (CRUD)
    ├── QuestionController (nested CRUD)
    ├── Assessment management UI
    └── Tests
```

---

## Technical Notes

### Lesson Progress Calculation
```php
// Calculate enrollment progress
public function calculateEnrollmentProgress(Enrollment $enrollment): int
{
    $totalLessons = $enrollment->course->lessons()->count();
    if ($totalLessons === 0) return 0;

    $completedLessons = LessonProgress::where('enrollment_id', $enrollment->id)
        ->where('is_completed', true)
        ->count();

    return (int) round(($completedLessons / $totalLessons) * 100);
}
```

### Assessment Linking Strategy
Assessments can be linked at 3 levels:
1. **Course level**: Final exam (course_id set, others null)
2. **Section level**: Section quiz (section_id set)
3. **Lesson level**: Quick quiz (lesson_id set)

### Model Relationships
```php
// Assessment
public function course(): BelongsTo
public function section(): BelongsTo
public function lesson(): BelongsTo
public function questions(): HasMany
public function attempts(): HasMany

// Question
public function assessment(): BelongsTo
public function options(): HasMany

// User
public function assessmentAttempts(): HasMany
public function lessonProgress(): HasMany
```

---

## Testing Requirements

Each feature requires:
1. **Feature Tests**: HTTP tests for controller actions
2. **Unit Tests**: Model relationship and calculation tests
3. **Authorization Tests**: Policy enforcement

### Test Cases Needed
```
# Lesson Progress Tests
- test_enrolled_user_can_mark_lesson_complete
- test_progress_updates_when_lesson_completed
- test_enrollment_progress_calculates_correctly
- test_unenrolled_user_cannot_mark_progress

# Rating Tests
- test_enrolled_user_can_rate_course
- test_user_can_only_rate_once
- test_average_rating_calculates_correctly

# Assessment Tests
- test_content_manager_can_create_assessment
- test_can_add_questions_to_assessment
- test_question_options_saved_correctly
- test_learner_cannot_create_assessment
```

---

# Future Phases

## Day 3 - Assessment Taking & Learning Paths
- Assessment taking UI for learners
- Auto-grading logic
- Learning Path CRUD
- Learning Path enrollment

## Day 4 - Competencies & Certificates
- Competency framework models
- Grading scale management
- Certificate templates
- Auto-certificate on completion

## Day 5 - Gamification
- Experience points system
- Badge definitions
- Badge awarding logic
- Leaderboard (optional)

---

## Appendix: Day 1 Implementation Reference

### Technical Decisions Made
| Decision | Choice | Rationale |
|----------|--------|-----------|
| RBAC | Laravel Gates/Policies | Sufficient for requirements |
| File Storage | Local storage | Can migrate to S3 later |
| WYSIWYG Editor | TipTap | Vue 3 native, stores JSON |
| Drag & Drop | VueDraggable | Vue 3 support |

### Existing File Structure
```
app/Models/
├── Category.php ✅
├── Tag.php ✅
├── Course.php ✅
├── CourseSection.php ✅
├── Lesson.php ✅
├── Media.php ✅
├── Enrollment.php ✅
└── CourseInvitation.php ✅

app/Http/Controllers/
├── CourseController.php ✅
├── CourseSectionController.php ✅
├── LessonController.php ✅
├── CourseReorderController.php ✅
├── CoursePublishController.php ✅
├── EnrollmentController.php ✅
└── LessonPreviewController.php ✅

app/Policies/
├── CoursePolicy.php ✅
└── LessonPolicy.php ✅

resources/js/Pages/
├── courses/
│   ├── Index.vue ✅
│   ├── Create.vue ✅
│   ├── Edit.vue ✅
│   ├── Detail.vue ✅
│   └── Browse.vue ✅
└── lessons/
    ├── Show.vue ✅ (Udemy-style)
    └── Edit.vue ✅
```
