import type { InertiaLinkProps } from '@inertiajs/vue3';
import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

// =============================================================================
// Tailwind CSS Utilities
// =============================================================================

/**
 * Merge Tailwind CSS classes with proper precedence
 */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}

// =============================================================================
// URL Utilities
// =============================================================================

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

// =============================================================================
// Function Utilities
// =============================================================================

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

// =============================================================================
// Object Utilities
// =============================================================================

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

// =============================================================================
// Array Utilities
// =============================================================================

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

/**
 * Chunk array into smaller arrays
 */
export function chunk<T>(array: T[], size: number): T[][] {
    const chunks: T[][] = [];
    for (let i = 0; i < array.length; i += size) {
        chunks.push(array.slice(i, i + size));
    }
    return chunks;
}

/**
 * Flatten nested arrays
 */
export function flatten<T>(array: (T | T[])[]): T[] {
    return array.flat() as T[];
}

/**
 * Get random item from array
 */
export function randomItem<T>(array: T[]): T | undefined {
    if (array.length === 0) return undefined;
    return array[Math.floor(Math.random() * array.length)];
}

/**
 * Shuffle array (Fisher-Yates algorithm)
 */
export function shuffle<T>(array: T[]): T[] {
    const shuffled = [...array];
    for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }
    return shuffled;
}

// =============================================================================
// Number Utilities
// =============================================================================

/**
 * Clamp number between min and max
 */
export function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

/**
 * Linear interpolation between two values
 */
export function lerp(start: number, end: number, t: number): number {
    return start + (end - start) * clamp(t, 0, 1);
}

/**
 * Round to specified decimal places
 */
export function round(value: number, decimals: number = 0): number {
    const factor = Math.pow(10, decimals);
    return Math.round(value * factor) / factor;
}

// =============================================================================
// Re-export All Utilities
// =============================================================================

export * from './formatters';
export * from './date';
export * from './string';
export * from './constants';
export * from './icons';
