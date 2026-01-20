// =============================================================================
// useAssessmentTimer Composable
// Manages timer state for assessment attempts
// =============================================================================

import { ref, onUnmounted } from 'vue';

// =============================================================================
// Types
// =============================================================================

interface UseAssessmentTimerOptions {
    /** When the attempt started */
    startedAt: string;
    /** Time limit in minutes (null for no limit) */
    timeLimitMinutes: number | null;
    /** Callback when time runs out */
    onTimeUp?: () => void;
}

interface AssessmentTimerReturn {
    /** Formatted elapsed time (HH:MM:SS) */
    timeElapsed: Readonly<typeof timeElapsed>;
    /** Formatted remaining time (HH:MM:SS), empty if no limit */
    timeLeft: Readonly<typeof timeLeft>;
    /** Whether time has run out */
    isTimeUp: Readonly<typeof isTimeUp>;
    /** Start the timer */
    start: () => void;
    /** Stop the timer */
    stop: () => void;
}

// =============================================================================
// Composable
// =============================================================================

export function useAssessmentTimer(options: UseAssessmentTimerOptions): AssessmentTimerReturn {
    const { startedAt, timeLimitMinutes, onTimeUp } = options;

    // State
    const timeElapsed = ref('00:00:00');
    const timeLeft = ref('');
    const isTimeUp = ref(false);

    let intervalId: ReturnType<typeof setInterval> | null = null;

    /**
     * Format seconds to HH:MM:SS
     */
    const formatTime = (totalSeconds: number): string => {
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    };

    /**
     * Calculate and update time values
     */
    const calculateTime = () => {
        const startTime = new Date(startedAt);
        const now = new Date();
        const elapsedSeconds = Math.floor((now.getTime() - startTime.getTime()) / 1000);

        // Update elapsed time
        timeElapsed.value = formatTime(elapsedSeconds);

        // Update remaining time if there's a limit
        if (timeLimitMinutes) {
            const totalLimitSeconds = timeLimitMinutes * 60;
            const remainingSeconds = Math.max(0, totalLimitSeconds - elapsedSeconds);

            timeLeft.value = formatTime(remainingSeconds);

            // Check if time is up
            if (remainingSeconds === 0 && !isTimeUp.value) {
                isTimeUp.value = true;
                onTimeUp?.();
            }
        }
    };

    /**
     * Start the timer
     */
    const start = () => {
        if (intervalId) return;

        // Calculate immediately
        calculateTime();

        // Update every second
        intervalId = setInterval(calculateTime, 1000);
    };

    /**
     * Stop the timer
     */
    const stop = () => {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    };

    // Auto-start on creation
    start();

    // Clean up on unmount
    onUnmounted(() => {
        stop();
    });

    return {
        timeElapsed,
        timeLeft,
        isTimeUp,
        start,
        stop,
    };
}
