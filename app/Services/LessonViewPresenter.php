<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Support\Collection;

class LessonViewPresenter
{
    /**
     * Get lesson view data with navigation.
     *
     * @return array{
     *     lessonProgress: \App\Models\LessonProgress|null,
     *     prevLesson: array|null,
     *     nextLesson: array|null,
     *     allLessons: Collection
     * }
     */
    public function getLessonViewData(
        Course $course,
        Lesson $lesson,
        ?Enrollment $enrollment
    ): array {
        $lessonProgress = null;
        $lessonProgressMap = [];

        if ($enrollment) {
            $lessonProgress = $enrollment->getProgressForLesson($lesson);
            $lessonProgressMap = $enrollment->lessonProgress()
                ->where('is_completed', true)
                ->pluck('is_completed', 'lesson_id')
                ->toArray();
        }

        $allLessons = $this->buildLessonNavigation($course, $lessonProgressMap);

        $currentIndex = $allLessons->search(fn ($l) => $l['id'] === $lesson->id);
        $prevLesson = $currentIndex > 0 ? $allLessons[$currentIndex - 1] : null;
        $nextLesson = $currentIndex < $allLessons->count() - 1 ? $allLessons[$currentIndex + 1] : null;

        return [
            'lessonProgress' => $lessonProgress,
            'prevLesson' => $prevLesson,
            'nextLesson' => $nextLesson,
            'allLessons' => $allLessons,
        ];
    }

    /**
     * Build lesson navigation array with completion status.
     *
     * @param  array<int, bool>  $completedLessonIds
     */
    protected function buildLessonNavigation(Course $course, array $completedLessonIds): Collection
    {
        $allLessons = collect();

        foreach ($course->sections as $section) {
            foreach ($section->lessons as $l) {
                $allLessons->push([
                    'id' => $l->id,
                    'title' => $l->title,
                    'section_title' => $section->title,
                    'order' => $section->order * 1000 + $l->order,
                    'is_completed' => isset($completedLessonIds[$l->id]),
                ]);
            }
        }

        return $allLessons->sortBy('order')->values();
    }
}
