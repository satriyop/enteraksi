# Course Management Implementation Plan

**Date:** 2025-11-27
**Project:** LMS E-Learning Application
**Feature:** Course Management (based on course-story.md)

---

## Overview

Implement Course Management features for the LMS application based on the user story requirements. This is a new Laravel 12 + Inertia v2 + Vue 3 project with authentication already set up.

## Technical Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| RBAC | Laravel built-in Gates/Policies | No external package needed, sufficient for requirements |
| File Storage | Local storage (`storage/app/public`) | Simple setup, can migrate to S3 later |
| WYSIWYG Editor | TipTap | Modern, Vue 3 native, stores JSON, extensible |
| Drag & Drop | VueDraggable | Popular, Vue 3 support, based on SortableJS |

---

## Phase 1: Foundation - Roles & Database Schema

### 1.1 User Roles Migration

Add `role` enum column to users table:
- `learner` (default)
- `content_manager`
- `trainer`
- `lms_admin`

### 1.2 Core Migrations (in order)

| # | Migration | Key Columns |
|---|-----------|-------------|
| 1 | `create_categories_table` | id, name, slug, description, parent_id, order |
| 2 | `create_tags_table` | id, name, slug |
| 3 | `create_courses_table` | id, user_id, title, slug, short_description, long_description, objectives (json), prerequisites (json), category_id, thumbnail_path, status (enum: draft/published/archived), visibility (enum: public/restricted/hidden), difficulty_level (enum), estimated_duration_minutes, manual_duration_minutes, published_at, published_by |
| 4 | `create_course_sections_table` | id, course_id, title, description, order, estimated_duration_minutes |
| 5 | `create_lessons_table` | id, course_section_id, title, description, order, content_type (enum: text/video/audio/document/youtube/conference), rich_content (json), youtube_url, conference_url, conference_type, estimated_duration_minutes, is_free_preview |
| 6 | `create_media_table` | id, mediable_type, mediable_id, collection_name, name, file_name, mime_type, disk, path, size, duration_seconds, custom_properties (json), order |
| 7 | `create_course_tag_table` | course_id, tag_id (pivot) |

### 1.3 Models to Create

```
app/Models/
├── Category.php
├── Tag.php
├── Course.php
├── CourseSection.php
├── Lesson.php
└── Media.php
```

**Key Relationships:**
- Course → hasMany Sections → hasMany Lessons
- Course → belongsToMany Tags
- Course → belongsTo Category
- Course → morphMany Media
- Lesson → morphMany Media

### 1.4 Course Model Schema

```php
// app/Models/Course.php
class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'title', 'slug', 'short_description', 'long_description',
        'objectives', 'prerequisites', 'category_id', 'thumbnail_path',
        'status', 'visibility', 'difficulty_level', 'estimated_duration_minutes',
        'manual_duration_minutes', 'published_at', 'published_by',
    ];

    protected function casts(): array
    {
        return [
            'objectives' => 'array',
            'prerequisites' => 'array',
            'published_at' => 'datetime',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    public function category(): BelongsTo
    public function publishedBy(): BelongsTo
    public function sections(): HasMany
    public function lessons(): HasManyThrough
    public function tags(): BelongsToMany
    public function media(): MorphMany

    // Scopes
    public function scopePublished(Builder $query): Builder
    public function scopeDraft(Builder $query): Builder
    public function scopeVisible(Builder $query): Builder

    // Accessors
    public function getDurationAttribute(): int
    public function getTotalLessonsAttribute(): int
    public function getIsEditableAttribute(): bool
}
```

---

## Phase 2: Authorization with Gates & Policies

### 2.1 Update User Model

Add role helpers:
```php
public function isContentManager(): bool
public function isTrainer(): bool
public function isLmsAdmin(): bool
public function isLearner(): bool
public function canManageCourses(): bool // content_manager, trainer, lms_admin
```

### 2.2 CoursePolicy

