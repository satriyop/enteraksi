<?php

namespace App\Http\Requests\LearningPath;

use Illuminate\Foundation\Http\FormRequest;

class ReorderPathCoursesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_order' => ['required', 'array'],
            'course_order.*.id' => ['required', 'exists:courses,id'],
            'course_order.*.position' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_order.required' => 'Urutan course wajib diisi.',
            'course_order.array' => 'Format urutan course tidak valid.',
            'course_order.*.id.required' => 'ID course wajib diisi.',
            'course_order.*.id.exists' => 'Course tidak ditemukan.',
            'course_order.*.position.required' => 'Posisi course wajib diisi.',
            'course_order.*.position.integer' => 'Posisi harus berupa angka.',
            'course_order.*.position.min' => 'Posisi tidak boleh negatif.',
        ];
    }
}
