# API Reference

HTTP endpoints for Enteraksi LMS. All routes use Inertia.js for rendering, not JSON API responses.

> **Note:** This is not a REST API. Enteraksi uses Inertia.js which returns HTML/Inertia responses. For programmatic access, a REST API is planned for future development.

---

## Authentication

All routes (except public pages) require authentication via Laravel session.

### Headers

```
Cookie: laravel_session=...
X-XSRF-TOKEN: ...
```

### Authentication Endpoints (Fortify)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/login` | Login page |
| POST | `/login` | Authenticate user |
| POST | `/logout` | Logout user |
| GET | `/register` | Registration page |
| POST | `/register` | Create account |
| GET | `/forgot-password` | Password reset request page |
| POST | `/forgot-password` | Send reset email |
| GET | `/reset-password/{token}` | Password reset page |
| POST | `/reset-password` | Reset password |
| GET | `/two-factor-challenge` | 2FA challenge page |
| POST | `/two-factor-challenge` | Verify 2FA code |

---

## Public Routes

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| GET | `/` | HomeController@index | Landing page |

---

## Dashboard Routes

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| GET | `/dashboard` | DashboardController | Admin/Instructor dashboard |
| GET | `/learner/dashboard` | LearnerDashboardController | Learner dashboard |

---

## Course Routes

### Course CRUD

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| GET | `/courses` | CourseController@index | List courses |
| GET | `/courses/create` | CourseController@create | Create form |
| POST | `/courses` | CourseController@store | Store new course |
| GET | `/courses/{course}` | CourseController@show | View course |
| GET | `/courses/{course}/edit` | CourseController@edit | Edit form |
| PUT | `/courses/{course}` | CourseController@update | Update course |
| DELETE | `/courses/{course}` | CourseController@destroy | Delete course |

### Course Status

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| POST | `/courses/{course}/publish` | CoursePublishController@publish | Publish course |
| POST | `/courses/{course}/unpublish` | CoursePublishController@unpublish | Unpublish |
| POST | `/courses/{course}/archive` | CoursePublishController@archive | Archive |
| PATCH | `/courses/{course}/status` | CoursePublishController@updateStatus | Update status |
| PATCH | `/courses/{course}/visibility` | CoursePublishController@updateVisibility | Update visibility |

### Course Reorder

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| POST | `/courses/{course}/sections/reorder` | CourseReorderController@sections | Reorder sections |
| POST | `/sections/{section}/lessons/reorder` | CourseReorderController@lessons | Reorder lessons |

### Course Duration

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| POST | `/courses/{course}/recalculate-duration` | CourseDurationController@recalculate | Recalculate |

---

## Section Routes

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| POST | `/courses/{course}/sections` | CourseSectionController@store | Create section |
| PATCH | `/sections/{section}` | CourseSectionController@update | Update section |
| DELETE | `/sections/{section}` | CourseSectionController@destroy | Delete section |

---

## Lesson Routes

### Lesson CRUD

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| GET | `/sections/{section}/lessons/create` | LessonController@create | Create form |
| POST | `/sections/{section}/lessons` | LessonController@store | Store lesson |
| GET | `/lessons/{lesson}/edit` | LessonController@edit | Edit form |
| PATCH | `/lessons/{lesson}` | LessonController@update | Update lesson |
| DELETE | `/lessons/{lesson}` | LessonController@destroy | Delete lesson |

### Lesson Viewing

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| GET | `/courses/{course}/lessons/{lesson}` | LessonController@show | View lesson |
| GET | `/courses/{course}/lessons/{lesson}/preview` | LessonPreviewController@show | Free preview |

### Lesson Progress

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| PATCH | `/courses/{course}/lessons/{lesson}/progress` | LessonProgressController@update | Update page progress |
| PATCH | `/courses/{course}/lessons/{lesson}/progress/media` | LessonProgressController@updateMedia | Update media progress |
| POST | `/courses/{course}/lessons/{lesson}/complete` | LessonProgressController@complete | Mark complete |

**Progress Update Request Body:**
```json
{
  "current_page": 3,
  "total_pages": 10,
  "pagination_metadata": {}
}
```

**Media Progress Request Body:**
```json
{
  "position_seconds": 125,
  "duration_seconds": 300
}
```

---

## Enrollment Routes

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| POST | `/courses/{course}/enroll` | EnrollmentController@store | Enroll in course |
| DELETE | `/courses/{course}/unenroll` | EnrollmentController@destroy | Unenroll |

---

## Invitation Routes

### Manage Invitations

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| POST | `/courses/{course}/invitations` | CourseInvitationController@store | Send invitation |
| POST | `/courses/{course}/invitations/bulk` | CourseInvitationController@bulkStore | Bulk CSV import |
| DELETE | `/courses/{course}/invitations/{invitation}` | CourseInvitationController@destroy | Cancel invitation |

### Respond to Invitations

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| POST | `/invitations/{invitation}/accept` | EnrollmentController@acceptInvitation | Accept |
| POST | `/invitations/{invitation}/decline` | EnrollmentController@declineInvitation | Decline |

### Search Learners

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| GET | `/api/users/search` | CourseInvitationController@searchLearners | Search learners |

**Query Parameters:**
- `q` - Search query (name or email)
- `course_id` - Filter by course (exclude enrolled)

---

## Rating Routes

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| POST | `/courses/{course}/ratings` | CourseRatingController@store | Create rating |
| PATCH | `/courses/{course}/ratings/{rating}` | CourseRatingController@update | Update rating |
| DELETE | `/courses/{course}/ratings/{rating}` | CourseRatingController@destroy | Delete rating |

