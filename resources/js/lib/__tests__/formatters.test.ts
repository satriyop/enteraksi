import { describe, it, expect } from 'vitest';
import {
    formatDuration,
    formatPlaybackTime,
    formatCurrency,
    formatFileSize,
    formatPercentage,
    formatNumber,
    pluralize,
    difficultyLabel,
    difficultyColor,
    courseStatusLabel,
    visibilityLabel,
    enrollmentStatusLabel,
    assessmentStatusLabel,
    attemptStatusLabel,
    contentTypeLabel,
    questionTypeLabel,
    statusBadgeColor,
} from '../formatters';

// =============================================================================
// Duration Formatters
// =============================================================================

describe('formatDuration', () => {
    it('returns "-" for null/undefined/zero', () => {
        expect(formatDuration(null)).toBe('-');
        expect(formatDuration(undefined)).toBe('-');
        expect(formatDuration(0)).toBe('-');
    });

    it('formats minutes only (short format)', () => {
        expect(formatDuration(30)).toBe('30m');
        expect(formatDuration(45)).toBe('45m');
        expect(formatDuration(59)).toBe('59m');
    });

    it('formats hours only (short format)', () => {
        expect(formatDuration(60)).toBe('1j');
        expect(formatDuration(120)).toBe('2j');
        expect(formatDuration(180)).toBe('3j');
    });

    it('formats hours and minutes (short format)', () => {
        expect(formatDuration(90)).toBe('1j 30m');
        expect(formatDuration(150)).toBe('2j 30m');
        expect(formatDuration(75)).toBe('1j 15m');
    });

    it('formats in long format', () => {
        expect(formatDuration(90, 'long')).toBe('1 jam 30 menit');
        expect(formatDuration(60, 'long')).toBe('1 jam');
        expect(formatDuration(30, 'long')).toBe('30 menit');
        expect(formatDuration(125, 'long')).toBe('2 jam 5 menit');
    });

    it('formats in compact format', () => {
        expect(formatDuration(90, 'compact')).toBe('1.5j');
        expect(formatDuration(30, 'compact')).toBe('30m');
        expect(formatDuration(60, 'compact')).toBe('1.0j');
        expect(formatDuration(45, 'compact')).toBe('45m');
    });
});

describe('formatPlaybackTime', () => {
    it('returns "0:00" for null/undefined/zero', () => {
        expect(formatPlaybackTime(null)).toBe('0:00');
        expect(formatPlaybackTime(undefined)).toBe('0:00');
        expect(formatPlaybackTime(0)).toBe('0:00');
    });

    it('formats seconds as mm:ss', () => {
        expect(formatPlaybackTime(30)).toBe('0:30');
        expect(formatPlaybackTime(90)).toBe('1:30');
        expect(formatPlaybackTime(125)).toBe('2:05');
    });

    it('formats with leading zeros', () => {
        expect(formatPlaybackTime(5)).toBe('0:05');
        expect(formatPlaybackTime(65)).toBe('1:05');
    });

    it('formats hours as hh:mm:ss', () => {
        expect(formatPlaybackTime(3661)).toBe('1:01:01');
        expect(formatPlaybackTime(3600)).toBe('1:00:00');
        expect(formatPlaybackTime(7265)).toBe('2:01:05');
    });
});

// =============================================================================
// Currency Formatter
// =============================================================================

describe('formatCurrency', () => {
    it('returns "-" for null/undefined', () => {
        expect(formatCurrency(null)).toBe('-');
        expect(formatCurrency(undefined)).toBe('-');
    });

    it('returns "Gratis" for zero by default', () => {
        expect(formatCurrency(0)).toBe('Gratis');
    });

    it('shows formatted zero when showFree is false', () => {
        const result = formatCurrency(0, { showFree: false });
        expect(result).toMatch(/Rp/);
        expect(result).toMatch(/0/);
    });

    it('formats currency in IDR', () => {
        expect(formatCurrency(100000)).toMatch(/Rp/);
        expect(formatCurrency(100000)).toMatch(/100\.000/);
        expect(formatCurrency(1500000)).toMatch(/1\.500\.000/);
    });

    it('formats compact millions', () => {
        expect(formatCurrency(1500000, { compact: true })).toBe('Rp 1.5jt');
        expect(formatCurrency(2000000, { compact: true })).toBe('Rp 2.0jt');
    });

    it('formats compact thousands', () => {
        expect(formatCurrency(50000, { compact: true })).toBe('Rp 50rb');
        expect(formatCurrency(150000, { compact: true })).toBe('Rp 150rb');
    });

    it('does not compact small amounts', () => {
        expect(formatCurrency(500, { compact: true })).toMatch(/Rp/);
        expect(formatCurrency(500, { compact: true })).toMatch(/500/);
    });
});

