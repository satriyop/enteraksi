<?php
namespace App\Http\Requests\Question;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
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
            'question_text'        => ['required', 'string'],
            'question_type'        => ['required', Rule::in(['multiple_choice', 'true_false', 'matching', 'short_answer', 'essay', 'file_upload'])],
            'points'               => ['required', 'integer', 'min:1'],
            'feedback'             => ['nullable', 'string'],
            'order'                => ['nullable', 'integer', 'min:0'],
            'options'              => ['required_if:question_type,multiple_choice,true_false', 'array'],
            'options.*.text'       => ['required', 'string'],
            'options.*.is_correct' => ['required', 'boolean'],
            'options.*.feedback'   => ['nullable', 'string'],
            'options.*.order'      => ['nullable', 'integer', 'min:0'],
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
            'question_text.required'        => 'Teks pertanyaan wajib diisi.',
            'question_type.required'        => 'Tipe pertanyaan wajib dipilih.',
            'question_type.in'              => 'Tipe pertanyaan tidak valid.',
            'points.required'               => 'Poin wajib diisi.',
            'points.min'                    => 'Poin minimal 1.',
            'options.required_if'           => 'Opsi wajib diisi untuk tipe pertanyaan ini.',
            'options.*.text.required'       => 'Teks opsi wajib diisi.',
            'options.*.is_correct.required' => 'Status kebeneran opsi wajib diisi.',
        ];
    }
}