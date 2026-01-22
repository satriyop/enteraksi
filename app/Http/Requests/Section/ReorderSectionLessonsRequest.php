<?php

namespace App\Http\Requests\Section;

use Illuminate\Foundation\Http\FormRequest;

class ReorderSectionLessonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lessons' => ['required', 'array'],
            'lessons.*' => ['required', 'integer', 'exists:lessons,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'lessons.required' => 'Daftar lesson wajib diisi.',
            'lessons.array' => 'Format daftar lesson tidak valid.',
            'lessons.*.required' => 'ID lesson wajib diisi.',
            'lessons.*.integer' => 'ID lesson harus berupa angka.',
            'lessons.*.exists' => 'Lesson tidak ditemukan.',
        ];
    }
}
