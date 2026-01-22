<?php

namespace App\Http\Requests\CourseRating;

use App\Domain\Enrollment\DTOs\EnrollmentContext;
use App\Models\CourseRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreRatingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $course = $this->route('course');

        // Pre-fetch context for authorization
        $context = EnrollmentContext::for($user, $course);

        // Check if user already has a rating for this course
        $hasExistingRating = $user->courseRatings()
            ->where('course_id', $course->id)
            ->exists();

        return Gate::allows('create', [CourseRating::class, $course, $context, $hasExistingRating]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:1000'],
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
            'rating.required' => 'Rating wajib diisi.',
            'rating.integer' => 'Rating harus berupa angka.',
            'rating.min' => 'Rating minimal 1 bintang.',
            'rating.max' => 'Rating maksimal 5 bintang.',
            'review.max' => 'Ulasan maksimal 1000 karakter.',
        ];
    }
}
