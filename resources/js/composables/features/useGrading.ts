// =============================================================================
// useGrading Composable
// Assessment grading state and actions management
// =============================================================================

import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

// =============================================================================
// Types
// =============================================================================

interface Question {
    id: number;
    question_type: string;
    points: number;
}

interface Answer {
    question_id: number;
    answer: string | string[] | Record<string, string>;
    score: number | null;
    feedback: string | null;
    is_correct: boolean | null;
}

interface GradeData {
    score: number;
    feedback?: string;
}

interface UseGradingOptions {
    /** Assessment attempt ID */
    attemptId: number;
    /** Questions with their point values */
    questions: Question[];
    /** Existing answers with scores */
    answers: Answer[];
    /** Passing score percentage */
    passingScore: number;
    /** Callback when grading is saved */
    onSave?: () => void;
}

// =============================================================================
// Composable
// =============================================================================

export function useGrading(options: UseGradingOptions) {
    const {
        attemptId,
        questions,
        answers: initialAnswers,
        passingScore,
        onSave,
    } = options;

    // =============================================================================
    // State
    // =============================================================================

    const grades = ref<Record<number, GradeData>>({});
    const isSaving = ref(false);
    const error = ref<string | null>(null);
    const currentQuestionIndex = ref(0);

    // Initialize grades from existing answers
    initialAnswers.forEach(answer => {
        if (answer.score !== null) {
            grades.value[answer.question_id] = {
                score: answer.score,
                feedback: answer.feedback ?? undefined,
            };
        }
    });

    // =============================================================================
    // Computed
    // =============================================================================

    const totalPoints = computed(() =>
        questions.reduce((sum, q) => sum + q.points, 0)
    );

    const earnedPoints = computed(() =>
        Object.values(grades.value).reduce((sum, g) => sum + (g.score ?? 0), 0)
    );

    const scorePercentage = computed(() =>
        totalPoints.value > 0
            ? Math.round((earnedPoints.value / totalPoints.value) * 100)
            : 0
    );

    const isPassing = computed(() =>
        scorePercentage.value >= passingScore
    );

    const gradedCount = computed(() =>
        Object.keys(grades.value).length
    );

    const totalQuestions = computed(() => questions.length);

    const isFullyGraded = computed(() =>
        gradedCount.value === totalQuestions.value
    );

    const ungradedQuestions = computed(() =>
        questions.filter(q => !(q.id in grades.value))
    );

    const currentQuestion = computed(() =>
        questions[currentQuestionIndex.value]
    );

    const currentAnswer = computed(() =>
        initialAnswers.find(a => a.question_id === currentQuestion.value?.id)
    );

    const currentGrade = computed(() =>
        currentQuestion.value ? grades.value[currentQuestion.value.id] : undefined
    );

    // =============================================================================
    // Methods
    // =============================================================================

    /**
     * Set grade for a question
     */
    function setGrade(questionId: number, score: number, feedback?: string): void {
        const question = questions.find(q => q.id === questionId);
        if (!question) return;

        // Clamp score to valid range
        const clampedScore = Math.max(0, Math.min(score, question.points));

        grades.value[questionId] = {
            score: clampedScore,
            feedback,
        };
    }

    /**
     * Set grade for current question
     */
    function setCurrentGrade(score: number, feedback?: string): void {
        if (currentQuestion.value) {
            setGrade(currentQuestion.value.id, score, feedback);
        }
    }

    /**
     * Give full points for a question
     */
    function giveFullPoints(questionId: number): void {
        const question = questions.find(q => q.id === questionId);
        if (question) {
            setGrade(questionId, question.points);
        }
    }

    /**
     * Give zero points for a question
     */
    function giveZeroPoints(questionId: number): void {
        setGrade(questionId, 0);
    }

    /**
     * Remove grade for a question
     */
    function clearGrade(questionId: number): void {
        delete grades.value[questionId];
    }

    /**
     * Navigate to next question
     */
    function nextQuestion(): void {
        if (currentQuestionIndex.value < questions.length - 1) {
            currentQuestionIndex.value++;
        }
    }

    /**
     * Navigate to previous question
     */
    function previousQuestion(): void {
        if (currentQuestionIndex.value > 0) {
            currentQuestionIndex.value--;
        }
    }

    /**
     * Go to specific question
     */
    function goToQuestion(index: number): void {
        if (index >= 0 && index < questions.length) {
            currentQuestionIndex.value = index;
        }
    }

    /**
     * Go to first ungraded question
     */
    function goToFirstUngraded(): void {
        const index = questions.findIndex(q => !(q.id in grades.value));
        if (index !== -1) {
            currentQuestionIndex.value = index;
        }
    }

    /**
     * Save all grades to server
     */
    async function saveGrades(): Promise<boolean> {
        if (!isFullyGraded.value) {
            error.value = 'Semua pertanyaan harus dinilai sebelum menyimpan';
            return false;
        }

        isSaving.value = true;
        error.value = null;

        return new Promise((resolve) => {
            const gradeData = Object.entries(grades.value).map(([questionId, grade]) => ({
                question_id: parseInt(questionId),
                score: grade.score,
                feedback: grade.feedback,
            }));

            router.post(`/attempts/${attemptId}/grade`, {
                grades: gradeData,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    onSave?.();
                    resolve(true);
                },
                onError: (errors) => {
                    error.value = errors.message || 'Gagal menyimpan nilai';
                    resolve(false);
                },
                onFinish: () => {
                    isSaving.value = false;
                },
            });
        });
    }

    /**
     * Auto-grade multiple choice and true/false questions
     */
    function autoGradeObjective(): void {
        questions.forEach(question => {
            // Skip if already graded
            if (question.id in grades.value) return;

            // Only auto-grade objective question types
            if (!['multiple_choice', 'true_false'].includes(question.question_type)) {
                return;
            }

            const answer = initialAnswers.find(a => a.question_id === question.id);
            if (answer && answer.is_correct !== null) {
                setGrade(
                    question.id,
                    answer.is_correct ? question.points : 0
                );
            }
        });
    }

    // =============================================================================
    // Return
    // =============================================================================

    return {
        // State
        grades,
        isSaving,
        error,
        currentQuestionIndex,

        // Computed
        totalPoints,
        earnedPoints,
        scorePercentage,
        isPassing,
        gradedCount,
        totalQuestions,
        isFullyGraded,
        ungradedQuestions,
        currentQuestion,
        currentAnswer,
        currentGrade,

        // Methods
        setGrade,
        setCurrentGrade,
        giveFullPoints,
        giveZeroPoints,
        clearGrade,
        nextQuestion,
        previousQuestion,
        goToQuestion,
        goToFirstUngraded,
        saveGrades,
        autoGradeObjective,
    };
}
