# Project Summary: Enteraksi

## Overview

This project, named "Enteraksi," is a Laravel-based Learning Management System (LMS) application designed for e-learning. It includes various components such as controllers, models, policies, and services, along with frontend assets and configuration files.

## Technologies Used

### Backend

- **PHP**: Version 8.2
- **Laravel Framework**: Version 12.0
- **Authentication**: Laravel Fortify
- **Database**: MySQL (default Laravel configuration)
- **ORM**: Eloquent ORM
- **Testing**: PHPUnit, Pest
- **Debugging**: Laravel Debugbar
- **Task Scheduling**: Laravel Queues

### Frontend

- **JavaScript Framework**: Vue.js 3
- **State Management**: Inertia.js (Laravel Inertia)
- **Styling**: Tailwind CSS
- **Rich Text Editor**: TipTap
- **Drag & Drop**: VueDraggable
- **Build Tool**: Vite
- **TypeScript**: Version 5.2.2

### Additional Tools

- **Code Formatting**: Prettier, ESLint
- **Version Control**: Git
- **Development Environment**: Laravel Sail (Docker)

## Milestones

### Day 1: Core Course Management (✅ ~90% Complete)

- **Course CRUD**: Full CRUD with thumbnail, tags, categories
- **Course Sections**: Drag-drop ordering, duration calculation
- **Lessons**: All 6 content types (text, video, youtube, audio, document, conference)
- **Enrollment System**: Enroll/unenroll, invitation workflow
- **Course Publishing**: Draft → Published → Archived workflow
- **Course Visibility**: Public, Restricted, Hidden
- **Authorization**: Role-based policies (learner, content_manager, lms_admin)
- **Learner Course Browse**: Search, filter by category/difficulty
- **Lesson Viewing**: Udemy-style immersive layout with sidebar
- **Free Preview**: is_free_preview flag working

### Day 2: Progress, Dashboard, Rating, Assessment Foundation (🔄 In Progress)

- **Lesson Progress**: 60% complete (migration exists, but progress not updating)
- **Learner Dashboard**: 40% complete (missing "My Learning" and "Invited Courses" sections)
- **Course Rating**: 0% complete (not started)
- **Duration Re-estimation**: 80% complete (re-estimate button not implemented)

### Day 3: Assessment Taking & Learning Paths (⏳ Pending)

- Assessment taking UI for learners
- Auto-grading logic
- Learning Path CRUD
- Learning Path enrollment

### Day 4: Competencies & Certificates (⏳ Pending)

- Competency framework models
- Grading scale management
- Certificate templates
- Auto-certificate on completion

### Day 5: Gamification (⏳ Pending)

- Experience points system
- Badge definitions
- Badge awarding logic
- Leaderboard (optional)

## Completed Features

### Core Course Management

- **Course CRUD**: Full CRUD with thumbnail, tags, categories
- **Course Sections**: Drag-drop ordering, duration calculation
- **Lessons**: All 6 content types (text, video, youtube, audio, document, conference)
- **Enrollment System**: Enroll/unenroll, invitation workflow
- **Course Publishing**: Draft → Published → Archived workflow
- **Course Visibility**: Public, Restricted, Hidden
- **Authorization**: Role-based policies (learner, content_manager, lms_admin)
- **Learner Course Browse**: Search, filter by category/difficulty
- **Lesson Viewing**: Udemy-style immersive layout with sidebar
- **Free Preview**: is_free_preview flag working

### Database Schema

- **Core Tables**:
    - `users` (with role: learner, content_manager, trainer, lms_admin)
    - `categories`
    - `tags`
    - `courses` (status, visibility, difficulty, objectives, prerequisites)
    - `course_sections` (order, duration)
    - `lessons` (content_type, rich_content, order)
    - `course_tag` (pivot)
    - `media` (polymorphic)
    - `enrollments` (status, progress_percentage, last_lesson_id)
    - `course_invitations`
    - `lesson_progress` (migration exists, not fully implemented)

### Controllers

- **CourseController**: CRUD operations for courses
- **CourseSectionController**: CRUD operations for course sections
- **LessonController**: CRUD operations for lessons
- **CourseReorderController**: Reordering sections and lessons
- **CoursePublishController**: Publishing and unpublishing courses
- **EnrollmentController**: Enrollment management
- **LessonPreviewController**: Previewing lessons

### Models

- **Category**: Manages course categories
- **Tag**: Manages course tags
- **Course**: Manages course details
- **CourseSection**: Manages course sections
- **Lesson**: Manages lesson details
- **Media**: Manages media files
- **Enrollment**: Manages course enrollments
- **CourseInvitation**: Manages course invitations

### Policies

- **CoursePolicy**: Authorization for course actions
- **CourseSectionPolicy**: Authorization for course section actions
- **LessonPolicy**: Authorization for lesson actions

### Frontend Components

- **CourseCard**: Card for course list
- **CourseForm**: Shared form fields
- **CourseOutline**: Draggable accordion container
- **SectionItem**: Single section with lessons
- **LessonItem**: Single lesson row
- **ThumbnailUploader**: Image upload with preview
- **ObjectivesEditor**: Dynamic list input
- **CategorySelect**: Category dropdown
- **TagsInput**: Multi-select tags
- **DifficultySelect**: Difficulty dropdown
- **StatusBadge**: Status indicator
- **DurationDisplay**: Formatted duration

## Missing Features

### Lesson Progress Tracking

- **LessonProgress Model**: Not fully implemented
- **LessonProgressController**: Not fully implemented
- **Progress Calculation**: Not updating enrollment progress
- **UI**: Missing "Tandai Selesai" (Mark as Complete) button
- **Sidebar**: Not showing completed lessons with checkmark

### Learner Dashboard Enhancement

- **My Learning Section**: Missing enrolled courses with progress bars
- **Invited Courses Section**: Missing invited courses section
- **Continue Learning Feature**: Missing "Lanjutkan Belajar" (Continue Learning) button
- **Featured Courses Carousel**: Missing carousel of featured courses

### Course Rating System

- **CourseRating Migration**: Not created
- **CourseRating Model**: Not created
- **CourseRatingController**: Not created
- **UI**: Missing rating UI in course detail page
- **UI**: Missing ratings in course browse/listing

### Assessment Module (Foundation)

- **Assessment Migration**: Not created
- **Assessment Model**: Not created
- **AssessmentController**: Not created
- **Question Management**: Not created
- **Assessment Attempts**: Not created
- **Attempt Answers**: Not created

### Future Enhancements

- **Assessment Taking UI**: Not created
- **Auto-grading Logic**: Not created
- **Learning Path CRUD**: Not created
- **Learning Path Enrollment**: Not created
- **Competency Framework Models**: Not created
- **Grading Scale Management**: Not created
- **Certificate Templates**: Not created
- **Auto-certificate on Completion**: Not created
- **Experience Points System**: Not created
- **Badge Definitions**: Not created
- **Badge Awarding Logic**: Not created
- **Leaderboard**: Not created

## Summary

The "Enteraksi" project is a well-structured Laravel application with a clear separation of concerns. It includes controllers, models, policies, and services, along with frontend assets and configuration files. The project is designed to handle various functionalities such as user authentication, course management, and media handling. While significant progress has been made on core course management features, several key features such as lesson progress tracking, course rating, and assessment modules are still pending or incomplete.