// =============================================================================
// File Size Formatter
// =============================================================================

describe('formatFileSize', () => {
    it('returns "0 B" for null/undefined/zero', () => {
        expect(formatFileSize(null)).toBe('0 B');
        expect(formatFileSize(undefined)).toBe('0 B');
        expect(formatFileSize(0)).toBe('0 B');
    });

    it('formats bytes', () => {
        expect(formatFileSize(500)).toBe('500 B');
        expect(formatFileSize(1023)).toBe('1023 B');
    });

    it('formats kilobytes', () => {
        expect(formatFileSize(1024)).toBe('1.0 KB');
        expect(formatFileSize(2048)).toBe('2.0 KB');
        expect(formatFileSize(1536)).toBe('1.5 KB');
    });

    it('formats megabytes', () => {
        expect(formatFileSize(1024 * 1024)).toBe('1.0 MB');
        expect(formatFileSize(5 * 1024 * 1024)).toBe('5.0 MB');
    });

    it('formats gigabytes', () => {
        expect(formatFileSize(1024 * 1024 * 1024)).toBe('1.0 GB');
        expect(formatFileSize(2.5 * 1024 * 1024 * 1024)).toBe('2.5 GB');
    });
});

// =============================================================================
// Number Formatters
// =============================================================================

describe('formatPercentage', () => {
    it('returns "0%" for null/undefined', () => {
        expect(formatPercentage(null)).toBe('0%');
        expect(formatPercentage(undefined)).toBe('0%');
    });

    it('formats percentage without decimals by default', () => {
        expect(formatPercentage(75)).toBe('75%');
        expect(formatPercentage(100)).toBe('100%');
        expect(formatPercentage(0)).toBe('0%');
    });

    it('formats percentage with decimals', () => {
        expect(formatPercentage(75.5, 1)).toBe('75.5%');
        expect(formatPercentage(33.333, 2)).toBe('33.33%');
    });
});

describe('formatNumber', () => {
    it('returns "0" for null/undefined', () => {
        expect(formatNumber(null)).toBe('0');
        expect(formatNumber(undefined)).toBe('0');
    });

    it('formats numbers with thousand separators', () => {
        expect(formatNumber(1000)).toBe('1.000');
        expect(formatNumber(1000000)).toBe('1.000.000');
        expect(formatNumber(12345)).toBe('12.345');
    });

    it('handles small numbers', () => {
        expect(formatNumber(0)).toBe('0');
        expect(formatNumber(999)).toBe('999');
    });
});

describe('pluralize', () => {
    it('returns formatted count with word', () => {
        expect(pluralize(1, 'siswa')).toBe('1 siswa');
        expect(pluralize(10, 'kursus')).toBe('10 kursus');
        expect(pluralize(1000, 'pengguna')).toBe('1.000 pengguna');
    });
});

// =============================================================================
// Label Formatters
// =============================================================================

describe('difficultyLabel', () => {
    it('returns correct Indonesian labels', () => {
        expect(difficultyLabel('beginner')).toBe('Pemula');
        expect(difficultyLabel('intermediate')).toBe('Menengah');
        expect(difficultyLabel('advanced')).toBe('Lanjutan');
    });

    it('returns "-" for null/undefined', () => {
        expect(difficultyLabel(null)).toBe('-');
        expect(difficultyLabel(undefined)).toBe('-');
    });

    it('returns original value for unknown levels', () => {
        expect(difficultyLabel('unknown')).toBe('unknown');
    });
});