```php
// app/Policies/CoursePolicy.php
viewAny()       → canManageCourses()
view()          → canManageCourses() OR owner
create()        → canManageCourses()
update()        → (owner OR lms_admin) AND not published (unless lms_admin)
delete()        → (owner AND draft) OR lms_admin
publish()       → lms_admin only
setStatus()     → lms_admin only
setVisibility() → lms_admin only
```

### 2.3 Register Policies

Register in `app/Providers/AppServiceProvider.php`:
- CoursePolicy
- CourseSectionPolicy
- LessonPolicy

---

## Phase 3: Course CRUD

### 3.1 Controllers

```
app/Http/Controllers/
├── CourseController.php           # index, create, store, show, edit, update, destroy
├── CourseSectionController.php    # store, update, destroy
├── LessonController.php           # create, store, edit, update, destroy
├── CourseReorderController.php    # sections(), lessons()
├── CoursePublishController.php    # publish, unpublish, archive
├── CourseDurationController.php   # recalculate, updateManual
└── MediaController.php            # store, destroy
```

### 3.2 Form Requests

```
app/Http/Requests/
├── Course/
│   ├── StoreCourseRequest.php
│   └── UpdateCourseRequest.php
├── Section/
│   ├── StoreSectionRequest.php
│   └── UpdateSectionRequest.php
├── Lesson/
│   ├── StoreLessonRequest.php
│   └── UpdateLessonRequest.php
└── Media/
    └── StoreMediaRequest.php
```

### 3.3 Validation Rules Example

```php
// StoreCourseRequest
public function rules(): array
{
    return [
        'title' => ['required', 'string', 'max:255'],
        'short_description' => ['nullable', 'string', 'max:500'],
        'long_description' => ['nullable', 'string'],
        'objectives' => ['nullable', 'array'],
        'objectives.*' => ['string', 'max:500'],
        'prerequisites' => ['nullable', 'array'],
        'prerequisites.*' => ['string', 'max:500'],
        'category_id' => ['nullable', 'exists:categories,id'],
        'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        'difficulty_level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
        'tags' => ['nullable', 'array'],
        'tags.*' => ['exists:tags,id'],
    ];
}
```

### 3.4 Routes (`routes/courses.php`)

```php
Route::middleware(['auth', 'verified'])->group(function () {
    // Course CRUD
    Route::resource('courses', CourseController::class);

    // Sections
    Route::post('courses/{course}/sections', [CourseSectionController::class, 'store'])
        ->name('courses.sections.store');
    Route::patch('sections/{section}', [CourseSectionController::class, 'update'])
        ->name('sections.update');
    Route::delete('sections/{section}', [CourseSectionController::class, 'destroy'])
        ->name('sections.destroy');

    // Lessons
    Route::get('sections/{section}/lessons/create', [LessonController::class, 'create'])
        ->name('sections.lessons.create');
    Route::post('sections/{section}/lessons', [LessonController::class, 'store'])
        ->name('sections.lessons.store');
    Route::get('lessons/{lesson}/edit', [LessonController::class, 'edit'])
        ->name('lessons.edit');
    Route::patch('lessons/{lesson}', [LessonController::class, 'update'])
        ->name('lessons.update');
    Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])
        ->name('lessons.destroy');

    // Reordering (AJAX)
    Route::post('courses/{course}/sections/reorder', [CourseReorderController::class, 'sections'])
        ->name('courses.sections.reorder');
    Route::post('sections/{section}/lessons/reorder', [CourseReorderController::class, 'lessons'])
        ->name('sections.lessons.reorder');

    // Publishing (LMS Admin)
    Route::post('courses/{course}/publish', [CoursePublishController::class, 'publish'])
        ->name('courses.publish');
    Route::post('courses/{course}/unpublish', [CoursePublishController::class, 'unpublish'])
        ->name('courses.unpublish');
    Route::post('courses/{course}/archive', [CoursePublishController::class, 'archive'])
        ->name('courses.archive');

    // Duration
    Route::post('courses/{course}/duration/recalculate', [CourseDurationController::class, 'recalculate'])
        ->name('courses.duration.recalculate');
    Route::patch('courses/{course}/duration', [CourseDurationController::class, 'updateManual'])
        ->name('courses.duration.update');

    // Media
    Route::post('media', [MediaController::class, 'store'])->name('media.store');
    Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
});
```

