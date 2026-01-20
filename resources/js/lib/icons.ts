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
    AlertTriangle,
    Info,
    type LucideIcon,
} from 'lucide-vue-next';

// =============================================================================
// Content Type Icons
// =============================================================================

/** Icon mapping for content types */
const contentTypeIconMap: Record<ContentType, LucideIcon> = {
    text: FileText,
    video: Video,
    youtube: Youtube,
    audio: Music,
    document: File,
    conference: Users,
};

/**
 * Get icon component for content type
 */
export function getContentTypeIcon(type: ContentType | string): LucideIcon {
    return contentTypeIconMap[type as ContentType] || FileText;
}

/**
 * Content type icon with color classes
 */
export function getContentTypeIconWithColor(type: ContentType | string): {
    icon: LucideIcon;
    colorClass: string;
    bgClass: string;
} {
    const config: Record<ContentType, { icon: LucideIcon; colorClass: string; bgClass: string }> = {
        text: { icon: FileText, colorClass: 'text-blue-500', bgClass: 'bg-blue-100 dark:bg-blue-900' },
        video: { icon: Video, colorClass: 'text-purple-500', bgClass: 'bg-purple-100 dark:bg-purple-900' },
        youtube: { icon: Youtube, colorClass: 'text-red-500', bgClass: 'bg-red-100 dark:bg-red-900' },
        audio: { icon: Music, colorClass: 'text-green-500', bgClass: 'bg-green-100 dark:bg-green-900' },
        document: { icon: File, colorClass: 'text-orange-500', bgClass: 'bg-orange-100 dark:bg-orange-900' },
        conference: { icon: Users, colorClass: 'text-cyan-500', bgClass: 'bg-cyan-100 dark:bg-cyan-900' },
    };
    return config[type as ContentType] || { icon: FileText, colorClass: 'text-gray-500', bgClass: 'bg-gray-100' };
}

// =============================================================================
// Difficulty Icons
// =============================================================================

/** Icon mapping for difficulty levels */
const difficultyIconMap: Record<DifficultyLevel, LucideIcon> = {
    beginner: BookOpen,
    intermediate: Award,
    advanced: Award,
};

/**
 * Get icon component for difficulty level
 */
export function getDifficultyIcon(level: DifficultyLevel | string): LucideIcon {
    return difficultyIconMap[level as DifficultyLevel] || BookOpen;
}

/**
 * Difficulty icon with color classes
 */
export function getDifficultyIconWithColor(level: DifficultyLevel | string): {
    icon: LucideIcon;
    colorClass: string;
    bgClass: string;
} {
    const config: Record<DifficultyLevel, { icon: LucideIcon; colorClass: string; bgClass: string }> = {
        beginner: { icon: BookOpen, colorClass: 'text-green-500', bgClass: 'bg-green-100 dark:bg-green-900' },
        intermediate: { icon: Award, colorClass: 'text-yellow-500', bgClass: 'bg-yellow-100 dark:bg-yellow-900' },
        advanced: { icon: Award, colorClass: 'text-red-500', bgClass: 'bg-red-100 dark:bg-red-900' },
    };
    return config[level as DifficultyLevel] || { icon: BookOpen, colorClass: 'text-gray-500', bgClass: 'bg-gray-100' };
}

// =============================================================================
// Status Icons
// =============================================================================

type StatusType = 'success' | 'error' | 'warning' | 'info' | 'pending';

/** Icon mapping for status types */
const statusIconMap: Record<StatusType, LucideIcon> = {
    success: CheckCircle,
    error: XCircle,
    warning: AlertTriangle,
    info: Info,
    pending: Clock,
};

/**
 * Get status icon
 */
export function getStatusIcon(status: StatusType): LucideIcon {
    return statusIconMap[status] || AlertCircle;
}

/**
 * Status icon with color classes
 */
export function getStatusIconWithColor(status: StatusType): {
    icon: LucideIcon;
    colorClass: string;
    bgClass: string;
} {
    const config: Record<StatusType, { icon: LucideIcon; colorClass: string; bgClass: string }> = {
        success: { icon: CheckCircle, colorClass: 'text-green-500', bgClass: 'bg-green-100 dark:bg-green-900' },
        error: { icon: XCircle, colorClass: 'text-red-500', bgClass: 'bg-red-100 dark:bg-red-900' },
        warning: { icon: AlertTriangle, colorClass: 'text-yellow-500', bgClass: 'bg-yellow-100 dark:bg-yellow-900' },
        info: { icon: Info, colorClass: 'text-blue-500', bgClass: 'bg-blue-100 dark:bg-blue-900' },
        pending: { icon: Clock, colorClass: 'text-gray-500', bgClass: 'bg-gray-100 dark:bg-gray-800' },
    };
    return config[status];
}

// =============================================================================
// Reexport Icon Components for Convenience
// =============================================================================

export {
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
    AlertTriangle,
    Info,
};
