/**
 * Assessment-related type definitions
 *
 * Matches the actual Assessment, Question, AssessmentAttempt, and AttemptAnswer models.
 */

import type {
    Timestamps,
    SoftDeletes,
    AssessmentId,
    CourseId,
    UserId,
    QuestionId,
    AttemptId,
    AssessmentStatus,
    AttemptStatus,
    QuestionType,
    CourseVisibility,
} from './common';
import type { User, UserSummary } from './user';

// =============================================================================
// Assessment Types
// =============================================================================

/**
 * Full Assessment model - matches database columns and model accessors.
 */
export interface Assessment extends SoftDeletes {
    id: AssessmentId;
    course_id: CourseId;
    user_id: UserId;
    title: string;
    slug: string;
    description: string | null;
    instructions: string | null;
    time_limit_minutes: number | null;
    passing_score: number;
    max_attempts: number;
    shuffle_questions: boolean;
    show_correct_answers: boolean;
    allow_review: boolean;
    status: AssessmentStatus;
    visibility: CourseVisibility;
    published_at: string | null;
    published_by: UserId | null;

    // Model accessors
    total_questions?: number;
    total_points?: number;
    is_editable?: boolean;

    // Relations (conditionally loaded)
    user?: User;
    course?: AssessmentCourse;
    published_by_user?: User;
    questions?: Question[];

    // Aggregates
    questions_count?: number;

    // User context (when authenticated user is present)
    user_attempts?: AssessmentAttempt[];
    user_best_score?: number;
    user_attempts_remaining?: number;
}

/**
 * Minimal course info for assessment context.
 */
export interface AssessmentCourse {
    id: CourseId;
    title: string;
    slug: string;
}

/**
 * Assessment for list/index pages.
 */
export interface AssessmentListItem {
    id: AssessmentId;
    title: string;
    slug: string;
    description: string | null;
    status: AssessmentStatus;
    time_limit_minutes: number | null;
    passing_score: number;
    max_attempts: number;
    total_questions: number;
    total_points: number;
    created_at: string;

    // Minimal relations
    course: AssessmentCourse;
    user: UserSummary;
}

// =============================================================================
// Question Types
// =============================================================================

/**
 * Full Question model - matches database columns.
 */
export interface Question extends SoftDeletes {
    id: QuestionId;
    assessment_id: AssessmentId;
    question_text: string;
    question_type: QuestionType;
    points: number;
    feedback: string | null;
    order: number;

    // Relations (conditionally loaded)
    options?: QuestionOption[];

    // Aggregates
    options_count?: number;
}

/**
 * Question option for multiple choice / true-false.
 */
export interface QuestionOption extends SoftDeletes {
    id: number;
    question_id: QuestionId;
    option_text: string;
    is_correct: boolean;
    feedback: string | null;
    order: number;
}

/**
 * Question for taking assessment (without answers revealed).
 */
export interface QuestionForAttempt {
    id: QuestionId;
    question_text: string;
    question_type: QuestionType;
    points: number;
    order: number;

    // Options without is_correct (hidden during attempt)
    options?: Array<{
        id: number;
        option_text: string;
        order: number;
    }>;
}

/**
 * Question with grading info (after submission).
 */
export interface QuestionWithGrading extends Question {
    options: QuestionOption[]; // With is_correct revealed
    user_answer?: AttemptAnswer;
}

// =============================================================================
// Assessment Attempt Types
// =============================================================================

/**
 * Full AssessmentAttempt model - matches database columns.
 */
export interface AssessmentAttempt extends SoftDeletes {
    id: AttemptId;
    assessment_id: AssessmentId;
    user_id: UserId;
    attempt_number: number;
    status: AttemptStatus;
    score: number | null;
    max_score: number | null;
    percentage: number | null;
    passed: boolean | null;
    started_at: string | null;
    submitted_at: string | null;
    graded_at: string | null;
    graded_by: UserId | null;
    feedback: string | null;

    // Relations (conditionally loaded)
    user?: User;
    assessment?: Assessment;
    graded_by_user?: User;
    answers?: AttemptAnswer[];
}

/**
 * Attempt answer - matches database columns.
 */
export interface AttemptAnswer extends SoftDeletes {
    id: number;
    assessment_attempt_id: AttemptId;
    question_id: QuestionId;
    answer_text: string | null;
    file_path: string | null;
    is_correct: boolean | null;
    score: number | null;
    feedback: string | null;
    graded_by: UserId | null;
    graded_at: string | null;

    // Relations
    question?: Question;
    graded_by_user?: User;

    // Accessors
    file_url?: string | null;
}

