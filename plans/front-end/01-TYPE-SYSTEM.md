# Phase 1: Type System Centralization

## Overview

This phase addresses the critical issue of type duplication across the codebase. Currently, interfaces like `Course`, `Lesson`, `Section`, and `Category` are defined inline in 10+ files each, leading to:
- Inconsistencies between definitions
- Maintenance nightmare when models change
- No IDE support for cross-file refactoring
- Risk of type drift

**Duration:** 1-2 weeks
**Risk Level:** Low
**Dependencies:** None

---

## Current State Analysis

### Type Duplication Examples

**Course Interface** - Found in 10+ files:
```typescript
// resources/js/pages/courses/Edit.vue
interface Course {
    id: number;
    title: string;
    status: 'draft' | 'published' | 'archived';
    // ...varies by file
}

// resources/js/pages/courses/Detail.vue
interface Course {
    id: number;
    title: string;
    status: string; // Different type!
    description?: string; // Sometimes optional
}
```

**Lesson Interface** - Found in 8+ files:
```typescript
// Inconsistent content_type definitions
content_type: 'text' | 'video' | 'youtube' | 'audio' | 'document' | 'conference';
content_type: 'video' | 'text' | 'quiz'; // Missing types!
content_type: string; // No type safety at all
```

### Current `/types/index.d.ts` (40 lines only)
```typescript
// Missing: Course, Lesson, Section, Assessment, Enrollment, Category, Tag
export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
}
```

---

## Target Architecture

### Directory Structure
```
resources/js/types/
├── index.d.ts              # Main export file
├── models/
│   ├── user.ts             # User, Role, Permission
│   ├── course.ts           # Course, Section, Category, Tag
│   ├── lesson.ts           # Lesson, LessonContent, ContentType
│   ├── assessment.ts       # Assessment, Question, Answer, Attempt
│   ├── enrollment.ts       # Enrollment, Progress
│   └── common.ts           # Shared types (Pagination, Timestamps)
├── api/
│   ├── responses.ts        # API response wrappers
│   ├── requests.ts         # API request payloads
│   └── errors.ts           # Error response types
├── components/
│   ├── forms.ts            # Form prop types
│   └── tables.ts           # Table/DataGrid types
└── inertia.d.ts            # Inertia page props augmentation
```

---

## Implementation Steps

### Step 1: Create Base Type Infrastructure

**File: `resources/js/types/models/common.ts`**
```typescript
/**
 * Common types shared across all models
 */

// Timestamp fields from Laravel
export interface Timestamps {
    created_at: string;
    updated_at: string;
}

export interface SoftDeletes extends Timestamps {
    deleted_at: string | null;
}

// Pagination from Laravel
export interface PaginationLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLinks;
    meta: PaginationMeta;
}

// Simple pagination (Laravel simplePaginate)
export interface SimplePaginated<T> {
    data: T[];
    current_page: number;
    per_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

// API Response wrapper
export interface ApiResponse<T> {
    data: T;
    message?: string;
}

// ID types for type safety
export type UserId = number;
export type CourseId = number;
export type LessonId = number;
export type SectionId = number;
export type AssessmentId = number;
export type EnrollmentId = number;

// Status enums as const objects (better than string unions for iteration)
export const CourseStatus = {
    DRAFT: 'draft',
    PUBLISHED: 'published',
    ARCHIVED: 'archived',
} as const;
export type CourseStatus = typeof CourseStatus[keyof typeof CourseStatus];

export const EnrollmentStatus = {
    PENDING: 'pending',
    ACTIVE: 'active',
    COMPLETED: 'completed',
    SUSPENDED: 'suspended',
    CANCELLED: 'cancelled',
} as const;
export type EnrollmentStatus = typeof EnrollmentStatus[keyof typeof EnrollmentStatus];

export const ContentType = {
    TEXT: 'text',
    VIDEO: 'video',
    YOUTUBE: 'youtube',
    AUDIO: 'audio',
    DOCUMENT: 'document',
    CONFERENCE: 'conference',
} as const;
export type ContentType = typeof ContentType[keyof typeof ContentType];

export const DifficultyLevel = {
    BEGINNER: 'beginner',
    INTERMEDIATE: 'intermediate',
    ADVANCED: 'advanced',
} as const;
export type DifficultyLevel = typeof DifficultyLevel[keyof typeof DifficultyLevel];
```

