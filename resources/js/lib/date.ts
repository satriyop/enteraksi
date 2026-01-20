/**
 * Date utilities using native JavaScript Intl APIs
 * No external dependencies required
 */

type DateInput = string | Date | null | undefined;

// =============================================================================
// Internal Helpers
// =============================================================================

/**
 * Parse date input to Date object
 */
function toDate(input: DateInput): Date | null {
    if (!input) return null;
    if (input instanceof Date) return input;
    const parsed = new Date(input);
    return isNaN(parsed.getTime()) ? null : parsed;
}

/**
 * Indonesian date formatter
 */
const idFormatter = {
    date: new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
    dateShort: new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }),
    dateTime: new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }),
    time: new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    }),
    weekday: new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
    }),
};

// =============================================================================
// Date Formatters
// =============================================================================

/**
 * Format date to Indonesian locale
 * @param date - Date string (ISO) or Date object
 * @param style - 'long' (1 Januari 2024) | 'short' (1 Jan 2024)
 */
export function formatDate(
    date: DateInput,
    style: 'long' | 'short' = 'long'
): string {
    const parsed = toDate(date);
    if (!parsed) return '-';
    return style === 'short'
        ? idFormatter.dateShort.format(parsed)
        : idFormatter.date.format(parsed);
}

/**
 * Format date with time
 */
export function formatDateTime(date: DateInput): string {
    const parsed = toDate(date);
    if (!parsed) return '-';
    return idFormatter.dateTime.format(parsed);
}

/**
 * Format time only
 */
export function formatTime(date: DateInput): string {
    const parsed = toDate(date);
    if (!parsed) return '-';
    return idFormatter.time.format(parsed);
}

/**
 * Format date as relative time (e.g., "2 hari yang lalu")
 */
export function formatRelativeTime(date: DateInput): string {
    const parsed = toDate(date);
    if (!parsed) return '-';

    const now = new Date();
    const diffMs = now.getTime() - parsed.getTime();
    const diffSecs = Math.floor(diffMs / 1000);
    const diffMins = Math.floor(diffSecs / 60);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    const diffWeeks = Math.floor(diffDays / 7);
    const diffMonths = Math.floor(diffDays / 30);
    const diffYears = Math.floor(diffDays / 365);

    const isFuture = diffMs < 0;
    const abs = Math.abs;

    if (abs(diffSecs) < 60) {
        return isFuture ? 'dalam beberapa detik' : 'baru saja';
    }
    if (abs(diffMins) < 60) {
        const mins = abs(diffMins);
        return isFuture ? `dalam ${mins} menit` : `${mins} menit yang lalu`;
    }
    if (abs(diffHours) < 24) {
        const hours = abs(diffHours);
        return isFuture ? `dalam ${hours} jam` : `${hours} jam yang lalu`;
    }
    if (abs(diffDays) < 7) {
        const days = abs(diffDays);
        return isFuture ? `dalam ${days} hari` : `${days} hari yang lalu`;
    }
    if (abs(diffWeeks) < 4) {
        const weeks = abs(diffWeeks);
        return isFuture ? `dalam ${weeks} minggu` : `${weeks} minggu yang lalu`;
    }
    if (abs(diffMonths) < 12) {
        const months = abs(diffMonths);
        return isFuture ? `dalam ${months} bulan` : `${months} bulan yang lalu`;
    }
    const years = abs(diffYears);
    return isFuture ? `dalam ${years} tahun` : `${years} tahun yang lalu`;
}

/**
 * Format date as smart relative (Hari ini, Kemarin, or full date)
 */
export function formatSmartDate(date: DateInput): string {
    const parsed = toDate(date);
    if (!parsed) return '-';

    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const dateOnly = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
    const diffDays = Math.floor((today.getTime() - dateOnly.getTime()) / (1000 * 60 * 60 * 24));

    const time = idFormatter.time.format(parsed);

    if (diffDays === 0) {
        return `Hari ini, ${time}`;
    }
    if (diffDays === 1) {
        return `Kemarin, ${time}`;
    }
    if (diffDays === -1) {
        return `Besok, ${time}`;
    }
    if (diffDays > 0 && diffDays <= 7) {
        return `${diffDays} hari yang lalu`;
    }
    if (diffDays < 0 && diffDays >= -7) {
        return `dalam ${Math.abs(diffDays)} hari`;
    }

    return formatDate(parsed);
}

