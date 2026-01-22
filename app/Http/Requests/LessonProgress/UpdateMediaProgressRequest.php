<?php

namespace App\Http\Requests\LessonProgress;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'position_seconds' => ['required', 'integer', 'min:0'],
            'duration_seconds' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'position_seconds.required' => 'Posisi media wajib diisi.',
            'position_seconds.integer' => 'Posisi media harus berupa angka.',
            'position_seconds.min' => 'Posisi media tidak boleh negatif.',
            'duration_seconds.required' => 'Durasi media wajib diisi.',
            'duration_seconds.integer' => 'Durasi media harus berupa angka.',
            'duration_seconds.min' => 'Durasi media harus minimal 1 detik.',
        ];
    }
}
