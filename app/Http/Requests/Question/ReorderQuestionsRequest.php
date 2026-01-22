<?php

namespace App\Http\Requests\Question;

use Illuminate\Foundation\Http\FormRequest;

class ReorderQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'questions' => ['required', 'array'],
            'questions.*.id' => ['sometimes', 'integer', 'nullable'],
            'questions.*.position' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'questions.required' => 'Daftar pertanyaan wajib diisi.',
            'questions.array' => 'Format daftar pertanyaan tidak valid.',
            'questions.*.id.integer' => 'ID pertanyaan harus berupa angka.',
            'questions.*.position.required' => 'Posisi pertanyaan wajib diisi.',
            'questions.*.position.integer' => 'Posisi harus berupa angka.',
            'questions.*.position.min' => 'Posisi tidak boleh negatif.',
        ];
    }
}
