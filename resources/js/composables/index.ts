// =============================================================================
// Composables Index
// Re-exports all composables for easy importing
// =============================================================================

// =============================================================================
// Data Composables
// For fetching and managing domain data
// =============================================================================

export { useCourse } from './data/useCourse';
export { useCourses } from './data/useCourses';
export { useEnrollment } from './data/useEnrollment';
export { useLesson } from './data/useLesson';
export { useAssessment } from './data/useAssessment';

// =============================================================================
// Feature Composables
// Domain-specific business logic
// =============================================================================

export { useVideoPlayer } from './features/useVideoPlayer';
export { useFileUpload } from './features/useFileUpload';
export { useGrading } from './features/useGrading';
export { useOptimisticUpdate, optimisticUpdate } from './features/useOptimisticUpdate';
export { useFeatureFlags, usePercentageRollout } from './features/useFeatureFlags';

// =============================================================================
// UI Composables
// For managing UI state
// =============================================================================

export { useModal } from './ui/useModal';
export { useConfirmation } from './ui/useConfirmation';
export { useToast } from './ui/useToast';
export { useSearch } from './ui/useSearch';
export { usePagination, type PaginationMeta } from './ui/usePagination';
export { useTabs } from './ui/useTabs';

// =============================================================================
// Utility Composables
// General-purpose composables for common patterns
// =============================================================================

export { useEventListener } from './utils/useEventListener';
export { useDebouncedWatch, useDebouncedWatchMultiple } from './utils/useDebouncedWatch';

// =============================================================================
// Existing Composables (Root Level)
// Legacy composables kept at root for backward compatibility
// =============================================================================

export { useAppearance } from './useAppearance';
export { useInitials } from './useInitials';
export { useTwoFactorAuth } from './useTwoFactorAuth';
export { useLessonProgress } from './useLessonProgress';
export { useLessonMediaProgress } from './useLessonMediaProgress';
export { useLessonPagination } from './useLessonPagination';
export { useAssessmentTimer } from './useAssessmentTimer';