---

## Phase 4: Vue Frontend Components

### 4.1 Page Components

```
resources/js/pages/courses/
├── Index.vue                 # Course list with filters/search
├── Create.vue                # Initial course creation form
├── Show.vue                  # Course preview/details
└── Edit/
    ├── Index.vue             # Main edit page (tab container)
    ├── Details.vue           # Basic info tab
    ├── Outline.vue           # Sections/Lessons drag-drop accordion
    └── Settings.vue          # Status, visibility, publish actions

resources/js/pages/lessons/
└── Edit.vue                  # Full lesson editor with TipTap
```

### 4.2 Reusable Components

```
resources/js/components/courses/
├── CourseCard.vue            # Card for course list
├── CourseForm.vue            # Shared form fields
├── CourseOutline.vue         # Draggable accordion container
├── SectionItem.vue           # Single section with lessons
├── LessonItem.vue            # Single lesson row
├── ThumbnailUploader.vue     # Image upload with preview
├── ObjectivesEditor.vue      # Dynamic list input
├── CategorySelect.vue        # Category dropdown
├── TagsInput.vue             # Multi-select tags
├── DifficultySelect.vue      # Difficulty dropdown
├── StatusBadge.vue           # Status indicator
└── DurationDisplay.vue       # Formatted duration

resources/js/components/lessons/
├── LessonEditor.vue          # TipTap WYSIWYG wrapper
├── ContentTypeSelector.vue   # Content type tabs/buttons
├── VideoUploader.vue         # Video upload
├── AudioUploader.vue         # Audio upload
├── DocumentUploader.vue      # PDF/PPT/DOC upload
├── YouTubeEmbed.vue          # YouTube URL input + preview
└── ConferenceLink.vue        # Zoom/Meet input
```

---

## Phase 5: NPM Dependencies

```bash
npm install vuedraggable@next
npm install @tiptap/vue-3 @tiptap/starter-kit @tiptap/extension-link @tiptap/extension-image @tiptap/extension-youtube @tiptap/extension-placeholder
```

---

## Phase 6: Factories & Seeders (Indonesian Context)

### 6.1 Factories

```
database/factories/
├── CategoryFactory.php
├── TagFactory.php
├── CourseFactory.php
├── CourseSectionFactory.php
└── LessonFactory.php
```

### 6.2 Seeders

```
database/seeders/
├── CategorySeeder.php        # Indonesian categories
├── TagSeeder.php             # Indonesian tags
├── CourseSeeder.php          # Sample courses with sections/lessons
└── DatabaseSeeder.php        # Updated to include new seeders
```

### 6.3 Sample Indonesian Data

**Categories:**
- Teknologi Informasi
- Bisnis & Manajemen
- Bahasa
- Desain & Multimedia
- Keuangan & Akuntansi
- Soft Skills

**Sample Course Titles:**
- Pengantar Pemrograman Python
- Manajemen Proyek untuk Pemula
- Desain UI/UX Modern
- Analisis Data dengan Excel
- Bahasa Inggris Bisnis
- Kepemimpinan Efektif

---

## Phase 7: Tests

### 7.1 Feature Tests

```
tests/Feature/
├── Course/
│   ├── CourseListTest.php
│   ├── CourseCreateTest.php
│   ├── CourseUpdateTest.php
│   ├── CourseDeleteTest.php
│   ├── CoursePublishTest.php
│   └── CoursePolicyTest.php
├── Section/
│   ├── SectionCrudTest.php
│   └── SectionReorderTest.php
├── Lesson/
│   ├── LessonCrudTest.php
│   └── LessonReorderTest.php
└── Media/
    └── MediaUploadTest.php
```

### 7.2 Unit Tests

```
tests/Unit/
├── Models/
│   ├── CourseTest.php        # Scopes, accessors, relationships
│   └── LessonTest.php
└── Services/
    └── CourseDurationCalculatorTest.php
```

### 7.3 Example Test

