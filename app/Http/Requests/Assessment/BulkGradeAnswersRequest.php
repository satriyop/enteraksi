<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkGradeAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attempt = $this->route('attempt');

        return [
            'grades' => ['required', 'array'],
            'grades.*.answer_id' => [
                'required',
                'integer',
                Rule::exists('attempt_answers', 'id')->where('attempt_id', $attempt->id),
            ],
            'grades.*.score' => ['required', 'numeric', 'min:0'],
            'grades.*.feedback' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'grades.required' => 'Nilai wajib diisi.',
            'grades.array' => 'Format nilai tidak valid.',
            'grades.*.answer_id.required' => 'ID jawaban wajib diisi.',
            'grades.*.answer_id.integer' => 'ID jawaban harus berupa angka.',
            'grades.*.answer_id.exists' => 'Jawaban tidak ditemukan dalam attempt ini.',
            'grades.*.score.required' => 'Nilai wajib diisi.',
            'grades.*.score.numeric' => 'Nilai harus berupa angka.',
            'grades.*.score.min' => 'Nilai tidak boleh kurang dari 0.',
            'grades.*.feedback.max' => 'Feedback maksimal 1000 karakter.',
        ];
    }
}
