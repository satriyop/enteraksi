<?php

namespace App\Http\Requests\Question;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BulkUpdateQuestionsRequest extends FormRequest
{
    private array $validQuestionTypes = [
        'multiple_choice',
        'true_false',
        'matching',
        'short_answer',
        'essay',
        'file_upload',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $course = $this->route('course');
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
            'questions' => ['required', 'array'],
            'questions.*.id' => [
                'sometimes',
                'integer',
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value !== 0 && ! \App\Models\Question::where('id', $value)->exists()) {
                        $fail('The selected '.$attribute.' is invalid.');
                    }
                },
            ],
            'questions.*.question_text' => ['required', 'string'],
            'questions.*.question_type' => ['required', 'string', Rule::in($this->validQuestionTypes)],
            'questions.*.points' => ['required', 'integer', 'min:1'],
            'questions.*.feedback' => ['nullable', 'string'],
            'questions.*.order' => ['sometimes', 'integer', 'min:0'],
            'questions.*.options' => ['sometimes', 'array', $this->optionsRule()],
            'questions.*.options.*.id' => ['sometimes', 'integer'],
            'questions.*.options.*.option_text' => ['required', 'string'],
            'questions.*.options.*.is_correct' => ['required', 'boolean'],
            'questions.*.options.*.feedback' => ['nullable', 'string'],
            'questions.*.options.*.order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Get validation rule for options based on question type.
     */
    protected function optionsRule(): \Illuminate\Contracts\Validation\ValidationRule
    {
        return new class($this) implements \Illuminate\Contracts\Validation\ValidationRule
        {
            private $request;

            public function __construct($request)
            {
                $this->request = $request;
            }

            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                preg_match('/questions\.(\d+)\.options/', $attribute, $matches);
                $index = $matches[1] ?? null;

                if ($index === null) {
                    return;
                }

                $questionType = $this->request->input("questions.{$index}.question_type");

                if (in_array($questionType, ['multiple_choice', 'matching'])) {
                    if (! is_array($value) || count($value) < 2) {
                        $fail('Pertanyaan pilihan ganda dan pencocokan memerlukan minimal 2 opsi.');
                    }
                }
            }
        };
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'questions.required' => 'Data pertanyaan wajib diisi.',
            'questions.*.id.exists' => 'Pertanyaan tidak valid.',
            'questions.*.question_text.required' => 'Teks pertanyaan wajib diisi.',
            'questions.*.question_type.required' => 'Tipe pertanyaan wajib dipilih.',
            'questions.*.question_type.in' => 'Tipe pertanyaan tidak valid.',
            'questions.*.points.required' => 'Poin wajib diisi.',
            'questions.*.points.min' => 'Poin minimal 1.',
            'questions.*.options.*.option_text.required' => 'Teks opsi wajib diisi.',
            'questions.*.options.*.is_correct.required' => 'Status kebenaran opsi wajib diisi.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $questionsData = $this->input('questions');

        if (is_string($questionsData)) {
            $decoded = json_decode($questionsData, true);
            if ($decoded !== null) {
                $this->merge(['questions' => $decoded]);
            }
        }
    }
}
