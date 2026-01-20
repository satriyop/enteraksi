import type {
    ContentType,
    DifficultyLevel,
    CourseStatus,
    EnrollmentStatus,
    AssessmentStatus,
    AttemptStatus,
    CourseVisibility,
} from '@/types';

// =============================================================================
// Duration Formatters
// =============================================================================

/**
 * Format duration from minutes to human-readable string
 * @param minutes - Duration in minutes
 * @param format - 'short' (1j 30m) | 'long' (1 jam 30 menit) | 'compact' (1.5j)
 */
export function formatDuration(
    minutes: number | null | undefined,
    format: 'short' | 'long' | 'compact' = 'short'
): string {
    if (minutes === null || minutes === undefined || minutes === 0) {
        return '-';
    }

    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    switch (format) {
        case 'long':
            if (hours === 0) return `${mins} menit`;
            if (mins === 0) return `${hours} jam`;
            return `${hours} jam ${mins} menit`;

        case 'compact':
            if (hours === 0) return `${mins}m`;
            return `${(minutes / 60).toFixed(1)}j`;

        case 'short':
        default:
            if (hours === 0) return `${mins}m`;
            if (mins === 0) return `${hours}j`;
            return `${hours}j ${mins}m`;
    }
}

/**
 * Format duration from seconds to mm:ss or hh:mm:ss format
 * Used for video/audio playback display
 */