### Step 2: Define Domain Model Types

**File: `resources/js/types/models/user.ts`**
```typescript
import type { Timestamps, UserId } from './common';

export interface User extends Timestamps {
    id: UserId;
    name: string;
    email: string;
    email_verified_at: string | null;
    avatar?: string;
    roles?: Role[];
    permissions?: Permission[];
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions?: Permission[];
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
}

// User with specific relations loaded
export interface UserWithRoles extends User {
    roles: Role[];
}

export interface UserWithPermissions extends User {
    permissions: Permission[];
}

// Form data types (for creating/updating)
export interface CreateUserData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role_ids?: number[];
}

export interface UpdateUserData {
    name?: string;
    email?: string;
    password?: string;
    password_confirmation?: string;
    role_ids?: number[];
}
```

**File: `resources/js/types/models/course.ts`**
```typescript
import type {
    Timestamps,
    CourseId,
    UserId,
    CourseStatus,
    DifficultyLevel
} from './common';
import type { User } from './user';
import type { Section } from './lesson';
import type { Assessment } from './assessment';
import type { Enrollment } from './enrollment';

export interface Category {
    id: number;
    name: string;
    slug: string;
    description?: string;
    parent_id?: number;
    courses_count?: number;
}

export interface Tag {
    id: number;
    name: string;
    slug: string;
    courses_count?: number;
}

export interface Course extends Timestamps {
    id: CourseId;
    title: string;
    slug: string;
    description: string | null;
    short_description: string | null;
    thumbnail: string | null;
    preview_video: string | null;
    status: CourseStatus;
    difficulty_level: DifficultyLevel;
    estimated_duration: number | null; // in minutes
    is_featured: boolean;
    price: number;
    instructor_id: UserId;
    category_id: number | null;

    // Computed/aggregate fields (may not always be present)
    lessons_count?: number;
    sections_count?: number;
    enrollments_count?: number;
    average_rating?: number;

    // Relations (loaded conditionally)
    instructor?: User;
    category?: Category;
    tags?: Tag[];
    sections?: Section[];
    assessments?: Assessment[];
    enrollments?: Enrollment[];
}

// Course variants with specific relations
export interface CourseWithSections extends Course {
    sections: Section[];
}

export interface CourseWithInstructor extends Course {
    instructor: User;
}

export interface CourseListItem extends Pick<Course,
    'id' | 'title' | 'slug' | 'thumbnail' | 'status' | 'difficulty_level' |
    'estimated_duration' | 'price' | 'lessons_count' | 'enrollments_count'
> {
    instructor: Pick<User, 'id' | 'name' | 'avatar'>;
    category?: Pick<Category, 'id' | 'name'>;
}

// Form data types
export interface CreateCourseData {
    title: string;
    description?: string;
    short_description?: string;
    thumbnail?: File | null;
    category_id?: number;
    tag_ids?: number[];
    difficulty_level?: DifficultyLevel;
    price?: number;
}

export interface UpdateCourseData extends Partial<CreateCourseData> {
    status?: CourseStatus;
}

// Course filter/query params
export interface CourseFilters {
    search?: string;
    status?: CourseStatus;
    category_id?: number;
    difficulty_level?: DifficultyLevel;
    instructor_id?: UserId;
    is_featured?: boolean;
    min_price?: number;
    max_price?: number;
    sort_by?: 'title' | 'created_at' | 'price' | 'enrollments_count';
    sort_order?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
}
```

