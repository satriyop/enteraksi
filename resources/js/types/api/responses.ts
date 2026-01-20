/**
 * API/Inertia Response Type Definitions
 *
 * These types represent the data structures passed to Inertia pages from controllers.
 * They help ensure type safety between backend and frontend.
 */

import type { Paginated, CategoryId } from '../models/common';
import type { User } from '../models/user';
import type { Course, CourseFilters, Category, Tag, CourseSection, CoursePermissions } from '../models/course';
import type { Lesson, LessonProgress, Media } from '../models/lesson';
import type { Assessment, AssessmentListItem, Question, AssessmentAttempt, AssessmentPermissions, AssessmentAttemptability } from '../models/assessment';
import type { Enrollment, EnrollmentWithProgress, EnrollmentListItem, EnrollmentStats } from '../models/enrollment';

// =============================================================================
// Course Page Responses
// =============================================================================

/**
 * Props for courses/Index page.
 */
export interface CoursesIndexPageProps {
    courses: Paginated<CourseWithCounts>;
    categories: Category[];
    filters: CourseFilters;
}

/**
 * Course with aggregation counts for list display.
 * Uses intersection to add required relations to base Course type.
 */
export type CourseWithCounts = Course & {
    category: Category | null;
    user: User;
    tags: Tag[];
    sections_count: number;
    lessons_count: number;
    enrollments_count: number;
};

/**
 * Props for courses/Create page.
 */
export interface CourseCreatePageProps {
    categories: Category[];
    tags: Tag[];
}

/**
 * Props for courses/Edit page.
 */
export interface CourseEditPageProps {
    course: CourseForEdit;
    categories: Category[];
    tags: Tag[];
    can: CoursePermissions;
}

/**
 * Course structure for edit page.
 */
export type CourseForEdit = Course & {
    tags: Tag[];
    sections: CourseSectionWithLessons[];
};

/**
 * Section with lessons for edit page.
 */
export type CourseSectionWithLessons = CourseSection & {
    lessons: LessonForCurriculum[];
};

/**
 * Lesson in curriculum (outline) context.
 */
export interface LessonForCurriculum {
    id: number;
    title: string;
    description: string | null;
    order: number;
    content_type: 'text' | 'video' | 'audio' | 'document' | 'youtube' | 'conference';
    estimated_duration_minutes: number | null;
    is_free_preview: boolean;
}

/**
 * Props for courses/Show page.
 */
export interface CourseShowPageProps {
    course: CourseWithDetails;
    enrollment: Enrollment | null;
    userRating: CourseRating | null;
    ratings: CourseRating[];
    can: {
        enroll: boolean;
        edit: boolean;
        manage: boolean;
    };
}

/**
 * Course with full details for show page.
 */
export type CourseWithDetails = Course & {
    category: Category | null;
    user: User;
    tags: Tag[];
    sections: CourseSectionWithLessons[];
    lessons_count: number;
    enrollments_count: number;
    average_rating: number | null;
    ratings_count: number;
};

/**
 * Course rating.
 */
export interface CourseRating {
    id: number;
    course_id: number;
    user_id: number;
    rating: number;
    review: string | null;
    created_at: string;
    user?: {
        id: number;
        name: string;
    };
}

// =============================================================================
// Lesson Page Responses
// =============================================================================

/**
 * Props for lessons/Show page.
 */
export interface LessonShowPageProps {
    lesson: LessonForViewing;
    course: LessonCourse;
    enrollment: EnrollmentWithProgress;
    progress: LessonProgress | null;
    previousLesson: LessonNavItem | null;
    nextLesson: LessonNavItem | null;
}

/**
 * Full lesson for viewing/learning.
 */
export type LessonForViewing = Lesson & {
    media: Media[];
    section: {
        id: number;
        title: string;
        course_id: number;
    };
};

/**
 * Course context for lesson page.
 */
export interface LessonCourse {
    id: number;
    title: string;
    slug: string;
    user: {
        id: number;
        name: string;
    };
    category: Category | null;
    sections: Array<{
        id: number;
        title: string;
        order: number;
        lessons: Array<{
            id: number;
            title: string;
            content_type: string;
            is_free_preview: boolean;
            order: number;
            estimated_duration_minutes: number | null;
            is_completed?: boolean;
        }>;
    }>;
}

/**
 * Navigation item for previous/next lesson.
 */
