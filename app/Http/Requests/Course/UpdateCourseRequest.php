<?php

namespace App\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('course'));
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
            'short_description' => ['nullable', 'string', 'max:500'],
            'long_description' => ['nullable', 'string'],
            'objectives' => ['nullable', 'array'],
            'objectives.*' => ['string', 'max:500'],
            'prerequisites' => ['nullable', 'array'],
            'prerequisites.*' => ['string', 'max:500'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'difficulty_level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'visibility' => ['required', Rule::in(['public', 'restricted', 'hidden'])],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            'manual_duration_minutes' => ['nullable', 'integer', 'min:1'],
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
            'title.required' => 'Judul kursus wajib diisi.',
            'title.max' => 'Judul kursus maksimal 255 karakter.',
            'short_description.max' => 'Deskripsi singkat maksimal 500 karakter.',
            'objectives.*.max' => 'Setiap tujuan pembelajaran maksimal 500 karakter.',
            'prerequisites.*.max' => 'Setiap prasyarat maksimal 500 karakter.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid.',
            'thumbnail.image' => 'Thumbnail harus berupa gambar.',
            'thumbnail.mimes' => 'Format thumbnail harus jpeg, png, jpg, atau webp.',
            'thumbnail.max' => 'Ukuran thumbnail maksimal 2MB.',
            'difficulty_level.required' => 'Tingkat kesulitan wajib dipilih.',
            'difficulty_level.in' => 'Tingkat kesulitan tidak valid.',
            'visibility.required' => 'Visibilitas wajib dipilih.',
            'visibility.in' => 'Visibilitas tidak valid.',
            'tags.*.exists' => 'Tag yang dipilih tidak valid.',
            'manual_duration_minutes.integer' => 'Durasi harus berupa angka.',
            'manual_duration_minutes.min' => 'Durasi minimal 1 menit.',
        ];
    }
}
