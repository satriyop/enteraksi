// =============================================================================
// Assessment Attempt Store (Provide/Inject Pattern)
// State management for taking an assessment
// =============================================================================

import {
    provide,
    inject,
    ref,
    computed,
    readonly,
    type InjectionKey,
    type Ref,
    type ComputedRef,
} from 'vue';
import { router } from '@inertiajs/vue3';
import type {
    Assessment,
    AssessmentAttempt,
    QuestionForAttempt,
    SubmitAnswerData,
} from '@/types';

// =============================================================================
// Types
// =============================================================================

type AnswerValue = string | string[] | Record<string, string> | null;

interface AnswerState {
    questionId: number;
    answer: AnswerValue;
    savedAt: Date | null;
    isDirty: boolean;
}

interface AssessmentAttemptState {
    /** Assessment being attempted */
    assessment: Ref<Assessment>;
    /** Current attempt */
    attempt: Ref<AssessmentAttempt>;
    /** Questions to answer */
    questions: Ref<QuestionForAttempt[]>;
    /** Current answers by question ID */
    answers: Ref<Map<number, AnswerState>>;
    /** Current question index */
    currentQuestionIndex: Ref<number>;
    /** Time remaining in seconds (null if no time limit) */
    timeRemaining: Ref<number | null>;
    /** Whether submission is in progress */
    isSubmitting: Ref<boolean>;
    /** Error message if any */
    error: Ref<string | null>;
    /** Whether time has expired */
    isExpired: Ref<boolean>;
}

interface AssessmentAttemptComputed {
    /** Current question */
    currentQuestion: ComputedRef<QuestionForAttempt | null>;
    /** Whether on first question */
    isFirstQuestion: ComputedRef<boolean>;
    /** Whether on last question */
    isLastQuestion: ComputedRef<boolean>;
    /** Number of answered questions */
    answeredCount: ComputedRef<number>;
    /** Total questions */
    totalQuestions: ComputedRef<number>;
    /** Progress percentage */
    progressPercentage: ComputedRef<number>;
    /** Whether all questions are answered */
    isComplete: ComputedRef<boolean>;
    /** Unanswered question indices */
    unansweredIndices: ComputedRef<number[]>;
    /** Current answer */
    currentAnswer: ComputedRef<AnswerValue>;
    /** Formatted time remaining (MM:SS) */
    formattedTimeRemaining: ComputedRef<string | null>;
    /** Whether time is running low (< 5 minutes) */
    isTimeLow: ComputedRef<boolean>;
}

interface AssessmentAttemptActions {
    /** Set answer for a question */
    setAnswer: (questionId: number, answer: AnswerValue) => void;
    /** Set answer for current question */
    setCurrentAnswer: (answer: AnswerValue) => void;
    /** Go to next question */
    nextQuestion: () => void;
    /** Go to previous question */
    previousQuestion: () => void;
    /** Go to specific question by index */
    goToQuestion: (index: number) => void;
    /** Go to first unanswered question */
    goToFirstUnanswered: () => void;
    /** Save current answer to server */
    saveAnswer: (questionId: number) => Promise<void>;
    /** Submit the entire attempt */
    submitAttempt: () => Promise<boolean>;
    /** Update time remaining */
    setTimeRemaining: (seconds: number) => void;
    /** Mark as expired */
    markExpired: () => void;
    /** Clear error */
    clearError: () => void;
    /** Get answer for a question */
    getAnswer: (questionId: number) => AnswerValue;
    /** Check if question is answered */
    isQuestionAnswered: (questionId: number) => boolean;
}

type AssessmentAttemptContext = AssessmentAttemptState & AssessmentAttemptComputed & AssessmentAttemptActions;

// =============================================================================
// Injection Key
// =============================================================================

const AssessmentAttemptKey: InjectionKey<AssessmentAttemptContext> = Symbol('AssessmentAttempt');

// =============================================================================
// Provider
// =============================================================================

