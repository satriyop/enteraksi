/**
 * Course-related type definitions
 *
 * Matches the actual Course, Category, Tag models and database schema.
 */

import type {
    Timestamps,
    SoftDeletes,
    CourseId,
    UserId,
    CategoryId,
    TagId,
    CourseStatus,
    CourseVisibility,
    DifficultyLevel,
} from './common';
import type { User, UserSummary } from './user';

// =============================================================================
// Category Types
// =============================================================================

/**
 * Category model - matches database columns.
 */
export interface Category extends Timestamps {
    id: CategoryId;
    name: string;
    slug: string;
    description: string | null;
    parent_id: CategoryId | null;
    order: number;

    // Relations (conditionally loaded)
    parent?: Category;
    children?: Category[];

    // Aggregates (conditionally loaded)
    courses_count?: number;
}

/**
 * Minimal category for dropdowns and references.
 */
export interface CategorySummary {
    id: CategoryId;
    name: string;
    slug?: string;
}

// =============================================================================
// Tag Types
// =============================================================================

/**
 * Tag model - matches database columns.
 */
export interface Tag extends Timestamps {
    id: TagId;
    name: string;
    slug: string;

    // Aggregates (conditionally loaded)
    courses_count?: number;
}

/**
 * Minimal tag for display.
 */
export interface TagSummary {
    id: TagId;
    name: string;
}

// =============================================================================
// Course Types
// =============================================================================

/**
 * Full Course model - matches database columns and model accessors.
 *
 * Note: Relations are optional because they're conditionally loaded.
 */
export interface Course extends SoftDeletes {
    id: CourseId;
    user_id: UserId;
    title: string;
    slug: string;
    short_description: string | null;
    long_description: string | null;
    objectives: string[] | null;
    prerequisites: string[] | null;
    category_id: CategoryId | null;
    thumbnail_path: string | null;
    status: CourseStatus;
    visibility: CourseVisibility;
    difficulty_level: DifficultyLevel;
    estimated_duration_minutes: number | null;
    manual_duration_minutes: number | null;
    published_at: string | null;
    published_by: UserId | null;

    // Model accessors (computed properties)
    duration?: number; // manual_duration_minutes ?? estimated_duration_minutes ?? 0
    total_lessons?: number;
    is_editable?: boolean;
    thumbnail_url?: string | null;
    average_rating?: number | null;
    ratings_count?: number;

    // Relations (conditionally loaded)
    user?: User;
    category?: Category;
    tags?: Tag[];
    sections?: CourseSection[];
    published_by_user?: User;

    // Aggregates (conditionally loaded)
    sections_count?: number;
    lessons_count?: number;
    enrollments_count?: number;
}

/**
 * Course section (module) - matches database columns.
 */
export interface CourseSection extends Timestamps {
    id: number;
    course_id: CourseId;
    title: string;
    description: string | null;
    order: number;
    estimated_duration_minutes: number | null;

    // Model accessors
    total_lessons?: number;
    duration?: number;

    // Relations (conditionally loaded)
    lessons?: Lesson[];

    // Aggregates
    lessons_count?: number;
}

/**
 * Minimal lesson info within a section (for curriculum display).
 * Forward declaration - full Lesson type is in lesson.ts
 */
export interface Lesson {
    id: number;
    course_section_id: number;
    title: string;
    description: string | null;
    order: number;
    content_type: 'text' | 'video' | 'audio' | 'document' | 'youtube' | 'conference';
    estimated_duration_minutes: number | null;
    is_free_preview: boolean;

    // Additional fields that may be present depending on context
    rich_content?: Record<string, unknown> | null;
    rich_content_html?: string | null;
    youtube_url?: string | null;
    youtube_video_id?: string | null;
    conference_url?: string | null;
    conference_type?: 'zoom' | 'google_meet' | 'other' | null;

    // Progress info (when user context is present)
    is_completed?: boolean;
}

/**
 * Course for list/index pages - lighter representation.
 */
export interface CourseListItem {
    id: CourseId;
    title: string;
    slug: string;
    short_description: string | null;
    thumbnail_url: string | null;
    status: CourseStatus;
    visibility: CourseVisibility;
    difficulty_level: DifficultyLevel;
    duration: number;
    lessons_count: number;
    enrollments_count: number;
    average_rating: number | null;
    ratings_count: number;
    created_at: string;

    // Minimal relations
    user: UserSummary;
    category: CategorySummary | null;
    tags?: TagSummary[];
}

/**
 * Course with full curriculum (sections + lessons).
 */
export interface CourseWithCurriculum extends Course {
    sections: CourseSectionWithLessons[];
}

/**
 * Section with lessons loaded.
 */
export interface CourseSectionWithLessons extends CourseSection {
    lessons: Lesson[];
}

// =============================================================================
// Form Data Types
// =============================================================================

/**
 * Data for creating a new course.
 */
export interface CreateCourseData {
    title: string;
    short_description?: string;
    long_description?: string;
    objectives?: string[];
    prerequisites?: string[];
    category_id?: CategoryId | null;
    difficulty_level?: DifficultyLevel;
    visibility?: CourseVisibility;
    manual_duration_minutes?: number | null;
    tag_ids?: TagId[];
    thumbnail?: File | null;
}

/**
 * Data for updating an existing course.
 */
export interface UpdateCourseData extends Partial<CreateCourseData> {
    status?: CourseStatus;
}

/**
 * Data for creating/updating a section.
 */
export interface SectionData {
    title: string;
    description?: string | null;
    order?: number;
}

// =============================================================================
// Filter/Query Types
// =============================================================================

/**
 * Query parameters for filtering courses list.
 */
export interface CourseFilters {
    search?: string;
    status?: CourseStatus | CourseStatus[];
    visibility?: CourseVisibility;
    category_id?: CategoryId;
    difficulty_level?: DifficultyLevel;
    user_id?: UserId;
    tag_ids?: TagId[];
    sort_by?: 'title' | 'created_at' | 'published_at' | 'enrollments_count' | 'average_rating';
    sort_order?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
}

// =============================================================================
// Permission/Capability Types
// =============================================================================

/**
 * Permissions for course actions (passed from backend).
 */
export interface CoursePermissions {
    publish: boolean;
    setStatus: boolean;
    setVisibility: boolean;
    delete: boolean;
    edit: boolean;
    manageEnrollments: boolean;
}

// =============================================================================
// Type Guards
// =============================================================================

/**
 * Check if course is published.
 */
export function isPublished(course: Course | CourseListItem): boolean {
    return course.status === 'published';
}

/**
 * Check if course is draft.
 */
export function isDraft(course: Course | CourseListItem): boolean {
    return course.status === 'draft';
}

/**
 * Check if course is archived.
 */
export function isArchived(course: Course | CourseListItem): boolean {
    return course.status === 'archived';
}

/**
 * Check if course is publicly visible.
 */
export function isPubliclyVisible(course: Course | CourseListItem): boolean {
    return course.visibility === 'public' && course.status === 'published';
}