export interface LessonNavItem {
    id: number;
    title: string;
    section_title: string;
    is_completed?: boolean;
}

/**
 * Props for lessons/Edit page.
 */
export interface LessonEditPageProps {
    lesson: LessonForEditing;
    course: {
        id: number;
        title: string;
        slug: string;
    };
    section: {
        id: number;
        title: string;
    };
}

/**
 * Lesson for editing.
 */
export interface LessonForEditing extends Lesson {
    media: Media[];
}

// =============================================================================
// Assessment Page Responses
// =============================================================================

/**
 * Props for assessments/Index page.
 */
export interface AssessmentsIndexPageProps {
    assessments: Paginated<AssessmentListItem>;
    course?: {
        id: number;
        title: string;
    };
    filters: {
        search?: string;
        status?: string;
    };
}

/**
 * Props for assessments/Show page.
 */
export interface AssessmentShowPageProps {
    assessment: AssessmentWithQuestions;
    course: {
        id: number;
        title: string;
        slug: string;
    };
    attemptability: AssessmentAttemptability;
    userAttempts: AssessmentAttempt[];
    can: AssessmentPermissions;
}

/**
 * Assessment with questions loaded.
 */
export interface AssessmentWithQuestions extends Assessment {
    questions: Question[];
}

/**
 * Props for assessments/Take page (during attempt).
 */
export interface AssessmentTakePageProps {
    assessment: {
        id: number;
        title: string;
        description: string | null;
        instructions: string | null;
        time_limit_minutes: number | null;
        shuffle_questions: boolean;
    };
    attempt: AssessmentAttempt;
    questions: QuestionForAttempt[];
    timeRemaining: number | null;
}

/**
 * Question during attempt (without correct answers).
 */
export interface QuestionForAttempt {
    id: number;
    question_text: string;
    question_type: string;
    points: number;
    order: number;
    options?: Array<{
        id: number;
        option_text: string;
        order: number;
    }>;
}

/**
 * Props for assessments/Grade page.
 */
export interface AssessmentGradePageProps {
    assessment: Assessment;
    attempt: AssessmentAttemptWithAnswers;
    course: {
        id: number;
        title: string;
    };
}

/**
 * Attempt with answers for grading.
 */
export type AssessmentAttemptWithAnswers = AssessmentAttempt & {
    answers: AttemptAnswerWithQuestion[];
    user: User;
};

/**
 * Answer with question for grading context.
 */
export interface AttemptAnswerWithQuestion {
    id: number;
    question_id: number;
    answer_text: string | null;
    file_path: string | null;
    file_url: string | null;
    is_correct: boolean | null;
    score: number | null;
    feedback: string | null;
    question: Question;
}

// =============================================================================
// Enrollment/Dashboard Page Responses
// =============================================================================

/**
 * Props for dashboard page.
 */
export interface DashboardPageProps {
    enrollments: EnrollmentWithProgress[];
    recentActivity: ActivityItem[];
    stats: DashboardStats;
}

/**
 * Activity item for dashboard.
 */
export interface ActivityItem {
    id: number;
    type: 'lesson_completed' | 'assessment_passed' | 'course_started' | 'course_completed' | 'course_enrolled';
    description: string;
    course?: {
        id: number;
        title: string;
        slug: string;
    };
    timestamp: string;
}

/**
 * Dashboard statistics.
 */
export interface DashboardStats {
    courses_in_progress: number;
    courses_completed: number;
    total_time_spent: number;
    average_score: number;
}

/**
 * Props for enrollments management page.
 */
export interface EnrollmentsIndexPageProps {
    enrollments: Paginated<EnrollmentListItem>;
    course?: {
        id: number;
        title: string;
        slug: string;
    };
    stats: EnrollmentStats;
    filters: {
        status?: string;
        search?: string;
    };
}

// =============================================================================
// User/Admin Page Responses
// =============================================================================

/**
 * Props for users/Index page (admin).
 */
export interface UsersIndexPageProps {
    users: Paginated<UserWithCounts>;
    roles: Array<{
        value: string;
        label: string;
        count: number;
    }>;
    filters: {
        search?: string;
        role?: string;
    };
}

/**
 * User with counts for admin list.
 */
export interface UserWithCounts extends User {
    courses_count: number;
    enrollments_count: number;
}

// =============================================================================
// Flash Messages
// =============================================================================

/**
 * Flash messages passed via Inertia.
 */
export interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
}
