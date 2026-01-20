# Phase 2: Utility Extraction

## Overview

This phase addresses the critical issue of utility function duplication across the codebase. Functions like `formatDuration()`, `difficultyLabel()`, and `contentTypeIcon()` are duplicated in 6-10+ files, leading to:
- Inconsistent implementations
- Bug propagation when fixing one instance
- Larger bundle size from duplicate code
- No single source of truth

**Duration:** 1 week
**Risk Level:** Low
**Dependencies:** Phase 1 (Type System) recommended but not required

---

## Current State Analysis

### Duplicated Functions Found

| Function | Occurrences | Files |
|----------|-------------|-------|
| `formatDuration()` | 8+ | Show.vue, Detail.vue, Index.vue, etc. |
| `difficultyLabel()` | 6+ | CourseCard.vue, Detail.vue, etc. |
| `contentTypeIcon()` | 7+ | LessonList.vue, Show.vue, etc. |
| `statusColor()` | 5+ | Multiple course/enrollment pages |
| `formatDate()` | 10+ | Almost every page |
| `formatCurrency()` | 4+ | Course detail, checkout |
| `truncate()` | 6+ | Card components |

### Example Duplication

**formatDuration() - Different implementations:**
```typescript
// File 1: pages/courses/Detail.vue
function formatDuration(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
}

// File 2: pages/lessons/Show.vue
function formatDuration(seconds: number): string {  // Different input!
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// File 3: components/CourseCard.vue
const formatDuration = (mins) => {  // No type safety!
    if (mins >= 60) return `${(mins/60).toFixed(1)} hours`;
    return `${mins} minutes`;
}
```

### Current `/lib/utils.ts` (Only 19 lines)
```typescript
import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(urlToCheck, currentUrl) {
    return toUrl(urlToCheck) === currentUrl;
}

export function toUrl(href) {
    return typeof href === 'string' ? href : href?.url;
}
```

---

## Target Architecture

### Directory Structure
```
resources/js/lib/
├── utils.ts              # General utilities (cn, urlIsActive, etc.)
├── formatters.ts         # All formatting functions
├── validators.ts         # Form validation helpers
├── constants.ts          # Application constants
├── date.ts               # Date/time utilities
├── string.ts             # String manipulation
├── number.ts             # Number utilities
└── icons.ts              # Icon mapping utilities
```

---

## Implementation Steps

### Step 1: Create Formatting Utilities

**File: `resources/js/lib/formatters.ts`**
```typescript
import type { ContentType, DifficultyLevel, CourseStatus, EnrollmentStatus } from '@/types';

/**
 * Format duration from minutes to human-readable string
 * @param minutes - Duration in minutes
 * @param format - 'short' (1h 30m) | 'long' (1 hour 30 minutes) | 'compact' (1.5h)
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
            return `${(minutes / 60).toFixed(1)}h`;

        case 'short':
        default:
            if (hours === 0) return `${mins}m`;
            if (mins === 0) return `${hours}h`;
            return `${hours}h ${mins}m`;
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
 * Get human-readable label for difficulty level
 */
export function difficultyLabel(level: DifficultyLevel | null | undefined): string {
    const labels: Record<DifficultyLevel, string> = {
        beginner: 'Pemula',
        intermediate: 'Menengah',
        advanced: 'Lanjutan',
    };
    return level ? labels[level] : '-';
}

/**
 * Get human-readable label for course status
 */
export function courseStatusLabel(status: CourseStatus | null | undefined): string {
    const labels: Record<CourseStatus, string> = {
        draft: 'Draf',
        published: 'Dipublikasikan',
        archived: 'Diarsipkan',
    };
    return status ? labels[status] : '-';
}

/**
 * Get human-readable label for enrollment status
 */
export function enrollmentStatusLabel(status: EnrollmentStatus | null | undefined): string {
    const labels: Record<EnrollmentStatus, string> = {
        pending: 'Menunggu',
        active: 'Aktif',
        completed: 'Selesai',
        suspended: 'Ditangguhkan',
        cancelled: 'Dibatalkan',
    };
    return status ? labels[status] : '-';
}

/**
 * Get human-readable label for content type
 */
export function contentTypeLabel(type: ContentType | null | undefined): string {
    const labels: Record<ContentType, string> = {
        text: 'Teks',
        video: 'Video',
        youtube: 'YouTube',
        audio: 'Audio',
        document: 'Dokumen',
        conference: 'Konferensi',
    };
    return type ? labels[type] : '-';
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
 */
export function pluralize(count: number, singular: string, plural?: string): string {
    // Indonesian doesn't really have plural forms, but we can handle common cases
    return `${formatNumber(count)} ${plural || singular}`;
}
```