**File: `resources/js/types/models/lesson.ts`**
```typescript
import type {
    Timestamps,
    LessonId,
    SectionId,
    CourseId,
    ContentType
} from './common';
import type { Assessment } from './assessment';

export interface Section extends Timestamps {
    id: SectionId;
    course_id: CourseId;
    title: string;
    description: string | null;
    position: number;

    // Relations
    lessons?: Lesson[];
    lessons_count?: number;
}

export interface Lesson extends Timestamps {
    id: LessonId;
    section_id: SectionId;
    title: string;
    slug: string;
    content_type: ContentType;
    content: LessonContent | null;
    description: string | null;
    duration: number | null; // in seconds
    position: number;
    is_preview: boolean;
    is_mandatory: boolean;

    // Relations
    section?: Section;
    assessments?: Assessment[];

    // Progress (when user context is present)
    user_progress?: LessonProgress;
}

// Content type-specific structures
export interface TextContent {
    type: 'text';
    body: string; // HTML content
}

export interface VideoContent {
    type: 'video';
    url: string;
    provider?: 'local' | 'vimeo' | 's3';
    thumbnail?: string;
    duration?: number;
}

export interface YouTubeContent {
    type: 'youtube';
    video_id: string;
    thumbnail?: string;
}

export interface AudioContent {
    type: 'audio';
    url: string;
    duration?: number;
}

export interface DocumentContent {
    type: 'document';
    url: string;
    filename: string;
    file_size?: number;
    mime_type?: string;
}

export interface ConferenceContent {
    type: 'conference';
    provider: 'zoom' | 'google_meet' | 'teams';
    meeting_url?: string;
    scheduled_at?: string;
    duration?: number;
}

export type LessonContent =
    | TextContent
    | VideoContent
    | YouTubeContent
    | AudioContent
    | DocumentContent
    | ConferenceContent;

// User's progress on a lesson
export interface LessonProgress {
    lesson_id: LessonId;
    status: 'not_started' | 'in_progress' | 'completed';
    progress_percentage: number;
    time_spent: number; // in seconds
    completed_at: string | null;
    last_position?: number; // for video/audio
}

// Form data
export interface CreateLessonData {
    title: string;
    content_type: ContentType;
    description?: string;
    is_preview?: boolean;
    is_mandatory?: boolean;
    content?: Partial<LessonContent>;
}

export interface UpdateLessonData extends Partial<CreateLessonData> {
    position?: number;
}

export interface ReorderLessonsData {
    lessons: Array<{ id: LessonId; position: number }>;
}
```

