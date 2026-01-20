/**
 * Lesson-related type definitions
 *
 * Matches the actual Lesson, LessonProgress, and Media models.
 */

import type {
    Timestamps,
    LessonId,
    CourseSectionId,
    EnrollmentId,
    ContentType,
    ConferenceType,
} from './common';

// =============================================================================
// Media Types
// =============================================================================

/**
 * Media model - matches database columns and appended attributes.
 */
export interface Media extends Timestamps {
    id: number;
    mediable_type: string;
    mediable_id: number;
    collection_name: string;
    name: string;
    file_name: string;
    mime_type: string;
    disk: string;
    path: string;
    size: number;
    duration_seconds: number | null;
    custom_properties: Record<string, unknown> | null;
    order_column: number | null;

    // Appended attributes (model accessors)
    url: string;
    human_readable_size: string;
    duration_formatted: string | null;
    is_video: boolean;
    is_audio: boolean;
    is_document: boolean;
    is_image: boolean;
}

/**
 * Minimal media info for display.
 */
export interface MediaSummary {
    id: number;
    name: string;
    file_name: string;
    mime_type: string;
    size: number;
    human_readable_size: string;
    url: string;
    duration_seconds: number | null;
    duration_formatted: string | null;
    is_video: boolean;
    is_audio: boolean;
    is_document: boolean;
    collection_name: string;
}

// =============================================================================
// Lesson Types
// =============================================================================

/**
 * Full Lesson model - matches database columns and model accessors.
 */
export interface Lesson extends Timestamps {
    id: LessonId;
    course_section_id: CourseSectionId;
    title: string;
    description: string | null;
    order: number;
    content_type: ContentType;
    rich_content: TipTapContent | null;
    youtube_url: string | null;
    conference_url: string | null;
    conference_type: ConferenceType | null;
    estimated_duration_minutes: number | null;
    is_free_preview: boolean;

    // Model accessors (appended attributes)
    youtube_video_id?: string | null;
    rich_content_html?: string | null;
    has_video?: boolean;
    has_audio?: boolean;
    has_document?: boolean;
    has_conference?: boolean;

    // Relations (conditionally loaded)
    section?: LessonSection;
    media?: Media[];

    // Progress info (when user context is present)
    user_progress?: LessonProgress;
    is_completed?: boolean;
}

/**
 * Minimal section info for lesson context.
 */
export interface LessonSection {
    id: CourseSectionId;
    course_id: number;
    title: string;
    order: number;
}

/**
 * Lesson for curriculum/outline display (lighter representation).
 */
export interface LessonSummary {
    id: LessonId;
    title: string;
    content_type: ContentType;
    order: number;
    estimated_duration_minutes: number | null;
    is_free_preview: boolean;

    // Progress (optional)
    is_completed?: boolean;
}

/**
 * Lesson with navigation context.
 */
export interface LessonWithNavigation extends Lesson {
    previous_lesson?: LessonNavItem | null;
    next_lesson?: LessonNavItem | null;
}

/**
 * Navigation item for previous/next lesson.
 */
export interface LessonNavItem {
    id: LessonId;
    title: string;
    section_title: string;
    is_completed?: boolean;
}

// =============================================================================
// TipTap Content (Rich Text Editor)
// =============================================================================

/**
 * TipTap JSON content structure.
 * This is the raw JSON stored in rich_content column.
 */
export interface TipTapContent {
    type: 'doc';
    content?: TipTapNode[];
}

/**
 * Generic TipTap node structure.
 */
export interface TipTapNode {
    type: string;
    attrs?: Record<string, unknown>;
    content?: TipTapNode[];
    text?: string;
    marks?: TipTapMark[];
}

/**
 * TipTap mark (text formatting).
 */
export interface TipTapMark {
    type: string;
    attrs?: Record<string, unknown>;
}

// =============================================================================
// Lesson Progress Types
// =============================================================================

/**
 * Lesson progress model - matches database columns.
 */
export interface LessonProgress extends Timestamps {
    id: number;
    enrollment_id: EnrollmentId;
    lesson_id: LessonId;