### Step 2: Create Date Utilities

**File: `resources/js/lib/date.ts`**
```typescript
import {
    format,
    formatDistance,
    formatRelative,
    isToday,
    isYesterday,
    isTomorrow,
    parseISO,
    differenceInDays,
    differenceInHours,
    differenceInMinutes,
} from 'date-fns';
import { id } from 'date-fns/locale';

type DateInput = string | Date | null | undefined;

/**
 * Parse date input to Date object
 */
function toDate(input: DateInput): Date | null {
    if (!input) return null;
    if (input instanceof Date) return input;
    return parseISO(input);
}

/**
 * Format date to Indonesian locale
 * @param date - Date string (ISO) or Date object
 * @param formatStr - date-fns format string
 */
export function formatDate(
    date: DateInput,
    formatStr: string = 'd MMMM yyyy'
): string {
    const parsed = toDate(date);
    if (!parsed) return '-';
    return format(parsed, formatStr, { locale: id });
}

/**
 * Format date with time
 */
export function formatDateTime(date: DateInput): string {
    return formatDate(date, 'd MMMM yyyy, HH:mm');
}

/**
 * Format time only
 */
export function formatTime(date: DateInput): string {
    return formatDate(date, 'HH:mm');
}

/**
 * Format date as relative time (e.g., "2 hari yang lalu")
 */
export function formatRelativeTime(date: DateInput): string {
    const parsed = toDate(date);
    if (!parsed) return '-';
    return formatDistance(parsed, new Date(), { addSuffix: true, locale: id });
}

/**
 * Format date as smart relative (Today, Yesterday, or full date)
 */
export function formatSmartDate(date: DateInput): string {
    const parsed = toDate(date);
    if (!parsed) return '-';

    if (isToday(parsed)) {
        return `Hari ini, ${format(parsed, 'HH:mm')}`;
    }
    if (isYesterday(parsed)) {
        return `Kemarin, ${format(parsed, 'HH:mm')}`;
    }
    if (isTomorrow(parsed)) {
        return `Besok, ${format(parsed, 'HH:mm')}`;
    }

    const daysDiff = differenceInDays(new Date(), parsed);
    if (daysDiff <= 7 && daysDiff > 0) {
        return formatRelative(parsed, new Date(), { locale: id });
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
    if (!endDate) return `Mulai ${formatDate(startDate)}`;
    if (!startDate) return `Sampai ${formatDate(endDate)}`;

    const startFormatted = formatDate(startDate, 'd MMM yyyy');
    const endFormatted = formatDate(endDate, 'd MMM yyyy');

    return `${startFormatted} - ${endFormatted}`;
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

/**
 * Get time remaining until date
 */
export function getTimeRemaining(date: DateInput): {
    days: number;
    hours: number;
    minutes: number;
    isExpired: boolean;
} {
    const parsed = toDate(date);
    if (!parsed) {
        return { days: 0, hours: 0, minutes: 0, isExpired: true };
    }

    const now = new Date();
    if (parsed <= now) {
        return { days: 0, hours: 0, minutes: 0, isExpired: true };
    }

    return {
        days: differenceInDays(parsed, now),
        hours: differenceInHours(parsed, now) % 24,
        minutes: differenceInMinutes(parsed, now) % 60,
        isExpired: false,
    };
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

    return { text: formatDate(date), urgency: 'normal' };
}
```

### Step 3: Create String Utilities