**File: `resources/js/types/models/assessment.ts`**
```typescript
import type { Timestamps, AssessmentId, LessonId, CourseId, UserId } from './common';

export const QuestionType = {
    MULTIPLE_CHOICE: 'multiple_choice',
    TRUE_FALSE: 'true_false',
    SHORT_ANSWER: 'short_answer',
    ESSAY: 'essay',
    MATCHING: 'matching',
    ORDERING: 'ordering',
} as const;
export type QuestionType = typeof QuestionType[keyof typeof QuestionType];

export const AttemptStatus = {
    IN_PROGRESS: 'in_progress',
    SUBMITTED: 'submitted',
    GRADED: 'graded',
    EXPIRED: 'expired',
} as const;
export type AttemptStatus = typeof AttemptStatus[keyof typeof AttemptStatus];

export interface Assessment extends Timestamps {
    id: AssessmentId;
    course_id: CourseId;
    lesson_id: LessonId | null;
    title: string;
    description: string | null;
    type: 'quiz' | 'exam' | 'assignment';
    passing_score: number;
    time_limit: number | null; // in minutes
    max_attempts: number | null;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    show_correct_answers: boolean;
    available_from: string | null;
    available_until: string | null;

    // Relations
    questions?: Question[];
    questions_count?: number;

    // User context
    user_attempts?: AssessmentAttempt[];
    user_best_score?: number;
    user_attempts_remaining?: number;
}

export interface Question extends Timestamps {
    id: number;
    assessment_id: AssessmentId;
    type: QuestionType;
    question_text: string;
    explanation: string | null;
    points: number;
    position: number;

    // Type-specific data
    options?: QuestionOption[];
    correct_answer?: string | string[];
    matching_pairs?: MatchingPair[];
    order_items?: OrderItem[];
}

export interface QuestionOption {
    id: string;
    text: string;
    is_correct?: boolean; // Only visible after grading
}

export interface MatchingPair {
    id: string;
    left: string;
    right: string;
}

export interface OrderItem {
    id: string;
    text: string;
    correct_position?: number;
}

export interface AssessmentAttempt extends Timestamps {
    id: number;
    assessment_id: AssessmentId;
    user_id: UserId;
    status: AttemptStatus;
    score: number | null;
    percentage: number | null;
    passed: boolean | null;
    started_at: string;
    submitted_at: string | null;
    graded_at: string | null;
    time_spent: number | null; // in seconds

    // Relations
    answers?: AttemptAnswer[];
    assessment?: Assessment;
}

export interface AttemptAnswer {
    id: number;
    attempt_id: number;
    question_id: number;
    answer: string | string[] | Record<string, string>;
    is_correct: boolean | null;
    points_earned: number | null;
    feedback: string | null;
}

// Form data
export interface SubmitAttemptData {
    answers: Array<{
        question_id: number;
        answer: string | string[] | Record<string, string>;
    }>;
}

export interface GradeAnswerData {
    points_earned: number;
    feedback?: string;
}
```

**File: `resources/js/types/models/enrollment.ts`**
```typescript
import type {
    Timestamps,
    EnrollmentId,
    CourseId,
    UserId,
    EnrollmentStatus
} from './common';
import type { User } from './user';
import type { Course } from './course';
import type { LessonProgress } from './lesson';

export interface Enrollment extends Timestamps {
    id: EnrollmentId;
    course_id: CourseId;
    user_id: UserId;
    status: EnrollmentStatus;
    enrolled_at: string;
    started_at: string | null;
    completed_at: string | null;
    expires_at: string | null;
    progress_percentage: number;

    // Payment info (if applicable)
    payment_status?: 'pending' | 'paid' | 'refunded';
    payment_amount?: number;

    // Relations
    user?: User;
    course?: Course;
    lesson_progress?: LessonProgress[];
}

export interface EnrollmentWithProgress extends Enrollment {
    course: Course;
    lesson_progress: LessonProgress[];
    current_lesson_id?: number;
    completed_lessons_count: number;
    total_lessons_count: number;
}

// Enrollment statistics for dashboard
export interface EnrollmentStats {
    total_enrollments: number;
    active_enrollments: number;
    completed_enrollments: number;
    average_progress: number;
    completion_rate: number;
}

// Form data
export interface EnrollUserData {
    user_id: UserId;
    course_id: CourseId;
    expires_at?: string;
}

export interface UpdateEnrollmentData {
    status?: EnrollmentStatus;
    expires_at?: string;
}
```

### Step 3: Create API Types

