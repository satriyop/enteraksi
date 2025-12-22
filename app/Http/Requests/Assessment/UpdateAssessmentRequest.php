<?php
namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $course     = $this->route('course');
        $assessment = $this->route('assessment');
        return Gate::allows('update', [$assessment, $course]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'                => ['sometimes', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'instructions'         => ['nullable', 'string'],
            'time_limit_minutes'   => ['nullable', 'integer', 'min:1', 'max:360'],
            'passing_score'        => ['sometimes', 'integer', 'min:0', 'max:100'],
            'max_attempts'         => ['sometimes', 'integer', 'min:1', 'max:10'],
            'shuffle_questions'    => ['sometimes', 'boolean'],
            'show_correct_answers' => ['sometimes', 'boolean'],
            'allow_review'         => ['sometimes', 'boolean'],
            'status'               => ['sometimes', Rule::in(['draft', 'published', 'archived'])],
            'visibility'           => ['sometimes', Rule::in(['public', 'restricted', 'hidden'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.string'           => 'Judul penilaian harus berupa teks.',
            'title.max'              => 'Judul penilaian maksimal 255 karakter.',
            'passing_score.min'      => 'Nilai kelulusan minimal 0.',
            'passing_score.max'      => 'Nilai kelulusan maksimal 100.',
            'max_attempts.min'       => 'Jumlah percobaan maksimal minimal 1.',
            'max_attempts.max'       => 'Jumlah percobaan maksimal maksimal 10.',
            'time_limit_minutes.min' => 'Batas waktu minimal 1 menit.',
            'time_limit_minutes.max' => 'Batas waktu maksimal 360 menit (6 jam).',
            'status.in'              => 'Status tidak valid.',
            'visibility.in'          => 'Visibilitas tidak valid.',
        ];
    }
}