**Rating Request Body:**
```json
{
  "rating": 5,
  "review": "Excellent course!"
}
```

---

## Assessment Routes

### Assessment CRUD

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| GET | `/courses/{course}/assessments` | AssessmentController@index | List assessments |
| GET | `/courses/{course}/assessments/create` | AssessmentController@create | Create form |
| POST | `/courses/{course}/assessments` | AssessmentController@store | Store assessment |
| GET | `/courses/{course}/assessments/{assessment}` | AssessmentController@show | View assessment |
| GET | `/courses/{course}/assessments/{assessment}/edit` | AssessmentController@edit | Edit form |
| PUT | `/courses/{course}/assessments/{assessment}` | AssessmentController@update | Update |
| DELETE | `/courses/{course}/assessments/{assessment}` | AssessmentController@destroy | Delete |

### Assessment Status

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| POST | `/courses/{course}/assessments/{assessment}/publish` | AssessmentController@publish | Publish |
| POST | `/courses/{course}/assessments/{assessment}/unpublish` | AssessmentController@unpublish | Unpublish |
| POST | `/courses/{course}/assessments/{assessment}/archive` | AssessmentController@archive | Archive |

### Assessment Attempts

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| POST | `/courses/{course}/assessments/{assessment}/start` | AssessmentController@startAttempt | Start attempt |
| GET | `/courses/{course}/assessments/{assessment}/attempts/{attempt}` | AssessmentController@attempt | Take assessment |
| POST | `/courses/{course}/assessments/{assessment}/attempts/{attempt}/submit` | AssessmentController@submitAttempt | Submit |
| GET | `/courses/{course}/assessments/{assessment}/attempts/{attempt}/complete` | AssessmentController@attemptComplete | Results |

### Assessment Grading

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| GET | `/courses/{course}/assessments/{assessment}/attempts/{attempt}/grade` | AssessmentController@grade | Grade form |
| POST | `/courses/{course}/assessments/{assessment}/attempts/{attempt}/grade` | AssessmentController@submitGrade | Submit grades |

---

## Question Routes

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| GET | `/courses/{course}/assessments/{assessment}/questions` | QuestionController@index | List questions |
| PUT | `/courses/{course}/assessments/{assessment}/questions` | QuestionController@bulkUpdate | Bulk update |
| DELETE | `/courses/{course}/assessments/{assessment}/questions/{question}` | QuestionController@destroy | Delete |
| POST | `/courses/{course}/assessments/{assessment}/questions/reorder` | QuestionController@reorder | Reorder |

---

## Learning Path Routes

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| GET | `/learning-paths` | LearningPathController@index | List paths |
| GET | `/learning-paths/create` | LearningPathController@create | Create form |
| POST | `/learning-paths` | LearningPathController@store | Store path |
| GET | `/learning-paths/{learning_path}` | LearningPathController@show | View path |
| GET | `/learning-paths/{learning_path}/edit` | LearningPathController@edit | Edit form |
| PUT | `/learning-paths/{learning_path}` | LearningPathController@update | Update |
| DELETE | `/learning-paths/{learning_path}` | LearningPathController@destroy | Delete |
| PUT | `/learning-paths/{learning_path}/publish` | LearningPathController@publish | Publish |
| PUT | `/learning-paths/{learning_path}/unpublish` | LearningPathController@unpublish | Unpublish |
| POST | `/learning-paths/{learning_path}/reorder` | LearningPathController@reorder | Reorder courses |

---

## Media Routes

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| POST | `/media` | MediaController@store | Upload file |
| DELETE | `/media/{media}` | MediaController@destroy | Delete file |

**Upload Request (multipart/form-data):**
```
file: (binary)
mediable_type: lesson
mediable_id: 123
collection_name: video
```

---

## Settings Routes

| Method | Endpoint | Controller | Description |
|--------|----------|------------|-------------|
| GET | `/settings/profile` | Settings/ProfileController@edit | Profile page |
| PATCH | `/settings/profile` | Settings/ProfileController@update | Update profile |
| DELETE | `/settings/profile` | Settings/ProfileController@destroy | Delete account |
| GET | `/settings/password` | Settings/PasswordController@edit | Password page |
| PUT | `/settings/password` | Settings/PasswordController@update | Change password |
| GET | `/settings/two-factor` | Settings/TwoFactorAuthenticationController@show | 2FA settings |
| GET | `/settings/appearance` | (Inertia view) | Appearance settings |

---

## Response Formats

### Success Response (Inertia)

Inertia returns a redirect or page component:

```json
{
  "component": "courses/Show",
  "props": {
    "course": { ... },
    "can": { "update": true }
  },
  "url": "/courses/1"
}
```

### Validation Error (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["Judul kursus wajib diisi."],
    "email": ["Email sudah terdaftar."]
  }
}
```

### Authorization Error (403)

```json
{
  "message": "This action is unauthorized."
}
```

### Not Found (404)

```json
{
  "message": "No query results for model [App\\Models\\Course] 999"
}
```

---

## Rate Limiting

| Endpoint | Limit |
|----------|-------|
| POST /login | 5 per minute |
| POST /two-factor-challenge | 5 per minute |
| PUT /settings/password | 6 per minute |

---

## Wayfinder Routes

For type-safe frontend routing, use Wayfinder:

```typescript
import { show } from '@/actions/App/Http/Controllers/CourseController';

// Get URL
show.url(courseId);  // "/courses/1"

// Get route object
show(courseId);  // { url: "/courses/1", method: "get" }
```

See [Wayfinder documentation](https://github.com/laravel/wayfinder) for details.