    // Progress tracking
    current_page: number;
    total_pages: number | null;
    highest_page_reached: number;
    time_spent_seconds: number;
    is_completed: boolean;
    last_viewed_at: string | null;
    completed_at: string | null;

    // Media progress (video/youtube/audio)
    media_position_seconds: number | null;
    media_duration_seconds: number | null;
    media_progress_percentage: number;

    // Pagination metadata (viewport info for text recalculation)
    pagination_metadata: PaginationMetadata | null;

    // Model accessors
    progress_percentage?: number;
    time_spent_formatted?: string;
    resume_position?: number | null;
}

/**
 * Pagination metadata stored for text content.
 */
export interface PaginationMetadata {
    viewport_width?: number;
    viewport_height?: number;
    font_size?: number;
    [key: string]: unknown;
}

/**
 * Progress update data sent from client.
 */
export interface UpdateProgressData {
    current_page?: number;
    total_pages?: number;
    time_spent_seconds?: number;
    pagination_metadata?: PaginationMetadata;

    // For media content
    media_position_seconds?: number;
    media_duration_seconds?: number;
}

// =============================================================================
// Form Data Types
// =============================================================================

/**
 * Data for creating a new lesson.
 */
export interface CreateLessonData {
    title: string;
    content_type: ContentType;
    description?: string | null;
    is_free_preview?: boolean;
    estimated_duration_minutes?: number | null;

    // Content-specific fields
    rich_content?: TipTapContent | null;
    youtube_url?: string | null;
    conference_url?: string | null;
    conference_type?: ConferenceType | null;
}

/**
 * Data for updating a lesson.
 */
export interface UpdateLessonData extends Partial<CreateLessonData> {
    order?: number;
}

/**
 * Data for reordering lessons.
 */
export interface ReorderLessonsData {
    lessons: Array<{ id: LessonId; order: number }>;
}

// =============================================================================
// Type Guards
// =============================================================================

/**
 * Check if lesson has text content.
 */
export function isTextLesson(lesson: Lesson | LessonSummary): boolean {
    return lesson.content_type === 'text';
}

/**
 * Check if lesson has video content (uploaded video or YouTube).
 */
export function isVideoLesson(lesson: Lesson | LessonSummary): boolean {
    return lesson.content_type === 'video' || lesson.content_type === 'youtube';
}

/**
 * Check if lesson has YouTube content.
 */
export function isYouTubeLesson(lesson: Lesson | LessonSummary): boolean {
    return lesson.content_type === 'youtube';
}

/**
 * Check if lesson has audio content.
 */
export function isAudioLesson(lesson: Lesson | LessonSummary): boolean {
    return lesson.content_type === 'audio';
}

/**
 * Check if lesson has document content.
 */
export function isDocumentLesson(lesson: Lesson | LessonSummary): boolean {
    return lesson.content_type === 'document';
}

/**
 * Check if lesson has conference content.
 */
export function isConferenceLesson(lesson: Lesson | LessonSummary): boolean {
    return lesson.content_type === 'conference';
}

/**
 * Check if lesson has media-based content (video, youtube, audio).
 */
export function isMediaBasedLesson(lesson: Lesson | LessonSummary): boolean {
    return ['video', 'youtube', 'audio'].includes(lesson.content_type);
}

/**
 * Get content type icon name (for lucide icons).
 */
export function getContentTypeIcon(contentType: ContentType): string {
    const icons: Record<ContentType, string> = {
        text: 'FileText',
        video: 'PlayCircle',
        youtube: 'Youtube',
        audio: 'Headphones',
        document: 'FileDown',
        conference: 'Video',
    };
    return icons[contentType];
}

/**
 * Get content type label in Indonesian.
 */
export function getContentTypeLabel(contentType: ContentType): string {
    const labels: Record<ContentType, string> = {
        text: 'Teks',
        video: 'Video',
        youtube: 'YouTube',
        audio: 'Audio',
        document: 'Dokumen',
        conference: 'Konferensi',
    };
    return labels[contentType];
}