**File: `resources/js/lib/string.ts`**
```typescript
/**
 * Truncate text to specified length with ellipsis
 */
export function truncate(
    text: string | null | undefined,
    length: number,
    suffix: string = '...'
): string {
    if (!text) return '';
    if (text.length <= length) return text;
    return text.slice(0, length - suffix.length).trim() + suffix;
}

/**
 * Truncate text by word count
 */
export function truncateWords(
    text: string | null | undefined,
    wordCount: number,
    suffix: string = '...'
): string {
    if (!text) return '';
    const words = text.split(/\s+/);
    if (words.length <= wordCount) return text;
    return words.slice(0, wordCount).join(' ') + suffix;
}

/**
 * Convert string to slug format
 */
export function slugify(text: string): string {
    return text
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/**
 * Convert string to title case
 */
export function titleCase(text: string | null | undefined): string {
    if (!text) return '';
    return text
        .toLowerCase()
        .split(' ')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/**
 * Get initials from name
 */
export function getInitials(name: string | null | undefined, maxLength: number = 2): string {
    if (!name) return '';
    return name
        .split(' ')
        .map(word => word.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, maxLength);
}

/**
 * Strip HTML tags from string
 */
export function stripHtml(html: string | null | undefined): string {
    if (!html) return '';
    return html.replace(/<[^>]*>/g, '');
}

/**
 * Highlight search term in text
 */
export function highlightText(
    text: string,
    searchTerm: string,
    highlightClass: string = 'bg-yellow-200'
): string {
    if (!searchTerm) return text;

    const regex = new RegExp(`(${escapeRegex(searchTerm)})`, 'gi');
    return text.replace(regex, `<mark class="${highlightClass}">$1</mark>`);
}

/**
 * Escape special regex characters
 */
export function escapeRegex(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Generate random string
 */
export function randomString(length: number = 8): string {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    return Array.from({ length }, () =>
        chars.charAt(Math.floor(Math.random() * chars.length))
    ).join('');
}

/**
 * Check if string is valid email
 */
export function isValidEmail(email: string): boolean {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Check if string is valid URL
 */
export function isValidUrl(url: string): boolean {
    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
}
```

### Step 4: Create Constants File

**File: `resources/js/lib/constants.ts`**
```typescript
import type { ContentType, DifficultyLevel, CourseStatus, EnrollmentStatus } from '@/types';

/**
 * Application-wide constants
 */

// Pagination defaults
export const DEFAULT_PAGE_SIZE = 10;
export const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

// File upload limits
export const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB
export const MAX_VIDEO_SIZE = 500 * 1024 * 1024; // 500MB
export const MAX_DOCUMENT_SIZE = 20 * 1024 * 1024; // 20MB

// Allowed file types
export const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
export const ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/ogg'];
export const ALLOWED_DOCUMENT_TYPES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];

// Status colors for badges/chips
export const COURSE_STATUS_COLORS: Record<CourseStatus, { bg: string; text: string }> = {
    draft: { bg: 'bg-gray-100', text: 'text-gray-700' },
    published: { bg: 'bg-green-100', text: 'text-green-700' },
    archived: { bg: 'bg-red-100', text: 'text-red-700' },
};

export const ENROLLMENT_STATUS_COLORS: Record<EnrollmentStatus, { bg: string; text: string }> = {
    pending: { bg: 'bg-yellow-100', text: 'text-yellow-700' },
    active: { bg: 'bg-blue-100', text: 'text-blue-700' },
    completed: { bg: 'bg-green-100', text: 'text-green-700' },
    suspended: { bg: 'bg-orange-100', text: 'text-orange-700' },
    cancelled: { bg: 'bg-red-100', text: 'text-red-700' },
};

export const DIFFICULTY_COLORS: Record<DifficultyLevel, { bg: string; text: string }> = {
    beginner: { bg: 'bg-green-100', text: 'text-green-700' },
    intermediate: { bg: 'bg-yellow-100', text: 'text-yellow-700' },
    advanced: { bg: 'bg-red-100', text: 'text-red-700' },
};

// Content type icons (Lucide icon names)
export const CONTENT_TYPE_ICONS: Record<ContentType, string> = {
    text: 'FileText',
    video: 'Video',
    youtube: 'Youtube',
    audio: 'Music',
    document: 'File',
    conference: 'Video',
};

// API endpoints (for reference, actual calls use Wayfinder)
export const API_ENDPOINTS = {
    courses: '/api/courses',
    lessons: '/api/lessons',
    assessments: '/api/assessments',
    enrollments: '/api/enrollments',
    users: '/api/users',
} as const;

// Local storage keys
export const STORAGE_KEYS = {
    theme: 'enteraksi-theme',
    sidebarCollapsed: 'enteraksi-sidebar-collapsed',
    recentCourses: 'enteraksi-recent-courses',
    videoProgress: 'enteraksi-video-progress',
} as const;

// Debounce delays (in milliseconds)
export const DEBOUNCE = {
    search: 300,
    autosave: 1000,
    resize: 100,
} as const;

// Animation durations (in milliseconds)
export const ANIMATION = {
    fast: 150,
    normal: 300,
    slow: 500,
} as const;

// Toast duration (in milliseconds)
export const TOAST_DURATION = {
    short: 3000,
    normal: 5000,
    long: 8000,
} as const;
```

