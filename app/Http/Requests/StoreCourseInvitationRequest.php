<?php

namespace App\Http\Requests;

use App\Models\CourseInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseInvitationRequest extends FormRequest
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
        $course = $this->route('course');

        return [
            'user_id' => [
                'required',
                'exists:users,id',
                // User must be a learner
                Rule::exists('users', 'id')->where('role', 'learner'),
                // Cannot invite already enrolled users
                function ($attribute, $value, $fail) use ($course) {
                    $isEnrolled = $course->enrollments()
                        ->where('user_id', $value)
                        ->where('status', 'active')
                        ->exists();

                    if ($isEnrolled) {
                        $fail('Pengguna sudah terdaftar di kursus ini.');
                    }
                },
                // Cannot have duplicate pending invitation
                function ($attribute, $value, $fail) use ($course) {
                    $hasPendingInvitation = CourseInvitation::where('user_id', $value)
                        ->where('course_id', $course->id)
                        ->where('status', 'pending')
                        ->exists();

                    if ($hasPendingInvitation) {
                        $fail('Pengguna sudah memiliki undangan yang menunggu.');
                    }
                },
            ],
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
            'user_id.required' => 'Pilih pengguna yang akan diundang.',
            'user_id.exists' => 'Pengguna tidak ditemukan atau bukan learner.',
            'message.max' => 'Pesan maksimal 500 karakter.',
            'expires_at.after' => 'Tanggal kadaluarsa harus setelah hari ini.',
        ];
    }
}