describe('difficultyColor', () => {
    it('returns Tailwind classes for difficulty levels', () => {
        expect(difficultyColor('beginner')).toContain('bg-green');
        expect(difficultyColor('intermediate')).toContain('bg-yellow');
        expect(difficultyColor('advanced')).toContain('bg-red');
    });

    it('returns empty string for null/undefined', () => {
        expect(difficultyColor(null)).toBe('');
        expect(difficultyColor(undefined)).toBe('');
    });
});

describe('courseStatusLabel', () => {
    it('returns correct Indonesian labels', () => {
        expect(courseStatusLabel('draft')).toBe('Draf');
        expect(courseStatusLabel('published')).toBe('Dipublikasikan');
        expect(courseStatusLabel('archived')).toBe('Diarsipkan');
    });

    it('returns "-" for null/undefined', () => {
        expect(courseStatusLabel(null)).toBe('-');
        expect(courseStatusLabel(undefined)).toBe('-');
    });
});

describe('visibilityLabel', () => {
    it('returns correct Indonesian labels', () => {
        expect(visibilityLabel('public')).toBe('Publik');
        expect(visibilityLabel('restricted')).toBe('Terbatas');
        expect(visibilityLabel('hidden')).toBe('Tersembunyi');
    });
});

describe('enrollmentStatusLabel', () => {
    it('returns correct Indonesian labels', () => {
        expect(enrollmentStatusLabel('pending')).toBe('Menunggu');
        expect(enrollmentStatusLabel('active')).toBe('Aktif');
        expect(enrollmentStatusLabel('completed')).toBe('Selesai');
        expect(enrollmentStatusLabel('suspended')).toBe('Ditangguhkan');
        expect(enrollmentStatusLabel('cancelled')).toBe('Dibatalkan');
    });
});

describe('assessmentStatusLabel', () => {
    it('returns correct Indonesian labels', () => {
        expect(assessmentStatusLabel('draft')).toBe('Draf');
        expect(assessmentStatusLabel('published')).toBe('Dipublikasikan');
        expect(assessmentStatusLabel('archived')).toBe('Diarsipkan');
    });
});

describe('attemptStatusLabel', () => {
    it('returns correct Indonesian labels', () => {
        expect(attemptStatusLabel('in_progress')).toBe('Sedang Dikerjakan');
        expect(attemptStatusLabel('submitted')).toBe('Telah Dikumpulkan');
        expect(attemptStatusLabel('graded')).toBe('Telah Dinilai');
        expect(attemptStatusLabel('expired')).toBe('Kadaluarsa');
    });
});

describe('contentTypeLabel', () => {
    it('returns correct Indonesian labels', () => {
        expect(contentTypeLabel('text')).toBe('Teks');
        expect(contentTypeLabel('video')).toBe('Video');
        expect(contentTypeLabel('youtube')).toBe('YouTube');
        expect(contentTypeLabel('audio')).toBe('Audio');
        expect(contentTypeLabel('document')).toBe('Dokumen');
        expect(contentTypeLabel('conference')).toBe('Konferensi');
    });
});

describe('questionTypeLabel', () => {
    it('returns correct Indonesian labels', () => {
        expect(questionTypeLabel('multiple_choice')).toBe('Pilihan Ganda');
        expect(questionTypeLabel('true_false')).toBe('Benar/Salah');
        expect(questionTypeLabel('matching')).toBe('Pencocokan');
        expect(questionTypeLabel('short_answer')).toBe('Jawaban Singkat');
        expect(questionTypeLabel('essay')).toBe('Esai');
        expect(questionTypeLabel('file_upload')).toBe('Unggah Berkas');
    });
});

// =============================================================================
// Badge Color Functions
// =============================================================================

describe('statusBadgeColor', () => {
    it('returns Tailwind classes for statuses', () => {
        expect(statusBadgeColor('draft')).toContain('bg-yellow');
        expect(statusBadgeColor('published')).toContain('bg-green');
        expect(statusBadgeColor('archived')).toContain('bg-gray');
    });

    it('returns fallback for unknown status', () => {
        expect(statusBadgeColor('unknown')).toBe('bg-gray-100 text-gray-800');
    });

    it('returns empty string for null/undefined', () => {
        expect(statusBadgeColor(null)).toBe('');
        expect(statusBadgeColor(undefined)).toBe('');
    });
});