export function provideAssessmentAttempt(
    initialAssessment: Assessment,
    initialAttempt: AssessmentAttempt,
    initialQuestions: QuestionForAttempt[],
    existingAnswers?: Record<number, AnswerValue>
): AssessmentAttemptContext {
    // =============================================================================
    // State
    // =============================================================================

    const assessment = ref<Assessment>({ ...initialAssessment });
    const attempt = ref<AssessmentAttempt>({ ...initialAttempt });
    const questions = ref<QuestionForAttempt[]>([...initialQuestions]);

    // Initialize answers map
    const answersMap = new Map<number, AnswerState>();
    for (const question of initialQuestions) {
        answersMap.set(question.id, {
            questionId: question.id,
            answer: existingAnswers?.[question.id] ?? null,
            savedAt: existingAnswers?.[question.id] ? new Date() : null,
            isDirty: false,
        });
    }
    const answers = ref(answersMap);

    const currentQuestionIndex = ref(0);
    const timeRemaining = ref<number | null>(
        initialAssessment.time_limit_minutes
            ? initialAssessment.time_limit_minutes * 60
            : null
    );
    const isSubmitting = ref(false);
    const error = ref<string | null>(null);
    const isExpired = ref(false);

    // =============================================================================
    // Computed
    // =============================================================================

    const currentQuestion = computed<QuestionForAttempt | null>(() =>
        questions.value[currentQuestionIndex.value] ?? null
    );

    const isFirstQuestion = computed(() =>
        currentQuestionIndex.value === 0
    );

    const isLastQuestion = computed(() =>
        currentQuestionIndex.value === questions.value.length - 1
    );

    const answeredCount = computed(() => {
        let count = 0;
        for (const [, state] of answers.value) {
            if (state.answer !== null && state.answer !== '') {
                count++;
            }
        }
        return count;
    });

    const totalQuestions = computed(() =>
        questions.value.length
    );

    const progressPercentage = computed(() =>
        totalQuestions.value > 0
            ? Math.round((answeredCount.value / totalQuestions.value) * 100)
            : 0
    );

    const isComplete = computed(() =>
        answeredCount.value === totalQuestions.value
    );

    const unansweredIndices = computed(() => {
        const indices: number[] = [];
        questions.value.forEach((q, index) => {
            const state = answers.value.get(q.id);
            if (!state?.answer || state.answer === '') {
                indices.push(index);
            }
        });
        return indices;
    });

    const currentAnswer = computed(() =>
        currentQuestion.value
            ? answers.value.get(currentQuestion.value.id)?.answer ?? null
            : null
    );

    const formattedTimeRemaining = computed<string | null>(() => {
        if (timeRemaining.value === null) return null;

        const minutes = Math.floor(timeRemaining.value / 60);
        const seconds = timeRemaining.value % 60;
        return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    });

    const isTimeLow = computed(() =>
        timeRemaining.value !== null && timeRemaining.value < 300 // 5 minutes
    );

    // =============================================================================
    // Actions
    // =============================================================================

    function setAnswer(questionId: number, answer: AnswerValue): void {
        const existing = answers.value.get(questionId);
        if (existing) {
            existing.answer = answer;
            existing.isDirty = true;
            // Trigger reactivity
            answers.value = new Map(answers.value);
        }
    }

    function setCurrentAnswer(answer: AnswerValue): void {
        if (currentQuestion.value) {
            setAnswer(currentQuestion.value.id, answer);
        }
    }

    function nextQuestion(): void {
        if (!isLastQuestion.value) {
            currentQuestionIndex.value++;
        }
    }

    function previousQuestion(): void {
        if (!isFirstQuestion.value) {
            currentQuestionIndex.value--;
        }
    }

    function goToQuestion(index: number): void {
        if (index >= 0 && index < questions.value.length) {
            currentQuestionIndex.value = index;
        }
    }

    function goToFirstUnanswered(): void {
        if (unansweredIndices.value.length > 0) {
            currentQuestionIndex.value = unansweredIndices.value[0];
        }
    }

    async function saveAnswer(questionId: number): Promise<void> {
        const state = answers.value.get(questionId);
        if (!state || !state.isDirty) return;

        try {
            await router.post(
                `/attempts/${attempt.value.id}/save-answer`,
                {
                    question_id: questionId,
                    answer: state.answer,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                }
            );

            state.savedAt = new Date();
            state.isDirty = false;
            answers.value = new Map(answers.value);
        } catch (err) {
            console.error('Failed to save answer:', err);
        }
    }

    async function submitAttempt(): Promise<boolean> {
        if (isSubmitting.value) return false;

        isSubmitting.value = true;
        error.value = null;

        // Prepare answers data
        const answersData: SubmitAnswerData[] = [];
        for (const [questionId, state] of answers.value) {
            answersData.push({
                question_id: questionId,
                answer: state.answer,
            });
        }

        return new Promise((resolve) => {
            router.post(
                `/attempts/${attempt.value.id}/submit`,
                { answers: answersData },
                {
                    preserveState: false,
                    onSuccess: () => {
                        isSubmitting.value = false;
                        resolve(true);
                    },
                    onError: (errors) => {
                        error.value = typeof errors === 'object'
                            ? Object.values(errors)[0] as string
                            : 'Gagal mengirim jawaban';
                        isSubmitting.value = false;
                        resolve(false);
                    },
                }
            );
        });
    }

    function setTimeRemaining(seconds: number): void {
        timeRemaining.value = Math.max(0, seconds);
        if (timeRemaining.value === 0) {
            markExpired();
        }
    }

    function markExpired(): void {
        isExpired.value = true;
    }

    function clearError(): void {
        error.value = null;
    }

    function getAnswer(questionId: number): AnswerValue {
        return answers.value.get(questionId)?.answer ?? null;
    }

    function isQuestionAnswered(questionId: number): boolean {
        const state = answers.value.get(questionId);
        return state?.answer !== null && state?.answer !== '';
    }

    // =============================================================================
    // Context
    // =============================================================================

    const context: AssessmentAttemptContext = {
        // State
        assessment,
        attempt,
        questions,
        answers,
        currentQuestionIndex,
        timeRemaining,
        isSubmitting: readonly(isSubmitting),
        error: readonly(error),
        isExpired: readonly(isExpired),

        // Computed
        currentQuestion,
        isFirstQuestion,
        isLastQuestion,
        answeredCount,
        totalQuestions,
        progressPercentage,
        isComplete,
        unansweredIndices,
        currentAnswer,
        formattedTimeRemaining,
        isTimeLow,

        // Actions
        setAnswer,
        setCurrentAnswer,
        nextQuestion,
        previousQuestion,
        goToQuestion,
        goToFirstUnanswered,
        saveAnswer,
        submitAttempt,
        setTimeRemaining,
        markExpired,
        clearError,
        getAnswer,
        isQuestionAnswered,
    };

    provide(AssessmentAttemptKey, context);

    return context;
}

// =============================================================================
// Consumer Hook
// =============================================================================

export function useAssessmentAttempt(): AssessmentAttemptContext {
    const context = inject(AssessmentAttemptKey);

    if (!context) {
        throw new Error(
            'useAssessmentAttempt must be used within a component that calls provideAssessmentAttempt'
        );
    }

    return context;
}

// =============================================================================
// Optional: Check if context exists (non-throwing version)
// =============================================================================

export function useAssessmentAttemptOptional(): AssessmentAttemptContext | null {
    return inject(AssessmentAttemptKey) ?? null;
}
