# TODO List for Enteraksi LMS Project

**Last Updated:** 2025-12-20T09:47:04.713Z (UTC)

This document outlines the step-by-step tasks required to implement all missing features for the Enteraksi LMS project.

## Priority 1: Fix Existing Issues

### 1. Fix the Failing Test

- **Task**: Resolve the learner access authorization policy issue.
- **Details**: The test for learner access to the courses index is failing due to an authorization policy issue.
- **Files to Modify**:
    - `app/Policies/CoursePolicy.php`
    - `tests/Feature/CourseListTest.php`
- **Status**: ❌ Not Started

### 2. Complete Learner Dashboard

- **Task**: Enhance the "My Learning" section with full progress integration.
- **Details**: The "My Learning" section structure exists but lacks full progress data integration.
- **Files to Modify**:
    - `resources/js/Pages/learner/Dashboard.vue`
    - `app/Http/Controllers/LearnerDashboardController.php`
- **Status**: ❌ Not Started

### 3. Implement Duration Re-estimation

- **Task**: Complete the lesson duration estimation feature.
- **Details**: The duration re-estimation button exists but is not fully implemented.
- **Files to Modify**:
    - `app/Http/Controllers/CourseDurationController.php`
    - `resources/js/components/courses/DurationDisplay.vue`
- **Status**: ❌ Not Started

## Priority 2: Partially Implemented Features

### 4. Complete Lesson Progress Tracking

- **Task**: Fully implement lesson progress tracking.
- **Details**: The lesson progress migration exists, but progress is not updating.
- **Files to Create/Modify**:
    - `app/Models/LessonProgress.php`
    - `app/Http/Controllers/LessonProgressController.php`
    - `resources/js/Pages/lessons/Show.vue`
    - `routes/courses.php`
- **Status**: ⚠️ Partially Complete (60%)

### 5. Enhance Learner Dashboard

- **Task**: Add "My Learning" section with enrolled courses and progress bars.
- **Details**: The "My Learning" section needs to display enrolled courses with progress bars and a "Continue Learning" button.
- **Files to Create/Modify**:
    - `resources/js/Pages/learner/Dashboard.vue`
    - `resources/js/components/courses/MyLearningCard.vue`
    - `app/Http/Controllers/LearnerDashboardController.php`
- **Status**: ⚠️ Partially Complete (40%)

## Priority 3: Missing Features

### 6. Build Assessment Module Foundation

- **Task**: Create the foundation for the assessment module.
- **Details**: Implement quiz/assessment creation for content managers.
- **Files to Create**:
    - `database/migrations/xxxx_create_assessments_table.php`
    - `database/migrations/xxxx_create_questions_table.php`
    - `database/migrations/xxxx_create_question_options_table.php`
    - `database/migrations/xxxx_create_assessment_attempts_table.php`
    - `database/migrations/xxxx_create_attempt_answers_table.php`
    - `app/Models/Assessment.php`
    - `app/Models/Question.php`
    - `app/Models/QuestionOption.php`
    - `app/Models/AssessmentAttempt.php`
    - `app/Models/AttemptAnswer.php`
    - `app/Http/Controllers/AssessmentController.php`
    - `app/Http/Controllers/QuestionController.php`
    - `app/Http/Requests/Assessment/StoreAssessmentRequest.php`
    - `app/Http/Requests/Assessment/UpdateAssessmentRequest.php`
    - `app/Http/Requests/Question/StoreQuestionRequest.php`
    - `app/Policies/AssessmentPolicy.php`
    - `resources/js/Pages/assessments/Index.vue`
    - `resources/js/Pages/assessments/Create.vue`
    - `resources/js/Pages/assessments/Edit.vue`
    - `resources/js/Pages/assessments/Show.vue`
- **Status**: ❌ Not Started

### 7. Implement Learning Paths

- **Task**: Create learning paths by combining multiple courses.
- **Details**: Implement course sequencing and dependency management.
- **Files to Create**:
    - `database/migrations/xxxx_create_learning_paths_table.php`
    - `database/migrations/xxxx_create_learning_path_course_table.php`
    - `app/Models/LearningPath.php`
    - `app/Http/Controllers/LearningPathController.php`
    - `resources/js/Pages/learning_paths/Index.vue`
    - `resources/js/Pages/learning_paths/Create.vue`
    - `resources/js/Pages/learning_paths/Edit.vue`
- **Status**: ❌ Not Started

### 8. Add Certificate System

- **Task**: Implement competency framework and certificate generation.
- **Details**: Create certificate templates and auto-certificate issuance on course completion.
- **Files to Create**:
    - `database/migrations/xxxx_create_certificates_table.php`
    - `database/migrations/xxxx_create_competencies_table.php`
    - `app/Models/Certificate.php`
    - `app/Models/Competency.php`
    - `app/Http/Controllers/CertificateController.php`
    - `resources/js/Pages/certificates/Index.vue`
    - `resources/js/Pages/certificates/Show.vue`
- **Status**: ❌ Not Started

### 9. Implement Gamification

- **Task**: Add experience points system and badge definitions.
- **Details**: Implement badge awarding logic and achievement tracking.
- **Files to Create**:
    - `database/migrations/xxxx_create_badges_table.php`
    - `database/migrations/xxxx_create_user_badges_table.php`
    - `app/Models/Badge.php`
    - `app/Http/Controllers/BadgeController.php`
    - `resources/js/components/gamification/BadgeDisplay.vue`
    - `resources/js/components/gamification/Leaderboard.vue`
- **Status**: ❌ Not Started

## Priority 4: Testing and Documentation

### 10. Write Tests for New Features

- **Task**: Write comprehensive tests for all new features.
- **Details**: Ensure all new features have proper test coverage.
- **Files to Create**:
    - `tests/Feature/AssessmentCrudTest.php`
    - `tests/Feature/QuestionCrudTest.php`
    - `tests/Feature/LearningPathCrudTest.php`
    - `tests/Feature/CertificateTest.php`
    - `tests/Feature/BadgeTest.php`
- **Status**: ❌ Not Started

### 11. Update Documentation

- **Task**: Update project documentation to reflect new features.
- **Details**: Ensure all new features are documented in the project's documentation.
- **Files to Modify**:
    - `SUMMARY.md`
    - `README.md`
    - `docs/` (if applicable)
- **Status**: ❌ Not Started

## Summary

This TODO list outlines the tasks required to complete the Enteraksi LMS project. The tasks are prioritized based on their importance and dependencies. The first priority is to fix existing issues, followed by completing partially implemented features, and then implementing missing features. Finally, comprehensive testing and documentation updates are required to ensure the project is production-ready.
