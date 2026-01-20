/**
 * Common types shared across all models
 *
 * These types align with Laravel's standard patterns and the actual database schema.
 */

// =============================================================================
// Timestamp Types
// =============================================================================

/**
 * Standard Laravel timestamps.
 * All models using HasTimestamps trait include these fields.
 */
export interface Timestamps {
    created_at: string;
    updated_at: string;
}

/**
 * Extended timestamps for models with SoftDeletes trait.
 */
export interface SoftDeletes extends Timestamps {
    deleted_at: string | null;
}

// =============================================================================
// Pagination Types (Laravel Standard)
// =============================================================================

/**
 * Navigation links from Laravel's paginate() method.
 */
export interface PaginationLinks {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
}

/**
 * Individual page link in the links array.
 */
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Metadata from Laravel's paginate() method.
 */
export interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

/**
 * Full paginated response from Laravel's paginate() method.
 */
export interface Paginated<T> {
    data: T[];
    links: PaginationLinks;
    meta: PaginationMeta;
}

/**
 * Simplified paginated response from Laravel's simplePaginate() method.
 */
export interface SimplePaginated<T> {
    data: T[];
    current_page: number;
    per_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
}

// =============================================================================
// Status Enums (matching actual database ENUMs)
// =============================================================================

/**
 * Course status - matches database ENUM('draft', 'published', 'archived')
 */
export const CourseStatus = {
    DRAFT: 'draft',
    PUBLISHED: 'published',
    ARCHIVED: 'archived',
} as const;
export type CourseStatus = (typeof CourseStatus)[keyof typeof CourseStatus];

/**
 * Course visibility - matches database ENUM('public', 'restricted', 'hidden')
 */
export const CourseVisibility = {
    PUBLIC: 'public',
    RESTRICTED: 'restricted',
    HIDDEN: 'hidden',
} as const;
export type CourseVisibility = (typeof CourseVisibility)[keyof typeof CourseVisibility];

/**
 * Difficulty level - matches database ENUM('beginner', 'intermediate', 'advanced')
 */
export const DifficultyLevel = {
    BEGINNER: 'beginner',
    INTERMEDIATE: 'intermediate',
    ADVANCED: 'advanced',
} as const;
export type DifficultyLevel = (typeof DifficultyLevel)[keyof typeof DifficultyLevel];

/**
 * Enrollment status - matches database ENUM('active', 'completed', 'dropped')
 */
export const EnrollmentStatus = {
    ACTIVE: 'active',
    COMPLETED: 'completed',
    DROPPED: 'dropped',
} as const;
export type EnrollmentStatus = (typeof EnrollmentStatus)[keyof typeof EnrollmentStatus];

/**
 * Lesson content type - matches database ENUM('text', 'video', 'audio', 'document', 'youtube', 'conference')
 */
export const ContentType = {
    TEXT: 'text',
    VIDEO: 'video',
    AUDIO: 'audio',
    DOCUMENT: 'document',
    YOUTUBE: 'youtube',
    CONFERENCE: 'conference',
} as const;
export type ContentType = (typeof ContentType)[keyof typeof ContentType];

/**
 * Conference type - matches database ENUM('zoom', 'google_meet', 'other')
 */
export const ConferenceType = {
    ZOOM: 'zoom',
    GOOGLE_MEET: 'google_meet',
    OTHER: 'other',
} as const;
export type ConferenceType = (typeof ConferenceType)[keyof typeof ConferenceType];

/**
 * Assessment status - matches database ENUM('draft', 'published', 'archived')
 */
export const AssessmentStatus = {
    DRAFT: 'draft',
    PUBLISHED: 'published',
    ARCHIVED: 'archived',
} as const;
export type AssessmentStatus = (typeof AssessmentStatus)[keyof typeof AssessmentStatus];

/**
 * Assessment attempt status - matches database ENUM('in_progress', 'submitted', 'graded', 'completed')
 */
export const AttemptStatus = {
    IN_PROGRESS: 'in_progress',
    SUBMITTED: 'submitted',
    GRADED: 'graded',
    COMPLETED: 'completed',
} as const;
export type AttemptStatus = (typeof AttemptStatus)[keyof typeof AttemptStatus];

/**
 * Question type - matches database ENUM
 */
export const QuestionType = {
    MULTIPLE_CHOICE: 'multiple_choice',
    TRUE_FALSE: 'true_false',
    MATCHING: 'matching',
    SHORT_ANSWER: 'short_answer',
    ESSAY: 'essay',
    FILE_UPLOAD: 'file_upload',
} as const;
export type QuestionType = (typeof QuestionType)[keyof typeof QuestionType];

// =============================================================================
// ID Type Aliases (for semantic clarity)
// =============================================================================

export type UserId = number;
export type CourseId = number;
export type CourseSectionId = number;
export type LessonId = number;
export type EnrollmentId = number;
export type AssessmentId = number;
export type AttemptId = number;
export type QuestionId = number;
export type CategoryId = number;
export type TagId = number;

// =============================================================================
// Utility Types
// =============================================================================

/**
 * Makes specified properties required while keeping others optional.
 */
export type WithRequired<T, K extends keyof T> = T & { [P in K]-?: T[P] };

/**
 * Makes specified properties optional while keeping others required.
 */
export type WithOptional<T, K extends keyof T> = Omit<T, K> & Partial<Pick<T, K>>;

/**
 * Pick specific properties and make them required.
 */
export type PickRequired<T, K extends keyof T> = Required<Pick<T, K>>;

/**
 * Generic API success response wrapper.
 */
export interface ApiResponse<T> {
    data: T;
    message?: string;
}

/**
 * Generic API error response.
 */
export interface ApiErrorResponse {
    message: string;
    errors?: Record<string, string[]>;
}
