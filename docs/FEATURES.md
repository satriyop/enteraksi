# Enteraksi LMS - Feature Flows Documentation

> **Purpose**: This document describes how each feature works end-to-end, from user action to database changes to UI updates. Use this to understand feature behavior before implementing changes or debugging issues.

---

## Table of Contents
1. [Authentication Features](#authentication-features)
2. [Course Management](#course-management)
3. [Lesson & Content Delivery](#lesson--content-delivery)
4. [Progress Tracking](#progress-tracking)
5. [Enrollment & Invitations](#enrollment--invitations)
6. [Assessments & Grading](#assessments--grading)
7. [Learning Paths](#learning-paths)
8. [Ratings & Reviews](#ratings--reviews)

---

## Authentication Features

### User Registration

**Route:** `POST /register`
**Controller:** Fortify (via `CreateNewUser` action)
**Page:** `auth/Register.vue`

**Flow:**
```
1. User fills registration form (name, email, password, password_confirmation)
2. Form submits to Fortify /register endpoint
3. CreateNewUser action validates:
   - name: required, max 255
   - email: required, unique, valid format
   - password: min 8, mixed case, numbers, symbols, confirmed
4. User created with role='learner' (default)
5. Email verification sent
6. Redirect to /dashboard with verification notice
```

**Database Changes:**
- New row in `users` table
- `email_verified_at` = null (until verified)

---

### Login with 2FA

**Routes:** `POST /login`, `POST /two-factor-challenge`
**Pages:** `auth/Login.vue`, `auth/TwoFactorChallenge.vue`

**Flow:**
```
1. User enters email + password
2. Fortify validates credentials
3. IF 2FA enabled:
   a. Redirect to two-factor-challenge
   b. User enters TOTP code from authenticator
   c. Fortify validates code against two_factor_secret
   d. Success: Create session, redirect to /dashboard
4. IF 2FA not enabled:
   - Create session immediately
   - Redirect to /dashboard or intended URL
```

**Rate Limiting:**
- 5 attempts per minute per email+IP
- 5 attempts per minute for 2FA codes

---

### Two-Factor Authentication Setup

**Route:** `GET /settings/two-factor`
**Controller:** `Settings/TwoFactorAuthenticationController@show`
**Page:** `settings/TwoFactor.vue`

**Flow:**
```
1. User navigates to Settings > Two-Factor
2. Click "Enable 2FA"
3. POST /user/two-factor-authentication (Fortify)
4. Fortify generates:
   - TOTP secret (encrypted in two_factor_secret)
   - 8 recovery codes (encrypted in two_factor_recovery_codes)
5. QR code displayed with secret
6. User scans with authenticator app
7. User confirms with TOTP code
8. POST /user/confirmed-two-factor-authentication
9. two_factor_confirmed_at set
10. Recovery codes displayed (one-time view)
```

**Database Changes:**
- `users.two_factor_secret` = encrypted TOTP secret
- `users.two_factor_recovery_codes` = encrypted JSON array
- `users.two_factor_confirmed_at` = timestamp

---

## Course Management

### Create Course (Content Manager/Trainer/Admin)

**Route:** `POST /courses`
**Controller:** `CourseController@store`
**Page:** `courses/Create.vue`
**Request:** `StoreCourseRequest`

**Flow:**
```
1. User clicks "Create Course" (requires canManageCourses)
2. Fill form:
   - title (required)
   - short_description
   - long_description
   - objectives[] (array)
   - prerequisites[] (array)
   - category_id
   - difficulty_level (required: beginner/intermediate/advanced)
   - thumbnail (image upload)
   - tags[] (array of tag IDs)
3. Submit form
4. StoreCourseRequest validates (Indonesian messages)
5. Controller:
   a. Generate unique slug from title
   b. Upload thumbnail to storage/app/public/courses/{id}/
   c. Create Course with user_id = auth user
   d. Attach tags via pivot table
6. Redirect to courses.show with success message
```

**Database Changes:**
- New `courses` row with status='draft', visibility='public'
- New rows in `course_tag` pivot table

**Files Created:**
- `storage/app/public/courses/{id}/thumbnail.{ext}`

---

### Publish Course (LMS Admin Only)

**Route:** `POST /courses/{course}/publish`
**Controller:** `CoursePublishController@publish`
**Policy:** `CoursePolicy@publish` (lms_admin only)

**Flow:**
```
1. LMS Admin views draft course
2. Clicks "Publish" button
3. Controller:
   a. Check policy (must be lms_admin)
   b. Update course:
      - status = 'published'
      - published_at = now()
      - published_by = auth user ID
4. Course now visible to learners
5. Redirect back with success message
```

**Business Rules:**
- Only draft/archived courses can be published
- Published courses cannot be edited (except by LMS Admin)
- Published courses appear in Browse and can be enrolled

---

### Add Section to Course

**Route:** `POST /courses/{course}/sections`
**Controller:** `CourseSectionController@store`
**Request:** `StoreSectionRequest`

**Flow:**
```
1. On course detail page, click "Add Section"
2. Enter title, optional description
3. Submit
4. Controller:
   a. Check can update course
   b. Calculate next order number
   c. Create CourseSection
5. Section appears in course structure
```

**Database Changes:**
- New `course_sections` row
- `order` = max(existing orders) + 1

---

### Add Lesson to Section

**Route:** `POST /sections/{section}/lessons`
**Controller:** `LessonController@store`
**Page:** `lessons/Edit.vue` (create mode)
**Request:** `StoreLessonRequest`

**Flow:**
```
1. Click "Add Lesson" in section
2. Fill form:
   - title (required)
   - description
   - content_type (text/video/youtube/audio/document/conference)
   - Content based on type:
     * text: rich_content (TipTap JSON)
     * youtube: youtube_url
     * video/audio/document: Upload via MediaController
     * conference: conference_url + conference_type
   - estimated_duration_minutes
   - is_free_preview (checkbox)
3. Submit
4. Controller creates lesson with order = max + 1
5. Update section/course estimated duration
```

**Content Type Handling:**
| Type | Storage | Fields Used |
|------|---------|-------------|
| text | Database | rich_content (JSON) |
| youtube | URL reference | youtube_url |
| video | File storage | media (via MediaController) |
| audio | File storage | media (via MediaController) |
| document | File storage | media (via MediaController) |
| conference | URL reference | conference_url, conference_type |

---

### Upload Media (Video/Audio/Document)

**Route:** `POST /media`
**Controller:** `MediaController@store`
**Request:** `StoreMediaRequest`

**Flow:**
```
1. In lesson edit, click upload for video/audio/document
2. Select file (drag-drop or browse)
3. StoreMediaRequest validates by collection_name:
   - video: mp4,webm,mov,avi,mkv, max 512MB
   - audio: mp3,wav,ogg,m4a,aac, max 100MB
   - document: pdf,doc,docx,ppt,pptx,xls,xlsx, max 50MB
   - thumbnail: jpg,jpeg,png,webp, max 5MB
4. Controller:
   a. Generate unique filename
   b. Store in storage/app/public/lessons/{lesson_id}/{collection}/
   c. For video/audio: Extract duration using getID3
   d. Create Media record with morph relationship
5. Return media data for frontend display
```

**Database Changes:**
- New `media` row with:
  - mediable_type = 'App\Models\Lesson'
  - mediable_id = lesson ID
  - path, mime_type, size, duration_seconds

---

### Reorder Sections/Lessons

**Routes:**
- `POST /courses/{course}/sections/reorder`
- `POST /sections/{section}/lessons/reorder`
**Controller:** `CourseReorderController`

**Flow:**
```
1. Drag section/lesson to new position
2. Frontend sends array of IDs in new order
3. Controller updates `order` column for each item
4. Returns success
```

**Request Body:**
```json
{
  "items": [3, 1, 2]  // IDs in desired order
}
```

---

## Lesson & Content Delivery

### View Lesson (Enrolled Learner)

**Route:** `GET /courses/{course}/lessons/{lesson}`
**Controller:** `LessonController@show`
**Page:** `lessons/Show.vue`
**Policy:** `LessonPolicy@view`

**Flow:**
```
1. Learner clicks lesson from course detail or sidebar
2. Policy check:
   - Must have ACTIVE enrollment (not just enrolled)
   - OR is course manager/owner
3. Controller loads:
   - Course with sections/lessons (for sidebar)
   - Lesson with media
   - Enrollment
   - LessonProgress (or creates new)
   - Previous/next lesson for navigation
4. Render based on content_type:
   - text: PaginatedTextContent (paginate long content)
   - youtube: YouTubePlayer component
   - video: HTML5 video with custom controls
   - audio: Audio player
   - document: PDF viewer or download link
   - conference: Link to join session
```

**Props to Frontend:**
```typescript
{
  course: Course,
  lesson: Lesson,
  enrollment: Enrollment,
  lessonProgress: LessonProgress | null,
  prevLesson: Lesson | null,
  nextLesson: Lesson | null,
  allLessons: Lesson[]
}
```

---

### Free Preview (Non-Enrolled)

**Route:** `GET /courses/{course}/lessons/{lesson}/preview`
**Controller:** `LessonPreviewController@show`
**Page:** `courses/LessonPreview.vue`

**Flow:**
```
1. Non-enrolled user clicks "Preview" on free lesson
2. Controller checks lesson.is_free_preview = true
3. Render lesson content (read-only, no progress tracking)
4. Show CTA to enroll in full course
```

**Authorization:**
- No enrollment required
- Lesson must have `is_free_preview = true`

---

## Progress Tracking

Progress tracking is handled by `ProgressTrackingService` which provides a clean API for all progress operations.

### Update Page Progress (Text/Document)

**Route:** `PATCH /courses/{course}/lessons/{lesson}/progress`
**Controller:** `LessonProgressController@update`

**Flow:**
```
1. User scrolls through text content or PDF pages
2. Frontend tracks current page (debounced 500ms)
3. Send PATCH request:
   {
     "current_page": 3,
     "total_pages": 10,
     "pagination_metadata": { "scrollPosition": 450 }
   }
4. Controller calls ProgressTrackingService:
   $service->updatePageProgress($enrollment, $lesson, $page, $totalPages);
   - Updates current_page, total_pages
   - Updates highest_page_reached if higher
   - Auto-completes at last page
   - Dispatches ProgressUpdated event
   - Recalculates course progress
```

**Auto-Completion:**
- When `current_page >= total_pages`, lesson marked complete

---

### Update Media Progress (Video/Audio)

**Route:** `PATCH /courses/{course}/lessons/{lesson}/progress/media`
**Controller:** `LessonProgressController@updateMedia`

**Flow:**
```
1. User plays video/audio
2. Frontend tracks position every 5 seconds + on pause
3. Send PATCH request:
   {
     "position_seconds": 125,
     "duration_seconds": 300
   }
4. Controller calls ProgressTrackingService:
   $service->updateMediaProgress($enrollment, $lesson, $position, $duration);
   - Updates media_position_seconds, media_duration_seconds
   - Calculates media_progress_percentage
   - Auto-completes at 90%
   - Dispatches ProgressUpdated event
```

**Auto-Completion:**
- When `media_progress_percentage >= 90%`, lesson marked complete

---

### Manual Completion

**Route:** `POST /courses/{course}/lessons/{lesson}/complete`
**Controller:** `LessonProgressController@complete`

**Flow:**
```
1. User clicks "Mark as Complete" button
2. Controller calls ProgressTrackingService:
   $service->markLessonComplete($enrollment, $lesson);
   - Sets is_completed = true, completed_at = now()
   - Dispatches LessonCompleted event
   - Recalculates course progress
3. If course complete, dispatches EnrollmentCompleted event
```

---

### Course Progress Calculation

**Service:** `ProgressTrackingService::calculateProgress()`

Progress calculation uses swappable **strategy patterns**:

| Strategy | Algorithm | Use Case |
|----------|-----------|----------|
| `LessonBasedProgressCalculator` | `(completed lessons / total lessons) * 100` | Simple courses |
| `AssessmentInclusiveProgressCalculator` | `(lessons * 0.7) + (assessments * 0.3)` | Courses with required assessments |
| `WeightedProgressCalculator` | Custom weights per section | Complex curricula |

**Assessment-Inclusive Calculation:**
```php
// Only required assessments (is_required = true) affect progress
$lessonProgress = (completedLessons / totalLessons) * 100;
$assessmentProgress = (passedRequiredAssessments / totalRequiredAssessments) * 100;
$totalProgress = ($lessonProgress * 0.7) + ($assessmentProgress * 0.3);
```

**Completion Check:**
- All lessons must be completed
- All **required** assessments must be passed (optional assessments don't block completion)

---

## Enrollment & Invitations

Enrollment is handled by `EnrollmentService` which manages the enrollment lifecycle.

### Self-Enrollment (Public Course)

**Route:** `POST /courses/{course}/enroll`
**Controller:** `EnrollmentController@store`
**Policy:** `CoursePolicy@enroll`

**Flow:**
```
1. Learner views published public course
2. Clicks "Enroll" button
3. Policy checks:
   - Course must be published
   - User not already enrolled
   - Course is public OR user has pending invitation
4. Controller calls EnrollmentService:
   $result = $service->enroll($user, $course);
   - Creates enrollment with status = 'active'
   - Dispatches UserEnrolled event
   - Triggers SendWelcomeNotification listener
5. Redirect to first lesson or course detail
```

**Database Changes:**
- New `enrollments` row

**Domain Events:**
- `UserEnrolled` - Triggers welcome notification

---

### Send Course Invitation

**Route:** `POST /courses/{course}/invitations`
**Controller:** `CourseInvitationController@store`
**Request:** `StoreCourseInvitationRequest`

**Flow:**
```
1. Course owner/trainer/admin opens Invitations tab
2. Search for learner by email/name
3. Select learner, add optional message and expiry
4. Submit
5. Request validates:
   - User exists and is learner role
   - Not already enrolled
   - No duplicate pending invitation
6. Create CourseInvitation:
   - status = 'pending'
   - invited_by = auth user
7. (Future: Send email notification)
```

---

### Bulk Invite via CSV

**Route:** `POST /courses/{course}/invitations/bulk`
**Controller:** `CourseInvitationController@bulkStore`
**Request:** `BulkCourseInvitationRequest`

**Flow:**
```
1. Click "Import CSV" button
2. Upload CSV file (max 2MB)
3. CSV format: email column required
4. Controller:
   a. Parse CSV
   b. For each email:
      - Find user by email
      - Skip if not learner, already enrolled, or already invited
      - Create invitation
   c. Return summary (invited, skipped, errors)
```

**CSV Format:**
```csv
email
john@example.com
jane@example.com
```

---

### Accept Invitation

**Route:** `POST /invitations/{invitation}/accept`
**Controller:** `EnrollmentController@acceptInvitation`

**Flow:**
```
1. Learner sees invitation on dashboard
2. Clicks "Accept"
3. Controller:
   a. Verify invitation is pending and not expired
   b. Update invitation: status='accepted', responded_at=now()
   c. Create enrollment:
      - invited_by = invitation.invited_by
      - status = 'active'
4. Redirect to course detail
```

---

### Decline Invitation

**Route:** `POST /invitations/{invitation}/decline`
**Controller:** `EnrollmentController@declineInvitation`

**Flow:**
```
1. Learner clicks "Decline"
2. Controller updates invitation:
   - status = 'declined'
   - responded_at = now()
3. Invitation removed from dashboard
```

---

## Assessments & Grading

### Create Assessment

**Route:** `POST /courses/{course}/assessments`
**Controller:** `AssessmentController@store`
**Request:** `StoreAssessmentRequest`

**Flow:**
```
1. Navigate to course assessments
2. Click "Create Assessment"
3. Fill form:
   - title, description, instructions
   - time_limit_minutes (optional)
   - passing_score (0-100)
   - max_attempts (1-10)
   - shuffle_questions, show_correct_answers, allow_review
   - status (draft), visibility
4. Submit
5. Controller creates assessment with auto-generated slug
6. Redirect to questions management
```

---

### Add Questions

**Route:** `PUT /courses/{course}/assessments/{assessment}/questions`
**Controller:** `QuestionController@bulkUpdate`

**Flow:**
```
1. On Questions page, click "Add Question"
2. Select question type:
   - multiple_choice: Add options, mark correct
   - true_false: Two options (True/False)
   - short_answer: Text input expected
   - essay: Long text expected
   - matching: Pairs to match
   - file_upload: File submission
3. Enter question_text, points, feedback
4. For MC/TF: Add options with is_correct flags
5. Submit (bulk update)
6. Controller creates/updates questions with order
```

**Question Types:**
| Type | Auto-Gradable | Options Required |
|------|---------------|------------------|
| multiple_choice | Yes | Yes |
| true_false | Yes | Yes (2) |
| short_answer | No | No |
| essay | No | No |
| matching | No | No |
| file_upload | No | No |

---

### Start Assessment Attempt

**Route:** `POST /courses/{course}/assessments/{assessment}/start`
**Controller:** `AssessmentController@startAttempt`

**Flow:**
```
1. Learner clicks "Start Assessment"
2. Controller checks canBeAttemptedBy():
   - Assessment is published
   - User is enrolled in course
   - Attempts < max_attempts
3. Create AssessmentAttempt:
   - attempt_number = previous attempts + 1
   - status = 'in_progress'
   - started_at = now()
4. Redirect to attempt page
```

---

### Take Assessment

**Route:** `GET /courses/{course}/assessments/{assessment}/attempts/{attempt}`
**Controller:** `AssessmentController@attempt`
**Page:** `assessments/Attempt.vue`

**Flow:**
```
1. Load attempt with questions
2. If shuffle_questions, randomize order
3. Display:
   - Timer (if time_limit_minutes set)
   - Questions with appropriate input types
   - Navigation grid
4. User answers questions
5. Frontend tracks answers locally
```

---

### Submit Assessment

**Route:** `POST /courses/{course}/assessments/{assessment}/attempts/{attempt}/submit`
**Controller:** `AssessmentController@submitAttempt`

**Flow:**
```
1. User clicks "Submit" (or timer expires)
2. Send all answers:
   ```json
   {
     "answers": {
       "question_id": "answer_value",
       ...
     }
   }
   ```
3. Controller:
   a. Create AttemptAnswer for each question
   b. Update attempt: status='submitted', submitted_at=now()
   c. Auto-grade gradable questions (MC, TF)
   d. If all auto-gradable, complete attempt
4. Redirect to completion page
```

**Auto-Grading:**
```php
// For multiple_choice/true_false
$correctOption = $question->options()->where('is_correct', true)->first();
$isCorrect = $answer->answer_text === $correctOption->id;
$score = $isCorrect ? $question->points : 0;
```

---

### Manual Grading

**Route:** `POST /courses/{course}/assessments/{assessment}/attempts/{attempt}/grade`
**Controller:** `AssessmentController@submitGrade`
**Page:** `assessments/Grade.vue`

**Flow:**
```
1. Instructor/Admin views attempt needing grading
2. For each ungraded answer (essay, short_answer, etc.):
   - Read student's answer
   - Assign score (0 to question.points)
   - Add optional feedback
3. Submit grades
4. Controller:
   a. Update each AttemptAnswer:
      - score, feedback, graded_by, graded_at
   b. Recalculate attempt totals
   c. Check if passed (percentage >= passing_score)
   d. Update attempt: status='graded'/'completed'
```

---

## Learning Paths

### Create Learning Path

**Route:** `POST /learning-paths`
**Controller:** `LearningPathController@store`
**Request:** `StoreLearningPathRequest`

**Flow:**
```
1. Navigate to Learning Paths
2. Click "Create Learning Path"
3. Fill form:
   - title, description, objectives[]
   - difficulty_level
   - thumbnail (optional)
   - courses[] with:
     * course.id
     * is_required
     * prerequisites (text)
     * min_completion_percentage
4. Submit
5. Controller:
   a. Generate slug
   b. Create LearningPath
   c. Attach courses with pivot data and position
```

**Pivot Table Data:**
```php
courses()->attach($courseId, [
    'position' => $index,
    'is_required' => true/false,
    'prerequisites' => 'Complete Course X first',
    'min_completion_percentage' => 80
]);
```

---

### Reorder Learning Path Courses

**Route:** `POST /learning-paths/{learning_path}/reorder`
**Controller:** `LearningPathController@reorder`

**Flow:**
```
1. Drag course to new position
2. Send array of course IDs in order
3. Controller updates pivot.position for each
```

---

## Ratings & Reviews

### Submit Course Rating

**Route:** `POST /courses/{course}/ratings`
**Controller:** `CourseRatingController@store`
**Request:** `StoreRatingRequest`
**Policy:** `CourseRatingPolicy@create`

**Flow:**
```
1. Enrolled learner views course detail
2. Sees rating form (if not already rated)
3. Select stars (1-5)
4. Add optional review text (max 1000 chars)
5. Submit
6. Policy checks:
   - User is enrolled
   - User hasn't already rated
7. Create CourseRating
8. Course averageRating recalculated (accessor)
```

---

### Update Rating

**Route:** `PATCH /courses/{course}/ratings/{rating}`
**Controller:** `CourseRatingController@update`
**Policy:** `CourseRatingPolicy@update` (owner only)

**Flow:**
```
1. User clicks "Edit" on their rating
2. Modify stars and/or review
3. Submit
4. Controller updates rating
```

---

### Delete Rating

**Route:** `DELETE /courses/{course}/ratings/{rating}`
**Controller:** `CourseRatingController@destroy`
**Policy:** `CourseRatingPolicy@delete` (owner or admin)

**Flow:**
```
1. User or admin clicks "Delete"
2. Confirmation dialog
3. Controller soft-deletes rating
4. Course average recalculated
```

---

## Route Quick Reference

### Public Routes
| Route | Method | Controller | Auth |
|-------|--------|------------|------|
| `/` | GET | HomeController@index | No |
| `/login` | GET/POST | Fortify | No |
| `/register` | GET/POST | Fortify | No |
| `/forgot-password` | GET/POST | Fortify | No |

### Authenticated Routes (All require `auth`, `verified`)

#### Dashboard
| Route | Method | Controller |
|-------|--------|------------|
| `/dashboard` | GET | DashboardController |
| `/learner/dashboard` | GET | LearnerDashboardController |

#### Courses
| Route | Method | Controller |
|-------|--------|------------|
| `/courses` | GET | CourseController@index |
| `/courses/create` | GET | CourseController@create |
| `/courses` | POST | CourseController@store |
| `/courses/{course}` | GET | CourseController@show |
| `/courses/{course}/edit` | GET | CourseController@edit |
| `/courses/{course}` | PUT | CourseController@update |
| `/courses/{course}` | DELETE | CourseController@destroy |
| `/courses/{course}/publish` | POST | CoursePublishController@publish |
| `/courses/{course}/unpublish` | POST | CoursePublishController@unpublish |
| `/courses/{course}/archive` | POST | CoursePublishController@archive |

#### Lessons
| Route | Method | Controller |
|-------|--------|------------|
| `/courses/{course}/lessons/{lesson}` | GET | LessonController@show |
| `/courses/{course}/lessons/{lesson}/preview` | GET | LessonPreviewController@show |
| `/courses/{course}/lessons/{lesson}/progress` | PATCH | LessonProgressController@update |
| `/courses/{course}/lessons/{lesson}/progress/media` | PATCH | LessonProgressController@updateMedia |
| `/courses/{course}/lessons/{lesson}/complete` | POST | LessonProgressController@complete |

#### Enrollments & Invitations
| Route | Method | Controller |
|-------|--------|------------|
| `/courses/{course}/enroll` | POST | EnrollmentController@store |
| `/courses/{course}/unenroll` | DELETE | EnrollmentController@destroy |
| `/invitations/{invitation}/accept` | POST | EnrollmentController@acceptInvitation |
| `/invitations/{invitation}/decline` | POST | EnrollmentController@declineInvitation |
| `/courses/{course}/invitations` | POST | CourseInvitationController@store |
| `/courses/{course}/invitations/bulk` | POST | CourseInvitationController@bulkStore |

#### Assessments
| Route | Method | Controller |
|-------|--------|------------|
| `/courses/{course}/assessments` | GET | AssessmentController@index |
| `/courses/{course}/assessments/{assessment}/start` | POST | AssessmentController@startAttempt |
| `/courses/{course}/assessments/{assessment}/attempts/{attempt}` | GET | AssessmentController@attempt |
| `/courses/{course}/assessments/{assessment}/attempts/{attempt}/submit` | POST | AssessmentController@submitAttempt |
| `/courses/{course}/assessments/{assessment}/attempts/{attempt}/grade` | POST | AssessmentController@submitGrade |

#### Learning Paths
| Route | Method | Controller |
|-------|--------|------------|
| `/learning-paths` | GET | LearningPathController@index |
| `/learning-paths` | POST | LearningPathController@store |
| `/learning-paths/{learning_path}` | GET | LearningPathController@show |
| `/learning-paths/{learning_path}` | PUT | LearningPathController@update |
| `/learning-paths/{learning_path}/publish` | PUT | LearningPathController@publish |
| `/learning-paths/{learning_path}/reorder` | POST | LearningPathController@reorder |

---

## Error Handling Patterns

### Validation Errors
- Return 422 with field-specific Indonesian messages
- Inertia displays via `errors` prop

### Authorization Failures
- Return 403 Forbidden
- Redirect with error flash message

### Not Found
- Return 404
- Inertia error page or redirect

### Business Logic Errors
- Flash error message
- Redirect back with error state