/**
 * Attempt for list display.
 */
export interface AttemptListItem {
    id: AttemptId;
    attempt_number: number;
    status: AttemptStatus;
    score: number | null;
    max_score: number | null;
    percentage: number | null;
    passed: boolean | null;
    started_at: string | null;
    submitted_at: string | null;
    graded_at: string | null;
}

// =============================================================================
// Form Data Types
// =============================================================================

/**
 * Data for creating a new assessment.
 */
export interface CreateAssessmentData {
    title: string;
    description?: string | null;
    instructions?: string | null;
    time_limit_minutes?: number | null;
    passing_score?: number;
    max_attempts?: number;
    shuffle_questions?: boolean;
    show_correct_answers?: boolean;
    allow_review?: boolean;
    visibility?: CourseVisibility;
}

/**
 * Data for updating an assessment.
 */
export interface UpdateAssessmentData extends Partial<CreateAssessmentData> {
    status?: AssessmentStatus;
}

/**
 * Data for creating a question.
 */
export interface CreateQuestionData {
    question_text: string;
    question_type: QuestionType;
    points?: number;
    feedback?: string | null;
    options?: CreateQuestionOptionData[];
}

/**
 * Data for creating a question option.
 */
export interface CreateQuestionOptionData {
    option_text: string;
    is_correct: boolean;
    feedback?: string | null;
}

/**
 * Data for submitting an answer.
 */
export interface SubmitAnswerData {
    question_id: QuestionId;
    answer_text?: string | null;
    selected_option_ids?: number[];
    file?: File | null;
}

/**
 * Data for submitting entire attempt.
 */
export interface SubmitAttemptData {
    answers: SubmitAnswerData[];
}

/**
 * Data for grading an answer (manual grading).
 */
export interface GradeAnswerData {
    score: number;
    feedback?: string | null;
}

// =============================================================================
// Filter/Query Types
// =============================================================================

/**
 * Query parameters for filtering assessments.
 */
export interface AssessmentFilters {
    search?: string;
    course_id?: CourseId;
    status?: AssessmentStatus;
    user_id?: UserId;
    sort_by?: 'title' | 'created_at' | 'published_at';
    sort_order?: 'asc' | 'desc';
    page?: number;
    per_page?: number;
}

// =============================================================================
// Permission/Capability Types
// =============================================================================

/**
 * Permissions for assessment actions.
 */
export interface AssessmentPermissions {
    edit: boolean;
    delete: boolean;
    publish: boolean;
    viewAttempts: boolean;
    gradeAttempts: boolean;
}

/**
 * Capability check for taking assessment.
 */
export interface AssessmentAttemptability {
    canAttempt: boolean;
    reason?: string;
    attemptsRemaining: number;
    hasInProgressAttempt: boolean;
    currentAttemptId?: AttemptId;
}

// =============================================================================
// Type Guards
// =============================================================================

/**
 * Check if question requires manual grading.
 */
export function requiresManualGrading(question: Question | QuestionForAttempt): boolean {
    return ['essay', 'file_upload'].includes(question.question_type);
}

/**
 * Check if question is multiple choice.
 */
export function isMultipleChoice(question: Question | QuestionForAttempt): boolean {
    return question.question_type === 'multiple_choice';
}

/**
 * Check if question is true/false.
 */
export function isTrueFalse(question: Question | QuestionForAttempt): boolean {
    return question.question_type === 'true_false';
}

/**
 * Check if attempt is in progress.
 */
export function isAttemptInProgress(attempt: AssessmentAttempt | AttemptListItem): boolean {
    return attempt.status === 'in_progress';
}

/**
 * Check if attempt needs grading.
 */
export function needsGrading(attempt: AssessmentAttempt | AttemptListItem): boolean {
    return attempt.status === 'submitted';
}

/**
 * Get question type label in Indonesian.
 */
export function getQuestionTypeLabel(type: QuestionType): string {
    const labels: Record<QuestionType, string> = {
        multiple_choice: 'Pilihan Ganda',
        true_false: 'Benar/Salah',
        matching: 'Pencocokan',
        short_answer: 'Jawaban Singkat',
        essay: 'Esai',
        file_upload: 'Unggah Berkas',
    };
    return labels[type];
}

/**
 * Get attempt status label in Indonesian.
 */
export function getAttemptStatusLabel(status: AttemptStatus): string {
    const labels: Record<AttemptStatus, string> = {
        in_progress: 'Sedang Dikerjakan',
        submitted: 'Sudah Dikirim',
        graded: 'Sudah Dinilai',
        completed: 'Selesai',
    };
    return labels[status];
}
