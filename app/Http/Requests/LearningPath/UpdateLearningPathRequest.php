<?php

namespace App\Http\Requests\LearningPath;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLearningPathRequest extends FormRequest
{
    public function authorize(): bool
    {
        $learningPath = $this->route('learning_path');

        return $this->user()->can('update', $learningPath);
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'objectives' => 'nullable|array',
            'objectives.*' => 'string',
            'estimated_duration' => 'nullable|integer|min:1',
            'difficulty_level' => 'nullable|string|in:beginner,intermediate,advanced,expert',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'courses' => 'required|array|min:1',
            'courses.*.id' => 'required|exists:courses,id',
            'courses.*.is_required' => 'boolean',
            'courses.*.prerequisites' => 'nullable|array',
            'courses.*.prerequisites.completed_courses' => 'nullable|array',
            'courses.*.prerequisites.completed_courses.*' => 'integer',
            'courses.*.min_completion_percentage' => 'nullable|integer|min:1|max:100',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $this->validatePrerequisitePositions($validator);
        });
    }

    /**
     * Validate that prerequisite courses come before the current course.
     */
    protected function validatePrerequisitePositions(\Illuminate\Validation\Validator $validator): void
    {
        $courses = $this->input('courses', []);

        // Build course ID to position map
        $coursePositions = [];
        foreach ($courses as $index => $course) {
            if (isset($course['id'])) {
                $coursePositions[$course['id']] = $index;
            }
        }

        // Validate each course's prerequisites
        foreach ($courses as $index => $course) {
            $prerequisites = $course['prerequisites']['completed_courses'] ?? [];

            foreach ($prerequisites as $prereqId) {
                // Check if prerequisite exists in the learning path
                if (! isset($coursePositions[$prereqId])) {
                    $validator->errors()->add(
                        "courses.{$index}.prerequisites",
                        "Kursus prasyarat dengan ID {$prereqId} tidak ada dalam learning path ini."
                    );

                    continue;
                }

                // Check if prerequisite comes BEFORE current course
                if ($coursePositions[$prereqId] >= $index) {
                    $validator->errors()->add(
                        "courses.{$index}.prerequisites",
                        'Kursus prasyarat harus berada di posisi sebelum kursus ini.'
                    );
                }
            }
        }
    }

    public function messages(): array
    {
        return [
            'courses.required' => 'A learning path must contain at least one course.',
            'courses.min' => 'A learning path must contain at least one course.',
        ];
    }
}
