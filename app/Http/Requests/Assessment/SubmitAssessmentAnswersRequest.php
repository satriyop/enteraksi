<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAssessmentAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer_id' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'answers.required' => 'Jawaban wajib diisi.',
            'answers.array' => 'Format jawaban tidak valid.',
            'answers.*.question_id.required' => 'ID pertanyaan wajib diisi.',
            'answers.*.question_id.integer' => 'ID pertanyaan harus berupa angka.',
            'answers.*.answer_id.required' => 'ID jawaban wajib diisi.',
            'answers.*.answer_id.integer' => 'ID jawaban harus berupa angka.',
        ];
    }
}
