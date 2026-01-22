<?php

namespace App\Http\Requests\Question;

use Illuminate\Foundation\Http\FormRequest;

class DeleteQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'question_ids.required' => 'Daftar ID pertanyaan wajib diisi.',
            'question_ids.array' => 'Format daftar ID tidak valid.',
            'question_ids.min' => 'Minimal harus ada 1 pertanyaan yang dihapus.',
            'question_ids.*.required' => 'ID pertanyaan wajib diisi.',
            'question_ids.*.integer' => 'ID pertanyaan harus berupa angka.',
        ];
    }
}
