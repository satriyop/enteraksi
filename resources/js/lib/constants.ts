import type {
    ContentType,
    DifficultyLevel,
    CourseStatus,
    EnrollmentStatus,
    AssessmentStatus,
    AttemptStatus,
    CourseVisibility,
} from '@/types';

/**
 * Application-wide constants
 */

// =============================================================================
// Pagination
// =============================================================================

export const DEFAULT_PAGE_SIZE = 10;
export const PAGE_SIZE_OPTIONS = [10, 25, 50, 100] as const;

// =============================================================================
// File Upload Limits
// =============================================================================

export const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB
export const MAX_VIDEO_SIZE = 500 * 1024 * 1024; // 500MB
export const MAX_AUDIO_SIZE = 50 * 1024 * 1024; // 50MB
export const MAX_DOCUMENT_SIZE = 20 * 1024 * 1024; // 20MB
export const MAX_THUMBNAIL_SIZE = 2 * 1024 * 1024; // 2MB

// =============================================================================
// Allowed File Types
// =============================================================================

export const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'] as const;
export const ALLOWED_IMAGE_EXTENSIONS = ['.jpg', '.jpeg', '.png', '.gif', '.webp'] as const;

export const ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/ogg'] as const;
export const ALLOWED_VIDEO_EXTENSIONS = ['.mp4', '.webm', '.ogg'] as const;

export const ALLOWED_AUDIO_TYPES = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp3'] as const;
export const ALLOWED_AUDIO_EXTENSIONS = ['.mp3', '.wav', '.ogg'] as const;

export const ALLOWED_DOCUMENT_TYPES = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
] as const;
export const ALLOWED_DOCUMENT_EXTENSIONS = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx'] as const;

// =============================================================================
// Status Colors (Tailwind classes)
// =============================================================================

export const COURSE_STATUS_COLORS: Record<CourseStatus, { bg: string; text: string; border: string }> = {
    draft: {
        bg: 'bg-gray-100 dark:bg-gray-800',
        text: 'text-gray-700 dark:text-gray-300',
        border: 'border-gray-200 dark:border-gray-700',
    },
    published: {
        bg: 'bg-green-100 dark:bg-green-900',
        text: 'text-green-700 dark:text-green-300',
        border: 'border-green-200 dark:border-green-800',
    },
    archived: {
        bg: 'bg-red-100 dark:bg-red-900',
        text: 'text-red-700 dark:text-red-300',
        border: 'border-red-200 dark:border-red-800',
    },
};

export const ENROLLMENT_STATUS_COLORS: Record<EnrollmentStatus, { bg: string; text: string; border: string }> = {
    pending: {
        bg: 'bg-yellow-100 dark:bg-yellow-900',
        text: 'text-yellow-700 dark:text-yellow-300',
        border: 'border-yellow-200 dark:border-yellow-800',
    },
    active: {
        bg: 'bg-blue-100 dark:bg-blue-900',
        text: 'text-blue-700 dark:text-blue-300',
        border: 'border-blue-200 dark:border-blue-800',
    },
    completed: {
        bg: 'bg-green-100 dark:bg-green-900',
        text: 'text-green-700 dark:text-green-300',
        border: 'border-green-200 dark:border-green-800',
    },
    suspended: {
        bg: 'bg-orange-100 dark:bg-orange-900',
        text: 'text-orange-700 dark:text-orange-300',
        border: 'border-orange-200 dark:border-orange-800',
    },
    cancelled: {
        bg: 'bg-red-100 dark:bg-red-900',
        text: 'text-red-700 dark:text-red-300',
        border: 'border-red-200 dark:border-red-800',
    },
};

export const DIFFICULTY_COLORS: Record<DifficultyLevel, { bg: string; text: string; border: string }> = {
    beginner: {
        bg: 'bg-green-100 dark:bg-green-900',
        text: 'text-green-700 dark:text-green-300',
        border: 'border-green-200 dark:border-green-800',
    },
    intermediate: {
        bg: 'bg-yellow-100 dark:bg-yellow-900',
        text: 'text-yellow-700 dark:text-yellow-300',
        border: 'border-yellow-200 dark:border-yellow-800',
    },
    advanced: {
        bg: 'bg-red-100 dark:bg-red-900',
        text: 'text-red-700 dark:text-red-300',
        border: 'border-red-200 dark:border-red-800',
    },
};

export const ASSESSMENT_STATUS_COLORS: Record<AssessmentStatus, { bg: string; text: string; border: string }> = {
    draft: {
        bg: 'bg-gray-100 dark:bg-gray-800',
        text: 'text-gray-700 dark:text-gray-300',
        border: 'border-gray-200 dark:border-gray-700',
    },
    published: {
        bg: 'bg-green-100 dark:bg-green-900',
        text: 'text-green-700 dark:text-green-300',
        border: 'border-green-200 dark:border-green-800',
    },
    archived: {
        bg: 'bg-red-100 dark:bg-red-900',
        text: 'text-red-700 dark:text-red-300',
        border: 'border-red-200 dark:border-red-800',
    },
};

