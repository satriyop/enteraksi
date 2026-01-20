import { describe, it, expect } from 'vitest';
import {
    truncate,
    truncateWords,
    slugify,
    titleCase,
    sentenceCase,
    humanize,
    getInitials,
    getFirstName,
    stripHtml,
    escapeHtml,
    escapeRegex,
    isValidEmail,
    isValidUrl,
    isValidPhone,
    randomString,
    uuid,
    padStart,
    normalizeWhitespace,
    isBlank,
    wordCount,
    charCount,
} from '../string';

// =============================================================================
// Text Truncation
// =============================================================================

describe('truncate', () => {
    it('returns empty string for null/undefined', () => {
        expect(truncate(null, 10)).toBe('');
        expect(truncate(undefined, 10)).toBe('');
    });

    it('returns original if shorter than limit', () => {
        expect(truncate('Hello', 10)).toBe('Hello');
        expect(truncate('Hi', 5)).toBe('Hi');
    });

    it('truncates with ellipsis', () => {
        expect(truncate('Hello World', 8)).toBe('Hello...');
        expect(truncate('Testing truncation', 10)).toBe('Testing...');
    });

    it('supports custom suffix', () => {
        expect(truncate('Hello World', 9, '…')).toBe('Hello Wo…');
        // Implementation trims before appending suffix, so 'Hello ' becomes 'Hello'
        expect(truncate('Hello World', 8, '--')).toBe('Hello--');
    });

    it('handles edge cases', () => {
        expect(truncate('AB', 2)).toBe('AB');
        expect(truncate('ABC', 3)).toBe('ABC');
    });
});

describe('truncateWords', () => {
    it('returns empty string for null/undefined', () => {
        expect(truncateWords(null, 3)).toBe('');
        expect(truncateWords(undefined, 3)).toBe('');
    });

    it('returns original if fewer words than limit', () => {
        expect(truncateWords('Hello World', 5)).toBe('Hello World');
        expect(truncateWords('One two', 3)).toBe('One two');
    });

    it('truncates by word count', () => {
        expect(truncateWords('One two three four five', 3)).toBe('One two three...');
        expect(truncateWords('A B C D E', 2)).toBe('A B...');
    });

    it('supports custom suffix', () => {
        expect(truncateWords('One two three four', 2, '…')).toBe('One two…');
    });
});

// =============================================================================
// String Transformation
// =============================================================================

describe('slugify', () => {
    it('converts to lowercase kebab-case', () => {
        expect(slugify('Hello World')).toBe('hello-world');
        expect(slugify('  Multiple   Spaces  ')).toBe('multiple-spaces');
        expect(slugify('Title Case Text')).toBe('title-case-text');
    });

    it('removes special characters', () => {
        expect(slugify('Hello! @World#')).toBe('hello-world');
        expect(slugify('Test $100 Price')).toBe('test-100-price');
    });

    it('handles underscores and hyphens', () => {
        expect(slugify('hello_world')).toBe('hello-world');
        expect(slugify('hello--world')).toBe('hello-world');
    });

    it('trims leading/trailing hyphens', () => {
        expect(slugify('-hello-world-')).toBe('hello-world');
        expect(slugify('---test---')).toBe('test');
    });
});

describe('titleCase', () => {
    it('returns empty string for null/undefined', () => {
        expect(titleCase(null)).toBe('');
        expect(titleCase(undefined)).toBe('');
    });

    it('converts to title case', () => {
        expect(titleCase('hello world')).toBe('Hello World');
        expect(titleCase('UPPERCASE TEXT')).toBe('Uppercase Text');
        expect(titleCase('mixed CASE text')).toBe('Mixed Case Text');
    });
});

describe('sentenceCase', () => {
    it('returns empty string for null/undefined', () => {
        expect(sentenceCase(null)).toBe('');
        expect(sentenceCase(undefined)).toBe('');
    });

    it('converts to sentence case', () => {
        expect(sentenceCase('HELLO WORLD')).toBe('Hello world');
        expect(sentenceCase('hello world')).toBe('Hello world');
    });
});

describe('humanize', () => {
    it('returns empty string for null/undefined', () => {
        expect(humanize(null)).toBe('');
        expect(humanize(undefined)).toBe('');
    });

    it('converts camelCase to human readable', () => {
        expect(humanize('camelCase')).toBe('Camel case');
        expect(humanize('myVariableName')).toBe('My variable name');
    });

    it('converts PascalCase to human readable', () => {
        expect(humanize('PascalCase')).toBe('Pascal case');
    });

    it('converts snake_case to human readable', () => {
        expect(humanize('snake_case')).toBe('Snake case');
        expect(humanize('my_variable_name')).toBe('My variable name');
    });
});