```php
class CourseCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_manager_can_create_course(): void
    {
        $user = User::factory()->create(['role' => 'content_manager']);

        $response = $this->actingAs($user)->post(route('courses.store'), [
            'title' => 'Kursus Pemrograman Python',
            'short_description' => 'Belajar Python dari dasar',
            'difficulty_level' => 'beginner',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', [
            'title' => 'Kursus Pemrograman Python',
            'user_id' => $user->id,
            'status' => 'draft',
        ]);
    }

    public function test_learner_cannot_create_course(): void
    {
        $user = User::factory()->create(['role' => 'learner']);

        $response = $this->actingAs($user)->post(route('courses.store'), [
            'title' => 'Test Course',
            'difficulty_level' => 'beginner',
        ]);

        $response->assertForbidden();
    }
}
```

---

## Implementation Order (Step by Step)

### Step 1: Database & Models
1. Create `add_role_to_users_table` migration
2. Create all migrations (categories, tags, courses, sections, lessons, media, pivot)
3. Create all Model classes with relationships
4. Update User model with role helpers
5. Run migrations

### Step 2: Authorization
1. Create CoursePolicy
2. Create CourseSectionPolicy
3. Create LessonPolicy
4. Register policies in AppServiceProvider

### Step 3: Backend - Course CRUD
1. Create Form Request classes
2. Create CourseController
3. Create routes/courses.php and register in bootstrap/app.php
4. Write Course CRUD tests

### Step 4: Frontend - Course List & Create
1. Create CourseCard component
2. Create courses/Index.vue page
3. Create CourseForm component
4. Create courses/Create.vue page
5. Test course creation flow

### Step 5: Frontend - Course Edit with Outline
1. Install VueDraggable
2. Create SectionItem, LessonItem components
3. Create CourseOutline component with drag-drop
4. Create courses/Edit/Outline.vue page
5. Create CourseSectionController, CourseReorderController
6. Write section/lesson reorder tests

### Step 6: Lesson Editor
1. Install TipTap packages
2. Create LessonEditor component
3. Create content type components (uploaders, YouTube, conference)
4. Create lessons/Edit.vue page
5. Create LessonController
6. Create MediaController for uploads
7. Write lesson tests

### Step 7: Publishing & Polish
1. Create CoursePublishController
2. Create courses/Edit/Settings.vue
3. Create CourseDurationController
4. Implement edit restrictions for published courses
5. Complete all remaining tests

### Step 8: Seeders & Final Testing
1. Create all factories
2. Create all seeders with Indonesian data
3. Run full test suite
4. Manual testing of all features

---

## Files Summary

### New Files to Create

| Category | Count | Files |
|----------|-------|-------|
| Migrations | 8 | role, categories, tags, courses, sections, lessons, media, pivot |
| Models | 6 | Category, Tag, Course, CourseSection, Lesson, Media |
| Policies | 3 | CoursePolicy, CourseSectionPolicy, LessonPolicy |
| Controllers | 7 | Course, Section, Lesson, Reorder, Publish, Duration, Media |
| Form Requests | 6 | Store/Update for Course, Section, Lesson |
| Vue Pages | 7 | Index, Create, Show, Edit/*, Lesson Edit |
| Vue Components | 19 | courses/*, lessons/* |
| Factories | 5 | Category, Tag, Course, Section, Lesson |
| Seeders | 4 | Category, Tag, Course, DatabaseSeeder |
| Tests | 12+ | Feature and Unit tests |

### Files to Modify

| File | Changes |
|------|---------|
| `app/Models/User.php` | Add role column cast, role helper methods |
| `app/Providers/AppServiceProvider.php` | Register policies |
| `bootstrap/app.php` | Register courses routes file |
| `database/seeders/DatabaseSeeder.php` | Include new seeders |
| `resources/js/layouts/AppLayout.vue` | Add Courses navigation link |

---

## Future Enhancements (Not in Current Scope)

Based on course-story.md, these features are planned for future implementation:
- Assessment linking to courses/sections/lessons
- Competency linking to courses
- Learning Paths management
- H5P, SCORM import
- LTI integration
- Learner enrollment and progress tracking
- Certificates, badges, and XP system