### Step 5: Create Icons Utility

**File: `resources/js/lib/icons.ts`**
```typescript
import type { ContentType, DifficultyLevel } from '@/types';
import {
    FileText,
    Video,
    Youtube,
    Music,
    File,
    Users,
    BookOpen,
    Award,
    Clock,
    CheckCircle,
    XCircle,
    AlertCircle,
    type LucideIcon,
} from 'lucide-vue-next';

/**
 * Get icon component for content type
 */
export function getContentTypeIcon(type: ContentType): LucideIcon {
    const icons: Record<ContentType, LucideIcon> = {
        text: FileText,
        video: Video,
        youtube: Youtube,
        audio: Music,
        document: File,
        conference: Users,
    };
    return icons[type] || FileText;
}

/**
 * Get icon component for difficulty level
 */
export function getDifficultyIcon(level: DifficultyLevel): LucideIcon {
    const icons: Record<DifficultyLevel, LucideIcon> = {
        beginner: BookOpen,
        intermediate: Award,
        advanced: Award,
    };
    return icons[level];
}

/**
 * Get status icon
 */
export function getStatusIcon(
    status: 'success' | 'error' | 'warning' | 'info' | 'pending'
): LucideIcon {
    const icons = {
        success: CheckCircle,
        error: XCircle,
        warning: AlertCircle,
        info: AlertCircle,
        pending: Clock,
    };
    return icons[status];
}

/**
 * Content type icon with color classes
 */
export function getContentTypeIconWithColor(type: ContentType): {
    icon: LucideIcon;
    colorClass: string;
} {
    const config: Record<ContentType, { icon: LucideIcon; colorClass: string }> = {
        text: { icon: FileText, colorClass: 'text-blue-500' },
        video: { icon: Video, colorClass: 'text-purple-500' },
        youtube: { icon: Youtube, colorClass: 'text-red-500' },
        audio: { icon: Music, colorClass: 'text-green-500' },
        document: { icon: File, colorClass: 'text-orange-500' },
        conference: { icon: Users, colorClass: 'text-cyan-500' },
    };
    return config[type] || { icon: FileText, colorClass: 'text-gray-500' };
}
```

### Step 6: Update Main Utils File