// =============================================================================
// Name Utilities
// =============================================================================

describe('getInitials', () => {
    it('returns empty string for null/undefined', () => {
        expect(getInitials(null)).toBe('');
        expect(getInitials(undefined)).toBe('');
        expect(getInitials('')).toBe('');
    });

    it('returns initials from name', () => {
        expect(getInitials('John Doe')).toBe('JD');
        expect(getInitials('Alice Bob')).toBe('AB');
    });

    it('handles single word names', () => {
        expect(getInitials('John')).toBe('J');
        expect(getInitials('Alice')).toBe('A');
    });

    it('respects maxLength', () => {
        expect(getInitials('Alice Bob Charlie', 3)).toBe('ABC');
        expect(getInitials('Alice Bob Charlie', 1)).toBe('A');
        expect(getInitials('John', 2)).toBe('J');
    });

    it('converts to uppercase', () => {
        expect(getInitials('alice bob')).toBe('AB');
    });
});

describe('getFirstName', () => {
    it('returns empty string for null/undefined', () => {
        expect(getFirstName(null)).toBe('');
        expect(getFirstName(undefined)).toBe('');
    });

    it('returns first name', () => {
        expect(getFirstName('John Doe')).toBe('John');
        expect(getFirstName('Alice')).toBe('Alice');
        expect(getFirstName('Bob Smith Jr')).toBe('Bob');
    });
});

// =============================================================================
// HTML Utilities
// =============================================================================

describe('stripHtml', () => {
    it('returns empty string for null/undefined', () => {
        expect(stripHtml(null)).toBe('');
        expect(stripHtml(undefined)).toBe('');
    });

    it('removes HTML tags', () => {
        expect(stripHtml('<p>Hello</p>')).toBe('Hello');
        expect(stripHtml('<div><span>Nested</span></div>')).toBe('Nested');
        expect(stripHtml('<strong>Bold</strong> text')).toBe('Bold text');
    });

    it('handles self-closing tags', () => {
        expect(stripHtml('Line<br/>break')).toBe('Linebreak');
        expect(stripHtml('Image<img src="test.jpg"/>here')).toBe('Imagehere');
    });
});

describe('escapeHtml', () => {
    it('returns empty string for null/undefined', () => {
        expect(escapeHtml(null)).toBe('');
        expect(escapeHtml(undefined)).toBe('');
    });

    it('escapes HTML special characters', () => {
        expect(escapeHtml('<script>')).toBe('&lt;script&gt;');
        expect(escapeHtml('"quotes"')).toBe('&quot;quotes&quot;');
        expect(escapeHtml("'apostrophe'")).toBe('&#039;apostrophe&#039;');
        expect(escapeHtml('&ampersand')).toBe('&amp;ampersand');
    });

    it('handles mixed content', () => {
        expect(escapeHtml('<p class="test">Hello & Goodbye</p>'))
            .toBe('&lt;p class=&quot;test&quot;&gt;Hello &amp; Goodbye&lt;/p&gt;');
    });
});

describe('escapeRegex', () => {
    it('escapes special regex characters', () => {
        expect(escapeRegex('hello.*world')).toBe('hello\\.\\*world');
        expect(escapeRegex('test(123)')).toBe('test\\(123\\)');
        expect(escapeRegex('[a-z]+')).toBe('\\[a-z\\]\\+');
    });
});

// =============================================================================
// Validation Utilities
// =============================================================================

describe('isValidEmail', () => {
    it('validates correct emails', () => {
        expect(isValidEmail('test@example.com')).toBe(true);
        expect(isValidEmail('user.name@domain.co.id')).toBe(true);
        expect(isValidEmail('user+tag@example.org')).toBe(true);
    });

    it('rejects invalid emails', () => {
        expect(isValidEmail('invalid')).toBe(false);
        expect(isValidEmail('missing@domain')).toBe(false);
        expect(isValidEmail('@nodomain.com')).toBe(false);
        expect(isValidEmail('spaces in@email.com')).toBe(false);
        expect(isValidEmail('')).toBe(false);
    });
});

describe('isValidUrl', () => {
    it('validates correct URLs', () => {
        expect(isValidUrl('https://example.com')).toBe(true);
        expect(isValidUrl('http://localhost:3000')).toBe(true);
        expect(isValidUrl('https://sub.domain.com/path?query=1')).toBe(true);
    });

    it('rejects invalid URLs', () => {
        expect(isValidUrl('not-a-url')).toBe(false);
        expect(isValidUrl('example.com')).toBe(false);
        expect(isValidUrl('')).toBe(false);
    });
});

