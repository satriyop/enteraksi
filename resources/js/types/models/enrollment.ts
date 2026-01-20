/**
 * Enrollment-related type definitions
 *
 * Matches the actual Enrollment model and database schema.
 */

import type {
    Timestamps,
    EnrollmentId,
    CourseId,
    UserId,
    LessonId,
    EnrollmentStatus,
} from './common';
import type { User, UserSummary } from './user';
import type { LessonProgress, LessonSummary } from './lesson';

// =============================================================================
// Enrollment Course (minimal)
// =============================================================================

/**
 * Minimal course info for enrollment context.
 */
export interface EnrollmentCourse {
    id: CourseId;
    title: string;
    slug: string;
    thumbnail_url: string | null;
    total_lessons: number;
}

// =============================================================================
// Enrollment Types
// =============================================================================

/**
 * Full Enrollment model - matches database columns.
 */
export interface Enrollment extends Timestamps {
    id: EnrollmentId;
    user_id: UserId;
    course_id: CourseId;
    status: EnrollmentStatus;
    progress_percentage: number;
    enrolled_at: string;
    started_at: string | null;
    completed_at: string | null;
    invited_by: UserId | null;
    last_lesson_id: LessonId | null;

    // Model accessors
    is_completed?: boolean;
    is_active?: boolean;

    // Relations (conditionally loaded)
    user?: User;
    course?: EnrollmentCourse;
    invited_by_user?: User;
    last_lesson?: LessonSummary;
    lesson_progress?: LessonProgress[];
}

/**
 * Enrollment with full progress details (for learner dashboard).
 */
export interface EnrollmentWithProgress extends Enrollment {
    course: EnrollmentCourseWithDetails;
    lesson_progress: LessonProgress[];
    current_lesson_id?: LessonId;
    completed_lessons_count: number;
    total_lessons_count: number;
}

/**
 * Course with more details for enrollment context.
 */
export interface EnrollmentCourseWithDetails extends EnrollmentCourse {
    user: UserSummary; // Instructor
    difficulty_level: 'beginner' | 'intermediate' | 'advanced';
    duration: number;
}

/**
 * Enrollment for admin/instructor list views.
 */
export interface EnrollmentListItem {
    id: EnrollmentId;
    status: EnrollmentStatus;
    progress_percentage: number;
    enrolled_at: string;
    started_at: string | null;
    completed_at: string | null;

    // Minimal relations
    user: UserSummary;
    course: {
        id: CourseId;
        title: string;
    };
}

// =============================================================================
// Enrollment Statistics
// =============================================================================

/**
 * Enrollment statistics for dashboard/analytics.
 */
export interface EnrollmentStats {
    total_enrollments: number;
    active_enrollments: number;
    completed_enrollments: number;
    dropped_enrollments: number;
    average_progress: number;
    completion_rate: number;
}

/**
 * Course enrollment statistics (for instructor/admin).
 */
export interface CourseEnrollmentStats extends EnrollmentStats {
    course_id: CourseId;
    recent_enrollments: EnrollmentListItem[];
    top_performers: Array<{
        user: UserSummary;
        progress_percentage: number;
        completed_at: string | null;
    }>;
}

// =============================================================================
// Form Data Types
// =============================================================================

/**
 * Data for enrolling a user in a course.
 */
export interface EnrollUserData {
    user_id: UserId;
    course_id: CourseId;
    invited_by?: UserId;
}

/**
 * Data for bulk enrollment.
 */
export interface BulkEnrollData {
    user_ids: UserId[];
    course_id: CourseId;
    invited_by?: UserId;
}

/**
 * Data for updating enrollment status.
 */
export interface UpdateEnrollmentData {
    status?: EnrollmentStatus;
}

// =============================================================================
// Filter/Query Types
// =============================================================================

/**
 * Query parameters for filtering enrollments.
 */
export interface EnrollmentFilters {
    user_id?: UserId;
    course_id?: CourseId;
    status?: EnrollmentStatus | EnrollmentStatus[];
    min_progress?: number;
    max_progress?: number;
    enrolled_after?: string;
    enrolled_before?: string;
    sort_by?: 'enrolled_at' | 'progress_percentage' | 'started_at' | 'completed_at';
    sort_order?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
}

// =============================================================================
// Type Guards
// =============================================================================

/**
 * Check if enrollment is active.
 */
export function isActive(enrollment: Enrollment | EnrollmentListItem): boolean {
    return enrollment.status === 'active';
}

/**
 * Check if enrollment is completed.
 */
export function isCompleted(enrollment: Enrollment | EnrollmentListItem): boolean {
    return enrollment.status === 'completed';
}

/**
 * Check if enrollment is dropped.
 */
export function isDropped(enrollment: Enrollment | EnrollmentListItem): boolean {
    return enrollment.status === 'dropped';
}

/**
 * Check if user has started the course.
 */
export function hasStarted(enrollment: Enrollment | EnrollmentListItem): boolean {
    return enrollment.started_at !== null;
}

/**
 * Get enrollment status label in Indonesian.
 */
export function getEnrollmentStatusLabel(status: EnrollmentStatus): string {
    const labels: Record<EnrollmentStatus, string> = {
        active: 'Aktif',
        completed: 'Selesai',
        dropped: 'Keluar',
    };
    return labels[status];
}

/**
 * Get enrollment status color for badges.
 */
export function getEnrollmentStatusColor(status: EnrollmentStatus): string {
    const colors: Record<EnrollmentStatus, string> = {
        active: 'blue',
        completed: 'green',
        dropped: 'gray',
    };
    return colors[status];
}
