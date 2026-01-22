<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SubmitAssessmentAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');
        $assessment = $this->route('assessment');
        $attempt = $this->route('attempt');

        return Gate::allows('submitAttempt', [$attempt, $assessment, $course]);
    }

    public function rules(): array
    {
        $assessment = $this->route('assessment');

        return [
            'answers' => ['required', 'array'],
            'answers.*.question_id' => [
                'required',
                'integer',
                Rule::exists('questions', 'id')->where('assessment_id', $assessment->id),
            ],
            'answers.*.answer_text' => 'nullable|string',
            'answers.*.selected_options' => 'nullable|array',
            'answers.*.selected_options.*' => 'integer',
            'answers.*.file' => 'nullable|file|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'answers.required' => 'Jawaban wajib diisi.',
            'answers.array' => 'Format jawaban tidak valid.',
            'answers.*.question_id.required' => 'ID pertanyaan wajib diisi.',
            'answers.*.question_id.integer' => 'ID pertanyaan harus berupa angka.',
            'answers.*.question_id.exists' => 'Pertanyaan tidak ditemukan dalam penilaian ini.',
            'answers.*.answer_text.string' => 'Jawaban harus berupa teks.',
            'answers.*.selected_options.array' => 'Opsi jawaban harus berupa array.',
            'answers.*.selected_options.*.integer' => 'ID opsi harus berupa angka.',
            'answers.*.file.file' => 'File tidak valid.',
            'answers.*.file.max' => 'File maksimal 10MB.',
        ];
    }
}