describe('isValidPhone', () => {
    it('validates correct Indonesian phone numbers', () => {
        expect(isValidPhone('081234567890')).toBe(true);
        expect(isValidPhone('+6281234567890')).toBe(true);
        expect(isValidPhone('6281234567890')).toBe(true);
        expect(isValidPhone('0812-3456-7890')).toBe(true);
    });

    it('rejects invalid phone numbers', () => {
        expect(isValidPhone('12345')).toBe(false);
        expect(isValidPhone('abcdefghij')).toBe(false);
        expect(isValidPhone('')).toBe(false);
    });
});

// =============================================================================
// Random & ID Generation
// =============================================================================

describe('randomString', () => {
    it('generates string of specified length', () => {
        expect(randomString(8).length).toBe(8);
        expect(randomString(16).length).toBe(16);
        expect(randomString(4).length).toBe(4);
    });

    it('generates alphanumeric characters', () => {
        const str = randomString(100);
        expect(str).toMatch(/^[A-Za-z0-9]+$/);
    });

    it('generates different strings', () => {
        const str1 = randomString(10);
        const str2 = randomString(10);
        expect(str1).not.toBe(str2);
    });
});

describe('uuid', () => {
    it('generates valid UUID v4 format', () => {
        const id = uuid();
        expect(id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
    });

    it('generates unique UUIDs', () => {
        const id1 = uuid();
        const id2 = uuid();
        expect(id1).not.toBe(id2);
    });
});

// =============================================================================
// Misc String Utilities
// =============================================================================

describe('padStart', () => {
    it('pads string to specified length', () => {
        expect(padStart('5', 2)).toBe('05');
        expect(padStart('42', 4)).toBe('0042');
        expect(padStart(7, 3)).toBe('007');
    });

    it('supports custom padding character', () => {
        expect(padStart('5', 3, '*')).toBe('**5');
        expect(padStart('1', 4, '-')).toBe('---1');
    });

    it('does not pad if already at length', () => {
        expect(padStart('123', 3)).toBe('123');
        expect(padStart('12345', 3)).toBe('12345');
    });
});

describe('normalizeWhitespace', () => {
    it('returns empty string for null/undefined', () => {
        expect(normalizeWhitespace(null)).toBe('');
        expect(normalizeWhitespace(undefined)).toBe('');
    });

    it('removes extra whitespace', () => {
        expect(normalizeWhitespace('hello  world')).toBe('hello world');
        expect(normalizeWhitespace('  spaces   everywhere  ')).toBe('spaces everywhere');
        expect(normalizeWhitespace('tabs\t\there')).toBe('tabs here');
        expect(normalizeWhitespace('newlines\n\nhere')).toBe('newlines here');
    });
});

describe('isBlank', () => {
    it('returns true for null/undefined/empty', () => {
        expect(isBlank(null)).toBe(true);
        expect(isBlank(undefined)).toBe(true);
        expect(isBlank('')).toBe(true);
    });

    it('returns true for whitespace-only', () => {
        expect(isBlank('   ')).toBe(true);
        expect(isBlank('\t\n')).toBe(true);
    });

    it('returns false for non-blank strings', () => {
        expect(isBlank('hello')).toBe(false);
        expect(isBlank('  hello  ')).toBe(false);
    });
});

describe('wordCount', () => {
    it('returns 0 for null/undefined/empty', () => {
        expect(wordCount(null)).toBe(0);
        expect(wordCount(undefined)).toBe(0);
        expect(wordCount('')).toBe(0);
    });

    it('counts words correctly', () => {
        expect(wordCount('hello world')).toBe(2);
        expect(wordCount('one two three four five')).toBe(5);
        expect(wordCount('single')).toBe(1);
    });

    it('handles extra whitespace', () => {
        expect(wordCount('  hello   world  ')).toBe(2);
        expect(wordCount('\ttab\nseparated\nwords')).toBe(3);
    });
});

describe('charCount', () => {
    it('returns 0 for null/undefined/empty', () => {
        expect(charCount(null)).toBe(0);
        expect(charCount(undefined)).toBe(0);
        expect(charCount('')).toBe(0);
    });

    it('counts characters excluding whitespace', () => {
        expect(charCount('hello')).toBe(5);
        expect(charCount('hello world')).toBe(10);
        // '  spaced  out  ' → 'spacedout' = 9 characters
        expect(charCount('  spaced  out  ')).toBe(9);
    });
});
