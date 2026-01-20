// =============================================================================
// useAssessment Composable
// Assessment data and attempt management
// =============================================================================

import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import type {
    Assessment,
    AssessmentAttempt,
    Question,
    QuestionForAttempt,
    AttemptAnswer,
    SubmitAnswerData,
} from '@/types';

// =============================================================================
// Types
// =============================================================================

interface UseAssessmentOptions {
    /** Initial assessment data (from Inertia page props) */
    initial?: Assessment;
    /** Current attempt (if in progress) */
    attempt?: AssessmentAttempt | null;
    /** Course ID for navigation */
    courseId?: number;
}

// =============================================================================
// Composable
// =============================================================================

export function useAssessment(options: UseAssessmentOptions = {}) {
    const { initial, attempt: initialAttempt, courseId } = options;

    // =============================================================================
    // State
    // =============================================================================

    const assessment = ref<Assessment | null>(initial ?? null);
    const currentAttempt = ref<AssessmentAttempt | null>(initialAttempt ?? null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);

    // =============================================================================
    // Computed - Assessment Details
    // =============================================================================

    const assessmentId = computed(() => assessment.value?.id ?? null);

    const isPublished = computed(() =>
        assessment.value?.status === 'published'
    );

    const isDraft = computed(() =>
        assessment.value?.status === 'draft'
    );

    const totalQuestions = computed(() =>
        assessment.value?.total_questions ??
        assessment.value?.questions?.length ??
        assessment.value?.questions_count ??
        0
    );

    const totalPoints = computed(() =>
        assessment.value?.total_points ??
        assessment.value?.questions?.reduce((sum, q) => sum + q.points, 0) ??
        0
    );

    const passingScore = computed(() =>
        assessment.value?.passing_score ?? 70
    );

    const maxAttempts = computed(() =>
        assessment.value?.max_attempts ?? 1
    );

    const timeLimit = computed(() =>
        assessment.value?.time_limit_minutes ?? null
    );

    const hasTimeLimit = computed(() => timeLimit.value !== null);

    const shuffleQuestions = computed(() =>
        assessment.value?.shuffle_questions ?? false
    );

    const showCorrectAnswers = computed(() =>
        assessment.value?.show_correct_answers ?? false
    );

    const allowReview = computed(() =>
        assessment.value?.allow_review ?? false
    );

    // =============================================================================
    // Computed - User Attempt Status
    // =============================================================================

    const userAttempts = computed(() =>
        assessment.value?.user_attempts ?? []
    );

    const attemptCount = computed(() => userAttempts.value.length);

    const attemptsRemaining = computed(() =>
        assessment.value?.user_attempts_remaining ??
        Math.max(0, maxAttempts.value - attemptCount.value)
    );

    const canAttempt = computed(() => attemptsRemaining.value > 0);

    const hasInProgressAttempt = computed(() =>
        userAttempts.value.some(a => a.status === 'in_progress')
    );

    const inProgressAttemptId = computed(() =>
        userAttempts.value.find(a => a.status === 'in_progress')?.id ?? null
    );

    const bestScore = computed(() =>
        assessment.value?.user_best_score ?? null
    );

    const hasPassed = computed(() => {
        if (bestScore.value === null) return false;
        return bestScore.value >= passingScore.value;
    });

    // =============================================================================
    // Computed - Current Attempt
    // =============================================================================

    const isAttemptInProgress = computed(() =>
        currentAttempt.value?.status === 'in_progress'
    );

    const isAttemptSubmitted = computed(() =>
        currentAttempt.value?.status === 'submitted'
    );

    const isAttemptGraded = computed(() =>
        currentAttempt.value?.status === 'graded'
    );

    const attemptScore = computed(() =>
        currentAttempt.value?.score ?? null
    );

    const attemptPercentage = computed(() =>
        currentAttempt.value?.percentage ?? null
    );

    const attemptPassed = computed(() =>
        currentAttempt.value?.passed ?? false
    );

    // =============================================================================
    // Methods
    // =============================================================================

    /**
     * Start a new attempt
     */
    async function startAttempt(): Promise<boolean> {
        if (!assessmentId.value || !canAttempt.value) {
            error.value = 'Tidak dapat memulai percobaan';
            return false;
        }

        isLoading.value = true;
        error.value = null;

        return new Promise((resolve) => {
            const url = courseId
                ? `/courses/${courseId}/assessments/${assessmentId.value}/attempt`
                : `/assessments/${assessmentId.value}/attempt`;

            router.post(url, {}, {
                onSuccess: (page) => {
                    if (page.props.attempt) {
                        currentAttempt.value = page.props.attempt as AssessmentAttempt;
                    }
                    resolve(true);
                },
                onError: (errors) => {
                    error.value = errors.message || 'Gagal memulai percobaan';
                    resolve(false);
                },
                onFinish: () => {
                    isLoading.value = false;
                },
            });
        });
    }

    /**
     * Resume an in-progress attempt
     */
    function resumeAttempt(): void {
        if (!inProgressAttemptId.value || !assessmentId.value) return;

        const url = courseId
            ? `/courses/${courseId}/assessments/${assessmentId.value}/attempt/${inProgressAttemptId.value}`
            : `/assessments/${assessmentId.value}/attempt/${inProgressAttemptId.value}`;

        router.visit(url);
    }

    /**
     * Submit current attempt
     */
    async function submitAttempt(answers: SubmitAnswerData[]): Promise<boolean> {
        if (!currentAttempt.value) {
            error.value = 'Tidak ada percobaan aktif';
            return false;
        }

        isLoading.value = true;
        error.value = null;

        return new Promise((resolve) => {
            router.post(`/attempts/${currentAttempt.value!.id}/submit`, {
                answers,
            }, {
                onSuccess: () => {
                    resolve(true);
                },
                onError: (errors) => {
                    error.value = errors.message || 'Gagal mengirim jawaban';
                    resolve(false);
                },
                onFinish: () => {
                    isLoading.value = false;
                },
            });
        });
    }

    /**
     * Save answer for current attempt (auto-save)
     */
    async function saveAnswer(answer: SubmitAnswerData): Promise<void> {
        if (!currentAttempt.value) return;

        await router.post(`/attempts/${currentAttempt.value.id}/save-answer`, answer, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    /**
     * Set assessment data (from Inertia props)
     */
    function setAssessment(newAssessment: Assessment | null): void {
        assessment.value = newAssessment;
    }

    /**
     * Set current attempt data (from Inertia props)
     */
    function setCurrentAttempt(attempt: AssessmentAttempt | null): void {
        currentAttempt.value = attempt;
    }

    /**
     * Navigate to assessment detail page
     */
    function viewAssessment(): void {
        if (!assessmentId.value) return;

        const url = courseId
            ? `/courses/${courseId}/assessments/${assessmentId.value}`
            : `/assessments/${assessmentId.value}`;

        router.visit(url);
    }

    /**
     * Navigate to attempt results
     */
    function viewAttemptResults(attemptId?: number): void {
        const id = attemptId ?? currentAttempt.value?.id;
        if (!id) return;

        router.visit(`/attempts/${id}/results`);
    }

    // =============================================================================
    // Return
    // =============================================================================

    return {
        // State
        assessment,
        currentAttempt,
        isLoading,
        error,

        // Computed - Assessment Details
        assessmentId,
        isPublished,
        isDraft,
        totalQuestions,
        totalPoints,
        passingScore,
        maxAttempts,
        timeLimit,
        hasTimeLimit,
        shuffleQuestions,
        showCorrectAnswers,
        allowReview,

        // Computed - User Attempt Status
        userAttempts,
        attemptCount,
        attemptsRemaining,
        canAttempt,
        hasInProgressAttempt,
        inProgressAttemptId,
        bestScore,
        hasPassed,

        // Computed - Current Attempt
        isAttemptInProgress,
        isAttemptSubmitted,
        isAttemptGraded,
        attemptScore,
        attemptPercentage,
        attemptPassed,

        // Methods
        startAttempt,
        resumeAttempt,
        submitAttempt,
        saveAnswer,
        setAssessment,
        setCurrentAttempt,
        viewAssessment,
        viewAttemptResults,
    };
}