export function formatPlaybackTime(seconds: number | null | undefined): string {
    if (seconds === null || seconds === undefined || seconds === 0) {
        return '0:00';
    }

    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);

    if (hrs > 0) {
        return `${hrs}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// =============================================================================
// Currency Formatters
// =============================================================================

/**
 * Format currency in Indonesian Rupiah
 */
export function formatCurrency(
    amount: number | null | undefined,
    options: {
        showFree?: boolean;
        compact?: boolean;
    } = {}
): string {
    const { showFree = true, compact = false } = options;

    if (amount === null || amount === undefined) {
        return '-';
    }

    if (amount === 0 && showFree) {
        return 'Gratis';
    }

    if (compact && amount >= 1_000_000) {
        return `Rp ${(amount / 1_000_000).toFixed(1)}jt`;
    }

    if (compact && amount >= 1_000) {
        return `Rp ${(amount / 1_000).toFixed(0)}rb`;
    }

    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

// =============================================================================
// File Size Formatter
// =============================================================================

/**
 * Format file size in human-readable format
 */
export function formatFileSize(bytes: number | null | undefined): string {
    if (bytes === null || bytes === undefined || bytes === 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let unitIndex = 0;
    let size = bytes;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }

    return `${size.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

// =============================================================================
// Number Formatters
// =============================================================================

/**
 * Format percentage with optional decimal places
 */
export function formatPercentage(
    value: number | null | undefined,
    decimals: number = 0
): string {
    if (value === null || value === undefined) {
        return '0%';
    }
    return `${value.toFixed(decimals)}%`;
}

/**
 * Format number with thousand separators
 */
export function formatNumber(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '0';
    }
    return new Intl.NumberFormat('id-ID').format(value);
}

/**
 * Pluralize Indonesian word (simple implementation)
 * Indonesian doesn't have plural forms, but we format count nicely
 */
export function pluralize(count: number, singular: string, plural?: string): string {
    return `${formatNumber(count)} ${plural || singular}`;
}

// =============================================================================
// Label Formatters
// =============================================================================

/**
 * Get human-readable label for difficulty level
 */
export function difficultyLabel(level: DifficultyLevel | string | null | undefined): string {
    const labels: Record<string, string> = {
        beginner: 'Pemula',
        intermediate: 'Menengah',
        advanced: 'Lanjutan',
        expert: 'Ahli',
    };
    return level ? labels[level] ?? level : '-';
}

/**
 * Get Tailwind CSS color classes for difficulty level badge
 */
export function difficultyColor(level: DifficultyLevel | string | null | undefined): string {
    const colors: Record<string, string> = {
        beginner: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        intermediate: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        advanced: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        expert: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    };
    return level ? colors[level] ?? '' : '';
}

/**
 * Get human-readable label for course status
 */
export function courseStatusLabel(status: CourseStatus | string | null | undefined): string {
    const labels: Record<string, string> = {
        draft: 'Draf',
        published: 'Dipublikasikan',
        archived: 'Diarsipkan',
    };
    return status ? labels[status] ?? status : '-';
}

/**
 * Get human-readable label for course visibility
 */
export function visibilityLabel(visibility: CourseVisibility | string | null | undefined): string {
    const labels: Record<string, string> = {
        public: 'Publik',
        restricted: 'Terbatas',
        hidden: 'Tersembunyi',
    };
    return visibility ? labels[visibility] ?? visibility : '-';
}

/**
 * Get human-readable label for enrollment status
 */
export function enrollmentStatusLabel(status: EnrollmentStatus | string | null | undefined): string {
    const labels: Record<string, string> = {
        pending: 'Menunggu',
        active: 'Aktif',
        completed: 'Selesai',
        suspended: 'Ditangguhkan',
        cancelled: 'Dibatalkan',
    };
    return status ? labels[status] ?? status : '-';
}

/**
 * Get human-readable label for assessment status
 */
export function assessmentStatusLabel(status: AssessmentStatus | string | null | undefined): string {
    const labels: Record<string, string> = {
        draft: 'Draf',
        published: 'Dipublikasikan',
        archived: 'Diarsipkan',
    };
    return status ? labels[status] ?? status : '-';
}

/**
 * Get human-readable label for attempt status
 */
export function attemptStatusLabel(status: AttemptStatus | string | null | undefined): string {
    const labels: Record<string, string> = {
        in_progress: 'Sedang Dikerjakan',
        submitted: 'Telah Dikumpulkan',
        graded: 'Telah Dinilai',
        expired: 'Kadaluarsa',
    };
    return status ? labels[status] ?? status : '-';
}

/**
 * Get human-readable label for content type
 */
export function contentTypeLabel(type: ContentType | string | null | undefined): string {
    const labels: Record<string, string> = {
        text: 'Teks',
        video: 'Video',
        youtube: 'YouTube',
        audio: 'Audio',
        document: 'Dokumen',
        conference: 'Konferensi',
    };
    return type ? labels[type] ?? type : '-';
}

/**
 * Get human-readable label for question type
 */
export function questionTypeLabel(type: string | null | undefined): string {
    const labels: Record<string, string> = {
        multiple_choice: 'Pilihan Ganda',
        true_false: 'Benar/Salah',
        matching: 'Pencocokan',
        short_answer: 'Jawaban Singkat',
        essay: 'Esai',
        file_upload: 'Unggah Berkas',
    };
    return type ? labels[type] ?? type : '-';
}

// =============================================================================
// Badge Color Functions
// =============================================================================

/**
 * Get Tailwind CSS color classes for assessment/course status badge
 */
export function statusBadgeColor(status: string | null | undefined): string {
    const colors: Record<string, string> = {
        draft: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        archived: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    };
    return status ? colors[status] ?? 'bg-gray-100 text-gray-800' : '';
}

/**
 * Get Tailwind CSS color classes for visibility badge
 */
export function visibilityBadgeColor(visibility: string | null | undefined): string {
    const colors: Record<string, string> = {
        public: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        restricted: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
        hidden: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    };
    return visibility ? colors[visibility] ?? 'bg-gray-100 text-gray-800' : '';
}

/**
 * Get Tailwind CSS color classes for attempt status badge
 */
export function attemptStatusBadgeColor(status: string | null | undefined): string {
    const colors: Record<string, string> = {
        in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        submitted: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        graded: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        completed: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
        expired: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    };
    return status ? colors[status] ?? 'bg-gray-100 text-gray-800' : '';
}
