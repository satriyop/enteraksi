/**
 * String manipulation utilities
 */

// =============================================================================
// Text Truncation
// =============================================================================

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

// =============================================================================
// String Transformation
// =============================================================================

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
 * Convert string to sentence case
 */
export function sentenceCase(text: string | null | undefined): string {
    if (!text) return '';
    return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
}

/**
 * Convert camelCase or PascalCase to human readable
 */
export function humanize(text: string | null | undefined): string {
    if (!text) return '';
    return text
        .replace(/([A-Z])/g, ' $1')
        .replace(/[_-]/g, ' ')
        .trim()
        .toLowerCase()
        .replace(/^\w/, c => c.toUpperCase());
}

// =============================================================================
// Name Utilities
// =============================================================================

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
 * Get first name from full name
 */
export function getFirstName(name: string | null | undefined): string {
    if (!name) return '';
    return name.split(' ')[0];
}

/**
 * Format name with greeting
 */
export function greetName(name: string | null | undefined): string {
    const firstName = getFirstName(name);
    const hour = new Date().getHours();

    let greeting: string;
    if (hour < 11) {
        greeting = 'Selamat pagi';
    } else if (hour < 15) {
        greeting = 'Selamat siang';
    } else if (hour < 18) {
        greeting = 'Selamat sore';
    } else {
        greeting = 'Selamat malam';
    }

    return firstName ? `${greeting}, ${firstName}!` : `${greeting}!`;
}

// =============================================================================
// HTML Utilities
// =============================================================================

/**
 * Strip HTML tags from string
 */
export function stripHtml(html: string | null | undefined): string {
    if (!html) return '';
    return html.replace(/<[^>]*>/g, '');
}

/**
 * Escape HTML special characters
 */
export function escapeHtml(text: string | null | undefined): string {
    if (!text) return '';
    const map: Record<string, string> = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    };
    return text.replace(/[&<>"']/g, char => map[char]);
}

/**
 * Highlight search term in text (returns HTML string)
 */
export function highlightText(
    text: string,
    searchTerm: string,
    highlightClass: string = 'bg-yellow-200 dark:bg-yellow-800'
): string {
    if (!searchTerm) return escapeHtml(text);

    const regex = new RegExp(`(${escapeRegex(searchTerm)})`, 'gi');
    return escapeHtml(text).replace(
        regex,
        `<mark class="${highlightClass}">$1</mark>`
    );
}

// =============================================================================
// Validation Utilities
// =============================================================================

/**
 * Escape special regex characters
 */
export function escapeRegex(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
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

/**
 * Check if string is valid phone number (Indonesian format)
 */
export function isValidPhone(phone: string): boolean {
    // Indonesian phone: starts with 08 or +62, 10-13 digits
    const regex = /^(\+62|62|0)8[1-9][0-9]{7,10}$/;
    return regex.test(phone.replace(/[\s-]/g, ''));
}

// =============================================================================
// Random & ID Generation
// =============================================================================

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
 * Generate UUID v4
 */
export function uuid(): string {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

// =============================================================================
// Misc String Utilities
// =============================================================================

/**
 * Pad string to length
 */
export function padStart(text: string | number, length: number, char: string = '0'): string {
    return String(text).padStart(length, char);
}

/**
 * Remove extra whitespace
 */
export function normalizeWhitespace(text: string | null | undefined): string {
    if (!text) return '';
    return text.replace(/\s+/g, ' ').trim();
}

/**
 * Check if string contains only whitespace
 */
export function isBlank(text: string | null | undefined): boolean {
    return !text || text.trim().length === 0;
}

/**
 * Count words in text
 */
export function wordCount(text: string | null | undefined): number {
    if (!text) return 0;
    return text.trim().split(/\s+/).filter(Boolean).length;
}

/**
 * Count characters (excluding whitespace)
 */
export function charCount(text: string | null | undefined): number {
    if (!text) return 0;
    return text.replace(/\s/g, '').length;
}