export const ATTEMPT_STATUS_COLORS: Record<AttemptStatus, { bg: string; text: string; border: string }> = {
    in_progress: {
        bg: 'bg-blue-100 dark:bg-blue-900',
        text: 'text-blue-700 dark:text-blue-300',
        border: 'border-blue-200 dark:border-blue-800',
    },
    submitted: {
        bg: 'bg-yellow-100 dark:bg-yellow-900',
        text: 'text-yellow-700 dark:text-yellow-300',
        border: 'border-yellow-200 dark:border-yellow-800',
    },
    graded: {
        bg: 'bg-green-100 dark:bg-green-900',
        text: 'text-green-700 dark:text-green-300',
        border: 'border-green-200 dark:border-green-800',
    },
    expired: {
        bg: 'bg-red-100 dark:bg-red-900',
        text: 'text-red-700 dark:text-red-300',
        border: 'border-red-200 dark:border-red-800',
    },
};

export const VISIBILITY_COLORS: Record<CourseVisibility, { bg: string; text: string; border: string }> = {
    public: {
        bg: 'bg-green-100 dark:bg-green-900',
        text: 'text-green-700 dark:text-green-300',
        border: 'border-green-200 dark:border-green-800',
    },
    restricted: {
        bg: 'bg-yellow-100 dark:bg-yellow-900',
        text: 'text-yellow-700 dark:text-yellow-300',
        border: 'border-yellow-200 dark:border-yellow-800',
    },
    hidden: {
        bg: 'bg-gray-100 dark:bg-gray-800',
        text: 'text-gray-700 dark:text-gray-300',
        border: 'border-gray-200 dark:border-gray-700',
    },
};

// =============================================================================
// Content Type Colors
// =============================================================================

export const CONTENT_TYPE_COLORS: Record<ContentType, { bg: string; text: string; icon: string }> = {
    text: {
        bg: 'bg-blue-100 dark:bg-blue-900',
        text: 'text-blue-700 dark:text-blue-300',
        icon: 'text-blue-500',
    },
    video: {
        bg: 'bg-purple-100 dark:bg-purple-900',
        text: 'text-purple-700 dark:text-purple-300',
        icon: 'text-purple-500',
    },
    youtube: {
        bg: 'bg-red-100 dark:bg-red-900',
        text: 'text-red-700 dark:text-red-300',
        icon: 'text-red-500',
    },
    audio: {
        bg: 'bg-green-100 dark:bg-green-900',
        text: 'text-green-700 dark:text-green-300',
        icon: 'text-green-500',
    },
    document: {
        bg: 'bg-orange-100 dark:bg-orange-900',
        text: 'text-orange-700 dark:text-orange-300',
        icon: 'text-orange-500',
    },
    conference: {
        bg: 'bg-cyan-100 dark:bg-cyan-900',
        text: 'text-cyan-700 dark:text-cyan-300',
        icon: 'text-cyan-500',
    },
};

// =============================================================================
// Local Storage Keys
// =============================================================================

export const STORAGE_KEYS = {
    theme: 'enteraksi-theme',
    sidebarCollapsed: 'enteraksi-sidebar-collapsed',
    recentCourses: 'enteraksi-recent-courses',
    videoProgress: 'enteraksi-video-progress',
    audioProgress: 'enteraksi-audio-progress',
    lessonProgress: 'enteraksi-lesson-progress',
} as const;

// =============================================================================
// Timing Constants
// =============================================================================

/** Debounce delays in milliseconds */
export const DEBOUNCE = {
    search: 300,
    autosave: 1000,
    resize: 100,
    input: 150,
} as const;

/** Throttle limits in milliseconds */
export const THROTTLE = {
    scroll: 100,
    resize: 200,
    videoProgress: 5000,
} as const;

/** Animation durations in milliseconds */
export const ANIMATION = {
    fast: 150,
    normal: 300,
    slow: 500,
} as const;

/** Toast duration in milliseconds */
export const TOAST_DURATION = {
    short: 3000,
    normal: 5000,
    long: 8000,
} as const;

// =============================================================================
// Assessment Constants
// =============================================================================

export const DEFAULT_PASSING_SCORE = 70;
export const DEFAULT_MAX_ATTEMPTS = 3;
export const DEFAULT_TIME_LIMIT_MINUTES = 60;

// =============================================================================
// Course Constants
// =============================================================================

export const DEFAULT_LESSON_DURATION_MINUTES = 30;
export const MIN_COMPLETION_PERCENTAGE = 80;

// =============================================================================
// Breakpoints (matching Tailwind)
// =============================================================================

export const BREAKPOINTS = {
    sm: 640,
    md: 768,
    lg: 1024,
    xl: 1280,
    '2xl': 1536,
} as const;
