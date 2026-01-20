// =============================================================================
// Test Factories
// Factory functions for creating test data
// =============================================================================

import type {
    User,
    Course,
    CourseSection,
    Lesson,
    Assessment,
    AssessmentAttempt,
    Question,
    Enrollment,
    LessonProgress,
} from '@/types';

// =============================================================================
// ID Counters
// =============================================================================

let userIdCounter = 0;
let courseIdCounter = 0;
let sectionIdCounter = 0;
let lessonIdCounter = 0;
let assessmentIdCounter = 0;
let questionIdCounter = 0;
let attemptIdCounter = 0;
let enrollmentIdCounter = 0;

// =============================================================================
// Reset Factories
// =============================================================================

/**
 * Reset all ID counters (call in beforeEach or afterEach)
 */
export function resetFactories(): void {
    userIdCounter = 0;
    courseIdCounter = 0;
    sectionIdCounter = 0;
    lessonIdCounter = 0;
    assessmentIdCounter = 0;
    questionIdCounter = 0;
    attemptIdCounter = 0;
    enrollmentIdCounter = 0;
}

// =============================================================================
// User Factory
// =============================================================================

export function createUser(overrides: Partial<User> = {}): User {
    const id = ++userIdCounter;
    return {
        id,
        name: `Test User ${id}`,
        email: `user${id}@example.com`,
        email_verified_at: new Date().toISOString(),
        role: 'learner',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    };
}

// =============================================================================
// Course Factory
// =============================================================================

export function createCourse(overrides: Partial<Course> = {}): Course {
    const id = ++courseIdCounter;
    return {
        id,
        user_id: 1,
        title: `Test Course ${id}`,
        slug: `test-course-${id}`,
        short_description: 'A test course description',
        long_description: 'A longer test course description with more details.',
        objectives: ['Learn testing', 'Master Vue.js'],
        prerequisites: ['Basic JavaScript'],
        category_id: 1,
        thumbnail_path: null,
        status: 'draft',
        visibility: 'public',
        difficulty_level: 'beginner',
        estimated_duration_minutes: 60,
        manual_duration_minutes: null,
        published_at: null,
        published_by: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        deleted_at: null,
        ...overrides,
    };
}

// =============================================================================
// Section Factory
// =============================================================================

export function createSection(overrides: Partial<CourseSection> = {}): CourseSection {
    const id = ++sectionIdCounter;
    return {
        id,
        course_id: 1,
        title: `Section ${id}`,
        description: null,
        order: id,
        estimated_duration_minutes: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        lessons: [],
        ...overrides,
    };
}

// =============================================================================
// Lesson Factory
// =============================================================================

export function createLesson(overrides: Partial<Lesson> = {}): Lesson {
    const id = ++lessonIdCounter;
    return {
        id,
        course_section_id: 1,
        title: `Lesson ${id}`,
        description: null,
        order: id,
        content_type: 'text',
        estimated_duration_minutes: 10,
        is_free_preview: false,
        ...overrides,
    };
}

// =============================================================================
// Assessment Factory
// =============================================================================

export function createAssessment(overrides: Partial<Assessment> = {}): Assessment {
    const id = ++assessmentIdCounter;
    return {
        id,
        course_id: 1,
        title: `Test Assessment ${id}`,
        description: 'A test assessment',
        instructions: 'Complete all questions',
        type: 'quiz',
        status: 'draft',
        passing_score: 70,
        max_attempts: 3,
        time_limit_minutes: 30,
        shuffle_questions: false,
        shuffle_options: false,
        show_correct_answers: false,
        allow_review: true,
        total_questions: 5,
        total_points: 50,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    } as Assessment;
}

// =============================================================================
// Question Factory
// =============================================================================

export function createQuestion(overrides: Partial<Question> = {}): Question {
    const id = ++questionIdCounter;
    return {
        id,
        assessment_id: 1,
        question_text: `Question ${id}: What is the answer?`,
        question_type: 'multiple_choice',
        points: 10,
        order: id,
        explanation: null,
        options: [
            { id: 'a', text: 'Option A', is_correct: false },
            { id: 'b', text: 'Option B', is_correct: true },
            { id: 'c', text: 'Option C', is_correct: false },
            { id: 'd', text: 'Option D', is_correct: false },
        ],
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    } as Question;
}

// =============================================================================
// Assessment Attempt Factory
// =============================================================================

export function createAttempt(overrides: Partial<AssessmentAttempt> = {}): AssessmentAttempt {
    const id = ++attemptIdCounter;
    return {
        id,
        assessment_id: 1,
        user_id: 1,
        status: 'in_progress',
        started_at: new Date().toISOString(),
        completed_at: null,
        score: null,
        percentage: null,
        passed: null,
        time_spent_seconds: 0,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    } as AssessmentAttempt;
}

// =============================================================================
// Enrollment Factory
// =============================================================================

export function createEnrollment(overrides: Partial<Enrollment> = {}): Enrollment {
    const id = ++enrollmentIdCounter;
    return {
        id,
        user_id: 1,
        course_id: 1,
        status: 'active',
        progress_percentage: 0,
        completed_lessons_count: 0,
        last_accessed_at: new Date().toISOString(),
        completed_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    } as Enrollment;
}

// =============================================================================
// Lesson Progress Factory
// =============================================================================

export function createLessonProgress(overrides: Partial<LessonProgress> = {}): LessonProgress {
    return {
        lesson_id: 1,
        enrollment_id: 1,
        is_completed: false,
        progress_percentage: 0,
        media_position_seconds: 0,
        current_page: 1,
        total_pages: 1,
        completed_at: null,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        ...overrides,
    } as LessonProgress;
}

// =============================================================================
// Composite Factories
// =============================================================================

/**
 * Create a course with sections and lessons
 */
export function createCourseWithCurriculum(
    courseOverrides: Partial<Course> = {},
    sectionsCount = 2,
    lessonsPerSection = 3
): Course & { sections: (CourseSection & { lessons: Lesson[] })[] } {
    const course = createCourse(courseOverrides);

    const sections = Array.from({ length: sectionsCount }, (_, sIndex) => {
        const section = createSection({
            course_id: course.id,
            order: sIndex + 1,
            title: `Section ${sIndex + 1}`,
        });

        const lessons = Array.from({ length: lessonsPerSection }, (_, lIndex) =>
            createLesson({
                course_section_id: section.id,
                order: lIndex + 1,
                title: `Lesson ${sIndex + 1}.${lIndex + 1}`,
            })
        );

        return { ...section, lessons };
    });

    return { ...course, sections };
}

/**
 * Create an assessment with questions
 */
export function createAssessmentWithQuestions(
    assessmentOverrides: Partial<Assessment> = {},
    questionsCount = 5
): Assessment & { questions: Question[] } {
    const assessment = createAssessment(assessmentOverrides);

    const questions = Array.from({ length: questionsCount }, (_, index) =>
        createQuestion({
            assessment_id: assessment.id,
            order: index + 1,
        })
    );

    return { ...assessment, questions, total_questions: questionsCount };
}
