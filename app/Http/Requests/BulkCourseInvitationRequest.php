<?php

namespace App\Http\Requests;

use App\Models\CourseInvitation;
use Illuminate\Foundation\Http\FormRequest;

class BulkCourseInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $this->user()->can('create', [CourseInvitation::class, $course]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'message' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file CSV untuk diimpor.',
            'file.mimes' => 'File harus berformat CSV.',
            'file.max' => 'Ukuran file maksimal 2MB.',
            'message.max' => 'Pesan maksimal 500 karakter.',
            'expires_at.after' => 'Tanggal kadaluarsa harus setelah hari ini.',
        ];
    }
}
