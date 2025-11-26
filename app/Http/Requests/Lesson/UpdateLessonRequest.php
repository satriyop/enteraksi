<?php

namespace App\Http\Requests\Lesson;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateLessonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('lesson'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'content_type' => ['required', Rule::in(['text', 'video', 'audio', 'document', 'youtube', 'conference'])],
            'rich_content' => ['nullable', 'array'],
            'youtube_url' => ['nullable', 'url', 'regex:/^(https?\:\/\/)?(www\.)?(youtube\.com|youtu\.?be)\/.+$/'],
            'conference_url' => ['nullable', 'url'],
            'conference_type' => ['nullable', Rule::in(['zoom', 'google_meet', 'other'])],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_free_preview' => ['boolean'],
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
            'title.required' => 'Judul pelajaran wajib diisi.',
            'title.max' => 'Judul pelajaran maksimal 255 karakter.',
            'content_type.required' => 'Tipe konten wajib dipilih.',
            'content_type.in' => 'Tipe konten tidak valid.',
            'youtube_url.url' => 'URL YouTube tidak valid.',
            'youtube_url.regex' => 'URL harus merupakan link YouTube yang valid.',
            'conference_url.url' => 'URL konferensi tidak valid.',
            'conference_type.in' => 'Tipe konferensi tidak valid.',
            'estimated_duration_minutes.integer' => 'Durasi harus berupa angka.',
            'estimated_duration_minutes.min' => 'Durasi minimal 1 menit.',
        ];
    }
}
