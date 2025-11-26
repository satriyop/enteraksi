<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->canManageCourses();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $collection = $this->input('collection_name', 'default');

        $fileRules = ['required', 'file'];

        // Add specific validation based on collection type
        $fileRules[] = match ($collection) {
            'video' => 'mimes:mp4,webm,mov,avi,mkv|max:524288', // 512MB for video
            'audio' => 'mimes:mp3,wav,ogg,m4a,aac|max:102400', // 100MB for audio
            'document' => 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx|max:51200', // 50MB for documents
            'thumbnail' => 'mimes:jpg,jpeg,png,webp|max:5120', // 5MB for thumbnails
            default => 'max:102400', // 100MB default
        };

        return [
            'file' => $fileRules,
            'mediable_type' => ['required', 'string', Rule::in(['course', 'lesson'])],
            'mediable_id' => ['required', 'integer'],
            'collection_name' => ['nullable', 'string', Rule::in(['default', 'video', 'audio', 'document', 'thumbnail'])],
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
            'file.required' => 'File wajib diunggah.',
            'file.file' => 'File tidak valid.',
            'file.max' => 'Ukuran file terlalu besar.',
            'file.mimes' => 'Format file tidak didukung.',
            'mediable_type.required' => 'Tipe media wajib diisi.',
            'mediable_type.in' => 'Tipe media tidak valid.',
            'mediable_id.required' => 'ID media wajib diisi.',
            'mediable_id.integer' => 'ID media harus berupa angka.',
            'collection_name.in' => 'Koleksi media tidak valid.',
        ];
    }
}