**File: `resources/js/types/api/responses.ts`**
```typescript
import type { Paginated, SimplePaginated } from '../models/common';
import type { Course, CourseListItem, Category, Tag } from '../models/course';
import type { Lesson, Section } from '../models/lesson';
import type { Assessment, AssessmentAttempt, Question } from '../models/assessment';
import type { Enrollment, EnrollmentWithProgress } from '../models/enrollment';
import type { User } from '../models/user';

// Generic API response wrapper
export interface ApiSuccessResponse<T> {
    data: T;
    message?: string;
}

export interface ApiErrorResponse {
    message: string;
    errors?: Record<string, string[]>;
}

// Specific endpoint responses
export interface CoursesIndexResponse {
    courses: Paginated<CourseListItem>;
    categories: Category[];
    tags: Tag[];
    filters: {
        status?: string;
        category_id?: number;
        search?: string;
    };
}

export interface CourseShowResponse {
    course: Course;
    sections: Section[];
    enrollment?: Enrollment;
    canEdit: boolean;
    canEnroll: boolean;
}

export interface CourseEditResponse {
    course: Course;
    categories: Category[];
    tags: Tag[];
    sections: Section[];
}

export interface LessonShowResponse {
    lesson: Lesson;
    course: Course;
    enrollment: EnrollmentWithProgress;
    previousLesson?: Pick<Lesson, 'id' | 'title' | 'slug'>;
    nextLesson?: Pick<Lesson, 'id' | 'title' | 'slug'>;
}

export interface AssessmentShowResponse {
    assessment: Assessment;
    questions: Question[];
    currentAttempt?: AssessmentAttempt;
    attemptsRemaining: number;
    canAttempt: boolean;
}

export interface DashboardResponse {
    user: User;
    enrollments: EnrollmentWithProgress[];
    recentActivity: ActivityItem[];
    stats: {
        coursesInProgress: number;
        coursesCompleted: number;
        totalTimeSpent: number;
        averageScore: number;
    };
}

export interface ActivityItem {
    id: number;
    type: 'lesson_completed' | 'assessment_passed' | 'course_started' | 'course_completed';
    description: string;
    course?: Pick<Course, 'id' | 'title' | 'slug'>;
    timestamp: string;
}

// Admin responses
export interface AdminUsersResponse {
    users: Paginated<User>;
    roles: Array<{ id: number; name: string; users_count: number }>;
}

export interface AdminCoursesResponse {
    courses: Paginated<Course>;
    stats: {
        totalCourses: number;
        publishedCourses: number;
        totalEnrollments: number;
        totalRevenue: number;
    };
}
```

### Step 4: Create Inertia Type Augmentation

**File: `resources/js/types/inertia.d.ts`**
```typescript
import type { User } from './models/user';
import type { LucideIcon } from 'lucide-vue-next';
import type { InertiaLinkProps } from '@inertiajs/vue3';

// Auth shape passed to all pages
export interface Auth {
    user: User;
}

// Flash messages
export interface Flash {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
}

// Navigation items
export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    badge?: string | number;
    children?: NavItem[];
}

export interface BreadcrumbItem {
    title: string;
    href?: string;
}

// Base page props (shared across all pages)
export interface SharedPageProps {
    auth: Auth;
    flash: Flash;
    sidebarOpen: boolean;
    name: string;
    quote: {
        message: string;
        author: string;
    };
}

// Helper type for page-specific props
export type PageProps<T = Record<string, unknown>> = T & SharedPageProps;

// Augment Inertia's page props globally
declare module '@inertiajs/vue3' {
    interface PageProps extends SharedPageProps {}
}
```

### Step 5: Create Main Export File

**File: `resources/js/types/index.d.ts`**
```typescript
/**
 * Enteraksi LMS Type Definitions
 *
 * This file exports all type definitions used across the application.
 * Import types from this file for consistent type usage.
 *
 * @example
 * import type { Course, Lesson, User } from '@/types';
 */

// Common types
export * from './models/common';

// Domain models
export * from './models/user';
export * from './models/course';
export * from './models/lesson';
export * from './models/assessment';
export * from './models/enrollment';

// API types
export * from './api/responses';

// Inertia types
export * from './inertia';

// Re-export commonly used types for convenience
export type {
    // Models
    User,
    Course,
    Lesson,
    Section,
    Assessment,
    Enrollment,
    Category,
    Tag,

    // Statuses
    CourseStatus,
    EnrollmentStatus,
    ContentType,

    // Pagination
    Paginated,
    PaginationMeta,
} from './models/common';
```

### Step 6: Type Guards and Utilities

