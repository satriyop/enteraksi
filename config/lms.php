<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Progress Calculator
    |--------------------------------------------------------------------------
    |
    | The default progress calculator strategy to use for enrollment progress.
    |
    | Options: 'lesson_based', 'weighted', 'assessment_inclusive'
    |
    */

    'progress_calculator' => env('LMS_PROGRESS_CALCULATOR', 'lesson_based'),

    /*
    |--------------------------------------------------------------------------
    | Grading Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for auto-grading behavior.
    |
    */

    'grading' => [
        // Partial credit settings for short answer fuzzy matching
        'short_answer_similarity_threshold' => 0.8,

        // Whether to enable partial credit for multiple choice
        'multiple_choice_partial_credit' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Default notification channels for different event types.
    |
    */

    'notifications' => [
        'enrollment_created' => ['mail', 'database'],
        'enrollment_completed' => ['mail', 'database'],
        'assessment_graded' => ['mail', 'database'],
        'default' => ['database'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Enrollment Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for course enrollments.
    |
    */

    'enrollment' => [
        // Whether to automatically complete enrollment when all lessons are done
        'auto_complete' => true,

        // Whether to allow re-enrollment after dropping
        'allow_re_enrollment' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Assessment Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for assessments.
    |
    */

    'assessment' => [
        // Default passing score (percentage)
        'default_passing_score' => 70,

        // Default maximum attempts (0 = unlimited)
        'default_max_attempts' => 3,

        // Default time limit in minutes (null = no limit)
        'default_time_limit' => null,
    ],

];
