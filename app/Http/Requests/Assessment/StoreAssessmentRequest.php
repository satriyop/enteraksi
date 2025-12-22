<?php
namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $course = $this->route('course');
        return Gate::allows('create', [\App\Models\Assessment::class, $course]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'                => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'instructions'         => ['nullable', 'string'],
            'time_limit_minutes'   => ['nullable', 'integer', 'min:1', 'max:360'],
            'passing_score'        => ['required', 'integer', 'min:0', 'max:100'],
            'max_attempts'         => ['required', 'integer', 'min:1', 'max:10'],
            'shuffle_questions'    => ['required', 'boolean'],
            'show_correct_answers' => ['required', 'boolean'],
            'allow_review'         => ['required', 'boolean'],
            'status'               => ['required', Rule::in(['draft', 'published', 'archived'])],
            'visibility'           => ['required', Rule::in(['public', 'restricted', 'hidden'])],
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
            'title.required'         => 'Judul penilaian wajib diisi.',
            'title.max'              => 'Judul penilaian maksimal 255 karakter.',
            'passing_score.required' => 'Nilai kelulusan wajib diisi.',
            'passing_score.min'      => 'Nilai kelulusan minimal 0.',
            'passing_score.max'      => 'Nilai kelulusan maksimal 100.',
            'max_attempts.required'  => 'Jumlah percobaan maksimal wajib diisi.',
            'max_attempts.min'       => 'Jumlah percobaan maksimal minimal 1.',
            'max_attempts.max'       => 'Jumlah percobaan maksimal maksimal 10.',
            'time_limit_minutes.min' => 'Batas waktu minimal 1 menit.',
            'time_limit_minutes.max' => 'Batas waktu maksimal 360 menit (6 jam).',
            'status.required'        => 'Status wajib dipilih.',
            'status.in'              => 'Status tidak valid.',
            'visibility.required'    => 'Visibilitas wajib dipilih.',
            'visibility.in'          => 'Visibilitas tidak valid.',
        ];
    }
}