**File: `resources/js/types/guards.ts`**
```typescript
import type {
    LessonContent,
    TextContent,
    VideoContent,
    YouTubeContent,
    AudioContent,
    DocumentContent,
    ConferenceContent
} from './models/lesson';
import type { CourseStatus, EnrollmentStatus, ContentType } from './models/common';

// Content type guards
export function isTextContent(content: LessonContent): content is TextContent {
    return content.type === 'text';
}

export function isVideoContent(content: LessonContent): content is VideoContent {
    return content.type === 'video';
}

export function isYouTubeContent(content: LessonContent): content is YouTubeContent {
    return content.type === 'youtube';
}

export function isAudioContent(content: LessonContent): content is AudioContent {
    return content.type === 'audio';
}

export function isDocumentContent(content: LessonContent): content is DocumentContent {
    return content.type === 'document';
}

export function isConferenceContent(content: LessonContent): content is ConferenceContent {
    return content.type === 'conference';
}

// Status helpers
export function isPublished(status: CourseStatus): boolean {
    return status === 'published';
}

export function isDraft(status: CourseStatus): boolean {
    return status === 'draft';
}

export function isActiveEnrollment(status: EnrollmentStatus): boolean {
    return status === 'active';
}

export function isCompletedEnrollment(status: EnrollmentStatus): boolean {
    return status === 'completed';
}

// Type assertion helpers
export function assertNever(value: never): never {
    throw new Error(`Unexpected value: ${value}`);
}

// Safe type narrowing for API responses
export function hasData<T>(response: { data?: T }): response is { data: T } {
    return response.data !== undefined;
}
```

---

## Migration Strategy

### Step 1: Create Types Without Breaking Changes
1. Create all type files as shown above
2. Types exist alongside current inline definitions
3. No existing code needs to change yet

### Step 2: Gradual Adoption (File by File)
```typescript
// BEFORE: Inline types in component
interface Course {
    id: number;
    title: string;
    // ...
}

// AFTER: Import from central types
import type { Course } from '@/types';
```

### Step 3: Migration Script
Create a helper script to identify inline type definitions:

```bash
# Find all inline interface definitions in Vue files
grep -r "interface Course" resources/js/pages/ --include="*.vue"
grep -r "interface Lesson" resources/js/pages/ --include="*.vue"
grep -r "interface Section" resources/js/pages/ --include="*.vue"
```

### Step 4: IDE Refactoring
Use VS Code's "Find and Replace" with regex:
- Find: `interface (Course|Lesson|Section|Assessment|Enrollment|Category|Tag) \{[^}]+\}`
- Replace with imports

---

## Checklist

### Infrastructure
- [ ] Create `types/models/common.ts` with base types
- [ ] Create `types/models/user.ts`
- [ ] Create `types/models/course.ts`
- [ ] Create `types/models/lesson.ts`
- [ ] Create `types/models/assessment.ts`
- [ ] Create `types/models/enrollment.ts`
- [ ] Create `types/api/responses.ts`
- [ ] Create `types/inertia.d.ts`
- [ ] Create `types/guards.ts`
- [ ] Update `types/index.d.ts` to export all

### Migration
- [ ] Configure TypeScript paths for `@/types`
- [ ] Migrate `pages/courses/` to use centralized types
- [ ] Migrate `pages/lessons/` to use centralized types
- [ ] Migrate `pages/assessments/` to use centralized types
- [ ] Migrate `pages/dashboard/` to use centralized types
- [ ] Remove all inline interface definitions
- [ ] Run TypeScript strict mode check

### Validation
- [ ] All pages compile without errors
- [ ] IDE autocomplete working correctly
- [ ] No `any` types in migrated files
- [ ] Type coverage report shows improvement

---

## Success Criteria

| Metric | Before | After |
|--------|--------|-------|
| Centralized type definitions | 40 lines | 1000+ lines |
| Inline interface definitions | 40+ | 0 |
| Type coverage | ~30% | 90%+ |
| IDE autocomplete accuracy | Partial | Full |

---

## Next Phase

After completing Type System Centralization, proceed to [Phase 2: Utility Extraction](./02-UTILITIES.md).
