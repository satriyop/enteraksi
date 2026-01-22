/**
 * Learning Path Type Definitions
 *
 * Types for learner-facing learning path features including enrollment,
 * progress tracking, and course sequencing.
 */

import type { DifficultyLevel, PaginationLink } from '@/types';

// =============================================================================
// State Enums
// =============================================================================

/**
 * Learning path enrollment state - matches database ENUM
 */
export const LearningPathEnrollmentState = {
    ACTIVE: 'active',
    COMPLETED: 'completed',
    DROPPED: 'dropped',
} as const;
export type LearningPathEnrollmentState =
    (typeof LearningPathEnrollmentState)[keyof typeof LearningPathEnrollmentState];

/**
 * Course progress status within a learning path
 */
export const CourseProgressStatus = {
    LOCKED: 'locked',
    AVAILABLE: 'available',
    IN_PROGRESS: 'in_progress',
    COMPLETED: 'completed',
} as const;
export type CourseProgressStatus =
    (typeof CourseProgressStatus)[keyof typeof CourseProgressStatus];

// =============================================================================
// Learning Path Browse Types
// =============================================================================

/**
 * Learning path item for browse/discovery pages
 */
export interface LearningPathItem {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    thumbnail_url: string | null;
    difficulty_level: DifficultyLevel;
    estimated_duration: number;
    courses_count: number;
    creator: { id: number; name: string } | null;
}

/**
 * Paginated learning paths response
 */
export interface PaginatedLearningPaths {
    data: LearningPathItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

// =============================================================================
// Learning Path Enrollment Types
// =============================================================================

/**
 * Learning path enrollment item for index/list pages
 */
export interface LearningPathEnrollmentItem {
    id: number;
    learning_path: LearningPathItem;
    state: LearningPathEnrollmentState;
    progress_percentage: number;
    completed_courses: number;
    total_courses: number;
    enrolled_at: string;
    completed_at: string | null;
}

/**
 * Paginated enrollments response
 */
export interface PaginatedLearningPathEnrollments {
    data: LearningPathEnrollmentItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
}

// =============================================================================
// Learning Path Detail Types
// =============================================================================

/**
 * Course within a learning path
 */
export interface LearningPathCourse {
    id: number;
    course_id: number;
    title: string;
    slug: string;
    short_description: string | null;
    thumbnail_path: string | null;
    difficulty_level: DifficultyLevel;
    estimated_duration_minutes: number;
    position: number;
    is_required: boolean;
    lessons_count: number;
}

/**
 * Learning path detail for show page
 */
export interface LearningPathDetail {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    thumbnail_url: string | null;
    difficulty_level: DifficultyLevel;
    estimated_duration: number;
    learning_objectives: string[] | null;
    prerequisites: string[] | null;
    courses_count: number;
    enrollments_count: number;
    creator: { id: number; name: string } | null;
    courses: LearningPathCourse[];
    created_at: string;
    updated_at: string;
}

/**
 * Learning path enrollment for show page context
 */
export interface LearningPathEnrollment {
    id: number;
    learning_path_id: number;
    user_id: number;
    state: LearningPathEnrollmentState;
    enrolled_at: string;
    completed_at: string | null;
    dropped_at: string | null;
}

// =============================================================================
// Progress Tracking Types
// =============================================================================

/**
 * Individual course progress within a learning path
 */
export interface CourseProgressItem {
    course_id: number;
    course_title: string;
    course_slug: string;
    status: CourseProgressStatus;
    position: number;
    is_required: boolean;
    completion_percentage: number;
    lock_reason: string | null;
    enrollment_id: number | null;
    lessons_count: number;
    completed_lessons: number;
    estimated_duration_minutes: number;
    time_spent_minutes: number;
}

/**
 * Overall path progress data
 */
export interface PathProgressData {
    path_enrollment_id: number;
    overall_percentage: number;
    total_courses: number;
    completed_courses: number;
    in_progress_courses: number;
    locked_courses: number;
    available_courses: number;
    total_time_spent_minutes: number;
    courses: CourseProgressItem[];
    is_completed: boolean;
    completed_at: string | null;
}

// =============================================================================
// Page Props Types
// =============================================================================

/**
 * Props for Browse page
 */
export interface BrowsePageProps {
    learningPaths: PaginatedLearningPaths;
    enrolledPathIds: number[];
    filters: {
        search?: string;
        difficulty?: DifficultyLevel | '';
    };
}

/**
 * Props for Index page (my learning paths)
 */
export interface IndexPageProps {
    enrollments: PaginatedLearningPathEnrollments;
    filters: {
        status?: LearningPathEnrollmentState | '';
    };
}

/**
 * Props for Show page
 */
export interface ShowPageProps {
    learningPath: LearningPathDetail;
    enrollment: LearningPathEnrollment | null;
    progress: PathProgressData | null;
    canEnroll: boolean;
}

/**
 * Props for Progress page
 */
export interface ProgressPageProps {
    learningPath: LearningPathDetail;
    enrollment: LearningPathEnrollment;
    progress: PathProgressData;
}

// =============================================================================
// Color & Label Utilities
// =============================================================================

/**
 * State colors for enrollment badges
 */
export const LEARNING_PATH_STATE_COLORS: Record<
    LearningPathEnrollmentState,
    { bg: string; text: string; border: string }
> = {
    active: {
        bg: 'bg-blue-100 dark:bg-blue-900',
        text: 'text-blue-700 dark:text-blue-300',
        border: 'border-blue-200 dark:border-blue-800',
    },
    completed: {
        bg: 'bg-green-100 dark:bg-green-900',
        text: 'text-green-700 dark:text-green-300',
        border: 'border-green-200 dark:border-green-800',
    },
    dropped: {
        bg: 'bg-gray-100 dark:bg-gray-800',
        text: 'text-gray-700 dark:text-gray-300',
        border: 'border-gray-200 dark:border-gray-700',
    },
};

/**
 * Status colors for course progress
 */
export const COURSE_PROGRESS_STATUS_COLORS: Record<
    CourseProgressStatus,
    { bg: string; text: string; icon: string }
> = {
    locked: {
        bg: 'bg-gray-100 dark:bg-gray-800',
        text: 'text-gray-500 dark:text-gray-400',
        icon: 'text-gray-400',
    },
    available: {
        bg: 'bg-blue-100 dark:bg-blue-900',
        text: 'text-blue-700 dark:text-blue-300',
        icon: 'text-blue-500',
    },
    in_progress: {
        bg: 'bg-yellow-100 dark:bg-yellow-900',
        text: 'text-yellow-700 dark:text-yellow-300',
        icon: 'text-yellow-500',
    },
    completed: {
        bg: 'bg-green-100 dark:bg-green-900',
        text: 'text-green-700 dark:text-green-300',
        icon: 'text-green-500',
    },
};

/**
 * Get Indonesian label for enrollment state
 */
export function enrollmentStateLabel(state: LearningPathEnrollmentState): string {
    const labels: Record<LearningPathEnrollmentState, string> = {
        active: 'Aktif',
        completed: 'Selesai',
        dropped: 'Dihentikan',
    };
    return labels[state] ?? state;
}

/**
 * Get Indonesian label for course progress status
 */
export function courseProgressStatusLabel(status: CourseProgressStatus): string {
    const labels: Record<CourseProgressStatus, string> = {
        locked: 'Terkunci',
        available: 'Tersedia',
        in_progress: 'Sedang Dikerjakan',
        completed: 'Selesai',
    };
    return labels[status] ?? status;
}
