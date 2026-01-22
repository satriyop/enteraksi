<?php

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseVisibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visibility' => ['required', Rule::in(['public', 'restricted', 'hidden'])],
        ];
    }

    public function messages(): array
    {
        return [
            'visibility.required' => 'Visibilitas wajib diisi.',
            'visibility.in' => 'Visibilitas harus salah satu dari: public, restricted, atau hidden.',
        ];
    }
}
