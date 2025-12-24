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
        $learningPath = $this->route('learning_path');

        return [
            'title'                               => 'required|string|max:255',
            'description'                         => 'nullable|string',
            'objectives'                          => 'nullable|array',
            'objectives.*'                        => 'string',
            'estimated_duration'                  => 'nullable|integer|min:1',
            'difficulty_level'                    => 'nullable|string|in:beginner,intermediate,advanced,expert',
            'thumbnail'                           => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'courses'                             => 'required|array|min:1',
            'courses.*.id'                        => 'required|exists:courses,id',
            'courses.*.is_required'               => 'boolean',
            'courses.*.prerequisites'             => 'nullable|string',
            'courses.*.min_completion_percentage' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'courses.required' => 'A learning path must contain at least one course.',
            'courses.min'      => 'A learning path must contain at least one course.',
        ];
    }
}