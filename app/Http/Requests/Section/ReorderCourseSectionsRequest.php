<?php

namespace App\Http\Requests\Section;

use Illuminate\Foundation\Http\FormRequest;

class ReorderCourseSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sections' => ['required', 'array'],
            'sections.*' => ['required', 'integer', 'exists:course_sections,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'sections.required' => 'Daftar section wajib diisi.',
            'sections.array' => 'Format daftar section tidak valid.',
            'sections.*.required' => 'ID section wajib diisi.',
            'sections.*.integer' => 'ID section harus berupa angka.',
            'sections.*.exists' => 'Section tidak ditemukan.',
        ];
    }
}