**File: `resources/js/lib/utils.ts`**
```typescript
import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { InertiaLinkProps } from '@inertiajs/vue3';

/**
 * Merge Tailwind CSS classes with proper precedence
 */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

/**
 * Check if a URL matches the current URL
 */
export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string
): boolean {
    return toUrl(urlToCheck) === currentUrl;
}

/**
 * Extract URL string from Inertia href
 */
export function toUrl(href: NonNullable<InertiaLinkProps['href']>): string {
    return typeof href === 'string' ? href : href?.url ?? '';
}

/**
 * Debounce function execution
 */
export function debounce<T extends (...args: unknown[]) => unknown>(
    fn: T,
    delay: number
): (...args: Parameters<T>) => void {
    let timeoutId: ReturnType<typeof setTimeout>;

    return (...args: Parameters<T>) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

/**
 * Throttle function execution
 */
export function throttle<T extends (...args: unknown[]) => unknown>(
    fn: T,
    limit: number
): (...args: Parameters<T>) => void {
    let inThrottle = false;

    return (...args: Parameters<T>) => {
        if (!inThrottle) {
            fn(...args);
            inThrottle = true;
            setTimeout(() => (inThrottle = false), limit);
        }
    };
}

/**
 * Create a promise that resolves after specified milliseconds
 */
export function sleep(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Safely parse JSON with fallback
 */
export function safeJsonParse<T>(json: string, fallback: T): T {
    try {
        return JSON.parse(json) as T;
    } catch {
        return fallback;
    }
}

/**
 * Check if value is empty (null, undefined, empty string, empty array, empty object)
 */
export function isEmpty(value: unknown): boolean {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') return value.trim() === '';
    if (Array.isArray(value)) return value.length === 0;
    if (typeof value === 'object') return Object.keys(value).length === 0;
    return false;
}

/**
 * Deep clone an object
 */
export function deepClone<T>(obj: T): T {
    return JSON.parse(JSON.stringify(obj));
}

/**
 * Pick specific keys from an object
 */
export function pick<T extends object, K extends keyof T>(
    obj: T,
    keys: K[]
): Pick<T, K> {
    return keys.reduce((acc, key) => {
        if (key in obj) {
            acc[key] = obj[key];
        }
        return acc;
    }, {} as Pick<T, K>);
}

/**
 * Omit specific keys from an object
 */
export function omit<T extends object, K extends keyof T>(
    obj: T,
    keys: K[]
): Omit<T, K> {
    const result = { ...obj };
    keys.forEach(key => delete result[key]);
    return result;
}

/**
 * Group array items by a key
 */
export function groupBy<T>(
    array: T[],
    keyFn: (item: T) => string | number
): Record<string | number, T[]> {
    return array.reduce((acc, item) => {
        const key = keyFn(item);
        if (!acc[key]) {
            acc[key] = [];
        }
        acc[key].push(item);
        return acc;
    }, {} as Record<string | number, T[]>);
}

/**
 * Sort array by key
 */
export function sortBy<T>(
    array: T[],
    keyFn: (item: T) => string | number,
    order: 'asc' | 'desc' = 'asc'
): T[] {
    return [...array].sort((a, b) => {
        const aVal = keyFn(a);
        const bVal = keyFn(b);

        if (aVal < bVal) return order === 'asc' ? -1 : 1;
        if (aVal > bVal) return order === 'asc' ? 1 : -1;
        return 0;
    });
}

/**
 * Remove duplicates from array by key
 */
export function uniqueBy<T>(array: T[], keyFn: (item: T) => unknown): T[] {
    const seen = new Set();
    return array.filter(item => {
        const key = keyFn(item);
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
}

// Re-export all utilities for convenience
export * from './formatters';
export * from './date';
export * from './string';
export * from './constants';
export * from './icons';
```

---

## Migration Strategy

### Step 1: Create All Utility Files
1. Create files as shown above
2. Ensure all functions have proper TypeScript types
3. Add JSDoc comments for documentation

### Step 2: Find and Replace Duplicates
```bash
# Find all inline function definitions
grep -r "function formatDuration" resources/js/pages/ --include="*.vue"
grep -r "function difficultyLabel" resources/js/pages/ --include="*.vue"
grep -r "function contentTypeIcon" resources/js/pages/ --include="*.vue"
```

### Step 3: Migrate File by File
```vue
<!-- BEFORE -->
<script setup lang="ts">
function formatDuration(minutes: number) {
    // inline implementation
}
</script>

<!-- AFTER -->
<script setup lang="ts">
import { formatDuration } from '@/lib/formatters';
</script>
```

### Step 4: Remove Inline Definitions
After migration, search for and remove all inline utility function definitions.

---

## Checklist

### Infrastructure
- [ ] Create `lib/formatters.ts`
- [ ] Create `lib/date.ts`
- [ ] Create `lib/string.ts`
- [ ] Create `lib/constants.ts`
- [ ] Create `lib/icons.ts`
- [ ] Update `lib/utils.ts` with re-exports

### Migration
- [ ] Migrate `formatDuration` usages
- [ ] Migrate `difficultyLabel` usages
- [ ] Migrate `contentTypeIcon` usages
- [ ] Migrate `formatDate` usages
- [ ] Migrate `formatCurrency` usages
- [ ] Migrate `truncate` usages
- [ ] Migrate status color functions
- [ ] Remove all inline utility definitions

### Validation
- [ ] All imports resolve correctly
- [ ] No duplicate function definitions remain
- [ ] Bundle size reduced (tree shaking working)
- [ ] All formatting consistent across pages

---

## Success Criteria

| Metric | Before | After |
|--------|--------|-------|
| Utility files | 1 (19 lines) | 6 (500+ lines) |
| Duplicated functions | 40+ occurrences | 0 |
| Type coverage | ~30% | 100% |
| Bundle duplicates | High | Eliminated |

---

## Next Phase

After completing Utility Extraction, proceed to [Phase 3: Component Architecture](./03-COMPONENT-ARCHITECTURE.md).