/**
 * Format date range
 */
export function formatDateRange(start: DateInput, end: DateInput): string {
    const startDate = toDate(start);
    const endDate = toDate(end);

    if (!startDate && !endDate) return '-';
    if (!endDate) return `Mulai ${formatDate(startDate, 'short')}`;
    if (!startDate) return `Sampai ${formatDate(endDate, 'short')}`;

    const startFormatted = formatDate(startDate, 'short');
    const endFormatted = formatDate(endDate, 'short');

    return `${startFormatted} - ${endFormatted}`;
}

// =============================================================================
// Date Checkers
// =============================================================================

/**
 * Check if date is today
 */
export function isToday(date: DateInput): boolean {
    const parsed = toDate(date);
    if (!parsed) return false;
    const now = new Date();
    return (
        parsed.getDate() === now.getDate() &&
        parsed.getMonth() === now.getMonth() &&
        parsed.getFullYear() === now.getFullYear()
    );
}

/**
 * Check if date is in the past
 */
export function isPast(date: DateInput): boolean {
    const parsed = toDate(date);
    if (!parsed) return false;
    return parsed < new Date();
}

/**
 * Check if date is in the future
 */
export function isFuture(date: DateInput): boolean {
    const parsed = toDate(date);
    if (!parsed) return false;
    return parsed > new Date();
}

// =============================================================================
// Time Remaining
// =============================================================================

/**
 * Get time remaining until date
 */
export function getTimeRemaining(date: DateInput): {
    days: number;
    hours: number;
    minutes: number;
    seconds: number;
    isExpired: boolean;
} {
    const parsed = toDate(date);
    if (!parsed) {
        return { days: 0, hours: 0, minutes: 0, seconds: 0, isExpired: true };
    }

    const now = new Date();
    const diffMs = parsed.getTime() - now.getTime();

    if (diffMs <= 0) {
        return { days: 0, hours: 0, minutes: 0, seconds: 0, isExpired: true };
    }

    const diffSecs = Math.floor(diffMs / 1000);
    const days = Math.floor(diffSecs / (60 * 60 * 24));
    const hours = Math.floor((diffSecs % (60 * 60 * 24)) / (60 * 60));
    const minutes = Math.floor((diffSecs % (60 * 60)) / 60);
    const seconds = diffSecs % 60;

    return { days, hours, minutes, seconds, isExpired: false };
}

/**
 * Format deadline with urgency indicator
 */
export function formatDeadline(date: DateInput): {
    text: string;
    urgency: 'expired' | 'urgent' | 'soon' | 'normal';
} {
    const remaining = getTimeRemaining(date);

    if (remaining.isExpired) {
        return { text: 'Telah berakhir', urgency: 'expired' };
    }

    if (remaining.days === 0 && remaining.hours < 1) {
        return { text: `${remaining.minutes} menit lagi`, urgency: 'urgent' };
    }

    if (remaining.days === 0) {
        return { text: `${remaining.hours} jam lagi`, urgency: 'urgent' };
    }

    if (remaining.days <= 3) {
        return { text: `${remaining.days} hari lagi`, urgency: 'soon' };
    }

    return { text: formatDate(date, 'short'), urgency: 'normal' };
}

// =============================================================================
// Date Manipulation
// =============================================================================

/**
 * Get start of day
 */
export function startOfDay(date: DateInput): Date | null {
    const parsed = toDate(date);
    if (!parsed) return null;
    return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
}

/**
 * Get end of day
 */
export function endOfDay(date: DateInput): Date | null {
    const parsed = toDate(date);
    if (!parsed) return null;
    return new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate(), 23, 59, 59, 999);
}

/**
 * Add days to date
 */
export function addDays(date: DateInput, days: number): Date | null {
    const parsed = toDate(date);
    if (!parsed) return null;
    const result = new Date(parsed);
    result.setDate(result.getDate() + days);
    return result;
}
