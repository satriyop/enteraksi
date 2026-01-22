<?php

namespace App\Http\Requests\LessonProgress;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaginationProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_page' => ['required', 'integer', 'min:1'],
            'total_pages' => ['nullable', 'integer', 'min:1'],
            'pagination_metadata' => ['nullable', 'array'],
            'time_spent_seconds' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_page.required' => 'Nomor halaman saat ini wajib diisi.',
            'current_page.integer' => 'Nomor halaman harus berupa angka.',
            'current_page.min' => 'Nomor halaman harus minimal 1.',
            'total_pages.integer' => 'Total halaman harus berupa angka.',
            'total_pages.min' => 'Total halaman harus minimal 1.',
            'pagination_metadata.array' => 'Metadata pagination harus berupa array.',
            'time_spent_seconds.numeric' => 'Waktu harus berupa angka.',
            'time_spent_seconds.min' => 'Waktu tidak boleh negatif.',
        ];
    }
}
