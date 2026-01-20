// =============================================================================
// Stores Index
// Re-exports all stores for easy importing
// =============================================================================

// =============================================================================
// Global Stores (Singleton Pattern)
// Truly global state shared across the entire application
// =============================================================================

export { useAuth } from './global/auth';

// =============================================================================
// Feature Stores (Provide/Inject Pattern)
// Scoped state shared within component subtrees
// =============================================================================

// Course Editor - for course editing interface
export {
    provideCourseEditor,
    useCourseEditor,
    useCourseEditorOptional,
} from './courseEditor';

// Lesson Viewer - for lesson viewing interface
export {
    provideLessonViewer,
    useLessonViewer,
    useLessonViewerOptional,
} from './lessonViewer';

// Assessment Attempt - for taking assessments
export {
    provideAssessmentAttempt,
    useAssessmentAttempt,
    useAssessmentAttemptOptional,
} from './assessmentAttempt';
